<?php
/**
 * Logger class for Reign Demo Installer
 *
 * @package Reign_Demo_Installer
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reign_Demo_Installer_Logger class.
 */
class Reign_Demo_Installer_Logger {

	/**
	 * Log levels
	 */
	const EMERGENCY = 'emergency';
	const ALERT     = 'alert';
	const CRITICAL  = 'critical';
	const ERROR     = 'error';
	const WARNING   = 'warning';
	const NOTICE    = 'notice';
	const INFO      = 'info';
	const DEBUG     = 'debug';

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private static $log_file = null;

	/**
	 * Maximum log file size (in bytes)
	 *
	 * @var int
	 */
	private static $max_file_size = 5242880; // 5MB

	/**
	 * Initialize logger
	 */
	public static function init() {
		if ( is_null( self::$log_file ) ) {
			$upload_dir = wp_upload_dir();
			$log_dir = $upload_dir['basedir'] . '/reign-demo-installer-logs';
			
			// Create log directory if it doesn't exist
			if ( ! is_dir( $log_dir ) ) {
				wp_mkdir_p( $log_dir );
				// Add .htaccess to protect log files
				file_put_contents( $log_dir . '/.htaccess', 'Deny from all' );
			}
			
			self::$log_file = $log_dir . '/reign-demo-installer.log';
		}
	}

	/**
	 * Log a message
	 *
	 * @param string $message Log message
	 * @param string $level Log level
	 * @param array $context Additional context
	 */
	public static function log( $message, $level = self::INFO, $context = array() ) {
		// Don't log in production unless it's an error or higher
		if ( ! self::should_log( $level ) ) {
			return;
		}

		self::init();

		// Rotate log file if it's too large
		self::rotate_log_if_needed();

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$level = strtoupper( $level );
		
		// Get calling function/class for better debugging
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
		$caller = isset( $backtrace[2] ) ? $backtrace[2]['function'] : 'unknown';
		
		// Format message
		$formatted_message = sprintf(
			"[%s] %s: %s (Called from: %s)",
			$timestamp,
			$level,
			$message,
			$caller
		);

		// Add context if provided
		if ( ! empty( $context ) ) {
			$formatted_message .= ' | Context: ' . wp_json_encode( $context );
		}

		$formatted_message .= PHP_EOL;

		// Write to log file
		self::write_to_file( $formatted_message );

		// Also log to WordPress debug log if enabled
		if ( WP_DEBUG_LOG && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Reign Demo Installer] ' . $formatted_message );
		}
	}

	/**
	 * Log emergency message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public static function emergency( $message, $context = array() ) {
		self::log( $message, self::EMERGENCY, $context );
	}

	/**
	 * Log alert message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public static function alert( $message, $context = array() ) {
		self::log( $message, self::ALERT, $context );
	}

	/**
	 * Log critical message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public static function critical( $message, $context = array() ) {
		self::log( $message, self::CRITICAL, $context );
	}

	/**
	 * Log error message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public static function error( $message, $context = array() ) {
		self::log( $message, self::ERROR, $context );
	}

	/**
	 * Log warning message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public static function warning( $message, $context = array() ) {
		self::log( $message, self::WARNING, $context );
	}

	/**
	 * Log notice message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public static function notice( $message, $context = array() ) {
		self::log( $message, self::NOTICE, $context );
	}

	/**
	 * Log info message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public static function info( $message, $context = array() ) {
		self::log( $message, self::INFO, $context );
	}

	/**
	 * Log debug message
	 *
	 * @param string $message
	 * @param array $context
	 */
	public static function debug( $message, $context = array() ) {
		self::log( $message, self::DEBUG, $context );
	}

	/**
	 * Log demo import start
	 *
	 * @param string $demo_name
	 * @param array $data
	 */
	public static function log_import_start( $demo_name, $data = array() ) {
		self::info( "Demo import started: {$demo_name}", $data );
	}

	/**
	 * Log demo import success
	 *
	 * @param string $demo_name
	 * @param float $duration
	 */
	public static function log_import_success( $demo_name, $duration = 0 ) {
		$message = "Demo import completed successfully: {$demo_name}";
		if ( $duration > 0 ) {
			$message .= " (Duration: {$duration}s)";
		}
		self::info( $message );
	}

	/**
	 * Log demo import error
	 *
	 * @param string $demo_name
	 * @param string $error_message
	 * @param array $context
	 */
	public static function log_import_error( $demo_name, $error_message, $context = array() ) {
		self::error( "Demo import failed for {$demo_name}: {$error_message}", $context );
	}

	/**
	 * Log plugin installation
	 *
	 * @param string $plugin_slug
	 * @param string $status
	 * @param string $message
	 */
	public static function log_plugin_action( $plugin_slug, $status, $message = '' ) {
		$log_message = "Plugin {$status}: {$plugin_slug}";
		if ( ! empty( $message ) ) {
			$log_message .= " - {$message}";
		}
		
		if ( $status === 'error' || $status === 'failed' ) {
			self::error( $log_message );
		} else {
			self::info( $log_message );
		}
	}

	/**
	 * Check if we should log based on level and environment
	 *
	 * @param string $level
	 * @return bool
	 */
	private static function should_log( $level ) {
		$log_levels = array(
			self::DEBUG     => 0,
			self::INFO      => 1,
			self::NOTICE    => 2,
			self::WARNING   => 3,
			self::ERROR     => 4,
			self::CRITICAL  => 5,
			self::ALERT     => 6,
			self::EMERGENCY => 7,
		);

		$current_level = isset( $log_levels[ $level ] ) ? $log_levels[ $level ] : 1;
		
		// In production, only log warnings and above
		$min_level = self::is_production() ? 3 : 0;
		
		// Always log if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$min_level = 0;
		}

		return $current_level >= $min_level;
	}

	/**
	 * Check if we're in production environment
	 *
	 * @return bool
	 */
	private static function is_production() {
		// Check common production indicators
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return false;
		}
		
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			return WP_ENVIRONMENT_TYPE === 'production';
		}
		
		// Check for development domains
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( $_SERVER['HTTP_HOST'] ) : '';
		$dev_indicators = array( 'localhost', '127.0.0.1', '.local', '.dev', '.test' );
		
		foreach ( $dev_indicators as $indicator ) {
			if ( strpos( $host, $indicator ) !== false ) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Write message to log file
	 *
	 * @param string $message
	 */
	private static function write_to_file( $message ) {
		if ( ! self::$log_file ) {
			return;
		}

		// Use WordPress filesystem if available
		if ( function_exists( 'WP_Filesystem' ) ) {
			global $wp_filesystem;
			
			if ( ! $wp_filesystem ) {
				WP_Filesystem();
			}
			
			if ( $wp_filesystem ) {
				$existing_content = $wp_filesystem->exists( self::$log_file ) ? $wp_filesystem->get_contents( self::$log_file ) : '';
				$wp_filesystem->put_contents( self::$log_file, $existing_content . $message );
				return;
			}
		}

		// Fallback to standard PHP file operations
		file_put_contents( self::$log_file, $message, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Rotate log file if it's too large
	 */
	private static function rotate_log_if_needed() {
		if ( ! self::$log_file || ! file_exists( self::$log_file ) ) {
			return;
		}

		if ( filesize( self::$log_file ) > self::$max_file_size ) {
			$backup_file = self::$log_file . '.old';
			
			// Remove old backup
			if ( file_exists( $backup_file ) ) {
				wp_delete_file( $backup_file );
			}
			
			// Move current log to backup
			rename( self::$log_file, $backup_file );
		}
	}

	/**
	 * Get log file contents
	 *
	 * @param int $lines Number of lines to return (0 for all)
	 * @return string
	 */
	public static function get_log_contents( $lines = 100 ) {
		self::init();
		
		if ( ! file_exists( self::$log_file ) ) {
			return '';
		}

		if ( $lines === 0 ) {
			return file_get_contents( self::$log_file );
		}

		// Get last N lines
		$file = file( self::$log_file );
		if ( $file === false ) {
			return '';
		}

		$total_lines = count( $file );
		$start = max( 0, $total_lines - $lines );
		
		return implode( '', array_slice( $file, $start ) );
	}

	/**
	 * Clear log file
	 */
	public static function clear_log() {
		self::init();
		
		if ( file_exists( self::$log_file ) ) {
			wp_delete_file( self::$log_file );
		}
	}

	/**
	 * Get log file size
	 *
	 * @return int Size in bytes
	 */
	public static function get_log_size() {
		self::init();
		
		if ( ! file_exists( self::$log_file ) ) {
			return 0;
		}

		return filesize( self::$log_file );
	}

	/**
	 * Format bytes to human readable
	 *
	 * @param int $bytes
	 * @return string
	 */
	public static function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		
		for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
			$bytes /= 1024;
		}
		
		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}
}