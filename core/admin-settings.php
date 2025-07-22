<?php
/**
 * Admin settings for Reign Demo Installer - Complete File with Reports
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
		 */
		private function init_hooks() {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 10 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_init', array( $this, 'handle_demo_actions' ) );
			
			// AJAX action for clearing file reports
			add_action( 'wp_ajax_clear_file_reports', array( $this, 'clear_file_reports' ) );
			
			// Initialize admin notices system
			$this->init_admin_notices();
		}

		/**
		 * Clear file reports AJAX handler.
		 */
		public function clear_file_reports() {
			// Security checks
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			}
			
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
			if ( ! wp_verify_nonce( $nonce, 'clear_file_reports' ) ) {
				wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
			}
			
			// Clear the file reports
			delete_option( 'reign_demo_import_summary' );
			
			wp_send_json_success( array( 'message' => 'Reports cleared successfully' ) );
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
			$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
			
			if ( empty( $action ) ) {
				return;
			}

			// Verify nonce for security
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( $_GET['_wpnonce'] ) : '';
			
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
				delete_option( 'reign_demo_import_summary' );
				
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
			</style>
			<?php
		}

		/**
		 * Show step header.
		 */
		public function show_step_header( $currentTab = '' ) {
			$steps = array(
				'select-demo' => __( 'Select Demo', 'reign-demo-installer' ),
				'manage-plugins' => __( 'Manage Plugins', 'reign-demo-installer' ),
				'install-demo' => __( 'Install Demo', 'reign-demo-installer' ),
				'success' => __( 'Success', 'reign-demo-installer' ),
			);
			
			// Add reports tab if we have import summary
			$import_summary = get_option( 'reign_demo_import_summary', array() );
			if ( ! empty( $import_summary ) ) {
				$steps['file-reports'] = __( 'File Reports', 'reign-demo-installer' );
			}
			
			?>
			<div class="tab">
				<?php foreach ( $steps as $step => $label ) : ?>
					<?php if ( $step === 'file-reports' ) : ?>
						<button class="tablinks <?php echo ( $currentTab === $step ) ? 'active' : ''; ?>" 
								onclick="showFileReports()" type="button">
							📋 <?php echo esc_html( $label ); ?>
						</button>
					<?php else : ?>
						<button class="tablinks <?php echo ( $currentTab === $step ) ? 'active' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			
			<?php if ( ! empty( $import_summary ) ) : ?>
			<script>
			function showFileReports() {
				// Hide main content
				document.querySelector('.reign-demos-wrapper').style.display = 'none';
				
				// Show reports
				let reportsDiv = document.getElementById('file-reports-section');
				if ( !reportsDiv ) {
					reportsDiv = document.createElement('div');
					reportsDiv.id = 'file-reports-section';
					reportsDiv.innerHTML = '<?php echo addslashes( $this->get_file_reports_html() ); ?>';
					document.querySelector('.demo-listing-wrap').appendChild(reportsDiv);
				}
				reportsDiv.style.display = 'block';
				
				// Update active tab
				document.querySelectorAll('.tablinks').forEach(btn => btn.classList.remove('active'));
				event.target.classList.add('active');
			}
			</script>
			<?php endif; ?>
			<?php
		}

		/**
		 * Get file reports HTML
		 */
		private function get_file_reports_html() {
			$import_summary = get_option( 'reign_demo_import_summary', array() );
			
			if ( empty( $import_summary ) || ! isset( $import_summary['fileReports'] ) ) {
				return '<div class="notice notice-info"><p>No file reports available.</p></div>';
			}

			$reports = $import_summary['fileReports'];
			ob_start();
			?>
			<div class="file-reports-container">
				<h2>📋 File Import Report</h2>
				
				<?php if ( ! empty( $reports['downloadFailed'] ) ) : ?>
				<div class="report-section error">
					<h3>❌ Download Failed (<?php echo count( $reports['downloadFailed'] ); ?>)</h3>
					<ul>
						<?php foreach ( $reports['downloadFailed'] as $file ) : ?>
						<li><code><?php echo esc_html( $file['name'] ); ?></code> - <?php echo esc_html( $file['lastError'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $reports['processingFailed'] ) ) : ?>
				<div class="report-section error">
					<h3>💥 Processing Failed (<?php echo count( $reports['processingFailed'] ); ?>)</h3>
					<ul>
						<?php foreach ( $reports['processingFailed'] as $file ) : ?>
						<li><code><?php echo esc_html( $file['name'] ); ?></code> - <?php echo esc_html( $file['error'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $reports['downloadedButNotProcessed'] ) ) : ?>
				<div class="report-section warning">
					<h3>⚠️ Downloaded but Not Processed (<?php echo count( $reports['downloadedButNotProcessed'] ); ?>)</h3>
					<ul>
						<?php foreach ( $reports['downloadedButNotProcessed'] as $file ) : ?>
						<li><code><?php echo esc_html( $file['name'] ); ?></code> - <?php echo esc_html( $file['reason'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $reports['downloadedAndProcessed'] ) ) : ?>
				<div class="report-section success">
					<h3>✅ Successfully Processed (<?php echo count( $reports['downloadedAndProcessed'] ); ?>)</h3>
					<p>All these files were successfully downloaded and processed.</p>
				</div>
				<?php endif; ?>

				<div class="report-section info">
					<h3>📊 Summary</h3>
					<p><strong>Total Files:</strong> <?php echo esc_html( $import_summary['downloads']['total'] ?? 0 ); ?></p>
					<p><strong>Downloaded:</strong> <?php echo esc_html( $import_summary['downloads']['completed'] ?? 0 ); ?></p>
					<p><strong>Processed:</strong> <?php echo esc_html( $import_summary['processing']['completed'] ?? 0 ); ?></p>
					<p><strong>Failed:</strong> <?php echo esc_html( $import_summary['failed'] ?? 0 ); ?></p>
					<p><strong>Duration:</strong> <?php echo esc_html( $import_summary['duration'] ?? 0 ); ?>s</p>
				</div>

				<div class="report-actions">
					<button type="button" class="button" onclick="clearReports()">Clear Reports</button>
				</div>
			</div>

			<style>
			.file-reports-container { margin: 20px 0; }
			.report-section { margin: 15px 0; padding: 15px; border-radius: 5px; }
			.report-section.error { background: #fdf2f2; border-left: 4px solid #dc3545; }
			.report-section.warning { background: #fffbf0; border-left: 4px solid #ffc107; }
			.report-section.success { background: #f0f9f3; border-left: 4px solid #28a745; }
			.report-section.info { background: #f0f8ff; border-left: 4px solid #17a2b8; }
			.report-section h3 { margin-top: 0; }
			.report-actions { margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; }
			</style>

			<script>
			function clearReports() {
				if (confirm('Clear all file reports?')) {
					jQuery.post(ajaxurl, {
						action: 'clear_file_reports',
						nonce: '<?php echo wp_create_nonce( 'clear_file_reports' ); ?>'
					}, function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('Failed to clear reports');
						}
					});
				}
			}
			</script>
			<?php
			return ob_get_clean();
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
		 */
		private function get_current_step() {
			if ( isset( $_GET['success'] ) && $_GET['success'] === 'success' ) {
				return 'success';
			}
			
			if ( isset( $_GET['step'] ) ) {
				$step = sanitize_text_field( $_GET['step'] );
				
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
			
			// Delete Kirki font transients
			global $wpdb;
			$transients = $wpdb->get_col( 
				"SELECT option_name FROM {$wpdb->options} 
				WHERE option_name LIKE '_transient_kirki_googlefonts%' 
				OR option_name LIKE '_transient_timeout_kirki_googlefonts%'"
			);
			foreach ( $transients as $transient ) {
				delete_option( $transient );
			}
			
			// Regenerate Elementor CSS files
			if ( class_exists( '\Elementor\Plugin' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
			
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
			$target_url = isset( $_GET['target_url'] ) ? esc_url_raw( $_GET['target_url'] ) : '';
			$theme_slug = isset( $_GET['theme_slug'] ) ? sanitize_text_field( $_GET['theme_slug'] ) : '';
			$demo_slug = isset( $_GET['demo_slug'] ) ? sanitize_text_field( $_GET['demo_slug'] ) : '';

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
					<!-- Progress Bar -->
					<div id="progress-bar-container" style="display: none;">
						<div class="progress-wrapper">
							<div class="progress-bar">
								<div class="completed">0%</div>
							</div>
						</div>
					</div>
					
					<!-- Status Message -->
					<div id="wbtd-current-action" style="display:none;">
						<?php esc_html_e( 'Initializing...', 'reign-demo-installer' ); ?>
					</div>
					
					<!-- Import Button -->
					<div class="import-button-container">
						<button type='submit' id='wbcom_get_theme_demo_data' class='button button-primary button-hero'>
							<?php esc_html_e( 'Start Demo Import', 'reign-demo-installer' ); ?>
						</button>
					</div>
					
					<!-- Hidden Fields -->
					<input type='hidden' id='theme_slug' value='<?php echo esc_attr( $theme_slug ); ?>' />
					<input type='hidden' id='demo_slug' value='<?php echo esc_attr( $demo_slug ); ?>' />
					<input type='hidden' id='target_url' value='<?php echo esc_url( $target_url ); ?>' />
					<input type='hidden' id='demo_nonce' value='<?php echo esc_attr( wp_create_nonce( 'reign_demo_installer_import' ) ); ?>' />
				</div>
			</div>

			<div class="info-importer">
				<div class="info-impoter-heading">
					<?php esc_html_e( 'Before You Start:', 'reign-demo-installer' ); ?>
				</div>
				<div class="info-impoter-content">
					<ul>
						<li><?php esc_html_e( 'This will download and import all demo content automatically', 'reign-demo-installer' ); ?></li>
						<li><?php esc_html_e( 'The process takes 5-15 minutes depending on your connection', 'reign-demo-installer' ); ?></li>
						<li><?php esc_html_e( 'You will remain logged in as admin throughout the process', 'reign-demo-installer' ); ?></li>
						<li><?php esc_html_e( 'Do not close this browser tab during the import', 'reign-demo-installer' ); ?></li>
					</ul>
				</div>
			</div>
			<?php
		}

		/**
		 * Render plugins manager page.
		 */
		private function render_plugins_manager_page() {
			$theme_slug = isset( $_GET['theme_slug'] ) ? sanitize_text_field( $_GET['theme_slug'] ) : '';
			$demo_slug = isset( $_GET['demo_slug'] ) ? sanitize_text_field( $_GET['demo_slug'] ) : '';
			$target_url = isset( $_GET['target_url'] ) ? esc_url_raw( $_GET['target_url'] ) : '';
			$plugins_json_key = isset( $_GET['plugins_json_key'] ) ? sanitize_text_field( $_GET['plugins_json_key'] ) : '';

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
		 */
		private function render_plugin_card( $plugin, $plugin_status, $plugin_dependency, $already_active_class, $plugins_json_key ) {
			$is_pro = isset( $plugin['is_paid'] ) && ( $plugin['is_paid'] === 'yes' || $plugin['is_paid'] === true );
			$external_url = isset( $plugin['external_url'] ) ? esc_url( $plugin['external_url'] ) : '';
			
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
								<p class="<?php echo esc_attr( $already_active_class ); ?>">
									<?php echo esc_html( $plugin_status['status_text'] ); ?>
								</p>
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
		}

		/**
		 * Get demo installer page URL.
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
 */
Reign_Demo_Installer_Admin_Settings::instance();