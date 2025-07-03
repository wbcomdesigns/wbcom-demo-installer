<?php 
/**
 * Plugins manager for Reign Demo Installer
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
	 * @since 3.0.0
	 */
	protected static $_instance = null;

	/**
	 * Plugins array.
	 *
	 * @var array
	 */
	var $plugins = array();

	/**
	 * TGM_Plugin_Activation Instance.
	 *
	 * @var TGM_Plugin_Activation
	 */
	var $tgmpa;

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
	function __construct() {
		// Register the plugins in our class
		add_action( 'init', array( $this, 'populate_plugins' ) );

		// Register Ajax actions (new naming)
		add_action( 'wp_ajax_reign_manage_plugin_installation', array( $this, 'do_plugin_action' ) );
		
		// Backward compatibility (old naming)
		add_action( 'wp_ajax_wbcom_manage_plugin_installation', array( $this, 'do_plugin_action' ) );

		add_action( 'tgmpa_register', array( $this, 'required_plugins' ) );
	}

	/**
	 * Register required plugins with TGMPA.
	 */
	public function required_plugins() {
		$plugins = ! empty( $this->get_required_plugins() ) ? $this->get_required_plugins() : array();

		$config = array(
			'id'           => 'reign',
			'default_path' => '',
			'menu'         => 'tgmpa-install-plugins',
			'parent_slug'  => 'plugins.php',
			'capability'   => 'manage_options',
			'has_notices'  => true,
			'dismissable'  => true,
			'dismiss_msg'  => '',
			'is_automatic' => false,
			'message'      => '',
		);

		if ( function_exists( 'tgmpa' ) ) {
			tgmpa( $plugins, $config );
		}
	}

	/**
	 * Populate plugins array.
	 */
	public function populate_plugins() {
		include_once 'class-tgm-plugin-activation.php';

		if ( class_exists( 'TGM_Plugin_Activation' ) ) {
			$this->tgmpa = TGM_Plugin_Activation::get_instance();
			$this->tgmpa->populate_file_path();
		}

		$get_required_plugins = $this->get_required_plugins();
		$_get_required_plugins = array();
		
		if ( ! empty( $get_required_plugins ) && is_array( $get_required_plugins ) ) {
			foreach ( $get_required_plugins as $plugin ) {
				if ( isset( $plugin['slug'] ) ) {
					$_get_required_plugins[ $plugin['slug'] ] = $plugin;
				}
			}
		}
		
		$this->plugins = $_get_required_plugins;
	}

	/**
	 * Handle plugin actions (install, activate, etc.).
	 */
	public function do_plugin_action() {
		// Security check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'reign-demo-installer' ) ) );
		}

		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'reign_demo_installer_ajax' ) && 
			 ! wp_verify_nonce( $nonce, 'reign_demo_installer_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'reign-demo-installer' ) ) );
		}

		$action = ! empty( $_POST['plugin_action'] ) ? sanitize_text_field( $_POST['plugin_action'] ) : false;
		$slug = ! empty( $_POST['plugin_slug'] ) ? sanitize_key( $_POST['plugin_slug'] ) : false;
		$demo = ! empty( $_POST['demo'] ) ? sanitize_key( $_POST['demo'] ) : false;

		if ( ! $action || ! $slug || ! $demo ) {
			wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'reign-demo-installer' ) ) );
		}

		// Load plugins configuration
		$this->load_plugins_configuration( $demo );

		// Log the action
		Reign_Demo_Installer_Logger::log_plugin_action( $slug, 'attempting_' . $action );

		try {
			switch ( $action ) {
				case 'enable_plugin':
					$this->do_plugin_activate( $slug );
					break;
				case 'install_plugin':
					$this->do_plugin_install( $slug );
					break;
				default:
					wp_send_json_error( array( 'message' => __( 'Invalid action.', 'reign-demo-installer' ) ) );
			}
		} catch ( Exception $e ) {
			Reign_Demo_Installer_Logger::error( 'Plugin action exception: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'An error occurred during plugin operation.', 'reign-demo-installer' ) ) );
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
				$_get_required_plugins = array();
				foreach ( $plugins_config as $plugin ) {
					if ( isset( $plugin['slug'] ) ) {
						$_get_required_plugins[ $plugin['slug'] ] = $plugin;
					}
				}
				$this->plugins = $_get_required_plugins;
			}
		}
	}

	/**
	 * Perform plugin installation.
	 *
	 * @param string $slug Plugin slug
	 * @param boolean $echo Whether to echo JSON response
	 * @return void|array
	 */
	function do_plugin_install( $slug, $echo = true ) {
		if ( empty( $this->plugins[ $slug ] ) ) {
			$error = array( 'error' => __( 'Plugin configuration not found.', 'reign-demo-installer' ) );
			if ( $echo ) {
				wp_send_json_error( $error );
			}
			return $error;
		}

		$url = $this->get_download_url( $slug );
		$status = $this->get_plugin_status( $slug );

		if ( ! current_user_can( 'install_plugins' ) ) {
			$error = array( 'error' => __( 'You don\'t have permissions to install plugins', 'reign-demo-installer' ) );
			if ( $echo ) {
				wp_send_json_error( $error );
			}
			return $error;
		}

		// Check if already installed
		if ( $this->is_plugin_installed( $slug ) ) {
			$status = $this->get_plugin_status( $slug );
			if ( $echo ) {
				wp_send_json_success( $status );
			}
			return $status;
		}

		// Set up filesystem
		if ( ! $this->setup_filesystem() ) {
			$error = array( 'error' => __( 'Could not access filesystem.', 'reign-demo-installer' ) );
			if ( $echo ) {
				wp_send_json_error( $error );
			}
			return $error;
		}

		if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		// Install plugin
		$result = $upgrader->install( $url );

		if ( is_wp_error( $result ) ) {
			$error = array( 'error' => $result->get_error_message() );
			Reign_Demo_Installer_Logger::log_plugin_action( $slug, 'install_error', $result->get_error_message() );
			if ( $echo ) {
				wp_send_json_error( $error );
			}
			return $error;
		}

		if ( is_wp_error( $skin->result ) ) {
			$error = array( 'error' => $skin->result->get_error_message() );
			Reign_Demo_Installer_Logger::log_plugin_action( $slug, 'install_error', $skin->result->get_error_message() );
			if ( $echo ) {
				wp_send_json_error( $error );
			}
			return $error;
		}

		// Populate file path after installation
		if ( $this->tgmpa ) {
			$this->tgmpa->populate_file_path( $slug );
		}

		// Activate the plugin
		$plugin_file = $this->_get_plugin_file_path_from_slug( $slug );
		$activate = activate_plugin( $plugin_file );
		
		if ( is_wp_error( $activate ) ) {
			$error = array( 'error' => $activate->get_error_message() );
			Reign_Demo_Installer_Logger::log_plugin_action( $slug, 'activate_error', $activate->get_error_message() );
			if ( $echo ) {
				wp_send_json_error( $error );
			}
			return $error;
		}

		$status = $this->get_plugin_status( $slug );
		Reign_Demo_Installer_Logger::log_plugin_action( $slug, 'installed_and_activated' );

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
	 * @return void|array
	 */
	function do_plugin_activate( $slug, $echo = true ) {
		$status = $this->get_plugin_status( $slug );

		if ( empty( $this->plugins[ $slug ] ) ) {
			$error = array( 'error' => __( 'Plugin configuration not found.', 'reign-demo-installer' ) );
			if ( $echo ) {
				wp_send_json_error( $error );
			}
			return $error;
		}

		if ( ! $this->is_plugin_installed( $slug ) ) {
			$error = array( 'error' => __( 'Plugin is not installed.', 'reign-demo-installer' ) );
			if ( $echo ) {
				wp_send_json_error( $error );
			}
			return $error;
		}

		$plugin_file_path = $this->_get_plugin_file_path_from_slug( $slug );
		$result = activate_plugin( $plugin_file_path );

		if ( is_wp_error( $result ) ) {
			$error = array( 'error' => $result->get_error_message() );
			Reign_Demo_Installer_Logger::log_plugin_action( $slug, 'activate_error', $result->get_error_message() );
			if ( $echo ) {
				wp_send_json_error( $error );
			}
			return $error;
		}

		$status = $this->get_plugin_status( $slug );
		Reign_Demo_Installer_Logger::log_plugin_action( $slug, 'activated' );

		if ( $echo ) {
			wp_send_json_success( $status );
		}
		return $status;
	}

	/**
	 * Get plugin file path from slug.
	 *
	 * @param string $slug Plugin slug
	 * @return string Plugin file path
	 */
	function _get_plugin_file_path_from_slug( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$plugins_list = get_plugins();
		$keys = array_keys( $plugins_list );
		
		foreach ( $keys as $key ) {
			if ( preg_match( '|^' . $slug . '/|', $key ) ) {
				return $key;
			}
		}
		
		return $slug;
	}

	/**
	 * Get download URL for plugin.
	 *
	 * @param string $slug Plugin slug
	 * @return string Download URL
	 */
	public function get_download_url( $slug ) {
		if ( ! isset( $this->plugins[ $slug ] ) ) {
			return '';
		}

		$plugin = $this->plugins[ $slug ];

		// Check for external URL first
		if ( isset( $plugin['external_url'] ) && ! empty( $plugin['external_url'] ) ) {
			return $plugin['external_url'];
		}

		// Check for direct source
		if ( isset( $plugin['source'] ) && ! empty( $plugin['source'] ) ) {
			$plugin_source_type = $this->_get_plugin_source_type( $plugin['source'] );
			
			switch ( $plugin_source_type ) {
				case 'repo':
					return $this->get_wp_repo_download_url( $slug );
				case 'external':
					return $plugin['source'];
				case 'bundled':
					return $this->tgmpa->default_path . $plugin['source'];
			}
		}

		// Default to WordPress repository
		return $this->get_wp_repo_download_url( $slug );
	}

	/**
	 * Determine plugin source type.
	 *
	 * @param string $source Plugin source
	 * @return string Source type
	 */
	function _get_plugin_source_type( $source ) {
		if ( 'repo' === $source || preg_match( self::WP_REPO_REGEX, $source ) ) {
			return 'repo';
		} elseif ( preg_match( self::IS_URL_REGEX, $source ) ) {
			return 'external';
		} else {
			return 'bundled';
		}
	}

	/**
	 * Get WordPress repository download URL.
	 *
	 * @param string $slug Plugin slug
	 * @return string Download URL
	 */
	function get_wp_repo_download_url( $slug ) {
		if ( ! function_exists( 'plugins_api' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		$api = plugins_api( 'plugin_information', array(
			'slug'   => $slug,
			'fields' => array( 'sections' => false ),
		) );

		if ( is_wp_error( $api ) ) {
			Reign_Demo_Installer_Logger::error( 'WordPress API error for ' . $slug . ': ' . $api->get_error_message() );
			return '';
		}

		return isset( $api->download_link ) ? $api->download_link : '';
	}

	/**
	 * Setup WordPress filesystem.
	 *
	 * @return bool True if successful, false otherwise
	 */
	private function setup_filesystem() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$method = '';
		$creds = request_filesystem_credentials( '', $method, false, false, array() );
		
		if ( false === $creds ) {
			return false;
		}

		return WP_Filesystem( $creds );
	}

	/**
	 * Check if plugin is installed.
	 *
	 * @param string $slug Plugin slug
	 * @return bool True if installed, false otherwise
	 */
	public function is_plugin_installed( $slug ) {
		if ( $this->tgmpa ) {
			return $this->tgmpa->is_plugin_installed( $slug );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins = get_plugins();
		$plugin_file = $this->_get_plugin_file_path_from_slug( $slug );
		
		return isset( $installed_plugins[ $plugin_file ] );
	}

	/**
	 * Check if plugin is active.
	 *
	 * @param string $slug Plugin slug
	 * @return bool True if active, false otherwise
	 */
	public function is_plugin_active( $slug ) {
		if ( $this->tgmpa ) {
			return $this->tgmpa->is_plugin_active( $slug );
		}

		$plugin_file = $this->_get_plugin_file_path_from_slug( $slug );
		return is_plugin_active( $plugin_file );
	}

	/**
	 * Get plugin status.
	 *
	 * @param string $plugin_slug Plugin slug
	 * @return array Plugin status information
	 */
	function get_plugin_status( $plugin_slug ) {
		$status = array();

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
			$status['status']      = 'reign-not-installed';
			$status['status_text'] = __( 'Not Installed', 'reign-demo-installer' );
			$status['action_text'] = __( 'Install Now', 'reign-demo-installer' );
			$status['action']      = 'install_plugin';

			if ( ! current_user_can( 'install_plugins' ) ) {
				$status['status']      = 'reign-not-installed reign-addons-disabled';
				$status['action_text'] = __( 'You don\'t have permission to install plugins. Contact site administrator.', 'reign-demo-installer' );
				$status['action']      = 'contact_network_admin';
			}
		}

		return $status;
	}

	/**
	 * Get installed plugin version.
	 *
	 * @param string $slug Plugin slug
	 * @return string Version number or empty string
	 */
	public function get_installed_version( $slug ) {
		if ( $this->tgmpa ) {
			return $this->tgmpa->get_installed_version( $slug );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins = get_plugins();
		$plugin_file = $this->_get_plugin_file_path_from_slug( $slug );

		if ( isset( $installed_plugins[ $plugin_file ]['Version'] ) ) {
			return $installed_plugins[ $plugin_file ]['Version'];
		}

		return '';
	}

	/**
	 * Get list of plugins.
	 *
	 * @param string $plugin_folder Plugin folder
	 * @return array Array of installed plugins
	 */
	public function get_plugins( $plugin_folder = '' ) {
		if ( $this->tgmpa ) {
			return $this->tgmpa->get_plugins( $plugin_folder );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugins( $plugin_folder );
	}

	/**
	 * Get required plugins configuration.
	 *
	 * @return array Required plugins
	 */
	public function get_required_plugins() {
		// Check if we're on the plugins manager step
		if ( isset( $_GET['theme_slug'] ) && isset( $_GET['step'] ) && 'plugins_manager' === $_GET['step'] ) {
			$plugins_json_key = isset( $_GET['plugins_json_key'] ) ? sanitize_key( $_GET['plugins_json_key'] ) : '';
			
			if ( ! empty( $plugins_json_key ) ) {
				return $this->fetch_plugins_config( $plugins_json_key );
			}
		}

		// Check if we have cached plugins config
		$cached_plugins = get_option( 'reign_theme_demo_req_plugins', array() );
		if ( ! empty( $cached_plugins ) ) {
			return $cached_plugins;
		}

		return array();
	}

	/**
	 * Fetch plugins configuration from remote source.
	 *
	 * @param string $plugins_json_key Plugins JSON key
	 * @return array Plugins configuration
	 */
	private function fetch_plugins_config( $plugins_json_key ) {
		$url_to_request = REIGN_DEMO_INSTALLER_PACKAGE_PLUGINS_URL . $plugins_json_key . '/plugins.json';
		$response = wp_remote_get( $url_to_request, array( 
			'sslverify' => true, 
			'timeout' => 30,
			'user-agent' => 'Reign Demo Installer/' . REIGN_DEMO_INSTALLER_VERSION
		) );

		if ( is_wp_error( $response ) ) {
			Reign_Demo_Installer_Logger::error( 'Failed to fetch plugins config: ' . $response->get_error_message() );
			return array();
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$plugins = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Reign_Demo_Installer_Logger::error( 'Invalid JSON in plugins config: ' . json_last_error_msg() );
			return array();
		}

		return is_array( $plugins ) ? $plugins : array();
	}

	/**
	 * Check if plugin has update available.
	 *
	 * @param string $slug Plugin slug
	 * @return false|string Version number or false
	 */
	public function does_plugin_have_update( $slug ) {
		if ( $this->tgmpa ) {
			return $this->tgmpa->does_plugin_have_update( $slug );
		}

		return false;
	}

	/**
	 * Check if plugin requires update.
	 *
	 * @param string $slug Plugin slug
	 * @return bool True if update required, false otherwise
	 */
	public function plugin_has_update( $slug ) {
		if ( empty( $this->plugins[ $slug ] ) ) {
			return false;
		}

		$installed_version = $this->get_installed_version( $slug );
		$minimum_version = isset( $this->plugins[ $slug ]['version'] ) ? $this->plugins[ $slug ]['version'] : '';

		if ( empty( $minimum_version ) || empty( $installed_version ) ) {
			return false;
		}

		return version_compare( $minimum_version, $installed_version, '>' );
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
instantiate_reign_demo_installer_plugins_manager();