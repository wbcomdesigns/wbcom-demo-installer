<?php
/**
 * Plugin Name: Wbcom Theme Demo Installer
 * Plugin URI: https://wbcomdesigns.com/
 * Description: Wbcom Theme Demo Installer
 * Version: 3.1.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com/
 * Requires at least: 4.0
 * Tested up to: 6.8.2
 *
 * Text Domain: wbcom-theme-demo-installer
 * Domain Path: /i18n/languages/
 *
 * @package WBCOM_Theme_Demo_Installer
 * @category Core
 * @author Wbcom Designs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WBCOM_Theme_Demo_Installer' ) ) :

	/**
	 * Main WBCOM_Theme_Demo_Installer Class.
	 *
	 * @class WBCOM_Theme_Demo_Installer
	 * @version 1.0.0
	 */
	class WBCOM_Theme_Demo_Installer {

		/**
		 * WBCOM_Theme_Demo_Installer version.
		 *
		 * @var string
		 */
		public $version = '3.1.0';

		/**
		 * The single instance of the class.
		 *
		 * @var WBCOM_Theme_Demo_Installer
		 * @since 1.0.0
		 */
		protected static $_instance = null;


		/**
		 * Main WBCOM_Theme_Demo_Installer Instance.
		 *
		 * Ensures only one instance of WBCOM_Theme_Demo_Installer is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @see INSTANTIATE_WBCOM_Theme_Demo_Installer()
		 * @return WBCOM_Theme_Demo_Installer - Main instance.
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}


		/**
		 * WBCOM_Theme_Demo_Installer Constructor.
		 */
		public function __construct() {
			$this->define_constants();
			$this->includes();
			$this->init_hooks();

			do_action( 'wbcom_theme_demo_installer_loaded' );
		}

		/**
		 * Hook into actions and filters.
		 *
		 * @since  1.0.0
		 */
		private function init_hooks() {
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
			add_filter( 'plugin_action_links_' . WBCOM_Theme_Demo_Installer_PLUGIN_BASENAME, array( $this, 'alter_plugin_action_links' ) );
			add_action( 'wp', array( $this, 'installer_update_checker' ) );
			add_filter( 'geodir_is_archive_page_id', array( $this, 'fix_geodir_archive_page_id' ), 10, 2 );
		}

		/**
		 * Fix GeoDirectory archive page loop warnings after demo re-import.
		 *
		 * Root cause: GeoDir_Template_Loader::setup_archive_loop_as_page() replaces
		 * $wp_query->posts with [archive_page] (1 element) and only resets post_count
		 * to 1 when no places exist. When places DO exist, post_count stays at 10 but
		 * posts has 1 element. GeoDirectory's setup_archive_page_content() filter was
		 * supposed to stop the outer loop (by setting current_post = post_count), but
		 * it only fires when is_archive_page_content() returns true — which requires
		 * is_archive_page_id($post->ID) to match geodir_settings['page_archive'].
		 *
		 * After re-import, the settings can be stale (old page IDs) so the IDs don't
		 * match, the outer loop stopper never fires, and next_post() generates
		 * "Undefined array key 1...9" warnings.
		 *
		 * Fix: add a geodir_is_archive_page_id filter that returns true for the exact
		 * archive page GeoDirectory chose ($wp_query->posts[0]) when $gd_temp_wp_query
		 * is non-empty (i.e., we are inside the GeoDirectory archive page setup state).
		 * This allows GeoDirectory's own loop management to run correctly:
		 * - restores all place posts into $wp_query->posts
		 * - runs the inner listing loop at full post_count
		 * - stops the outer theme loop via current_post = post_count
		 *
		 * @param bool $result Current result.
		 * @param int  $id     Page ID being checked.
		 * @return bool
		 */
		public function fix_geodir_archive_page_id( $result, $id ) {
			if ( $result ) {
				return $result;
			}

			// Only applies on GeoDirectory CPT archives (/places/) and taxonomy archives
			// (/places/category/restaurants/ etc.) — the two page types that call
			// setup_archive_loop_as_page() which triggers this mismatch.
			$is_gd_archive = (
				( function_exists( 'geodir_is_post_type_archive' ) && geodir_is_post_type_archive() ) ||
				( function_exists( 'geodir_is_taxonomy' ) && geodir_is_taxonomy() )
			);

			if ( ! $is_gd_archive ) {
				return $result;
			}

			global $wp_query, $gd_temp_wp_query;

			// Only apply when GeoDirectory has saved the real listing posts in $gd_temp_wp_query
			// (i.e., setup_archive_loop_as_page has run and replaced $wp_query->posts with a
			// single archive template page) AND the $id being checked is exactly that page.
			// This ensures setup_archive_page_content() fires and:
			//   1. Restores all listing posts into $wp_query->posts.
			//   2. Runs the inner listing loop at the full post_count.
			//   3. Stops the outer theme loop via current_post = post_count.
			if (
				! empty( $gd_temp_wp_query ) &&
				! empty( $wp_query->posts ) &&
				count( $wp_query->posts ) === 1 &&
				! empty( $wp_query->posts[0]->ID ) &&
				(int) $wp_query->posts[0]->ID === (int) $id
			) {
				return true;
			}

			return $result;
		}

		/**
		 * Add settings link to plugin action links.
		 *
		 * @param array $plugin_links Existing plugin links.
		 * @return array Modified plugin links.
		 */
		public function alter_plugin_action_links( $plugin_links ) {
			$settings_link = '<a href="admin.php?page=wbcom-theme-demo-installer">Settings</a>';
			array_unshift( $plugin_links, $settings_link );
			return $plugin_links;
		}

		/**
		 * Define WBCOM_Theme_Demo_Installer Constants.
		 */
		private function define_constants() {
			$this->define( 'WBCOM_Theme_Demo_Installer_PLUGIN_FILE', __FILE__ );
			$this->define( 'WBCOM_Theme_Demo_Installer_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			$this->define( 'WBCOM_Theme_Demo_Installer_VERSION', $this->version );
			$this->define( 'WBCOM_Theme_Demo_Installer_TEXT_DOMAIN', 'wbcom-theme-demo-installer' );
			$this->define( 'WBCOM_Theme_Demo_Installer_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
			$this->define( 'WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
			$this->define( 'WBCOM_DEMO_INSTALLER_PACKAGE_URL', WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'demos/' );
			$this->define( 'WBCOM_DEMO_INSTALLER_PACKAGE_PLUGINS_URL', WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'demo-plugins/' );
		}

		/**
		 * Define constant if not already set.
		 *
		 * @param  string      $name  Constant name.
		 * @param  string|bool $value Constant value.
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 */
		public function includes() {
			include_once 'core/admin-settings.php';
			include_once 'core/ajax-handler.php';
			include_once 'core/plugins-manager.php';
			include_once 'update-checker/update-checker.php';
		}

		/**
		 * Load Localisation files.
		 */
		public function load_plugin_textdomain() {
			$locale = apply_filters( 'wbcom_theme_demo_installer_plugin_locale', get_locale(), 'wbcom-theme-demo-installer' );
			load_textdomain( 'wbcom-theme-demo-installer', WBCOM_Theme_Demo_Installer_PLUGIN_DIR_PATH . 'language/wbcom-theme-demo-installer-' . $locale . '.mo' );
			load_plugin_textdomain( 'wbcom-theme-demo-installer', false, plugin_basename( __DIR__ ) . '/language' );
		}

		/**
		 * Initialize the plugin update checker.
		 */
		public function installer_update_checker() {
			if ( class_exists( 'PucFactory' ) ) {
				$my_update_checker = PucFactory::buildUpdateChecker(
					'https://demos.wbcomdesigns.com/exporter/free-plugins/wbcom-demo-installer.json',
					__FILE__,
					'wbcom-demo-installer'
				);
			}
		}
	}

endif;

/**
 * Main instance of WBCOM_Theme_Demo_Installer.
 *
 * Returns the main instance of WBCOM_Theme_Demo_Installer to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return WBCOM_Theme_Demo_Installer
 */
function instantiate_wbcom_theme_demo_installer() {
	return WBCOM_Theme_Demo_Installer::instance();
}

// Global for backwards compatibility.
$GLOBALS['wbcom_theme_demo_installer'] = instantiate_wbcom_theme_demo_installer();
