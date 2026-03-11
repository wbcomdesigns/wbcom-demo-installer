<?php
/**
 * Admin settings page for demo installer.
 *
 * @package WBCOM_Theme_Demo_Installer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WBCOM_TDI_ADMIN_SETTINGS' ) ) :

	/**
	 * Admin settings for the theme demo installer.
	 *
	 * @class WBCOM_TDI_ADMIN_SETTINGS
	 * @version 1.0.0
	 */
	class WBCOM_TDI_ADMIN_SETTINGS {

		/**
		 * The single instance of the class.
		 *
		 * @var WBCOM_TDI_ADMIN_SETTINGS
		 * @since 1.0.0
		 */
		protected static $_instance = null;

		/**
		 * Admin page slug.
		 *
		 * @var string
		 */
		protected static $_slug = 'wbcom-theme-demo-installer';

		/**
		 * Main WBCOM_TDI_ADMIN_SETTINGS Instance.
		 *
		 * Ensures only one instance of WBCOM_TDI_ADMIN_SETTINGS is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @see WBCOM_TDI_ADMIN_SETTINGS()
		 * @return WBCOM_TDI_ADMIN_SETTINGS - Main instance.
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}


		/**
		 * WBCOM_TDI_ADMIN_SETTINGS Constructor.
		 */
		public function __construct() {
			$this->init_hooks();
		}

		/**
		 * Hook into actions and filters.
		 *
		 * @since  1.0.0
		 */
		private function init_hooks() {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 10 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_init', array( $this, 'maybe_repair_geodir_detail_table' ) );
		}

		/**
		 * One-time repair: ensure every imported GeoDirectory place post has a matching
		 * detail-table row, and that all custom field columns exist in the detail table.
		 * Runs once and sets a flag so it never runs again.
		 *
		 * Two problems after demo import:
		 * 1. Post IDs mismatch: XML import reassigns IDs but JSON import keeps source IDs.
		 * 2. Missing columns: custom field ALTER TABLE statements were never run because the
		 *    geodir_custom_fields table was imported directly, bypassing the admin UI that
		 *    normally triggers geodir_add_column_if_not_exist().
		 */
		public function maybe_repair_geodir_detail_table() {
			if ( get_option( 'wbcom_geodir_detail_repaired_v2' ) ) {
				return;
			}

			if ( class_exists( 'GeoDirectory' ) ) {
				$this->sync_geodir_custom_field_columns();
				$this->populate_geodir_detail_table();
			}

			update_option( 'wbcom_geodir_detail_repaired_v2', true );
		}

		/**
		 * Add any missing custom field columns to the GeoDirectory detail tables.
		 *
		 * When the demo imports geodir_custom_fields directly via JSON, the ALTER TABLE
		 * operations that normally add matching columns to the detail table are skipped.
		 * This method runs those operations for any columns that are missing.
		 */
		public function sync_geodir_custom_field_columns() {
			global $wpdb;

			if ( ! function_exists( 'geodir_add_column_if_not_exist' ) ) {
				return;
			}

			$custom_fields_table = defined( 'GEODIR_CUSTOM_FIELDS_TABLE' ) ? GEODIR_CUSTOM_FIELDS_TABLE : $wpdb->prefix . 'geodir_custom_fields';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$fields = $wpdb->get_results( "SELECT post_type, htmlvar_name, data_type, field_type FROM `{$custom_fields_table}` WHERE is_active = 1" );

			if ( empty( $fields ) ) {
				return;
			}

			// Core columns that are part of the base table schema - never need ALTER TABLE.
			$core_columns = array(
				'post_title', 'post_status', 'post_tags', 'post_category',
				'default_category', 'featured', 'featured_image', 'submit_ip',
				'overall_rating', 'rating_count', 'street', 'street2', 'city',
				'region', 'country', 'zip', 'latitude', 'longitude', 'mapview', 'mapzoom',
			);

			foreach ( $fields as $field ) {
				if ( empty( $field->htmlvar_name ) || empty( $field->post_type ) ) {
					continue;
				}

				// Core columns already exist in the table schema.
				if ( in_array( $field->htmlvar_name, $core_columns, true ) ) {
					continue;
				}

				// Fieldset fields do not have a corresponding column.
				if ( 'fieldset' === $field->field_type ) {
					continue;
				}

				$table = $wpdb->prefix . 'geodir_' . $field->post_type . '_detail';

				// Check if detail table exists.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
					continue;
				}

				// Map data_type to an appropriate column definition.
				$data_type = strtoupper( (string) $field->data_type );
				if ( 'INT' === $data_type ) {
					$col_def = 'INT NULL DEFAULT NULL';
				} elseif ( 'DECIMAL' === $data_type ) {
					$col_def = 'DECIMAL(11,4) NULL DEFAULT NULL';
				} elseif ( 'FLOAT' === $data_type ) {
					$col_def = 'FLOAT NULL DEFAULT NULL';
				} elseif ( 'TINYINT' === $data_type ) {
					$col_def = "TINYINT(1) NOT NULL DEFAULT '0'";
				} elseif ( 'VARCHAR' === $data_type ) {
					$col_def = 'VARCHAR(255) NULL DEFAULT NULL';
				} elseif ( 'DATE' === $data_type ) {
					$col_def = 'DATE NULL DEFAULT NULL';
				} elseif ( 'TIME' === $data_type ) {
					$col_def = 'TIME NULL DEFAULT NULL';
				} elseif ( 'DATETIME' === $data_type ) {
					$col_def = 'DATETIME NULL DEFAULT NULL';
				} else {
					$col_def = 'TEXT NULL DEFAULT NULL';
				}

				geodir_add_column_if_not_exist( $table, $field->htmlvar_name, $col_def );
			}
		}

		/**
		 * Register the admin menu page.
		 */
		public function add_admin_menu() {
			add_menu_page(
				__( 'Theme Installer', 'wbcom-theme-demo-installer' ),
				__( 'Theme Installer', 'wbcom-theme-demo-installer' ),
				'manage_options',
				self::$_slug,
				array( $this, 'render_page_for_added_menu' ),
				'',
				null
			);
		}

		/**
		 * Display the step navigation header.
		 *
		 * @param string $current_tab The current active tab.
		 */
		public function show_step_header( $current_tab = '' ) {
			?>
			<div class="tab">
				<button class="tablinks <?php echo ( 'select-demo' === $current_tab ) ? 'active' : ''; ?>"><?php esc_html_e( 'Select Demo', 'wbcom-theme-demo-installer' ); ?></button>
				<button class="tablinks <?php echo ( 'manage-plugins' === $current_tab ) ? 'active' : ''; ?>"><?php esc_html_e( 'Manage Plugins', 'wbcom-theme-demo-installer' ); ?></button>
				<button class="tablinks <?php echo ( 'install-demo' === $current_tab ) ? 'active' : ''; ?>"><?php esc_html_e( 'Install Demo', 'wbcom-theme-demo-installer' ); ?></button>
				<button class="tablinks <?php echo ( 'success' === $current_tab ) ? 'active' : ''; ?>"><?php esc_html_e( 'Success', 'wbcom-theme-demo-installer' ); ?></button>
			</div>
			<?php
		}

		/**
		 * Render the demo installer admin page.
		 */
		public function render_page_for_added_menu() {
			// Security check.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wbcom-theme-demo-installer' ) );
			}

			$theme_info = wp_get_theme();

			// Get parent theme name.
			$reflection = new ReflectionClass( $theme_info );
			$property   = $reflection->getProperty( 'parent' );
			$property->setAccessible( true );
			$parent = $property->getValue( $theme_info );
			if ( $parent ) {
				$theme_info = $property->getValue( $theme_info );
			} else {
				$reflection = new ReflectionClass( $theme_info );
				$property   = $reflection->getProperty( 'headers' );
				$property->setAccessible( true );
				$theme_info = $property->getValue( $theme_info );
			}

			echo '<div class="wrap">';

			echo '<div class="demo-listing-wrap">';

			// Check if this is a multisite installation.
			if ( is_multisite() ) {
				?>
				<div class="notice notice-error">
					<h2><?php esc_html_e( 'Multisite Installation Detected', 'wbcom-theme-demo-installer' ); ?></h2>
					<p><?php esc_html_e( 'The Demo Importer is not compatible with WordPress Multisite installations. Please use a single site WordPress installation to import demo content.', 'wbcom-theme-demo-installer' ); ?></p>
					<p><?php esc_html_e( 'Demo import modifies database tables and settings in ways that are not compatible with the multisite architecture.', 'wbcom-theme-demo-installer' ); ?></p>
				</div>
				</div></div>
				<?php
				return;
			}

			?>

		<div class="theme-info">
			<h1><?php echo esc_html( $theme_info['Name'] ); ?></h1>
		</div>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['success'] ) && ( 'success' === sanitize_text_field( wp_unslash( $_GET['success'] ) ) ) ) {
				$this->show_step_header( 'success' );
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_GET['theme_slug'] ) && isset( $_GET['demo_slug'] ) && isset( $_GET['step'] ) && ( 'demo_import' === sanitize_text_field( wp_unslash( $_GET['step'] ) ) ) ) {
				$this->show_step_header( 'install-demo' );
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_GET['theme_slug'] ) && isset( $_GET['demo_slug'] ) && isset( $_GET['step'] ) && ( 'plugins_manager' === sanitize_text_field( wp_unslash( $_GET['step'] ) ) ) ) {
				$this->show_step_header( 'manage-plugins' );
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_GET['action'] ) && ( 'fix_buddypress_users' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) ) {
				// Handle BuddyPress user fix.
				$this->fix_buddypress_users();
				return;
			} else {
				$this->show_step_header( 'select-demo' );
			}
			?>

		<div class="reign-demos-wrapper reign-importer-section">
			<?php

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['success'] ) && ( 'success' === sanitize_text_field( wp_unslash( $_GET['success'] ) ) ) ) {
				delete_option( 'wbcom_theme_demo_import_data' );
				delete_option( 'wbcom_theme_demo_req_plugins' );

				// Delete specific Kirki-related options.
				delete_option( '_transient_timeout_kirki_remote_url_contents' );
				delete_option( '_transient_kirki_remote_url_contents' );
				delete_option( '_site_transient_timeout_kirki_googlefonts_cache' );
				delete_option( '_site_transient_kirki_googlefonts_cache' );
				delete_option( 'kirki_downloaded_font_files' );

				// Regenerate Elementor CSS files.
				if ( class_exists( '\Elementor\Plugin' ) ) {
					\Elementor\Plugin::$instance->files_manager->clear_cache();
				}

				include_once 'success.php';
				/** To deal with GeoDirectory import issue. */
				if ( function_exists( 'geodir_tool_restore_cpt_from_taxonomies' ) ) {
					geodir_tool_restore_cpt_from_taxonomies();
				}
				// Sync GeoDirectory custom field columns and populate detail table after import.
				if ( class_exists( 'GeoDirectory' ) ) {
					$this->sync_geodir_custom_field_columns();
					$this->populate_geodir_detail_table();
					// Rebuild GeoDirectory page references in case pages were re-imported with new IDs.
					if ( class_exists( 'GeoDir_Admin_Install' ) ) {
						GeoDir_Admin_Install::create_pages();
					}
					// Reset repair flag so it re-runs on next admin load for any missed items.
					delete_option( 'wbcom_geodir_detail_repaired_v2' );
				}

				// Flush rewrite rules so CPT archive slugs (e.g. /places/) work correctly
				// after re-import. Without this, WordPress serves stale routing rules.
				flush_rewrite_rules( false );
				return;
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_GET['theme_slug'] ) && isset( $_GET['demo_slug'] ) && isset( $_GET['step'] ) && ( 'demo_import' === sanitize_text_field( wp_unslash( $_GET['step'] ) ) ) ) {

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$target_url       = isset( $_GET['target_url'] ) ? esc_url_raw( wp_unslash( $_GET['target_url'] ) ) : '';
				$target_demo_info = array();

				$current_url           = $this->get_demo_installer_page_url();
				$parent_url_to_request = WBCOM_DEMO_INSTALLER_PACKAGE_URL . 'demos.json';
				$retrieved_data        = '';
				$response              = wp_remote_get(
					$parent_url_to_request,
					array(
						'sslverify' => false,
						'timeout'   => 120,
					)
				);
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					echo esc_html( "Something went wrong: $error_message" );
				} elseif ( isset( $response['response']['code'] ) && ( 200 === (int) $response['response']['code'] ) ) {
						$response = isset( $response['body'] ) ? $response['body'] : '';
					if ( ! empty( $response ) ) {
						$response = json_decode( $response, true );
					}
					if ( ! empty( $response ) && is_array( $response ) ) {
						$motive_key = '';
						foreach ( $response as $key => $value ) {
							$demo_target_url = isset( $value['target_url'] ) ? $value['target_url'] : '';
							if ( $demo_target_url === $target_url ) {
								$target_demo_info = $value;
								break;
							}
						}
					} else {
						esc_html_e( 'No Theme Demo Available', 'wbcom-theme-demo-installer' );
					}
				}

				echo "<div class='wrap wbcom-demo-importer'>";
				?>
			<div class="reign-demos-alertboxes">
				<img src="<?php echo esc_url( WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'demos-imgs/' . $target_demo_info['screenshot'] ); ?>" style="width:100%;" />
			</div>
			<div class="reign-demos-progress-container">
				<div id="progress-bar-container" style="display: none;">
					<div class="skills completed">80%</div>
				</div>
				<div id="progress-snackbar"></div>
				<?php
				echo "<div class='loader' style='display:none;text-align:center;'></div>";
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				echo "<input type='hidden' id='theme_slug' value='" . esc_attr( sanitize_text_field( wp_unslash( $_GET['theme_slug'] ) ) ) . "' />";
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				echo "<input type='hidden' id='demo_slug' value='" . esc_attr( sanitize_text_field( wp_unslash( $_GET['demo_slug'] ) ) ) . "' />";
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				echo "<input type='hidden' id='target_url' value='" . esc_attr( esc_url_raw( wp_unslash( $_GET['target_url'] ) ) ) . "' />";
				echo "<input type='hidden' id='current_site_url' value='" . esc_attr( esc_url_raw( home_url() ) ) . "' />";
				echo "<button type='submit' id='wbcom_get_theme_demo_data' class='wbcom-button'>" . esc_html__( 'Install Demo', 'wbcom-theme-demo-installer' ) . '</button>';
				echo '<div id="wbtd-current-action" style="display:none;">downloading</div>';
				echo '</div>';
				?>
			</div>


			<div class="info-importer">
				<div class="info-impoter-heading">Please note:</div>
				<div class="info-impoter-content">
					<ul>
						<li>Demo Importer is suggested for <strong>Fresh Installation only</strong>. Please make sure you have a <strong>full backup</strong> of your site before importing demo data.</li>
						<li>Demo Importer is <strong>NOT compatible with Multisite</strong> installations. Please use a single site WordPress installation.</li>
						<li>Importing all the demo content will take some time, so please be patient.</li>
						<!-- <li>Seem's Hard ?? We offer free demo installation services, please submit details at <a href="https://brndle.com/downloads/free-theme-installation-service/"> Free Theme Installation</a> </li> -->
					</ul>
				</div>
			</div>

				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_GET['theme_slug'] ) && isset( $_GET['demo_slug'] ) && isset( $_GET['step'] ) && ( 'plugins_manager' === sanitize_text_field( wp_unslash( $_GET['step'] ) ) ) ) {

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$plugins_json_key = isset( $_GET['plugins_json_key'] ) ? sanitize_text_field( wp_unslash( $_GET['plugins_json_key'] ) ) : '';
				$url_to_request   = WBCOM_DEMO_INSTALLER_PACKAGE_PLUGINS_URL . $plugins_json_key . '/plugins.json';
				$retrieved_data   = '';
				$response         = wp_remote_get(
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
							$response = json_decode( $response, true );
						}
						if ( ! empty( $response ) && is_array( $response ) ) {
							update_option( 'wbcom_theme_demo_req_plugins', $response );
						}
					}
				}

				$num_of_req_plugins_installed = 0;
				$required_plugins_to_activate = 0;
				$demo_import_url              = $this->get_demo_installer_page_url(
					array(
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'theme_slug' => sanitize_text_field( wp_unslash( $_GET['theme_slug'] ) ),
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'demo_slug'  => sanitize_text_field( wp_unslash( $_GET['demo_slug'] ) ),
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'target_url' => esc_url_raw( wp_unslash( $_GET['target_url'] ) ),
						'step'       => 'demo_import',
					)
				);

				$plugins_list = get_option( 'wbcom_theme_demo_req_plugins', array() );
				?>
			<div class="goto-install-demo-step">
				<a href="<?php echo esc_url( $demo_import_url ); ?>" class="button button-primary"><?php esc_html_e( 'Go To Demo Installation', 'wbcom-theme-demo-installer' ); ?></a>
			</div>
				<?php
				foreach ( $plugins_list as $key => $plugin ) {
					$plugin_status = instantiate_wbcom_demo_importer_plugins_manager()->get_plugin_status( $plugin['slug'] );

					$plugin_dependency = 'Optional';
					if ( isset( $plugin['required'] ) && true === $plugin['required'] ) {
						++$required_plugins_to_activate;
						$plugin_dependency = 'Required';
						if ( 'Active' === $plugin_status['status_text'] ) {
							++$num_of_req_plugins_installed;
						}
					}
					$already_active_class = '';
					if ( 'Active' === $plugin_status['status_text'] ) {
						$already_active_class = 'already-active';
					}
					?>

				<div class="wbcom-req-plugin-card">
					<div class="plugin-container">
						<div class="plugin-importer-sec">
							<ul>
								<li class="importer-plugin-thumb"><img src="<?php echo esc_url( ! empty( $plugin['plugin_thumb'] ) ? $plugin['plugin_thumb'] : WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'plugin-thumb/admin-plugins.svg' ); ?>" alt="plugin-thumb" class="plugin_image"></li>
								<li class="plugin-name"><?php echo esc_html( $plugin['name'] ); ?></li>
								<li class="plugin-status"><span class="<?php echo esc_attr( $already_active_class ); ?>"><?php echo esc_html( $plugin_status['status_text'] ); ?></span></li>
								<li class="plugin-dependency <?php echo esc_attr( strtolower( $plugin_dependency ) ); ?>"><?php echo esc_html( $plugin_dependency ); ?></li>
								<li class="plugin-description"><?php echo esc_html( $plugin['description'] ); ?></li>
								<li class="importer-button">
								<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
								<input type="hidden" class="demo-name" name="demo-name" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['plugins_json_key'] ) ) ); ?>">
								<input type="hidden" class="plugin-slug" name="plugin-slug" value="<?php echo esc_attr( $plugin['slug'] ); ?>">
								<input type="hidden" class="plugin-action" name="plugin-action" value="<?php echo esc_attr( $plugin_status['action'] ); ?>">
									<?php
									if ( isset( $plugin['is_paid'] ) ) {
										if ( 'Active' !== $plugin_status['status_text'] ) {
											?>
												<a class="button button-primary buy-now-plugins" target="_blank" href="<?php echo esc_url( $plugin['external_url'] ); ?>"><?php esc_html_e( 'Buy Now', 'wbcom-theme-demo-installer' ); ?></a>
												<a class="plugin-action-button button upload-plugins" target="_blank" href="plugin-install.php"><?php esc_html_e( 'Upload Plugin', 'wbcom-theme-demo-installer' ); ?></a>
												<?php
										} else {
											?>
												<button class="plugin-action-button button <?php echo esc_attr( $already_active_class ); ?>"><?php echo esc_html( $plugin_status['action_text'] ); ?></button>
												<?php
										}
									} else {
										?>
											<button class="plugin-action-button button <?php echo esc_attr( $already_active_class ); ?>"><?php echo esc_html( $plugin_status['action_text'] ); ?></button>
											<?php
									}
									?>
								</li>
							</ul>

						</div>
					</div>
				</div>
					<?php
				}
				?>
			<div class="demo_listing_modal"></div>
			<input type="hidden" id="required_plugins_to_activate" name="required_plugins_to_activate" value="<?php echo esc_attr( $required_plugins_to_activate ); ?>">
			<input type="hidden" id="num_of_req_plugins_installed" name="num_of_req_plugins_installed" value="<?php echo esc_attr( $num_of_req_plugins_installed ); ?>">
				<?php
			} else {
				delete_option( 'wbcom_theme_demo_import_data' );
				delete_option( 'wbcom_theme_demo_req_plugins' );

				$current_url = $this->get_demo_installer_page_url();

				$parent_url_to_request = WBCOM_DEMO_INSTALLER_PACKAGE_URL . 'demos.json';
				$retrieved_data        = '';
				$response              = wp_remote_get(
					$parent_url_to_request,
					array(
						'sslverify' => false,
						'timeout'   => 120,
					)
				);

				echo '<div id="demos_import_filter">';

				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					echo esc_html( "Something went wrong: $error_message" );
				} elseif ( isset( $response['response']['code'] ) && ( 200 === (int) $response['response']['code'] ) ) {
						$response = isset( $response['body'] ) ? $response['body'] : '';
					if ( ! empty( $response ) ) {
						$response = json_decode( $response, true );
					}
					if ( ! empty( $response ) && is_array( $response ) ) {
						$motive_key = '';
						foreach ( $response as $key => $value ) {
							if ( ( 0 !== $key ) && ( $motive_key !== $value['motive_key'] ) ) {
								echo '</div>';
							}
							if ( $motive_key !== $value['motive_key'] ) {
								$motive_key = $value['motive_key'];
								echo '<div class="demo-content-wrap">';
							}
							$preview_url = isset( $value['preview_url'] ) ? $value['preview_url'] : '';
							$href        = $this->get_demo_installer_page_url(
								array(
									'theme_slug'       => $value['theme_slug'],
									'demo_slug'        => $value['demo_slug'],
									'target_url'       => $value['target_url'],
									'step'             => 'plugins_manager',
									'plugins_json_key' => $value['plugins_json_key'],
								)
							);

							// Get demo categories based on plugins.
							$demo_categories = $this->get_demo_categories( $value['plugins_json_key'] );
							$categories_attr = ! empty( $demo_categories ) ? implode( ' ', $demo_categories ) : '';
							?>
								<div class='wbcom-demo-importer demo-details import_filter <?php echo esc_attr( $value['motive_key'] ); ?>' data-demo-slug="<?php echo esc_attr( $value['demo_slug'] ); ?>" data-plugins-key="<?php echo esc_attr( $value['plugins_json_key'] ); ?>" data-categories="<?php echo esc_attr( $categories_attr ); ?>">
									<div class="container">
										<img src="<?php echo esc_url( WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'demos-imgs/' . $value['screenshot'] ); ?>" alt="Avatar" class="image" style="width:100%">
										<div class="demo-title">
											<h2 class="demo-name"><?php echo esc_html( $value['demo_name'] ); ?></h2>
										<?php if ( ! empty( $demo_categories ) ) : ?>
												<div class="category-badges">
													<?php foreach ( $demo_categories as $category ) : ?>
														<span class="category-badge <?php echo esc_attr( $category ); ?>"><?php echo esc_html( $category ); ?></span>
													<?php endforeach; ?>
												</div>
											<?php endif; ?>
											<form method="get" action="<?php echo esc_url( $current_url ); ?>">
												<div class="middle demo-import-actions">
													<a href="<?php echo esc_url( $href ); ?>" class="wbcom-button import"><?php echo 'Import'; ?></a>
													<a target="_blank" href="<?php echo esc_url( $preview_url ); ?>" class="wbcom-button preview"><?php echo 'Preview'; ?></a>
												</div>
											</form>
										</div>
									</div>
								</div>
								<?php
								if ( ( count( $response ) - 1 ) === $key ) {
									echo '</div>';
								}
						}
					} else {
						esc_html_e( 'No Theme Demo Available', 'wbcom-theme-demo-installer' );
					}
				}
			}
			echo '</div>';
			echo '</div>';

			echo '</div>';
		}

		/**
		 * Enqueue admin scripts and styles.
		 */
		public function admin_enqueue_scripts() {
			$screen = get_current_screen();
			if ( 'toplevel_page_wbcom-theme-demo-installer' !== $screen->id ) {
				return; }

			$required_plugins_to_activate = 0;
			$plugins_list                 = get_option( 'wbcom_theme_demo_req_plugins', array() );
			foreach ( $plugins_list as $key => $value ) {
				if ( $value['required'] ) {
					++$required_plugins_to_activate;
				}
			}

			wp_register_script(
				'wbcom_theme_demo_installer_js',
				WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'assets/js/importer.js',
				array( 'jquery' ),
				time(),
				true
			);
			wp_register_script(
				'wbcom_theme_demo_installer_js_filter',
				WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'assets/js/jquery.mixitup.min.js',
				array( 'jquery' ),
				time(),
				true
			);

			wp_localize_script(
				'wbcom_theme_demo_installer_js',
				'wbcom_theme_demo_installer_params',
				array(
					'ajax_url'                     => admin_url( 'admin-ajax.php' ),
					'success_url'                  => $this->get_demo_installer_page_url( array( 'success' => 'success' ) ),
					'required_plugins_to_activate' => $required_plugins_to_activate,
					'ajax_nonce'                   => wp_create_nonce( 'wbcom_demo_installer_nonce' ),
				)
			);

			wp_enqueue_script( 'wbcom_theme_demo_installer_js' );
			wp_enqueue_script( 'wbcom_theme_demo_installer_js_filter' );

			// Register and enqueue demo filter assets.
			wp_register_script(
				'wbcom_demo_filter_js',
				WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'assets/js/demo-filter.js',
				array( 'jquery' ),
				time(),
				true
			);
			wp_enqueue_script( 'wbcom_demo_filter_js' );

			wp_register_style(
				'wbcom-demo-listing-css',
				WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'assets/css/demo-listing.css',
				array(),
				time(),
				'all'
			);
			wp_enqueue_style( 'wbcom-demo-listing-css' );

			// Register and enqueue demo filter styles.
			wp_register_style(
				'wbcom-demo-filter-css',
				WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'assets/css/demo-filter.css',
				array(),
				time(),
				'all'
			);
			wp_enqueue_style( 'wbcom-demo-filter-css' );
		}

		/**
		 * Get the demo installer page URL.
		 *
		 * @param array $args Query arguments to append.
		 * @return string
		 */
		public function get_demo_installer_page_url( $args = array() ) {
			$current_url        = admin_url();
			$installer_page_url = $current_url . 'admin.php?page=wbcom-theme-demo-installer';
			if ( ! empty( $args ) ) {
				$installer_page_url = add_query_arg(
					$args,
					$installer_page_url
				);
			}
			return $installer_page_url;
		}

		/**
		 * Get demo categories based on plugins used.
		 *
		 * @param string $plugins_key The plugins JSON key identifier.
		 */
		public function get_demo_categories( $plugins_key ) {
			$categories = array();

			// Plugin to category mapping.
			$plugin_category_map = array(
				// BuddyBoss plugins.
				'buddyboss-platform'         => 'buddyboss',

				// BuddyPress plugins.
				'buddypress'                 => 'buddypress',

				// PeepSo plugins.
				'peepso'                     => 'peepso',
				'peepso-core'                => 'peepso',

				// LMS plugins.
				'learndash'                  => 'lms',
				'sfwd-lms'                   => 'lms',
				'sensei-lms'                 => 'lms',
				'tutor'                      => 'lms',
				'lifterlms'                  => 'lms',

				// Marketplace plugins.
				'dokan-lite'                 => 'marketplace',
				'dokan'                      => 'marketplace',
				'wc-multivendor-marketplace' => 'marketplace',
				'wc-frontend-manager'        => 'marketplace',
				'wc-vendors'                 => 'marketplace',

				// Directory plugins.
				'geodirectory'               => 'directory',

				// Job plugins.
				'wp-job-manager'             => 'jobs',
			);

			// Try to read plugins.json file.
			$plugins_file = WBCOM_Theme_Demo_Installer_PLUGIN_DIR_PATH . 'demo-plugins/' . $plugins_key . '/plugins.json';

			if ( file_exists( $plugins_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$plugins_data = file_get_contents( $plugins_file );
				$plugins_data = json_decode( $plugins_data, true );

				if ( is_array( $plugins_data ) ) {
					foreach ( $plugins_data as $plugin ) {
						if ( isset( $plugin['slug'] ) && isset( $plugin_category_map[ $plugin['slug'] ] ) ) {
							$category = $plugin_category_map[ $plugin['slug'] ];
							if ( ! in_array( $category, $categories, true ) ) {
								$categories[] = $category;
							}
						}
					}
				}
			}

			return $categories;
		}

		/**
		 * Populate GeoDirectory detail table from wp_posts after demo import.
		 *
		 * The demo exporter may not export the geodir detail table, leaving it empty
		 * even though gd_place posts exist in wp_posts. This rebuilds the detail table
		 * with basic data from wp_posts and term relationships.
		 */
		public function populate_geodir_detail_table() {
			global $wpdb;

			$post_types = function_exists( 'geodir_get_posttypes' ) ? geodir_get_posttypes() : array( 'gd_place' );

			foreach ( $post_types as $post_type ) {
				$table = $wpdb->prefix . 'geodir_' . $post_type . '_detail';

				// Check if table exists.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
					continue;
				}

				// Get all published posts for this post type.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$posts = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'draft', 'pending')",
						$post_type
					)
				);

				if ( empty( $posts ) ) {
					continue;
				}

				$taxonomy = $post_type . 'category';

				foreach ( $posts as $post ) {
					// Skip if a detail row already exists for this post ID.
					// Handles the case where the demo JSON import populated the table with
					// source-site IDs that don't match the locally re-assigned post IDs.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$existing = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM `{$table}` WHERE post_id = %d", $post->ID ) );
					if ( $existing ) {
						continue;
					}

					// Get categories from term relationships.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$term_ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT t.term_id FROM {$wpdb->term_relationships} tr
							INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
							INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
							WHERE tr.object_id = %d AND tt.taxonomy = %s",
							$post->ID,
							$taxonomy
						)
					);

					// Get tags.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$tag_names = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT t.name FROM {$wpdb->term_relationships} tr
							INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
							INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
							WHERE tr.object_id = %d AND tt.taxonomy = %s",
							$post->ID,
							$post_type . '_tags'
						)
					);

					$post_category    = ! empty( $term_ids ) ? ',' . implode( ',', $term_ids ) . ',' : '';
					$default_category = ! empty( $term_ids ) ? (int) $term_ids[0] : 0;
					$post_tags        = ! empty( $tag_names ) ? implode( ',', $tag_names ) : '';

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->insert(
						$table,
						array(
							'post_id'          => $post->ID,
							'post_title'       => $post->post_title,
							'_search_title'    => sanitize_title( $post->post_title ),
							'post_status'      => $post->post_status,
							'post_category'    => $post_category,
							'default_category' => $default_category,
							'post_tags'        => $post_tags,
						)
					);
				}
			}
		}
	}

endif;

/**
 * Main instance of WBCOM_TDI_ADMIN_SETTINGS.
 *
 * @since  1.0.0
 * @return WBCOM_TDI_ADMIN_SETTINGS
 */
WBCOM_TDI_ADMIN_SETTINGS::instance();
