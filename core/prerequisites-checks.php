<?php
/**
 * Prerequisites checker for Reign Demo Installer
 *
 * @package Reign_Demo_Installer
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Reign_Demo_Installer_Prerequisites_Checker' ) ) :

/**
 * Reign_Demo_Installer_Prerequisites_Checker class.
 *
 * @class Reign_Demo_Installer_Prerequisites_Checker
 * @version 3.0.0
 */
class Reign_Demo_Installer_Prerequisites_Checker {
	
	/**
	 * The single instance of the class.
	 *
	 * @var Reign_Demo_Installer_Prerequisites_Checker
	 * @since 3.0.0
	 */
	protected static $_instance = null;
	
	/**
	 * Main instance.
	 *
	 * Ensures only one instance is loaded or can be loaded.
	 *
	 * @since 3.0.0
	 * @static
	 * @return Reign_Demo_Installer_Prerequisites_Checker - Main instance.
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
		// Hook into wp_loaded instead of admin_init to ensure all functions are available
		add_action( 'wp_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialize the checker.
	 */
	public function init() {
		// Only run checks on our admin pages
		if ( is_admin() && $this->is_reign_demo_installer_page() ) {
			$this->run_system_checks();
		}
	}

	/**
	 * Check if we're on a Reign Demo Installer page.
	 *
	 * @return bool True if on plugin page, false otherwise
	 */
	private function is_reign_demo_installer_page() {
		// Add safety check for get_current_screen
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return strpos( $screen->id, 'reign-demo-installer' ) !== false ||
			   strpos( $screen->id, 'wbcom-theme-demo-installer' ) !== false; // Backward compatibility
	}

	/**
	 * Run comprehensive system checks.
	 */
	public function run_system_checks() {
		$issues = array();

		// Check PHP version
		if ( ! $this->check_php_version() ) {
			$issues[] = array(
				'type' => 'error',
				'message' => sprintf(
					__( 'PHP version %s or higher is required. Current version: %s', 'reign-demo-installer' ),
					'7.4',
					PHP_VERSION
				)
			);
		}

		// Check WordPress version
		if ( ! $this->check_wp_version() ) {
			$issues[] = array(
				'type' => 'error',
				'message' => sprintf(
					__( 'WordPress version %s or higher is required. Current version: %s', 'reign-demo-installer' ),
					'5.0',
					get_bloginfo( 'version' )
				)
			);
		}

		// Check required PHP extensions
		$missing_extensions = $this->get_missing_extensions();
		if ( ! empty( $missing_extensions ) ) {
			$issues[] = array(
				'type' => 'error',
				'message' => sprintf(
					__( 'Missing required PHP extensions: %s', 'reign-demo-installer' ),
					implode( ', ', $missing_extensions )
				)
			);
		}

		// Check memory limit
		if ( ! $this->check_memory_limit() ) {
			$issues[] = array(
				'type' => 'warning',
				'message' => sprintf(
					__( 'Memory limit is low (%s). Recommended: 256M or higher for smooth operation.', 'reign-demo-installer' ),
					ini_get( 'memory_limit' )
				)
			);
		}

		// Check execution time
		if ( ! $this->check_execution_time() ) {
			$issues[] = array(
				'type' => 'warning',
				'message' => sprintf(
					__( 'Max execution time is low (%s seconds). Recommended: 300 seconds or higher.', 'reign-demo-installer' ),
					ini_get( 'max_execution_time' )
				)
			);
		}

		// Check filesystem permissions
		if ( ! $this->check_filesystem_permissions() ) {
			$issues[] = array(
				'type' => 'error',
				'message' => __( 'WordPress cannot write to the uploads directory. Please check file permissions.', 'reign-demo-installer' )
			);
		}

		// Check Reign theme
		if ( ! $this->is_reign_theme_active() ) {
			$issues[] = array(
				'type' => 'warning',
				'message' => __( 'Reign theme is not active. This plugin is designed specifically for the Reign theme.', 'reign-demo-installer' )
			);
		}

		// Check user capabilities - FIXED: Added safety checks
		if ( ! $this->check_user_capabilities() ) {
			$issues[] = array(
				'type' => 'error',
				'message' => __( 'Current user lacks required permissions (manage_options, install_plugins, activate_plugins).', 'reign-demo-installer' )
			);
		}

		// Display issues if any
		if ( ! empty( $issues ) ) {
			$this->display_issues( $issues );
		} else {
			$this->log_success();
		}
	}

	/**
	 * Check PHP version.
	 *
	 * @return bool True if version is adequate, false otherwise
	 */
	public function check_php_version() {
		return version_compare( PHP_VERSION, '7.4', '>=' );
	}

	/**
	 * Check WordPress version.
	 *
	 * @return bool True if version is adequate, false otherwise
	 */
	public function check_wp_version() {
		return version_compare( get_bloginfo( 'version' ), '5.0', '>=' );
	}

	/**
	 * Check if cURL extension is enabled.
	 *
	 * @return bool True if enabled, false otherwise
	 */
	public function isCurlEnabled() {
		return extension_loaded( 'curl' ) && function_exists( 'curl_init' );
	}

	/**
	 * Check if ZIP extension is enabled.
	 *
	 * @return bool True if enabled, false otherwise
	 */
	public function isZipEnabled() {
		return extension_loaded( 'zip' ) && class_exists( 'ZipArchive' );
	}

	/**
	 * Check if XML extension is enabled.
	 *
	 * @return bool True if enabled, false otherwise
	 */
	public function isXmlEnabled() {
		return extension_loaded( 'xml' ) && extension_loaded( 'simplexml' );
	}

	/**
	 * Check if JSON extension is enabled.
	 *
	 * @return bool True if enabled, false otherwise
	 */
	public function isJsonEnabled() {
		return extension_loaded( 'json' ) && function_exists( 'json_decode' );
	}

	/**
	 * Get list of missing required extensions.
	 *
	 * @return array Missing extensions
	 */
	public function get_missing_extensions() {
		$required_extensions = array(
			'curl' => 'cURL',
			'zip' => 'ZIP',
			'xml' => 'XML',
			'json' => 'JSON'
		);

		$missing = array();

		foreach ( $required_extensions as $ext => $name ) {
			$check_method = 'is' . ucfirst( $ext ) . 'Enabled';
			if ( method_exists( $this, $check_method ) && ! $this->$check_method() ) {
				$missing[] = $name;
			}
		}

		return $missing;
	}

	/**
	 * Check memory limit.
	 *
	 * @param string $required_limit Required memory limit (default: 256M)
	 * @return bool True if adequate, false otherwise
	 */
	public function check_memory_limit( $required_limit = '256M' ) {
		$current_limit = ini_get( 'memory_limit' );
		
		// Unlimited memory
		if ( $current_limit == -1 ) {
			return true;
		}

		$current_bytes = $this->convert_to_bytes( $current_limit );
		$required_bytes = $this->convert_to_bytes( $required_limit );

		return $current_bytes >= $required_bytes;
	}

	/**
	 * Check execution time limit.
	 *
	 * @param int $required_time Required time in seconds (default: 300)
	 * @return bool True if adequate, false otherwise
	 */
	public function check_execution_time( $required_time = 300 ) {
		$current_time = ini_get( 'max_execution_time' );
		
		// Unlimited execution time
		if ( $current_time == 0 ) {
			return true;
		}

		return intval( $current_time ) >= $required_time;
	}

	/**
	 * Check filesystem permissions.
	 *
	 * @return bool True if writable, false otherwise
	 */
	public function check_filesystem_permissions() {
		$upload_dir = wp_upload_dir();
		
		// Check if uploads directory exists and is writable
		if ( ! is_dir( $upload_dir['basedir'] ) ) {
			return wp_mkdir_p( $upload_dir['basedir'] );
		}

		return is_writable( $upload_dir['basedir'] );
	}

	/**
	 * Check if Reign theme is active.
	 *
	 * @return bool True if Reign theme is active, false otherwise
	 */
	public function is_reign_theme_active() {
		return get_template() === 'reign';
	}

	/**
	 * Check user capabilities.
	 * FIXED: Added safety checks for WordPress functions
	 *
	 * @return bool True if user has required capabilities, false otherwise
	 */
	public function check_user_capabilities() {
		// Check if WordPress functions are available
		if ( ! function_exists( 'current_user_can' ) || ! function_exists( 'wp_get_current_user' ) ) {
			return false;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return current_user_can( 'manage_options' ) &&
			   current_user_can( 'install_plugins' ) &&
			   current_user_can( 'activate_plugins' );
	}

	/**
	 * Convert memory size to bytes.
	 *
	 * @param string $size Memory size (e.g., '256M')
	 * @return int Size in bytes
	 */
	private function convert_to_bytes( $size ) {
		$size = trim( $size );
		$last = strtolower( $size[ strlen( $size ) - 1 ] );
		$size = (int) $size;
		
		switch ( $last ) {
			case 'g':
				$size *= 1024;
			case 'm':
				$size *= 1024;
			case 'k':
				$size *= 1024;
		}
		
		return $size;
	}

	/**
	 * Display issues to admin.
	 *
	 * @param array $issues Array of issues
	 */
	private function display_issues( $issues ) {
		add_action( 'admin_notices', function() use ( $issues ) {
			foreach ( $issues as $issue ) {
				$class = $issue['type'] === 'error' ? 'notice-error' : 'notice-warning';
				echo '<div class="notice ' . esc_attr( $class ) . '">';
				echo '<p><strong>' . esc_html__( 'Reign Demo Installer:', 'reign-demo-installer' ) . '</strong> ';
				echo esc_html( $issue['message'] ) . '</p>';
				echo '</div>';
			}
		} );

		// Log issues
		if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
			foreach ( $issues as $issue ) {
				$level = $issue['type'] === 'error' ? 'error' : 'warning';
				Reign_Demo_Installer_Logger::log( 
					'Prerequisites check: ' . $issue['message'], 
					$level 
				);
			}
		}
	}

	/**
	 * Log successful check.
	 */
	private function log_success() {
		if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
			Reign_Demo_Installer_Logger::info( 'All prerequisites checks passed successfully' );
		}
	}

	/**
	 * Get comprehensive system report.
	 * FIXED: Added safety checks for WordPress functions
	 *
	 * @return array System report
	 */
	public function get_system_report() {
		$current_user = null;
		
		// Safely get current user
		if ( function_exists( 'wp_get_current_user' ) && is_user_logged_in() ) {
			$current_user = wp_get_current_user();
		}

		return array(
			'php_version' => array(
				'required' => '7.4+',
				'current' => PHP_VERSION,
				'status' => $this->check_php_version()
			),
			'wp_version' => array(
				'required' => '5.0+',
				'current' => get_bloginfo( 'version' ),
				'status' => $this->check_wp_version()
			),
			'memory_limit' => array(
				'required' => '256M+',
				'current' => ini_get( 'memory_limit' ),
				'status' => $this->check_memory_limit()
			),
			'max_execution_time' => array(
				'required' => '300s+',
				'current' => ini_get( 'max_execution_time' ) . 's',
				'status' => $this->check_execution_time()
			),
			'extensions' => array(
				'curl' => $this->isCurlEnabled(),
				'zip' => $this->isZipEnabled(),
				'xml' => $this->isXmlEnabled(),
				'json' => $this->isJsonEnabled()
			),
			'filesystem' => array(
				'uploads_writable' => $this->check_filesystem_permissions(),
			),
			'theme' => array(
				'reign_active' => $this->is_reign_theme_active(),
				'current_theme' => get_template()
			),
			'user' => array(
				'has_required_caps' => $this->check_user_capabilities(),
				'current_user' => $current_user ? $current_user->user_login : 'Not logged in'
			)
		);
	}

	/**
	 * Display system report in admin.
	 * FIXED: Added safety check for current_user_can
	 */
	public function display_system_report() {
		// Safety check
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$report = $this->get_system_report();
		
		echo '<div class="reign-system-report">';
		echo '<h3>' . esc_html__( 'System Report', 'reign-demo-installer' ) . '</h3>';
		
		echo '<table class="widefat">';
		echo '<thead><tr><th>' . esc_html__( 'Check', 'reign-demo-installer' ) . '</th>';
		echo '<th>' . esc_html__( 'Required', 'reign-demo-installer' ) . '</th>';
		echo '<th>' . esc_html__( 'Current', 'reign-demo-installer' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'reign-demo-installer' ) . '</th></tr></thead>';
		echo '<tbody>';

		// Basic checks
		$basic_checks = array( 'php_version', 'wp_version', 'memory_limit', 'max_execution_time' );
		foreach ( $basic_checks as $check ) {
			if ( isset( $report[ $check ] ) ) {
				$data = $report[ $check ];
				$status_icon = $data['status'] ? '✓' : '✗';
				$status_class = $data['status'] ? 'success' : 'error';
				
				echo '<tr>';
				echo '<td>' . esc_html( ucwords( str_replace( '_', ' ', $check ) ) ) . '</td>';
				echo '<td>' . esc_html( $data['required'] ) . '</td>';
				echo '<td>' . esc_html( $data['current'] ) . '</td>';
				echo '<td class="' . esc_attr( $status_class ) . '">' . esc_html( $status_icon ) . '</td>';
				echo '</tr>';
			}
		}

		// Extensions
		if ( isset( $report['extensions'] ) ) {
			foreach ( $report['extensions'] as $ext => $status ) {
				$status_icon = $status ? '✓' : '✗';
				$status_class = $status ? 'success' : 'error';
				
				echo '<tr>';
				echo '<td>' . esc_html( strtoupper( $ext ) . ' Extension' ) . '</td>';
				echo '<td>' . esc_html__( 'Enabled', 'reign-demo-installer' ) . '</td>';
				echo '<td>' . ( $status ? esc_html__( 'Enabled', 'reign-demo-installer' ) : esc_html__( 'Disabled', 'reign-demo-installer' ) ) . '</td>';
				echo '<td class="' . esc_attr( $status_class ) . '">' . esc_html( $status_icon ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>';

		// Add basic CSS
		echo '<style>
		.reign-system-report .success { color: #46b450; font-weight: bold; }
		.reign-system-report .error { color: #dc3232; font-weight: bold; }
		.reign-system-report table { margin-top: 10px; }
		</style>';
	}

	/**
	 * Check if all requirements are met.
	 *
	 * @return bool True if all requirements met, false otherwise
	 */
	public function all_requirements_met() {
		return $this->check_php_version() &&
			   $this->check_wp_version() &&
			   empty( $this->get_missing_extensions() ) &&
			   $this->check_filesystem_permissions() &&
			   $this->check_user_capabilities();
	}

	/**
	 * Get requirements status for API.
	 *
	 * @return array Requirements status
	 */
	public function get_requirements_status() {
		return array(
			'all_met' => $this->all_requirements_met(),
			'php_version_ok' => $this->check_php_version(),
			'wp_version_ok' => $this->check_wp_version(),
			'extensions_ok' => empty( $this->get_missing_extensions() ),
			'filesystem_ok' => $this->check_filesystem_permissions(),
			'user_caps_ok' => $this->check_user_capabilities(),
			'reign_theme_active' => $this->is_reign_theme_active(),
			'missing_extensions' => $this->get_missing_extensions()
		);
	}
}

endif;

// Backward compatibility - create alias for old class name
if ( ! class_exists( 'WBCOM_Demo_Importer_PreRequisites_Checker' ) ) {
	class_alias( 'Reign_Demo_Installer_Prerequisites_Checker', 'WBCOM_Demo_Importer_PreRequisites_Checker' );
}

/**
 * Main instance of Reign_Demo_Installer_Prerequisites_Checker.
 * FIXED: Only instantiate after WordPress is loaded
 *
 * @since 3.0.0
 * @return Reign_Demo_Installer_Prerequisites_Checker
 */
function reign_demo_installer_prerequisites_checker() {
	return Reign_Demo_Installer_Prerequisites_Checker::instance();
}

// Hook the initialization to ensure WordPress is fully loaded
add_action( 'init', 'reign_demo_installer_prerequisites_checker' );