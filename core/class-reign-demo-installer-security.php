<?php
/**
 * Security class for Reign Demo Installer - Enhanced Version
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
	 * Rate limit transient prefix.
	 *
	 * @var string
	 */
	private $rate_limit_prefix = 'reign_demo_installer_rate_limit_';

	/**
	 * Maximum requests per time window.
	 *
	 * @var int
	 */
	private $max_requests = 10;

	/**
	 * Time window in seconds.
	 *
	 * @var int
	 */
	private $time_window = 300; // 5 minutes

	/**
	 * Allowed domains for external requests.
	 *
	 * @var array
	 */
	private $allowed_domains = array(
		'wbcomdesigns.com',
		'installer.wbcomdesigns.com',
		'wordpress.org',
		'downloads.wordpress.org',
		'api.wordpress.org'
	);

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
		// Hook into AJAX actions for security checks
		$ajax_actions = array(
			'reign_get_theme_demo_data',
			'reign_read_theme_demo_package_file',
			'reign_manage_plugin_installation',
			'wbcom_get_theme_demo_data',
			'wbcom_read_theme_demo_package_file',
			'wbcom_manage_plugin_installation'
		);

		foreach ( $ajax_actions as $action ) {
			add_action( "wp_ajax_{$action}", array( $this, 'pre_ajax_security_check' ), 1 );
		}

		// Additional security hooks
		add_action( 'wp_ajax_heartbeat', array( $this, 'handle_heartbeat_security' ), 1 );
	}

	/**
	 * Pre-AJAX security check.
	 */
	public function pre_ajax_security_check() {
		// Rate limiting
		if ( ! $this->check_rate_limit() ) {
			$this->security_die( 'Too many requests. Please wait before trying again.', 429 );
		}

		// User capabilities
		if ( ! $this->check_user_capabilities() ) {
			$this->security_die( 'Insufficient permissions to perform this action.', 403 );
		}

		// Nonce verification
		if ( ! $this->verify_ajax_nonce() ) {
			$this->security_die( 'Invalid security token. Please refresh the page and try again.', 403 );
		}

		// Request source validation
		if ( ! $this->is_valid_request_source() ) {
			$this->security_die( 'Invalid request source.', 403 );
		}
	}

	/**
	 * Handle heartbeat security.
	 */
	public function handle_heartbeat_security() {
		// Allow heartbeat with basic rate limiting
		if ( ! $this->check_rate_limit( 30, 60 ) ) { // 30 requests per minute
			wp_die( 'Heartbeat rate limit exceeded.', '', array( 'response' => 429 ) );
		}
	}

	/**
	 * Verify AJAX nonce.
	 *
	 * @return bool
	 */
	private function verify_ajax_nonce() {
		$nonce = $this->get_request_param( 'nonce', 'string' );
		
		if ( empty( $nonce ) ) {
			return false;
		}

		$valid_actions = array(
			'reign_demo_installer_ajax',
			'reign_demo_installer_import',
			'reign_demo_installer_plugins',
			REIGN_DEMO_INSTALLER_NONCE_KEY
		);

		foreach ( $valid_actions as $action ) {
			if ( wp_verify_nonce( $nonce, $action ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check user capabilities.
	 */
	public function check_user_capabilities() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Check rate limiting.
	 *
	 * @param int $max_requests Maximum requests allowed
	 * @param int $time_window Time window in seconds
	 * @return bool
	 */
	public function check_rate_limit( $max_requests = null, $time_window = null ) {
		$max_requests = $max_requests ?? $this->max_requests;
		$time_window = $time_window ?? $this->time_window;
		
		$user_id = get_current_user_id();
		$ip_address = $this->get_client_ip();
		$transient_key = $this->rate_limit_prefix . md5( $user_id . '_' . $ip_address );
		
		$attempts = get_transient( $transient_key );
		
		if ( $attempts && $attempts >= $max_requests ) {
			$this->log_security_event( 
				"Rate limit exceeded for user {$user_id} from IP {$ip_address}", 
				'warning',
				array( 'attempts' => $attempts, 'limit' => $max_requests )
			);
			return false;
		}

		$attempts = $attempts ? $attempts + 1 : 1;
		set_transient( $transient_key, $attempts, $time_window );

		return true;
	}

	/**
	 * Sanitize and validate request parameters.
	 *
	 * @param string $key Parameter key
	 * @param string $type Expected type
	 * @param mixed $default Default value
	 * @return mixed Sanitized value
	 */
	public function get_request_param( $key, $type = 'string', $default = null ) {
		$value = null;

		// Check both POST and GET with POST taking precedence
		if ( isset( $_POST[ $key ] ) ) {
			$value = wp_unslash( $_POST[ $key ] );
		} elseif ( isset( $_GET[ $key ] ) ) {
			$value = wp_unslash( $_GET[ $key ] );
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
				if ( ! is_array( $value ) ) {
					return array();
				}
				return array_map( 'sanitize_text_field', $value );
			
			case 'slug':
				return sanitize_title( $value );
			
			case 'filename':
				return sanitize_file_name( $value );
			
			case 'html':
				return wp_kses_post( $value );
			
			case 'textarea':
				return sanitize_textarea_field( $value );
			
			case 'key':
				return sanitize_key( $value );
			
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
		$parsed_url = wp_parse_url( $url );
		
		if ( ! isset( $parsed_url['host'] ) ) {
			return false;
		}

		$host = strtolower( $parsed_url['host'] );
		
		foreach ( $this->allowed_domains as $domain ) {
			if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
				return true;
			}
		}

		$this->log_security_event( 
			"Blocked request to unauthorized domain: {$host}",
			'warning',
			array( 'url' => $url, 'allowed_domains' => $this->allowed_domains )
		);

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
		$is_valid = in_array( $ext, $allowed_extensions, true );
		
		if ( ! $is_valid ) {
			$this->log_security_event( 
				"Blocked file with unauthorized extension: {$ext}",
				'warning',
				array( 'filename' => $filename, 'allowed_extensions' => $allowed_extensions )
			);
		}
		
		return $is_valid;
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
	 * @param array $context Additional context
	 */
	public function log_security_event( $message, $type = 'warning', $context = array() ) {
		$user_id = get_current_user_id();
		$ip_address = $this->get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

		$enhanced_context = array_merge( $context, array(
			'user_id' => $user_id,
			'ip_address' => $ip_address,
			'user_agent' => $user_agent,
			'timestamp' => current_time( 'mysql' ),
			'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : ''
		));

		$log_message = sprintf(
			'[SECURITY] %s',
			$message
		);

		if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
			Reign_Demo_Installer_Logger::log( $log_message, $type, $enhanced_context );
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
				
				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';
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
		
		if ( wp_doing_ajax() ) {
			wp_send_json_error( array( 'message' => $message ), $code );
		} else {
			wp_die(
				esc_html( $message ),
				esc_html__( 'Security Error', 'reign-demo-installer' ),
				array( 'response' => $code )
			);
		}
	}

	/**
	 * Check if request is from valid source.
	 *
	 * @return bool True if valid, false otherwise
	 */
	public function is_valid_request_source() {
		// Must be admin request
		if ( ! is_admin() ) {
			return false;
		}

		// Check if AJAX request
		if ( wp_doing_ajax() ) {
			// Verify referer for AJAX requests
			if ( ! check_ajax_referer( '', '', false ) ) {
				// Allow if referer is from admin area
				$referer = wp_get_referer();
				if ( ! $referer || strpos( $referer, admin_url() ) !== 0 ) {
					return false;
				}
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

		// Validate theme slug format
		if ( ! preg_match( '/^[a-z0-9\-_]+$/i', $data['theme_slug'] ) ) {
			return new WP_Error( 'invalid_theme_slug', 'Invalid theme slug format.' );
		}

		// Validate demo slug format
		if ( ! preg_match( '/^[a-z0-9\-_]+$/i', $data['demo_slug'] ) ) {
			return new WP_Error( 'invalid_demo_slug', 'Invalid demo slug format.' );
		}

		// Validate target URL
		if ( ! $this->validate_url_domain( $data['target_url'] ) ) {
			return new WP_Error( 'invalid_domain', 'URL domain is not allowed.' );
		}

		return true;
	}

	/**
	 * Clean up temporary files securely.
	 *
	 * @param string $directory Directory to clean
	 * @param int $max_age Maximum age in seconds (default 1 hour)
	 */
	public function cleanup_temp_files( $directory, $max_age = 3600 ) {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$files = glob( $directory . '/*' );
		$cleaned_count = 0;
		
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				// Only delete files older than max age
				if ( filemtime( $file ) < time() - $max_age ) {
					if ( wp_delete_file( $file ) ) {
						$cleaned_count++;
					}
				}
			}
		}

		if ( $cleaned_count > 0 ) {
			$this->log_security_event( 
				"Cleaned up {$cleaned_count} temporary files from {$directory}",
				'info'
			);
		}
	}

	/**
	 * Add allowed domain.
	 *
	 * @param string $domain Domain to allow
	 */
	public function add_allowed_domain( $domain ) {
		if ( ! in_array( $domain, $this->allowed_domains, true ) ) {
			$this->allowed_domains[] = $domain;
		}
	}

	/**
	 * Remove allowed domain.
	 *
	 * @param string $domain Domain to remove
	 */
	public function remove_allowed_domain( $domain ) {
		$key = array_search( $domain, $this->allowed_domains, true );
		if ( $key !== false ) {
			unset( $this->allowed_domains[ $key ] );
			$this->allowed_domains = array_values( $this->allowed_domains );
		}
	}

	/**
	 * Get allowed domains.
	 *
	 * @return array
	 */
	public function get_allowed_domains() {
		return $this->allowed_domains;
	}

	/**
	 * Reset rate limit for user.
	 *
	 * @param int $user_id User ID (optional, defaults to current user)
	 */
	public function reset_rate_limit( $user_id = null ) {
		$user_id = $user_id ?? get_current_user_id();
		$ip_address = $this->get_client_ip();
		$transient_key = $this->rate_limit_prefix . md5( $user_id . '_' . $ip_address );
		
		delete_transient( $transient_key );
		
		$this->log_security_event( 
			"Rate limit reset for user {$user_id}",
			'info'
		);
	}
}

// Initialize security
Reign_Demo_Installer_Security::instance();