<?php
/**
 * Admin settings for Reign Demo Installer - WordPress Standard Notices
 * Updated to handle JSON format with is_paid and external_url
 *
 * @package Reign_Demo_Installer
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Emergency fix for missing pluggable functions
if ( ! function_exists( 'wp_get_current_user' ) && file_exists( ABSPATH . 'wp-includes/pluggable.php' ) ) {
	require_once ABSPATH . 'wp-includes/pluggable.php';
}

if ( ! class_exists( 'Reign_Demo_Installer_Admin_Settings' ) ) :

	/**
	 * Reign_Demo_Installer_Admin_Settings class.
	 *
	 * @class Reign_Demo_Installer_Admin_Settings
	 * @version 3.0.0
	 */
	class Reign_Demo_Installer_Admin_Settings {

		/**
		 * The single instance of the class.
		 *
		 * @var Reign_Demo_Installer_Admin_Settings
		 */
		protected static $_instance = null;
		
		/**
		 * Menu slug
		 *
		 * @var string
		 */
		protected static $_slug = 'reign-demo-installer';

		/**
		 * Security instance
		 *
		 * @var Reign_Demo_Installer_Security
		 */
		private $security;

		/**
		 * Logger instance
		 *
		 * @var Reign_Demo_Installer_Logger
		 */
		private $logger;

		/**
		 * Cached demos data
		 *
		 * @var array
		 */
		private $demos_cache = null;

		/**
		 * Main instance.
		 *
		 * @since 3.0.0
		 * @static
		 * @return Reign_Demo_Installer_Admin_Settings - Main instance.
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
			if ( class_exists( 'Reign_Demo_Installer_Security' ) ) {
				$this->security = Reign_Demo_Installer_Security::instance();
			}
			
			if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
				$this->logger = new Reign_Demo_Installer_Logger();
			}
			
			$this->init_hooks();
		}

		/**
		 * Hook into actions and filters.
		 *
		 * @since 3.0.0
		 */
		private function init_hooks() {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 10 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_init', array( $this, 'handle_demo_actions' ) );
			
			// Initialize admin notices system
			$this->init_admin_notices();
		}

		/**
		 * Initialize admin notices system
		 */
		private function init_admin_notices() {
			// Display notices on admin pages
			add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
			
			// Add custom CSS for our notices
			add_action( 'admin_head', array( $this, 'admin_notices_css' ) );
		}

		/**
		 * Add admin menu.
		 */
		public function add_admin_menu() {
			// Always add menu for admin users
			add_menu_page(
				esc_html__( 'Reign Demo Installer', 'reign-demo-installer' ),
				esc_html__( 'Demo Installer', 'reign-demo-installer' ),
				'manage_options',
				self::$_slug,
				array( $this, 'render_page_for_added_menu' ),
				'dashicons-download',
				59
			);
		}

		/**
		 * Handle demo actions (install, activate, etc.).
		 */
		public function handle_demo_actions() {
			// Only process if we're on our admin page
			if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::$_slug ) {
				return;
			}

			// Check if there's an action to handle
			$action = $this->security ? $this->security->get_request_param( 'action', 'string' ) : '';
			
			if ( empty( $action ) ) {
				return;
			}

			// Verify nonce for security
			$nonce = $this->security ? $this->security->get_request_param( '_wpnonce', 'string' ) : '';
			
			if ( ! wp_verify_nonce( $nonce, 'reign_demo_installer_' . $action ) ) {
				$this->show_admin_notice( 
					__( 'Security check failed. Please try again.', 'reign-demo-installer' ), 
					'error' 
				);
				return;
			}

			// Check user capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				$this->show_admin_notice( 
					__( 'You do not have sufficient permissions to perform this action.', 'reign-demo-installer' ), 
					'error' 
				);
				return;
			}

			// Handle different actions
			switch ( $action ) {
				case 'clear_logs':
					$this->handle_clear_logs();
					break;
				
				case 'reset_plugin':
					$this->handle_reset_plugin();
					break;
			}
		}

		/**
		 * Handle clear logs action.
		 */
		private function handle_clear_logs() {
			if ( $this->logger && method_exists( $this->logger, 'clear_log' ) ) {
				$this->logger->clear_log();
				$this->show_admin_notice( 
					__( 'Logs cleared successfully.', 'reign-demo-installer' ), 
					'success' 
				);
			} else {
				$this->show_admin_notice( 
					__( 'Could not clear logs. Logger not available.', 'reign-demo-installer' ), 
					'error' 
				);
			}
		}

		/**
		 * Handle reset plugin action.
		 */
		private function handle_reset_plugin() {
			try {
				// Clear all plugin options
				delete_option( 'reign_theme_demo_import_data' );
				delete_option( 'reign_theme_demo_req_plugins' );
				
				$this->show_admin_notice( 
					__( 'Plugin reset successfully. All demo import data has been cleared.', 'reign-demo-installer' ), 
					'success' 
				);
			} catch ( Exception $e ) {
				$this->show_admin_notice( 
					__( 'Error occurred while resetting plugin.', 'reign-demo-installer' ), 
					'error',
					true,
					array( 'details' => $e->getMessage() )
				);
			}
		}

		/**
		 * Show admin notice with proper WordPress styling
		 *
		 * @param string $message Notice message
		 * @param string $type Notice type (success, error, warning, info)
		 * @param bool $is_dismissible Whether the notice can be dismissed
		 * @param array $data Additional data for the notice
		 */
		public function show_admin_notice( $message, $type = 'info', $is_dismissible = true, $data = array() ) {
			// Validate notice type
			$valid_types = array( 'success', 'error', 'warning', 'info' );
			if ( ! in_array( $type, $valid_types, true ) ) {
				$type = 'info';
			}
			
			$notice_data = array(
				'message' => $message,
				'type' => $type,
				'is_dismissible' => $is_dismissible,
				'data' => $data,
				'timestamp' => time()
			);
			
			// Store notice in transient for display
			$notices = get_transient( 'reign_demo_installer_admin_notices' ) ?: array();
			$notices[] = $notice_data;
			set_transient( 'reign_demo_installer_admin_notices', $notices, 60 ); // 1 minute
		}

		/**
		 * Display stored admin notices
		 */
		public function display_admin_notices() {
			$notices = get_transient( 'reign_demo_installer_admin_notices' );
			
			if ( empty( $notices ) || ! is_array( $notices ) ) {
				return;
			}
			
			foreach ( $notices as $notice ) {
				$this->render_admin_notice( $notice );
			}
			
			// Clear notices after display
			delete_transient( 'reign_demo_installer_admin_notices' );
		}

		/**
		 * Render individual admin notice
		 *
		 * @param array $notice Notice data
		 */
		private function render_admin_notice( $notice ) {
			$message = isset( $notice['message'] ) ? $notice['message'] : '';
			$type = isset( $notice['type'] ) ? $notice['type'] : 'info';
			$is_dismissible = isset( $notice['is_dismissible'] ) ? $notice['is_dismissible'] : true;
			$data = isset( $notice['data'] ) ? $notice['data'] : array();
			
			if ( empty( $message ) ) {
				return;
			}
			
			$notice_classes = array(
				'notice',
				'notice-' . $type,
			);
			
			if ( $is_dismissible ) {
				$notice_classes[] = 'is-dismissible';
			}
			
			// Add custom class for our plugin notices
			$notice_classes[] = 'reign-demo-installer-notice';
			
			$class_string = implode( ' ', $notice_classes );
			
			?>
			<div class="<?php echo esc_attr( $class_string ); ?>">
				<?php if ( $type === 'error' && ! empty( $data['details'] ) ) : ?>
					<p><strong><?php echo esc_html( $message ); ?></strong></p>
					<p><?php echo esc_html( $data['details'] ); ?></p>
				<?php else : ?>
					<p><?php echo esc_html( $message ); ?></p>
				<?php endif; ?>
				
				<?php if ( ! empty( $data['action_url'] ) && ! empty( $data['action_text'] ) ) : ?>
					<p>
						<a href="<?php echo esc_url( $data['action_url'] ); ?>" class="button button-primary">
							<?php echo esc_html( $data['action_text'] ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Add custom CSS for admin notices
		 */
		public function admin_notices_css() {
			?>
			<style type="text/css">
			.reign-demo-installer-notice {
				border-left-width: 4px;
				border-left-style: solid;
				margin: 5px 15px 2px;
				padding: 12px;
				background: #fff;
				box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
			}
			
			.reign-demo-installer-notice.notice-success {
				border-left-color: #00a32a;
			}
			
			.reign-demo-installer-notice.notice-error {
				border-left-color: #d63638;
			}
			
			.reign-demo-installer-notice.notice-warning {
				border-left-color: #dba617;
			}
			
			.reign-demo-installer-notice.notice-info {
				border-left-color: #72aee6;
			}
			
			.reign-demo-installer-notice p {
				margin: 0.5em 0;
				padding: 2px;
				font-size: 13px;
				line-height: 1.5;
			}
			
			.reign-demo-installer-notice .button {
				margin-top: 8px;
			}

			/* WordPress standard notice button styling */
			.reign-demo-installer-notice .button-primary {
				background: #2271b1;
				border-color: #2271b1;
				color: #fff;
			}

			.reign-demo-installer-notice .button-primary:hover {
				background: #135e96;
				border-color: #135e96;
			}
			</style>
			<?php
		}

		/**
		 * Show step header.
		 *
		 * @param string $currentTab Current tab
		 */
		public function show_step_header( $currentTab = '' ) {
			$steps = array(
				'select-demo' => __( 'Select Demo', 'reign-demo-installer' ),
				'manage-plugins' => __( 'Manage Plugins', 'reign-demo-installer' ),
				'install-demo' => __( 'Install Demo', 'reign-demo-installer' ),
				'success' => __( 'Success', 'reign-demo-installer' ),
			);
			?>
			<div class="tab">
				<?php foreach ( $steps as $step => $label ) : ?>
					<button class="tablinks <?php echo ( $currentTab === $step ) ? 'active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<?php
		}

		/**
		 * Render admin page.
		 */
		public function render_page_for_added_menu() {
			// Simple capability check
			if ( ! current_user_can( 'manage_options' ) ) {
				$this->show_admin_notice( 
					__( 'You do not have sufficient permissions to access this page.', 'reign-demo-installer' ), 
					'error' 
				);
				return;
			}

			$theme_info = $this->get_theme_info();
			$current_step = $this->get_current_step();

			?>
			<div class="wrap">
				<div class="demo-listing-wrap">
					<?php $this->render_theme_info( $theme_info ); ?>
					<?php $this->show_step_header( $current_step ); ?>
					
					<div class="reign-demos-wrapper reign-importer-section">
						<?php $this->render_step_content( $current_step ); ?>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Get theme information.
		 *
		 * @return array
		 */
		private function get_theme_info() {
			$theme_info = wp_get_theme();

			// Get parent theme name if child theme
			if ( $theme_info->parent() ) {
				$theme_info = $theme_info->parent();
			}

			return array(
				'Name' => $theme_info->get( 'Name' ),
				'Version' => $theme_info->get( 'Version' ),
				'Description' => $theme_info->get( 'Description' ),
			);
		}

		/**
		 * Render theme info section.
		 *
		 * @param array $theme_info Theme information
		 */
		private function render_theme_info( $theme_info ) {
			?>
			<div class="theme-info">
				<h1><?php echo esc_html( $theme_info['Name'] ); ?></h1>
				<?php if ( ! empty( $theme_info['Version'] ) ) : ?>
					<p class="theme-version"><?php 
						printf( 
							esc_html__( 'Version: %s', 'reign-demo-installer' ), 
							esc_html( $theme_info['Version'] ) 
						); 
					?></p>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Get current step based on URL parameters.
		 *
		 * @return string Current step
		 */
		private function get_current_step() {
			if ( isset( $_GET['success'] ) && $_GET['success'] === 'success' ) {
				return 'success';
			}
			
			if ( isset( $_GET['step'] ) ) {
				$step = $this->security ? $this->security->get_request_param( 'step', 'string' ) : sanitize_text_field( $_GET['step'] );
				
				switch ( $step ) {
					case 'demo_import':
						return 'install-demo';
					case 'plugins_manager':
						return 'manage-plugins';
				}
			}
			
			return 'select-demo';
		}

		/**
		 * Render step content based on current step.
		 *
		 * @param string $current_step Current step
		 */
		private function render_step_content( $current_step ) {
			switch ( $current_step ) {
				case 'success':
					$this->render_success_page();
					break;
					
				case 'install-demo':
					$this->render_demo_install_page();
					break;
					
				case 'manage-plugins':
					$this->render_plugins_manager_page();
					break;
					
				default:
					$this->render_demo_selection_page();
					break;
			}
		}

		/**
		 * Render success page.
		 */
		private function render_success_page() {
			delete_option( 'reign_theme_demo_import_data' );
			delete_option( 'reign_theme_demo_req_plugins' );
			
			$success_file = REIGN_DEMO_INSTALLER_PLUGIN_DIR_PATH . 'core/success.php';
			if ( file_exists( $success_file ) ) {
				include_once $success_file;
			} else {
				$this->show_admin_notice( 
					__( 'Success page template not found.', 'reign-demo-installer' ), 
					'error' 
				);
			}
			
			// Handle GeoDirectory import issue
			if ( function_exists( 'geodir_tool_restore_cpt_from_taxonomies' ) ) {
				geodir_tool_restore_cpt_from_taxonomies();
			}
		}

		/**
		 * Render demo installation page.
		 */
		private function render_demo_install_page() {
			$target_url = $this->security ? $this->security->get_request_param( 'target_url', 'url' ) : '';
			$theme_slug = $this->security ? $this->security->get_request_param( 'theme_slug', 'slug' ) : '';
			$demo_slug = $this->security ? $this->security->get_request_param( 'demo_slug', 'slug' ) : '';

			if ( ! $target_url || ! $theme_slug || ! $demo_slug ) {
				$this->show_admin_notice( 
					__( 'Invalid parameters provided for demo installation.', 'reign-demo-installer' ), 
					'error' 
				);
				return;
			}

			$target_demo_info = $this->get_demo_info( $target_url );
			
			if ( empty( $target_demo_info ) ) {
				$this->show_admin_notice( 
					__( 'Could not retrieve demo information. Please try again later.', 'reign-demo-installer' ), 
					'error' 
				);
				return;
			}

			$this->render_demo_importer_interface( $target_demo_info, $theme_slug, $demo_slug, $target_url );
		}

		/**
		 * Get demo information from target URL.
		 *
		 * @param string $target_url Target URL
		 * @return array Demo information
		 */
		private function get_demo_info( $target_url ) {
			$demos = $this->get_available_demos();
			
			if ( empty( $demos ) ) {
				return array();
			}

			// Find the target demo
			foreach ( $demos as $demo ) {
				if ( isset( $demo['target_url'] ) && $demo['target_url'] === $target_url ) {
					return $demo;
				}
			}

			return array();
		}

		/**
		 * Render demo importer interface.
		 *
		 * @param array $demo_info Demo information
		 * @param string $theme_slug Theme slug
		 * @param string $demo_slug Demo slug
		 * @param string $target_url Target URL
		 */
		private function render_demo_importer_interface( $demo_info, $theme_slug, $demo_slug, $target_url ) {
			?>
			<div class='wrap wbcom-demo-importer'>
				<div class="reign-demos-alertboxes">
					<img src="<?php echo esc_url( REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL . 'demos-imgs/' . $demo_info['screenshot'] ); ?>" 
						 style="width:100%;" 
						 alt="<?php echo esc_attr( $demo_info['demo_name'] ); ?>" />
				</div>
				<div class="reign-demos-progress-container">
					<div id="progress-bar-container" style="display: none;">
						<div class="skills completed">0%</div>
					</div>
					<div id="progress-snackbar"></div>
					<div class='loader' style='display:none;text-align:center;'></div>
					
					<input type='hidden' id='theme_slug' value='<?php echo esc_attr( $theme_slug ); ?>' />
					<input type='hidden' id='demo_slug' value='<?php echo esc_attr( $demo_slug ); ?>' />
					<input type='hidden' id='target_url' value='<?php echo esc_url( $target_url ); ?>' />
					<input type='hidden' id='demo_nonce' value='<?php echo esc_attr( wp_create_nonce( 'reign_demo_installer_import' ) ); ?>' />
					
					<button type='submit' id='wbcom_get_theme_demo_data' class='wbcom-button'>
						<?php esc_html_e( 'Install Demo', 'reign-demo-installer' ); ?>
					</button>
					
					<div id="wbtd-current-action" style="display:none;">
						<?php esc_html_e( 'Initializing...', 'reign-demo-installer' ); ?>
					</div>
				</div>
			</div>

			<?php $this->render_important_notes(); ?>
			<?php
		}

		/**
		 * Render important notes section.
		 */
		private function render_important_notes() {
			?>
			<div class="info-importer">
				<div class="info-impoter-heading">
					<?php esc_html_e( 'Important Notes:', 'reign-demo-installer' ); ?>
				</div>
				<div class="info-impoter-content">
					<ul>
						<li><?php esc_html_e( 'Demo Importer is suggested for fresh installations only. Please make sure you have a full backup of your site before importing demo data.', 'reign-demo-installer' ); ?></li>
						<li><?php esc_html_e( 'Importing all the demo content will take some time, so please be patient.', 'reign-demo-installer' ); ?></li>
						<li><?php esc_html_e( 'Do not close this browser tab during the import process.', 'reign-demo-installer' ); ?></li>
						<li><?php esc_html_e( 'If the import fails, you can try again or contact support.', 'reign-demo-installer' ); ?></li>
						<li><?php esc_html_e( 'You will remain logged in as admin after the import completes.', 'reign-demo-installer' ); ?></li>
					</ul>
				</div>
			</div>
			<?php
		}

		/**
		 * Render plugins manager page.
		 */
		private function render_plugins_manager_page() {
			$theme_slug = $this->security ? $this->security->get_request_param( 'theme_slug', 'slug' ) : '';
			$demo_slug = $this->security ? $this->security->get_request_param( 'demo_slug', 'slug' ) : '';
			$target_url = $this->security ? $this->security->get_request_param( 'target_url', 'url' ) : '';
			$plugins_json_key = $this->security ? $this->security->get_request_param( 'plugins_json_key', 'slug' ) : '';

			if ( ! $plugins_json_key ) {
				$this->show_admin_notice( 
					__( 'Missing plugins configuration. Please go back and select a demo.', 'reign-demo-installer' ), 
					'error' 
				);
				return;
			}

			// Fetch plugins configuration
			$plugins_list = $this->get_plugins_configuration( $plugins_json_key );
			
			if ( empty( $plugins_list ) ) {
				$this->show_admin_notice( 
					__( 'Could not retrieve plugins configuration. Please try again later.', 'reign-demo-installer' ), 
					'error' 
				);
				return;
			}

			$demo_import_url = $this->get_demo_installer_page_url( array(
				'theme_slug' => $theme_slug,
				'demo_slug'  => $demo_slug,
				'target_url' => $target_url,
				'step'       => 'demo_import',
			) );

			$this->render_plugins_list( $plugins_list, $demo_import_url, $plugins_json_key );
		}

		/**
		 * Get plugins configuration.
		 *
		 * @param string $plugins_json_key Plugins JSON key
		 * @return array Plugins configuration
		 */
		private function get_plugins_configuration( $plugins_json_key ) {
			$url_to_request = REIGN_DEMO_INSTALLER_PACKAGE_PLUGINS_URL . $plugins_json_key . '/plugins.json';
			$response = wp_remote_get( $url_to_request, array( 
				'sslverify' => true, 
				'timeout' => 15,
				'user-agent' => 'Reign Demo Installer/' . REIGN_DEMO_INSTALLER_VERSION
			) );

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				return array();
			}

			$body = wp_remote_retrieve_body( $response );
			$plugins = json_decode( $body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return array();
			}

			// Store in option for later use
			update_option( 'reign_theme_demo_req_plugins', $plugins );

			return is_array( $plugins ) ? $plugins : array();
		}

		/**
		 * Render plugins list.
		 *
		 * @param array $plugins_list Plugins list
		 * @param string $demo_import_url Demo import URL
		 * @param string $plugins_json_key Plugins JSON key
		 */
		private function render_plugins_list( $plugins_list, $demo_import_url, $plugins_json_key ) {
			$num_of_req_plugins_installed = 0;
			$required_plugins_to_activate = 0;

			?>
			<div class="goto-install-demo-step">
				<a href="<?php echo esc_url( $demo_import_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Proceed to Demo Installation', 'reign-demo-installer' ); ?>
				</a>
			</div>
			<?php

			foreach ( $plugins_list as $plugin ) {
				$plugin_status = $this->get_plugin_status( $plugin['slug'] );
				$plugin_dependency = 'Optional';
				
				if ( isset( $plugin['required'] ) && $plugin['required'] == true ) {
					$required_plugins_to_activate++;
					$plugin_dependency = 'Required';
					if ( $plugin_status['status_text'] == 'Active' ) {
						$num_of_req_plugins_installed++;
					}
				}

				$already_active_class = ( $plugin_status['status_text'] == 'Active' ) ? 'already-active' : '';

				$this->render_plugin_card( $plugin, $plugin_status, $plugin_dependency, $already_active_class, $plugins_json_key );
			}

			$this->render_plugins_scripts( $required_plugins_to_activate, $num_of_req_plugins_installed );
		}

		/**
		 * Render individual plugin card.
		 *
		 * @param array $plugin Plugin data
		 * @param array $plugin_status Plugin status
		 * @param string $plugin_dependency Plugin dependency
		 * @param string $already_active_class CSS class
		 * @param string $plugins_json_key Plugins JSON key
		 */
		private function render_plugin_card( $plugin, $plugin_status, $plugin_dependency, $already_active_class, $plugins_json_key ) {
			$is_pro = isset( $plugin['is_paid'] ) && ( $plugin['is_paid'] === 'yes' || $plugin['is_paid'] === true );
			$external_url = isset( $plugin['external_url'] ) ? esc_url( $plugin['external_url'] ) : '';
			$has_external_link = ! empty( $external_url );
			
			?>
			<div class="wbcom-req-plugin-card">
				<div class="plugin-container">
					<div class="plugin-importer-sec">
						<ul>
							<li class="importer-plugin-thumb">
								<img src="<?php echo esc_url( REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL . 'plugin-thumb/' . $plugin['plugin_thumb'] ); ?>" 
									 alt="<?php echo esc_attr( $plugin['name'] ); ?>" 
									 class="pluign_image">
							</li>
							<li class="plugin-name">
								<?php echo esc_html( $plugin['name'] ); ?>
								<?php if ( $is_pro ) : ?>
									<span class="pro-badge"><?php esc_html_e( 'Premium', 'reign-demo-installer' ); ?></span>
								<?php endif; ?>
							</li>
							<li class="plugin-status">
								<span class="<?php echo esc_attr( $already_active_class ); ?>">
									<?php echo esc_html( $plugin_status['status_text'] ); ?>
								</span>
							</li>
							<li class="plugin-dependency <?php echo esc_attr( strtolower( $plugin_dependency ) ); ?>">
								<?php echo esc_html( $plugin_dependency ); ?>
							</li>
							<li class="plugin-description"><?php echo esc_html( $plugin['description'] ); ?></li>
							<li class="importer-button">
								<input type="hidden" class="demo-name" name="demo-name" value="<?php echo esc_attr( $plugins_json_key ); ?>">
								<input type="hidden" class="plugin-slug" name="plugin-slug" value="<?php echo esc_attr( $plugin['slug'] ); ?>">
								<input type="hidden" class="plugin-action" name="plugin-action" value="<?php echo esc_attr( $plugin_status['action'] ); ?>">
								
								<?php $this->render_plugin_buttons( $plugin, $plugin_status, $is_pro, $external_url, $already_active_class ); ?>
							</li>
						</ul>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Render plugin action buttons.
		 *
		 * @param array $plugin Plugin data
		 * @param array $plugin_status Plugin status
		 * @param bool $is_pro Is premium plugin
		 * @param string $external_url External URL
		 * @param string $already_active_class CSS class
		 */
		private function render_plugin_buttons( $plugin, $plugin_status, $is_pro, $external_url, $already_active_class ) {
			if ( $is_pro ) {
				if ( $plugin_status['status_text'] !== 'Active' ) {
					if ( ! empty( $external_url ) ) {
						?>
						<a class="button button-primary buy-now-plugins" target="_blank" 
						   href="<?php echo esc_url( $external_url ); ?>">
							<?php esc_html_e( 'Buy Now', 'reign-demo-installer' ); ?>
						</a>
						<?php
					}
					?>
					<a class="plugin-action-button button upload-plugins" target="_blank" 
					   href="<?php echo esc_url( admin_url( 'plugin-install.php' ) ); ?>">
						<?php esc_html_e( 'Upload Plugin', 'reign-demo-installer' ); ?>
					</a>
					<?php
					if ( $plugin_status['action'] === 'enable_plugin' ) {
						?>
						<button class="plugin-action-button button activate-plugin">
							<?php esc_html_e( 'Activate', 'reign-demo-installer' ); ?>
						</button>
						<?php
					}
				} else {
					?>
					<button class="plugin-action-button button <?php echo esc_attr( $already_active_class ); ?>">
						<?php echo esc_html( $plugin_status['action_text'] ); ?>
					</button>
					<?php
				}
			} else {
				?>
				<button class="plugin-action-button button <?php echo esc_attr( $already_active_class ); ?>">
					<?php echo esc_html( $plugin_status['action_text'] ); ?>
				</button>
				<?php
			}
		}

		/**
		 * Render plugins management scripts.
		 *
		 * @param int $required_plugins_to_activate Required plugins count
		 * @param int $num_of_req_plugins_installed Installed plugins count
		 */
		private function render_plugins_scripts( $required_plugins_to_activate, $num_of_req_plugins_installed ) {
			?>
			<div class="demo_listing_modal"></div>
			<input type="hidden" id="required_plugins_to_activate" 
				   name="required_plugins_to_activate" 
				   value="<?php echo esc_attr( $required_plugins_to_activate ); ?>">
			<input type="hidden" id="num_of_req_plugins_installed" 
				   name="num_of_req_plugins_installed" 
				   value="<?php echo esc_attr( $num_of_req_plugins_installed ); ?>">
			<input type="hidden" id="plugins_nonce" 
				   value="<?php echo esc_attr( wp_create_nonce( 'reign_demo_installer_plugins' ) ); ?>">
			<?php
		}

		/**
		 * Render demo selection page.
		 */
		private function render_demo_selection_page() {
			delete_option( 'reign_theme_demo_import_data' );
			delete_option( 'reign_theme_demo_req_plugins' );

			echo '<div id="demos_import_filter">';

			$demos = $this->get_available_demos();
			
			if ( empty( $demos ) ) {
				$this->show_admin_notice( 
					__( 'No demos available at this time. Please try again later or contact support.', 'reign-demo-installer' ), 
					'warning' 
				);
				echo '</div>';
				return;
			}

			$this->render_demos_grid( $demos );
			echo '</div>';
		}

		/**
		 * Get available demos.
		 *
		 * @return array Available demos
		 */
		private function get_available_demos() {
			// Use cached data if available
			if ( $this->demos_cache !== null ) {
				return $this->demos_cache;
			}

			$parent_url_to_request = REIGN_DEMO_INSTALLER_PACKAGE_URL . 'demos.json';
			$response = wp_remote_get( $parent_url_to_request, array( 
				'sslverify' => true, 
				'timeout' => 15,
				'user-agent' => 'Reign Demo Installer/' . REIGN_DEMO_INSTALLER_VERSION
			) );

			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				$this->demos_cache = array();
				return array();
			}

			$body = wp_remote_retrieve_body( $response );
			$demos = json_decode( $body, true );

			$this->demos_cache = ( json_last_error() === JSON_ERROR_NONE ) ? $demos : array();
			return $this->demos_cache;
		}

		/**
		 * Render demos grid.
		 *
		 * @param array $demos Available demos
		 */
		private function render_demos_grid( $demos ) {
			$current_motive = '';
			
			foreach ( $demos as $key => $demo ) {
				// Start new section for different motive
				if ( $current_motive !== $demo['motive_key'] ) {
					if ( $current_motive !== '' ) {
						echo '</div>'; // Close previous section
					}
					$current_motive = $demo['motive_key'];
					echo '<div class="demo-content-wrap">';
				}

				$this->render_demo_card( $demo );

				// Close last section
				if ( $key === count( $demos ) - 1 ) {
					echo '</div>';
				}
			}
		}

		/**
		 * Render individual demo card.
		 *
		 * @param array $demo Demo data
		 */
		private function render_demo_card( $demo ) {
			$preview_url = isset( $demo['preview_url'] ) ? esc_url( $demo['preview_url'] ) : '';
			$import_url = $this->get_demo_installer_page_url( array(
				'theme_slug'       => $demo['theme_slug'],
				'demo_slug'        => $demo['demo_slug'],
				'target_url'       => $demo['target_url'],
				'step'             => 'plugins_manager',
				'plugins_json_key' => $demo['plugins_json_key'],
			) );

			?>
			<div class='wbcom-demo-importer import_filter <?php echo esc_attr( $demo['motive_key'] ); ?>'>
				<div class="container">
					<img src="<?php echo esc_url( REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL . 'demos-imgs/' . $demo['screenshot'] ); ?>" 
						 alt="<?php echo esc_attr( $demo['demo_name'] ); ?>" 
						 class="image" 
						 style="width:100%">
					<div class="demo-title">
						<h2><?php echo esc_html( $demo['demo_name'] ); ?></h2>
						<div class="middle">
							<a href="<?php echo esc_url( $import_url ); ?>" class="wbcom-button import">
								<?php esc_html_e( 'Import', 'reign-demo-installer' ); ?>
							</a>
							<?php if ( $preview_url ) : ?>
								<a target="_blank" href="<?php echo esc_url( $preview_url ); ?>" class="wbcom-button preview">
									<?php esc_html_e( 'Preview', 'reign-demo-installer' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Get plugin status.
		 *
		 * @param string $plugin_slug Plugin slug
		 * @return array Plugin status
		 */
		private function get_plugin_status( $plugin_slug ) {
			if ( class_exists( 'Reign_Demo_Installer_Plugins_Manager' ) ) {
				$plugins_manager = Reign_Demo_Installer_Plugins_Manager::instance();
				return $plugins_manager->get_plugin_status( $plugin_slug );
			}
			
			// Fallback status
			return array(
				'status_text' => 'Unknown',
				'action_text' => 'Check Status',
				'action' => 'check_status'
			);
		}

		/**
		 * Enqueue admin scripts and styles.
		 */
		public function admin_enqueue_scripts() {
			$screen = get_current_screen();
			if ( ! $screen || $screen->id !== 'toplevel_page_' . self::$_slug ) {
				return;
			}

			// Enqueue scripts
			wp_enqueue_script(
				'reign-demo-installer-js',
				REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL . 'assets/js/importer.js',
				array( 'jquery' ),
				REIGN_DEMO_INSTALLER_VERSION,
				true
			);

			wp_enqueue_script(
				'reign-demo-installer-filter-js',
				REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL . 'assets/js/jquery.mixitup.min.js',
				array( 'jquery' ),
				REIGN_DEMO_INSTALLER_VERSION,
				true
			);

			// Localize script
			wp_localize_script(
				'reign-demo-installer-js',
				'reignDemoInstaller',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'successUrl' => $this->get_demo_installer_page_url( array( 'success' => 'success' ) ),
					'nonce' => wp_create_nonce( 'reign_demo_installer_ajax' ),
					'strings' => array(
						'installing' => esc_html__( 'Installing...', 'reign-demo-installer' ),
						'activating' => esc_html__( 'Activating...', 'reign-demo-installer' ),
						'error' => esc_html__( 'An error occurred. Please try again.', 'reign-demo-installer' ),
						'success' => esc_html__( 'Operation completed successfully.', 'reign-demo-installer' ),
					)
				)
			);

			// Enqueue styles
			wp_enqueue_style(
				'reign-demo-installer-css',
				REIGN_DEMO_INSTALLER_PLUGIN_DIR_URL . 'assets/css/demo-listing.css',
				array(),
				REIGN_DEMO_INSTALLER_VERSION
			);

			// Add inline styles for enhanced UI
			$this->add_custom_styles();
		}

		/**
		 * Add custom inline styles.
		 */
		private function add_custom_styles() {
			$custom_css = "
				.pro-badge {
					background: #ff6b35;
					color: white;
					font-size: 10px;
					padding: 2px 6px;
					border-radius: 3px;
					margin-left: 8px;
					font-weight: bold;
					text-transform: uppercase;
				}
				
				.buy-now-plugins {
					margin-right: 5px !important;
				}
				
				.upload-plugins {
					background: #0073aa;
					border-color: #0073aa;
					color: white;
				}
				
				.plugin-dependency.optional {
					color: #666;
				}
				
				.plugin-dependency.required {
					color: #d63384;
					font-weight: bold;
				}
				
				.theme-version {
					color: #666;
					font-style: italic;
				}
			";
			wp_add_inline_style( 'reign-demo-installer-css', $custom_css );
		}

		/**
		 * Get demo installer page URL.
		 *
		 * @param array $args URL arguments
		 * @return string Page URL
		 */
		public function get_demo_installer_page_url( $args = array() ) {
			$base_url = admin_url( 'admin.php?page=' . self::$_slug );
			
			if ( ! empty( $args ) ) {
				$base_url = add_query_arg( $args, $base_url );
			}
			
			return $base_url;
		}
	}

endif;

/**
 * Main instance of Reign_Demo_Installer_Admin_Settings.
 *
 * @since 3.0.0
 * @return Reign_Demo_Installer_Admin_Settings
 */
Reign_Demo_Installer_Admin_Settings::instance();