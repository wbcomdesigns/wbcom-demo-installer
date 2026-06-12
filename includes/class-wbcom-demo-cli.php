<?php
/**
 * WP-CLI commands for the Wbcom Theme Demo Installer.
 *
 * Reuses the same internals as the admin AJAX flow:
 * - WBCOM_Demo_Importer_Ajax_Handler for manifest fetch, table/uploads import and finalize.
 * - WBCOM_Demo_Importer_Plugins_Manager (+ TGMPA) for plugin install/activate.
 *
 * @package WBCOM_Theme_Demo_Installer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

/**
 * Install Wbcom theme demos from the command line.
 *
 * ## EXAMPLES
 *
 *     # List all available demos.
 *     $ wp wbcom-demo list
 *
 *     # Show the plugin stack for a demo.
 *     $ wp wbcom-demo plugins reign-dokan
 *
 *     # Run the full demo import.
 *     $ wp wbcom-demo install reign-dokan --yes
 */
class WBCOM_Demo_CLI {

	/**
	 * List all available demos.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wbcom-demo list
	 *     wp wbcom-demo list --format=json
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$demos  = $this->get_demos();

		$items = array();
		foreach ( $demos as $demo ) {
			$stack    = $this->get_stack( $demo['plugins_json_key'] );
			$required = 0;
			if ( is_array( $stack ) ) {
				foreach ( $stack as $plugin ) {
					if ( ! empty( $plugin['required'] ) ) {
						++$required;
					}
				}
			}

			$items[] = array(
				'key'              => $demo['plugins_json_key'],
				'name'             => $demo['demo_name'],
				'motive'           => trim( $demo['motive_key'] ),
				'required_plugins' => $required,
				'target_url'       => $demo['target_url'],
			);
		}

		WP_CLI\Utils\format_items( $format, $items, array( 'key', 'name', 'motive', 'required_plugins', 'target_url' ) );
	}

	/**
	 * Show (and optionally install/activate) the plugin stack of a demo.
	 *
	 * Without flags this is a dry-run listing. With flags, the required plugins
	 * are installed/activated through the same plugins-manager logic the admin
	 * AJAX path uses (wordpress.org slugs and external/bundled zips alike).
	 *
	 * ## OPTIONS
	 *
	 * <demo-key>
	 * : The demo key as shown by `wp wbcom-demo list` (plugins_json_key).
	 *
	 * [--install]
	 * : Install (and activate) the required plugins that are not installed yet.
	 *
	 * [--activate]
	 * : Activate required plugins that are installed but inactive.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wbcom-demo plugins reign-dokan
	 *     wp wbcom-demo plugins reign-dokan --install --activate
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function plugins( $args, $assoc_args ) {
		$demo_key = $args[0];
		$demo     = $this->find_demo( $demo_key );
		$stack    = $this->get_stack( $demo_key );

		if ( ! is_array( $stack ) || empty( $stack ) ) {
			WP_CLI::error( sprintf( 'Could not load the plugin stack for "%s".', $demo_key ) );
		}

		$do_install  = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'install', false );
		$do_activate = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'activate', false );

		if ( $do_install || $do_activate ) {
			$this->ensure_admin_context();
		}

		$manager = $this->prepare_plugins_manager( $stack );

		WP_CLI::log( sprintf( 'Demo: %s (%s)', $demo['demo_name'], $demo_key ) );

		if ( $do_install || $do_activate ) {
			$this->process_plugin_stack( $manager, $stack, $do_install, $do_activate );
		}

		$items = array();
		foreach ( $stack as $plugin ) {
			$status  = $manager->get_plugin_status( $plugin['slug'] );
			$items[] = array(
				'name'     => $plugin['name'],
				'slug'     => $plugin['slug'],
				'required' => ! empty( $plugin['required'] ) ? 'yes' : 'no',
				'source'   => $this->get_plugin_source_label( $plugin ),
				'status'   => $status['status_text'],
			);
		}

		WP_CLI\Utils\format_items( 'table', $items, array( 'name', 'slug', 'required', 'source', 'status' ) );

		if ( ! $do_install && ! $do_activate ) {
			WP_CLI::log( 'Dry run. Use --install and/or --activate to apply the required plugin stack.' );
		}
	}

	/**
	 * Run the full demo import pipeline for a demo.
	 *
	 * Mirrors the admin UI flow: required plugin stack, demo package manifest
	 * from the demo server, database table + uploads import, then finalize
	 * (URL search-replace, BuddyPress users/components, cache + permalink flush).
	 *
	 * Intended for fresh installations only. Take a full backup first.
	 * Not compatible with multisite.
	 *
	 * ## OPTIONS
	 *
	 * <demo-key>
	 * : The demo key as shown by `wp wbcom-demo list` (plugins_json_key).
	 *
	 * [--skip-plugins]
	 * : Skip the required plugin install/activate step.
	 *
	 * [--skip-media]
	 * : Skip downloading and extracting the demo uploads (media) archives.
	 *
	 * [--yes]
	 * : Answer yes to the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wbcom-demo install reign-dokan --yes
	 *     wp wbcom-demo install reign-buddyboss-default --skip-media --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function install( $args, $assoc_args ) {
		$demo_key = $args[0];
		$demo     = $this->find_demo( $demo_key );

		if ( is_multisite() ) {
			WP_CLI::error( 'The demo importer is not compatible with WordPress Multisite installations.' );
		}

		WP_CLI::log( sprintf( 'Demo:   %s (%s)', $demo['demo_name'], $demo_key ) );
		WP_CLI::log( sprintf( 'Source: %s', $demo['target_url'] ) );

		WP_CLI::confirm(
			'This will import demo content into the current site (database tables are cleared and repopulated). Intended for fresh installations only - take a full backup first. Proceed?',
			$assoc_args
		);

		$this->ensure_admin_context();

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Long-running import, mirrors the chunked AJAX flow.
		@set_time_limit( 0 );

		$handler = WBCOM_Demo_Importer_Ajax_Handler::instance();
		$stack   = $this->get_stack( $demo_key );

		// Step 1: required plugin stack (same logic as the Manage Plugins step).
		if ( ! WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-plugins', false ) ) {
			if ( ! is_array( $stack ) || empty( $stack ) ) {
				WP_CLI::error( sprintf( 'Could not load the plugin stack for "%s". Use --skip-plugins to import anyway.', $demo_key ) );
			}

			WP_CLI::log( 'Step 1/4: Installing and activating the required plugin stack...' );
			$manager = $this->prepare_plugins_manager( $stack );
			$blocked = $this->process_plugin_stack( $manager, $stack, true, true );

			if ( ! empty( $blocked ) ) {
				WP_CLI::error(
					sprintf(
						'Required premium plugin(s) need manual installation: %s. Install them, then re-run with --skip-plugins.',
						implode( ', ', $blocked )
					)
				);
			}
		} else {
			WP_CLI::log( 'Step 1/4: Skipped plugin stack (--skip-plugins).' );
		}

		// Reset import state, mirroring a fresh visit to the demo listing page.
		delete_option( 'wbcom_theme_demo_import_data' );
		if ( is_array( $stack ) && ! empty( $stack ) ) {
			update_option( 'wbcom_theme_demo_req_plugins', $stack );
		}

		// Step 2: fetch the demo package manifest from the demo server.
		WP_CLI::log( 'Step 2/4: Fetching demo package manifest...' );
		$manifest = $handler->fetch_demo_package_manifest( $demo['theme_slug'], $demo['demo_slug'], $demo['target_url'] );
		if ( is_wp_error( $manifest ) ) {
			WP_CLI::error( $manifest->get_error_message() );
		}

		$package         = $manifest['data'];
		$database_tables = ( isset( $package['database_tables'] ) && is_array( $package['database_tables'] ) ) ? $package['database_tables'] : array();
		$upload_folders  = ( isset( $package['upload_folders'] ) && is_array( $package['upload_folders'] ) ) ? $package['upload_folders'] : array();

		WP_CLI::log( sprintf( 'Package contains %d database table file(s) and %d uploads archive(s).', count( $database_tables ), count( $upload_folders ) ) );

		// Convert wp_die() inside the shared import internals into catchable
		// exceptions so one failed file does not abort the run (the AJAX flow
		// also continues with the next file on error).
		$die_handler_filter = static function () {
			return static function ( $message ) {
				if ( is_wp_error( $message ) ) {
					$message = $message->get_error_message();
				}
				throw new RuntimeException( is_scalar( $message ) ? (string) $message : 'Import step failed.' );
			};
		};
		add_filter( 'wp_die_handler', $die_handler_filter, 100 );

		$failures = 0;

		// Step 3a: database tables.
		WP_CLI::log( 'Step 3/4: Importing demo content...' );
		$total = count( $database_tables );
		foreach ( $database_tables as $index => $url_to_request ) {
			$table_name = $handler->get_table_name_from_file_url( $url_to_request );
			$file_name  = basename( (string) wp_parse_url( $url_to_request, PHP_URL_PATH ) );
			WP_CLI::log( sprintf( '  [%d/%d] Importing %s (table: %s)', $index + 1, $total, $file_name, $table_name ) );

			if ( empty( $table_name ) ) {
				WP_CLI::warning( sprintf( 'Skipped %s: invalid table name.', $url_to_request ) );
				++$failures;
				continue;
			}

			try {
				$handler->clone_database_table( $table_name, $url_to_request );
			} catch ( Exception $e ) {
				WP_CLI::warning( sprintf( 'Failed importing %s: %s', $file_name, $e->getMessage() ) );
				++$failures;
			}
		}

		// Step 3b: uploads (media) archives.
		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-media', false ) ) {
			WP_CLI::log( '  Skipped uploads archives (--skip-media).' );
		} else {
			$total = count( $upload_folders );
			foreach ( $upload_folders as $index => $url_to_request ) {
				$file_name = basename( (string) wp_parse_url( $url_to_request, PHP_URL_PATH ) );
				WP_CLI::log( sprintf( '  [%d/%d] Downloading uploads archive %s', $index + 1, $total, $file_name ) );

				try {
					$handler->clone_uploads_folder( $url_to_request );
				} catch ( Exception $e ) {
					WP_CLI::warning( sprintf( 'Failed extracting %s: %s', $file_name, $e->getMessage() ) );
					++$failures;
				}
			}
		}

		// Step 4: finalize - URL search-replace, BuddyPress users, caches, permalinks.
		WP_CLI::log( 'Step 4/4: Finalizing import (URL search-replace, BuddyPress users, caches)...' );
		try {
			$handler->finalize_import( $demo['target_url'] );
		} catch ( Exception $e ) {
			remove_filter( 'wp_die_handler', $die_handler_filter, 100 );
			WP_CLI::error( sprintf( 'Finalize failed: %s', $e->getMessage() ) );
		}

		remove_filter( 'wp_die_handler', $die_handler_filter, 100 );

		$this->run_success_cleanup();

		if ( $failures > 0 ) {
			WP_CLI::error( sprintf( 'Demo import for "%s" completed with %d failed step(s). Review the warnings above.', $demo_key, $failures ) );
		}

		WP_CLI::success( sprintf( 'Demo "%s" imported successfully.', $demo['demo_name'] ) );
	}

	/**
	 * Show the active theme and which demo plugin stacks are currently satisfied.
	 *
	 * The installer does not persist a record of completed imports, so this
	 * reports the active theme, any in-progress import state, and the per-demo
	 * required plugin stack status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wbcom-demo status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ) {
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		WP_CLI::log( sprintf( 'Active theme: %s %s%s', $theme->get( 'Name' ), $theme->get( 'Version' ), $parent ? sprintf( ' (child of %s)', $parent->get( 'Name' ) ) : '' ) );

		$import_state = get_option( 'wbcom_theme_demo_import_data', array() );
		if ( ! empty( $import_state ) ) {
			WP_CLI::warning( sprintf( 'A demo import appears to be in progress or incomplete (%d table(s) cleared and re-imported).', count( $import_state ) ) );
		} else {
			WP_CLI::log( 'No demo import in progress. Note: the installer does not record completed imports.' );
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$manager = instantiate_wbcom_demo_importer_plugins_manager();
		$demos   = $this->get_demos();
		$items   = array();

		foreach ( $demos as $demo ) {
			$stack = $this->get_stack( $demo['plugins_json_key'] );
			if ( ! is_array( $stack ) ) {
				continue;
			}

			$required = 0;
			$active   = 0;
			foreach ( $stack as $plugin ) {
				if ( empty( $plugin['required'] ) ) {
					continue;
				}
				++$required;

				$file_path = $manager->_get_plugin_file_path_from_slug( $plugin['slug'] );
				if ( is_plugin_active( $file_path ) ) {
					++$active;
				}
			}

			$items[] = array(
				'key'             => $demo['plugins_json_key'],
				'name'            => $demo['demo_name'],
				'required_active' => sprintf( '%d/%d', $active, $required ),
				'stack_satisfied' => ( $required > 0 && $active === $required ) ? 'yes' : 'no',
			);
		}

		WP_CLI\Utils\format_items( 'table', $items, array( 'key', 'name', 'required_active', 'stack_satisfied' ) );
	}

	/**
	 * Read and decode the bundled demos.json listing.
	 *
	 * @return array The decoded demo list.
	 */
	private function get_demos() {
		$demos_file = WBCOM_Theme_Demo_Installer_PLUGIN_DIR_PATH . 'demos/demos.json';

		if ( ! file_exists( $demos_file ) ) {
			WP_CLI::error( sprintf( 'Demo listing not found at %s.', $demos_file ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled file.
		$demos = json_decode( (string) file_get_contents( $demos_file ), true );

		if ( empty( $demos ) || ! is_array( $demos ) ) {
			WP_CLI::error( 'Demo listing could not be decoded.' );
		}

		return $demos;
	}

	/**
	 * Find a demo by its plugins_json_key.
	 *
	 * @param string $demo_key The demo key.
	 * @return array The demo entry. Exits with an error when unknown.
	 */
	private function find_demo( $demo_key ) {
		foreach ( $this->get_demos() as $demo ) {
			if ( isset( $demo['plugins_json_key'] ) && $demo['plugins_json_key'] === $demo_key ) {
				return $demo;
			}
		}

		WP_CLI::error( sprintf( 'Unknown demo key "%s". Run `wp wbcom-demo list` to see available demos.', $demo_key ) );
	}

	/**
	 * Load the plugin stack for a demo key via the shared handler logic.
	 *
	 * @param string $demo_key The demo key.
	 * @return array|false The plugin stack or false.
	 */
	private function get_stack( $demo_key ) {
		return WBCOM_Demo_Importer_Ajax_Handler::instance()->get_demo_plugins_list( $demo_key );
	}

	/**
	 * Prepare the plugins manager for CLI use.
	 *
	 * Loads the wp-admin includes the AJAX context gets for free and registers
	 * the demo plugin stack with TGMPA so installed/active checks resolve.
	 *
	 * @param array $stack The demo plugin stack.
	 * @return WBCOM_Demo_Importer_Plugins_Manager The prepared manager.
	 */
	private function prepare_plugins_manager( $stack ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$manager = instantiate_wbcom_demo_importer_plugins_manager();

		if ( ! is_object( $manager->tgmpa ) ) {
			$manager->populate_plugins();
		}

		$keyed = array();
		foreach ( $stack as $plugin ) {
			$keyed[ $plugin['slug'] ] = $plugin;
			$manager->tgmpa->register( $plugin );
		}
		$manager->tgmpa->populate_file_path();
		$manager->plugins = $keyed;

		return $manager;
	}

	/**
	 * Install/activate the required plugins of a stack via the shared manager logic.
	 *
	 * @param WBCOM_Demo_Importer_Plugins_Manager $manager     The prepared plugins manager.
	 * @param array                               $stack       The demo plugin stack.
	 * @param bool                                $do_install  Whether to install missing required plugins.
	 * @param bool                                $do_activate Whether to activate inactive required plugins.
	 * @return array Slugs of required premium plugins that need manual installation.
	 */
	private function process_plugin_stack( $manager, $stack, $do_install, $do_activate ) {
		$blocked = array();

		foreach ( $stack as $plugin ) {
			$slug = $plugin['slug'];

			if ( empty( $plugin['required'] ) ) {
				WP_CLI::log( sprintf( '  - %s: optional, skipped (install from the admin UI if needed).', $slug ) );
				continue;
			}

			$status = $manager->get_plugin_status( $slug );
			$action = isset( $status['action'] ) ? $status['action'] : '';

			if ( 'install_plugin' === $action && $do_install ) {
				if ( isset( $plugin['is_paid'] ) && empty( $plugin['download_url'] ) ) {
					WP_CLI::warning( sprintf( '%s is a premium plugin without a direct download. Purchase/upload it manually: %s', $slug, isset( $plugin['external_url'] ) ? $plugin['external_url'] : '' ) );
					$blocked[] = $slug;
					continue;
				}

				WP_CLI::log( sprintf( '  - Installing and activating %s...', $slug ) );
				$result = $manager->do_plugin_install( $slug, false );

				if ( is_array( $result ) && ! empty( $result['error'] ) ) {
					WP_CLI::warning( sprintf( 'Failed installing %s: %s', $slug, wp_strip_all_tags( $result['error'] ) ) );
					$blocked[] = $slug;
				} elseif ( ! is_array( $result ) ) {
					WP_CLI::warning( sprintf( 'Failed installing %s: filesystem credentials unavailable.', $slug ) );
					$blocked[] = $slug;
				} else {
					WP_CLI::log( sprintf( '    %s is now %s.', $slug, strtolower( $result['status_text'] ) ) );
				}
			} elseif ( 'enable_plugin' === $action && $do_activate ) {
				WP_CLI::log( sprintf( '  - Activating %s...', $slug ) );
				$result = $manager->do_plugin_activate( $slug, false );

				if ( is_array( $result ) && ! empty( $result['error'] ) ) {
					WP_CLI::warning( sprintf( 'Failed activating %s: %s', $slug, wp_strip_all_tags( $result['error'] ) ) );
					$blocked[] = $slug;
				} else {
					WP_CLI::log( sprintf( '    %s activated.', $slug ) );
				}
			} elseif ( 'disable_plugin' === $action || 'no_action' === $action ) {
				WP_CLI::log( sprintf( '  - %s: already active.', $slug ) );
			} else {
				WP_CLI::log( sprintf( '  - %s: %s (no action taken).', $slug, strtolower( $status['status_text'] ) ) );
			}
		}

		return $blocked;
	}

	/**
	 * Mirror the success-page cleanup the admin UI performs after an import.
	 *
	 * @return void
	 */
	private function run_success_cleanup() {
		delete_option( 'wbcom_theme_demo_import_data' );
		delete_option( 'wbcom_theme_demo_req_plugins' );

		// Delete specific Kirki-related options.
		delete_option( '_transient_timeout_kirki_remote_url_contents' );
		delete_option( '_transient_kirki_remote_url_contents' );
		delete_option( '_site_transient_timeout_kirki_googlefonts_cache' );
		delete_option( '_site_transient_kirki_googlefonts_cache' );
		delete_option( 'kirki_downloaded_font_files' );

		// Regenerate Elementor CSS files.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		// GeoDirectory post-import repairs (same as the success page).
		if ( function_exists( 'geodir_tool_restore_cpt_from_taxonomies' ) ) {
			geodir_tool_restore_cpt_from_taxonomies();
		}
		if ( class_exists( 'GeoDirectory' ) && class_exists( 'WBCOM_TDI_ADMIN_SETTINGS' ) ) {
			WBCOM_TDI_ADMIN_SETTINGS::instance()->populate_geodir_detail_table();
		}
	}

	/**
	 * Get a human-readable source label for a stack plugin entry.
	 *
	 * @param array $plugin The plugin stack entry.
	 * @return string The source label.
	 */
	private function get_plugin_source_label( $plugin ) {
		if ( ! empty( $plugin['download_url'] ) ) {
			return 'external-zip';
		}
		if ( isset( $plugin['is_paid'] ) ) {
			return 'premium';
		}
		if ( ! empty( $plugin['external_url'] ) ) {
			return 'external';
		}
		return 'wordpress.org';
	}

	/**
	 * Ensure write actions run in an admin context.
	 *
	 * The shared AJAX internals check capabilities and skip the current user's
	 * rows during the users table import, so a real admin user must be set.
	 * Honors --user when provided; otherwise falls back to the first administrator.
	 *
	 * @return void
	 */
	private function ensure_admin_context() {
		if ( get_current_user_id() && current_user_can( 'install_plugins' ) ) {
			return;
		}

		if ( get_current_user_id() ) {
			WP_CLI::error( 'The provided --user cannot install plugins. Use an administrator account.' );
		}

		$admins = get_users(
			array(
				'role'    => 'administrator',
				'fields'  => 'ID',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		if ( empty( $admins ) ) {
			WP_CLI::error( 'No administrator user found. Re-run with --user=<admin>.' );
		}

		wp_set_current_user( (int) $admins[0] );
		WP_CLI::log( sprintf( 'Running as administrator user ID %d (pass --user=<admin> to override).', (int) $admins[0] ) );
	}
}

WP_CLI::add_command( 'wbcom-demo', 'WBCOM_Demo_CLI' );
