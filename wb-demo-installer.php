<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://www.wbcomdesigns.com
 * @since             1.0.0
 * @package           Wb_Demo_Installer
 *
 * @wordpress-plugin
 * Plugin Name:       WB Demo Installer
 * Plugin URI:        http://www.wbcomdesigns.com
 * Description:       This plugin allows the site administrataor to install demo posts per theme.
 * Version:           1.0.0
 * Author:            Wbcom Designs
 * Author URI:        http://www.wbcomdesigns.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wb-demo-installer
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WBDI_PLUGIN_VERSION', '1.0.0' );

if ( ! defined( 'WBDI_PLUGIN_PATH' ) ) {
	define( 'WBDI_PLUGIN_PATH', plugin_dir_path(__FILE__) );
}

if ( ! defined( 'WBDI_TEXT_DOMAIN' ) ) {
	define( 'WBDI_TEXT_DOMAIN', 'wb-demo-installer' );
}

if ( ! defined( 'WBDI_PLUGIN_URL' ) ) {
	define( 'WBDI_PLUGIN_URL', plugin_dir_url(__FILE__) );
}

if ( ! defined( 'WBDI_IS_BP_ACTIVE' ) ) {
	define( 'WBDI_IS_BP_ACTIVE', in_array( 'buddypress/bp-loader.php', get_option( 'active_plugins' ) ) );
}

if ( ! defined( 'WBDI_IS_BBP_ACTIVE' ) ) {
	define( 'WBDI_IS_BBP_ACTIVE', in_array( 'bbpress/bbpress.php', get_option( 'active_plugins' ) ) );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wb-demo-installer-activator.php
 */
function activate_wb_demo_installer() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wb-demo-installer-activator.php';
	Wb_Demo_Installer_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wb-demo-installer-deactivator.php
 */
function deactivate_wb_demo_installer() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wb-demo-installer-deactivator.php';
	Wb_Demo_Installer_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wb_demo_installer' );
register_deactivation_hook( __FILE__, 'deactivate_wb_demo_installer' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wb_demo_installer() {
	/**
	 * The core plugin class that is used to define internationalization,
	 * admin-specific hooks, and public-facing site hooks.
	 */
	require plugin_dir_path( __FILE__ ) . 'includes/class-wb-demo-installer.php';
	$plugin = new Wb_Demo_Installer();
	$plugin->run();
}

/**
 * Actions performed on hook: plugins loaded.
 */
add_action( 'plugins_loaded', 'wbdi_plugin_init' );
function wbdi_plugin_init() {
	run_wb_demo_installer();
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wbdi_plugin_links' );
}

function wbdi_plugin_links( $links ) {
	$wbdi_links = array(
		'<a href="' . admin_url( 'admin.php?page=wb-demo-installer' ) . '">' . __( 'Settings', WBDI_TEXT_DOMAIN ) . '</a>',
		'<a href="https://wbcomdesigns.com/contact/" target="_blank" title="' . __( 'Go for any custom development.', WBDI_TEXT_DOMAIN ) . '">' . __( 'Support', WBDI_TEXT_DOMAIN ) . '</a>'
	);
	return array_merge( $links, $wbdi_links );
}