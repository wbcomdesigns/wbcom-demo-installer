
jQuery( document ).ready( function( $ ) {

	var wbcom_theme_demo_data = '';
    var thisRef = '';
    var wbcom_tdd_post_types_count = '';
    var wbcom_tdd_post_types_done = 0;
    var wbcom_tdd_database_tables_count = '';
    var wbcom_tdd_database_tables_done = 0;

	$( 'div.wbcom-demo-importer button#wbcom_get_theme_demo_data' ).click( function( event ) {
		event.preventDefault();
		$( this ).siblings( 'div.loader' ).show();
		thisRef = $( this );
		_wbcom_read_theme_demo_package_file();
    });

    function _wbcom_read_theme_demo_package_file() {
    	wbcom_tdd_make_progress_alert( 'reading package file start' );
		$.ajax({
			url : wbcom_theme_demo_installer_params.ajax_url,
			type : 'post',
			data : {
				action : 'wbcom_read_theme_demo_package_file',
				theme_slug : thisRef.siblings( '#theme_slug' ).val(),
				demo_slug : thisRef.siblings( '#demo_slug' ).val(),
			},
			success : function( response ) {
				wbcom_tdd_make_progress_alert( 'reading package file done' );
				$( '#progress-bar-container' ).show();
				wbcom_theme_demo_data = $.parseJSON( response );
				wbcom_tdd_update_progress_bar( '10%' );
				_wbcom_read_theme_demo_xml_files();
			}
		});
	}

	function _wbcom_read_theme_demo_xml_files() {
		if ( typeof( wbcom_theme_demo_data.post_types ) === "undefined" ) {
			return;
		}
		wbcom_tdd_make_progress_alert( 'post_types start' );
		wbcom_tdd_post_types_count = wbcom_theme_demo_data.post_types.length;
		if( wbcom_tdd_post_types_count == 0 ) {
			_wbcom_read_theme_demo_json_files();
		}
		for (i = 0; i < wbcom_theme_demo_data.post_types.length; i++) {
			_wbcom_get_theme_demo_data( wbcom_theme_demo_data.post_types[i], 'post_types' );
		}
	}

	function _wbcom_read_theme_demo_json_files() {
		if ( typeof( wbcom_theme_demo_data.database_tables ) === "undefined" ) {
			return;
		}
		wbcom_tdd_make_progress_alert( 'database_tables start' );
		wbcom_tdd_database_tables_count = wbcom_theme_demo_data.database_tables.length;
		if( wbcom_tdd_database_tables_count == 0 ) {
			_wbcom_read_theme_demo_upload_folder();
		}
		for (i = 0; i < wbcom_theme_demo_data.database_tables.length; i++) {
			_wbcom_get_theme_demo_data( wbcom_theme_demo_data.database_tables[i], 'database_tables' );
		}
	}

	function _wbcom_read_theme_demo_upload_folder() {
		if ( typeof( wbcom_theme_demo_data.upload_folders ) === "undefined" ) {
			setTimeout( function() { 
				thisRef.siblings( 'div.loader' ).hide();
				$( '#progress-bar-container' ).hide();
				window.location = wbcom_theme_demo_installer_params.success_url;
			},
			2000
			);
			return;
		}
		wbcom_tdd_make_progress_alert( 'upload_folders start' );
		_wbcom_get_theme_demo_data( wbcom_theme_demo_data.upload_folders, 'upload_folders' );
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
				if( action_for == 'post_types' ) {
					wbcom_tdd_post_types_done = wbcom_tdd_post_types_done + 1;
					if( wbcom_tdd_post_types_done == wbcom_tdd_post_types_count ) {
						wbcom_tdd_make_progress_alert( 'post_types complete' );
						wbcom_tdd_update_progress_bar( '45%' );
						_wbcom_read_theme_demo_json_files();
					}
				}
				else if( action_for == 'database_tables' ) {
					wbcom_tdd_database_tables_done = wbcom_tdd_database_tables_done + 1;
					if( wbcom_tdd_database_tables_done == wbcom_tdd_database_tables_count ) {
						wbcom_tdd_make_progress_alert( 'database_tables complete' );
						wbcom_tdd_update_progress_bar( '75%' );
						_wbcom_read_theme_demo_upload_folder();
					}
				}
				else {
					wbcom_tdd_make_progress_alert( 'upload_folders complete' );
					wbcom_tdd_update_progress_bar( '100%' );
					setTimeout( function() { 
						thisRef.siblings( 'div.loader' ).hide();
						$( '#progress-bar-container' ).hide();
						window.location = wbcom_theme_demo_installer_params.success_url;
					},
					2000
					);
				}
			}
		});
	}

	function wbcom_tdd_make_progress_alert( notification ) {
		$( '#progress-snackbar' ).html( notification );
		$( '#progress-snackbar' ).show();
		$( '#progress-snackbar' ).addClass( 'show' );
		setTimeout( function() { $( '#progress-snackbar' ).removeClass( 'show' ); }, 3000 );
	}

	function wbcom_tdd_update_progress_bar( progress_percentage ) {
		$( '#progress-bar-container .completed' ).css( 'width', progress_percentage );
		$( '#progress-bar-container .completed' ).html( progress_percentage );
	}

});