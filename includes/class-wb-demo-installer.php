<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://www.wbcomdesigns.com
 * @since      1.0.0
 *
 * @package    Wb_Demo_Installer
 * @subpackage Wb_Demo_Installer/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wb_Demo_Installer
 * @subpackage Wb_Demo_Installer/includes
 * @author     Wbcom Designs <admin@wbcomdesigns.com>
 */
class Wb_Demo_Installer {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Wb_Demo_Installer_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'WBDI_PLUGIN_VERSION' ) ) {
			$this->version = WBDI_PLUGIN_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'wb-demo-installer';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wb_Demo_Installer_Loader. Orchestrates the hooks of the plugin.
	 * - Wb_Demo_Installer_i18n. Defines internationalization functionality.
	 * - Wb_Demo_Installer_Admin. Defines all hooks for the admin area.
	 * - Wb_Demo_Installer_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wb-demo-installer-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wb-demo-installer-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wb-demo-installer-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wb-demo-installer-public.php';

		$this->loader = new Wb_Demo_Installer_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wb_Demo_Installer_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Wb_Demo_Installer_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Wb_Demo_Installer_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'wbdi_enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'wbdi_enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'wbdi_demo_installer_page' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'wbdi_demo_installer_process_tabs' );
		$this->loader->add_action( 'wp_ajax_wbdi_plugin_activate', $plugin_admin, 'wbdi_plugin_activate' );
		$this->loader->add_action( 'wp_ajax_wbdi_plugin_install', $plugin_admin, 'wbdi_plugin_install' );
		$this->loader->add_action( 'wp_ajax_wbdi_import_demo', $plugin_admin, 'wbdi_import_demo' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Wb_Demo_Installer_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Wb_Demo_Installer_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Check if the plugin is installed or not
	 */
	public static function wbdi_check_if_plugin_is_installed( $plugin_slug ) {
		$installed_plugins = get_plugins();
		$flag = 0;
		foreach( $installed_plugins as $index => $plugin ) {
			if ( preg_match("~\b$plugin_slug\b~",$index) ) {
				$flag = 1;
				break;
			}
		}

		if( $flag == 1 ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if the plugin is installed or not
	 */
	public static function wbdi_check_if_plugin_is_active( $plugin_slug ) {
		$installed_plugins = get_plugins();
		$flag = 0;
		$active_plugins = array();
		foreach( $installed_plugins as $key => $plugin ) {
			if( is_plugin_active( $key ) ) {
				$active_plugins[] = $key;
			}
		}

		if( !empty( $active_plugins ) ) {
			foreach( $active_plugins as $plugin ) {
				if( stripos( $plugin, $plugin_slug ) !== false ) {
					$flag = 1;
					break;
				}
			}
		}

		if( $flag == 1 ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 *
	 */
	public static function wbdi_set_post_thumbnail( $image_url, $post_id ) {
		$upload_dir = wp_upload_dir();
		$image_data = file_get_contents( $image_url );
		$filename = basename( $image_url );
		if( wp_mkdir_p( $upload_dir['path'] ) ) {
			$file = $upload_dir['path'] . '/' . $filename;
		} else {
			$file = $upload_dir['basedir'] . '/' . $filename;
		}

		file_put_contents( $file, $image_data );

		$wp_filetype = wp_check_filetype($filename, null );
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => sanitize_file_name($filename),
			'post_content' => '',
			'post_status' => 'inherit'
		);
		$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		$res1 = wp_update_attachment_metadata( $attach_id, $attach_data );
		$res2 = set_post_thumbnail( $post_id, $attach_id );
		return;
	}
}
