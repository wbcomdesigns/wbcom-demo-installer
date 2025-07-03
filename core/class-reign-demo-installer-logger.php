<?php
/**
 * Logger class for Reign Demo Installer - Enhanced Version
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
	 * Log level hierarchy
	 *
	 * @var array
	 */
	private static $log_levels = array(
		self::DEBUG     => 0,
		self::INFO      => 1,
		self::NOTICE    => 2,
		self::WARNING   => 3,
		self::ERROR     => 4,
		self::CRITICAL  => 5,
		self::ALERT     => 6,
		self::EMERGENCY => 7,
	);

	/**
	 * Current minimum log level
	 *
	 * @var int
	 */
	private static $min_log_level = null;

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
				self::create_htaccess_protection( $log_dir );
			}
			
			self::$log_file = $log_dir . '/reign-demo-installer.log';
		}

		// Set minimum log level
		if ( is_null( self::$min_log_level ) ) {
			self::$min_log_level = self::determine_min_log_level();
		}
	}

	/**
	 * Create .htaccess protection for log directory.
	 *
	 * @param string $log_dir Log directory path
	 */
	private static function create_htaccess_protection( $log_dir ) {
		$htaccess_content = "# Protect log files\n";
		$htaccess_content .= "Order deny,allow\n";
		$htaccess_content .= "Deny from all\n";
		$htaccess_content .= "<Files ~ \"\\.log$\">\n";
		$htaccess_content .= "    Order deny,allow\n";
		$htaccess_content .= "    Deny from all\n";
		$htaccess_content .= "</Files>\n";

		file_put_contents( $log_dir . '/.htaccess', $htaccess_content );
	}

	/**
	 * Determine minimum log level based on environment.
	 *
	 * @return int
	 */
	private static function determine_min_log_level() {
		// Debug mode: log everything
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return 0;
		}

		// Development environment: log info and above
		if ( self::is_development_environment() ) {
			return self::$log_levels[ self::INFO ];
		}

		// Production: only warnings and above
		return self::$log_levels[ self::WARNING ];
	}

	/**
	 * Check if we're in development environment.
	 *
	 * @return bool
	 */
	private static function is_development_environment() {
		// Check for development indicators
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE !== 'production' ) {
			return true;
		}

		// Check for development domains
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( $_SERVER['HTTP_HOST'] ) : '';
		$dev_indicators = array( 'localhost', '127.0.0.1', '.local', '.dev', '.test' );
		
		foreach ( $dev_indicators as $indicator ) {
			if ( strpos( $host, $indicator ) !== false ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Log a message
	 *
	 * @param string $message Log message
	 * @param string $level Log level
	 * @param array $context Additional context
	 */
	public static function log( $message, $level = self::INFO, $context = array() ) {
		// Don't log if level is below minimum
		if ( ! self::should_log( $level ) ) {
			return;
		}

		self::init();

		// Rotate log file if it's too large
		self::rotate_log_if_needed();

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$level = strtoupper( $level );
		
		// Get calling function/class for better debugging
		$caller = self::get_caller_info();
		
		// Format message
		$formatted_message = sprintf(
			"[%s] %s: %s %s",
			$timestamp,
			$level,
			$message,
			$caller ? "(Called from: {$caller})" : ''
		);

		// Add context if provided
		if ( ! empty( $context ) ) {
			$formatted_message .= ' | Context: ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
		}

		// Add memory usage for performance monitoring
		if ( $level === 'ERROR' || $level === 'CRITICAL' ) {
			$formatted_message .= ' | Memory: ' . self::format_bytes( memory_get_usage( true ) );
		}

		$formatted_message .= PHP_EOL;

		// Write to log file
		self::write_to_file( $formatted_message );

		// Also log to WordPress debug log if enabled
		if ( WP_DEBUG_LOG && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Reign Demo Installer] ' . trim( $formatted_message ) );
		}

		// Send to external logging service if configured
		self::send_to_external_service( $level, $message, $context );
	}

	/**
	 * Get caller information for debugging.
	 *
	 * @return string|null
	 */
	private static function get_caller_info() {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 4 );
		
		// Skip the log() and specific log level methods
		foreach ( $backtrace as $trace ) {
			if ( isset( $trace['function'] ) && 
				 ! in_array( $trace['function'], array( 'log', 'error', 'warning', 'info', 'debug', 'notice', 'critical', 'alert', 'emergency' ) ) ) {
				
				$class = isset( $trace['class'] ) ? $trace['class'] . '::' : '';
				return $class . $trace['function'];
			}
		}
		
		return null;
	}

	/**
	 * Send log to external service if configured.
	 *
	 * @param string $level Log level
	 * @param string $message Message
	 * @param array $context Context
	 */
	private static function send_to_external_service( $level, $message, $context ) {
		// Only send critical errors to external service
		if ( ! in_array( $level, array( 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' ) ) ) {
			return;
		}

		// Hook for external logging services
		do_action( 'reign_demo_installer_log_external', $level, $message, $context );
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
		$enhanced_data = array_merge( $data, array(
			'user_id' => get_current_user_id(),
			'user_ip' => self::get_client_ip(),
			'wp_version' => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'memory_limit' => ini_get( 'memory_limit' ),
		) );
		
		self::info( "Demo import started: {$demo_name}", $enhanced_data );
	}

	/**
	 * Log demo import success
	 *
	 * @param string $demo_name
	 * @param float $duration
	 */
	public static function log_import_success( $demo_name, $duration = 0 ) {
		$message = "Demo import completed successfully: {$demo_name}";
		$context = array();
		
		if ( $duration > 0 ) {
			$message .= " (Duration: {$duration}s)";
			$context['duration'] = $duration;
		}
		
		$context['memory_peak'] = self::format_bytes( memory_get_peak_usage( true ) );
		
		self::info( $message, $context );
	}

	/**
	 * Log demo import error
	 *
	 * @param string $demo_name
	 * @param string $error_message
	 * @param array $context
	 */
	public static function log_import_error( $demo_name, $error_message, $context = array() ) {
		$enhanced_context = array_merge( $context, array(
			'demo_name' => $demo_name,
			'memory_usage' => self::format_bytes( memory_get_usage( true ) ),
			'time_limit' => ini_get( 'max_execution_time' ),
		) );
		
		self::error( "Demo import failed for {$demo_name}: {$error_message}", $enhanced_context );
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
		
		$context = array(
			'plugin_slug' => $plugin_slug,
			'action_status' => $status,
		);
		
		if ( $status === 'error' || $status === 'failed' ) {
			self::error( $log_message, $context );
		} else {
			self::info( $log_message, $context );
		}
	}

	/**
	 * Check if we should log based on level and environment
	 *
	 * @param string $level
	 * @return bool
	 */
	private static function should_log( $level ) {
		if ( ! isset( self::$log_levels[ $level ] ) ) {
			return false;
		}

		$current_level = self::$log_levels[ $level ];
		
		return $current_level >= self::$min_log_level;
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
			
			if ( $wp_filesystem && $wp_filesystem->exists( dirname( self::$log_file ) ) ) {
				$existing_content = $wp_filesystem->exists( self::$log_file ) ? $wp_filesystem->get_contents( self::$log_file ) : '';
				$wp_filesystem->put_contents( self::$log_file, $existing_content . $message );
				return;
			}
		}

		// Fallback to standard PHP file operations
		if ( is_writable( dirname( self::$log_file ) ) ) {
			file_put_contents( self::$log_file, $message, FILE_APPEND | LOCK_EX );
		}
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
			
			// Log rotation event
			self::info( 'Log file rotated due to size limit' );
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

		// Get last N lines efficiently
		$file = file( self::$log_file );
		if ( $file === false ) {
			return '';
		}

		$total_lines = count( $file );
		$start = max( 0, $total_lines - $lines );
		
		return implode( '', array_slice( $file, $start ) );
	}

	/**
	 * Get filtered log contents by level.
	 *
	 * @param string $level Log level to filter by
	 * @param int $lines Number of lines to return
	 * @return string
	 */
	public static function get_log_contents_by_level( $level, $lines = 100 ) {
		$all_content = self::get_log_contents( 0 );
		$filtered_lines = array();
		
		foreach ( explode( "\n", $all_content ) as $line ) {
			if ( strpos( $line, strtoupper( $level ) ) !== false ) {
				$filtered_lines[] = $line;
			}
		}
		
		if ( $lines > 0 ) {
			$filtered_lines = array_slice( $filtered_lines, -$lines );
		}
		
		return implode( "\n", $filtered_lines );
	}

	/**
	 * Clear log file
	 */
	public static function clear_log() {
		self::init();
		
		if ( file_exists( self::$log_file ) ) {
			wp_delete_file( self::$log_file );
		}
		
		// Also clear backup file
		$backup_file = self::$log_file . '.old';
		if ( file_exists( $backup_file ) ) {
			wp_delete_file( $backup_file );
		}
		
		self::info( 'Log files cleared by user request' );
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
	 * Get log statistics.
	 *
	 * @return array
	 */
	public static function get_log_stats() {
		$content = self::get_log_contents( 0 );
		$lines = explode( "\n", $content );
		
		$stats = array(
			'total_lines' => count( $lines ),
			'file_size' => self::get_log_size(),
			'file_size_formatted' => self::format_bytes( self::get_log_size() ),
			'levels' => array(),
			'latest_entry' => '',
		);
		
		// Count by log level
		foreach ( self::$log_levels as $level => $priority ) {
			$count = 0;
			foreach ( $lines as $line ) {
				if ( strpos( $line, strtoupper( $level ) ) !== false ) {
					$count++;
				}
			}
			$stats['levels'][ $level ] = $count;
		}
		
		// Get latest entry
		if ( ! empty( $lines ) ) {
			$stats['latest_entry'] = trim( end( $lines ) );
		}
		
		return $stats;
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

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private static function get_client_ip() {
		$ip_keys = array(
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR'
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( $_SERVER[ $key ] );
				
				// Handle comma-separated IPs (from proxies)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				
				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';
	}

	/**
	 * Export logs for debugging.
	 *
	 * @return array
	 */
	public static function export_logs() {
		return array(
			'current_log' => self::get_log_contents( 0 ),
			'backup_log' => file_exists( self::$log_file . '.old' ) ? file_get_contents( self::$log_file . '.old' ) : '',
			'stats' => self::get_log_stats(),
			'system_info' => array(
				'wp_version' => get_bloginfo( 'version' ),
				'php_version' => PHP_VERSION,
				'memory_limit' => ini_get( 'memory_limit' ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
				'server_software' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ) : 'Unknown',
			),
		);
	}

	/**
	 * Set minimum log level dynamically.
	 *
	 * @param string $level
	 */
	public static function set_min_log_level( $level ) {
		if ( isset( self::$log_levels[ $level ] ) ) {
			self::$min_log_level = self::$log_levels[ $level ];
		}
	}

	/**
	 * Get current minimum log level.
	 *
	 * @return string
	 */
	public static function get_min_log_level() {
		$current_level = self::$min_log_level ?? self::determine_min_log_level();
		
		foreach ( self::$log_levels as $level => $priority ) {
			if ( $priority === $current_level ) {
				return $level;
			}
		}
		
		return self::INFO;
	}
}