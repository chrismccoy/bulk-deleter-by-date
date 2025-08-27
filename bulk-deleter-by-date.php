<?php
/**
 * Plugin Name:       Bulk Deleter by Date
 * Plugin URI:        https://github.com/chrismccoy/
 * Description:       Deletes comments or attachments within a specified date range.
 * Version:           1.0
 * Author:            Chris McCoy
 * Author URI:        https://github.com/chrismccoy
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bulk-deleter-by-date
 * Requires PHP:      7.4
 *
 * @package Bulk_Deleter_By_Date
 */

// Prevent direct file access for security.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class for the Bulk Deleter by Date.
 *
 * It handles the initialization of hooks, admin page rendering, script
 * enqueueing, and AJAX request processing for deleting items.
 *
 */
final class Bulk_Deleter_By_Date {

	/**
	 * The plugin version number.
	 *
	 */
	private const VERSION = '2.2.0';

	/**
	 * The menu slug for the admin page.
	 *
	 */
	private const MENU_SLUG = 'bulk-deleter-by-date';

	/**
	 * The nonce action name for security checks.
	 *
	 */
	private const NONCE_ACTION = 'bdd_delete_nonce_action';

	/**
	 * The AJAX action name for handling the delete request.
	 *
	 */
	private const AJAX_ACTION = 'bdd_delete_items';

	/**
	 * Class constructor.
	 *
	 * Initializes the plugin by setting up the primary action hook.
	 *
	 */
	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	/**
	 * Registers all necessary WordPress action hooks.
	 *
	 * This method is hooked into `plugins_loaded` and is responsible for
	 * setting up the admin menu, script enqueueing, and the AJAX endpoint.
	 *
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'handle_ajax_delete_request' ] );
	}

	/**
	 * Adds the plugin's admin page as a submenu under 'Tools'.
	 *
	 * Uses `add_management_page()` to create the interface page.
	 *
	 */
	public function add_admin_menu_page(): void {
		add_management_page(
			__( 'Bulk Deleter by Date', 'bulk-deleter-by-date' ),
			__( 'Bulk Deleter', 'bulk-deleter-by-date' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Renders the HTML markup and inline JavaScript for the admin page.
	 *
	 * This method outputs the complete user interface, including the form for
	 * selecting content type and date range, the confirmation dialog, and the
	 * deletion log area. The inline JavaScript handles all client-side
	 * interactions, date picker initialization, and the AJAX call.
	 *
	 */
	public function render_admin_page(): void {
		?>
		<!-- Main wrapper for the Bulk Deleter admin page -->
		<div class="wrap">
			<h1><?php esc_html_e( 'Bulk Deleter by Date Range', 'bulk-deleter-by-date' ); ?></h1>

			<!-- Dynamic notices (success/error messages) will be inserted here by JavaScript -->
			<div id="bdd-notices-wrapper"></div>

			<p><?php esc_html_e( 'Select a content type, a start date, and an end date to permanently delete all items within that range (inclusive).', 'bulk-deleter-by-date' ); ?></p>
			<p><strong><?php esc_html_e( 'Warning: This action is irreversible and will bypass the trash.', 'bulk-deleter-by-date' ); ?></strong></p>

			<!-- Main settings form -->
			<form id="bdd-form" class="bdd-form">
				<table class="form-table" role="presentation">
					<tbody>
						<!-- Combined Type and Date controls into a single table row to prevent layout shifts. -->
						<tr>
							<td>
								<p>
									<label for="bdd-delete-type" style="display: block; margin-bottom: 5px;"><strong><?php esc_html_e( 'Select Content Type', 'bulk-deleter-by-date' ); ?></strong></label>
									<select name="delete_type" id="bdd-delete-type">
										<option value=""><?php esc_html_e( '-- Select Type --', 'bulk-deleter-by-date' ); ?></option>
										<option value="comments"><?php esc_html_e( 'Comments', 'bulk-deleter-by-date' ); ?></option>
										<option value="attachments"><?php esc_html_e( 'Attachments (Media)', 'bulk-deleter-by-date' ); ?></option>
									</select>
								</p>

								<!-- This container holds the date pickers and is hidden by default. -->
								<div id="bdd-date-picker-wrapper" style="display:none;">
									<p style="margin-top: 20px; margin-bottom: 20px;">
										<strong><?php esc_html_e( 'Select Date Range', 'bulk-deleter-by-date' ); ?></strong>
									</p>
									<div class="bdd-flex-container">
										<div class="bdd-date-field-wrapper">
											<div id="bdd-start-date-container"></div>
											<input type="hidden" id="bdd-start-date" name="start_date">
										</div>
										<div class="bdd-date-field-wrapper">
											<div id="bdd-end-date-container"></div>
											<input type="hidden" id="bdd-end-date" name="end_date">
										</div>
									</div>
								</div>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Form submission button -->
				<p class="submit">
					<?php submit_button( __( 'Delete Items', 'bulk-deleter-by-date' ), 'primary', 'bdd-submit', false, [ 'id' => 'bdd-submit-button' ] ); ?>
				</p>
			</form>
			<!-- End of main settings form -->

			<!-- Confirmation dialog area. Hidden by default. JavaScript will populate and show this. -->
			<div id="bdd-confirmation-area" class="notice notice-warning inline" style="display:none;"></div>

			<!-- Deletion log container. Hidden by default. JavaScript will populate and show this after a successful deletion. -->
			<div id="bdd-log-container" style="display:none;">
				<button type="button" class="notice-dismiss bdd-dismiss-log"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'bulk-deleter-by-date' ); ?></span></button>
				<h2><?php esc_html_e( 'Deletion Log', 'bulk-deleter-by-date' ); ?></h2>
				<!-- The results table will be injected here -->
				<div id="bdd-log-results"></div>
			</div>

			<!-- Inline styles for the admin page layout -->
			<style>
				/* Styles the container for the date pickers, placing them side-by-side. */
				.bdd-flex-container { display: flex; align-items: flex-start; gap: 20px; }

				/* Styles the labels for the individual date picker calendars. */
				.bdd-date-field-wrapper label { display: block; margin-bottom: 5px; font-weight: 600; text-align:center; }

				/* Styles the main container for the deletion log, adding spacing and a top border. */
				#bdd-log-container { position: relative; padding-top: 10px; border-top: 1px solid #c3c4c7; margin-top: 20px; }

				/* Sets a maximum width for the confirmation dialog to keep it tidy. */
				#bdd-confirmation-area { max-width: 500px;}

				/* Constrains the size of attachment preview thumbnails in the deletion log table. */
				.bdd-log-thumb { max-width: 60px; max-height: 60px; }
			</style>

			<!-- Inline JavaScript for client-side functionality -->
			<script>
				jQuery(document).ready(function ($) {
					/**
					 * Handles UI interactions, date picker initialization, form validation,
					 * and the AJAX request for deleting items.
					 */

					/**
					 * Escapes HTML special characters in a string to prevent XSS.
					 */
					function escapeHtml(str) {
						return $("<div/>").text(str).html();
					}

					// Caching jQuery selectors for performance and readability.
					const form = $("#bdd-form");
					const deleteTypeSelect = $("#bdd-delete-type");
					const datePickerWrapper = $("#bdd-date-picker-wrapper");
					const startDateContainer = $("#bdd-start-date-container");
					const endDateContainer = $("#bdd-end-date-container");
					const startDateInput = $("#bdd-start-date");
					const endDateInput = $("#bdd-end-date");
					const formSubmitButton = $("#bdd-form .submit");
					const confirmationArea = $("#bdd-confirmation-area");
					const logResultsContainer = $("#bdd-log-results");
					const logSectionContainer = $("#bdd-log-container");
					const noticesWrapper = $("#bdd-notices-wrapper");

					/**
					 * Initializes or re-initializes an inline Calentim date picker instance.
					 * Destroys any existing instance before creating a new one.
					 */
					function initializeInlineCalendar(containerEl, targetInputEl) {
						if (containerEl.data("calentim")) containerEl.data("calentim").destroy();
						containerEl.calentim({
							startEmpty: true, singleDate: true, showTimePickers: false,
							showHeader: false, showFooter: false, inline: true,
							calendarCount: 1, format: "YYYY-MM-DD", autoCloseOnSelect: true,
							showInput: false, target: targetInputEl,
						});
					}

					// Initialize calendars on page load.
					initializeInlineCalendar(startDateContainer, startDateInput);
					initializeInlineCalendar(endDateContainer, endDateInput);

					/**
					 * Resets the form and UI elements to their initial state.
					 * Clears selected values, hides the date wrapper, and re-initializes the calendars.
					 */
					function resetInterface() {
						deleteTypeSelect.val("");
						startDateInput.val("");
						endDateInput.val("");
						datePickerWrapper.hide(); // Hide the date wrapper on reset.
						initializeInlineCalendar(startDateContainer, startDateInput);
						initializeInlineCalendar(endDateContainer, endDateInput);
					}

					/**
					 * Handles the change event on the content type dropdown.
					 * Shows or hides the date picker wrapper based on whether a valid type is selected.
					 */
					deleteTypeSelect.on('change', function() {
						if ($(this).val()) {
							datePickerWrapper.fadeIn();
						} else {
							datePickerWrapper.fadeOut();
						}
					});

					/**
					 * Handles the main form submission.
					 * Prevents default submission, validates inputs, and shows the confirmation dialog.
					 */
					form.on("submit", function (e) {
						e.preventDefault();
						noticesWrapper.empty();

						if (!deleteTypeSelect.val() || !startDateInput.val() || !endDateInput.val()) {
							const validationNotice = $('<div class="notice notice-error is-dismissible inline"><p>Please select a content type, start date, and end date.</p></div>');
							noticesWrapper.append(validationNotice);
							$(document).trigger("wp-updates-notice-added");
							return;
						}

						const typeLabel = deleteTypeSelect.find("option:selected").text();
						const confirmationHtml = '<p><strong>Are you sure you want to permanently delete all ' + escapeHtml(typeLabel) + ' in the selected range?</strong></p>' +
							'<p><button type="button" id="bdd-confirm-yes" class="button button-primary">Yes, Delete</button> ' +
							'<button type="button" id="bdd-confirm-no" class="button button-secondary">No, Cancel</button></p>';
						confirmationArea.html(confirmationHtml).show();
						formSubmitButton.hide();
					});

					/**
					 * Handles the 'No, Cancel' button click in the confirmation dialog.
					 * Hides the confirmation area and restores the form.
					 */
					confirmationArea.on("click", "#bdd-confirm-no", function () {
						confirmationArea.hide().empty();
						formSubmitButton.show();
						resetInterface();
					});

					/**
					 * Handles the 'Yes, Delete' button click.
					 * Disables buttons, prepares the UI, and initiates the AJAX request to the backend.
					 */
					confirmationArea.on("click", "#bdd-confirm-yes", function () {
						const confirmYesButton = $(this);
						const confirmNoButton = $("#bdd-confirm-no");

						confirmYesButton.prop("disabled", true).text("Deleting...");
						confirmNoButton.prop("disabled", true);
						noticesWrapper.empty();
						logSectionContainer.hide();
						logResultsContainer.html("<p>Processing...</p>");

						$.ajax({
							url: ajaxurl,
							type: "POST",
							data: {
								action: "<?php echo esc_js( self::AJAX_ACTION ); ?>",
								nonce: "<?php echo esc_js( wp_create_nonce( self::NONCE_ACTION ) ); ?>",
								delete_type: deleteTypeSelect.val(),
								start_date: startDateInput.val(),
								end_date: endDateInput.val(),
							},
							/**
							 * Handles a successful AJAX response.
							 */
							success: function (response) {
								const noticeClass = response.success ? "notice-success" : "notice-error";
								const ajaxNotice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + escapeHtml(response.data.message) + "</p></div>");
								noticesWrapper.append(ajaxNotice);
								$(document).trigger("wp-updates-notice-added");

								if (response.success && response.data.deleted_items && response.data.deleted_items.length > 0) {
									let logHtml = '<table class="wp-list-table widefat striped">';
									logHtml += "<thead><tr>";
									$.each(response.data.log_headers, function(i, header) {
										logHtml += "<th>" + escapeHtml(header) + "</th>";
									});
									logHtml += "</tr></thead><tbody>";
									$.each(response.data.deleted_items, function (i, row) {
										logHtml += "<tr>";
										$.each(row, function(j, cell) {
											// The first cell might be HTML (e.g., thumbnail), so don't escape it.
											logHtml += "<td>" + (j === 0 ? cell : escapeHtml(cell)) + "</td>";
										});
										logHtml += "</tr>";
									});
									logHtml += "</tbody></table>";
									logResultsContainer.html(logHtml);
									logSectionContainer.show();
								} else {
									logResultsContainer.empty();
								}
							},
							/**
							 * Handles an AJAX error (e.g., network issue, server 500 error).
							 */
							error: function () {
								const errorNotice = $('<div class="notice notice-error is-dismissible"><p>An unexpected error occurred.</p></div>');
								noticesWrapper.append(errorNotice);
								$(document).trigger("wp-updates-notice-added");
							},
							/**
							 * Executes after the AJAX request completes, regardless of success or error.
							 * Resets the UI to its initial state.
							 */
							complete: function () {
								confirmationArea.hide().empty();
								formSubmitButton.show();
								resetInterface();
							},
						});
					});

					/**
					 * Handles the dismissal of the deletion log container.
					 */
					$("body").on("click", ".bdd-dismiss-log", function (e) {
						e.preventDefault();
						$(this).closest("#bdd-log-container").hide();
					});
				});
			</script>
		</div>
		<!-- End of the Bulk Deleter admin page wrapper -->
		<?php
	}

	/**
	 * Enqueues scripts and styles for the admin page.
	 *
	 * Only loads assets on the specific plugin page to avoid conflicts. Enqueues
	 * moment.js (from WordPress core) and the Calentim date picker library.
	 *
	 */
	public function enqueue_admin_scripts( string $hook_suffix ): void {
		if ( 'tools_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}
		$plugin_url = plugin_dir_url( __FILE__ );

		wp_enqueue_script( 'calentim', $plugin_url . 'assets/js/calentim.js', [ 'jquery', 'moment' ], self::VERSION, true );
		wp_enqueue_style( 'calentim-css', $plugin_url . 'assets/css/calentim.css', [], self::VERSION );
	}

	/**
	 * Handles the AJAX request to delete items based on type.
	 *
	 * This is the core processing function. It performs the following steps:
	 * 1. Verifies the nonce and user capabilities for security.
	 * 2. Sanitizes and validates all incoming `$_POST` data.
	 * 3. Uses a configuration array to determine the correct logic for the
	 *    selected item type (comments or attachments).
	 * 4. Calls the appropriate functions for querying IDs, fetching log data,
	 *    and deleting the items.
	 * 5. Constructs and sends a JSON response indicating success or failure,
	 *    including a user-friendly message and a detailed deletion log.
	 *
	 */
	public function handle_ajax_delete_request(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), self::NONCE_ACTION ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'bulk-deleter-by-date' ) ], 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'bulk-deleter-by-date' ) ], 403 );
		}

		$start_date  = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date    = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$delete_type = isset( $_POST['delete_type'] ) ? sanitize_key( $_POST['delete_type'] ) : '';

		if ( ! $this->is_valid_date( $start_date ) || ! $this->is_valid_date( $end_date ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid date format. Please use YYYY-MM-DD.', 'bulk-deleter-by-date' ) ], 400 );
		}
		if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
			wp_send_json_error( [ 'message' => __( 'Start date cannot be after end date.', 'bulk-deleter-by-date' ) ], 400 );
		}

		$deleter_configs = [
			'comments'    => [
				'query_callback'    => [ $this, 'query_comments' ],
				'delete_callback'   => 'wp_delete_comment',
				'log_data_callback' => [ $this, 'get_comment_log_data' ],
				'log_headers'       => [ 'Author', 'Email', 'Date', 'Comment Excerpt' ],
				'label_singular'    => __( 'comment', 'bulk-deleter-by-date' ),
				'label_plural'      => __( 'comments', 'bulk-deleter-by-date' ),
			],
			'attachments' => [
				'query_callback'    => [ $this, 'query_attachments' ],
				'delete_callback'   => 'wp_delete_attachment',
				'log_data_callback' => [ $this, 'get_attachment_log_data' ],
				'log_headers'       => [ 'Preview', 'File Name', 'Uploaded By', 'Date' ],
				'label_singular'    => __( 'attachment', 'bulk-deleter-by-date' ),
				'label_plural'      => __( 'attachments', 'bulk-deleter-by-date' ),
			],
		];

		if ( ! array_key_exists( $delete_type, $deleter_configs ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid content type selected.', 'bulk-deleter-by-date' ) ], 400 );
		}

		$config = $deleter_configs[ $delete_type ];
		$ids    = call_user_func( $config['query_callback'], $start_date, $end_date );

		if ( empty( $ids ) ) {
			$message = sprintf( __( 'No %s were found in the specified date range.', 'bulk-deleter-by-date' ), $config['label_plural'] );
			wp_send_json_success( [ 'message' => $message, 'deleted_items' => [] ] );
		}

		$deleted_count = 0;
		$deleted_log   = [];

		foreach ( $ids as $id ) {
			$deleted_log[] = call_user_func( $config['log_data_callback'], $id );
			if ( call_user_func( $config['delete_callback'], $id, true ) ) {
				$deleted_count++;
			}
		}

		$message = sprintf(
			_n( 'Successfully deleted %1$d %2$s.', 'Successfully deleted %1$d %3$s.', $deleted_count, 'bulk-deleter-by-date' ),
			$deleted_count,
			$config['label_singular'],
			$config['label_plural']
		);

		wp_send_json_success( [
			'message'       => $message,
			'deleted_items' => $deleted_log,
			'log_headers'   => $config['log_headers'],
		] );
	}

	/**
	 * Queries the database for comment IDs within a specific date range.
	 *
	 */
	private function query_comments( string $start_date, string $end_date ): array {
		return get_comments( [
			'date_query' => [ [ 'after' => "$start_date 00:00:00", 'before' => "$end_date 23:59:59", 'inclusive' => true ] ],
			'status'     => 'all',
			'fields'     => 'ids',
		] );
	}

	/**
	 * Queries the database for attachment IDs within a specific date range.
	 *
	 */
	private function query_attachments( string $start_date, string $end_date ): array {
		return get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'date_query'     => [ [ 'after' => "$start_date 00:00:00", 'before' => "$end_date 23:59:59", 'inclusive' => true ] ],
		] );
	}

	/**
	 * Gathers relevant data for a single comment to be used in the deletion log.
	 *
	 */
	private function get_comment_log_data( int $comment_id ): array {
		$comment = get_comment( $comment_id );
		return [
			$comment->comment_author,
			$comment->comment_author_email,
			$comment->comment_date,
			wp_trim_words( $comment->comment_content, 15, '...' ),
		];
	}

	/**
	 * Gathers relevant data for a single attachment to be used in the deletion log.
	 *
	 */
	private function get_attachment_log_data( int $attachment_id ): array {
		$post = get_post( $attachment_id );
		return [
			wp_get_attachment_image( $attachment_id, [ 60, 60 ], true, [ 'class' => 'bdd-log-thumb' ] ),
			get_the_title( $attachment_id ),
			get_the_author_meta( 'display_name', $post->post_author ),
			get_the_date( '', $attachment_id ),
		];
	}

	/**
	 * Validates a date string to ensure it matches the 'Y-m-d' format.
	 *
	 */
	private function is_valid_date( string $date ): bool {
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}
}

new Bulk_Deleter_By_Date();
