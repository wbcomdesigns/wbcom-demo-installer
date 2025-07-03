<?php
/**
 * Admin User Guardian - FIXED VERSION with reduced logging
 * 
 * @package Reign_Demo_Installer
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Reign_Demo_Installer_Admin_Guardian' ) ) :

/**
 * Reign_Demo_Installer_Admin_Guardian class.
 */
class Reign_Demo_Installer_Admin_Guardian {

	/**
	 * The single instance of the class.
	 *
	 * @var Reign_Demo_Installer_Admin_Guardian
	 */
	protected static $_instance = null;

	/**
	 * Current admin user backup.
	 *
	 * @var array
	 */
	private $admin_backup = null;

	/**
	 * Import session ID.
	 *
	 * @var string
	 */
	private $import_session_id = null;

	/**
	 * Flag to prevent multiple activations.
	 *
	 * @var bool
	 */
	private $guardian_active = false;

	/**
	 * Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Only initialize for admin users during actual import
		add_action( 'wp_ajax_wbcom_read_theme_demo_package_file', array( $this, 'activate_guardian_for_import' ), 1 );
		add_action( 'wp_ajax_reign_read_theme_demo_package_file', array( $this, 'activate_guardian_for_import' ), 1 );
	}

	/**
	 * Activate guardian ONLY during actual import.
	 */
	public function activate_guardian_for_import() {
		// Only activate once per session
		if ( $this->guardian_active ) {
			return;
		}

		// Only for admin users
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->guardian_active = true;
		$this->activate_guardian();
	}

	/**
	 * Activate guardian protection.
	 */
	private function activate_guardian() {
		// Create import session
		$this->import_session_id = 'reign_import_' . time() . '_' . wp_generate_password( 8, false );
		set_transient( 'reign_demo_import_active', $this->import_session_id, HOUR_IN_SECONDS );

		// Backup current admin immediately
		$this->backup_current_admin_silent();

		// Set up periodic admin verification (reduced frequency)
		$this->schedule_admin_verification();

		// Reduced logging - only log once per session
		if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
			Reign_Demo_Installer_Logger::info( 'Admin guardian activated for import session: ' . $this->import_session_id );
		}
	}

	/**
	 * Backup current admin user silently.
	 */
	private function backup_current_admin_silent() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Don't backup if already exists
		if ( $this->admin_backup !== null ) {
			return true;
		}

		$current_user = wp_get_current_user();
		
		$this->admin_backup = array(
			'ID' => $current_user->ID,
			'user_login' => $current_user->user_login,
			'user_email' => $current_user->user_email,
			'user_pass' => $current_user->user_pass,
			'user_nicename' => $current_user->user_nicename,
			'user_url' => $current_user->user_url,
			'user_registered' => $current_user->user_registered,
			'user_activation_key' => $current_user->user_activation_key,
			'user_status' => $current_user->user_status,
			'display_name' => $current_user->display_name,
			'first_name' => $current_user->first_name,
			'last_name' => $current_user->last_name,
			'nickname' => $current_user->nickname,
			'description' => $current_user->description,
			'roles' => $current_user->roles,
			'capabilities' => $current_user->allcaps,
			'meta' => get_user_meta( $current_user->ID ),
			'backup_time' => time(),
			'session_tokens' => get_user_meta( $current_user->ID, 'session_tokens', true )
		);

		// Store backup in database as option
		update_option( 'reign_admin_backup_' . $current_user->ID, $this->admin_backup, false );

		// Also store in transient for quick access
		set_transient( 'reign_current_admin_backup', $this->admin_backup, DAY_IN_SECONDS );

		// Reduced logging - only log once
		if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
			Reign_Demo_Installer_Logger::info( 'Admin user backup created: ' . $current_user->user_login );
		}
		
		return true;
	}

	/**
	 * Schedule periodic admin verification during import.
	 */
	private function schedule_admin_verification() {
		// Use WordPress cron to verify admin every 2 minutes during import (reduced frequency)
		if ( ! wp_next_scheduled( 'reign_verify_admin_hook' ) ) {
			wp_schedule_event( time(), 'reign_2_minutes', 'reign_verify_admin_hook' );
		}

		add_action( 'reign_verify_admin_hook', array( $this, 'periodic_admin_check' ) );

		// Add custom cron schedule
		add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedule' ) );
	}

	/**
	 * Add custom cron schedule.
	 */
	public function add_custom_cron_schedule( $schedules ) {
		$schedules['reign_2_minutes'] = array(
			'interval' => 120, // 2 minutes instead of 30 seconds
			'display' => __( 'Every 2 minutes', 'reign-demo-installer' )
		);
		
		return $schedules;
	}

	/**
	 * Periodic admin check during import.
	 */
	public function periodic_admin_check() {
		// Only run during active import
		if ( ! get_transient( 'reign_demo_import_active' ) ) {
			// Clear the scheduled event
			wp_clear_scheduled_hook( 'reign_verify_admin_hook' );
			return;
		}

		$backup = get_transient( 'reign_current_admin_backup' );
		
		if ( ! $backup ) {
			return;
		}

		// Check if admin user still exists and has proper permissions
		$admin_user = get_user_by( 'ID', $backup['ID'] );
		
		if ( ! $admin_user || ! user_can( $admin_user, 'manage_options' ) ) {
			// Admin user is missing or downgraded, restore it
			$this->restore_admin_user_silent( $backup['ID'] );
			
			// Log warning only when restoration is needed
			if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
				Reign_Demo_Installer_Logger::warning( 'Admin user automatically restored during import' );
			}
		}
	}

	/**
	 * Restore admin user silently.
	 */
	private function restore_admin_user_silent( $user_id = 0 ) {
		if ( ! $user_id && $this->admin_backup ) {
			$user_id = $this->admin_backup['ID'];
		}

		if ( ! $user_id ) {
			return false;
		}

		// Get backup data
		$backup = $this->admin_backup ?: get_option( 'reign_admin_backup_' . $user_id );
		
		if ( ! $backup ) {
			if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
				Reign_Demo_Installer_Logger::error( 'No admin backup found for user ID: ' . $user_id );
			}
			return false;
		}

		global $wpdb;

		try {
			// Restore user data
			$user_data = array(
				'ID' => $backup['ID'],
				'user_login' => $backup['user_login'],
				'user_email' => $backup['user_email'],
				'user_pass' => $backup['user_pass'],
				'user_nicename' => $backup['user_nicename'],
				'user_url' => $backup['user_url'],
				'user_registered' => $backup['user_registered'],
				'user_activation_key' => $backup['user_activation_key'],
				'user_status' => $backup['user_status'],
				'display_name' => $backup['display_name']
			);

			// Check if user exists
			$existing_user = get_user_by( 'ID', $backup['ID'] );
			
			if ( $existing_user ) {
				// Update existing user
				$result = $wpdb->update( $wpdb->users, $user_data, array( 'ID' => $backup['ID'] ) );
			} else {
				// Insert user if missing
				$result = $wpdb->insert( $wpdb->users, $user_data );
			}

			if ( $result === false ) {
				throw new Exception( 'Failed to update user table' );
			}

			// Restore user meta
			if ( isset( $backup['meta'] ) && is_array( $backup['meta'] ) ) {
				// Clear existing meta
				$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $backup['ID'] ) );
				
				// Restore all meta
				foreach ( $backup['meta'] as $meta_key => $meta_values ) {
					if ( is_array( $meta_values ) ) {
						foreach ( $meta_values as $meta_value ) {
							add_user_meta( $backup['ID'], $meta_key, maybe_unserialize( $meta_value ) );
						}
					}
				}
			}

			// Ensure admin role
			$user = new WP_User( $backup['ID'] );
			$user->set_role( 'administrator' );

			// Restore session tokens if available
			if ( isset( $backup['session_tokens'] ) && ! empty( $backup['session_tokens'] ) ) {
				update_user_meta( $backup['ID'], 'session_tokens', $backup['session_tokens'] );
			}

			// Grant super admin if multisite
			if ( is_multisite() ) {
				grant_super_admin( $backup['ID'] );
			}

			// Clear user cache
			clean_user_cache( $backup['ID'] );

			// Set authentication cookie
			wp_set_auth_cookie( $backup['ID'], true );

			// Log success only when needed
			if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
				Reign_Demo_Installer_Logger::info( 'Admin user restored successfully: ' . $backup['user_login'] );
			}
			
			return true;

		} catch ( Exception $e ) {
			if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
				Reign_Demo_Installer_Logger::error( 'Failed to restore admin user: ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Check admin restoration on login.
	 */
	public function check_admin_restoration( $user_login, $user ) {
		// Check if we have a backup for this user
		$backup = get_option( 'reign_admin_backup_' . $user->ID );
		
		if ( $backup && ! user_can( $user, 'manage_options' ) ) {
			// User lost admin permissions, restore them
			$user->set_role( 'administrator' );
			
			if ( is_multisite() ) {
				grant_super_admin( $user->ID );
			}
			
			// Log only when restoration happens
			if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
				Reign_Demo_Installer_Logger::info( 'Admin permissions restored on login for: ' . $user_login );
			}
		}
	}

	/**
	 * Emergency admin check on admin_init.
	 */
	public function emergency_admin_check() {
		// Only run if we recently had an import and are in admin
		if ( ! is_admin() ) {
			return;
		}

		$recent_backup = get_transient( 'reign_current_admin_backup' );
		
		if ( ! $recent_backup ) {
			return;
		}

		// Check if current user is the backed up admin
		$current_user = wp_get_current_user();
		
		if ( $current_user->ID == $recent_backup['ID'] && ! current_user_can( 'manage_options' ) ) {
			// Admin lost permissions, restore them
			$current_user->set_role( 'administrator' );
			
			if ( is_multisite() ) {
				grant_super_admin( $current_user->ID );
			}
			
			// Refresh current user object
			wp_set_current_user( $current_user->ID );
			
			// Log only when restoration happens
			if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
				Reign_Demo_Installer_Logger::info( 'Emergency admin restoration completed' );
			}
			
			// Show admin notice
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>';
				echo esc_html__( 'Your admin permissions have been automatically restored after demo import.', 'reign-demo-installer' );
				echo '</p></div>';
			} );
		}
	}

	/**
	 * Clean up after import completion.
	 */
	public function cleanup_after_import() {
		// Clear import session
		delete_transient( 'reign_demo_import_active' );
		delete_transient( 'reign_current_admin_backup' );
		
		// Clear scheduled events
		wp_clear_scheduled_hook( 'reign_verify_admin_hook' );
		
		// Reset guardian state
		$this->guardian_active = false;
		
		// Log cleanup only once
		if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
			Reign_Demo_Installer_Logger::info( 'Admin guardian cleanup completed' );
		}
	}

	/**
	 * Get admin backup status.
	 */
	public function get_backup_status() {
		$backup = get_transient( 'reign_current_admin_backup' );
		
		return array(
			'has_backup' => ! empty( $backup ),
			'backup_time' => $backup ? $backup['backup_time'] : null,
			'user_login' => $backup ? $backup['user_login'] : null,
			'import_active' => ! empty( get_transient( 'reign_demo_import_active' ) )
		);
	}

	/**
	 * Emergency restore function (can be called manually).
	 */
	public function emergency_restore( $user_id = 0 ) {
		if ( ! $user_id ) {
			$backup = get_transient( 'reign_current_admin_backup' );
			$user_id = $backup ? $backup['ID'] : 0;
		}

		if ( ! $user_id ) {
			return false;
		}

		return $this->restore_admin_user_silent( $user_id );
	}
}

endif;

/**
 * Initialize the Admin Guardian only when needed.
 */
function reign_demo_installer_admin_guardian() {
	return Reign_Demo_Installer_Admin_Guardian::instance();
}

// Initialize only when needed
add_action( 'admin_init', 'reign_demo_installer_admin_guardian', 5 );