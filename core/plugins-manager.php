<?php
/**
 * Plugin manager for demo installer.
 *
 * @package WBCOM_Theme_Demo_Installer
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Handles plugin installation, activation, and status checks for demo imports.
 *
 * @class WBCOM_Demo_Importer_Plugins_Manager
 * @since 1.0.0
 */
class WBCOM_Demo_Importer_Plugins_Manager {

	/**
	 * The single instance of the class.
	 *
	 * @var WBCOM_Demo_Importer_Plugins_Manager
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Registered plugins array.
	 *
	 * @var array
	 */
	public $plugins = array();

	/**
	 * TGM Plugin Activation instance.
	 *
	 * @var TGM_Plugin_Activation
	 */
	public $tgmpa;

	const WP_REPO_REGEX = '|^http[s]?://wordpress\.org/(?:extend/)?plugins/|';
	const IS_URL_REGEX  = '|^http[s]?://|';


	/**
	 * Main WBCOM_Demo_Importer_Plugins_Manager Instance
	 *
	 * Ensures only one instance of WBCOM_Demo_Importer_Plugins_Manager is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see WBCOM_Demo_Importer_Plugins_Manager()
	 * @return WBCOM_Demo_Importer_Plugins_Manager - Main instance
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

		// Register the plugins in our class.
		add_action( 'init', array( $this, 'populate_plugins' ) );

		// Register Ajax actions.
		add_action( 'wp_ajax_wbcom_manage_plugin_installation', array( $this, 'do_plugin_action' ) );

		add_action( 'tgmpa_register', array( $this, 'required_plugins' ) );
	}

	/**
	 * Register required plugins with TGMPA.
	 */
	public function required_plugins() {
		/*
		 * Array of plugin arrays. Required keys are name and slug.
		 * If the source is NOT from the .org repo, then source is also required.
		 */
		$plugins = array();

		$plugins = ! empty( $this->get_required_plugins() ) ? $this->get_required_plugins() : array();

		/*
		 * Array of configuration settings. Amend each line as needed.
		 *
		 * TGMPA will start providing localized text strings soon. If you already have translations of our standard
		 * strings available, please help us make TGMPA even better by giving us access to these translations or by
		 * sending in a pull-request with .po file(s) with the translations.
		 *
		 * Only uncomment the strings in the config array if you want to customize the strings.
		 */
		$config = array(
			'id'           => 'wbcom',                 // Unique ID for hashing notices for multiple instances of TGMPA.
			'default_path' => '',                      // Default absolute path to bundled plugins.
			'menu'         => 'tgmpa-install-plugins', // Menu slug.
			'parent_slug'  => 'plugins.php',            // Parent menu slug.
			'capability'   => 'manage_options',    // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
			'has_notices'  => true,                    // Show admin notices or not.
			'dismissable'  => true,                    // If false, a user cannot dismiss the nag message.
			'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
			'is_automatic' => false,                   // Automatically Activate Plugins after installation or not.
			'message'      => '',                      // Message to output right before the plugins table.
		);

		tgmpa( $plugins, $config );
	}


	/**
	 * Populate plugins list from required plugins configuration.
	 */
	public function populate_plugins() {

		include_once 'class-tgm-plugin-activation.php';

		$this->tgmpa = TGM_Plugin_Activation::get_instance();

		$this->tgmpa->populate_file_path();

		$get_required_plugins  = $this->get_required_plugins();
		$_get_required_plugins = array();
		if ( ! empty( $get_required_plugins ) && is_array( $get_required_plugins ) ) {
			foreach ( $get_required_plugins as $key => $value ) {
				$_get_required_plugins[ $value['slug'] ] = $value;
			}
		}
		$this->plugins = $_get_required_plugins;
	}

	/**
	 * Hook to determine if TGMPA should load.
	 *
	 * @return bool Whether to load TGMPA.
	 */
	public function tgmpa_load_hook() {
		return is_admin();
	}

	/**
	 * Handle plugin install/activate AJAX actions.
	 */
	public function do_plugin_action() {
		// Security check.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wbcom_demo_installer_nonce' ) ) {
			wp_send_json_error( array( 'error' => __( 'Security check failed', 'wbcom-theme-demo-installer' ) ) );
		}

		// Capability check.
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'error' => __( 'You do not have permission to install plugins', 'wbcom-theme-demo-installer' ) ) );
		}

		$action                = ! empty( $_POST['plugin_action'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_action'] ) ) : false;
		$slug                  = ! empty( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : false;
		$demo                  = ! empty( $_POST['demo'] ) ? sanitize_text_field( wp_unslash( $_POST['demo'] ) ) : false;
		$_get_required_plugins = array();
		$url_to_request        = WBCOM_DEMO_INSTALLER_PACKAGE_PLUGINS_URL . $demo . '/plugins.json';
		$response              = wp_remote_get(
			$url_to_request,
			array(
				'sslverify' => false,
				'timeout'   => 120,
			)
		);

		if ( ! is_wp_error( $response ) ) {
			if ( isset( $response['response']['code'] ) && 200 === (int) $response['response']['code'] ) {
				$response = isset( $response['body'] ) ? $response['body'] : '';
				if ( ! empty( $response ) ) {
					$response = json_decode( $response, true );
				}
				if ( ! empty( $response ) && is_array( $response ) ) {
					$get_required_plugins = $response;
				}
			}
		}

		foreach ( $get_required_plugins as $key => $value ) {
			$_get_required_plugins[ $value['slug'] ] = $value;
		}
		$this->plugins = $_get_required_plugins;

		switch ( $action ) {
			case 'enable_plugin':
				$this->do_plugin_activate( $slug );
				break;
			case 'install_plugin':
				$this->do_plugin_install( $slug );
				break;
			default:
				break;
		}
	}

	/**
	 * Performs the plugin update.
	 *
	 * @param string $slug Plugin slug.
	 */
	public function do_plugin_update( $slug ) {

		$status = $this->get_plugin_status( $slug );

		$active = false;
		if ( $this->is_plugin_active( $slug ) ) {
			$active = true;
		}

		if ( empty( $this->plugins[ $slug ] ) ) {
			$status['error'] = 'We have no data about this plugin.';
			wp_send_json_error( $status );
		}

		if ( $this->does_plugin_have_update( $slug ) ) {

			if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}

			$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
			// Inject our info into the update transient.
			$source                       = $this->get_download_url( $slug );
			$to_inject                    = array( $slug => $this->plugins[ $slug ] );
			$to_inject[ $slug ]['source'] = $source;
			$this->inject_update_info( $to_inject );
			$result = $upgrader->upgrade( $this->plugins[ $slug ]['file_path'] );

			if ( is_wp_error( $result ) ) {
				$status['error'] = $result->get_error_message();
				wp_send_json_error( $status );
			}

			if ( true === $active ) {
				$this->tgmpa->populate_file_path( $slug );
				$result = activate_plugin( $this->plugins[ $slug ]['file_path'] );
				if ( is_wp_error( $result ) ) {
					$status['error'] = wp_kses_post( $result->get_error_message() );
				}
			}

			// Return the status of the plugin.
			$status = $this->get_plugin_status( $slug );
			wp_send_json_success( $status );
		}

		$status['error'] = 'The plugin does not have an update.';
		wp_send_json_error( $status );
	}

	/**
	 * Enable a child theme.
	 *
	 * @param  string $slug The slug used in the addons config file for the child theme.
	 * @return void
	 */
	public function enable_child_theme( $slug ) {

		$status = $this->get_plugin_status( $slug );

		// Get all installed themes.
		$current_installed_themes = wp_get_themes();
		// Get the themes currently installed.
		$active_theme      = wp_get_theme();
		$theme_folder_name = $active_theme->get_template();

		$child_theme = false;

		if ( is_array( $current_installed_themes ) ) {
			foreach ( $current_installed_themes as $key => $theme_obj ) {
				if ( $theme_obj->get( 'Template' ) === $theme_folder_name ) {
					$child_theme = $theme_obj;
				}
			}
		}

		if ( false !== $child_theme ) {
			switch_theme( $child_theme->get_stylesheet() );
			$status = $this->get_plugin_status( $slug );
		}

		wp_send_json_success( $status );
	}

	/**
	 * Install a child theme from its download URL.
	 *
	 * @param string $slug The child theme slug.
	 */
	public function install_child_theme( $slug ) {
		if ( empty( $this->plugins[ $slug ] ) ) {
			wp_send_json_error( array( 'error' => 'We don\'t know anything about this theme' ) );
		}

		$url    = $this->get_download_url( $slug );
		$status = $this->get_plugin_status( $slug );

		if ( ! current_user_can( 'install_themes' ) ) {
			$status['error'] = 'You don\'t have permissions to install install_themes';
			wp_send_json_error( array( 'error' => '' ) );
		}

		if ( ! class_exists( 'Theme_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin, array( 'clear_destination' => true ) );
		$result   = $upgrader->install( $url );

		// There is a bug in WP where the install method can return null in case the folder already exists.
		// see https://core.trac.wordpress.org/ticket/27365.
		if ( null === $result && ! empty( $skin->result ) ) {
			$result = $skin->result;
		}

		if ( is_wp_error( $skin->result ) ) {
			$status['error'] = $result->get_error_message();
			wp_send_json_error( $status );
		}

		$status = $this->get_plugin_status( $slug );
		wp_send_json_success( $status );
	}

	/**
	 * Will check if a child theme is installed for the current theme.
	 *
	 * @return boolean true/false if a child theme is installed or not.
	 */
	public function is_child_theme_installed() {

		// Get all installed themes.
		$current_installed_themes = wp_get_themes();
		// Get the themes currently installed.
		$active_theme      = wp_get_theme();
		$theme_folder_name = $active_theme->get_template();

		if ( is_array( $current_installed_themes ) ) {
			foreach ( $current_installed_themes as $key => $theme_obj ) {
				if ( $theme_obj->get( 'Template' ) === $theme_folder_name ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks if a child theme is active or not.
	 *
	 * @return boolean If the child theme is in use.
	 */
	public function is_child_theme_active() {
		$active_theme = wp_get_theme();
		$template     = $active_theme->get( 'Template' );
		return ! empty( $template );
	}

	/**
	 * Retrieve the addon configuration for a given plugin slug.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return array|void The plugin configuration array or void if not found.
	 */
	public function get_addon_config( $plugin_slug ) {
		if ( ! empty( $this->plugins[ $plugin_slug ] ) ) {
			return $this->plugins[ $plugin_slug ];
		}
	}

	/**
	 * Returns the status and actions for a plugin.
	 *
	 * @param  string $plugin_slug The plugin slug.
	 * @return array  The status and actions for the requested plugin.
	 */
	public function get_plugin_status( $plugin_slug ) {

		$status        = array();
		$plugin_config = $this->get_addon_config( $plugin_slug );

		if ( isset( $plugin_config['addon_type'] ) && 'child_theme' === $plugin_config['addon_type'] ) {
			// We have a theme.
			if ( $this->is_child_theme_installed() ) {
				// Check if the theme is active or not.
				if ( $this->is_child_theme_active() ) {
					$status['status']      = 'wbcom-active wbcom-addons-disabled';
					$status['status_text'] = __( 'Active', 'wbcom-theme-demo-installer' );
					$status['action_text'] = __( 'Child theme installed and active', 'wbcom-theme-demo-installer' );
					$status['action']      = 'no_action';
				} else {
					$status['status']      = 'wbcom-inactive';
					$status['status_text'] = __( 'Inactive', 'wbcom-theme-demo-installer' );
					$status['action_text'] = __( 'Activate child theme', 'wbcom-theme-demo-installer' );
					$status['action']      = 'enable_child_theme';
				}
			} else {
				$status['status']      = 'wbcom-needs-install';
				$status['status_text'] = __( 'Not installed', 'wbcom-theme-demo-installer' );
				$status['action_text'] = __( 'Install child theme', 'wbcom-theme-demo-installer' );
				$status['action']      = 'install_theme';

				if ( ! current_user_can( 'install_themes' ) ) {
					$status['status']      = 'wbcom-not-installed wbcom-addons-disabled';
					$status['action_text'] = __( 'Permissions needed to install child themes. Contact site administrator.', 'wbcom-theme-demo-installer' );
					$status['action']      = 'contact_network_admin';
				}
			}
		} elseif ( $this->is_plugin_installed( $plugin_slug ) ) {
			if ( $this->is_plugin_active( $plugin_slug ) ) {
				$status['status']      = 'wbcom-active';
				$status['status_text'] = __( 'Active', 'wbcom-theme-demo-installer' );
				$status['action_text'] = __( 'Installed', 'wbcom-theme-demo-installer' );
				$status['action']      = 'disable_plugin';
			} else {
				$status['status']      = 'wbcom-inactive';
				$status['status_text'] = __( 'Inactive', 'wbcom-theme-demo-installer' );
				$status['action_text'] = __( 'Activate', 'wbcom-theme-demo-installer' );
				$status['action']      = 'enable_plugin';
			}
		} else {
			$status['status']      = 'wbcom-not-installed';
			$status['status_text'] = __( 'Not Installed', 'wbcom-theme-demo-installer' );
			$status['action_text'] = __( 'Install Now', 'wbcom-theme-demo-installer' );
			$status['action']      = 'install_plugin';

			if ( ! current_user_can( 'install_plugins' ) ) {
				$status['status']      = 'wbcom-not-installed wbcom-addons-disabled';
				$status['action_text'] = __( 'You don\'t have permission to install plugins. Contact site administrator.', 'wbcom-theme-demo-installer' );
				$status['action']      = 'contact_network_admin';
			}
		}

		return $status;
	}

	/**
	 * Inject information into the 'update_plugins' site transient as WP checks that before running an update.
	 *
	 * @since 1.0.0
	 *
	 * @param array $plugins The plugin information for the plugins which are to be updated.
	 */
	public function inject_update_info( $plugins ) {
		$this->tgmpa->inject_update_info( $plugins );
	}

	/**
	 * Performs plugin update.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool Whether the plugin has an update available.
	 */
	public function plugin_has_update( $slug ) {
		if ( empty( $this->plugins[ $slug ] ) ) {
			return false;
		}

		$installed_version = $this->get_installed_version( $slug );
		$minimum_version   = $this->plugins[ $slug ]['version'];

		return version_compare( $minimum_version, $installed_version, '>' );
	}

	/**
	 * Performs plugins installation.
	 *
	 * @param string  $slug Plugin slug.
	 * @param boolean $echo Whether to echo JSON response or return status array.
	 * @return void|array Status array when echo is false, void otherwise.
	 */
	public function do_plugin_install( $slug, $echo = true ) {

		if ( empty( $this->plugins[ $slug ] ) ) {
			return false;
		}

		$url = $this->get_download_url( $slug );

		$status = $this->get_plugin_status( $slug );

		// Unreachable via AJAX (get_wp_repo_download_url dies there); WP-CLI callers get the error back.
		if ( is_wp_error( $url ) ) {
			$status['error'] = $url->get_error_message();
			if ( $echo ) {
				wp_send_json_error( $status );
			} else {
				return $status;
			}
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			$status['error'] = 'You don\'t have permissions to install plugins';
			if ( $echo ) {
				wp_send_json_error( $status );
			} else {
				return $status;
			}
		}

		$method = ''; // Leave blank so WP_Filesystem can populate it as necessary.

		$creds = request_filesystem_credentials( esc_url_raw( $url ), $method, false, false, array() );
		if ( false === $creds ) {
			return true;
		}

		if ( ! WP_Filesystem( $creds ) ) {
			request_filesystem_credentials( esc_url_raw( $url ), $method, true, false, array() ); // Setup WP_Filesystem.
			return true;
		}

		if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin, array( 'clear_destination' => true ) );

		add_filter( 'http_request_args', array( $this, 'increase_redirect_limit' ), 10, 2 );
		$result = $upgrader->install( $url );
		remove_filter( 'http_request_args', array( $this, 'increase_redirect_limit' ), 10 );

		// There is a bug in WP where the install method can return null in case the folder already exists.
		// See https://core.trac.wordpress.org/ticket/27365.
		if ( null === $result && ! empty( $skin->result ) ) {
			$result = $skin->result;
		}

		if ( is_wp_error( $skin->result ) ) {
			$status['error'] = $result->get_error_message();
			if ( $echo ) {
				wp_send_json_error( $status );
			} else {
				return $status;
			}
		}

		$this->tgmpa->populate_file_path( $slug );
		$plugin_activate = $upgrader->plugin_info();
		$activate        = activate_plugin( $plugin_activate );
		if ( is_wp_error( $activate ) ) {
			$status['error'] = wp_kses_post( $activate->get_error_message() );
			if ( $echo ) {
				wp_send_json_error( $status );
			} else {
				return $status;
			}
		}

		$status = $this->get_plugin_status( $slug );

		if ( $echo ) {
			wp_send_json_success( $status );
		} else {
			return $status;
		}
	}

	/**
	 * Performs a plugin deactivation.
	 *
	 * @param string $slug Plugin slug.
	 * @return void
	 */
	public function do_plugin_deactivate( $slug ) {

		$status = $this->get_plugin_status( $slug );

		if ( empty( $this->plugins[ $slug ] ) ) {
			$status['error'] = 'We have no data about this plugin.';
			wp_send_json_error( $status );
		}

		deactivate_plugins( $this->plugins[ $slug ]['file_path'] );

		$status = $this->get_plugin_status( $slug );
		wp_send_json_success( $status );
	}

	/**
	 * Performs plugins activation.
	 *
	 * @param string $slug Plugin slug.
	 * @param bool   $echo Whether to echo JSON response or return status array.
	 * @return void|array Status array when echo is false, void otherwise.
	 */
	public function do_plugin_activate( $slug, $echo = true ) {

		$status = $this->get_plugin_status( $slug );

		if ( empty( $this->plugins[ $slug ] ) ) {
			$status['error'] = 'We have no data about this plugin.';
			if ( $echo ) {
				wp_send_json_error( $status );
			} else {
				return $status;
			}
		}

		$plugin_file_path = $this->_get_plugin_file_path_from_slug( $slug );
		$result           = activate_plugin( $plugin_file_path );

		if ( is_wp_error( $result ) ) {
			$status['error'] = $result->get_error_message();
			if ( $echo ) {
				wp_send_json_error( $status );
			} else {
				return $status;
			}
		}

		$status = $this->get_plugin_status( $slug );
		if ( $echo ) {
			wp_send_json_success( $status );
		} else {
			return $status;
		}
	}

	/**
	 * Get the plugin file path from slug by searching installed plugins.
	 *
	 * @param string $slug Plugin slug.
	 * @return string The plugin file path or the slug if not found.
	 */
	public function _get_plugin_file_path_from_slug( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins_list = get_plugins();
		$keys         = array_keys( $plugins_list );
		foreach ( $keys as $key ) {
			if ( preg_match( '|^' . $slug . '/|', $key ) ) {
				return $key;
			}
		}
		return $slug;
	}

	/**
	 * Returns the install url for the current plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return string The download URL for the plugin.
	 */
	public function get_download_url( $slug ) {
		$dl_source = '';

		// Prefer download_url (direct ZIP) over external_url (may be a webpage).
		if ( ! empty( $this->plugins[ $slug ]['download_url'] ) ) {
			return $this->plugins[ $slug ]['download_url'];
		}

		if ( isset( $this->plugins[ $slug ]['external_url'] ) && ! empty( $this->plugins[ $slug ]['external_url'] ) ) {
			return $this->plugins[ $slug ]['external_url'];
		} else {
			$plugin_source_type = 'repo';
		}

		switch ( $plugin_source_type ) {
			case 'repo':
				return $this->get_wp_repo_download_url( $slug );
			case 'external':
				return $this->plugins[ $slug ]['source'];
			case 'bundled':
				return $this->tgmpa->default_path . $this->plugins[ $slug ]['source'];
		}

		return $dl_source; // Should never happen.
	}

	/**
	 * Determine the plugin source type based on the source string.
	 *
	 * @param string $source The plugin source string.
	 * @return string The source type: 'repo', 'external', or 'bundled'.
	 */
	public function _get_plugin_source_type( $source ) {
		if ( 'repo' === $source || preg_match( self::WP_REPO_REGEX, $source ) ) {
			return 'repo';
		} elseif ( preg_match( self::IS_URL_REGEX, $source ) ) {
			return 'external';
		} else {
			return 'bundled';
		}
	}

	/**
	 * Get the download URL for a plugin from the WordPress.org repository.
	 *
	 * @param string $slug Plugin slug.
	 * @return string|WP_Error The download link from the WordPress.org API, or WP_Error
	 *                         for non-AJAX callers (e.g. WP-CLI) when the API fails.
	 */
	public function get_wp_repo_download_url( $slug ) {
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php'; // For plugins_api.
		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		); // Save on a bit of bandwidth.
		if ( is_wp_error( $api ) ) {
			if ( wp_doing_ajax() ) {
				$status['error'] = $api->get_error_message();
				wp_send_json_error( $status );
			}

			return $api;
		}

		return $api->download_link;
	}


	/**
	 * Check if a plugin is installed. Does not take must-use plugins into account.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Plugin slug.
	 * @return bool True if installed, false otherwise.
	 */
	public function is_plugin_installed( $slug ) {

		return $this->tgmpa->is_plugin_installed( $slug );
	}

	/**
	 * Check whether there is an update available for a plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Plugin slug.
	 * @return false|string Version number string of the available update or false if no update available.
	 */
	public function does_plugin_have_update( $slug ) {
		return $this->tgmpa->does_plugin_have_update( $slug );
	}

	/**
	 * Check if a plugin is active.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Plugin slug.
	 * @return bool True if active, false otherwise.
	 */
	public function is_plugin_active( $slug ) {
		return $this->tgmpa->is_plugin_active( $slug );
	}

	/**
	 * Retrieve the version number of an installed plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Plugin slug.
	 * @return string Version number as string or an empty string if the plugin is not installed
	 *                or version unknown (plugins which don't comply with the plugin header standard).
	 */
	public function get_installed_version( $slug ) {

		return $this->tgmpa->get_installed_version( $slug );
	}

	/**
	 * Wrapper around the core WP get_plugins function, making sure it's actually available.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_folder Optional. Relative path to single plugin folder.
	 * @return array Array of installed plugins with plugin information.
	 */
	public function get_plugins( $plugin_folder = '' ) {
		return $this->tgmpa->get_plugins( $plugin_folder );
	}

	/**
	 * Increase the HTTP redirect limit for plugin downloads.
	 *
	 * @param array  $args HTTP request arguments.
	 * @param string $url  The request URL.
	 * @return array Modified HTTP request arguments.
	 */
	public function increase_redirect_limit( $args, $url ) {
		if ( isset( $args['redirection'] ) && $args['redirection'] < 10 ) {
			$args['redirection'] = 10;
		}
		return $args;
	}

	/**
	 * Retrieve the list of required plugins from a remote JSON configuration.
	 *
	 * @return array|void Array of required plugins or void if conditions are not met.
	 */
	public function get_required_plugins() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['theme_slug'] ) && isset( $_GET['step'] ) && 'plugins_manager' === sanitize_text_field( wp_unslash( $_GET['step'] ) ) ) {

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$plugins_json_key = isset( $_GET['plugins_json_key'] ) ? sanitize_text_field( wp_unslash( $_GET['plugins_json_key'] ) ) : '';
			$url_to_request   = WBCOM_DEMO_INSTALLER_PACKAGE_PLUGINS_URL . $plugins_json_key . '/plugins.json';
			$response         = wp_remote_get(
				$url_to_request,
				array(
					'sslverify' => false,
					'timeout'   => 120,
				)
			);

			if ( ! is_wp_error( $response ) ) {
				if ( isset( $response['response']['code'] ) && 200 === (int) $response['response']['code'] ) {
					$response = isset( $response['body'] ) ? $response['body'] : '';
					if ( ! empty( $response ) ) {
						$response = json_decode( $response, true );
					}
					if ( ! empty( $response ) && is_array( $response ) ) {
						return $response;
					}
				}
			}
		}
	}
}

/**
 * Shortcut to WBCOM_Demo_Importer_Plugins_Manager class
 */
function instantiate_wbcom_demo_importer_plugins_manager() {
	return WBCOM_Demo_Importer_Plugins_Manager::instance();
}
instantiate_wbcom_demo_importer_plugins_manager();
