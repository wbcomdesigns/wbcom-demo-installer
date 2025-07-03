<?php
/**
 * AJAX handler for Reign Demo Installer - Enhanced Version
 * Preserves current admin user and prevents role downgrade
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
		 */
		protected static $_instance = null;

		/**
		 * Security instance.
		 *
		 * @var Reign_Demo_Installer_Security
		 */
		private $security;

		/**
		 * Logger instance.
		 *
		 * @var Reign_Demo_Installer_Logger
		 */
		private $logger;

		/**
		 * Current admin user data to preserve.
		 *
		 * @var array
		 */
		private $current_admin_user = null;

		/**
		 * Import session data.
		 *
		 * @var array
		 */
		private $import_session = array();

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
			$this->logger = class_exists( 'Reign_Demo_Installer_Logger' ) ? new Reign_Demo_Installer_Logger() : null;
			$this->init_hooks();
		}

		/**
		 * Hook into actions and filters.
		 *
		 * @since 3.0.0
		 */
		private function init_hooks() {
			// New AJAX actions
			add_action( 'wp_ajax_reign_get_theme_demo_data', array( $this, 'get_theme_demo_data' ) );
			add_action( 'wp_ajax_reign_read_theme_demo_package_file', array( $this, 'read_theme_demo_package_file' ) );
			add_action( 'wp_ajax_reign_manage_plugin_installation', array( $this, 'manage_plugin_installation' ) );

			// Backward compatibility
			add_action( 'wp_ajax_wbcom_get_theme_demo_data', array( $this, 'get_theme_demo_data' ) );
			add_action( 'wp_ajax_wbcom_read_theme_demo_package_file', array( $this, 'read_theme_demo_package_file' ) );
			add_action( 'wp_ajax_wbcom_manage_plugin_installation', array( $this, 'manage_plugin_installation' ) );
		}

		/**
		 * Handle plugin installation/activation.
		 */
		public function manage_plugin_installation() {
			try {
				// Security checks are handled by the Security class pre-hook
				
				$plugin_action = $this->security->get_request_param( 'plugin_action', 'string' );
				$plugin_slug = $this->security->get_request_param( 'plugin_slug', 'slug' );
				$demo = $this->security->get_request_param( 'demo', 'slug' );

				if ( ! $plugin_action || ! $plugin_slug || ! $demo ) {
					wp_send_json_error( array( 
						'message' => __( 'Missing required parameters.', 'reign-demo-installer' ) 
					) );
				}

				$this->log_info( "Plugin action requested: {$plugin_action} for {$plugin_slug}" );

				// Get plugins manager instance
				$plugins_manager = $this->get_plugins_manager();
				if ( ! $plugins_manager ) {
					wp_send_json_error( array( 
						'message' => __( 'Plugins manager not available.', 'reign-demo-installer' ) 
					) );
				}

				// Handle plugin action
				$result = $this->handle_plugin_action( $plugins_manager, $plugin_action, $plugin_slug, $demo );
				
				if ( is_wp_error( $result ) ) {
					$this->log_error( "Plugin action failed: {$result->get_error_message()}" );
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				$this->log_info( "Plugin action completed successfully: {$plugin_action} for {$plugin_slug}" );
				wp_send_json_success( $result );

			} catch ( Exception $e ) {
				$this->log_error( "Plugin action exception: {$e->getMessage()}" );
				wp_send_json_error( array( 
					'message' => __( 'An error occurred during plugin operation.', 'reign-demo-installer' ) 
				) );
			}
		}

		/**
		 * Read theme demo package file.
		 */
		public function read_theme_demo_package_file() {
			try {
				// Get and validate parameters
				$theme_slug = $this->security->get_request_param( 'theme_slug', 'slug' );
				$demo_slug = $this->security->get_request_param( 'demo_slug', 'slug' );
				$target_url = $this->security->get_request_param( 'target_url', 'url' );

				if ( ! $theme_slug || ! $demo_slug || ! $target_url ) {
					wp_die( 
						esc_html__( 'Missing required parameters.', 'reign-demo-installer' ), 
						'', 
						array( 'response' => 400 ) 
					);
				}

				// Validate target URL
				if ( ! $this->security->validate_url_domain( $target_url ) ) {
					$this->log_error( "Invalid target URL attempted: {$target_url}" );
					wp_die( 
						esc_html__( 'Invalid target URL.', 'reign-demo-installer' ), 
						'', 
						array( 'response' => 400 ) 
					);
				}

				// Preserve admin user
				$this->preserve_current_admin_user();

				// Log import start
				$this->log_import_start( $demo_slug, array(
					'theme_slug' => $theme_slug,
					'target_url' => $target_url,
					'admin_user' => $this->current_admin_user ? $this->current_admin_user['user_login'] : 'unknown'
				) );

				// Set import limits
				$this->set_import_limits();

				// Fetch demo package
				$demo_data = $this->fetch_demo_package( $target_url, $theme_slug, $demo_slug );
				
				if ( is_wp_error( $demo_data ) ) {
					wp_die( $demo_data->get_error_message(), '', array( 'response' => 500 ) );
				}

				echo $demo_data;

			} catch ( Exception $e ) {
				$this->log_error( "Demo package read exception: {$e->getMessage()}" );
				wp_die( 
					esc_html__( 'An error occurred while reading demo package.', 'reign-demo-installer' ), 
					'', 
					array( 'response' => 500 ) 
				);
			}

			wp_die();
		}

		/**
		 * Get theme demo data.
		 */
		public function get_theme_demo_data() {
			try {
				$action_for = $this->security->get_request_param( 'action_for', 'string' );
				$url_to_request = $this->security->get_request_param( 'url_to_request', 'url' );

				if ( ! $action_for || ! $url_to_request ) {
					wp_die( 
						esc_html__( 'Missing required parameters.', 'reign-demo-installer' ), 
						'', 
						array( 'response' => 400 ) 
					);
				}

				// Validate URL
				if ( ! $this->security->validate_url_domain( $url_to_request ) ) {
					$this->log_error( "Invalid URL attempted: {$url_to_request}" );
					wp_die( 
						esc_html__( 'Invalid URL.', 'reign-demo-installer' ), 
						'', 
						array( 'response' => 400 ) 
					);
				}

				// Set import limits
				$this->set_import_limits();

				// Handle different data types
				$result = $this->handle_demo_data_import( $action_for, $url_to_request );
				
				if ( is_wp_error( $result ) ) {
					wp_die( $result->get_error_message(), '', array( 'response' => 500 ) );
				}

			} catch ( Exception $e ) {
				$this->log_error( "Demo data import exception: {$e->getMessage()}" );
				wp_die( 
					esc_html__( 'An error occurred during import.', 'reign-demo-installer' ), 
					'', 
					array( 'response' => 500 ) 
				);
			}

			wp_die();
		}

		/**
		 * Preserve current admin user data.
		 */
		private function preserve_current_admin_user() {
			if ( $this->current_admin_user !== null || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
				return;
			}
			
			$current_user = wp_get_current_user();
			
			$this->current_admin_user = array(
				'ID' => $current_user->ID,
				'user_login' => $current_user->user_login,
				'user_email' => $current_user->user_email,
				'user_pass' => $current_user->user_pass,
				'user_nicename' => $current_user->user_nicename,
				'user_url' => $current_user->user_url,
				'user_registered' => $current_user->user_registered,
				'user_activation_key' => $current_user->user_activation_key,
				'user_status' => $current_user->user_status,
				'display_name' => $current_user->display_name,
				'roles' => $current_user->roles,
				'capabilities' => $current_user->allcaps,
				'meta' => get_user_meta( $current_user->ID ),
				'preserved_at' => current_time( 'mysql' )
			);
			
			$this->log_info( "Admin user preserved: {$current_user->user_login}" );
		}

		/**
		 * Handle plugin action.
		 *
		 * @param object $plugins_manager Plugins manager instance
		 * @param string $action Plugin action
		 * @param string $slug Plugin slug
		 * @param string $demo Demo name
		 * @return array|WP_Error
		 */
		private function handle_plugin_action( $plugins_manager, $action, $slug, $demo ) {
			switch ( $action ) {
				case 'install_plugin':
					return $plugins_manager->do_plugin_install( $slug, false );
				
				case 'enable_plugin':
					return $plugins_manager->do_plugin_activate( $slug, false );
				
				default:
					return new WP_Error( 'invalid_action', __( 'Invalid plugin action.', 'reign-demo-installer' ) );
			}
		}

		/**
		 * Handle demo data import based on type.
		 *
		 * @param string $action_for Action type
		 * @param string $url_to_request URL to request
		 * @return bool|WP_Error
		 */
		private function handle_demo_data_import( $action_for, $url_to_request ) {
			switch ( $action_for ) {
				case 'post_types':
					return $this->handle_post_types_import( $url_to_request );
				
				case 'database_tables':
					return $this->handle_database_tables_import( $url_to_request );
				
				case 'upload_folders':
					return $this->handle_upload_folders_import( $url_to_request );
				
				default:
					return new WP_Error( 'invalid_action_type', __( 'Invalid action type.', 'reign-demo-installer' ) );
			}
		}

		/**
		 * Handle post types import.
		 *
		 * @param string $url_to_request URL to request
		 * @return bool|WP_Error
		 */
		private function handle_post_types_import( $url_to_request ) {
			$post_slug = basename( $url_to_request, '.xml' );
			return $this->clone_post_type( $post_slug, $url_to_request );
		}

		/**
		 * Handle database tables import.
		 *
		 * @param string $url_to_request URL to request
		 * @return bool|WP_Error
		 */
		private function handle_database_tables_import( $url_to_request ) {
			$table_name = basename( $url_to_request, '.json' );
			$table_name = preg_replace( '/[0-9]+/', '', $table_name );
			return $this->clone_database_table( $table_name, $url_to_request );
		}

		/**
		 * Handle upload folders import.
		 *
		 * @param string $url_to_request URL to request
		 * @return bool|WP_Error
		 */
		private function handle_upload_folders_import( $url_to_request ) {
			return $this->clone_uploads_folder( $url_to_request );
		}

		/**
		 * Fetch demo package from remote server.
		 *
		 * @param string $target_url Target URL
		 * @param string $theme_slug Theme slug
		 * @param string $demo_slug Demo slug
		 * @return string|WP_Error
		 */
		private function fetch_demo_package( $target_url, $theme_slug, $demo_slug ) {
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
				$this->log_error( "Failed to fetch demo package: {$response->get_error_message()}" );
				return new WP_Error( 'fetch_failed', __( 'Failed to connect to demo server.', 'reign-demo-installer' ) );
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code !== 200 ) {
				$this->log_error( "Invalid response code from demo server: {$response_code}" );
				return new WP_Error( 'server_error', __( 'Demo server returned an error.', 'reign-demo-installer' ) );
			}

			$body = wp_remote_retrieve_body( $response );
			if ( empty( $body ) ) {
				return new WP_Error( 'empty_response', __( 'Empty response from demo server.', 'reign-demo-installer' ) );
			}

			// Validate JSON response
			$demo_data = json_decode( $body, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$this->log_error( "Invalid JSON response: {" . json_last_error_msg() . "}" );
			}

			return $body;
		}

		/**
		 * Clone post type data.
		 *
		 * @param string $post_slug Post slug
		 * @param string $url_to_request URL to request
		 * @return bool|WP_Error
		 */
		private function clone_post_type( $post_slug, $url_to_request ) {
			$retrieved_data = $this->safe_remote_get( $url_to_request );
			
			if ( is_wp_error( $retrieved_data ) ) {
				$this->log_error( "Failed to fetch post type data: {$retrieved_data->get_error_message()}" );
				return $retrieved_data;
			}

			$upload = wp_upload_dir();
			$upload_dir = $upload['basedir'] . '/reign-temp-folder';
			
			if ( ! is_dir( $upload_dir ) ) {
				wp_mkdir_p( $upload_dir );
			}

			$file_path = $upload_dir . '/' . sanitize_file_name( $post_slug ) . '.xml';
			
			// Write file securely
			$write_result = $this->write_file_securely( $file_path, $retrieved_data );
			if ( is_wp_error( $write_result ) ) {
				return $write_result;
			}

			// Import XML data
			global $wbcom_xml_wp_import;
			if ( isset( $wbcom_xml_wp_import ) ) {
				$wbcom_xml_wp_import->import( $file_path );
			}

			// Clean up
			wp_delete_file( $file_path );
			
			return true;
		}

		/**
		 * Clone database table.
		 *
		 * @param string $table_name Table name
		 * @param string $url_to_request URL to request
		 * @return bool|WP_Error
		 */
		private function clone_database_table( $table_name, $url_to_request ) {
			$retrieved_data = $this->safe_remote_get( $url_to_request, true );
			
			if ( is_wp_error( $retrieved_data ) ) {
				$this->log_error( "Failed to fetch database table data: {$retrieved_data->get_error_message()}" );
				return $retrieved_data;
			}

			if ( empty( $retrieved_data ) || ! is_array( $retrieved_data ) ) {
				$this->log_error( 'Invalid database table data format' );
				return new WP_Error( 'invalid_data', 'Invalid database table data format' );
			}

			// Handle different table types
			switch ( $table_name ) {
				case 'theme_mods':
					return $this->import_theme_mods( $retrieved_data );
				
				case 'options':
					return $this->import_options( $retrieved_data );
				
				default:
					return $this->import_table_data( $table_name, $retrieved_data );
			}
		}

		/**
		 * Clone uploads folder.
		 *
		 * @param string $url_to_request URL to request
		 * @return bool|WP_Error
		 */
		private function clone_uploads_folder( $url_to_request ) {
			$parent_folder_name = $this->extract_parent_folder_name( $url_to_request );
			
			$retrieved_data = $this->safe_remote_get( $url_to_request );
			
			if ( is_wp_error( $retrieved_data ) ) {
				$this->log_error( "Failed to fetch upload folder: {$retrieved_data->get_error_message()}" );
				return $retrieved_data;
			}

			if ( empty( $retrieved_data ) ) {
				return true; // Empty folder, nothing to do
			}

			$upload = wp_upload_dir();
			$zip_file_path = $upload['basedir'] . '/reign-theme-demo.zip';

			// Write zip file securely
			$write_result = $this->write_file_securely( $zip_file_path, $retrieved_data );
			if ( is_wp_error( $write_result ) ) {
				return $write_result;
			}

			// Extract zip file
			$extract_result = $this->extract_zip_file( $zip_file_path, $upload['basedir'] . '/' . $parent_folder_name . '/' );
			
			// Clean up zip file
			wp_delete_file( $zip_file_path );
			
			return $extract_result;
		}

		/**
		 * Extract ZIP file.
		 *
		 * @param string $zip_file_path ZIP file path
		 * @param string $extract_path Extract path
		 * @return bool|WP_Error
		 */
		private function extract_zip_file( $zip_file_path, $extract_path ) {
			if ( class_exists( 'ZipArchive' ) ) {
				$zip = new ZipArchive();
				$res = $zip->open( $zip_file_path );
				
				if ( $res === true ) {
					$zip->extractTo( $extract_path );
					$zip->close();
					
					$this->log_info( "Extracted uploads to {$extract_path}" );
					return true;
				} else {
					$this->log_error( "Failed to extract zip file: {$zip_file_path}" );
					return new WP_Error( 'extract_failed', 'Failed to extract zip file' );
				}
			} else {
				$this->log_error( 'ZipArchive class not available' );
				return new WP_Error( 'zip_not_available', 'ZipArchive class not available' );
			}
		}

		/**
		 * Write file securely.
		 *
		 * @param string $file_path File path
		 * @param string $content File content
		 * @return bool|WP_Error
		 */
		private function write_file_securely( $file_path, $content ) {
			global $wp_filesystem;
			
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( $wp_filesystem ) {
				$result = $wp_filesystem->put_contents( $file_path, $content );
				if ( ! $result ) {
					return new WP_Error( 'write_failed', 'Failed to write file using WP Filesystem' );
				}
			} else {
				$result = file_put_contents( $file_path, $content );
				if ( $result === false ) {
					return new WP_Error( 'write_failed', 'Failed to write file using file_put_contents' );
				}
			}
			
			return true;
		}

		/**
		 * Safely perform remote GET request.
		 *
		 * @param string $url URL to request
		 * @param bool $decode_json Whether to decode JSON response
		 * @return mixed|WP_Error Response data or WP_Error
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
		 * Import theme modifications.
		 *
		 * @param array $data Theme mods data
		 * @return bool
		 */
		private function import_theme_mods( $data ) {
			foreach ( $data as $key => $value ) {
				set_theme_mod( $key, $value );
			}
			$this->log_info( 'Theme mods imported successfully' );
			return true;
		}

		/**
		 * Import options data.
		 *
		 * @param array $data Options data
		 * @return bool
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

			$this->log_info( "Imported {$imported_count} options" );
			return true;
		}

		/**
		 * Import table data.
		 *
		 * @param string $table_name Table name
		 * @param array $data Table data
		 * @return bool|WP_Error
		 */
		private function import_table_data( $table_name, $data ) {
			global $wpdb;

			$full_table_name = $wpdb->prefix . $table_name;
			
			// Check if we need to clear the table first
			$import_data_key = 'reign_theme_demo_import_data';
			$import_data = get_option( $import_data_key, array() );
			
			if ( ! isset( $import_data[ $full_table_name . '_done' ] ) ) {
				// Special handling for user tables - don't clear if admin user exists
				if ( ! in_array( $table_name, array( 'users', 'usermeta' ) ) ) {
					$wpdb->query( $wpdb->prepare( "DELETE FROM %i", $full_table_name ) );
				} else {
					// For user tables, only delete non-admin users
					if ( $this->current_admin_user ) {
						$admin_id = $this->current_admin_user['ID'];
						if ( $table_name === 'users' ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE ID != %d", $full_table_name, $admin_id ) );
						} elseif ( $table_name === 'usermeta' ) {
							$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE user_id != %d", $full_table_name, $admin_id ) );
						}
					}
				}
				$import_data[ $full_table_name . '_done' ] = 'yes';
				update_option( $import_data_key, $import_data );
			}

			$inserted_count = 0;
			foreach ( $data as $row ) {
				// Skip current admin user data for users/usermeta tables
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

			$this->log_info( "Imported {$inserted_count} rows to {$table_name}" );

			// CRITICAL: Restore admin user after user table imports
			if ( in_array( $table_name, array( 'users', 'usermeta' ) ) ) {
				$this->restore_admin_user();
			}

			return true;
		}

		/**
		 * CRITICAL: Restore admin user after import.
		 */
		private function restore_admin_user() {
			if ( ! $this->current_admin_user ) {
				$this->log_error( 'No admin user data to restore!' );
				return false;
			}

			global $wpdb;
			
			try {
				// Restore user data
				$user_data = array(
					'ID' => $this->current_admin_user['ID'],
					'user_login' => $this->current_admin_user['user_login'],
					'user_email' => $this->current_admin_user['user_email'],
					'user_pass' => $this->current_admin_user['user_pass'],
					'user_nicename' => $this->current_admin_user['user_nicename'],
					'user_url' => $this->current_admin_user['user_url'],
					'user_registered' => $this->current_admin_user['user_registered'],
					'user_activation_key' => $this->current_admin_user['user_activation_key'],
					'user_status' => $this->current_admin_user['user_status'],
					'display_name' => $this->current_admin_user['display_name'],
				);

				// Update or insert user
				$existing_user = get_user_by( 'ID', $this->current_admin_user['ID'] );
				if ( $existing_user ) {
					$wpdb->update( 
						$wpdb->users, 
						$user_data, 
						array( 'ID' => $this->current_admin_user['ID'] ) 
					);
				} else {
					$wpdb->insert( $wpdb->users, $user_data );
				}

				// Restore user meta and capabilities
				$user_id = $this->current_admin_user['ID'];
				
				// Clear existing meta first
				$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id ) );
				
				// Restore all user meta
				if ( isset( $this->current_admin_user['meta'] ) && is_array( $this->current_admin_user['meta'] ) ) {
					foreach ( $this->current_admin_user['meta'] as $meta_key => $meta_values ) {
						if ( is_array( $meta_values ) ) {
							foreach ( $meta_values as $meta_value ) {
								add_user_meta( $user_id, $meta_key, maybe_unserialize( $meta_value ) );
							}
						}
					}
				}

				// Ensure admin capabilities
				$user = new WP_User( $user_id );
				$user->set_role( 'administrator' );
				
				// Add super admin capability if multisite
				if ( is_multisite() ) {
					grant_super_admin( $user_id );
				}

				// Force WordPress to recognize the restored user
				wp_cache_delete( $user_id, 'users' );
				wp_cache_delete( $this->current_admin_user['user_login'], 'userlogins' );
				wp_cache_delete( $this->current_admin_user['user_email'], 'useremail' );

				$this->log_info( "Admin user successfully restored: {$this->current_admin_user['user_login']}" );

				// Set authentication cookies to keep user logged in
				wp_set_auth_cookie( $user_id, true );
				
				return true;
				
			} catch ( Exception $e ) {
				$this->log_error( "Failed to restore admin user: {$e->getMessage()}" );
				
				// Emergency fallback - ensure at least one admin exists
				$this->ensure_admin_user_exists();
				return false;
			}
		}

		/**
		 * Emergency fallback to ensure admin user exists.
		 */
		private function ensure_admin_user_exists() {
			$admin_users = get_users( array( 'role' => 'administrator' ) );
			
			if ( empty( $admin_users ) && $this->current_admin_user ) {
				// Create emergency admin user
				$user_id = wp_insert_user( array(
					'user_login' => $this->current_admin_user['user_login'],
					'user_email' => $this->current_admin_user['user_email'],
					'user_pass' => wp_generate_password(),
					'role' => 'administrator'
				) );

				if ( ! is_wp_error( $user_id ) ) {
					$this->log_info( "Emergency admin user created: {$this->current_admin_user['user_login']}" );
					wp_set_auth_cookie( $user_id, true );
				}
			}
		}

		/**
		 * Check if user data should be skipped.
		 *
		 * @param string $table_name Table name
		 * @param array $row Row data
		 * @return bool
		 */
		private function should_skip_user_data( $table_name, $row ) {
			if ( ! $this->current_admin_user ) {
				return false;
			}

			$current_user_id = $this->current_admin_user['ID'];
			
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
		 * @return array
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
		 * Replace URLs in option value.
		 *
		 * @param mixed $option_value Option value
		 * @return mixed
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
		 * Extract parent folder name from URL.
		 *
		 * @param string $url URL
		 * @return string
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
		 * @return array
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
		 * Get plugins manager instance.
		 *
		 * @return object|null
		 */
		private function get_plugins_manager() {
			if ( class_exists( 'Reign_Demo_Installer_Plugins_Manager' ) ) {
				return Reign_Demo_Installer_Plugins_Manager::instance();
			} elseif ( function_exists( 'instantiate_wbcom_demo_importer_plugins_manager' ) ) {
				return instantiate_wbcom_demo_importer_plugins_manager();
			}
			
			return null;
		}

		/**
		 * Log import start.
		 *
		 * @param string $demo_slug Demo slug
		 * @param array $data Additional data
		 */
		private function log_import_start( $demo_slug, $data = array() ) {
			if ( $this->logger ) {
				$this->logger->log_import_start( $demo_slug, $data );
			}
		}

		/**
		 * Log info message.
		 *
		 * @param string $message Message
		 */
		private function log_info( $message ) {
			if ( $this->logger ) {
				$this->logger->info( $message );
			}
		}

		/**
		 * Log error message.
		 *
		 * @param string $message Message
		 */
		private function log_error( $message ) {
			if ( $this->logger ) {
				$this->logger->error( $message );
			}
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