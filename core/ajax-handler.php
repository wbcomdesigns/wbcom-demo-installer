<?php
/**
 * AJAX handler for demo import operations.
 *
 * @package WBCOM_Theme_Demo_Installer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WBCOM_Demo_Importer_Ajax_Handler' ) ) :

	/**
	 * WBCOM_Demo_Importer_Ajax_Handler class.
	 *
	 * @class WBCOM_Demo_Importer_Ajax_Handler
	 * @version 1.0.0
	 */
	class WBCOM_Demo_Importer_Ajax_Handler {

		/**
		 * The single instance of the class.
		 *
		 * @var WBCOM_Demo_Importer_Ajax_Handler
		 * @since 1.0.0
		 */
		protected static $_instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * Main WBCOM_Demo_Importer_Ajax_Handler Instance.
		 *
		 * Ensures only one instance of WBCOM_Demo_Importer_Ajax_Handler is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @see WBCOM_Demo_Importer_Ajax_Handler()
		 * @return WBCOM_Demo_Importer_Ajax_Handler - Main instance.
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}


		/**
		 * WBCOM_Demo_Importer_Ajax_Handler Constructor.
		 */
		public function __construct() {
			$this->init_hooks();
			$this->includes();
		}

		/**
		 * Include required files.
		 */
		private function includes() {
			$plugin_dir = plugin_dir_path( __DIR__ );
			if ( file_exists( $plugin_dir . 'includes/class-buddypress-components-enabler.php' ) ) {
				require_once $plugin_dir . 'includes/class-buddypress-components-enabler.php';
			}
		}

		/**
		 * Hook into actions and filters.
		 *
		 * @since  1.0.0
		 */
		private function init_hooks() {
			add_action( 'wp_ajax_wbcom_get_theme_demo_data', array( $this, 'wbcom_get_theme_demo_data' ) );
			add_action( 'wp_ajax_wbcom_read_theme_demo_package_file', array( $this, 'wbcom_read_theme_demo_package_file' ) );
			add_action( 'wp_ajax_wbcom_get_demo_plugins_data', array( $this, 'wbcom_get_demo_plugins_data' ) );
			add_action( 'wp_ajax_wbcom_demo_import_finalize', array( $this, 'wbcom_demo_import_finalize' ) );
			add_action( 'wp_ajax_wbcom_enable_buddypress_components', array( $this, 'wbcom_enable_buddypress_components' ) );
		}

		/**
		 * Read theme demo package file via AJAX.
		 *
		 * @return void
		 */
		public function wbcom_read_theme_demo_package_file() {
			// Disable error reporting to prevent PHP warnings/notices from corrupting JSON.
			error_reporting( 0 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@ini_set( 'display_errors', 0 ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed

			// Start output buffering to catch any unexpected output.
			ob_start();

			try {
				// Security check.
				if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbcom_demo_installer_nonce' ) ) {
					ob_end_clean();
					wp_send_json_error( array( 'message' => __( 'Security check failed', 'wbcom-theme-demo-installer' ) ) );
				}

				// Capability check.
				if ( ! current_user_can( 'manage_options' ) ) {
					ob_end_clean();
					wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action', 'wbcom-theme-demo-installer' ) ) );
				}

				if ( isset( $_POST['action'] ) && ( 'wbcom_read_theme_demo_package_file' === $_POST['action'] ) ) {
					if ( isset( $_POST['theme_slug'] ) && isset( $_POST['demo_slug'] ) ) {
						// Sanitize inputs.
						$theme_slug = sanitize_text_field( wp_unslash( $_POST['theme_slug'] ) );
						$demo_slug  = sanitize_text_field( wp_unslash( $_POST['demo_slug'] ) );
						$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';

						$manifest = $this->fetch_demo_package_manifest( $theme_slug, $demo_slug, $target_url );

						if ( is_wp_error( $manifest ) ) {
							ob_end_clean();
							wp_send_json_error( $manifest->get_error_data() );
						}

						// Clear output buffer.
						ob_end_clean();

						// Set proper headers.
						header( 'Content-Type: application/json; charset=utf-8' );

						// Output the clean JSON.
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw JSON passthrough.
						echo $manifest['raw'];
						wp_die();
					}
				}
			} catch ( Exception $e ) {
				ob_end_clean();
				wp_send_json_error(
					array(
						'message' => __( 'An error occurred while fetching demo data', 'wbcom-theme-demo-installer' ),
						'error'   => $e->getMessage(),
					)
				);
			}

			ob_end_clean();
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'wbcom-theme-demo-installer' ) ) );
		}

		/**
		 * Fetch the demo package manifest from the remote demo server.
		 *
		 * Shared by the AJAX handler and the WP-CLI command so both surfaces
		 * resolve the demo package exactly the same way.
		 *
		 * @param string $theme_slug The theme slug.
		 * @param string $demo_slug  The demo slug.
		 * @param string $target_url The demo site URL to request the package from.
		 * @return array|WP_Error Array with 'raw' (clean JSON string) and 'data' (decoded array) keys,
		 *                        or WP_Error whose error data matches the AJAX error payload.
		 */
		public function fetch_demo_package_manifest( $theme_slug, $demo_slug, $target_url ) {
			// Validate URL.
			if ( ! filter_var( $target_url, FILTER_VALIDATE_URL ) ) {
				$message = __( 'Invalid target URL provided', 'wbcom-theme-demo-installer' );
				return new WP_Error( 'wbcom_demo_invalid_url', $message, array( 'message' => $message ) );
			}

			// Add API key for internal exporter access.
			$api_key        = apply_filters( 'wbcom_demo_exporter_api_key', 'demo-export-2024' );
			$url_to_request = $target_url . '?wbcom_theme_demo_listing=yes&api_key=' . $api_key;

			$response = wp_remote_post(
				$url_to_request,
				array(
					'method'    => 'POST',
					'timeout'   => 120,
					'headers'   => array(
						'Content-Type' => 'application/x-www-form-urlencoded',
						'Accept'       => 'application/json',
					),
					'body'      => array(
						'theme_slug' => $theme_slug,
						'demo_slug'  => $demo_slug,
					),
					'sslverify' => false,
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error(
					'wbcom_demo_http_error',
					__( 'Failed to connect to demo server', 'wbcom-theme-demo-installer' ),
					array(
						'message' => __( 'Failed to connect to demo server', 'wbcom-theme-demo-installer' ),
						'details' => $response->get_error_message(),
					)
				);
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			if ( 200 !== $response_code ) {
				/* translators: %d: HTTP response code. */
				$message = sprintf( __( 'Demo server returned error code %d', 'wbcom-theme-demo-installer' ), $response_code );
				return new WP_Error(
					'wbcom_demo_bad_status',
					$message,
					array(
						'message' => $message,
						'code'    => $response_code,
					)
				);
			}

			if ( empty( $response_body ) ) {
				$message = __( 'Demo server returned empty response', 'wbcom-theme-demo-installer' );
				return new WP_Error( 'wbcom_demo_empty_response', $message, array( 'message' => $message ) );
			}

			// Clean any potential BOM or whitespace.
			$response_body = trim( $response_body );

			// Remove UTF-8 BOM if present.
			$bom           = pack( 'H*', 'EFBBBF' );
			$response_body = preg_replace( "/^$bom/", '', $response_body );

			// Validate JSON before sending.
			$json_test = json_decode( $response_body, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error(
					'wbcom_demo_invalid_json',
					__( 'Invalid JSON response from demo server', 'wbcom-theme-demo-installer' ),
					array(
						'message'    => __( 'Invalid JSON response from demo server', 'wbcom-theme-demo-installer' ),
						'json_error' => json_last_error_msg(),
					)
				);
			}

			return array(
				'raw'  => $response_body,
				'data' => $json_test,
			);
		}

		/**
		 * Derive a database table name from a demo package file URL.
		 *
		 * Shared by the AJAX handler and the WP-CLI command.
		 *
		 * @param string $url_to_request The demo package file URL (e.g. .../posts_0001.json).
		 * @return string The sanitized table name (without prefix), or empty string if invalid.
		 */
		public function get_table_name_from_file_url( $url_to_request ) {
			$url_parts  = explode( '/', $url_to_request );
			$filename   = end( $url_parts );
			$table_name = str_replace( '.json', '', $filename );

			// Remove number suffix from table name (e.g., posts_0001 -> posts).
			// This handles both old format (posts_1) and new format (posts_0001).
			$table_name = preg_replace( '/_\d+$/', '', $table_name );

			// Sanitize table name to prevent SQL injection.
			$table_name = preg_replace( '/[^a-zA-Z0-9_]/', '', $table_name );

			return $table_name;
		}

		/**
		 * Get theme demo data via AJAX.
		 *
		 * @return void
		 */
		public function wbcom_get_theme_demo_data() {
			// Disable error reporting to prevent PHP warnings/notices from corrupting output.
			error_reporting( 1 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@ini_set( 'display_errors', 1 ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed

			// Start output buffering to catch any unexpected output.
			ob_start();

			try {
				// Security check.
				if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbcom_demo_installer_nonce' ) ) {
					ob_end_clean();
					wp_send_json_error( array( 'message' => __( 'Security check failed', 'wbcom-theme-demo-installer' ) ) );
				}

				// Capability check.
				if ( ! current_user_can( 'manage_options' ) ) {
					ob_end_clean();
					wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action', 'wbcom-theme-demo-installer' ) ) );
				}

				if ( isset( $_POST['action'] ) && ( 'wbcom_get_theme_demo_data' === $_POST['action'] ) ) {

					if ( isset( $_POST['action_for'] ) && ( 'post_types' === sanitize_text_field( wp_unslash( $_POST['action_for'] ) ) ) ) {
						$url_to_request = isset( $_POST['url_to_request'] ) ? esc_url_raw( wp_unslash( $_POST['url_to_request'] ) ) : '';

						// Validate URL.
						if ( ! empty( $url_to_request ) && filter_var( $url_to_request, FILTER_VALIDATE_URL ) ) {
							$url_parts = explode( '/', $url_to_request );
							$post_slug = end( $url_parts );
							$post_slug = sanitize_file_name( str_replace( '.xml', '', $post_slug ) );
							$this->clone_post_type( $post_slug, $url_to_request );
						}
						ob_end_clean();
						wp_die();
					}

					if ( isset( $_POST['action_for'] ) && ( 'database_tables' === sanitize_text_field( wp_unslash( $_POST['action_for'] ) ) ) ) {
						$url_to_request = isset( $_POST['url_to_request'] ) ? esc_url_raw( wp_unslash( $_POST['url_to_request'] ) ) : '';

						// Validate URL.
						if ( ! empty( $url_to_request ) && filter_var( $url_to_request, FILTER_VALIDATE_URL ) ) {
							global $wpdb;
							$table_name = $this->get_table_name_from_file_url( $url_to_request );

							// Ensure table name is not empty after sanitization.
							if ( empty( $table_name ) ) {
								wp_die( esc_html__( 'Invalid table name extracted from filename', 'wbcom-theme-demo-installer' ) );
							}

							$this->clone_database_table( $table_name, $url_to_request );
						}
						ob_end_clean();
						wp_die();
					}

					if ( isset( $_POST['action_for'] ) && ( 'upload_folders' === sanitize_text_field( wp_unslash( $_POST['action_for'] ) ) ) ) {
						$url_to_request = isset( $_POST['url_to_request'] ) ? esc_url_raw( wp_unslash( $_POST['url_to_request'] ) ) : '';
						$this->clone_uploads_folder( $url_to_request );
						ob_end_clean();
						wp_die();
					}
				}
			} catch ( Exception $e ) {
				ob_end_clean();
				wp_send_json_error(
					array(
						'message' => __( 'An error occurred while processing demo data', 'wbcom-theme-demo-installer' ),
						'error'   => $e->getMessage(),
					)
				);
			}

			ob_end_clean();
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'wbcom-theme-demo-installer' ) ) );
		}

		/**
		 * Clone a post type from a remote XML file.
		 *
		 * @param string $post_slug      The post slug.
		 * @param string $url_to_request The URL to request.
		 * @return void
		 */
		public function clone_post_type( $post_slug = 'post', $url_to_request = '' ) {
			// Use wp_remote_get instead of file_get_contents.
			$response = wp_remote_get(
				$url_to_request,
				array(
					'timeout'   => 300, // 5 minutes for large files.
					'sslverify' => false,
				)
			);

			// Check for errors.
			if ( is_wp_error( $response ) ) {
				wp_die( esc_html( $response->get_error_message() ) );
			}

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				wp_die( esc_html__( 'Failed to retrieve file from remote server', 'wbcom-theme-demo-installer' ) );
			}

			$retrieved_data = wp_remote_retrieve_body( $response );

			if ( empty( $retrieved_data ) ) {
				wp_die( esc_html__( 'Retrieved empty data from remote server', 'wbcom-theme-demo-installer' ) );
			}

			$upload     = wp_upload_dir();
			$upload_dir = $upload['basedir'];
			$upload_dir = $upload_dir . '/wbcom-temp-folder';
			if ( ! is_dir( $upload_dir ) ) {
				wp_mkdir_p( $upload_dir );
			}
			$dir_path = $upload_dir . '/';

			// Use WP_Filesystem for file operations.
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$file_path = $dir_path . sanitize_file_name( "$post_slug.xml" );
			if ( ! $wp_filesystem->put_contents( $file_path, $retrieved_data, FS_CHMOD_FILE ) ) {
				wp_die( esc_html__( 'Failed to write temporary file', 'wbcom-theme-demo-installer' ) );
			}

			// Check if we have a custom importer or use WordPress importer.
			global $wbcom_xml_wp_import;
			if ( isset( $wbcom_xml_wp_import ) && is_object( $wbcom_xml_wp_import ) ) {
				$wbcom_xml_wp_import->import( $file_path );
			} else {
				// Use WordPress core importer if available.
				if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
					define( 'WP_LOAD_IMPORTERS', true );
				}

				// Load WordPress importer.
				if ( ! class_exists( 'WP_Import' ) ) {
					$class_wp_import = ABSPATH . 'wp-admin/includes/class-wp-import.php';
					if ( file_exists( $class_wp_import ) ) {
						require_once $class_wp_import;
					}
				}

				if ( class_exists( 'WP_Import' ) ) {
					$wp_import = new WP_Import();
					$wp_import->import( $file_path );
				}
			}

			// Use WP_Filesystem to delete file.
			$wp_filesystem->delete( $file_path );
		}

		/**
		 * Clone a database table from a remote JSON file.
		 *
		 * @param string $table_name     The table name.
		 * @param string $url_to_request The URL to request.
		 * @return void
		 * @throws Exception When database operations fail.
		 */
		public function clone_database_table( $table_name = '', $url_to_request = '' ) {
			$retrieved_data = '';

			$response = wp_remote_get(
				$url_to_request,
				array(
					'sslverify' => false,
					'timeout'   => 120,
				)
			);

			if ( ! is_wp_error( $response ) ) {
				if ( isset( $response['response']['code'] ) && ( 200 === (int) $response['response']['code'] ) ) {
					$response = isset( $response['body'] ) ? $response['body'] : '';
					if ( ! empty( $response ) ) {
						$retrieved_data = json_decode( $response, true );
					}
				}
			}

			if ( ! empty( $retrieved_data ) && is_array( $retrieved_data ) ) {
				// No URL replacement here - will be done in finalize step.

				// Enable all BuddyPress/BuddyBoss components before importing BP tables.
				if ( 0 === strpos( $table_name, 'bp_' ) || 'signups' === $table_name ) {
					static $components_enabled = false;

					if ( ! $components_enabled && function_exists( 'buddypress' ) && class_exists( 'WBCOM_BuddyPress_Components_Enabler' ) ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'WBCOM Demo Import: Enabling all BP components before importing ' . $table_name );
						WBCOM_BuddyPress_Components_Enabler::enable_all_components();
						$components_enabled = true;
					}
				}

				if ( 'theme_mods' === $table_name ) {
					foreach ( $retrieved_data as $key => $value ) {
						set_theme_mod( $key, $value );
					}
					return;
				}

				if ( 'options' === $table_name ) {
					// Enable all BP components before importing options.
					if ( function_exists( 'buddypress' ) && class_exists( 'WBCOM_BuddyPress_Components_Enabler' ) ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'WBCOM Demo Import: Enabling all BP components before importing options table' );
						WBCOM_BuddyPress_Components_Enabler::enable_all_components();
					}

					$default_options_keys = array(
						'siteurl',
						'home',
						'blogname',
						'blogdescription',
						'users_can_register',
						'admin_email',
						'new_admin_email',
						'start_of_week',
						'use_balanceTags',
						'use_smilies',
						'require_name_email',
						'comments_notify',
						'posts_per_rss',
						'rss_use_excerpt',
						'mailserver_url',
						'mailserver_login',
						'mailserver_pass',
						'mailserver_port',
						'default_category',
						'default_comment_status',
						'default_ping_status',
						'default_pingback_flag',
						// 'posts_per_page',
						'date_format',
						'time_format',
						'links_updated_date_format',
						'comment_moderation',
						'moderation_notify',
						// 'permalink_structure',
						'rewrite_rules',
						'hack_file',
						'blog_charset',
						'active_plugins',
						'category_base',
						'ping_sites',
						'comment_max_links',
						'gmt_offset',
						'default_email_category',
						'template',
						'stylesheet',
						'comment_whitelist',
						'comment_registration',
						'html_type',
						'use_trackback',
						'default_role',
						'db_version',
						'uploads_use_yearmonth_folders',
						'upload_path',
						'blog_public',
						'default_link_category',
						// 'show_on_front',
						'tag_base',
						'show_avatars',
						'avatar_rating',
						'upload_url_path',
						'thumbnail_size_w',
						'thumbnail_size_h',
						'thumbnail_crop',
						'medium_size_w',
						'medium_size_h',
						'avatar_default',
						'large_size_w',
						'large_size_h',
						'image_default_link_type',
						'image_default_size',
						'image_default_align',
						'close_comments_for_old_posts',
						'close_comments_days_old',
						'thread_comments',
						'thread_comments_depth',
						'page_comments',
						'comments_per_page',
						'default_comments_page',
						'comment_order',
						'sticky_posts',
						// 'widget_categories',
						// 'widget_text',
						// 'widget_rss',
						'timezone_string',
						// 'page_for_posts',
						// 'page_on_front',
						'default_post_format',
						'link_manager_enabled',
						'finished_splitting_shared_terms',
						'site_icon',
						'medium_large_size_w',
						'medium_large_size_h',
						'initial_db_version',
						'wp_user_roles',
						'fresh_site',
						// 'widget_search',
						// 'widget_recent-posts',
						// 'widget_recent-comments',
						// 'widget_archives',
						// 'widget_meta',
						// 'sidebars_widgets',
						// 'widget_pages',
						// 'widget_calendar',
						// 'widget_media_audio',
						// 'widget_media_image',
						// 'widget_media_video',
						// 'widget_tag_cloud',
						// 'widget_nav_menu',
						// 'widget_custom_html',
						// 'reign_options',
						'cron',
						'theme_mods_twentyseventeen',
						// 'theme_mods_reign-theme',
						'_transient_is_multi_author',
						'_transient_twentyseventeen_categories',
						'_worker_public_key',
						// GeoDirectory - preserve local setup.
						'geodir_settings',
						'geodirectory_db_version',
						'geodirectory_version',
						'geodirectory_admin_notices',
					);

					foreach ( $retrieved_data as $key => $value ) {
						if ( ! in_array( $value['option_name'], $default_options_keys, true ) ) {
							// Skip bp-active-components to preserve our enabled components.
							if ( 'bp-active-components' === $value['option_name'] ) {
								continue;
							}

							$option_value = maybe_unserialize( $value['option_value'] );
							update_option( $value['option_name'], $option_value, $value['autoload'] );
						}
					}

					if ( function_exists( 'buddypress' ) ) {
						// Store current active components before import.
						$preserve_components = bp_get_option( 'bp-active-components', array() );
						// Restore our enabled components after import.
						if ( ! empty( $preserve_components ) ) {
							bp_update_option( 'bp-active-components', $preserve_components );
						}
					}
					return;
				}

				global $wpdb;

				// Start transaction for data integrity.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'START TRANSACTION' );

				try {
					if ( ( 'users' === $table_name ) || ( 'usermeta' === $table_name ) ) {
						$table_name = $wpdb->prefix . $table_name;

						// Verify table exists.
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
							/* translators: %s: Table name. */
							throw new Exception( sprintf( __( 'Table %s does not exist', 'wbcom-theme-demo-installer' ), $table_name ) );
						}

						$processed_count = 0;
						$skipped_count   = 0;

						foreach ( $retrieved_data as $key => $value ) {
							if ( ( isset( $value['ID'] ) ) && ( get_current_user_id() === (int) $value['ID'] ) ) {
								++$skipped_count;
								continue;
							} elseif ( ( isset( $value['user_id'] ) ) && ( get_current_user_id() === (int) $value['user_id'] ) ) {
								++$skipped_count;
								continue;
							}

							// User table structure mismatch fix.
							if ( isset( $value['spam'] ) ) {
								unset( $value['spam'] );
							}
							if ( isset( $value['deleted'] ) ) {
								unset( $value['deleted'] );
							}
							// End user table structure mismatch fix.

							// For usermeta, handle table prefix in capability keys.
							if ( $wpdb->prefix . 'usermeta' === $table_name && isset( $value['meta_key'] ) ) {
								$current_prefix = $wpdb->prefix;

								// Common capability/role related meta keys that need prefix update.
								$prefix_keys = array( 'capabilities', 'user_level', 'dashboard_quick_press_last_post_id', 'user-settings', 'user-settings-time' );

								foreach ( $prefix_keys as $pkey ) {
									// Detect source prefix by looking for the key pattern.
									if ( preg_match( '/^(.+_)' . preg_quote( $pkey, '/' ) . '$/', $value['meta_key'], $matches ) ) {
										$source_prefix = $matches[1];
										// Replace source prefix with current prefix.
										$value['meta_key'] = $current_prefix . $pkey;
										break;
									}
								}

								// Also handle blog-specific capabilities for multisite.
								if ( preg_match( '/^(.+_)(\d+_capabilities)$/', $value['meta_key'], $matches ) ) {
									$value['meta_key'] = $current_prefix . $matches[2];
								}

								// If this is a last_activity key and it's empty/old, update it.
								if ( 'last_activity' === $value['meta_key'] &&
									( empty( $value['meta_value'] ) || strtotime( $value['meta_value'] ) < strtotime( '-1 year' ) ) ) {
									$value['meta_value'] = current_time( 'mysql', true );
								}
							}

							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
							$result = $wpdb->insert( $table_name, $value );
							if ( false === $result ) {
								/* translators: %1$s: Table name, %2$s: Error message. */
								throw new Exception( sprintf( __( 'Failed to insert data into %1$s: %2$s', 'wbcom-theme-demo-installer' ), $table_name, $wpdb->last_error ) );
							}

							// After inserting user meta, if BuddyPress is active, ensure user is activated.
							if ( $wpdb->prefix . 'usermeta' === $table_name &&
								'last_activity' === $value['meta_key'] &&
								function_exists( 'bp_update_user_last_activity' ) ) {
								bp_update_user_last_activity( $value['user_id'] );
							}

							++$processed_count;
						}

						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->query( 'COMMIT' );

						// Log import results.
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log(
							sprintf(
								'WBCOM Demo Import - Table: %s, Processed: %d, Skipped: %d, Total: %d',
								$table_name,
								$processed_count,
								$skipped_count,
								count( $retrieved_data )
							)
						);

						return;
					} else {
						$table_name = $wpdb->prefix . $table_name;

						// Verify table exists.
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
							/* translators: %s: Table name. */
							throw new Exception( sprintf( __( 'Table %s does not exist', 'wbcom-theme-demo-installer' ), $table_name ) );
						}

						$wbcom_theme_demo_import_data = get_option( 'wbcom_theme_demo_import_data', array() );
						if ( ! isset( $wbcom_theme_demo_import_data[ $table_name . '_done' ] ) ) {
							// Use proper prepared statement for safety.
							$sql = $wpdb->prepare( 'DELETE FROM `%s`', $table_name );
							// WordPress doesn't support table name placeholders, so we need to validate.
							// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							$sql = 'DELETE FROM `' . esc_sql( $table_name ) . '`';
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
							$results = $wpdb->query( $sql );

							if ( false === $results ) {
								/* translators: %1$s: Table name, %2$s: Error message. */
								throw new Exception( sprintf( __( 'Failed to clear table %1$s: %2$s', 'wbcom-theme-demo-installer' ), $table_name, $wpdb->last_error ) );
							}

							$wbcom_theme_demo_import_data[ $table_name . '_done' ] = 'yes';
							update_option( 'wbcom_theme_demo_import_data', $wbcom_theme_demo_import_data );
						}

						$inserted_count = 0;

						// Detect if we're using BuddyBoss or BuddyPress (check once for all tables).
						$is_buddyboss = $this->is_buddyboss_platform();

						// Check if this is a BuddyPress/BuddyBoss table and handle compatibility.
						if ( false !== strpos( $table_name, 'bp_' ) ) {
							// Get table columns to check compatibility.
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							$columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table_name`" );
							$columns = array_flip( $columns );

							if ( $is_buddyboss ) {
								// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
								error_log( 'WBCOM Demo Import: Processing ' . $table_name . ' for BuddyBoss Platform' );
							} else {
								// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
								error_log( 'WBCOM Demo Import: Processing ' . $table_name . ' for standard BuddyPress' );
							}
						}

						foreach ( $retrieved_data as $key => $value ) {
							// For BuddyPress tables, remove fields that don't exist.
							if ( false !== strpos( $table_name, 'bp_' ) && isset( $columns ) ) {
								foreach ( $value as $field => $data ) {
									if ( ! isset( $columns[ $field ] ) ) {
										// Remove fields that don't exist in current installation.
										unset( $value[ $field ] );
									}
								}
							}

							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
							$result = $wpdb->insert( $table_name, $value );
							if ( false === $result ) {
								/* translators: %1$s: Table name, %2$s: Error message. */
								throw new Exception( sprintf( __( 'Failed to insert data into %1$s: %2$s', 'wbcom-theme-demo-installer' ), $table_name, $wpdb->last_error ) );
							}
							++$inserted_count;
						}
					}

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->query( 'COMMIT' );

				} catch ( Exception $e ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->query( 'ROLLBACK' );
					wp_die( esc_html( $e->getMessage() ) );
				}
			}
		}

		/**
		 * Clone uploads folder from a remote zip file.
		 *
		 * @param string $url_to_request The URL to request.
		 * @return void
		 */
		public function clone_uploads_folder( $url_to_request = '' ) {
			$parent_folder_name = explode( '/', $url_to_request );
			$parent_folder_name = array_filter( $parent_folder_name );
			$parent_folder_name = array_values( $parent_folder_name );
			$parent_folder_name = $parent_folder_name[ count( $parent_folder_name ) - 2 ];

			$response       = wp_remote_get(
				$url_to_request,
				array(
					'sslverify' => false,
					'timeout'   => 120,
				)
			);
			$retrieved_data = array();
			if ( ! is_wp_error( $response ) ) {
				if ( isset( $response['response']['code'] ) && ( 200 === (int) $response['response']['code'] ) ) {
					$response = isset( $response['body'] ) ? $response['body'] : '';
					if ( ! empty( $response ) ) {
						$retrieved_data = $response;
					}
				}
			}

			if ( ! empty( $retrieved_data ) ) {
				$upload     = wp_upload_dir();
				$upload_dir = $upload['basedir'] . '/wbcom-theme-demo.zip';

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
				$file = fopen( $upload_dir, 'w+' );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs
				fputs( $file, $retrieved_data );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose( $file );

				$zip = new ZipArchive();
				$res = $zip->open( $upload_dir );
				if ( true === $res ) {
					$zip->extractTo( $upload['basedir'] . '/' . $parent_folder_name . '/' );
					$zip->close();
				}

				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $upload_dir );
			}
		}

		/**
		 * Finalize demo import - perform search and replace.
		 *
		 * @return void
		 */
		public function wbcom_demo_import_finalize() {
			// Security check.
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbcom_demo_installer_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed', 'wbcom-theme-demo-installer' ) ) );
			}

			// Capability check.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action', 'wbcom-theme-demo-installer' ) ) );
			}

			// Get the source URL from the demo server.
			$source_url = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';
			if ( empty( $source_url ) ) {
				wp_send_json_error( array( 'message' => __( 'Source URL not provided', 'wbcom-theme-demo-installer' ) ) );
			}

			$this->finalize_import( $source_url );

			wp_send_json_success(
				array(
					'message' => __( 'Demo import finalized successfully', 'wbcom-theme-demo-installer' ),
				)
			);
		}

		/**
		 * Finalize a demo import for a given source URL.
		 *
		 * Performs the URL search-replace, activates BuddyPress users and clears
		 * caches. Shared by the AJAX handler and the WP-CLI command.
		 *
		 * @param string $source_url The demo source URL imported content came from.
		 * @return void
		 */
		public function finalize_import( $source_url ) {
			// Parse the source URL.
			$source_url_parsed = wp_parse_url( $source_url );

			// Build the full source URL including path.
			$source_base_url = $source_url_parsed['scheme'] . '://' . $source_url_parsed['host'];
			if ( ! empty( $source_url_parsed['path'] ) && '/' !== $source_url_parsed['path'] ) {
				$source_base_url .= rtrim( $source_url_parsed['path'], '/' );
			}

			// Get current site URL (without trailing slash).
			$current_url = rtrim( home_url(), '/' );

			// Perform search-replace on all tables.
			$this->search_replace_all_tables( $source_base_url, $current_url );

			// Activate BuddyPress users.
			$this->activate_buddypress_users();

			// Clear caches.
			wp_cache_flush();

			// Regenerate permalinks.
			flush_rewrite_rules();

			// Clear Elementor cache if active.
			if ( class_exists( '\Elementor\Plugin' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		}

		/**
		 * Search and replace in all database tables.
		 *
		 * @param string $search  The string to search for.
		 * @param string $replace The replacement string.
		 * @return void
		 */
		private function search_replace_all_tables( $search, $replace ) {
			global $wpdb;

			// Get all tables.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$tables = $wpdb->get_col( 'SHOW TABLES' );

			foreach ( $tables as $table ) {
				// Skip non-WordPress tables.
				if ( 0 !== strpos( $table, $wpdb->prefix ) ) {
					continue;
				}

				// Get all columns.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$columns = $wpdb->get_results( "SHOW COLUMNS FROM `$table`" );
				if ( empty( $columns ) ) {
					continue;
				}

				$text_columns = array();
				$primary_key  = '';
				foreach ( $columns as $column ) {
					// Only process text-based columns.
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column property.
					if ( preg_match( '/text|varchar|char|blob/i', $column->Type ) ) {
						// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column property.
						$text_columns[] = $column->Field;
					}
					// Find primary key.
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column property.
					if ( 'PRI' === $column->Key ) {
						// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column property.
						$primary_key = $column->Field;
					}
				}

				if ( empty( $text_columns ) ) {
					continue;
				}

				// If no primary key, try to find a unique identifier.
				if ( empty( $primary_key ) ) {
					// Look for common ID fields.
					foreach ( $columns as $column ) {
						// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column property.
						if ( in_array( strtolower( $column->Field ), array( 'id', 'ID', 'option_id', 'meta_id', 'comment_id', 'term_id' ), true ) ) {
							// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column property.
							$primary_key = $column->Field;
							break;
						}
					}
					// If still no key found, skip this table.
					if ( empty( $primary_key ) ) {
						continue;
					}
				}

				// Process each row individually to handle serialized data.
				foreach ( $text_columns as $text_column ) {
					// Get all rows that contain the search string.
					$search_variants = array(
						str_replace( 'https://', 'http://', $search ),
						str_replace( 'http://', 'https://', $search ),
					);

					foreach ( $search_variants as $search_variant ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$rows = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT `$primary_key`, `$text_column` FROM `$table` WHERE `$text_column` LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								'%' . $wpdb->esc_like( $search_variant ) . '%'
							)
						);

						foreach ( $rows as $row ) {
							$data          = $row->$text_column;
							$primary_value = $row->$primary_key;

							// Process the data.
							$updated_data = $this->recursive_unserialize_replace( $search_variant, $replace, $data );

							// Only update if data changed.
							if ( $updated_data !== $data ) {
								// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->update(
									$table,
									array( $text_column => $updated_data ),
									array( $primary_key => $primary_value )
								);
							}
						}
					}
				}
			}
		}

		/**
		 * Recursively unserialize and replace data.
		 *
		 * @param string $search  The string to search for.
		 * @param string $replace The replacement string.
		 * @param mixed  $data    The data to process.
		 * @return mixed The processed data.
		 */
		private function recursive_unserialize_replace( $search, $replace, $data ) {
			// Check if it's serialized data.
			if ( is_serialized( $data ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
				$unserialized = @unserialize( $data );
				if ( false !== $unserialized ) {
					$unserialized = $this->recursive_replace( $search, $replace, $unserialized );
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
					return serialize( $unserialized );
				}
			}

			// If not serialized, do simple replace.
			if ( is_string( $data ) ) {
				return str_replace( $search, $replace, $data );
			}

			return $data;
		}

		/**
		 * Recursively replace strings in arrays/objects.
		 *
		 * @param string $search  The string to search for.
		 * @param string $replace The replacement string.
		 * @param mixed  $data    The data to process.
		 * @return mixed The processed data.
		 */
		private function recursive_replace( $search, $replace, $data ) {
			if ( is_string( $data ) ) {
				return str_replace( $search, $replace, $data );
			} elseif ( is_array( $data ) ) {
				foreach ( $data as $key => $value ) {
					$data[ $key ] = $this->recursive_replace( $search, $replace, $value );
				}
			} elseif ( is_object( $data ) ) {
				foreach ( $data as $key => $value ) {
					$data->$key = $this->recursive_replace( $search, $replace, $value );
				}
			}

			return $data;
		}

		/**
		 * Activate BuddyPress users by setting last activity.
		 *
		 * @return void
		 */
		private function activate_buddypress_users() {
			// Check if BuddyPress/BuddyBoss is active.
			if ( ! function_exists( 'bp_is_active' ) ) {
				return;
			}

			// Log which platform we're using.
			if ( defined( 'BP_PLATFORM_VERSION' ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WBCOM Demo Import: Activating users for BuddyBoss Platform v' . BP_PLATFORM_VERSION );
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'WBCOM Demo Import: Activating users for BuddyPress' );
			}

			global $wpdb;

			// Get users without last_activity meta.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$users_without_activity = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT u.ID
					FROM {$wpdb->users} u
					LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'last_activity'
					WHERE um.umeta_id IS NULL
					AND u.ID != %d",
					get_current_user_id()
				)
			);

			// Set last activity for users who don't have it.
			foreach ( $users_without_activity as $user_id ) {
				bp_update_user_last_activity( $user_id );
			}

			// Ensure all users are properly indexed by BuddyPress.
			$all_users = get_users( array( 'fields' => 'ID' ) );
			foreach ( $all_users as $user_id ) {
				// Also ensure user has member_type if needed.
				if ( function_exists( 'bp_set_member_type' ) ) {
					$member_types = bp_get_member_type( $user_id, false );
					if ( empty( $member_types ) ) {
						// Set default member type if none exists.
						bp_set_member_type( $user_id, '' );
					}
				}
			}

			// Handle signups table if it exists.
			$signups_table = $wpdb->prefix . 'signups';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $signups_table ) ) === $signups_table ) {
				// Activate any pending signups.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$signups_table,
					array(
						'active'    => 1,
						'activated' => current_time( 'mysql', true ),
					),
					array( 'active' => 0 )
				);
			}
		}

		/**
		 * Helper function to detect if BuddyBoss Platform is active.
		 *
		 * @return bool True if BuddyBoss Platform is active.
		 */
		private function is_buddyboss_platform() {
			// Check for BuddyBoss-specific constants or classes.
			return defined( 'BP_PLATFORM_VERSION' ) || class_exists( 'BuddyBoss_Platform' );
		}

		/**
		 * Enable all BuddyPress/BuddyBoss components.
		 *
		 * @return void
		 */
		public function wbcom_enable_buddypress_components() {
			// Security check.
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbcom_demo_installer_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed', 'wbcom-theme-demo-installer' ) ) );
			}

			// Capability check.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action', 'wbcom-theme-demo-installer' ) ) );
			}

			// Check if BuddyPress or BuddyBoss is active.
			if ( ! function_exists( 'buddypress' ) ) {
				wp_send_json_error( array( 'message' => __( 'BuddyPress or BuddyBoss Platform is not active', 'wbcom-theme-demo-installer' ) ) );
			}

			// Enable all components.
			if ( class_exists( 'WBCOM_BuddyPress_Components_Enabler' ) ) {
				$enabled_components = WBCOM_BuddyPress_Components_Enabler::enable_all_components();

				if ( $enabled_components ) {
					wp_send_json_success(
						array(
							'message'    => __( 'All components enabled successfully', 'wbcom-theme-demo-installer' ),
							'components' => $enabled_components,
						)
					);
				} else {
					wp_send_json_error( array( 'message' => __( 'Failed to enable components', 'wbcom-theme-demo-installer' ) ) );
				}
			} else {
				wp_send_json_error( array( 'message' => __( 'Component enabler class not found', 'wbcom-theme-demo-installer' ) ) );
			}
		}

		/**
		 * Get demo plugins data via AJAX.
		 *
		 * @return void
		 */
		public function wbcom_get_demo_plugins_data() {
			// Security check.
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbcom_demo_installer_nonce' ) ) {
				wp_send_json_error( __( 'Security check failed', 'wbcom-theme-demo-installer' ) );
			}

			// Capability check.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You do not have permission to perform this action', 'wbcom-theme-demo-installer' ) );
			}

			if ( isset( $_POST['plugins_key'] ) ) {
				$plugins_key  = sanitize_text_field( wp_unslash( $_POST['plugins_key'] ) );
				$plugins_data = $this->get_demo_plugins_list( $plugins_key );

				if ( $plugins_data ) {
					wp_send_json_success( $plugins_data );
				}
			}

			wp_send_json_error( __( 'Failed to fetch plugin data', 'wbcom-theme-demo-installer' ) );
		}

		/**
		 * Get the plugin stack for a demo, preferring the bundled local file.
		 *
		 * Shared by the AJAX handler and the WP-CLI command.
		 *
		 * @param string $plugins_key The demo plugins key (demo-plugins/<key>/plugins.json).
		 * @return array|false The decoded plugin stack, or false when unavailable.
		 */
		public function get_demo_plugins_list( $plugins_key ) {
			// Try local file first.
			$local_path = WBCOM_Theme_Demo_Installer_PLUGIN_DIR_PATH . 'demo-plugins/' . $plugins_key . '/plugins.json';
			if ( file_exists( $local_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$plugins_data = file_get_contents( $local_path );
				$plugins_data = json_decode( $plugins_data, true );

				if ( $plugins_data ) {
					return $plugins_data;
				}
			}

			// Fallback to remote URL.
			$url_to_request = WBCOM_DEMO_INSTALLER_PACKAGE_PLUGINS_URL . $plugins_key . '/plugins.json';
			$response       = wp_remote_get(
				$url_to_request,
				array(
					'sslverify' => false,
					'timeout'   => 30,
				)
			);

			if ( ! is_wp_error( $response ) ) {
				if ( isset( $response['response']['code'] ) && ( 200 === (int) $response['response']['code'] ) ) {
					$response_body = isset( $response['body'] ) ? $response['body'] : '';
					if ( ! empty( $response_body ) ) {
						$plugins_data = json_decode( $response_body, true );
						if ( $plugins_data ) {
							return $plugins_data;
						}
					}
				}
			}

			return false;
		}
	}

endif;

/**
 * Main instance of WBCOM_Demo_Importer_Ajax_Handler.
 *
 * @since  1.0.0
 * @return WBCOM_Demo_Importer_Ajax_Handler
 */
WBCOM_Demo_Importer_Ajax_Handler::instance();
