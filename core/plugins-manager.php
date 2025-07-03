<?php 
/**
 * Independent Plugins Manager for Reign Demo Installer - Enhanced Version
 * No dependency on TGM Plugin Activation
 *
 * @package Reign_Demo_Installer
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Reign_Demo_Installer_Plugins_Manager' ) ) :

/**
 * Reign_Demo_Installer_Plugins_Manager class.
 */
class Reign_Demo_Installer_Plugins_Manager {

	/**
	 * The single instance of the class.
	 *
	 * @var Reign_Demo_Installer_Plugins_Manager
	 */
	protected static $_instance = null;

	/**
	 * Plugins array.
	 *
	 * @var array
	 */
	private $plugins = array();

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
	 * WordPress repository regex.
	 */
	const WP_REPO_REGEX = '|^http[s]?://wordpress\.org/(?:extend/)?plugins/|';
	
	/**
	 * URL regex.
	 */
	const IS_URL_REGEX  = '|^http[s]?://|';

	/**
	 * Main instance.
	 *
	 * @since 3.0.0
	 * @static
	 * @return Reign_Demo_Installer_Plugins_Manager - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Main class constructor.
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
		// Register Ajax actions (new naming)
		add_action( 'wp_ajax_reign_manage_plugin_installation', array( $this, 'do_plugin_action' ) );
		
		// Backward compatibility (old naming)
		add_action( 'wp_ajax_wbcom_manage_plugin_installation', array( $this, 'do_plugin_action' ) );
	}

	/**
	 * Handle plugin actions (install, activate, etc.).
	 */
	public function do_plugin_action() {
		try {
			// Security checks are handled by the Security class pre-hook
			
			$action = $this->security->get_request_param( 'plugin_action', 'string' );
			$slug = $this->security->get_request_param( 'plugin_slug', 'slug' );
			$demo = $this->security->get_request_param( 'demo', 'slug' );

			if ( ! $action || ! $slug || ! $demo ) {
				wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'reign-demo-installer' ) ) );
			}

			// Load plugins configuration
			$this->load_plugins_configuration( $demo );

			// Log the action
			$this->log_plugin_action( $slug, 'attempting_' . $action );

			// Check if this is a pro plugin that's not installed
			if ( $this->is_pro_plugin( $slug ) && ! $this->is_plugin_installed( $slug ) ) {
				$message = sprintf( 
					__( '%s is a premium plugin. Please purchase and upload it manually from the WordPress admin.', 'reign-demo-installer' ), 
					$this->get_plugin_name( $slug )
				);
				$this->log_plugin_action( $slug, 'pro_plugin_not_available', $message );
				wp_send_json_error( array( 'message' => $message ) );
			}

			// Handle plugin action
			$result = $this->handle_plugin_action( $action, $slug );
			
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( $result );

		} catch ( Exception $e ) {
			$this->log_error( 'Plugin action exception: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'An error occurred during plugin operation.', 'reign-demo-installer' ) ) );
		}
	}

	/**
	 * Handle plugin action based on type.
	 *
	 * @param string $action Action type
	 * @param string $slug Plugin slug
	 * @return array|WP_Error
	 */
	private function handle_plugin_action( $action, $slug ) {
		switch ( $action ) {
			case 'enable_plugin':
				return $this->do_plugin_activate( $slug, false );
			
			case 'install_plugin':
				return $this->do_plugin_install( $slug, false );
			
			default:
				return new WP_Error( 'invalid_action', __( 'Invalid action.', 'reign-demo-installer' ) );
		}
	}

	/**
	 * Load plugins configuration for demo.
	 *
	 * @param string $demo Demo slug
	 */
	private function load_plugins_configuration( $demo ) {
		$url_to_request = REIGN_DEMO_INSTALLER_PACKAGE_PLUGINS_URL . $demo . '/plugins.json';
		$response = wp_remote_get( $url_to_request, array( 
			'sslverify' => true, 
			'timeout' => 30,
			'user-agent' => 'Reign Demo Installer/' . REIGN_DEMO_INSTALLER_VERSION
		) );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$plugins_config = json_decode( $body, true );
			
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $plugins_config ) ) {
				$parsed_plugins = array();
				foreach ( $plugins_config as $plugin ) {
					if ( isset( $plugin['slug'] ) ) {
						$parsed_plugins[ $plugin['slug'] ] = $plugin;
					}
				}
				$this->plugins = $parsed_plugins;
				
				$this->log_info( 'Loaded ' . count( $parsed_plugins ) . ' plugins configuration for demo: ' . $demo );
			} else {
				$this->log_warning( 'Invalid plugins configuration JSON for demo: ' . $demo );
			}
		} else {
			$error_msg = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response );
			$this->log_warning( 'Failed to load plugins configuration for demo ' . $demo . ': ' . $error_msg );
		}
	}

	/**
	 * Perform plugin installation.
	 *
	 * @param string $slug Plugin slug
	 * @param boolean $echo Whether to echo JSON response
	 * @return array|WP_Error
	 */
	public function do_plugin_install( $slug, $echo = true ) {
		// Check if this is a pro plugin
		if ( $this->is_pro_plugin( $slug ) ) {
			$error = array( 'error' => sprintf( 
				__( '%s is a premium plugin. Please purchase and upload it manually.', 'reign-demo-installer' ), 
				$this->get_plugin_name( $slug )
			) );
			$this->log_plugin_action( $slug, 'pro_plugin_install_blocked' );
			if ( $echo ) {
				wp_send_json_error( $error );
			}
			return new WP_Error( 'pro_plugin', $error['error'] );
		}

		// Check if already installed and active
		if ( $this->is_plugin_active( $slug ) ) {
			$status = $this->get_plugin_status( $slug );
			$this->log_plugin_action( $slug, 'already_active' );
			if ( $echo ) {
				wp_send_json_success( $status );
			}
			return $status;
		}

		// Check if already installed but not active
		if ( $this->is_plugin_installed( $slug ) ) {
			return $this->do_plugin_activate( $slug, $echo );
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			$error = array( 'error' => __( 'You don\'t have permissions to install plugins', 'reign-demo-installer' ) );
			if ( $echo ) {
				wp_send_json_error( $error );
			}
			return new WP_Error( 'no_permission', $error['error'] );
		}

		// Get download URL
		$download_url = $this->get_download_url( $slug );
		
		if ( empty( $download_url ) ) {
			$error_msg = sprintf( __( 'Download URL not found for plugin %s.', 'reign-demo-installer' ), $slug );
			$this->log_plugin_action( $slug, 'error', 'No download URL found' );
			if ( $echo ) {
				wp_send_json_error( array( 'error' => $error_msg ) );
			}
			return new WP_Error( 'no_download_url', $error_msg );
		}

		// Set up filesystem
		$filesystem_result = $this->setup_filesystem();
		if ( is_wp_error( $filesystem_result ) ) {
			if ( $echo ) {
				wp_send_json_error( array( 'error' => $filesystem_result->get_error_message() ) );
			}
			return $filesystem_result;
		}

		// Include required files
		if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		// Create upgrader
		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		// Install plugin
		$this->log_info( 'Installing plugin from: ' . $download_url );
		$result = $upgrader->install( $download_url );

		if ( is_wp_error( $result ) ) {
			$this->log_plugin_action( $slug, 'install_error', $result->get_error_message() );
			if ( $echo ) {
				wp_send_json_error( array( 'error' => $result->get_error_message() ) );
			}
			return $result;
		}

		if ( is_wp_error( $skin->result ) ) {
			$this->log_plugin_action( $slug, 'install_error', $skin->result->get_error_message() );
			if ( $echo ) {
				wp_send_json_error( array( 'error' => $skin->result->get_error_message() ) );
			}
			return $skin->result;
		}

		// Installation successful, now activate
		$plugin_file = $this->get_plugin_file_path( $slug );
		if ( $plugin_file ) {
			$activate_result = activate_plugin( $plugin_file );
			
			if ( is_wp_error( $activate_result ) ) {
				$this->log_plugin_action( $slug, 'activate_error', $activate_result->get_error_message() );
				if ( $echo ) {
					wp_send_json_error( array( 'error' => $activate_result->get_error_message() ) );
				}
				return $activate_result;
			}
		}

		$status = $this->get_plugin_status( $slug );
		$this->log_plugin_action( $slug, 'installed_and_activated' );

		if ( $echo ) {
			wp_send_json_success( $status );
		}
		return $status;
	}

	/**
	 * Perform plugin activation.
	 *
	 * @param string $slug Plugin slug
	 * @param bool $echo Whether to echo JSON response
	 * @return array|WP_Error
	 */
	public function do_plugin_activate( $slug, $echo = true ) {
		// Check if already active
		if ( $this->is_plugin_active( $slug ) ) {
			$status = $this->get_plugin_status( $slug );
			$this->log_plugin_action( $slug, 'already_active' );
			if ( $echo ) {
				wp_send_json_success( $status );
			}
			return $status;
		}

		if ( ! $this->is_plugin_installed( $slug ) ) {
			$error_msg = sprintf( __( 'Plugin %s is not installed.', 'reign-demo-installer' ), $slug );
			if ( $echo ) {
				wp_send_json_error( array( 'error' => $error_msg ) );
			}
			return new WP_Error( 'not_installed', $error_msg );
		}

		$plugin_file = $this->get_plugin_file_path( $slug );
		
		if ( ! $plugin_file ) {
			$error_msg = sprintf( __( 'Plugin file not found for %s.', 'reign-demo-installer' ), $slug );
			if ( $echo ) {
				wp_send_json_error( array( 'error' => $error_msg ) );
			}
			return new WP_Error( 'file_not_found', $error_msg );
		}

		$result = activate_plugin( $plugin_file );

		if ( is_wp_error( $result ) ) {
			$this->log_plugin_action( $slug, 'activate_error', $result->get_error_message() );
			if ( $echo ) {
				wp_send_json_error( array( 'error' => $result->get_error_message() ) );
			}
			return $result;
		}

		$status = $this->get_plugin_status( $slug );
		$this->log_plugin_action( $slug, 'activated' );

		if ( $echo ) {
			wp_send_json_success( $status );
		}
		return $status;
	}

	/**
	 * Get plugin file path from slug.
	 *
	 * @param string $slug Plugin slug
	 * @return string|false Plugin file path or false if not found
	 */
	public function get_plugin_file_path( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$plugins_list = get_plugins();
		
		// Method 1: Look for folder/file.php pattern
		foreach ( $plugins_list as $plugin_file => $plugin_data ) {
			if ( strpos( $plugin_file, $slug . '/' ) === 0 ) {
				return $plugin_file;
			}
		}
		
		// Method 2: Look for slug.php pattern (single file plugins)
		if ( isset( $plugins_list[ $slug . '.php' ] ) ) {
			return $slug . '.php';
		}
		
		// Method 3: Look in plugin data for matching slug
		foreach ( $plugins_list as $plugin_file => $plugin_data ) {
			$plugin_slug = dirname( $plugin_file );
			if ( $plugin_slug === $slug ) {
				return $plugin_file;
			}
		}
		
		// Method 4: Try to match by plugin name
		foreach ( $plugins_list as $plugin_file => $plugin_data ) {
			$plugin_name_slug = sanitize_title( $plugin_data['Name'] );
			if ( $plugin_name_slug === $slug ) {
				return $plugin_file;
			}
		}
		
		// Method 5: For pro plugins, check common variations silently
		if ( $this->is_pro_plugin( $slug ) ) {
			return $this->get_plugin_file_path_silent( $slug );
		}
		
		$this->log_warning( 'Plugin file path not found for slug: ' . $slug );
		return false;
	}

	/**
	 * Get plugin file path from slug (silent version for pro plugins).
	 *
	 * @param string $slug Plugin slug
	 * @return string|false Plugin file path or false if not found
	 */
	private function get_plugin_file_path_silent( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$plugins_list = get_plugins();
		
		// Pro plugin variations
		$pro_variations = array(
			$slug . '-pro',
			str_replace( '-pro', '', $slug ),
			$slug . '_pro',
			str_replace( '_pro', '', $slug )
		);
		
		foreach ( $pro_variations as $variation ) {
			foreach ( $plugins_list as $plugin_file => $plugin_data ) {
				if ( strpos( $plugin_file, $variation . '/' ) === 0 ) {
					return $plugin_file;
				}
			}
		}
		
		return false;
	}

	/**
	 * Get download URL for plugin.
	 *
	 * @param string $slug Plugin slug
	 * @return string Download URL
	 */
	public function get_download_url( $slug ) {
		// Check if we have plugin configuration
		if ( isset( $this->plugins[ $slug ] ) ) {
			$plugin = $this->plugins[ $slug ];

			// For paid plugins, don't provide download URLs
			if ( isset( $plugin['is_paid'] ) && ( $plugin['is_paid'] === 'yes' || $plugin['is_paid'] === true ) ) {
				return '';
			}

			// Check for external URL (for free plugins with custom hosting)
			if ( isset( $plugin['external_url'] ) && ! empty( $plugin['external_url'] ) ) {
				$external_url = $plugin['external_url'];
				
				// If it's a purchase link (contains pricing keywords), don't use it for download
				$purchase_indicators = array( '/pricing', '/buy', '/purchase', '/downloads/', 'wbcomdesigns.com/downloads' );
				foreach ( $purchase_indicators as $indicator ) {
					if ( strpos( $external_url, $indicator ) !== false ) {
						return '';
					}
				}
				
				// If it's a direct zip file or plugin download, use it
				if ( strpos( $external_url, '.zip' ) !== false || strpos( $external_url, '/plugins/' ) !== false ) {
					return $external_url;
				}
			}

			// Check for direct source
			if ( isset( $plugin['source'] ) && ! empty( $plugin['source'] ) ) {
				$source = $plugin['source'];
				
				if ( $source === 'repo' || preg_match( self::WP_REPO_REGEX, $source ) ) {
					return $this->get_wp_repo_download_url( $slug );
				} elseif ( preg_match( self::IS_URL_REGEX, $source ) ) {
					return $source;
				}
			}
		}

		// Default to WordPress repository for free plugins
		return $this->get_wp_repo_download_url( $slug );
	}

	/**
	 * Get WordPress repository download URL.
	 *
	 * @param string $slug Plugin slug
	 * @return string Download URL
	 */
	private function get_wp_repo_download_url( $slug ) {
		if ( ! function_exists( 'plugins_api' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		$api = plugins_api( 'plugin_information', array(
			'slug'   => $slug,
			'fields' => array( 'sections' => false ),
		) );

		if ( is_wp_error( $api ) ) {
			$this->log_error( 'WordPress API error for ' . $slug . ': ' . $api->get_error_message() );
			return '';
		}

		return isset( $api->download_link ) ? $api->download_link : '';
	}

	/**
	 * Setup WordPress filesystem.
	 *
	 * @return bool|WP_Error True if successful, WP_Error if failed
	 */
	private function setup_filesystem() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$method = '';
		$creds = request_filesystem_credentials( '', $method, false, false, array() );
		
		if ( false === $creds ) {
			return new WP_Error( 'filesystem_error', __( 'Could not access filesystem.', 'reign-demo-installer' ) );
		}

		if ( ! WP_Filesystem( $creds ) ) {
			return new WP_Error( 'filesystem_error', __( 'Could not initialize filesystem.', 'reign-demo-installer' ) );
		}

		return true;
	}

	/**
	 * Check if plugin is installed.
	 *
	 * @param string $slug Plugin slug
	 * @return bool True if installed, false otherwise
	 */
	public function is_plugin_installed( $slug ) {
		$plugin_file = $this->is_pro_plugin( $slug ) ? 
			$this->get_plugin_file_path_silent( $slug ) : 
			$this->get_plugin_file_path( $slug );
			
		return ! empty( $plugin_file );
	}

	/**
	 * Check if plugin is active.
	 *
	 * @param string $slug Plugin slug
	 * @return bool True if active, false otherwise
	 */
	public function is_plugin_active( $slug ) {
		$plugin_file = $this->is_pro_plugin( $slug ) ? 
			$this->get_plugin_file_path_silent( $slug ) : 
			$this->get_plugin_file_path( $slug );
			
		return $plugin_file ? is_plugin_active( $plugin_file ) : false;
	}

	/**
	 * Get plugin status.
	 *
	 * @param string $plugin_slug Plugin slug
	 * @return array Plugin status information
	 */
	public function get_plugin_status( $plugin_slug ) {
		$status = array();
		$is_pro = $this->is_pro_plugin( $plugin_slug );
		$plugin_config = isset( $this->plugins[ $plugin_slug ] ) ? $this->plugins[ $plugin_slug ] : array();
		$external_url = isset( $plugin_config['external_url'] ) ? $plugin_config['external_url'] : '';

		if ( $this->is_plugin_installed( $plugin_slug ) ) {
			if ( $this->is_plugin_active( $plugin_slug ) ) {
				$status['status']      = 'reign-active';
				$status['status_text'] = __( 'Active', 'reign-demo-installer' );
				$status['action_text'] = __( 'Already Installed & Activated', 'reign-demo-installer' );
				$status['action']      = 'no_action';
			} else {
				$status['status']      = 'reign-inactive';
				$status['status_text'] = __( 'Inactive', 'reign-demo-installer' );
				$status['action_text'] = __( 'Activate', 'reign-demo-installer' );
				$status['action']      = 'enable_plugin';
			}
		} else {
			if ( $is_pro ) {
				$status['status']      = 'reign-pro-not-installed';
				$status['status_text'] = __( 'Not Installed (Premium)', 'reign-demo-installer' );
				
				if ( ! empty( $external_url ) ) {
					$status['action_text'] = __( 'Buy Now', 'reign-demo-installer' );
					$status['action']      = 'buy_now';
					$status['external_url'] = $external_url;
				} else {
					$status['action_text'] = __( 'Purchase Required', 'reign-demo-installer' );
					$status['action']      = 'purchase_required';
				}
			} else {
				$status['status']      = 'reign-not-installed';
				$status['status_text'] = __( 'Not Installed', 'reign-demo-installer' );
				$status['action_text'] = __( 'Install Now', 'reign-demo-installer' );
				$status['action']      = 'install_plugin';
			}

			if ( ! current_user_can( 'install_plugins' ) && ! $is_pro ) {
				$status['status']      = 'reign-not-installed reign-addons-disabled';
				$status['action_text'] = __( 'You don\'t have permission to install plugins. Contact site administrator.', 'reign-demo-installer' );
				$status['action']      = 'contact_network_admin';
			}
		}

		return $status;
	}

	/**
	 * Check if plugin is a pro/premium plugin.
	 *
	 * @param string $slug Plugin slug
	 * @return bool True if pro plugin, false otherwise
	 */
	public function is_pro_plugin( $slug ) {
		// Check plugin configuration first
		if ( isset( $this->plugins[ $slug ] ) ) {
			$plugin = $this->plugins[ $slug ];
			
			// Check if marked as paid in config
			if ( isset( $plugin['is_paid'] ) && ( $plugin['is_paid'] === 'yes' || $plugin['is_paid'] === true ) ) {
				return true;
			}
		}

		// Common pro plugin indicators in slug
		$pro_indicators = array( '-pro', '_pro', '-premium', '_premium', '-paid', '_paid' );

		foreach ( $pro_indicators as $indicator ) {
			if ( strpos( $slug, $indicator ) !== false ) {
				return true;
			}
		}

		// Known pro plugins list (fallback)
		$known_pro_plugins = array(
			'dokan-pro', 'elementor-pro', 'woocommerce-memberships', 'woocommerce-subscriptions',
			'buddyboss-platform-pro', 'learndash', 'lifter-lms', 'restrict-content-pro',
			'memberpress', 'ultimate-member-pro', 'wpml-multilingual-cms', 'acf-pro',
			'gravity-forms', 'ninja-forms-pro', 'wp-rocket', 'oxygen', 'divi-builder',
			'reign-dokan-addon'
		);

		return in_array( $slug, $known_pro_plugins, true );
	}

	/**
	 * Get plugin name from slug or configuration.
	 *
	 * @param string $slug Plugin slug
	 * @return string Plugin name
	 */
	public function get_plugin_name( $slug ) {
		// Check configuration first
		if ( isset( $this->plugins[ $slug ] ) && isset( $this->plugins[ $slug ]['name'] ) ) {
			return $this->plugins[ $slug ]['name'];
		}

		// Check installed plugins
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins_list = get_plugins();
		$plugin_file = $this->get_plugin_file_path( $slug );
		
		if ( $plugin_file && isset( $plugins_list[ $plugin_file ]['Name'] ) ) {
			return $plugins_list[ $plugin_file ]['Name'];
		}

		// Fallback: prettify slug
		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	/**
	 * Get installed plugin version.
	 *
	 * @param string $slug Plugin slug
	 * @return string Version number or empty string
	 */
	public function get_installed_version( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins = get_plugins();
		$plugin_file = $this->get_plugin_file_path( $slug );

		if ( $plugin_file && isset( $installed_plugins[ $plugin_file ]['Version'] ) ) {
			return $installed_plugins[ $plugin_file ]['Version'];
		}

		return '';
	}

	/**
	 * Get required plugins configuration.
	 *
	 * @return array Required plugins
	 */
	public function get_required_plugins() {
		// Check if we have cached plugins config
		$cached_plugins = get_option( 'reign_theme_demo_req_plugins', array() );
		if ( ! empty( $cached_plugins ) ) {
			return $cached_plugins;
		}

		return array();
	}

	/**
	 * Log plugin action.
	 *
	 * @param string $plugin_slug Plugin slug
	 * @param string $status Status
	 * @param string $message Optional message
	 */
	private function log_plugin_action( $plugin_slug, $status, $message = '' ) {
		$log_message = "Plugin {$status}: {$plugin_slug}";
		if ( ! empty( $message ) ) {
			$log_message .= " - {$message}";
		}
		
		if ( $status === 'error' || $status === 'failed' ) {
			$this->log_error( $log_message );
		} else {
			$this->log_info( $log_message );
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

	/**
	 * Debug function to list all installed plugins.
	 *
	 * @return array List of all installed plugins
	 */
	public function debug_list_installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$plugins = get_plugins();
		$plugin_list = array();
		
		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$plugin_list[] = array(
				'file' => $plugin_file,
				'name' => $plugin_data['Name'],
				'slug' => dirname( $plugin_file ),
				'active' => is_plugin_active( $plugin_file )
			);
		}
		
		return $plugin_list;
	}
}

endif;

// Backward compatibility - maintain old class name as alias
if ( ! class_exists( 'WBCOM_Demo_Importer_Plugins_Manager' ) ) {
	class_alias( 'Reign_Demo_Installer_Plugins_Manager', 'WBCOM_Demo_Importer_Plugins_Manager' );
}

/**
 * Shortcut to Reign_Demo_Installer_Plugins_Manager class.
 *
 * @return Reign_Demo_Installer_Plugins_Manager
 */
function instantiate_reign_demo_installer_plugins_manager() {
	return Reign_Demo_Installer_Plugins_Manager::instance();
}

/**
 * Backward compatibility function.
 *
 * @return Reign_Demo_Installer_Plugins_Manager
 */
function instantiate_wbcom_demo_importer_plugins_manager() {
	return Reign_Demo_Installer_Plugins_Manager::instance();
}

// Initialize the plugins manager
Reign_Demo_Installer_Plugins_Manager::instance();