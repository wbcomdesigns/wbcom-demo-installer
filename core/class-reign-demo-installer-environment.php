<?php
/**
 * Environment detection class for Reign Demo Installer
 *
 * @package Reign_Demo_Installer
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reign_Demo_Installer_Environment class.
 */
class Reign_Demo_Installer_Environment {

	/**
	 * Check if we're in production environment.
	 *
	 * @return bool True if production, false otherwise
	 */
	public static function is_production() {
		// Check WordPress environment type (WP 5.5+)
		if ( function_exists( 'wp_get_environment_type' ) ) {
			return wp_get_environment_type() === 'production';
		}

		// Fallback checks
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			return WP_ENVIRONMENT_TYPE === 'production';
		}

		// Check for debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return false;
		}

		// Check for development URLs
		$host = self::get_site_host();
		$dev_indicators = array(
			'localhost',
			'127.0.0.1',
			'::1',
			'.local',
			'.dev',
			'.test',
			'staging',
			'dev.',
			'test.',
		);

		foreach ( $dev_indicators as $indicator ) {
			if ( strpos( $host, $indicator ) !== false ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if we're in development environment.
	 *
	 * @return bool True if development, false otherwise
	 */
	public static function is_development() {
		return ! self::is_production();
	}

	/**
	 * Check if Reign theme is active.
	 *
	 * @return bool True if Reign theme is active, false otherwise
	 */
	public static function is_reign_theme_active() {
		return get_template() === 'reign';
	}

	/**
	 * Check if we have sufficient server resources.
	 *
	 * @return array Status of server resources
	 */
	public static function check_server_requirements() {
		$requirements = array(
			'php_version' => array(
				'required' => '7.4',
				'current' => PHP_VERSION,
				'status' => version_compare( PHP_VERSION, '7.4', '>=' )
			),
			'wp_version' => array(
				'required' => '5.0',
				'current' => get_bloginfo( 'version' ),
				'status' => version_compare( get_bloginfo( 'version' ), '5.0', '>=' )
			),
			'memory_limit' => array(
				'required' => '256M',
				'current' => ini_get( 'memory_limit' ),
				'status' => self::check_memory_limit( '256M' )
			),
			'max_execution_time' => array(
				'required' => '300',
				'current' => ini_get( 'max_execution_time' ),
				'status' => intval( ini_get( 'max_execution_time' ) ) >= 300 || ini_get( 'max_execution_time' ) == 0
			),
			'upload_max_filesize' => array(
				'required' => '32M',
				'current' => ini_get( 'upload_max_filesize' ),
				'status' => self::check_file_size_limit( '32M', 'upload_max_filesize' )
			),
			'post_max_size' => array(
				'required' => '32M',
				'current' => ini_get( 'post_max_size' ),
				'status' => self::check_file_size_limit( '32M', 'post_max_size' )
			)
		);

		// Check required PHP extensions
		$extensions = array( 'curl', 'zip', 'json', 'xml' );
		foreach ( $extensions as $ext ) {
			$requirements[ $ext . '_extension' ] = array(
				'required' => 'Enabled',
				'current' => extension_loaded( $ext ) ? 'Enabled' : 'Disabled',
				'status' => extension_loaded( $ext )
			);
		}

		return $requirements;
	}

	/**
	 * Check if server meets minimum requirements.
	 *
	 * @return bool True if requirements are met, false otherwise
	 */
	public static function meets_requirements() {
		$requirements = self::check_server_requirements();
		
		foreach ( $requirements as $requirement ) {
			if ( ! $requirement['status'] ) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Get list of requirement failures.
	 *
	 * @return array Array of failed requirements
	 */
	public static function get_requirement_failures() {
		$requirements = self::check_server_requirements();
		$failures = array();
		
		foreach ( $requirements as $key => $requirement ) {
			if ( ! $requirement['status'] ) {
				$failures[ $key ] = $requirement;
			}
		}
		
		return $failures;
	}

	/**
	 * Check memory limit.
	 *
	 * @param string $required_limit Required memory limit
	 * @return bool True if sufficient, false otherwise
	 */
	private static function check_memory_limit( $required_limit ) {
		$current = ini_get( 'memory_limit' );
		
		if ( $current == -1 ) {
			return true; // Unlimited
		}
		
		$required_bytes = self::convert_to_bytes( $required_limit );
		$current_bytes = self::convert_to_bytes( $current );
		
		return $current_bytes >= $required_bytes;
	}

	/**
	 * Check file size limit.
	 *
	 * @param string $required_limit Required file size limit
	 * @param string $setting PHP setting name
	 * @return bool True if sufficient, false otherwise
	 */
	private static function check_file_size_limit( $required_limit, $setting ) {
		$current = ini_get( $setting );
		
		$required_bytes = self::convert_to_bytes( $required_limit );
		$current_bytes = self::convert_to_bytes( $current );
		
		return $current_bytes >= $required_bytes;
	}

	/**
	 * Convert human readable size to bytes.
	 *
	 * @param string $size Size string (e.g., '256M')
	 * @return int Size in bytes
	 */
	private static function convert_to_bytes( $size ) {
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
	 * Get site host.
	 *
	 * @return string Site host
	 */
	private static function get_site_host() {
		$parsed_url = wp_parse_url( home_url() );
		return isset( $parsed_url['host'] ) ? strtolower( $parsed_url['host'] ) : '';
	}

	/**
	 * Check if WordPress filesystem is available.
	 *
	 * @return bool True if available, false otherwise
	 */
	public static function is_filesystem_available() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		
		return WP_Filesystem();
	}

	/**
	 * Get upload directory information.
	 *
	 * @return array Upload directory info
	 */
	public static function get_upload_dir_info() {
		$upload_dir = wp_upload_dir();
		
		return array(
			'basedir' => $upload_dir['basedir'],
			'baseurl' => $upload_dir['baseurl'],
			'writable' => is_writable( $upload_dir['basedir'] ),
			'exists' => is_dir( $upload_dir['basedir'] )
		);
	}

	/**
	 * Check if we can create directories.
	 *
	 * @return bool True if can create directories, false otherwise
	 */
	public static function can_create_directories() {
		$upload_dir = wp_upload_dir();
		$test_dir = $upload_dir['basedir'] . '/reign-demo-installer-test';
		
		// Try to create test directory
		if ( wp_mkdir_p( $test_dir ) ) {
			// Clean up test directory
			rmdir( $test_dir );
			return true;
		}
		
		return false;
	}

	/**
	 * Check if we can write files.
	 *
	 * @return bool True if can write files, false otherwise
	 */
	public static function can_write_files() {
		$upload_dir = wp_upload_dir();
		$test_file = $upload_dir['basedir'] . '/reign-demo-installer-test.txt';
		
		// Try to write test file
		$result = file_put_contents( $test_file, 'test' );
		
		if ( $result !== false ) {
			// Clean up test file
			unlink( $test_file );
			return true;
		}
		
		return false;
	}

	/**
	 * Get WordPress configuration info.
	 *
	 * @return array WordPress configuration
	 */
	public static function get_wp_config_info() {
		return array(
			'wp_debug' => defined( 'WP_DEBUG' ) ? WP_DEBUG : false,
			'wp_debug_log' => defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : false,
			'wp_debug_display' => defined( 'WP_DEBUG_DISPLAY' ) ? WP_DEBUG_DISPLAY : false,
			'script_debug' => defined( 'SCRIPT_DEBUG' ) ? SCRIPT_DEBUG : false,
			'wp_cache' => defined( 'WP_CACHE' ) ? WP_CACHE : false,
			'multisite' => is_multisite(),
			'wp_environment_type' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'unknown'
		);
	}

	/**
	 * Check if current user has required capabilities.
	 *
	 * @return bool True if user has required capabilities, false otherwise
	 */
	public static function user_can_import() {
		return current_user_can( 'manage_options' ) && 
			   current_user_can( 'install_plugins' ) && 
			   current_user_can( 'activate_plugins' ) &&
			   current_user_can( 'import' );
	}

	/**
	 * Get system status for debugging.
	 *
	 * @return array System status information
	 */
	public static function get_system_status() {
		return array(
			'environment' => array(
				'is_production' => self::is_production(),
				'is_reign_active' => self::is_reign_theme_active(),
				'meets_requirements' => self::meets_requirements(),
				'user_can_import' => self::user_can_import(),
			),
			'server' => self::check_server_requirements(),
			'wordpress' => self::get_wp_config_info(),
			'filesystem' => array(
				'available' => self::is_filesystem_available(),
				'can_create_dirs' => self::can_create_directories(),
				'can_write_files' => self::can_write_files(),
				'upload_dir' => self::get_upload_dir_info(),
			),
			'plugin' => array(
				'version' => REIGN_DEMO_INSTALLER_VERSION,
				'path' => REIGN_DEMO_INSTALLER_PLUGIN_DIR_PATH,
				'url' => REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL,
			)
		);
	}

	/**
	 * Display system status in admin.
	 */
	public static function display_system_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = self::get_system_status();
		
		echo '<div class="reign-demo-installer-system-status">';
		echo '<h3>' . esc_html__( 'System Status', 'reign-demo-installer' ) . '</h3>';
		
		// Environment status
		echo '<h4>' . esc_html__( 'Environment', 'reign-demo-installer' ) . '</h4>';
		echo '<ul>';
		foreach ( $status['environment'] as $key => $value ) {
			$status_text = $value ? '✓' : '✗';
			$class = $value ? 'success' : 'error';
			echo '<li class="' . esc_attr( $class ) . '">';
			echo '<span class="status">' . esc_html( $status_text ) . '</span> ';
			echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) );
			echo '</li>';
		}
		echo '</ul>';
		
		// Server requirements
		echo '<h4>' . esc_html__( 'Server Requirements', 'reign-demo-installer' ) . '</h4>';
		echo '<ul>';
		foreach ( $status['server'] as $key => $requirement ) {
			$status_text = $requirement['status'] ? '✓' : '✗';
			$class = $requirement['status'] ? 'success' : 'error';
			echo '<li class="' . esc_attr( $class ) . '">';
			echo '<span class="status">' . esc_html( $status_text ) . '</span> ';
			echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) );
			echo ' (Required: ' . esc_html( $requirement['required'] ) . ', Current: ' . esc_html( $requirement['current'] ) . ')';
			echo '</li>';
		}
		echo '</ul>';
		
		echo '</div>';
	}
}