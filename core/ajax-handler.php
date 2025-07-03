<?php
/**
 * AJAX handler for Reign Demo Installer
 *
 * @package Reign_Demo_Installer
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Reign_Demo_Installer_Ajax_Handler' ) ) :

	/**
	 * Reign_Demo_Installer_Ajax_Handler class.
	 *
	 * @class Reign_Demo_Installer_Ajax_Handler
	 * @version 3.0.0
	 */
	class Reign_Demo_Installer_Ajax_Handler {

		/**
		 * The single instance of the class.
		 *
		 * @var Reign_Demo_Installer_Ajax_Handler
		 * @since 3.0.0
		 */
		protected static $_instance = null;

		/**
		 * Security instance.
		 *
		 * @var Reign_Demo_Installer_Security
		 */
		private $security;

		/**
		 * Main instance.
		 *
		 * @since 3.0.0
		 * @static
		 * @return Reign_Demo_Installer_Ajax_Handler - Main instance.
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->security = Reign_Demo_Installer_Security::instance();
			$this->init_hooks();
		}

		/**
		 * Hook into actions and filters.
		 *
		 * @since 3.0.0
		 */
		private function init_hooks() {
			// New AJAX actions (with proper naming)
			add_action( 'wp_ajax_reign_get_theme_demo_data', array( $this, 'get_theme_demo_data' ) );
			add_action( 'wp_ajax_reign_read_theme_demo_package_file', array( $this, 'read_theme_demo_package_file' ) );
			add_action( 'wp_ajax_reign_manage_plugin_installation', array( $this, 'manage_plugin_installation' ) );

			// Backward compatibility - keep old action names
			add_action( 'wp_ajax_wbcom_get_theme_demo_data', array( $this, 'wbcom_get_theme_demo_data' ) );
			add_action( 'wp_ajax_wbcom_read_theme_demo_package_file', array( $this, 'wbcom_read_theme_demo_package_file' ) );
			add_action( 'wp_ajax_wbcom_manage_plugin_installation', array( $this, 'wbcom_manage_plugin_installation' ) );
		}

		/**
		 * Handle plugin installation/activation.
		 */
		public function manage_plugin_installation() {
			$this->wbcom_manage_plugin_installation();
		}

		/**
		 * Read theme demo package file.
		 */
		public function read_theme_demo_package_file() {
			$this->wbcom_read_theme_demo_package_file();
		}

		/**
		 * Get theme demo data.
		 */
		public function get_theme_demo_data() {
			$this->wbcom_get_theme_demo_data();
		}

		/**
		 * Legacy: Read theme demo package file.
		 */
		public function wbcom_read_theme_demo_package_file() {
			// Security checks
			if ( ! $this->verify_ajax_request() ) {
				wp_die( esc_html__( 'Security check failed.', 'reign-demo-installer' ), '', array( 'response' => 403 ) );
			}

			// Get and validate parameters
			$theme_slug = $this->security->get_request_param( 'theme_slug', 'slug' );
			$demo_slug = $this->security->get_request_param( 'demo_slug', 'slug' );
			$target_url = $this->security->get_request_param( 'target_url', 'url' );

			if ( ! $theme_slug || ! $demo_slug || ! $target_url ) {
				wp_die( esc_html__( 'Missing required parameters.', 'reign-demo-installer' ), '', array( 'response' => 400 ) );
			}

			// Validate target URL domain
			if ( ! $this->security->validate_url_domain( $target_url ) ) {
				Reign_Demo_Installer_Logger::error( 'Invalid target URL attempted: ' . $target_url );
				wp_die( esc_html__( 'Invalid target URL.', 'reign-demo-installer' ), '', array( 'response' => 400 ) );
			}

			// Log import start
			Reign_Demo_Installer_Logger::log_import_start( $demo_slug, array(
				'theme_slug' => $theme_slug,
				'target_url' => $target_url
			) );

			// Set memory and time limits
			$this->set_import_limits();

			try {
				$url_to_request = $target_url . 'wp-admin/?wbcom_theme_demo_listing=yes';
				
				$response = wp_remote_post( $url_to_request, array(
					'method'     => 'POST',
					'timeout'    => 120,
					'sslverify'  => true,
					'user-agent' => 'Reign Demo Installer/' . REIGN_DEMO_INSTALLER_VERSION,
					'headers'    => array(
						'Content-Type' => 'application/x-www-form-urlencoded'
					),
					'body'       => array(
						'theme_slug' => $theme_slug,
						'demo_slug'  => $demo_slug,
					),
				) );

				if ( is_wp_error( $response ) ) {
					Reign_Demo_Installer_Logger::error( 'Failed to fetch demo package: ' . $response->get_error_message() );
					wp_die( esc_html__( 'Failed to connect to demo server.', 'reign-demo-installer' ), '', array( 'response' => 500 ) );
				}

				$response_code = wp_remote_retrieve_response_code( $response );
				if ( $response_code !== 200 ) {
					Reign_Demo_Installer_Logger::error( 'Invalid response code from demo server: ' . $response_code );
					wp_die( esc_html__( 'Demo server returned an error.', 'reign-demo-installer' ), '', array( 'response' => 500 ) );
				}

				$body = wp_remote_retrieve_body( $response );
				if ( empty( $body ) ) {
					wp_die( esc_html__( 'Empty response from demo server.', 'reign-demo-installer' ), '', array( 'response' => 500 ) );
				}

				// Validate JSON response
				$demo_data = json_decode( $body, true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					Reign_Demo_Installer_Logger::error( 'Invalid JSON response: ' . json_last_error_msg() );
				}

				echo $body;

			} catch ( Exception $e ) {
				Reign_Demo_Installer_Logger::error( 'Exception during demo package read: ' . $e->getMessage() );
				wp_die( esc_html__( 'An error occurred while reading demo package.', 'reign-demo-installer' ), '', array( 'response' => 500 ) );
			}

			wp_die();
		}

		/**
		 * Legacy: Get theme demo data.
		 */
		public function wbcom_get_theme_demo_data() {
			// Security checks
			if ( ! $this->verify_ajax_request() ) {
				wp_die( esc_html__( 'Security check failed.', 'reign-demo-installer' ), '', array( 'response' => 403 ) );
			}

			$action_for = $this->security->get_request_param( 'action_for', 'string' );
			$url_to_request = $this->security->get_request_param( 'url_to_request', 'url' );

			if ( ! $action_for || ! $url_to_request ) {
				wp_die( esc_html__( 'Missing required parameters.', 'reign-demo-installer' ), '', array( 'response' => 400 ) );
			}

			// Validate URL
			if ( ! $this->security->validate_url_domain( $url_to_request ) ) {
				Reign_Demo_Installer_Logger::error( 'Invalid URL attempted: ' . $url_to_request );
				wp_die( esc_html__( 'Invalid URL.', 'reign-demo-installer' ), '', array( 'response' => 400 ) );
			}

			// Set memory and time limits
			$this->set_import_limits();

			try {
				switch ( $action_for ) {
					case 'post_types':
						$this->handle_post_types_import( $url_to_request );
						break;

					case 'database_tables':
						$this->handle_database_tables_import( $url_to_request );
						break;

					case 'upload_folders':
						$this->handle_upload_folders_import( $url_to_request );
						break;

					default:
						wp_die( esc_html__( 'Invalid action type.', 'reign-demo-installer' ), '', array( 'response' => 400 ) );
				}

			} catch ( Exception $e ) {
				Reign_Demo_Installer_Logger::error( 'Exception during data import: ' . $e->getMessage() );
				wp_die( esc_html__( 'An error occurred during import.', 'reign-demo-installer' ), '', array( 'response' => 500 ) );
			}

			wp_die();
		}

		/**
		 * Legacy: Manage plugin installation.
		 */
		public function wbcom_manage_plugin_installation() {
			// Security checks
			if ( ! $this->verify_ajax_request() ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'reign-demo-installer' ) ) );
			}

			$plugin_action = $this->security->get_request_param( 'plugin_action', 'string' );
			$plugin_slug = $this->security->get_request_param( 'plugin_slug', 'slug' );
			$demo = $this->security->get_request_param( 'demo', 'slug' );

			if ( ! $plugin_action || ! $plugin_slug || ! $demo ) {
				wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'reign-demo-installer' ) ) );
			}

			// Log plugin action
			Reign_Demo_Installer_Logger::log_plugin_action( $plugin_slug, 'attempting_' . $plugin_action );

			try {
				// Get plugins manager instance
				if ( class_exists( 'Reign_Demo_Installer_Plugins_Manager' ) ) {
					$plugins_manager = Reign_Demo_Installer_Plugins_Manager::instance();
				} elseif ( function_exists( 'instantiate_wbcom_demo_importer_plugins_manager' ) ) {
					$plugins_manager = instantiate_wbcom_demo_importer_plugins_manager();
				} else {
					wp_send_json_error( array( 'message' => __( 'Plugins manager not available.', 'reign-demo-installer' ) ) );
				}

				// Handle plugin action
				switch ( $plugin_action ) {
					case 'install_plugin':
						$result = $plugins_manager->do_plugin_install( $plugin_slug, false );
						break;

					case 'enable_plugin':
						$result = $plugins_manager->do_plugin_activate( $plugin_slug, false );
						break;

					default:
						wp_send_json_error( array( 'message' => __( 'Invalid plugin action.', 'reign-demo-installer' ) ) );
				}

				if ( isset( $result['error'] ) ) {
					Reign_Demo_Installer_Logger::log_plugin_action( $plugin_slug, 'error', $result['error'] );
					wp_send_json_error( array( 'message' => $result['error'] ) );
				} else {
					Reign_Demo_Installer_Logger::log_plugin_action( $plugin_slug, 'success' );
					wp_send_json_success( $result );
				}

			} catch ( Exception $e ) {
				Reign_Demo_Installer_Logger::log_plugin_action( $plugin_slug, 'exception', $e->getMessage() );
				wp_send_json_error( array( 'message' => __( 'An error occurred during plugin operation.', 'reign-demo-installer' ) ) );
			}
		}

		/**
		 * Handle post types import.
		 *
		 * @param string $url_to_request URL to request
		 */
		private function handle_post_types_import( $url_to_request ) {
			$post_slug = basename( $url_to_request, '.xml' );
			$this->clone_post_type( $post_slug, $url_to_request );
		}

		/**
		 * Handle database tables import.
		 *
		 * @param string $url_to_request URL to request
		 */
		private function handle_database_tables_import( $url_to_request ) {
			$table_name = basename( $url_to_request, '.json' );
			$table_name = preg_replace( '/[0-9]+/', '', $table_name );
			$this->clone_database_table( $table_name, $url_to_request );
		}

		/**
		 * Handle upload folders import.
		 *
		 * @param string $url_to_request URL to request
		 */
		private function handle_upload_folders_import( $url_to_request ) {
			$this->clone_uploads_folder( $url_to_request );
		}

		/**
		 * Clone post type data.
		 *
		 * @param string $post_slug Post slug
		 * @param string $url_to_request URL to request
		 */
		private function clone_post_type( $post_slug = 'post', $url_to_request = '' ) {
			$retrieved_data = $this->safe_remote_get( $url_to_request );
			
			if ( is_wp_error( $retrieved_data ) ) {
				Reign_Demo_Installer_Logger::error( 'Failed to fetch post type data: ' . $retrieved_data->get_error_message() );
				return;
			}

			$upload = wp_upload_dir();
			$upload_dir = $upload['basedir'] . '/reign-temp-folder';
			
			if ( ! is_dir( $upload_dir ) ) {
				wp_mkdir_p( $upload_dir );
			}

			$file_path = $upload_dir . '/' . sanitize_file_name( $post_slug ) . '.xml';
			
			// Write file securely
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( $wp_filesystem ) {
				$wp_filesystem->put_contents( $file_path, $retrieved_data );
			} else {
				file_put_contents( $file_path, $retrieved_data );
			}

			// Import XML data
			global $wbcom_xml_wp_import;
			if ( isset( $wbcom_xml_wp_import ) ) {
				$wbcom_xml_wp_import->import( $file_path );
			}

			// Clean up
			wp_delete_file( $file_path );
		}

		/**
		 * Clone database table.
		 *
		 * @param string $table_name Table name
		 * @param string $url_to_request URL to request
		 */
		private function clone_database_table( $table_name = '', $url_to_request = '' ) {
			$retrieved_data = $this->safe_remote_get( $url_to_request, true );
			
			if ( is_wp_error( $retrieved_data ) ) {
				Reign_Demo_Installer_Logger::error( 'Failed to fetch database table data: ' . $retrieved_data->get_error_message() );
				return;
			}

			if ( empty( $retrieved_data ) || ! is_array( $retrieved_data ) ) {
				Reign_Demo_Installer_Logger::error( 'Invalid database table data format' );
				return;
			}

			// Replace placeholders
			if ( $table_name !== 'options' ) {
				$retrieved_data = $this->replace_url_placeholders( $retrieved_data );
			}

			// Handle different table types
			switch ( $table_name ) {
				case 'theme_mods':
					$this->import_theme_mods( $retrieved_data );
					break;

				case 'options':
					$this->import_options( $retrieved_data );
					break;

				default:
					$this->import_table_data( $table_name, $retrieved_data );
					break;
			}
		}

		/**
		 * Import theme modifications.
		 *
		 * @param array $data Theme mods data
		 */
		private function import_theme_mods( $data ) {
			foreach ( $data as $key => $value ) {
				set_theme_mod( $key, $value );
			}
			Reign_Demo_Installer_Logger::info( 'Theme mods imported successfully' );
		}

		/**
		 * Import options data.
		 *
		 * @param array $data Options data
		 */
		private function import_options( $data ) {
			$default_options_keys = $this->get_default_options_keys();
			$imported_count = 0;

			foreach ( $data as $option ) {
				if ( ! isset( $option['option_name'] ) ) {
					continue;
				}

				if ( in_array( $option['option_name'], $default_options_keys, true ) ) {
					continue; // Skip default WordPress options
				}

				$option_value = maybe_unserialize( $option['option_value'] );
				$option_value = $this->replace_url_in_option_value( $option_value );
				
				$autoload = isset( $option['autoload'] ) ? $option['autoload'] : 'yes';
				
				update_option( $option['option_name'], $option_value, $autoload );
				$imported_count++;
			}

			Reign_Demo_Installer_Logger::info( "Imported {$imported_count} options" );
		}

		/**
		 * Import table data.
		 *
		 * @param string $table_name Table name
		 * @param array $data Table data
		 */
		private function import_table_data( $table_name, $data ) {
			global $wpdb;

			$full_table_name = $wpdb->prefix . $table_name;
			
			// Check if we need to clear the table first
			$import_data_key = 'reign_theme_demo_import_data';
			$wbcom_theme_demo_import_data = get_option( $import_data_key, array() );
			
			if ( ! isset( $wbcom_theme_demo_import_data[ $full_table_name . '_done' ] ) ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM %i", $full_table_name ) );
				$wbcom_theme_demo_import_data[ $full_table_name . '_done' ] = 'yes';
				update_option( $import_data_key, $wbcom_theme_demo_import_data );
			}

			$inserted_count = 0;
			foreach ( $data as $row ) {
				// Skip current user data for users/usermeta tables
				if ( $this->should_skip_user_data( $table_name, $row ) ) {
					continue;
				}

				// Clean up invalid columns for user tables
				$row = $this->clean_user_table_data( $table_name, $row );

				$result = $wpdb->insert( $full_table_name, $row );
				if ( $result !== false ) {
					$inserted_count++;
				}
			}

			Reign_Demo_Installer_Logger::info( "Imported {$inserted_count} rows to {$table_name}" );
		}

		/**
		 * Clone uploads folder.
		 *
		 * @param string $url_to_request URL to request
		 */
		private function clone_uploads_folder( $url_to_request = '' ) {
			$parent_folder_name = $this->extract_parent_folder_name( $url_to_request );
			
			$retrieved_data = $this->safe_remote_get( $url_to_request );
			
			if ( is_wp_error( $retrieved_data ) ) {
				Reign_Demo_Installer_Logger::error( 'Failed to fetch upload folder: ' . $retrieved_data->get_error_message() );
				return;
			}

			if ( empty( $retrieved_data ) ) {
				return;
			}

			$upload = wp_upload_dir();
			$zip_file_path = $upload['basedir'] . '/reign-theme-demo.zip';

			// Write zip file securely
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( $wp_filesystem ) {
				$wp_filesystem->put_contents( $zip_file_path, $retrieved_data );
			} else {
				file_put_contents( $zip_file_path, $retrieved_data );
			}

			// Extract zip file
			if ( class_exists( 'ZipArchive' ) ) {
				$zip = new ZipArchive();
				$res = $zip->open( $zip_file_path );
				
				if ( $res === true ) {
					$extract_path = $upload['basedir'] . '/' . $parent_folder_name . '/';
					$zip->extractTo( $extract_path );
					$zip->close();
					
					Reign_Demo_Installer_Logger::info( "Extracted uploads to {$extract_path}" );
				} else {
					Reign_Demo_Installer_Logger::error( "Failed to extract zip file: {$zip_file_path}" );
				}
			} else {
				Reign_Demo_Installer_Logger::error( 'ZipArchive class not available' );
			}

			// Clean up zip file
			wp_delete_file( $zip_file_path );
		}

		/**
		 * Safely perform remote GET request.
		 *
		 * @param string $url URL to request
		 * @param bool $decode_json Whether to decode JSON response
		 * @return mixed Response data or WP_Error
		 */
		private function safe_remote_get( $url, $decode_json = false ) {
			$response = wp_remote_get( $url, array( 
				'sslverify' => true, 
				'timeout' => 120,
				'user-agent' => 'Reign Demo Installer/' . REIGN_DEMO_INSTALLER_VERSION
			) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code !== 200 ) {
				return new WP_Error( 'http_error', "HTTP {$response_code} error" );
			}

			$body = wp_remote_retrieve_body( $response );
			
			if ( $decode_json ) {
				$decoded = json_decode( $body, true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					return new WP_Error( 'json_error', 'Invalid JSON response' );
				}
				return $decoded;
			}

			return $body;
		}

		/**
		 * Verify AJAX request security.
		 *
		 * @return bool True if valid, false otherwise
		 */
		private function verify_ajax_request() {
			// Check user capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				return false;
			}

			// Verify nonce
			$nonce = $this->security->get_request_param( 'nonce', 'string' );
			if ( ! wp_verify_nonce( $nonce, 'reign_demo_installer_ajax' ) && 
				 ! wp_verify_nonce( $nonce, 'reign_demo_installer_import' ) &&
				 ! wp_verify_nonce( $nonce, 'reign_demo_installer_plugins' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Set memory and time limits for import.
		 */
		private function set_import_limits() {
			if ( defined( 'REIGN_DEMO_INSTALLER_MEMORY_LIMIT' ) ) {
				ini_set( 'memory_limit', REIGN_DEMO_INSTALLER_MEMORY_LIMIT );
			}
			
			if ( defined( 'REIGN_DEMO_INSTALLER_MAX_EXECUTION_TIME' ) ) {
				set_time_limit( REIGN_DEMO_INSTALLER_MAX_EXECUTION_TIME );
			}
		}

		/**
		 * Replace URL placeholders in data.
		 *
		 * @param array $data Data array
		 * @return array Modified data
		 */
		private function replace_url_placeholders( $data ) {
			return array_map( function( $value ) {
				if ( is_string( $value ) ) {
					return str_replace( '{{*home_url}}', home_url(), $value );
				}
				return $value;
			}, $data );
		}

		/**
		 * Replace URLs in option value.
		 *
		 * @param mixed $option_value Option value
		 * @return mixed Modified option value
		 */
		private function replace_url_in_option_value( $option_value ) {
			if ( is_array( $option_value ) ) {
				foreach ( $option_value as $key => $value ) {
					if ( is_string( $value ) ) {
						$option_value[ $key ] = str_replace( '{{*home_url}}', get_site_url(), $value );
					}
				}
			} elseif ( is_string( $option_value ) ) {
				$option_value = str_replace( '{{*home_url}}', get_site_url(), $option_value );
			}
			
			return $option_value;
		}

		/**
		 * Check if user data should be skipped.
		 *
		 * @param string $table_name Table name
		 * @param array $row Row data
		 * @return bool True if should skip, false otherwise
		 */
		private function should_skip_user_data( $table_name, $row ) {
			$current_user_id = get_current_user_id();
			
			if ( $table_name === 'users' && isset( $row['ID'] ) && $row['ID'] == $current_user_id ) {
				return true;
			}
			
			if ( $table_name === 'usermeta' && isset( $row['user_id'] ) && $row['user_id'] == $current_user_id ) {
				return true;
			}
			
			return false;
		}

		/**
		 * Clean user table data.
		 *
		 * @param string $table_name Table name
		 * @param array $row Row data
		 * @return array Cleaned row data
		 */
		private function clean_user_table_data( $table_name, $row ) {
			if ( $table_name === 'users' ) {
				// Remove fields that might not exist in all WordPress installations
				$invalid_fields = array( 'spam', 'deleted' );
				foreach ( $invalid_fields as $field ) {
					if ( isset( $row[ $field ] ) ) {
						unset( $row[ $field ] );
					}
				}
			}
			
			return $row;
		}

		/**
		 * Extract parent folder name from URL.
		 *
		 * @param string $url URL
		 * @return string Parent folder name
		 */
		private function extract_parent_folder_name( $url ) {
			$path_parts = array_filter( explode( '/', $url ) );
			$path_parts = array_values( $path_parts );
			
			if ( count( $path_parts ) >= 2 ) {
				return $path_parts[ count( $path_parts ) - 2 ];
			}
			
			return 'uploads';
		}

		/**
		 * Get default WordPress options keys that should not be imported.
		 *
		 * @return array Default options keys
		 */
		private function get_default_options_keys() {
			return array(
				'siteurl', 'home', 'blogname', 'blogdescription', 'users_can_register',
				'admin_email', 'new_admin_email', 'start_of_week', 'use_balanceTags',
				'use_smilies', 'require_name_email', 'comments_notify', 'posts_per_rss',
				'rss_use_excerpt', 'mailserver_url', 'mailserver_login', 'mailserver_pass',
				'mailserver_port', 'default_category', 'default_comment_status',
				'default_ping_status', 'default_pingback_flag', 'date_format',
				'time_format', 'links_updated_date_format', 'comment_moderation',
				'moderation_notify', 'rewrite_rules', 'hack_file', 'blog_charset',
				'active_plugins', 'category_base', 'ping_sites', 'comment_max_links',
				'gmt_offset', 'default_email_category', 'template', 'stylesheet',
				'comment_whitelist', 'comment_registration', 'html_type', 'use_trackback',
				'default_role', 'db_version', 'uploads_use_yearmonth_folders', 'upload_path',
				'blog_public', 'default_link_category', 'tag_base', 'show_avatars',
				'avatar_rating', 'upload_url_path', 'thumbnail_size_w', 'thumbnail_size_h',
				'thumbnail_crop', 'medium_size_w', 'medium_size_h', 'avatar_default',
				'large_size_w', 'large_size_h', 'image_default_link_type',
				'image_default_size', 'image_default_align', 'close_comments_for_old_posts',
				'close_comments_days_old', 'thread_comments', 'thread_comments_depth',
				'page_comments', 'comments_per_page', 'default_comments_page',
				'comment_order', 'sticky_posts', 'timezone_string', 'default_post_format',
				'link_manager_enabled', 'finished_splitting_shared_terms', 'site_icon',
				'medium_large_size_w', 'medium_large_size_h', 'initial_db_version',
				'wp_user_roles', 'fresh_site', 'cron'
			);
		}
	}

endif;

/**
 * Main instance of Reign_Demo_Installer_Ajax_Handler.
 *
 * @since 3.0.0
 * @return Reign_Demo_Installer_Ajax_Handler
 */
Reign_Demo_Installer_Ajax_Handler::instance();