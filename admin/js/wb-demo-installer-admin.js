jQuery(document).ready(function($){
	'use strict';

	/** 
	 * Support Tab JS
	 */
	var acc = document.getElementsByClassName("wbdi-accordion");
	var i;
	for (i = 0; i < acc.length; i++) {
		acc[i].onclick = function() {
			this.classList.toggle("active");
			var panel = this.nextElementSibling;
			if (panel.style.maxHeight){
				panel.style.maxHeight = null;
			} else {
				panel.style.maxHeight = panel.scrollHeight + "px";
			} 
		}
	}
	$(document).on('click', '.wbdi-accordion', function(){
		return false;
	});

	/**
	 * Install the plugin
	 */
	$(document).on('click', '.wbdi-plugin-install', function(){
		var link = $(this);
		var row = link.closest('tr');
		var plugin_name = link.data('plugin');
		var plugin_download_url = link.data('downloadurl');
		var plugin_slug = link.data('pluginslug');
		if( plugin_name == '' || plugin_download_url == '' || plugin_slug == '' ) {
			console.log('Unable to detect which plugin to install!');
		} else {
			var link_html = $(this).html( '<i class="fa fa-refresh fa-spin"></i> Installing...' );
			var data = {
				'action'				: 'wbdi_plugin_install',
				'plugin_name'			: plugin_name,
				'plugin_download_url'	: plugin_download_url,
				'plugin_slug'			: plugin_slug
			}
			$.ajax({
				dataType: "JSON",
				url: wbdi_admin_js_object.ajaxurl,
				type: 'POST',
				data: data,
				success: function( response ) {
					console.log(response['data']['message']);
					if( response['data']['result'] == 'not-installed' ) {
						row.find('td.column-action').html( response['data']['plugin_action'] );
						row.find('td.column-action').css( 'color', 'red' );
					} else if( response['data']['result'] == 'activated' ) {
						row.find('td.column-action').html( response['data']['plugin_action'] );
						row.find('td.column-status').html( response['data']['plugin_status'] );
					}
				},
			});
		}
	});

	/**
	 * Activate the plugin
	 */
	$(document).on('click', '.wbdi-plugin-activate', function(){
		var link = $(this);
		var row = link.closest('tr');
		var plugin = link.data('plugin');
		if( plugin == '' ) {
			console.log('Unable to detect which plugin to activate!');
		} else {
			var link_html = $(this).html( '<i class="fa fa-refresh fa-spin"></i> Activating...' );
			var data = {
				'action'	: 'wbdi_plugin_activate',
				'plugin'	: plugin
			}
			$.ajax({
				dataType: "JSON",
				url: wbdi_admin_js_object.ajaxurl,
				type: 'POST',
				data: data,
				success: function( response ) {
					console.log(response['data']['message']);
					row.find('td.column-status').html( response['data']['plugin_status'] );
					row.find('td.column-action').html( response['data']['plugin_action'] );
				},
			});
		}
	});

	/**
	 * Import the demo data
	 */
	$('#progress-bar').width( '0%' );
	$(document).on('click', '.wbdi-import', function(){
		var btn 		= $(this);
		var btn_txt 	= $(this).html();
		var file_url 		= btn.data('fileurl');
		btn.html( '<i class="fa fa-refresh fa-spin"></i> Importing...' );
		var data = {
			'action'	: 'wbdi_import_demo',
			'file_url'	: file_url
		}
		$.ajax({
			dataType: "JSON",
			url: wbdi_admin_js_object.ajaxurl,
			type: 'POST',
			data: data,
			success: function( response ) {
				console.log(response['data']['message']);
				if( response['data']['data_import_result'] == 'false' ) {
					btn.html( '<i class="fa fa-times"></i> Import Failed' ).addClass('wbdi-import-failed');
				} else {
					btn.html( '<i class="fa fa-check"></i> Import Success' ).addClass('wbdi-import-success');
				}
			},
			progress: function (event, position, total, percentComplete){	
				$("#progress-bar").width(percentComplete + '%');
				$("#progress-bar").html('<div id="progress-status">' + percentComplete +' %</div>')
			},
		});
	});
});