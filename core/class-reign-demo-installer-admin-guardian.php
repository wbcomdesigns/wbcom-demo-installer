<?php
/**
 * Admin User Guardian - Extra safety layer to preserve admin users during demo import
 * 
 * This class provides an additional safety mechanism to ensure the current admin user
 * is never lost during the demo import process.
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
	 * Main instance.
	 *
	 * @return Reign_Demo_Installer_Admin_Guardian
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
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize guardian.
	 */
	public function init() {
		// Only activate during demo import
		if ( $this->is_demo_import_in_progress() ) {
			$this->activate_guardian();
		}

		// Handle AJAX requests
		add_action( 'wp_ajax_reign_guardian_backup_admin', array( $this, 'backup_current_admin' ) );
		add_action( 'wp_ajax_reign_guardian_restore_admin', array( $this, 'restore_admin_user' ) );
		add_action( 'wp_ajax_reign_guardian_verify_session', array( $this, 'verify_admin_session' ) );

		// Hook into user deletion to prevent admin removal
		add_action( 'delete_user', array( $this, 'prevent_admin_deletion' ), 1, 3 );

		// Hook into user meta updates to preserve admin capabilities
		add_action( 'update_user_meta', array( $this, 'preserve_admin_capabilities' ), 1, 4 );

		// Emergency recovery hooks
		add_action( 'wp_login', array( $this, 'check_admin_restoration' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'emergency_admin_check' ) );
	}

	/**
	 * Check if demo import is in progress.
	 *
	 * @return bool
	 */
	private function is_demo_import_in_progress() {
		// Check for import-related AJAX actions
		$import_actions = array(
			'wbcom_read_theme_demo_package_file',
			'wbcom_get_theme_demo_data',
			'reign_read_theme_demo_package_file',
			'reign_get_theme_demo_data'
		);

		$current_action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
		
		return in_array( $current_action, $import_actions, true ) || 
			   isset( $_POST['import_session_id'] ) ||
			   get_transient( 'reign_demo_import_active' );
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

		// Set up periodic admin verification
		$this->schedule_admin_verification();

		Reign_Demo_Installer_Logger::info( 'Admin guardian activated for import session: ' . $this->import_session_id );
	}

	/**
	 * Backup current admin user (AJAX handler).
	 */
	public function backup_current_admin() {
		// Verify request
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'reign_guardian_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		$backup_result = $this->backup_current_admin_silent();
		
		if ( $backup_result ) {
			wp_send_json_success( 'Admin user backed up successfully' );
		} else {
			wp_send_json_error( 'Failed to backup admin user' );
		}
	}

	/**
	 * Backup current admin user silently.
	 *
	 * @return bool Success status
	 */
	private function backup_current_admin_silent() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return false;
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

		Reign_Demo_Installer_Logger::info( 'Admin user backup created: ' . $current_user->user_login );
		
		return true;
	}

	/**
	 * Restore admin user (AJAX handler).
	 */
	public function restore_admin_user() {
		// Verify request
		if ( ! wp_verify_nonce( $_POST['nonce'], 'reign_guardian_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		
		if ( ! $user_id ) {
			wp_send_json_error( 'Invalid user ID' );
		}

		$restore_result = $this->restore_admin_user_silent( $user_id );
		
		if ( $restore_result ) {
			wp_send_json_success( 'Admin user restored successfully' );
		} else {
			wp_send_json_error( 'Failed to restore admin user' );
		}
	}

	/**
	 * Restore admin user silently.
	 *
	 * @param int $user_id User ID to restore
	 * @return bool Success status
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
			Reign_Demo_Installer_Logger::error( 'No admin backup found for user ID: ' . $user_id );
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

			Reign_Demo_Installer_Logger::info( 'Admin user restored successfully: ' . $backup['user_login'] );
			
			return true;

		} catch ( Exception $e ) {
			Reign_Demo_Installer_Logger::error( 'Failed to restore admin user: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Verify admin session (AJAX handler).
	 */
	public function verify_admin_session() {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			$current_user = wp_get_current_user();
			wp_send_json_success( array(
				'user_id' => $current_user->ID,
				'user_login' => $current_user->user_login,
				'roles' => $current_user->roles
			) );
		} else {
			wp_send_json_error( 'Admin session not found' );
		}
	}

	/**
	 * Prevent admin user deletion during import.
	 *
	 * @param int $user_id User ID being deleted
	 * @param int|null $reassign_id User ID to reassign posts to
	 * @param WP_User $user User object being deleted
	 */
	public function prevent_admin_deletion( $user_id, $reassign_id, $user ) {
		// Only intervene during demo import
		if ( ! get_transient( 'reign_demo_import_active' ) ) {
			return;
		}

		// Check if this is the backed up admin user
		$backup = get_transient( 'reign_current_admin_backup' );
		
		if ( $backup && $backup['ID'] == $user_id ) {
			// Prevent deletion by throwing an error
			wp_die( 
				esc_html__( 'Cannot delete admin user during demo import.', 'reign-demo-installer' ),
				esc_html__( 'Operation Blocked', 'reign-demo-installer' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Preserve admin capabilities during meta updates.
	 *
	 * @param int $meta_id Meta ID
	 * @param int $user_id User ID
	 * @param string $meta_key Meta key
	 * @param mixed $meta_value Meta value
	 */
	public function preserve_admin_capabilities( $meta_id, $user_id, $meta_key, $meta_value ) {
		// Only intervene during demo import
		if ( ! get_transient( 'reign_demo_import_active' ) ) {
			return;
		}

		// Check if this affects admin capabilities
		if ( in_array( $meta_key, array( 'wp_capabilities', 'wp_user_level' ) ) ) {
			$backup = get_transient( 'reign_current_admin_backup' );
			
			if ( $backup && $backup['ID'] == $user_id ) {
				// Restore admin capabilities
				update_user_meta( $user_id, 'wp_capabilities', array( 'administrator' => true ) );
				update_user_meta( $user_id, 'wp_user_level', 10 );
				
				Reign_Demo_Installer_Logger::info( 'Admin capabilities preserved for user: ' . $user_id );
			}
		}
	}

	/**
	 * Schedule periodic admin verification during import.
	 */
	private function schedule_admin_verification() {
		// Use WordPress cron to verify admin every 30 seconds during import
		if ( ! wp_next_scheduled( 'reign_verify_admin_hook' ) ) {
			wp_schedule_event( time(), 'reign_30_seconds', 'reign_verify_admin_hook' );
		}

		add_action( 'reign_verify_admin_hook', array( $this, 'periodic_admin_check' ) );

		// Add custom cron schedule
		add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedule' ) );
	}

	/**
	 * Add custom cron schedule.
	 *
	 * @param array $schedules Existing schedules
	 * @return array Modified schedules
	 */
	public function add_custom_cron_schedule( $schedules ) {
		$schedules['reign_30_seconds'] = array(
			'interval' => 30,
			'display' => __( 'Every 30 seconds', 'reign-demo-installer' )
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
			
			Reign_Demo_Installer_Logger::warning( 'Admin user automatically restored during import' );
		}
	}

	/**
	 * Check admin restoration on login.
	 *
	 * @param string $user_login User login
	 * @param WP_User $user User object
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
			
			Reign_Demo_Installer_Logger::info( 'Admin permissions restored on login for: ' . $user_login );
		}
	}

	/**
	 * Emergency admin check on admin_init.
	 */
	public function emergency_admin_check() {
		// Only run if we recently had an import
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
			
			Reign_Demo_Installer_Logger::info( 'Emergency admin restoration completed' );
			
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
		
		Reign_Demo_Installer_Logger::info( 'Admin guardian cleanup completed' );
	}

	/**
	 * Get admin backup status.
	 *
	 * @return array Backup status
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
	 *
	 * @param int $user_id Optional user ID
	 * @return bool Success status
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
 * Initialize the Admin Guardian.
 *
 * @return Reign_Demo_Installer_Admin_Guardian
 */
function reign_demo_installer_admin_guardian() {
	return Reign_Demo_Installer_Admin_Guardian::instance();
}

// Initialize only when needed
add_action( 'init', 'reign_demo_installer_admin_guardian', 5 );