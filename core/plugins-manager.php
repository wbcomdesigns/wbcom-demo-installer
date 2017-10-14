<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WBCOM_Demo_Importer_Plugins_Manager' ) ) :

/**
 * @class WBCOM_Demo_Importer_Plugins_Manager
 * @version	1.0.0
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
	 * Main WBCOM_Demo_Importer_Plugins_Manager Instance.
	 *
	 * Ensures only one instance of WBCOM_Demo_Importer_Plugins_Manager is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see WBCOM_Demo_Importer_Plugins_Manager()
	 * @return WBCOM_Demo_Importer_Plugins_Manager - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	
	/**
	 * WBCOM_Demo_Importer_Plugins_Manager Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Hook into actions and filters.
	 * @since  1.0.0
	 */
	private function init_hooks() {
		add_action( 'tgmpa_register', array( $this, 'register_required_plugins' ) );
	}

	public function register_required_plugins() {
		/*
		 * Array of plugin arrays. Required keys are name and slug.
		 * If the source is NOT from the .org repo, then source is also required.
		 */
		$plugins = array();

		$plugins = $this->check_for_required_plugins( $plugins );


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
			'id'           => 'asdfgh',                 // Unique ID for hashing notices for multiple instances of TGMPA.
			'default_path' => '',                      // Default absolute path to bundled plugins.
			'menu'         => 'tgmpa-install-plugins', // Menu slug.
			'parent_slug'  => 'plugins.php',            // Parent menu slug.
			'capability'   => 'manage_options',    // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
			'has_notices'  => true,                    // Show admin notices or not.
			'dismissable'  => true,                    // If false, a user cannot dismiss the nag message.
			'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
			'is_automatic' => false,                   // Automatically activate plugins after installation or not.
			'message'      => '',                      // Message to output right before the plugins table.
		);

		tgmpa( $plugins, $config );
	}

	public function check_for_required_plugins( $plugins = array() ) {
		if( isset( $_GET['theme_slug'] ) && isset( $_GET['demo_slug'] ) ) {
			$url_to_request = WBCOM_Theme_Demo_Installer_URL_TO_REQUEST;
			$response = wp_remote_post( $url_to_request, array(
				'method' => 'POST',
				'timeout' => 45,
				'headers' => array(),
				'body' => array(
					'theme_slug' => $_GET['theme_slug'],
					'demo_slug' => $_GET['demo_slug'],
				)
			) );
			if ( !is_wp_error( $response ) ) {
				if ( isset( $response['response']['code'] ) &&  ( $response['response']['code'] == 200 ) ) {
					$response = isset( $response['body'] ) ? $response['body'] : '';
					if( !empty( $response ) ) {
						$response = json_decode( $response, true );
						if( isset( $response['plugins'] ) && is_array( $response['plugins'] ) ) {
							$_plugins_required_by_demo = $response['plugins'];
							foreach ( $_plugins_required_by_demo as $plugin ) {
								$required_plugin = array(
									'name'      => $plugin['name'],
									'slug'      => $plugin['slug'],
									'required'  => true,
								);
								array_push( $plugins, $required_plugin );
							}
						}
					}
				}
			}
			$wbcom_theme_demo_import_data = get_option( 'wbcom_theme_demo_import_data', array() );
			$wbcom_theme_demo_import_data['plugins'] = $plugins;
			$wbcom_theme_demo_import_data['theme_slug'] = $_GET['theme_slug'];
			$wbcom_theme_demo_import_data['demo_slug'] = $_GET['demo_slug'];
			update_option( 'wbcom_theme_demo_import_data', $wbcom_theme_demo_import_data );
		}
		else {
			$wbcom_theme_demo_import_data = get_option( 'wbcom_theme_demo_import_data', array() );
			$required_plugin = array();
			if( isset( $wbcom_theme_demo_import_data['plugins'] ) && is_array( $wbcom_theme_demo_import_data['plugins'] )) {
				$required_plugin = $wbcom_theme_demo_import_data['plugins'];
			}
			$plugins = array_merge( $plugins, $required_plugin );
		}
		return $plugins;
	}

}

endif;

/**
 * Main instance of WBCOM_Demo_Importer_Plugins_Manager.
 * @since  1.0.0
 * @return WBCOM_Demo_Importer_Plugins_Manager
 */
WBCOM_Demo_Importer_Plugins_Manager::instance();
?>