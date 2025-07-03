<?php
/**
 * Security class for Reign Demo Installer
 *
 * @package Reign_Demo_Installer
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reign_Demo_Installer_Security class.
 */
class Reign_Demo_Installer_Security {

	/**
	 * The single instance of the class.
	 *
	 * @var Reign_Demo_Installer_Security
	 */
	protected static $_instance = null;

	/**
	 * Main instance.
	 *
	 * @return Reign_Demo_Installer_Security
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
	 * Initialize security measures.
	 */
	public function init() {
		// Rate limiting
		add_action( 'wp_ajax_reign_demo_installer_action', array( $this, 'check_rate_limit' ), 1 );
		
		// CSRF protection
		add_action( 'wp_ajax_reign_demo_installer_action', array( $this, 'verify_csrf_token' ), 2 );
		
		// Capability check
		add_action( 'wp_ajax_reign_demo_installer_action', array( $this, 'check_user_capabilities' ), 3 );
	}

	/**
	 * Verify CSRF token.
	 */
	public function verify_csrf_token() {
		$nonce = $this->get_request_param( 'nonce', 'string' );
		
		if ( ! wp_verify_nonce( $nonce, REIGN_DEMO_INSTALLER_NONCE_KEY ) ) {
			$this->security_die( 'Invalid security token. Please refresh the page and try again.' );
		}
	}

	/**
	 * Check user capabilities.
	 */
	public function check_user_capabilities() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->security_die( 'Insufficient permissions to perform this action.' );
		}
	}

	/**
	 * Check rate limiting.
	 */
	public function check_rate_limit() {
		$user_id = get_current_user_id();
		$transient_key = 'reign_demo_installer_rate_limit_' . $user_id;
		$attempts = get_transient( $transient_key );

		if ( $attempts && $attempts >= 5 ) {
			$this->security_die( 'Too many requests. Please wait before trying again.' );
		}

		$attempts = $attempts ? $attempts + 1 : 1;
		set_transient( $transient_key, $attempts, MINUTE_IN_SECONDS * 5 );
	}

	/**
	 * Sanitize and validate request parameters.
	 *
	 * @param string $key Parameter key
	 * @param string $type Expected type (string, int, url, email, etc.)
	 * @param mixed $default Default value
	 * @return mixed Sanitized value
	 */
	public function get_request_param( $key, $type = 'string', $default = null ) {
		$value = null;

		// Check both POST and GET
		if ( isset( $_POST[ $key ] ) ) {
			$value = $_POST[ $key ];
		} elseif ( isset( $_GET[ $key ] ) ) {
			$value = $_GET[ $key ];
		}

		if ( is_null( $value ) ) {
			return $default;
		}

		return $this->sanitize_value( $value, $type );
	}

	/**
	 * Sanitize value based on type.
	 *
	 * @param mixed $value Value to sanitize
	 * @param string $type Type of sanitization
	 * @return mixed Sanitized value
	 */
	public function sanitize_value( $value, $type ) {
		switch ( $type ) {
			case 'string':
				return sanitize_text_field( $value );
			
			case 'email':
				return sanitize_email( $value );
			
			case 'url':
				return esc_url_raw( $value );
			
			case 'int':
				return intval( $value );
			
			case 'float':
				return floatval( $value );
			
			case 'bool':
				return (bool) $value;
			
			case 'array':
				return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
			
			case 'slug':
				return sanitize_title( $value );
			
			case 'filename':
				return sanitize_file_name( $value );
			
			case 'html':
				return wp_kses_post( $value );
			
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Validate URL against allowed domains.
	 *
	 * @param string $url URL to validate
	 * @return bool True if valid, false otherwise
	 */
	public function validate_url_domain( $url ) {
		$allowed_domains = array(
			'wbcomdesigns.com',
			'installer.wbcomdesigns.com',
			'wordpress.org',
			'downloads.wordpress.org'
		);

		$parsed_url = wp_parse_url( $url );
		
		if ( ! isset( $parsed_url['host'] ) ) {
			return false;
		}

		$host = strtolower( $parsed_url['host'] );
		
		foreach ( $allowed_domains as $domain ) {
			if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate file extension.
	 *
	 * @param string $filename Filename to validate
	 * @param array $allowed_extensions Allowed extensions
	 * @return bool True if valid, false otherwise
	 */
	public function validate_file_extension( $filename, $allowed_extensions = array( 'zip', 'json', 'xml' ) ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		return in_array( $ext, $allowed_extensions, true );
	}

	/**
	 * Clean up temporary files.
	 *
	 * @param string $directory Directory to clean
	 */
	public function cleanup_temp_files( $directory ) {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$files = glob( $directory . '/*' );
		
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				// Only delete files older than 1 hour
				if ( filemtime( $file ) < time() - HOUR_IN_SECONDS ) {
					wp_delete_file( $file );
				}
			}
		}
	}

	/**
	 * Generate secure nonce.
	 *
	 * @param string $action Action name
	 * @return string Nonce value
	 */
	public function generate_nonce( $action = REIGN_DEMO_INSTALLER_NONCE_KEY ) {
		return wp_create_nonce( $action );
	}

	/**
	 * Verify nonce.
	 *
	 * @param string $nonce Nonce to verify
	 * @param string $action Action name
	 * @return bool True if valid, false otherwise
	 */
	public function verify_nonce( $nonce, $action = REIGN_DEMO_INSTALLER_NONCE_KEY ) {
		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Log security events.
	 *
	 * @param string $message Log message
	 * @param string $type Event type
	 */
	public function log_security_event( $message, $type = 'warning' ) {
		$user_id = get_current_user_id();
		$ip_address = $this->get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

		$log_message = sprintf(
			'[SECURITY] %s | User ID: %d | IP: %s | User Agent: %s',
			$message,
			$user_id,
			$ip_address,
			$user_agent
		);

		if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
			Reign_Demo_Installer_Logger::log( $log_message, $type );
		}
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address
	 */
	public function get_client_ip() {
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
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
	}

	/**
	 * Security die with proper logging.
	 *
	 * @param string $message Error message
	 * @param int $code HTTP status code
	 */
	public function security_die( $message, $code = 403 ) {
		$this->log_security_event( $message, 'error' );
		
		status_header( $code );
		
		wp_die(
			esc_html( $message ),
			esc_html__( 'Security Error', 'reign-demo-installer' ),
			array( 'response' => $code )
		);
	}

	/**
	 * Check if request is from valid source.
	 *
	 * @return bool True if valid, false otherwise
	 */
	public function is_valid_request_source() {
		// Check if request is from admin area
		if ( ! is_admin() ) {
			return false;
		}

		// Check referer
		if ( ! wp_verify_nonce( wp_get_referer(), 'wp_rest' ) && ! check_admin_referer() ) {
			$referer = wp_get_referer();
			if ( $referer && strpos( $referer, admin_url() ) !== 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate demo import data.
	 *
	 * @param array $data Import data
	 * @return bool|WP_Error True if valid, WP_Error if not
	 */
	public function validate_import_data( $data ) {
		// Check required fields
		$required_fields = array( 'theme_slug', 'demo_slug', 'target_url' );
		
		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) || empty( $data[ $field ] ) ) {
				return new WP_Error( 
					'missing_field', 
					sprintf( 'Required field "%s" is missing.', $field ) 
				);
			}
		}

		// Validate theme slug
		if ( ! preg_match( '/^[a-z0-9\-_]+$/i', $data['theme_slug'] ) ) {
			return new WP_Error( 'invalid_theme_slug', 'Invalid theme slug format.' );
		}

		// Validate demo slug
		if ( ! preg_match( '/^[a-z0-9\-_]+$/i', $data['demo_slug'] ) ) {
			return new WP_Error( 'invalid_demo_slug', 'Invalid demo slug format.' );
		}

		// Validate target URL
		if ( ! $this->validate_url_domain( $data['target_url'] ) ) {
			return new WP_Error( 'invalid_domain', 'URL domain is not allowed.' );
		}

		return true;
	}
}

// Initialize security
Reign_Demo_Installer_Security::instance();