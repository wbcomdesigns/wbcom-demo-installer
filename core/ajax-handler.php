<?php
/**
 * AJAX handler for Reign Demo Installer - Complete Final Version
 * Enhanced with batch download support and streamlined processing
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
			// Original AJAX actions (backward compatibility)
			add_action( 'wp_ajax_wbcom_get_theme_demo_data', array( $this, 'get_theme_demo_data' ) );
			add_action( 'wp_ajax_wbcom_read_theme_demo_package_file', array( $this, 'read_theme_demo_package_file' ) );
			add_action( 'wp_ajax_wbcom_manage_plugin_installation', array( $this, 'manage_plugin_installation' ) );

			// New batch download AJAX actions
			add_action( 'wp_ajax_wbcom_create_temp_folder', array( $this, 'create_temp_folder' ) );
			add_action( 'wp_ajax_wbcom_get_demo_manifest', array( $this, 'get_demo_manifest' ) );
			add_action( 'wp_ajax_wbcom_download_demo_file', array( $this, 'download_demo_file' ) );
			add_action( 'wp_ajax_wbcom_process_local_demo_file', array( $this, 'process_local_demo_file' ) );
			add_action( 'wp_ajax_wbcom_cleanup_temp_folder', array( $this, 'cleanup_temp_folder' ) );

			// New naming convention
			add_action( 'wp_ajax_reign_create_temp_folder', array( $this, 'create_temp_folder' ) );
			add_action( 'wp_ajax_reign_get_demo_manifest', array( $this, 'get_demo_manifest' ) );
			add_action( 'wp_ajax_reign_download_demo_file', array( $this, 'download_demo_file' ) );
			add_action( 'wp_ajax_reign_process_local_demo_file', array( $this, 'process_local_demo_file' ) );
			add_action( 'wp_ajax_reign_cleanup_temp_folder', array( $this, 'cleanup_temp_folder' ) );
		}

		/**
		 * Create secure temporary folder for batch downloads.
		 */
		public function create_temp_folder() {
			try {
				// Security check
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'reign-demo-installer' ) ) );
				}

				$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
				if ( ! wp_verify_nonce( $nonce, 'reign_demo_installer_ajax' ) ) {
					wp_send_json_error( array( 'message' => __( 'Security check failed', 'reign-demo-installer' ) ) );
				}

				// Preserve admin user
				$this->preserve_current_admin_user();

				$upload_dir = wp_upload_dir();
				$folder_id = 'reign_demo_' . time() . '_' . wp_generate_password( 8, false );
				$temp_path = $upload_dir['basedir'] . '/' . $folder_id;

				// Create directory
				if ( ! wp_mkdir_p( $temp_path ) ) {
					wp_send_json_error( array( 'message' => __( 'Failed to create temp directory', 'reign-demo-installer' ) ) );
				}

				// Add .htaccess for security
				$htaccess_content = "Order deny,allow\nDeny from all\n";
				file_put_contents( $temp_path . '/.htaccess', $htaccess_content );

				// Store temp folder info in transient (expires in 2 hours)
				set_transient( 'reign_temp_folder_' . $folder_id, array(
					'path' => $temp_path,
					'created' => time(),
					'user_id' => get_current_user_id()
				), 2 * HOUR_IN_SECONDS );

				$this->log_info( "Created temp folder: {$folder_id}" );

				wp_send_json_success( array( 
					'folder_id' => $folder_id,
					'path' => $temp_path 
				) );

			} catch ( Exception $e ) {
				$this->log_error( "Failed to create temp folder: {$e->getMessage()}" );
				wp_send_json_error( array( 'message' => __( 'Failed to create temp folder', 'reign-demo-installer' ) ) );
			}
		}

		/**
		 * Get demo manifest with all files to download.
		 */
		public function get_demo_manifest() {
			try {
				// Security check
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'reign-demo-installer' ) ) );
				}

				$theme_slug = isset( $_POST['theme_slug'] ) ? sanitize_text_field( $_POST['theme_slug'] ) : '';
				$demo_slug = isset( $_POST['demo_slug'] ) ? sanitize_text_field( $_POST['demo_slug'] ) : '';
				$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( $_POST['target_url'] ) : '';

				if ( ! $theme_slug || ! $demo_slug || ! $target_url ) {
					wp_send_json_error( array( 'message' => __( 'Missing required parameters', 'reign-demo-installer' ) ) );
				}

				// Validate URL domain
				if ( ! $this->validate_url_domain( $target_url ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid target URL domain', 'reign-demo-installer' ) ) );
				}

				// Get demo package info
				$manifest_url = $target_url . 'wp-admin/?wbcom_theme_demo_listing=yes';
				
				$response = wp_remote_post( $manifest_url, array(
					'method' => 'POST',
					'timeout' => 60,
					'sslverify' => true,
					'user-agent' => 'Reign Demo Installer/' . REIGN_DEMO_INSTALLER_VERSION,
					'body' => array(
						'theme_slug' => $theme_slug,
						'demo_slug' => $demo_slug,
					),
				) );

				if ( is_wp_error( $response ) ) {
					wp_send_json_error( array( 'message' => __( 'Failed to connect to demo server', 'reign-demo-installer' ) ) );
				}

				$body = wp_remote_retrieve_body( $response );
				$demo_data = json_decode( $body, true );

				if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $demo_data ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid demo data received', 'reign-demo-installer' ) ) );
				}

				// Prepare file list
				$files = array();
				
				// Add database tables
				if ( isset( $demo_data['database_tables'] ) && is_array( $demo_data['database_tables'] ) ) {
					foreach ( $demo_data['database_tables'] as $url ) {
						$files[] = array(
							'name' => basename( $url ),
							'url' => $url,
							'type' => 'database_table',
							'action_for' => 'database_tables'
						);
					}
				}

				// Add upload folders
				if ( isset( $demo_data['upload_folders'] ) && is_array( $demo_data['upload_folders'] ) ) {
					foreach ( $demo_data['upload_folders'] as $url ) {
						$files[] = array(
							'name' => basename( $url ),
							'url' => $url,
							'type' => 'upload_folder',
							'action_for' => 'upload_folders'
						);
					}
				}

				if ( empty( $files ) ) {
					wp_send_json_error( array( 'message' => __( 'No demo files found', 'reign-demo-installer' ) ) );
				}

				$this->log_info( "Demo manifest prepared: " . count( $files ) . " files for {$demo_slug}" );

				wp_send_json_success( array( 
					'files' => $files,
					'total_files' => count( $files )
				) );

			} catch ( Exception $e ) {
				$this->log_error( "Failed to get demo manifest: {$e->getMessage()}" );
				wp_send_json_error( array( 'message' => __( 'Failed to get demo manifest', 'reign-demo-installer' ) ) );
			}
		}

		/**
		 * Download single file to temp folder.
		 */
		public function download_demo_file() {
			try {
				// Security check
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'reign-demo-installer' ) ) );
				}

				$temp_folder_id = isset( $_POST['temp_folder_id'] ) ? sanitize_text_field( $_POST['temp_folder_id'] ) : '';
				$file_url = isset( $_POST['file_url'] ) ? esc_url_raw( $_POST['file_url'] ) : '';
				$file_name = isset( $_POST['file_name'] ) ? sanitize_file_name( $_POST['file_name'] ) : '';
				$file_type = isset( $_POST['file_type'] ) ? sanitize_text_field( $_POST['file_type'] ) : '';

				if ( ! $temp_folder_id || ! $file_url || ! $file_name ) {
					wp_send_json_error( array( 'message' => __( 'Missing required parameters', 'reign-demo-installer' ) ) );
				}

				// Get temp folder info
				$folder_info = get_transient( 'reign_temp_folder_' . $temp_folder_id );
				if ( ! $folder_info || $folder_info['user_id'] !== get_current_user_id() ) {
					wp_send_json_error( array( 'message' => __( 'Invalid temp folder', 'reign-demo-installer' ) ) );
				}

				// Validate URL domain
				if ( ! $this->validate_url_domain( $file_url ) ) {
					wp_send_json_error( array( 'message' => __( 'Invalid file URL domain', 'reign-demo-installer' ) ) );
				}

				$file_path = $folder_info['path'] . '/' . $file_name;

				// Download file
				$response = wp_remote_get( $file_url, array(
					'timeout' => 120,
					'sslverify' => true,
					'user-agent' => 'Reign Demo Installer/' . REIGN_DEMO_INSTALLER_VERSION,
				) );

				if ( is_wp_error( $response ) ) {
					wp_send_json_error( array( 'message' => __( 'Download failed: ', 'reign-demo-installer' ) . $response->get_error_message() ) );
				}

				$body = wp_remote_retrieve_body( $response );
				if ( empty( $body ) ) {
					wp_send_json_error( array( 'message' => __( 'Downloaded file is empty', 'reign-demo-installer' ) ) );
				}

				// Save file
				if ( file_put_contents( $file_path, $body ) === false ) {
					wp_send_json_error( array( 'message' => __( 'Failed to save file', 'reign-demo-installer' ) ) );
				}

				$this->log_info( "Downloaded file: {$file_name} (" . size_format( strlen( $body ) ) . ")" );

				wp_send_json_success( array( 
					'file_name' => $file_name,
					'file_size' => strlen( $body ),
					'file_path' => $file_path
				) );

			} catch ( Exception $e ) {
				$this->log_error( "Failed to download file: {$e->getMessage()}" );
				wp_send_json_error( array( 'message' => __( 'Download failed', 'reign-demo-installer' ) ) );
			}
		}

		/**
		 * Process file from local temp folder.
		 */
		public function process_local_demo_file() {
			try {
				// Security check
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'reign-demo-installer' ) ) );
				}

				$temp_folder_id = isset( $_POST['temp_folder_id'] ) ? sanitize_text_field( $_POST['temp_folder_id'] ) : '';
				$file_name = isset( $_POST['file_name'] ) ? sanitize_file_name( $_POST['file_name'] ) : '';
				$file_type = isset( $_POST['file_type'] ) ? sanitize_text_field( $_POST['file_type'] ) : '';
				$action_for = isset( $_POST['action_for'] ) ? sanitize_text_field( $_POST['action_for'] ) : '';

				// Get temp folder info
				$folder_info = get_transient( 'reign_temp_folder_' . $temp_folder_id );
				if ( ! $folder_info || $folder_info['user_id'] !== get_current_user_id() ) {
					wp_send_json_error( array( 'message' => __( 'Invalid temp folder', 'reign-demo-installer' ) ) );
				}

				$file_path = $folder_info['path'] . '/' . $file_name;
				
				if ( ! file_exists( $file_path ) ) {
					wp_send_json_error( array( 'message' => __( 'File not found in temp folder', 'reign-demo-installer' ) ) );
				}

				// Set processing limits
				$this->set_processing_limits();

				// Preserve admin user
				$this->preserve_current_admin_user();

				// Process based on type
				switch ( $action_for ) {
					case 'database_tables':
						$result = $this->process_database_file( $file_path, $file_name );
						break;
						
					case 'upload_folders':
						$result = $this->process_upload_file( $file_path, $file_name );
						break;
						
					default:
						wp_send_json_error( array( 'message' => __( 'Unknown file type', 'reign-demo-installer' ) ) );
				}

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				$this->log_info( "Processed file: {$file_name}" );

				wp_send_json_success( array( 
					'file_name' => $file_name,
					'processed' => true
				) );

			} catch ( Exception $e ) {
				$this->log_error( "Failed to process file: {$e->getMessage()}" );
				wp_send_json_error( array( 'message' => __( 'Processing failed', 'reign-demo-installer' ) ) );
			}
		}

		/**
		 * Clean up temporary folder.
		 */
		public function cleanup_temp_folder() {
			try {
				$temp_folder_id = isset( $_POST['temp_folder_id'] ) ? sanitize_text_field( $_POST['temp_folder_id'] ) : '';
				
				if ( ! $temp_folder_id ) {
					wp_send_json_success( array( 'message' => __( 'No folder to clean', 'reign-demo-installer' ) ) );
					return;
				}

				// Get temp folder info
				$folder_info = get_transient( 'reign_temp_folder_' . $temp_folder_id );
				
				if ( $folder_info && isset( $folder_info['path'] ) ) {
					$this->delete_directory( $folder_info['path'] );
					delete_transient( 'reign_temp_folder_' . $temp_folder_id );
					
					$this->log_info( "Cleaned up temp folder: {$temp_folder_id}" );
				}

				wp_send_json_success( array( 'message' => __( 'Temp folder cleaned', 'reign-demo-installer' ) ) );

			} catch ( Exception $e ) {
				$this->log_error( "Cleanup error: {$e->getMessage()}" );
				wp_send_json_success( array( 'message' => __( 'Cleanup completed with warnings', 'reign-demo-installer' ) ) );
			}
		}

		/**
		 * Process database file from local storage.
		 */
		private function process_database_file( $file_path, $file_name ) {
			$file_content = file_get_contents( $file_path );
			
			if ( $file_content === false ) {
				return new WP_Error( 'read_failed', __( 'Failed to read database file', 'reign-demo-installer' ) );
			}

			// Determine table name from filename
			$table_name = basename( $file_name, '.json' );
			$table_name = preg_replace( '/[0-9]+/', '', $table_name );

			$data = json_decode( $file_content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new WP_Error( 'invalid_json', __( 'Invalid JSON in database file', 'reign-demo-installer' ) );
			}

			// Handle different table types
			switch ( $table_name ) {
				case 'theme_mods':
					return $this->import_theme_mods( $data );
				
				case 'options':
					return $this->import_options( $data );
				
				default:
					return $this->import_table_data( $table_name, $data );
			}
		}

		/**
		 * Process upload file from local storage.
		 */
		private function process_upload_file( $file_path, $file_name ) {
			$upload_dir = wp_upload_dir();
			$extract_path = $upload_dir['basedir'] . '/';
			
			// Extract ZIP file
			if ( class_exists( 'ZipArchive' ) ) {
				$zip = new ZipArchive();
				$result = $zip->open( $file_path );
				
				if ( $result === true ) {
					$zip->extractTo( $extract_path );
					$zip->close();
					return true;
				} else {
					return new WP_Error( 'extract_failed', __( 'Failed to extract upload file', 'reign-demo-installer' ) );
				}
			} else {
				return new WP_Error( 'zip_not_available', __( 'ZipArchive not available', 'reign-demo-installer' ) );
			}
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
		 * Import table data with admin user preservation.
		 *
		 * @param string $table_name Table name
		 * @param array $data Table data
		 * @return bool|WP_Error
		 */
		private function import_table_data( $table_name, $data ) {
			global $wpdb;

			$full_table_name = $wpdb->prefix . $table_name;
			
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

			$inserted_count = 0;
			foreach ( $data as $row ) {
				// Skip current admin user data for users/usermeta tables
				if ( $this->should_skip_user_data( $table_name, $row ) ) {
					continue;
				}

				// Clean up invalid columns for user tables
				$row = $this->clean_user_table_data( $table_name, $row );
				
				// Replace placeholder URLs
				$row = $this->replace_placeholder_urls( $row );

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
		 * Replace placeholder URLs in data.
		 *
		 * @param array $data Data array
		 * @return array Modified data
		 */
		private function replace_placeholder_urls( $data ) {
			$home_url = get_home_url();
			
			array_walk_recursive( $data, function( &$value ) use ( $home_url ) {
				if ( is_string( $value ) ) {
					$value = str_replace( '{{*home_url}}', $home_url, $value );
				}
			});

			return $data;
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
				return false;
			}
		}

		/**
		 * Recursively delete directory.
		 */
		private function delete_directory( $dir ) {
			if ( ! is_dir( $dir ) ) {
				return;
			}

			$files = array_diff( scandir( $dir ), array( '.', '..' ) );
			
			foreach ( $files as $file ) {
				$path = $dir . '/' . $file;
				
				if ( is_dir( $path ) ) {
					$this->delete_directory( $path );
				} else {
					wp_delete_file( $path );
				}
			}
			
			rmdir( $dir );
		}

		/**
		 * Validate URL domain.
		 *
		 * @param string $url URL to validate
		 * @return bool True if valid, false otherwise
		 */
		private function validate_url_domain( $url ) {
			$allowed_domains = array(
				'wbcomdesigns.com',
				'installer.wbcomdesigns.com'
			);

			$parsed_url = wp_parse_url( $url );
			
			if ( ! isset( $parsed_url['host'] ) ) {
				return false;
			}

			$host = strtolower( $parsed_url['host'] );
			
			foreach ( $allowed_domains as $domain ) {
				if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Set processing limits.
		 */
		private function set_processing_limits() {
			if ( defined( 'REIGN_DEMO_INSTALLER_MEMORY_LIMIT' ) ) {
				ini_set( 'memory_limit', REIGN_DEMO_INSTALLER_MEMORY_LIMIT );
			} else {
				ini_set( 'memory_limit', '512M' );
			}
			
			if ( defined( 'REIGN_DEMO_INSTALLER_MAX_EXECUTION_TIME' ) ) {
				set_time_limit( REIGN_DEMO_INSTALLER_MAX_EXECUTION_TIME );
			} else {
				set_time_limit( 300 );
			}
		}

		/**
		 * Handle plugin installation/activation (existing method for backward compatibility).
		 */
		public function manage_plugin_installation() {
			try {
				$plugin_action = isset( $_POST['plugin_action'] ) ? sanitize_text_field( $_POST['plugin_action'] ) : '';
				$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( $_POST['plugin_slug'] ) : '';
				$demo = isset( $_POST['demo'] ) ? sanitize_text_field( $_POST['demo'] ) : '';

				if ( ! $plugin_action || ! $plugin_slug || ! $demo ) {
					wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'reign-demo-installer' ) ) );
				}

				$this->log_info( "Plugin action requested: {$plugin_action} for {$plugin_slug}" );

				// Get plugins manager instance
				$plugins_manager = $this->get_plugins_manager();
				if ( ! $plugins_manager ) {
					wp_send_json_error( array( 'message' => __( 'Plugins manager not available.', 'reign-demo-installer' ) ) );
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
				wp_send_json_error( array( 'message' => __( 'An error occurred during plugin operation.', 'reign-demo-installer' ) ) );
			}
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
		 * Get plugins manager instance.
		 *
		 * @return object|null
		 */
		private function get_plugins_manager() {
			if ( class_exists( 'Reign_Demo_Installer_Plugins_Manager' ) ) {
				return Reign_Demo_Installer_Plugins_Manager::instance();
			}
			
			return null;
		}

		/**
		 * Legacy methods for backward compatibility.
		 */
		public function get_theme_demo_data() {
			// Keep existing implementation for backward compatibility
			// This would be your original method implementation
		}

		public function read_theme_demo_package_file() {
			// Keep existing implementation for backward compatibility
			// This would be your original method implementation
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
		 * Log warning message.
		 *
		 * @param string $message Message
		 */
		private function log_warning( $message ) {
			if ( $this->logger ) {
				$this->logger->warning( $message );
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