
/*
* Plugin Installer Manager Code
*/
jQuery( document ).ready( function( $ ) {

	_check_all_required_plugin_installed();

	$( 'button.plugin-action-button' ).click( function( event ) {
		event.preventDefault();
		var thisRef = $( this );

		if( thisRef.hasClass( 'already-active' ) ) {
			return;
		}

		_show_plugin_installer_loader();
		$.ajax({
			url : wbcom_theme_demo_installer_params.ajax_url,
			type : 'post',
			dataType : 'json',
			data : {
				action : 'wbcom_manage_plugin_installation',
				plugin_action : thisRef.siblings( 'input.plugin-action').val(),
				plugin_slug : thisRef.siblings( 'input.plugin-slug').val(),
				demo : thisRef.siblings( 'input.demo-name').val(),
				nonce : wbcom_theme_demo_installer_params.ajax_nonce
			},
			success : function( response ) {
				_hide_plugin_installer_loader();
				if( response.success ) {
					thisRef.siblings( 'p.plugin-status').html( 'Active' );
					thisRef.siblings( 'p.plugin-status').addClass( 'already-active' );
					thisRef.html( 'Already Installed & Activated' );
					thisRef.attr( 'class', 'plugin-action-button button already-active' );
					var temp_counter = parseInt( $( 'input#num_of_req_plugins_installed').val() );
					temp_counter++;
					$( 'input#num_of_req_plugins_installed').val( temp_counter );
					_check_all_required_plugin_installed();
				}
				else {
					_show_admin_notice( 'There was a problem performing the action.', 'error' );
				}
			},
			'error' : function( response ) {
				_hide_plugin_installer_loader();
				var errorMsg = response.responseJSON && response.responseJSON.data && response.responseJSON.data.error 
					? response.responseJSON.data.error 
					: 'There was a problem performing the action.';
				_show_admin_notice( errorMsg, 'error' );
			}
		});
	});

	function _check_all_required_plugin_installed() {
		if( ( parseInt( $( 'input#required_plugins_to_activate').val() ) - parseInt( $( 'input#num_of_req_plugins_installed').val() ) == 0 ) ) {
			$( 'div.goto-install-demo-step').show();
		}
		else {
			$( 'div.goto-install-demo-step').hide();
		}
	}

	function _show_plugin_installer_loader() {
		jQuery( 'body' ).addClass( 'demo_listing_loading' );
	}

	function _hide_plugin_installer_loader() {
		jQuery( 'body' ).removeClass( 'demo_listing_loading' );
	}
	
	function _show_admin_notice( message, type ) {
		// Remove any existing notices
		$( '.wbcom-notice' ).remove();
		
		// Create notice HTML
		var noticeClass = 'notice notice-' + type + ' is-dismissible wbcom-notice';
		var noticeHtml = '<div class="' + noticeClass + '"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
		
		// Add notice after page title
		$( '.wrap h1' ).first().after( noticeHtml );
		
		// Make dismissible
		$( '.wbcom-notice .notice-dismiss' ).on( 'click', function() {
			$( this ).parent().fadeOut( 300, function() { $( this ).remove(); } );
		});
		
		// Auto-hide success messages after 5 seconds
		if ( type === 'success' ) {
			setTimeout( function() {
				$( '.wbcom-notice' ).fadeOut( 300, function() { $( this ).remove(); } );
			}, 5000 );
		}
	}

});


/*
* Demo Importer Manager Code
*/
jQuery( document ).ready( function( $ ) {

	var wbcom_theme_demo_data = '';
    var thisRef = '';

    var wbcom_tdd_database_tables_count = '';
    var wbcom_tdd_database_tables_done = 0;

    var wbcom_tdd_upload_folders_count = '';
    var wbcom_tdd_upload_folders_done = 0;

    var wbcom_tdd_database_tables_complete = false;
    var wbcom_tdd_upload_folders_complete = false;

    var total_requests = 0;
    var percentage_increment = 0;
    var current_percentage_progress = 0;

	$( 'div.wbcom-demo-importer button#wbcom_get_theme_demo_data' ).click( function( event ) {
		event.preventDefault();
		$( this ).siblings( 'div.loader' ).show();
		thisRef = $( this );
		_wbcom_read_theme_demo_package_file();
    });

    function _wbcom_read_theme_demo_package_file() {
    	wbcom_tdd_show_current_activity( 'Reading Files ...' );
    	$.ajax({
			url : wbcom_theme_demo_installer_params.ajax_url,
			type : 'post',
			data : {
				action : 'wbcom_read_theme_demo_package_file',
				theme_slug : thisRef.siblings( '#theme_slug' ).val(),
				demo_slug : thisRef.siblings( '#demo_slug' ).val(),
				target_url : thisRef.siblings( '#target_url' ).val(),
				nonce : wbcom_theme_demo_installer_params.ajax_nonce
			},
			success : function( response ) {
				wbcom_tdd_update_progress_bar( Math.floor(current_percentage_progress)+"%" );
				$( '#progress-bar-container' ).show();
				wbcom_theme_demo_data = $.parseJSON( response );
				total_requests = ( wbcom_theme_demo_data.database_tables.length + wbcom_theme_demo_data.upload_folders.length );
				percentage_increment = ( 100 / total_requests );
				_wbcom_read_theme_demo_json_files();
				_wbcom_read_theme_demo_upload_folders();
			}
		});
	}

	function _wbcom_read_theme_demo_json_files() {
		if ( typeof( wbcom_theme_demo_data.database_tables ) === "undefined" ) {
			return;
		}
		wbcom_tdd_database_tables_count = wbcom_theme_demo_data.database_tables.length;
		if( wbcom_tdd_database_tables_count == 0 ) {
			wbcom_tdd_database_tables_complete = true;
		}
		_wbcom_get_theme_demo_data( wbcom_theme_demo_data.database_tables[0], 'database_tables' );
	}

	function _wbcom_read_theme_demo_upload_folders() {
		if ( typeof( wbcom_theme_demo_data.upload_folders ) === "undefined" ) {
			return;
		}
		wbcom_tdd_upload_folders_count = wbcom_theme_demo_data.upload_folders.length;
		if( wbcom_tdd_upload_folders_count == 0 ) {
			wbcom_tdd_upload_folders_complete = true;
		}
		_wbcom_get_theme_demo_data( wbcom_theme_demo_data.upload_folders[0], 'upload_folders' );
	}

	function _wbcom_get_theme_demo_data( url_to_request, action_for ) {
		wbcom_tdd_show_current_activity( 'Reading Files ...' );
		$.ajax({
			url : wbcom_theme_demo_installer_params.ajax_url,
			type : 'post',
			data : {
				action : 'wbcom_get_theme_demo_data',
				url_to_request : url_to_request,
				action_for : action_for,
				nonce : wbcom_theme_demo_installer_params.ajax_nonce
			},
			success : function( response ) {
				if( action_for == 'database_tables' ) {
					wbcom_tdd_database_tables_done = wbcom_tdd_database_tables_done + 1;
					if( wbcom_tdd_database_tables_done == wbcom_tdd_database_tables_count ) {
						wbcom_tdd_database_tables_complete = true;
						if( wbcom_tdd_database_tables_complete && wbcom_tdd_upload_folders_complete ) {
							current_percentage_progress = 100;
							wbcom_tdd_update_progress_bar( Math.floor(current_percentage_progress)+"%" );
							wbcom_demo_import_done();
						}
					}
					else {
						current_percentage_progress += percentage_increment;
						wbcom_tdd_update_progress_bar( Math.floor(current_percentage_progress)+"%" );
						_wbcom_get_theme_demo_data( wbcom_theme_demo_data.database_tables[wbcom_tdd_database_tables_done], 'database_tables' );
					}
				}
				else {
					wbcom_tdd_upload_folders_done = wbcom_tdd_upload_folders_done + 1;
					if( wbcom_tdd_upload_folders_done == wbcom_tdd_upload_folders_count ) {
						wbcom_tdd_upload_folders_complete = true;
						if( wbcom_tdd_database_tables_complete && wbcom_tdd_upload_folders_complete ) {
							current_percentage_progress = 100;
							wbcom_tdd_update_progress_bar( Math.floor(current_percentage_progress)+"%" );
							wbcom_demo_import_done();
						}
					}
					else {
						current_percentage_progress += percentage_increment;
						wbcom_tdd_update_progress_bar( Math.floor(current_percentage_progress)+"%" );
						_wbcom_get_theme_demo_data( wbcom_theme_demo_data.upload_folders[wbcom_tdd_upload_folders_done], 'upload_folders' );
					}
				}
			},
			error: function ( jqXHR, status, err ) {
				_show_import_error( 'Error processing: ' + url_to_request + '. ' + ( err || 'Unknown error' ) );
				if( action_for == 'database_tables' ) {
					wbcom_tdd_database_tables_done = wbcom_tdd_database_tables_done + 1;
					if( wbcom_tdd_database_tables_done == wbcom_tdd_database_tables_count ) {
						wbcom_tdd_database_tables_complete = true;
						if( wbcom_tdd_database_tables_complete && wbcom_tdd_upload_folders_complete ) {
							current_percentage_progress = 100;
							wbcom_tdd_update_progress_bar( Math.floor(current_percentage_progress)+"%" );
							wbcom_demo_import_done();
						}
					}
					else {
						_wbcom_get_theme_demo_data( wbcom_theme_demo_data.database_tables[wbcom_tdd_database_tables_done], 'database_tables' );
					}
				}
				else {
					wbcom_tdd_upload_folders_done = wbcom_tdd_upload_folders_done + 1;
					if( wbcom_tdd_upload_folders_done == wbcom_tdd_upload_folders_count ) {
						wbcom_tdd_upload_folders_complete = true;
						if( wbcom_tdd_database_tables_complete && wbcom_tdd_upload_folders_complete ) {
							current_percentage_progress = 100;
							wbcom_tdd_update_progress_bar( Math.floor(current_percentage_progress)+"%" );
							wbcom_demo_import_done();
						}
					}
					else {
						_wbcom_get_theme_demo_data( wbcom_theme_demo_data.upload_folders[wbcom_tdd_upload_folders_done], 'upload_folders' );
					}
				}
			}
		});
	}

	function wbcom_demo_import_done() {
		setTimeout( function() {
			window.location = wbcom_theme_demo_installer_params.success_url;
		},
		2000
		);
	}

	function wbcom_tdd_update_progress_bar( progress_percentage ) {
		$( '#progress-bar-container .completed' ).css( 'width', progress_percentage );
		$( '#progress-bar-container .completed' ).html( progress_percentage );
	}

	function wbcom_tdd_show_current_activity( message ) {
		$( '#wbtd-current-action' ).show();
		$( '#wbtd-current-action' ).html( message );
	}
	
	function _show_import_error( message ) {
		// Update progress bar to show error state
		$( '#progress-bar-container .completed' ).css( 'background-color', '#dc3232' );
		
		// Show error in snackbar
		var $snackbar = $( '#progress-snackbar' );
		$snackbar.html( '<div class="notice notice-error"><p>' + message + '</p></div>' );
		$snackbar.show();
		
		// Hide loader
		$( 'div.loader' ).hide();
		
		// Change button text to allow retry
		$( '#wbcom_get_theme_demo_data' ).text( 'Retry Import' ).prop( 'disabled', false );
	}

});
