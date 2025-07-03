<?php
/**
 * Compatibility functions for Reign Demo Installer - Enhanced Version
 * 
 * This file maintains backward compatibility with old function names
 * and constants to prevent breaking existing functionality.
 * Enhanced with file missing detection and reduced duplicate logging.
 *
 * @package Reign_Demo_Installer
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ============================================================================
 * CONSTANT MAPPINGS
 * Map old constants to new ones for backward compatibility
 * ============================================================================
 */

// Plugin constants mapping
if ( ! defined( 'WBCOM_Theme_Demo_Installer_PLUGIN_FILE' ) ) {
	define( 'WBCOM_Theme_Demo_Installer_PLUGIN_FILE', REIGN_DEMO_INSTALLER_PLUGIN_FILE );
}

if ( ! defined( 'WBCOM_Theme_Demo_Installer_PLUGIN_BASENAME' ) ) {
	define( 'WBCOM_Theme_Demo_Installer_PLUGIN_BASENAME', REIGN_DEMO_INSTALLER_PLUGIN_BASENAME );
}

if ( ! defined( 'WBCOM_Theme_Demo_Installer_VERSION' ) ) {
	define( 'WBCOM_Theme_Demo_Installer_VERSION', REIGN_DEMO_INSTALLER_VERSION );
}

if ( ! defined( 'WBCOM_Theme_Demo_Installer_TEXT_DOMAIN' ) ) {
	define( 'WBCOM_Theme_Demo_Installer_TEXT_DOMAIN', REIGN_DEMO_INSTALLER_TEXT_DOMAIN );
}

if ( ! defined( 'WBCOM_Theme_Demo_Installer_PLUGIN_DIR_PATH' ) ) {
	define( 'WBCOM_Theme_Demo_Installer_PLUGIN_DIR_PATH', REIGN_DEMO_INSTALLER_PLUGIN_DIR_PATH );
}

if ( ! defined( 'WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL' ) ) {
	define( 'WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL', REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL );
}

if ( ! defined( 'WBCOM_DEMO_INSTALLER_PACKAGE_URL' ) ) {
	define( 'WBCOM_DEMO_INSTALLER_PACKAGE_URL', REIGN_DEMO_INSTALLER_PACKAGE_URL );
}

if ( ! defined( 'WBCOM_DEMO_INSTALLER_PACKAGE_PLUGINS_URL' ) ) {
	define( 'WBCOM_DEMO_INSTALLER_PACKAGE_PLUGINS_URL', REIGN_DEMO_INSTALLER_PACKAGE_PLUGINS_URL );
}

/**
 * ============================================================================
 * FILE DETECTION AND LOGGING
 * Check for missing files and log them only once
 * ============================================================================
 */

/**
 * Check and log missing files once per session
 */
function reign_demo_installer_check_missing_files() {
	static $files_checked = false;
	
	// Only check once per session to avoid duplicate logs
	if ( $files_checked ) {
		return;
	}
	
	$files_checked = true;
	
	$required_files = array(
		'core/class-reign-demo-installer-logger.php',
		'core/class-reign-demo-installer-security.php',
		'core/class-reign-demo-installer-environment.php',
		'core/class-reign-demo-installer-admin-guardian.php',
		'core/admin-settings.php',
		'core/ajax-handler.php',
		'core/plugins-manager.php',
		'core/prerequisites-checks.php',
		'core/success.php',
		'assets/js/importer.js',
		'assets/js/jquery.mixitup.min.js',
		'assets/css/demo-listing.css',
		'languages/',
		'demos/',
		'demo-plugins/',
		'plugin-thumb/',
		'demos-imgs/',
	);
	
	$missing_files = array();
	$plugin_dir = REIGN_DEMO_INSTALLER_PLUGIN_DIR_PATH;
	
	foreach ( $required_files as $file ) {
		$file_path = $plugin_dir . $file;
		
		// For directories, check if they exist
		if ( substr( $file, -1 ) === '/' ) {
			if ( ! is_dir( $file_path ) ) {
				$missing_files[] = array(
					'file' => $file,
					'type' => 'directory',
					'path' => $file_path,
					'critical' => in_array( $file, array( 'core/', 'assets/' ), true )
				);
			}
		} else {
			// For files, check if they exist and are readable
			if ( ! file_exists( $file_path ) ) {
				$missing_files[] = array(
					'file' => $file,
					'type' => 'file',
					'path' => $file_path,
					'critical' => strpos( $file, 'core/' ) === 0
				);
			} elseif ( ! is_readable( $file_path ) ) {
				$missing_files[] = array(
					'file' => $file,
					'type' => 'file',
					'path' => $file_path,
					'critical' => strpos( $file, 'core/' ) === 0,
					'issue' => 'not_readable'
				);
			}
		}
	}
	
	if ( ! empty( $missing_files ) ) {
		_log_missing_files( $missing_files );
	} else if ( class_exists( 'Reign_Demo_Installer_Logger' ) && WP_DEBUG ) {
		Reign_Demo_Installer_Logger::debug( 'All required plugin files found and accessible' );
	}
}

/**
 * Log missing files with detailed information
 *
 * @param array $missing_files Array of missing file information
 */
function _log_missing_files( $missing_files ) {
	$critical_missing = array();
	$non_critical_missing = array();
	
	foreach ( $missing_files as $file_info ) {
		if ( $file_info['critical'] ) {
			$critical_missing[] = $file_info;
		} else {
			$non_critical_missing[] = $file_info;
		}
	}
	
	// Log critical missing files as errors
	if ( ! empty( $critical_missing ) ) {
		$critical_list = array_map( function( $file ) {
			$status = isset( $file['issue'] ) ? ' (' . $file['issue'] . ')' : ' (missing)';
			return $file['file'] . $status;
		}, $critical_missing );
		
		if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
			Reign_Demo_Installer_Logger::error( 
				'Critical plugin files missing or inaccessible: ' . implode( ', ', $critical_list ),
				array( 'missing_files' => $critical_missing )
			);
		}
		
		// Also log to WordPress error log
		error_log( '[Reign Demo Installer] Critical files missing: ' . implode( ', ', $critical_list ) );
	}
	
	// Log non-critical missing files as warnings
	if ( ! empty( $non_critical_missing ) ) {
		$non_critical_list = array_map( function( $file ) {
			$status = isset( $file['issue'] ) ? ' (' . $file['issue'] . ')' : ' (missing)';
			return $file['file'] . $status;
		}, $non_critical_missing );
		
		if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
			Reign_Demo_Installer_Logger::warning( 
				'Optional plugin files missing: ' . implode( ', ', $non_critical_list ),
				array( 'missing_files' => $non_critical_missing )
			);
		}
	}
	
	// Show admin notice for critical missing files
	if ( ! empty( $critical_missing ) ) {
		add_action( 'admin_notices', function() use ( $critical_missing ) {
			$file_list = implode( ', ', array_column( $critical_missing, 'file' ) );
			?>
			<div class="notice notice-error">
				<p>
					<strong>Reign Demo Installer:</strong> 
					Critical files missing or inaccessible: <?php echo esc_html( $file_list ); ?>
					<br>Please reinstall the plugin or check file permissions.
				</p>
			</div>
			<?php
		});
	}
}

/**
 * ============================================================================
 * FUNCTION MAPPINGS
 * Map old function names to new ones for backward compatibility
 * ============================================================================
 */

/**
 * Backward compatibility for main plugin instance function.
 *
 * @return Reign_Demo_Installer
 */
if ( ! function_exists( 'instantiate_wbcom_theme_demo_installer' ) ) {
	function instantiate_wbcom_theme_demo_installer() {
		return reign_demo_installer();
	}
}

/**
 * Get demo installer URL (backward compatibility).
 *
 * @param array $args URL arguments
 * @return string Demo installer URL
 */
if ( ! function_exists( 'get_wbcom_demo_installer_url' ) ) {
	function get_wbcom_demo_installer_url( $args = array() ) {
		if ( class_exists( 'Reign_Demo_Installer_Admin_Settings' ) ) {
			$admin_settings = Reign_Demo_Installer_Admin_Settings::instance();
			return $admin_settings->get_demo_installer_page_url( $args );
		}
		
		$base_url = admin_url( 'admin.php?page=reign-demo-installer' );
		if ( ! empty( $args ) ) {
			$base_url = add_query_arg( $args, $base_url );
		}
		return $base_url;
	}
}

/**
 * ============================================================================
 * AJAX COMPATIBILITY
 * Ensure old AJAX actions still work
 * ============================================================================
 */

/**
 * Register compatibility AJAX actions.
 */
function reign_demo_installer_register_compatibility_ajax() {
	// Map old action names to new handlers
	$ajax_mappings = array(
		'wbcom_get_theme_demo_data' => 'reign_get_theme_demo_data',
		'wbcom_read_theme_demo_package_file' => 'reign_read_theme_demo_package_file',
		'wbcom_manage_plugin_installation' => 'reign_manage_plugin_installation',
	);

	foreach ( $ajax_mappings as $old_action => $new_action ) {
		// Add old action hooks that redirect to new handlers
		add_action( "wp_ajax_{$old_action}", function() use ( $new_action ) {
			do_action( "wp_ajax_{$new_action}" );
		} );
	}
}
add_action( 'init', 'reign_demo_installer_register_compatibility_ajax' );

/**
 * ============================================================================
 * GLOBAL VARIABLE MAPPINGS
 * Maintain old global variable names
 * ============================================================================
 */

/**
 * Set up global variable compatibility.
 */
function reign_demo_installer_setup_globals() {
	global $wbcom_theme_demo_installer, $reign_demo_installer;
	
	// Make old global variable point to new instance
	if ( ! isset( $wbcom_theme_demo_installer ) ) {
		$wbcom_theme_demo_installer = reign_demo_installer();
	}
}
add_action( 'init', 'reign_demo_installer_setup_globals', 5 );

/**
 * ============================================================================
 * OPTION KEY MAPPINGS
 * Map old option keys to new ones
 * ============================================================================
 */

/**
 * Get option with backward compatibility.
 *
 * @param string $option Option name
 * @param mixed $default Default value
 * @return mixed Option value
 */
function reign_demo_installer_get_option( $option, $default = false ) {
	$option_mappings = array(
		'wbcom_theme_demo_import_data' => 'reign_theme_demo_import_data',
		'wbcom_theme_demo_req_plugins' => 'reign_theme_demo_req_plugins',
	);

	if ( isset( $option_mappings[ $option ] ) ) {
		$new_option = $option_mappings[ $option ];
		
		// Try new option first
		$value = get_option( $new_option, null );
		if ( $value !== null ) {
			return $value;
		}
		
		// Fallback to old option and migrate
		$value = get_option( $option, $default );
		if ( $value !== $default ) {
			update_option( $new_option, $value );
			delete_option( $option ); // Clean up old option
		}
		
		return $value;
	}

	return get_option( $option, $default );
}

/**
 * Update option with backward compatibility.
 *
 * @param string $option Option name
 * @param mixed $value Option value
 * @param string|bool $autoload Whether to autoload option
 * @return bool True if successful, false otherwise
 */
function reign_demo_installer_update_option( $option, $value, $autoload = null ) {
	$option_mappings = array(
		'wbcom_theme_demo_import_data' => 'reign_theme_demo_import_data',
		'wbcom_theme_demo_req_plugins' => 'reign_theme_demo_req_plugins',
	);

	if ( isset( $option_mappings[ $option ] ) ) {
		$new_option = $option_mappings[ $option ];
		
		// Update new option
		$result = update_option( $new_option, $value, $autoload );
		
		// Clean up old option
		delete_option( $option );
		
		return $result;
	}

	return update_option( $option, $value, $autoload );
}

/**
 * Delete option with backward compatibility.
 *
 * @param string $option Option name
 * @return bool True if successful, false otherwise
 */
function reign_demo_installer_delete_option( $option ) {
	$option_mappings = array(
		'wbcom_theme_demo_import_data' => 'reign_theme_demo_import_data',
		'wbcom_theme_demo_req_plugins' => 'reign_theme_demo_req_plugins',
	);

	if ( isset( $option_mappings[ $option ] ) ) {
		$new_option = $option_mappings[ $option ];
		
		// Delete both old and new options
		$result1 = delete_option( $option );
		$result2 = delete_option( $new_option );
		
		return $result1 || $result2;
	}

	return delete_option( $option );
}

/**
 * ============================================================================
 * FILTER AND ACTION HOOK MAPPINGS
 * Ensure old hook names still work
 * ============================================================================
 */

/**
 * Map old action hooks to new ones.
 */
function reign_demo_installer_map_action_hooks() {
	$action_mappings = array(
		'wbcom_theme_demo_installer_loaded' => 'reign_demo_installer_loaded',
	);

	foreach ( $action_mappings as $old_hook => $new_hook ) {
		add_action( $new_hook, function() use ( $old_hook ) {
			do_action( $old_hook );
		} );
	}
}
add_action( 'init', 'reign_demo_installer_map_action_hooks', 1 );

/**
 * Map old filter hooks to new ones.
 */
function reign_demo_installer_map_filter_hooks() {
	$filter_mappings = array(
		'wbcom_theme_demo_installer_plugin_locale' => 'reign_demo_installer_plugin_locale',
	);

	foreach ( $filter_mappings as $old_hook => $new_hook ) {
		add_filter( $new_hook, function( $value ) use ( $old_hook ) {
			return apply_filters( $old_hook, $value );
		} );
	}
}
add_action( 'init', 'reign_demo_installer_map_filter_hooks', 1 );

/**
 * ============================================================================
 * CLASS ALIAS COMPATIBILITY
 * Create aliases for old class names
 * ============================================================================
 */

/**
 * Set up class aliases after classes are loaded.
 */
function reign_demo_installer_setup_class_aliases() {
	$class_mappings = array(
		'WBCOM_TDI_ADMIN_SETTINGS' => 'Reign_Demo_Installer_Admin_Settings',
		'WBCOM_Demo_Importer_Ajax_Handler' => 'Reign_Demo_Installer_Ajax_Handler',
		'WBCOM_Demo_Importer_Plugins_Manager' => 'Reign_Demo_Installer_Plugins_Manager',
		'WBCOM_Demo_Importer_PreRequisites_Checker' => 'Reign_Demo_Installer_Environment',
		'WBCOM_Theme_Demo_Installer' => 'Reign_Demo_Installer',
	);

	foreach ( $class_mappings as $old_class => $new_class ) {
		if ( class_exists( $new_class ) && ! class_exists( $old_class ) ) {
			class_alias( $new_class, $old_class );
		}
	}
}
add_action( 'plugins_loaded', 'reign_demo_installer_setup_class_aliases', 20 );

/**
 * ============================================================================
 * JAVASCRIPT LOCALIZATION COMPATIBILITY
 * Ensure old JS variable names still work
 * ============================================================================
 */

/**
 * Add compatibility for old JavaScript variables.
 */
function reign_demo_installer_js_compatibility() {
	if ( is_admin() ) {
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'reign-demo-installer' ) !== false ) {
			?>
			<script type="text/javascript">
			// Backward compatibility for old JS variable names
			if (typeof wbcom_theme_demo_installer_params === 'undefined' && typeof reignDemoInstaller !== 'undefined') {
				var wbcom_theme_demo_installer_params = reignDemoInstaller;
			}
			
			// Map old function names to new ones if needed
			if (typeof _wbcom_read_theme_demo_package_file === 'undefined') {
				window._wbcom_read_theme_demo_package_file = window._reignReadThemeDemoPackageFile || function() {};
			}
			</script>
			<?php
		}
	}
}
add_action( 'admin_footer', 'reign_demo_installer_js_compatibility' );

/**
 * ============================================================================
 * DATABASE TABLE COMPATIBILITY
 * Handle old table names and data migration
 * ============================================================================
 */

/**
 * Migrate old data to new format with reduced logging.
 */
function reign_demo_installer_migrate_data() {
	static $migration_checked = false;
	
	// Only run migration check once per session
	if ( $migration_checked ) {
		return;
	}
	
	$migration_checked = true;
	$migration_version = get_option( 'reign_demo_installer_migration_version', '0' );
	
	if ( version_compare( $migration_version, '3.0.0', '<' ) ) {
		// Migrate old options to new names
		$old_options = array(
			'wbcom_theme_demo_import_data',
			'wbcom_theme_demo_req_plugins',
		);
		
		$migrated_count = 0;
		foreach ( $old_options as $old_option ) {
			$value = get_option( $old_option );
			if ( $value !== false ) {
				$new_option = str_replace( 'wbcom_', 'reign_', $old_option );
				update_option( $new_option, $value );
				$migrated_count++;
				
				// Don't delete old option immediately to allow rollback
				// delete_option( $old_option );
			}
		}
		
		// Update migration version
		update_option( 'reign_demo_installer_migration_version', '3.0.0' );
		
		// Log migration only if something was actually migrated
		if ( $migrated_count > 0 && class_exists( 'Reign_Demo_Installer_Logger' ) ) {
			Reign_Demo_Installer_Logger::info( 
				"Data migration completed from version {$migration_version} to 3.0.0. Migrated {$migrated_count} options." 
			);
		}
	}
}
add_action( 'admin_init', 'reign_demo_installer_migrate_data' );

/**
 * ============================================================================
 * URL MAPPING COMPATIBILITY
 * Ensure old URLs still work
 * ============================================================================
 */

/**
 * Handle old admin page URLs.
 */
function reign_demo_installer_handle_old_urls() {
	if ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] === 'wbcom-theme-demo-installer' ) {
		// Redirect old URL to new URL
		$new_url = admin_url( 'admin.php?page=reign-demo-installer' );
		
		// Preserve query parameters
		$query_params = $_GET;
		unset( $query_params['page'] );
		
		if ( ! empty( $query_params ) ) {
			$new_url = add_query_arg( $query_params, $new_url );
		}
		
		wp_safe_redirect( $new_url, 301 );
		exit;
	}
}
add_action( 'admin_init', 'reign_demo_installer_handle_old_urls', 5 );

/**
 * ============================================================================
 * ERROR HANDLING COMPATIBILITY
 * Ensure old error handling methods still work
 * ============================================================================
 */

/**
 * Legacy error logging function.
 *
 * @param string $message Error message
 * @param string $type Error type
 */
if ( ! function_exists( 'wbcom_theme_demo_installer_log_error' ) ) {
	function wbcom_theme_demo_installer_log_error( $message, $type = 'error' ) {
		if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
			Reign_Demo_Installer_Logger::log( $message, $type );
		} else {
			error_log( '[Reign Demo Installer] ' . $message );
		}
	}
}

/**
 * ============================================================================
 * LOCALIZATION COMPATIBILITY
 * Ensure old text domain still works
 * ============================================================================
 */

/**
 * Load legacy text domain.
 */
function reign_demo_installer_load_legacy_textdomain() {
	static $textdomain_loaded = false;
	
	// Only load once
	if ( $textdomain_loaded ) {
		return;
	}
	
	$textdomain_loaded = true;
	
	// Load old text domain for backward compatibility
	$old_domain = 'wbcom-theme-demo-installer';
	$new_domain = 'reign-demo-installer';
	
	// If translation exists for old domain, load it
	$locale = apply_filters( 'plugin_locale', get_locale(), $old_domain );
	$mo_file = WP_LANG_DIR . '/plugins/' . $old_domain . '-' . $locale . '.mo';
	
	if ( file_exists( $mo_file ) ) {
		load_textdomain( $old_domain, $mo_file );
	}
}
add_action( 'plugins_loaded', 'reign_demo_installer_load_legacy_textdomain' );

/**
 * ============================================================================
 * OPTIMIZED COMPATIBILITY CHECK
 * Reduce duplicate logging by checking compatibility status smartly
 * ============================================================================
 */

/**
 * Check and log compatibility status with smart caching.
 */
function reign_demo_installer_compatibility_check() {
	// Only run on plugin pages and limit frequency
	if ( ! is_admin() ) {
		return;
	}
	
	$screen = get_current_screen();
	if ( ! $screen || strpos( $screen->id, 'reign-demo-installer' ) === false ) {
		return;
	}
	
	// Use transient to cache compatibility check for 1 hour
	$cache_key = 'reign_demo_installer_compatibility_check';
	$cached_result = get_transient( $cache_key );
	
	if ( $cached_result !== false ) {
		return; // Skip if already checked recently
	}
	
	// Check for missing files first
	reign_demo_installer_check_missing_files();
	
	// Only log compatibility status in debug mode and if something is wrong
	if ( class_exists( 'Reign_Demo_Installer_Logger' ) && WP_DEBUG ) {
		$compatibility_status = array(
			'constants_mapped' => defined( 'WBCOM_Theme_Demo_Installer_VERSION' ),
			'functions_mapped' => function_exists( 'instantiate_wbcom_theme_demo_installer' ),
			'classes_aliased' => class_exists( 'WBCOM_Theme_Demo_Installer' ),
			'migration_complete' => get_option( 'reign_demo_installer_migration_version' ) === '3.0.0',
		);
		
		// Only log if there are issues
		$has_issues = in_array( false, $compatibility_status, true );
		
		if ( $has_issues ) {
			Reign_Demo_Installer_Logger::warning( 
				'Compatibility issues detected: ' . wp_json_encode( $compatibility_status ),
				array( 'compatibility_status' => $compatibility_status )
			);
		} else {
			// Only log success once per hour in debug mode
			Reign_Demo_Installer_Logger::debug( 
				'Compatibility status: All checks passed',
				array( 'compatibility_status' => $compatibility_status )
			);
		}
	}
	
	// Cache the result for 1 hour to prevent duplicate checks
	set_transient( $cache_key, true, HOUR_IN_SECONDS );
}
add_action( 'admin_init', 'reign_demo_installer_compatibility_check' );

/**
 * ============================================================================
 * CLEAR CACHE WHEN NEEDED
 * Clear compatibility cache when plugin is updated or files change
 * ============================================================================
 */

/**
 * Clear compatibility cache on plugin update.
 */
function reign_demo_installer_clear_compatibility_cache() {
	delete_transient( 'reign_demo_installer_compatibility_check' );
}
add_action( 'upgrader_process_complete', 'reign_demo_installer_clear_compatibility_cache' );
add_action( 'activated_plugin', 'reign_demo_installer_clear_compatibility_cache' );
add_action( 'deactivated_plugin', 'reign_demo_installer_clear_compatibility_cache' );

/**
 * ============================================================================
 * DEVELOPMENT HELPERS
 * Functions to help with debugging and development
 * ============================================================================
 */

/**
 * Get detailed compatibility report for debugging.
 *
 * @return array Compatibility report
 */
function reign_demo_installer_get_compatibility_report() {
	$report = array(
		'plugin_info' => array(
			'version' => REIGN_DEMO_INSTALLER_VERSION,
			'path' => REIGN_DEMO_INSTALLER_PLUGIN_DIR_PATH,
			'url' => REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL,
		),
		'constants' => array(
			'new_constants_defined' => defined( 'REIGN_DEMO_INSTALLER_VERSION' ),
			'old_constants_mapped' => defined( 'WBCOM_Theme_Demo_Installer_VERSION' ),
		),
		'functions' => array(
			'main_function_exists' => function_exists( 'reign_demo_installer' ),
			'compatibility_function_exists' => function_exists( 'instantiate_wbcom_theme_demo_installer' ),
		),
		'classes' => array(
			'main_class_exists' => class_exists( 'Reign_Demo_Installer' ),
			'compatibility_class_exists' => class_exists( 'WBCOM_Theme_Demo_Installer' ),
		),
		'migration' => array(
			'version' => get_option( 'reign_demo_installer_migration_version', '0' ),
			'complete' => get_option( 'reign_demo_installer_migration_version' ) === '3.0.0',
		),
		'files' => array(),
		'timestamp' => current_time( 'mysql' ),
	);
	
	// Check file existence
	$required_files = array(
		'core/admin-settings.php',
		'core/ajax-handler.php',
		'core/plugins-manager.php',
		'assets/js/importer.js',
	);
	
	foreach ( $required_files as $file ) {
		$file_path = REIGN_DEMO_INSTALLER_PLUGIN_DIR_PATH . $file;
		$report['files'][ $file ] = array(
			'exists' => file_exists( $file_path ),
			'readable' => file_exists( $file_path ) && is_readable( $file_path ),
			'size' => file_exists( $file_path ) ? filesize( $file_path ) : 0,
		);
	}
	
	return $report;
}

/**
 * Debug function to output compatibility report.
 */
function reign_demo_installer_debug_compatibility() {
	if ( ! current_user_can( 'manage_options' ) || ! WP_DEBUG ) {
		return;
	}
	
	$report = reign_demo_installer_get_compatibility_report();
	
	echo '<pre>';
	echo 'Reign Demo Installer Compatibility Report:' . PHP_EOL;
	echo wp_json_encode( $report, JSON_PRETTY_PRINT );
	echo '</pre>';
}

// Add debug action for administrators
if ( WP_DEBUG && current_user_can( 'manage_options' ) ) {
	add_action( 'wp_ajax_reign_debug_compatibility', 'reign_demo_installer_debug_compatibility' );
}