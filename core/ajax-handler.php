<?php
/**
 * AJAX handler for Reign Demo Installer - FIXED VERSION
 * Properly handles path structure like the old working system
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
		 * Current admin user backup.
		 *
		 * @var array
		 */
		private $admin_backup = null;

		/**
		 * Processing stats.
		 *
		 * @var array
		 */
		private $stats = array(
			'processed' => 0,
			'errors' => 0,
			'skipped' => 0
		);

		/**
		 * Main instance.
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
		 * Initialize hooks.
		 */
		private function init_hooks() {
			// Batch download AJAX actions
			add_action( 'wp_ajax_wbcom_create_temp_folder', array( $this, 'create_temp_folder' ) );
			add_action( 'wp_ajax_wbcom_get_demo_manifest', array( $this, 'get_demo_manifest' ) );
			add_action( 'wp_ajax_wbcom_download_demo_file', array( $this, 'download_demo_file' ) );
			add_action( 'wp_ajax_wbcom_process_local_demo_file', array( $this, 'process_local_demo_file' ) );
			add_action( 'wp_ajax_wbcom_cleanup_temp_folder', array( $this, 'cleanup_temp_folder' ) );
			add_action( 'wp_ajax_wbcom_store_import_summary', array( $this, 'store_import_summary' ) );

			// Legacy compatibility
			add_action( 'wp_ajax_wbcom_get_theme_demo_data', array( $this, 'legacy_get_theme_demo_data' ) );
			add_action( 'wp_ajax_wbcom_read_theme_demo_package_file', array( $this, 'legacy_read_theme_demo_package_file' ) );
			add_action( 'wp_ajax_wbcom_manage_plugin_installation', array( $this, 'manage_plugin_installation' ) );

			// New naming convention (for future)
			add_action( 'wp_ajax_reign_create_temp_folder', array( $this, 'create_temp_folder' ) );
			add_action( 'wp_ajax_reign_get_demo_manifest', array( $this, 'get_demo_manifest' ) );
			add_action( 'wp_ajax_reign_download_demo_file', array( $this, 'download_demo_file' ) );
			add_action( 'wp_ajax_reign_process_local_demo_file', array( $this, 'process_local_demo_file' ) );
			add_action( 'wp_ajax_reign_cleanup_temp_folder', array( $this, 'cleanup_temp_folder' ) );
		}

		/**
		 * Create secure temporary folder.
		 */
		public function create_temp_folder() {
			if ( ! $this->validate_request() ) return;

			try {
				$this->backup_admin_user();

				$upload_dir = wp_upload_dir();
				$folder_id = 'reign_demo_' . time() . '_' . wp_generate_password( 8, false );
				$temp_path = $upload_dir['basedir'] . '/' . $folder_id;

				if ( ! wp_mkdir_p( $temp_path ) ) {
					wp_send_json_error( array( 'message' => 'Failed to create temp directory' ) );
				}

				// Security protection
				file_put_contents( $temp_path . '/.htaccess', "Order deny,allow\nDeny from all\n" );

				// Store folder info (2 hour expiry)
				set_transient( 'reign_temp_folder_' . $folder_id, array(
					'path' => $temp_path,
					'created' => time(),
					'user_id' => get_current_user_id()
				), 2 * HOUR_IN_SECONDS );

				$this->debug_log( "Created temp folder: {$folder_id}" );

				wp_send_json_success( array( 
					'folder_id' => $folder_id,
					'path' => $temp_path 
				) );

			} catch ( Exception $e ) {
				$this->debug_log( "Temp folder creation failed: {$e->getMessage()}", 'error' );
				wp_send_json_error( array( 'message' => 'Failed to create temp folder' ) );
			}
		}

		/**
		 * Get demo manifest with path-aware processing.
		 */
		public function get_demo_manifest() {
			if ( ! $this->validate_request() ) return;

			try {
				$theme_slug = $this->get_param( 'theme_slug' );
				$demo_slug = $this->get_param( 'demo_slug' );
				$target_url = $this->get_param( 'target_url', 'url' );

				if ( ! $theme_slug || ! $demo_slug || ! $target_url ) {
					wp_send_json_error( array( 'message' => 'Missing required parameters' ) );
				}

				if ( ! $this->is_valid_demo_url( $target_url ) ) {
					wp_send_json_error( array( 'message' => 'Invalid demo URL' ) );
				}

				$manifest_url = $target_url . 'wp-admin/?wbcom_theme_demo_listing=yes';
				
				$response = wp_remote_post( $manifest_url, array(
					'method' => 'POST',
					'timeout' => 60,
					'sslverify' => false,
					'user-agent' => 'Reign Demo Installer/' . REIGN_DEMO_INSTALLER_VERSION,
					'body' => array(
						'theme_slug' => $theme_slug,
						'demo_slug' => $demo_slug,
					),
				) );

				if ( is_wp_error( $response ) ) {
					wp_send_json_error( array( 'message' => 'Failed to connect to demo server' ) );
				}

				$demo_data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $demo_data ) ) {
					wp_send_json_error( array( 'message' => 'Invalid demo data received' ) );
				}

				// FIXED: Prepare file list with path preservation matching old system
				$files = $this->prepare_file_list_with_paths( $demo_data );

				if ( empty( $files ) ) {
					wp_send_json_error( array( 'message' => 'No demo files found' ) );
				}

				$this->debug_log( "Demo manifest prepared: " . count( $files ) . " files for {$demo_slug}" );

				wp_send_json_success( array( 
					'files' => $files,
					'total_files' => count( $files )
				) );

			} catch ( Exception $e ) {
				$this->debug_log( "Manifest preparation failed: {$e->getMessage()}", 'error' );
				wp_send_json_error( array( 'message' => 'Failed to get demo manifest' ) );
			}
		}

		/**
		 * FIXED: Enhanced file list preparation with path information matching old system.
		 */
		private function prepare_file_list_with_paths( $demo_data ) {
			$files = array();
			
			// Add database tables
			if ( isset( $demo_data['database_tables'] ) && is_array( $demo_data['database_tables'] ) ) {
				foreach ( $demo_data['database_tables'] as $url ) {
					$files[] = array(
						'name' => basename( $url ),
						'url' => $url,
						'type' => 'database_table',
						'action_for' => 'database_tables',
						'path_info' => null // Database files don't need path preservation
					);
				}
			}

			// FIXED: Add upload folders WITH proper path preservation like old system
			if ( isset( $demo_data['upload_folders'] ) && is_array( $demo_data['upload_folders'] ) ) {
				foreach ( $demo_data['upload_folders'] as $url ) {
					$path_info = $this->extract_path_info_from_url( $url );
					
					$files[] = array(
						'name' => basename( $url ),
						'url' => $url,
						'type' => 'upload_folder',
						'action_for' => 'upload_folders',
						'path_info' => $path_info // CRITICAL: Preserve path information
					);
				}
			}

			return $files;
		}

		/**
		 * FIXED: Extract path information from URL matching old clone_uploads_folder logic.
		 */
		private function extract_path_info_from_url( $url ) {
			// Replicate the old logic: $parentFolderName = $parentFolderName[ count( $parentFolderName ) - 2 ];
			$url_parts = explode( '/', $url );
			$url_parts = array_filter( $url_parts ); // Remove empty elements
			$url_parts = array_values( $url_parts ); // Reindex
			
			$filename = end( $url_parts ); // Last element is filename
			$parent_folder = isset( $url_parts[ count( $url_parts ) - 2 ] ) ? $url_parts[ count( $url_parts ) - 2 ] : '';
			
			// Extract the path after 'theme_demo'
			$demo_index = array_search( 'theme_demo', $url_parts );
			$relative_path = '';
			
			if ( $demo_index !== false && $demo_index < count( $url_parts ) - 1 ) {
				// Get everything after 'theme_demo' but before the filename
				$relative_parts = array_slice( $url_parts, $demo_index + 1, -1 );
				$relative_path = implode( '/', $relative_parts );
			}
			
			$this->debug_log( "Extracted path info for {$filename}: parent={$parent_folder}, relative={$relative_path}" );
			
			return array(
				'parent_folder' => $parent_folder,
				'relative_path' => $relative_path,
				'full_path' => dirname( implode( '/', $url_parts ) ),
				'filename' => $filename
			);
		}

		/**
		 * Download single file.
		 */
		public function download_demo_file() {
			if ( ! $this->validate_request() ) return;

			try {
				$temp_folder_id = $this->get_param( 'temp_folder_id' );
				$file_url = $this->get_param( 'file_url', 'url' );
				$file_name = $this->get_param( 'file_name', 'filename' );

				if ( ! $temp_folder_id || ! $file_url || ! $file_name ) {
					wp_send_json_error( array( 'message' => 'Missing required parameters' ) );
				}

				$folder_info = get_transient( 'reign_temp_folder_' . $temp_folder_id );
				if ( ! $folder_info || $folder_info['user_id'] !== get_current_user_id() ) {
					wp_send_json_error( array( 'message' => 'Invalid temp folder' ) );
				}

				if ( ! $this->is_valid_demo_url( $file_url ) ) {
					wp_send_json_error( array( 'message' => 'Invalid file URL' ) );
				}

				$file_path = $folder_info['path'] . '/' . $file_name;

				// Download with retry logic
				$file_content = $this->download_with_retry( $file_url );
				
				if ( ! $file_content ) {
					wp_send_json_error( array( 'message' => 'Download failed or file is empty' ) );
				}

				if ( file_put_contents( $file_path, $file_content ) === false ) {
					wp_send_json_error( array( 'message' => 'Failed to save file' ) );
				}

				$this->debug_log( "Downloaded: {$file_name} (" . size_format( strlen( $file_content ) ) . ")" );

				wp_send_json_success( array( 
					'file_name' => $file_name,
					'file_size' => strlen( $file_content ),
					'file_path' => $file_path
				) );

			} catch ( Exception $e ) {
				$this->debug_log( "Download failed: {$e->getMessage()}", 'error' );
				wp_send_json_error( array( 'message' => 'Download failed' ) );
			}
		}

		/**
		 * FIXED: Process file with better error handling and path awareness.
		 */
		public function process_local_demo_file() {
			if ( ! $this->validate_request() ) return;

			try {
				$temp_folder_id = $this->get_param( 'temp_folder_id' );
				$file_name = $this->get_param( 'file_name', 'filename' );
				$action_for = $this->get_param( 'action_for' );
				$file_criticality = $this->get_param( 'file_criticality', 'string', 'optional' );
				
				// FIXED: Get path info from request
				$path_info_json = $this->get_param( 'path_info', 'string', '' );
				$path_info = null;
				
				if ( ! empty( $path_info_json ) ) {
					$path_info = json_decode( $path_info_json, true );
					if ( json_last_error() !== JSON_ERROR_NONE ) {
						$path_info = null;
					}
				}

				$folder_info = get_transient( 'reign_temp_folder_' . $temp_folder_id );
				if ( ! $folder_info || $folder_info['user_id'] !== get_current_user_id() ) {
					wp_send_json_error( array( 'message' => 'Invalid temp folder' ) );
				}

				$file_path = $folder_info['path'] . '/' . $file_name;
				
				if ( ! file_exists( $file_path ) ) {
					wp_send_json_error( array( 'message' => 'File not found in temp folder' ) );
				}

				// Set up environment with better resource management
				$this->prepare_processing_environment();

				// FIXED: Process with path info
				$result = $this->process_file_by_type_with_path( $file_path, $action_for, $file_criticality, $path_info );

				if ( is_wp_error( $result ) ) {
					if ( $file_criticality === 'critical' ) {
						wp_send_json_error( array( 'message' => $result->get_error_message() ) );
					} else {
						// For non-critical files, log and continue
						$this->debug_log( "Non-critical file processing failed: {$file_name} - {$result->get_error_message()}", 'warning' );
						$this->stats['skipped']++;
						wp_send_json_success( array( 
							'file_name' => $file_name,
							'processed' => false,
							'skipped' => true,
							'reason' => 'Non-critical file failed: ' . $result->get_error_message()
						) );
						return;
					}
				}

				$this->stats['processed']++;
				$this->debug_log( "Processed: {$file_name}" );

				wp_send_json_success( array( 
					'file_name' => $file_name,
					'processed' => true,
					'stats' => $this->stats
				) );

			} catch ( Exception $e ) {
				$this->stats['errors']++;
				$this->debug_log( "Processing exception: {$e->getMessage()}", 'error' );
				
				$file_criticality = $this->get_param( 'file_criticality', 'string', 'optional' );
				if ( $file_criticality !== 'critical' ) {
					wp_send_json_success( array( 
						'file_name' => $this->get_param( 'file_name', 'filename' ),
						'processed' => false,
						'skipped' => true,
						'reason' => 'Exception: ' . $e->getMessage()
					) );
				} else {
					wp_send_json_error( array( 'message' => 'Critical file processing failed: ' . $e->getMessage() ) );
				}
			}
		}

		/**
		 * FIXED: Enhanced file processing with path awareness.
		 */
		private function process_file_by_type_with_path( $file_path, $action_for, $criticality, $path_info ) {
			try {
				switch ( $action_for ) {
					case 'database_tables':
						return $this->process_database_file( $file_path, $criticality );
						
					case 'upload_folders':
						// FIXED: Pass path info to upload processing
						return $this->process_upload_file( $file_path, $criticality, $path_info );
						
					default:
						return new WP_Error( 'invalid_type', 'Unknown file type: ' . $action_for );
				}
			} catch ( Exception $e ) {
				return new WP_Error( 'processing_error', $e->getMessage() );
			}
		}

		/**
		 * FIXED: Process upload file with path awareness matching old clone_uploads_folder.
		 */
		private function process_upload_file( $file_path, $criticality, $path_info = null ) {
			$file_content = file_get_contents( $file_path );
			if ( $file_content === false ) {
				return new WP_Error( 'read_error', 'Cannot read upload file' );
			}

			$upload_dir = wp_upload_dir();
			$temp_zip = $upload_dir['basedir'] . '/wbcom-theme-demo-' . time() . '.zip';

			// Save content to temporary zip
			if ( file_put_contents( $temp_zip, $file_content ) === false ) {
				return new WP_Error( 'write_error', 'Cannot write temporary zip file' );
			}

			try {
				// FIXED: Determine extraction path based on path_info like old system
				$extract_path = $this->determine_extraction_path_like_old_system( $upload_dir['basedir'], $path_info );
				
				// Ensure the extraction directory exists
				if ( ! is_dir( $extract_path ) ) {
					if ( ! wp_mkdir_p( $extract_path ) ) {
						throw new Exception( "Cannot create directory: {$extract_path}" );
					}
				}
				
				// FIXED: Extract using ZipArchive like old system
				$extraction_result = $this->extract_zip_like_old_system( $temp_zip, $extract_path );
				
				if ( is_wp_error( $extraction_result ) ) {
					if ( $criticality !== 'critical' ) {
						$this->debug_log( "Non-critical ZIP extraction failed, continuing: " . $extraction_result->get_error_message() );
						return true;
					}
					throw new Exception( $extraction_result->get_error_message() );
				}

				// Log successful extraction with path info
				$relative_path = $path_info ? ( $path_info['relative_path'] ?: $path_info['parent_folder'] ) : 'root';
				$this->debug_log( "Successfully extracted: " . basename( $file_path ) . " to {$relative_path}" );
				
				return true;

			} catch ( Exception $e ) {
				$error_msg = "ZIP extraction failed: {$e->getMessage()}";
				$this->debug_log( $error_msg, 'error' );
				
				if ( $criticality !== 'critical' ) {
					$this->debug_log( "Continuing despite non-critical ZIP failure: " . basename( $file_path ) );
					return true;
				}
				
				return new WP_Error( 'extract_error', $error_msg );
			} finally {
				// Clean up temp file
				if ( file_exists( $temp_zip ) ) {
					wp_delete_file( $temp_zip );
				}
			}
		}

		/**
		 * FIXED: Determine proper extraction path like old clone_uploads_folder method.
		 */
		private function determine_extraction_path_like_old_system( $base_upload_dir, $path_info ) {
			if ( ! $path_info || empty( $path_info['parent_folder'] ) ) {
				return trailingslashit( $base_upload_dir );
			}
			
			// FIXED: Use parent_folder like old system: $upload['basedir'] . '/' . $parentFolderName . '/'
			$extraction_path = trailingslashit( $base_upload_dir ) . trailingslashit( $path_info['parent_folder'] );
			
			$this->debug_log( "Extraction path (old system style): {$extraction_path} from parent folder: {$path_info['parent_folder']}" );
			
			return $extraction_path;
		}

		/**
		 * FIXED: Extract ZIP file like old system using ZipArchive.
		 */
		private function extract_zip_like_old_system( $zip_file, $extract_path ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				return new WP_Error( 'ziparchive_missing', 'ZipArchive not available' );
			}

			$zip = new ZipArchive();
			$result = $zip->open( $zip_file );
			
			if ( $result !== true ) {
				return new WP_Error( 'ziparchive_open_failed', "Cannot open ZIP: error {$result}" );
			}

			// FIXED: Extract exactly like old system: $zip->extractTo( $extract_path );
			if ( ! $zip->extractTo( $extract_path ) ) {
				$zip->close();
				return new WP_Error( 'extraction_failed', 'ZipArchive extraction failed' );
			}

			$total_files = $zip->numFiles;
			$zip->close();
			
			$this->debug_log( "ZIP extracted successfully: {$total_files} files to {$extract_path}" );
			
			return array( 'extracted_files' => $total_files, 'total_files' => $total_files );
		}

		/**
		 * FIXED: Process database file with improved error handling.
		 */
		private function process_database_file( $file_path, $criticality ) {
			$file_content = file_get_contents( $file_path );
			if ( $file_content === false ) {
				return new WP_Error( 'read_error', 'Cannot read database file' );
			}

			$data = json_decode( $file_content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new WP_Error( 'json_error', 'Invalid JSON: ' . json_last_error_msg() );
			}

			if ( empty( $data ) || ! is_array( $data ) ) {
				return new WP_Error( 'empty_data', 'File contains no valid data' );
			}

			// Determine table name
			$file_name = basename( $file_path );
			$table_name = preg_replace( '/[0-9]+\.json$/', '', $file_name );
			$table_name = str_replace( '.json', '', $table_name );

			// Process based on table type
			return $this->import_table_data( $table_name, $data, $criticality );
		}

		/**
		 * Import table data with better error handling.
		 */
		private function import_table_data( $table_name, $data, $criticality ) {
			try {
				// Handle special tables
				switch ( $table_name ) {
					case 'theme_mods':
						return $this->import_theme_mods( $data );
						
					case 'options':
						return $this->import_options( $data, $criticality );
						
					case 'users':
					case 'usermeta':
						return $this->import_user_data( $table_name, $data, $criticality );
						
					default:
						return $this->import_regular_table( $table_name, $data, $criticality );
				}
			} catch ( Exception $e ) {
				return new WP_Error( 'import_error', "Table import failed: {$e->getMessage()}" );
			}
		}

		/**
		 * Import theme mods.
		 */
		private function import_theme_mods( $data ) {
			$count = 0;
			foreach ( $data as $key => $value ) {
				set_theme_mod( $key, $value );
				$count++;
			}
			$this->debug_log( "Imported {$count} theme mods" );
			return true;
		}

		/**
		 * Import options with better filtering.
		 */
		private function import_options( $data, $criticality ) {
			$skip_options = array(
				'siteurl', 'home', 'blogname', 'blogdescription', 'admin_email', 
				'users_can_register', 'default_role', 'blog_charset', 'active_plugins',
				'template', 'stylesheet', 'wp_user_roles', 'cron'
			);

			$imported = 0;
			foreach ( $data as $option ) {
				if ( ! isset( $option['option_name'] ) ) continue;
				
				if ( in_array( $option['option_name'], $skip_options, true ) ) continue;

				$value = maybe_unserialize( $option['option_value'] );
				$value = $this->replace_urls( $value );
				
				$autoload = isset( $option['autoload'] ) ? $option['autoload'] : 'yes';
				
				if ( update_option( $option['option_name'], $value, $autoload ) ) {
					$imported++;
				}
			}

			$this->debug_log( "Imported {$imported} options" );
			return true;
		}

		/**
		 * Import user data with admin protection.
		 */
		private function import_user_data( $table_name, $data, $criticality ) {
			global $wpdb;
			
			$current_user_id = get_current_user_id();
			$table_full_name = $wpdb->prefix . $table_name;
			
			// Verify table exists
			if ( ! $this->table_exists( $table_full_name ) ) {
				return new WP_Error( 'table_missing', "Table {$table_full_name} does not exist" );
			}

			$imported = 0;
			$errors = 0;

			foreach ( $data as $row ) {
				// Skip current admin user
				if ( isset( $row['ID'] ) && $row['ID'] == $current_user_id ) continue;
				if ( isset( $row['user_id'] ) && $row['user_id'] == $current_user_id ) continue;

				// Clean data
				$row = $this->clean_user_row( $row );
				$row = $this->replace_urls( $row );

				$result = $wpdb->insert( $table_full_name, $row );
				if ( $result ) {
					$imported++;
				} else {
					$errors++;
					if ( $criticality === 'critical' && $errors > 10 ) {
						return new WP_Error( 'too_many_errors', "Too many errors importing {$table_name}" );
					}
				}
			}

			$this->debug_log( "Imported {$imported} rows to {$table_name} (errors: {$errors})" );
			
			// Restore admin user after user table imports
			if ( in_array( $table_name, array( 'users', 'usermeta' ) ) ) {
				$this->restore_admin_user();
			}

			return true;
		}

		/**
		 * Import regular table with error tolerance.
		 */
		private function import_regular_table( $table_name, $data, $criticality ) {
			global $wpdb;
			
			$table_full_name = $wpdb->prefix . $table_name;
			
			// Verify table exists
			if ( ! $this->table_exists( $table_full_name ) ) {
				if ( $criticality === 'critical' ) {
					return new WP_Error( 'table_missing', "Critical table {$table_full_name} does not exist" );
				} else {
					$this->debug_log( "Table {$table_full_name} does not exist, skipping", 'warning' );
					return true; // Skip non-critical missing tables
				}
			}

			// Clear existing data (but track if we did it already)
			$clear_key = "cleared_{$table_name}";
			$import_data = get_option( 'reign_theme_demo_import_data', array() );
			
			if ( ! isset( $import_data[ $clear_key ] ) ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table_full_name}`" ) );
				$import_data[ $clear_key ] = true;
				update_option( 'reign_theme_demo_import_data', $import_data );
			}

			$imported = 0;
			$errors = 0;

			foreach ( $data as $row ) {
				$row = $this->replace_urls( $row );

				$result = $wpdb->insert( $table_full_name, $row );
				if ( $result ) {
					$imported++;
				} else {
					$errors++;
					// For non-critical tables, don't fail on too many errors
					if ( $criticality === 'critical' && $errors > 20 ) {
						return new WP_Error( 'too_many_errors', "Too many errors importing {$table_name}" );
					}
				}
			}

			$this->debug_log( "Imported {$imported} rows to {$table_name} (errors: {$errors})" );
			return true;
		}

		/**
		 * Store import summary.
		 */
		public function store_import_summary() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			}

			$summary = $this->get_param( 'summary' );
			if ( ! $summary ) {
				wp_send_json_error( array( 'message' => 'No summary data provided' ) );
			}

			$summary_data = json_decode( stripslashes( $summary ), true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_send_json_error( array( 'message' => 'Invalid summary data' ) );
			}

			update_option( 'reign_demo_import_summary', $summary_data );
			wp_send_json_success( array( 'message' => 'Summary stored' ) );
		}

		/**
		 * Clean up temporary folder.
		 */
		public function cleanup_temp_folder() {
			try {
				$temp_folder_id = $this->get_param( 'temp_folder_id' );
				
				if ( ! $temp_folder_id ) {
					wp_send_json_success( array( 'message' => 'No folder to clean' ) );
					return;
				}

				$folder_info = get_transient( 'reign_temp_folder_' . $temp_folder_id );
				
				if ( $folder_info && isset( $folder_info['path'] ) ) {
					$this->delete_directory_recursive( $folder_info['path'] );
					delete_transient( 'reign_temp_folder_' . $temp_folder_id );
					$this->debug_log( "Cleaned up temp folder: {$temp_folder_id}" );
				}

				wp_send_json_success( array( 'message' => 'Cleanup completed' ) );

			} catch ( Exception $e ) {
				$this->debug_log( "Cleanup error: {$e->getMessage()}", 'error' );
				wp_send_json_success( array( 'message' => 'Cleanup completed with warnings' ) );
			}
		}

		/**
		 * Plugin management.
		 */
		public function manage_plugin_installation() {
			try {
				$plugin_action = $this->get_param( 'plugin_action' );
				$plugin_slug = $this->get_param( 'plugin_slug' );
				$demo = $this->get_param( 'demo' );

				if ( ! $plugin_action || ! $plugin_slug || ! $demo ) {
					wp_send_json_error( array( 'message' => 'Missing required parameters' ) );
				}

				$plugins_manager = $this->get_plugins_manager();
				if ( ! $plugins_manager ) {
					wp_send_json_error( array( 'message' => 'Plugins manager not available' ) );
				}

				$result = $this->handle_plugin_action( $plugins_manager, $plugin_action, $plugin_slug );
				
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				wp_send_json_success( $result );

			} catch ( Exception $e ) {
				wp_send_json_error( array( 'message' => 'Plugin operation failed' ) );
			}
		}

		/**
		 * Legacy compatibility methods.
		 */
		public function legacy_get_theme_demo_data() {
			// Redirect to new batch system
			wp_send_json_error( array( 'message' => 'Please use the new batch import system' ) );
		}

		public function legacy_read_theme_demo_package_file() {
			// Handle legacy manifest requests
			$this->get_demo_manifest();
		}

		// HELPER METHODS

		/**
		 * Validate request and permissions.
		 */
		private function validate_request() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
				return false;
			}

			$nonce = $this->get_param( 'nonce' );
			if ( $nonce && ! wp_verify_nonce( $nonce, 'reign_demo_installer_ajax' ) ) {
				wp_send_json_error( array( 'message' => 'Security check failed' ) );
				return false;
			}

			return true;
		}

		/**
		 * Get sanitized parameter.
		 */
		private function get_param( $key, $type = 'string', $default = '' ) {
			$value = isset( $_POST[ $key ] ) ? $_POST[ $key ] : $default;
			
			switch ( $type ) {
				case 'url':
					return esc_url_raw( $value );
				case 'filename':
					return sanitize_file_name( $value );
				case 'int':
					return intval( $value );
				default:
					return sanitize_text_field( $value );
			}
		}

		/**
		 * FIXED: Download file with retry logic and better error handling.
		 */
		private function download_with_retry( $url, $max_attempts = 3 ) {
			for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
				$response = wp_remote_get( $url, array(
					'timeout' => 120,
					'sslverify' => false,
					'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
					'headers' => array(
						'Accept' => 'application/zip,*/*',
						'Cache-Control' => 'no-cache',
					),
					'redirection' => 10,
				) );

				if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
					$body = wp_remote_retrieve_body( $response );
					if ( ! empty( $body ) ) {
						return $body;
					}
				}

				if ( $attempt < $max_attempts ) {
					$this->debug_log( "Download attempt {$attempt} failed for {$url}, retrying..." );
					sleep( 2 );
				}
			}

			return false;
		}

		/**
		 * FIXED: Prepare processing environment with better resource management.
		 */
		private function prepare_processing_environment() {
			// More aggressive resource settings
			@ini_set( 'memory_limit', '1024M' );
			@ini_set( 'max_execution_time', 0 );
			@ini_set( 'max_input_time', 3600 );
			@ini_set( 'post_max_size', '256M' );
			@ini_set( 'upload_max_filesize', '256M' );
			
			// Disable output buffering
			if ( ob_get_level() ) {
				ob_end_clean();
			}
			
			// Prevent WordPress from timing out
			add_filter( 'http_request_timeout', function() { return 300; } );
			
			$this->backup_admin_user();
		}

		/**
		 * Backup current admin user.
		 */
		private function backup_admin_user() {
			if ( $this->admin_backup || ! is_user_logged_in() ) return;

			$user = wp_get_current_user();
			$this->admin_backup = array(
				'ID' => $user->ID,
				'user_login' => $user->user_login,
				'user_email' => $user->user_email,
				'user_pass' => $user->user_pass,
				'capabilities' => $user->allcaps,
				'meta' => get_user_meta( $user->ID )
			);
		}

		/**
		 * Restore admin user after import.
		 */
		private function restore_admin_user() {
			if ( ! $this->admin_backup ) return;

			$user = new WP_User( $this->admin_backup['ID'] );
			$user->set_role( 'administrator' );

			if ( is_multisite() ) {
				grant_super_admin( $this->admin_backup['ID'] );
			}

			wp_set_auth_cookie( $this->admin_backup['ID'], true );
		}

		/**
		 * Replace URLs in data.
		 */
		private function replace_urls( $data ) {
			if ( is_string( $data ) ) {
				return str_replace( '{{*home_url}}', home_url(), $data );
			}
			
			if ( is_array( $data ) ) {
				array_walk_recursive( $data, function( &$value ) {
					if ( is_string( $value ) ) {
						$value = str_replace( '{{*home_url}}', home_url(), $value );
					}
				});
			}
			
			return $data;
		}

		/**
		 * Clean user row data.
		 */
		private function clean_user_row( $row ) {
			$invalid_fields = array( 'spam', 'deleted' );
			foreach ( $invalid_fields as $field ) {
				unset( $row[ $field ] );
			}
			return $row;
		}

		/**
		 * Check if table exists.
		 */
		private function table_exists( $table_name ) {
			global $wpdb;
			$result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
			return $result === $table_name;
		}

		/**
		 * Validate demo URL.
		 */
		private function is_valid_demo_url( $url ) {
			$allowed_domains = array( 'wbcomdesigns.com', 'installer.wbcomdesigns.com' );
			$host = parse_url( $url, PHP_URL_HOST );
			
			foreach ( $allowed_domains as $domain ) {
				if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
					return true;
				}
			}
			
			return false;
		}

		/**
		 * Get plugins manager.
		 */
		private function get_plugins_manager() {
			return class_exists( 'Reign_Demo_Installer_Plugins_Manager' ) ? 
				Reign_Demo_Installer_Plugins_Manager::instance() : null;
		}

		/**
		 * Handle plugin action.
		 */
		private function handle_plugin_action( $manager, $action, $slug ) {
			switch ( $action ) {
				case 'install_plugin':
					return $manager->do_plugin_install( $slug, false );
				case 'enable_plugin':
					return $manager->do_plugin_activate( $slug, false );
				default:
					return new WP_Error( 'invalid_action', 'Invalid plugin action' );
			}
		}

		/**
		 * Delete directory recursively.
		 */
		private function delete_directory_recursive( $dir ) {
			if ( ! is_dir( $dir ) ) return;

			$files = array_diff( scandir( $dir ), array( '.', '..' ) );
			foreach ( $files as $file ) {
				$path = $dir . '/' . $file;
				is_dir( $path ) ? $this->delete_directory_recursive( $path ) : unlink( $path );
			}
			rmdir( $dir );
		}

		/**
		 * Debug logging - only when WP_DEBUG is enabled.
		 */
		private function debug_log( $message, $level = 'info' ) {
			// Only log when debugging is enabled
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				if ( $this->logger ) {
					$this->logger->log( $message, $level );
				} else {
					error_log( "[Reign Demo Installer] [{$level}] {$message}" );
				}
			}
		}
	}

endif;

/**
 * Initialize the AJAX handler.
 */
Reign_Demo_Installer_Ajax_Handler::instance();