
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
    	$.ajax({
			url : wbcom_theme_demo_installer_params.ajax_url,
			type : 'post',
			data : {
				action : 'wbcom_read_theme_demo_package_file',
				theme_slug : thisRef.siblings( '#theme_slug' ).val(),
				demo_slug : thisRef.siblings( '#demo_slug' ).val(),
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
		$.ajax({
			url : wbcom_theme_demo_installer_params.ajax_url,
			type : 'post',
			data : {
				action : 'wbcom_get_theme_demo_data',
				url_to_request : url_to_request,
				action_for : action_for,
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
				alert( "error in :: " + url_to_request );
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

});