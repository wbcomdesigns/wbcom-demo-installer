<?php
/**
 * Plugin Name: Reign Theme Demo Installer
 * Plugin URI: https://wbcomdesigns.com/downloads/reign-wordpress-theme/
 * Description: One-click demo content installer for the Reign theme. Import complete demo sites with plugins, content, and settings.
 * Version: 3.0.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com/
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 *
 * Text Domain: reign-demo-installer
 * Domain Path: /languages/
 *
 * @package Reign_Demo_Installer
 * @category Core
 * @author Wbcom Designs
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// EMERGENCY FIX - Force load pluggable functions
if ( ! function_exists( 'wp_get_current_user' ) ) {
    require_once( ABSPATH . 'wp-includes/pluggable.php' );
}

// Check if Reign theme is active
if ( get_template() !== 'reign' && ! is_admin() ) {
	return;
}

if ( ! class_exists( 'Reign_Demo_Installer' ) ) :

	/**
	 * Main Reign_Demo_Installer Class.
	 *
	 * @class Reign_Demo_Installer
	 * @version 3.0.0
	 */
	class Reign_Demo_Installer {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		public $version = '3.0.0';

		/**
		 * The single instance of the class.
		 *
		 * @var Reign_Demo_Installer
		 * @since 3.0.0
		 */
		protected static $_instance = null;

		/**
		 * Main Reign_Demo_Installer Instance.
		 *
		 * Ensures only one instance of Reign_Demo_Installer is loaded or can be loaded.
		 *
		 * @since 3.0.0
		 * @static
		 * @return Reign_Demo_Installer - Main instance.
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Reign_Demo_Installer Constructor.
		 */
		public function __construct() {
			$this->define_constants();
			$this->check_requirements();
			$this->includes();
			add_action( 'plugins_loaded', array( $this, 'init_hooks' ) );

			do_action( 'reign_demo_installer_loaded' );
		}

		/**
		 * Check plugin requirements.
		 * 
		 * @since 3.0.0
		 */
		private function check_requirements() {
			// Check PHP version
			if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
				add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
				return;
			}

			// Check WordPress version
			if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
				add_action( 'admin_notices', array( $this, 'wp_version_notice' ) );
				return;
			}
		}

		/**
		 * Display PHP version notice.
		 */
		public function php_version_notice() {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Reign Demo Installer requires PHP 7.4 or higher. Please update your PHP version.', 'reign-demo-installer' );
			echo '</p></div>';
		}

		/**
		 * Display WordPress version notice.
		 */
		public function wp_version_notice() {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Reign Demo Installer requires WordPress 5.0 or higher. Please update your WordPress version.', 'reign-demo-installer' );
			echo '</p></div>';
		}

		/**
		 * Hook into actions and filters.
		 * 
		 * @since 3.0.0
		 */
		public function init_hooks() {
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
			add_filter( 'plugin_action_links_' . REIGN_DEMO_INSTALLER_PLUGIN_BASENAME, array( $this, 'alter_plugin_action_links' ) );
			
			// Security: Only allow installation for administrators
			if ( ! function_exists( 'current_user_can' ) || ( ! current_user_can( 'manage_options' ) && is_admin() ) ) {
				return;
			}

			// Initialize update checker if in admin
			if ( is_admin() ) {
				add_action( 'wp_loaded', array( $this, 'init_update_checker' ) );
			}
		}

		/**
		 * Add settings link to plugin actions.
		 * 
		 * @param array $plugin_links
		 * @return array
		 */
		function alter_plugin_action_links( $plugin_links ) {
			if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
				$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=reign-demo-installer' ) ) . '">' . 
								esc_html__( 'Import Demos', 'reign-demo-installer' ) . '</a>';
				array_unshift( $plugin_links, $settings_link );
			}
			return $plugin_links;
		}

		/**
		 * Define plugin Constants.
		 */
		private function define_constants() {
			$this->define( 'REIGN_DEMO_INSTALLER_PLUGIN_FILE', __FILE__ );
			$this->define( 'REIGN_DEMO_INSTALLER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			$this->define( 'REIGN_DEMO_INSTALLER_VERSION', $this->version );
			$this->define( 'REIGN_DEMO_INSTALLER_TEXT_DOMAIN', 'reign-demo-installer' );
			$this->define( 'REIGN_DEMO_INSTALLER_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
			$this->define( 'REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
			$this->define( 'REIGN_DEMO_INSTALLER_PACKAGE_URL', REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL . 'demos/' );
			$this->define( 'REIGN_DEMO_INSTALLER_PACKAGE_PLUGINS_URL', REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL . 'demo-plugins/' );
			
			// Security constants
			$this->define( 'REIGN_DEMO_INSTALLER_NONCE_KEY', 'reign_demo_installer_nonce' );
			$this->define( 'REIGN_DEMO_INSTALLER_MAX_EXECUTION_TIME', 300 ); // 5 minutes
			$this->define( 'REIGN_DEMO_INSTALLER_MEMORY_LIMIT', '512M' );
		}

		/**
		 * Define constant if not already set.
		 *
		 * @param string $name
		 * @param string|bool $value
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
			// Core includes
			include_once 'core/class-reign-demo-installer-logger.php';
			include_once 'core/class-reign-demo-installer-security.php';
			include_once 'core/class-reign-demo-installer-environment.php';
			
			// Admin includes
			if ( is_admin() ) {
				include_once 'core/admin-settings.php';
				include_once 'core/ajax-handler.php';
				include_once 'core/plugins-manager.php';
				include_once 'core/prerequisites-checks.php';
			}

			// Legacy compatibility (to be phased out)
			$this->load_legacy_compatibility();
		}

		/**
		 * Load legacy compatibility functions.
		 * 
		 * @deprecated 3.0.0 Use new class structure instead
		 */
		private function load_legacy_compatibility() {
			// Map old function names to new classes for backward compatibility
			if ( ! function_exists( 'instantiate_wbcom_demo_importer_plugins_manager' ) ) {
				function instantiate_wbcom_demo_importer_plugins_manager() {
					return Reign_Demo_Installer_Plugins_Manager::instance();
				}
			}
		}

		/**
		 * Initialize update checker.
		 */
		public function init_update_checker() {
			// This would be implemented if you have an update server
			// For now, we'll skip this as it's not production critical
		}

		/**
		 * Load Localisation files.
		 */
		public function load_plugin_textdomain() {
			$locale = apply_filters( 'reign_demo_installer_plugin_locale', get_locale(), REIGN_DEMO_INSTALLER_TEXT_DOMAIN );
			
			load_textdomain( 
				REIGN_DEMO_INSTALLER_TEXT_DOMAIN, 
				REIGN_DEMO_INSTALLER_PLUGIN_DIR_PATH . 'languages/' . REIGN_DEMO_INSTALLER_TEXT_DOMAIN . '-' . $locale . '.mo' 
			);
			
			load_plugin_textdomain( 
				REIGN_DEMO_INSTALLER_TEXT_DOMAIN, 
				false, 
				plugin_basename( dirname( __FILE__ ) ) . '/languages' 
			);
		}

		/**
		 * Get the plugin url.
		 * 
		 * @return string
		 */
		public function plugin_url() {
			return untrailingslashit( plugins_url( '/', __FILE__ ) );
		}

		/**
		 * Get the plugin path.
		 * 
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		}

		/**
		 * Get plugin data.
		 * 
		 * @param string $key
		 * @return string
		 */
		public function get_plugin_data( $key = '' ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			
			$plugin_data = get_plugin_data( __FILE__ );
			
			if ( ! empty( $key ) && isset( $plugin_data[ $key ] ) ) {
				return $plugin_data[ $key ];
			}
			
			return $plugin_data;
		}

		/**
		 * Log messages for debugging.
		 * 
		 * @param string $message
		 * @param string $level
		 */
		public static function log( $message, $level = 'info' ) {
			if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
				Reign_Demo_Installer_Logger::log( $message, $level );
			}
		}
	}

endif;

/**
 * Main instance of Reign_Demo_Installer.
 *
 * Returns the main instance of Reign_Demo_Installer to prevent the need to use globals.
 *
 * @since 3.0.0
 * @return Reign_Demo_Installer
 */
function reign_demo_installer() {
	return Reign_Demo_Installer::instance();
}

// Initialize the plugin
add_action( 'plugins_loaded', 'reign_demo_installer' );

// Global for backwards compatibility.
add_action( 'plugins_loaded', function() {
	$GLOBALS['reign_demo_installer'] = reign_demo_installer();
} );