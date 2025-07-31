<?php
/**
 * Plugin Name: Reign Theme Demo Installer
 * Plugin URI: https://wbcomdesigns.com/downloads/reign-wordpress-theme/
 * Description: One-click demo content installer for the Reign theme. Import complete demo sites with plugins, content, and settings.
 * Version: 3.0.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com/
 * Requires at least: 5.0
 * Tested up to: 6.8.2
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
	exit;
}

// Emergency fix for missing pluggable functions
if ( ! function_exists( 'wp_get_current_user' ) && file_exists( ABSPATH . 'wp-includes/pluggable.php' ) ) {
	require_once ABSPATH . 'wp-includes/pluggable.php';
}

// Only proceed if Reign theme is active or in admin
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
	final class Reign_Demo_Installer {

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
		 */
		protected static $_instance = null;

		/**
		 * Service container.
		 *
		 * @var array
		 */
		private $services = array();

		/**
		 * Plugin initialization status.
		 *
		 * @var bool
		 */
		private $initialized = false;

		/**
		 * Main Reign_Demo_Installer Instance.
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
		 * Cloning is forbidden.
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, 'Cloning is forbidden.', '3.0.0' );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, 'Unserializing instances is forbidden.', '3.0.0' );
		}

		/**
		 * Reign_Demo_Installer Constructor.
		 */
		public function __construct() {
			if ( $this->initialized ) {
				return;
			}

			$this->define_constants();
			
			if ( ! $this->check_requirements() ) {
				return;
			}

			$this->includes();
			$this->init_hooks();
			$this->initialized = true;

			do_action( 'reign_demo_installer_loaded' );
		}

		/**
		 * Check plugin requirements.
		 * 
		 * @since 3.0.0
		 * @return bool
		 */
		private function check_requirements() {
			$requirements_met = true;

			// Check PHP version
			if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
				add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
				$requirements_met = false;
			}

			// Check WordPress version
			if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
				add_action( 'admin_notices', array( $this, 'wp_version_notice' ) );
				$requirements_met = false;
			}

			// Check for required PHP extensions
			$required_extensions = array( 'curl', 'zip', 'json', 'xml' );
			foreach ( $required_extensions as $extension ) {
				if ( ! extension_loaded( $extension ) ) {
					add_action( 'admin_notices', function() use ( $extension ) {
						$this->extension_notice( $extension );
					});
					$requirements_met = false;
				}
			}

			return $requirements_met;
		}

		/**
		 * Display PHP version notice.
		 */
		public function php_version_notice() {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Reign Demo Installer', 'reign-demo-installer' ); ?>:</strong>
					<?php
					printf(
						esc_html__( 'PHP version %s or higher is required. Current version: %s. Please update your PHP version.', 'reign-demo-installer' ),
						'<code>7.4</code>',
						'<code>' . PHP_VERSION . '</code>'
					);
					?>
				</p>
			</div>
			<?php
		}

		/**
		 * Display WordPress version notice.
		 */
		public function wp_version_notice() {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Reign Demo Installer', 'reign-demo-installer' ); ?>:</strong>
					<?php
					printf(
						esc_html__( 'WordPress version %s or higher is required. Current version: %s. Please update WordPress.', 'reign-demo-installer' ),
						'<code>5.0</code>',
						'<code>' . get_bloginfo( 'version' ) . '</code>'
					);
					?>
				</p>
			</div>
			<?php
		}

		/**
		 * Display extension missing notice.
		 *
		 * @param string $extension Extension name
		 */
		public function extension_notice( $extension ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Reign Demo Installer', 'reign-demo-installer' ); ?>:</strong>
					<?php
					printf(
						esc_html__( 'Required PHP extension "%s" is missing. Please contact your hosting provider.', 'reign-demo-installer' ),
						esc_html( strtoupper( $extension ) )
					);
					?>
				</p>
			</div>
			<?php
		}

		/**
		 * Hook into actions and filters.
		 * 
		 * @since 3.0.0
		 */
		public function init_hooks() {
			add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
			add_filter( 'plugin_action_links_' . REIGN_DEMO_INSTALLER_PLUGIN_BASENAME, array( $this, 'alter_plugin_action_links' ) );
			
			// Admin only hooks
			if ( is_admin() ) {
				add_action( 'admin_init', array( $this, 'admin_init' ) );
			}
		}

		/**
		 * When WP has loaded all plugins, trigger the `reign_demo_installer_init` hook.
		 */
		public function on_plugins_loaded() {
			do_action( 'reign_demo_installer_init' );
		}

		/**
		 * Admin initialization.
		 */
		public function admin_init() {
			// Only allow installation for administrators
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Initialize services for admin users
			$this->init_services();
		}

		/**
		 * Initialize services.
		 */
		private function init_services() {
			$this->services['security'] = Reign_Demo_Installer_Security::instance();
			$this->services['logger'] = new Reign_Demo_Installer_Logger();
			$this->services['environment'] = new Reign_Demo_Installer_Environment();
		}

		/**
		 * Get service instance.
		 *
		 * @param string $service Service name
		 * @return mixed|null
		 */
		public function get_service( $service ) {
			return isset( $this->services[ $service ] ) ? $this->services[ $service ] : null;
		}

		/**
		 * Add settings link to plugin actions.
		 * 
		 * @param array $plugin_links
		 * @return array
		 */
		public function alter_plugin_action_links( $plugin_links ) {
			if ( current_user_can( 'manage_options' ) ) {
				$settings_link = sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=reign-demo-installer' ) ),
					esc_html__( 'Import Demos', 'reign-demo-installer' )
				);
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
			$this->define( 'REIGN_DEMO_INSTALLER_MAX_EXECUTION_TIME', 300 );
			$this->define( 'REIGN_DEMO_INSTALLER_MEMORY_LIMIT', '512M' );
		}

		/**
		 * Define constant if not already set.
		 *
		 * @param string $name Constant name
		 * @param string|bool $value Constant value
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Include required core files.
		 */
		public function includes() {
			$includes = array(
				'core/class-reign-demo-installer-logger.php',
				'core/class-reign-demo-installer-security.php',
				'core/class-reign-demo-installer-environment.php',
			);

			// Admin-only includes
			if ( is_admin() ) {
				$admin_includes = array(
					'core/class-reign-demo-installer-admin-guardian.php',
					'core/admin-settings.php',
					'core/ajax-handler.php',
					'core/plugins-manager.php',
					'core/prerequisites-checks.php',
				);
				$includes = array_merge( $includes, $admin_includes );
			}

			foreach ( $includes as $file ) {
				$file_path = REIGN_DEMO_INSTALLER_PLUGIN_DIR_PATH . $file;
				if ( file_exists( $file_path ) ) {
					require_once $file_path;
				}
			}

			// Load backward compatibility last
			$this->load_backward_compatibility();
		}

		/**
		 * Load backward compatibility functions.
		 */
		private function load_backward_compatibility() {
			$compatibility_file = REIGN_DEMO_INSTALLER_PLUGIN_DIR_PATH . 'core/compatibility-functions.php';
			if ( file_exists( $compatibility_file ) ) {
				require_once $compatibility_file;
			}
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
		 * @param string $key Optional specific key to retrieve
		 * @return string|array
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
		 * @param string $message Log message
		 * @param string $level Log level
		 */
		public static function log( $message, $level = 'info' ) {
			if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
				Reign_Demo_Installer_Logger::log( $message, $level );
			}
		}

		/**
		 * Get plugin version.
		 *
		 * @return string
		 */
		public function get_version() {
			return $this->version;
		}

		/**
		 * Check if plugin is properly initialized.
		 *
		 * @return bool
		 */
		public function is_initialized() {
			return $this->initialized;
		}
	}

endif;

/**
 * Main instance of Reign_Demo_Installer.
 *
 * @since 3.0.0
 * @return Reign_Demo_Installer
 */
function reign_demo_installer() {
	return Reign_Demo_Installer::instance();
}

// Global for backwards compatibility.
$GLOBALS['reign_demo_installer'] = reign_demo_installer();
