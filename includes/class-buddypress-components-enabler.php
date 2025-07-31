<?php
/**
 * BuddyPress/BuddyBoss Components Enabler
 *
 * @package WBCOM_Theme_Demo_Installer
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class to handle enabling all BuddyPress/BuddyBoss components
 */
class WBCOM_BuddyPress_Components_Enabler {

	/**
	 * Enable all components based on which platform is active
	 *
	 * @return array|false Array of enabled components or false on failure
	 */
	public static function enable_all_components() {
		// Check if BuddyPress or BuddyBoss is active
		if ( ! function_exists( 'buddypress' ) ) {
			return false;
		}

		// Detect which platform we're using
		$is_buddyboss = self::is_buddyboss_platform();
		
		// Log which platform we're using
		if ( $is_buddyboss ) {
			error_log( 'WBCOM Demo Installer: Enabling components for BuddyBoss Platform' );
		} else {
			error_log( 'WBCOM Demo Installer: Enabling components for BuddyPress' );
		}

		// Get all available components
		$default_components = self::get_default_components();
		$optional_components = self::get_optional_components();
		$required_components = self::get_required_components();

		// Combine all components
		$all_components = array();

		// Add required components
		foreach ( $required_components as $component => $details ) {
			$all_components[ $component ] = 1;
		}

		// Add optional components
		foreach ( $optional_components as $component => $details ) {
			// Skip blogs component if not multisite
			if ( $component === 'blogs' && ! is_multisite() ) {
				continue;
			}
			
			// Skip BuddyBoss-specific components if using standard BuddyPress
			if ( ! $is_buddyboss && in_array( $component, array( 'media', 'document', 'video', 'moderation', 'search' ) ) ) {
				continue;
			}
			
			$all_components[ $component ] = 1;
		}

		// Get current active components to compare
		$current_components = bp_get_option( 'bp-active-components', array() );
		
		// Only update if there are changes
		if ( $all_components != $current_components ) {
			// Save active components
			bp_update_option( 'bp-active-components', $all_components );

			// Install component database tables
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			
			// Load BuddyPress schema functions
			$bp = buddypress();
			if ( file_exists( $bp->plugin_dir . '/bp-core/admin/bp-core-admin-schema.php' ) ) {
				require_once( $bp->plugin_dir . '/bp-core/admin/bp-core-admin-schema.php' );
			}
			
			// Install components
			if ( function_exists( 'bp_core_install' ) ) {
				bp_core_install( $all_components );
			}

			// Add page mappings
			if ( function_exists( 'bp_core_add_page_mappings' ) ) {
				bp_core_add_page_mappings( $all_components );
			}

			// Handle Forums page creation for BuddyBoss
			if ( $is_buddyboss && isset( $all_components['forums'] ) ) {
				self::create_forums_page();
			}

			// Refresh permalinks
			if ( function_exists( 'bp_delete_rewrite_rules' ) ) {
				bp_delete_rewrite_rules();
			}
			flush_rewrite_rules( true );
			
			error_log( 'WBCOM Demo Installer: Enabled ' . count( $all_components ) . ' components' );
		} else {
			error_log( 'WBCOM Demo Installer: All components already enabled' );
		}

		return $all_components;
	}

	/**
	 * Check if BuddyBoss Platform is active
	 *
	 * @return bool
	 */
	private static function is_buddyboss_platform() {
		return defined( 'BP_PLATFORM_VERSION' ) || class_exists( 'BuddyBoss_Platform' );
	}

	/**
	 * Get default components
	 *
	 * @return array
	 */
	private static function get_default_components() {
		if ( function_exists( 'bp_core_admin_get_components' ) ) {
			return bp_core_admin_get_components( 'default' );
		}
		
		// Fallback default components
		return array(
			'xprofile' => array(),
			'settings' => array(),
			'notifications' => array(),
		);
	}

	/**
	 * Get optional components
	 *
	 * @return array
	 */
	private static function get_optional_components() {
		if ( function_exists( 'bp_core_admin_get_components' ) ) {
			return bp_core_admin_get_components( 'optional' );
		}
		
		// Fallback optional components
		$components = array(
			'activity' => array(),
			'groups' => array(),
			'messages' => array(),
			'friends' => array(),
		);
		
		// Add BuddyBoss-specific components if active
		if ( self::is_buddyboss_platform() ) {
			$components['media'] = array();
			$components['document'] = array();
			$components['video'] = array();
			$components['forums'] = array();
			$components['invites'] = array();
			$components['search'] = array();
			$components['moderation'] = array();
		}
		
		// Add blogs for multisite
		if ( is_multisite() ) {
			$components['blogs'] = array();
		}
		
		return $components;
	}

	/**
	 * Get required components
	 *
	 * @return array
	 */
	private static function get_required_components() {
		if ( function_exists( 'bp_core_admin_get_components' ) ) {
			return bp_core_admin_get_components( 'required' );
		}
		
		// Fallback required components
		$components = array(
			'members' => array(),
		);
		
		// For BuddyBoss, xprofile is also required
		if ( self::is_buddyboss_platform() ) {
			$components['xprofile'] = array();
		} else {
			// For standard BuddyPress, core is required
			$components['core'] = array();
		}
		
		return $components;
	}

	/**
	 * Create Forums page for BuddyBoss
	 */
	private static function create_forums_page() {
		$option = bp_get_option( '_bbp_root_slug_custom_slug', '' );
		if ( '' === $option ) {
			$default_titles = bp_core_get_directory_page_default_titles();
			$title = isset( $default_titles['new_forums_page'] ) ? $default_titles['new_forums_page'] : 'Forums';

			$new_page = array(
				'post_title'     => $title,
				'post_status'    => 'publish',
				'post_author'    => get_current_user_id() ?: 1,
				'post_type'      => 'page',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			);

			$page_id = wp_insert_post( $new_page );

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				bp_update_option( '_bbp_root_slug_custom_slug', $page_id );
				$slug = get_page_uri( $page_id );
				bp_update_option( '_bbp_root_slug', urldecode( $slug ) );
			}
		}
	}

	/**
	 * Get list of all available components for current platform
	 *
	 * @return array
	 */
	public static function get_all_components() {
		$is_buddyboss = self::is_buddyboss_platform();
		
		if ( $is_buddyboss ) {
			return array(
				'members' => 1,
				'xprofile' => 1,
				'settings' => 1,
				'notifications' => 1,
				'groups' => 1,
				'forums' => 1,
				'activity' => 1,
				'media' => 1,
				'document' => 1,
				'video' => 1,
				'messages' => 1,
				'friends' => 1,
				'invites' => 1,
				'search' => 1,
				'moderation' => 1,
				'blogs' => is_multisite() ? 1 : 0,
			);
		} else {
			return array(
				'core' => 1,
				'members' => 1,
				'xprofile' => 1,
				'settings' => 1,
				'friends' => 1,
				'messages' => 1,
				'activity' => 1,
				'notifications' => 1,
				'groups' => 1,
				'blogs' => is_multisite() ? 1 : 0,
			);
		}
	}
}