(function ($) {
	'use strict';

	var that;

	var fgj2wp = {

		plugin_id: 'fgj2wp',
		fatal_error: '',
		is_logging: false,

		/**
		 * Manage the behaviour of the Skip Media checkbox
		 */
		hide_unhide_media: function () {
			$("#media_import_box").toggle(!$("#skip_media").is(':checked'));
		},

		/**
		 * Security question before deleting WordPress content
		 */
		check_empty_content_option: function () {
			var confirm_message;
			var action = $('input:radio[name=empty_action]:checked').val();
			switch ( action ) {
				case 'imported':
					confirm_message = objectL10n.delete_imported_data_confirmation_message;
					break;
				case 'all':
					confirm_message = objectL10n.delete_all_confirmation_message;
					break;
				default:
					alert(objectL10n.delete_no_answer_message);
					return false;
					break;
			}
			return confirm(confirm_message);
		},

		/**
		 * Start the logger
		 */
		start_logger: function () {
			that.is_logging = true;
			clearTimeout(that.display_logs_timeout);
			clearTimeout(that.update_progressbar_timeout);
			clearTimeout(that.update_wordpress_info_timeout);
			that.update_display();
		},

		/**
		 * Stop the logger
		 */
		stop_logger: function () {
			that.is_logging = false;
		},

		/**
		 * Update the display
		 */
		update_display: function () {
			that.display_logs();
			that.update_progressbar();
			that.update_wordpress_info();
		},

		/**
		 * Display the logs
		 */
		display_logs: function () {
			if ( $("#logger_autorefresh").is(":checked") ) {
				$.ajax({
					url: objectPlugin.log_file_url,
					cache: false
				}).done(function (result) {
					$('#action_message').html(''); // Clear the action message
					$("#logger").html('');
					result.split("\n").forEach(function (row) {
						if ( row.substr(0, 7) === '[ERROR]' || row.substr(0, 9) === '[WARNING]' || row === 'IMPORT STOPPED BY USER' ) {
							row = '<span class="error_msg">' + row + '</span>'; // Mark the errors in red
						}
						// Test if the import is completed
						else if ( row === 'IMPORT COMPLETED' ) {
							row = '<span class="completed_msg">' + row + '</span>'; // Mark the completed message in green
							$('#action_message').html(objectL10n.import_completed)
								.removeClass('failure').addClass('success');
						}
						$("#logger").append(row + "<br />\n");

					});
					$("#logger").append('<span class="error_msg">' + that.fatal_error + '</span>' + "<br />\n");
				}).always(function () {
					if (that.is_logging) {
						that.display_logs_timeout = setTimeout(that.display_logs, 1000);
					}
				});
			} else {
				if ( that.is_logging ) {
					that.display_logs_timeout = setTimeout(that.display_logs, 1000);
				}
			}
		},

		/**
		 * Update the progressbar
		 */
		update_progressbar: function () {
			$.ajax({
				url: objectPlugin.progress_url,
				cache: false,
				dataType: 'json'
			}).always(function (result) {
				// Move the progress bar
				var progress = 0;
				if ( (result.total !== undefined) && (Number(result.total) !== 0) ) {
					progress = Math.round(Number(result.current) / Number(result.total) * 100);
				}
				jQuery('#progressbar').progressbar('option', 'value', progress);
				jQuery('#progresslabel').html(progress + '%');
				if ( that.is_logging ) {
					that.update_progressbar_timeout = setTimeout(that.update_progressbar, 1000);
				}
			});
		},

		/**
		 * Update WordPress database info
		 */
		update_wordpress_info: function () {
			var data = 'action=' + that.plugin_id + '_import&plugin_action=update_wordpress_info';
			$.ajax({
				method: "POST",
				url: ajaxurl,
				data: data
			}).done(function (result) {
				$('#fgj2wp_database_info_content').html(result);
				if ( that.is_logging ) {
					that.update_wordpress_info_timeout = setTimeout(that.update_wordpress_info, 1000);
				}
			});
		},

		/**
		 * Empty WordPress content
		 * 
		 * @returns {Boolean}
		 */
		empty_wp_content: function () {
			if ( that.check_empty_content_option() ) {
				// Start displaying the logs
				that.start_logger();
				$('#empty').attr('disabled', 'disabled'); // Disable the button
				$('#empty_spinner').addClass("is-active");
				$('#empty_message').html('');

				var data = $('#form_empty_wordpress_content').serialize() + '&action=' + that.plugin_id + '_import&plugin_action=empty';
				$.ajax({
					method: "POST",
					url: ajaxurl,
					data: data
				}).done(function (result) {
					if (result) {
						that.fatal_error = result;
					}
					$('#empty_message').html(objectL10n.content_removed_from_wordpress).addClass('success');
				}).fail(function (result) {
					that.fatal_error = result.responseText;
				}).always(function () {
					that.stop_logger();
					$('#empty').removeAttr('disabled'); // Enable the button
					$('#empty_spinner').removeClass("is-active");
				});
			}
			return false;
		},

		/**
		 * Test the database connection
		 * 
		 * @returns {Boolean}
		 */
		test_database: function () {
			// Start displaying the logs
			that.start_logger();
			$('#test_database').attr('disabled', 'disabled'); // Disable the button
			$('#database_test_message').html('');

			var data = $('#form_import').serialize() + '&action=' + that.plugin_id + '_import&plugin_action=test_database';
			$.ajax({
				method: 'POST',
				url: ajaxurl,
				data: data,
				dataType: 'json'
			}).done(function (result) {
				if ( typeof result.message !== 'undefined' ) {
					$('#database_test_message').toggleClass('success', result.status === 'OK')
						.toggleClass('failure', result.status !== 'OK')
						.html(result.message);
				}
			}).fail(function (result) {
				that.fatal_error = result.responseText;
			}).always(function () {
				that.stop_logger();
				$('#test_database').removeAttr('disabled'); // Enable the button
			});
			return false;
		},

		/**
		 * Change the Download protocol
		 * 
		 */
		change_protocol: function () {
			var protocol = $('input:radio[name=download_protocol]:checked').val();
			switch ( protocol ) {
				case 'ftp':
					$('.ftp_parameters').show();
					$('.file_system_parameters').hide();
					$('.test_media').hide();
					break;
				case 'file_system':
					$('.ftp_parameters').hide();
					$('.file_system_parameters').show();
					$('.test_media').show();
					break;
				default:
					if ( objectPlugin.enable_ftp ) { // Show the FTP parameters for the add-ons which need it
						$('.ftp_parameters').show();
					} else {
						$('.ftp_parameters').hide();
					}
					$('.file_system_parameters').hide();
					$('.test_media').show();
			}
			// TODO To remove once the add-ons using it have been updated
			if ( objectPlugin.legacy_enable_ftp ) { // Show the FTP parameters for the add-ons which need it
				$('.ftp_parameters').show();
			}
		},

		/**
		 * Test the Media connection
		 * 
		 * @returns {Boolean}
		 */
		test_download: function () {
			// Start displaying the logs
			that.start_logger();
			$('#test_download').attr('disabled', 'disabled'); // Disable the button
			$('#download_test_message').html('');

			var data = $('#form_import').serialize() + '&action=' + that.plugin_id + '_import&plugin_action=test_download';
			$.ajax({
				method: 'POST',
				url: ajaxurl,
				data: data,
				dataType: 'json'
			}).done(function (result) {
				if ( typeof result.message !== 'undefined' ) {
					$('#download_test_message').toggleClass('success', result.status === 'OK')
						.toggleClass('failure', result.status !== 'OK')
						.html(result.message);
				}
			}).fail(function (result) {
				that.fatal_error = result.responseText;
			}).always(function () {
				that.stop_logger();
				$('#test_download').removeAttr('disabled'); // Enable the button
			});
			return false;
		},

		/**
		 * Test the FTP connection
		 * 
		 * @returns {Boolean}
		 */
		test_ftp: function () {
			// Start displaying the logs
			that.start_logger();
			$('#test_ftp').attr('disabled', 'disabled'); // Disable the button
			$('#ftp_test_message').html('');

			var data = $('#form_import').serialize() + '&action=' + that.plugin_id + '_import&plugin_action=test_ftp';
			$.ajax({
				method: 'POST',
				url: ajaxurl,
				data: data,
				dataType: 'json'
			}).done(function (result) {
				if ( typeof result.message !== 'undefined' ) {
					$('#ftp_test_message').toggleClass('success', result.status === 'OK')
						.toggleClass('failure', result.status !== 'OK')
						.html(result.message);
				}
			}).fail(function (result) {
				that.fatal_error = result.responseText;
			}).always(function () {
				that.stop_logger();
				$('#test_ftp').removeAttr('disabled'); // Enable the button
			});
			return false;
		},

		/**
		 * Save the settings
		 * 
		 * @returns {Boolean}
		 */
		save: function () {
			// Start displaying the logs
			that.start_logger();
			$('#save').attr('disabled', 'disabled'); // Disable the button
			$('#save_spinner').addClass("is-active");
			$('#save_message').html('');

			var data = $('#form_import').serialize() + '&action=' + that.plugin_id + '_import&plugin_action=save';
			$.ajax({
				method: "POST",
				url: ajaxurl,
				data: data
			}).done(function () {
				$('#save_message').html(objectL10n.settings_saved).addClass('success');
			}).always(function () {
				that.stop_logger();
				$('#save').removeAttr('disabled'); // Enable the button
				$('#save_spinner').removeClass("is-active");
			});
			return false;
		},

		/**
		 * Start the import
		 * 
		 * @returns {Boolean}
		 */
		start_import: function () {
			that.fatal_error = '';
			// Start displaying the logs
			that.start_logger();

			// Disable the import button
			that.import_button_label = $('#import').val();
			$('#import').val(objectL10n.importing).attr('disabled', 'disabled');
			// Show the stop button
			$('#stop-import').show();
			$('#import_spinner').addClass("is-active");
			// Clear the action message
			$('#action_message').html('');

			// Run the import
			var data = $('#form_import').serialize() + '&action=' + that.plugin_id + '_import&plugin_action=import';
			$.ajax({
				method: "POST",
				url: ajaxurl,
				data: data
			}).done(function (result) {
				if (result) {
					that.fatal_error = result;
				}
			}).fail(function (result) {
				that.fatal_error = result.responseText;
			}).always(function () {
				that.stop_logger();
				that.update_display(); // Get the latest information after the import was stopped
				that.reactivate_import_button();
			});
			return false;
		},

		/**
		 * Reactivate the import button
		 * 
		 */
		reactivate_import_button: function () {
			$('#import').val(that.import_button_label).removeAttr('disabled');
			$('#stop-import').hide();
			$('#import_spinner').removeClass("is-active");
		},

		/**
		 * Stop import
		 * 
		 * @returns {Boolean}
		 */
		stop_import: function () {
			$('#stop-import').attr('disabled', 'disabled');
			$('#action_message').html(objectL10n.import_stopped_by_user)
				.removeClass('success').addClass('failure');
			// Stop the import
			var data = $('#form_import').serialize() + '&action=' + that.plugin_id + '_import&plugin_action=stop_import';
			$.ajax({
				method: "POST",
				url: ajaxurl,
				data: data
			}).fail(function (result) {
				that.fatal_error = result.responseText;
			}).always(function () {
				that.stop_logger();
				$('#stop-import').removeAttr('disabled'); // Enable the button
				that.reactivate_import_button();
			});
			return false;
		},

		/**
		 * Modify the internal links
		 * 
		 * @returns {Boolean}
		 */
		modify_links: function () {
			// Start displaying the logs
			that.start_logger();
			$('#modify_links').attr('disabled', 'disabled'); // Disable the button
			$('#modify_links_spinner').addClass("is-active");
			$('#modify_links_message').html('');

			var data = $('#form_modify_links').serialize() + '&action=' + that.plugin_id + '_import&plugin_action=modify_links';
			$.ajax({
				method: "POST",
				url: ajaxurl,
				data: data
			}).done(function (result) {
				if (result) {
					that.fatal_error = result;
				}
				$('#modify_links_message').html(objectL10n.internal_links_modified).addClass('success');
			}).fail(function (result) {
				that.fatal_error = result.responseText;
			}).always(function () {
				that.stop_logger();
				$('#modify_links').removeAttr('disabled'); // Enable the button
				$('#modify_links_spinner').removeClass("is-active");
			});
			return false;
		},

		/**
		 * Copy a field value to the clipboard
		 * 
		 */
		copy_to_clipboard: function () {
			var containerid = $(this).data("field");
			if (document.selection) {
				var range = document.body.createTextRange();
				range.moveToElementText(document.getElementById(containerid));
				range.select().createTextRange();

			} else if (window.getSelection) {
				window.getSelection().removeAllRanges();
				var range = document.createRange();
				range.selectNode(document.getElementById(containerid));
				window.getSelection().addRange(range);
			}
			document.execCommand("copy");
			return false;
		}

	};

	/**
	 * Actions to run when the DOM is ready
	 */
	$(function () {
		that = fgj2wp;

		$('#progressbar').progressbar({value: 0});

		// Skip media checkbox
		$("#skip_media").bind('click', that.hide_unhide_media);
		that.hide_unhide_media();

		// Empty WordPress content confirmation
		$("#form_empty_wordpress_content").bind('submit', that.check_empty_content_option);

		// Partial import checkbox
		$("#partial_import").hide();
		$("#partial_import_toggle").click(function () {
			$("#partial_import").slideToggle("slow");
		});

		// Empty button
		$('#empty').click(that.empty_wp_content);

		// Test database button
		$('#test_database').click(that.test_database);

		// Change the Download protocol
		$('input[name="download_protocol"]').bind('click', that.change_protocol);
		that.change_protocol();

		// Test Media button
		$('#test_download').click(that.test_download);

		// Test FTP button
		$('#test_ftp').click(that.test_ftp);

		// Save settings button
		$('#save').click(that.save);

		// Import button
		$('#import').click(that.start_import);

		// Stop import button
		$('#stop-import').click(that.stop_import);

		// Modify links button
		$('#modify_links').click(that.modify_links);

		// Display the logs
		$('#logger_autorefresh').click(that.display_logs);

		$('.copy_to_clipboard').click(that.copy_to_clipboard);

		that.update_display();
	});

})(jQuery);
