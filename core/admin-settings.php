<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WBCOM_TDI_ADMIN_SETTINGS' ) ) :

/**
 * @class WBCOM_TDI_ADMIN_SETTINGS
 * @version	1.0.0
 */
class WBCOM_TDI_ADMIN_SETTINGS {
	
	/**
	 * The single instance of the class.
	 *
	 * @var WBCOM_TDI_ADMIN_SETTINGS
	 * @since 1.0.0
	 */
	protected static $_instance = null;
	protected static $_slug = 'wbcom-theme-demo-installer';

	/**
	 * Main WBCOM_TDI_ADMIN_SETTINGS Instance.
	 *
	 * Ensures only one instance of WBCOM_TDI_ADMIN_SETTINGS is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see WBCOM_TDI_ADMIN_SETTINGS()
	 * @return WBCOM_TDI_ADMIN_SETTINGS - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	
	/**
	 * WBCOM_TDI_ADMIN_SETTINGS Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Hook into actions and filters.
	 * @since  1.0.0
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	public function add_admin_menu() {
		add_menu_page(
			$page_title	=	__( 'Theme Installer', WBCOM_Theme_Demo_Installer_TEXT_DOMAIN ),
			$menu_title	=	__( 'Theme Installer', WBCOM_Theme_Demo_Installer_TEXT_DOMAIN ),
			$capability	=	'manage_options',
			$menu_slug	=	self::$_slug,
			$function	=	array( $this, 'render_page_for_added_menu' ),
			$icon_url	=	'',
			$position	=	null
		);
	}

	public function show_step_header( $currentTab = '' ) {
		?>
		<div class="tab">
			<button class="tablinks <?php echo ( $currentTab == 'select-demo' ) ? 'active' : ''; ?>"><?php _e( 'Select Demo', WBCOM_Theme_Demo_Installer_TEXT_DOMAIN ); ?></button>
			<button class="tablinks <?php echo ( $currentTab == 'manage-plugins' ) ? 'active' : ''; ?>"><?php _e( 'Manage Plugins', WBCOM_Theme_Demo_Installer_TEXT_DOMAIN ); ?></button>
			<button class="tablinks <?php echo ( $currentTab == 'install-demo' ) ? 'active' : ''; ?>"><?php _e( 'Install Demo', WBCOM_Theme_Demo_Installer_TEXT_DOMAIN ); ?></button>
			<button class="tablinks <?php echo ( $currentTab == 'success' ) ? 'active' : ''; ?>"><?php _e( 'Success', WBCOM_Theme_Demo_Installer_TEXT_DOMAIN ); ?></button>
		</div>
		<?php
	}

	public function render_page_for_added_menu() {
		$theme_info = wp_get_theme();
		$reflection = new ReflectionClass( $theme_info );
		$property = $reflection->getProperty( 'headers' );
		$property->setAccessible(true);
		$theme_info = $property->getValue( $theme_info );

		echo '<div class="wrap">';

		

		echo '<div class="demo-listing-wrap">';

		?>

		<div class="theme-info">
			<h1><?php echo $theme_info['Name']; ?></h1>
		</div>

		<?php
		if( isset( $_GET['success'] ) && ( $_GET['success'] == 'success' ) ) {
			$this->show_step_header( 'success' );
		}
		else if( isset( $_GET['theme_slug'] ) && isset( $_GET['demo_slug'] ) && isset( $_GET['step'] ) && ( $_GET['step'] == 'demo_import' ) ) {
			$this->show_step_header( 'install-demo' );
		}
		else if( isset( $_GET['theme_slug'] ) && isset( $_GET['demo_slug'] ) && isset( $_GET['step'] ) && ( $_GET['step'] == 'plugins_manager' ) ) {
			$this->show_step_header( 'manage-plugins' );

		}
		else {
			$this->show_step_header( 'select-demo' );
		}
		?>

		<div class="demo-content-wrap">
		<?php
		
		if( isset( $_GET['success'] ) && ( $_GET['success'] == 'success' ) ) {
			delete_option( 'wbcom_theme_demo_import_data' );
			delete_option( 'wbcom_theme_demo_req_plugins' );
			include_once 'success.php';
			return;
		}
		else if( isset( $_GET['theme_slug'] ) && isset( $_GET['demo_slug'] ) && isset( $_GET['step'] ) && ( $_GET['step'] == 'demo_import' ) ) {
			echo "<div class='wrap wbcom-demo-importer'>";
			?>
			<div class="alert">
				<?php _e( 'ATTENTION PLEASE !!! All you data will be replaced by demo data.', WBCOM_Theme_Demo_Installer_TEXT_DOMAIN ); ?>
			</div>
			<div id="progress-bar-container" style="display: none;">
				<div class="skills completed">80%</div>
			</div>
			<div id="progress-snackbar"></div>
			<?php
			echo "<div class='loader' style='display:none;'></div>";
			echo "<input type='hidden' id='theme_slug' value='$_GET[theme_slug]' />";
			echo "<input type='hidden' id='demo_slug' value='$_GET[demo_slug]' />";
			echo "<button type='submit' id='wbcom_get_theme_demo_data' class='wbcom-button'>" . __( 'Install Demo', 'ASDF' ) . "</button>";
			echo "<div>";
		}
		else if( isset( $_GET['theme_slug'] ) && isset( $_GET['demo_slug'] ) && isset( $_GET['step'] ) && ( $_GET['step'] == 'plugins_manager' ) ) {
			if( empty( get_option( 'wbcom_theme_demo_req_plugins', array() ) ) ) {
				$url_to_request = WBCOM_Theme_Demo_Installer_URL_TO_REQUEST;
				$response = wp_remote_post( $url_to_request, array(
					'method' => 'POST',
					'timeout' => 45,
					'headers' => array(),
					'body' => array(
						'theme_slug'	=> $_GET['theme_slug'],
						'demo_slug'	=> $_GET['demo_slug'],
						'plugins_list' => 'get_plugins_list',
					)
				) );
				if ( !is_wp_error( $response ) ) {
					if ( isset( $response['response']['code'] ) &&  ( $response['response']['code'] == 200 ) ) {
						$response = isset( $response['body'] ) ? $response['body'] : '';
						if( !empty( $response ) ) {
							$response = json_decode( $response, true );
						}
						if( !empty( $response ) && is_array( $response ) ) {
							update_option( 'wbcom_theme_demo_req_plugins', $response );
						}
					}
				}
			}

			$num_of_req_plugins_installed = 0;
			$demo_import_url = $this->get_demo_installer_page_url(
				array(
					'theme_slug' => $_GET['theme_slug'],
					'demo_slug' => $_GET['demo_slug'],
					'step' => 'demo_import',
				) );
			$plugins_list = get_option( 'wbcom_theme_demo_req_plugins', array() );
			?>
			<div class="goto-install-demo-step">
				<a href="<?php echo $demo_import_url; ?>" class="button button-primary"><?php _e( 'Go To Demo Installation', WBCOM_Theme_Demo_Installer_TEXT_DOMAIN ); ?></a>
			</div>
			<?php
			foreach ( $plugins_list as $key => $plugin ) {
				$plugin_status = instantiate_wbcom_demo_importer_plugins_manager()->get_plugin_status( $plugin['slug'] );
				$plugin_dependency = 'Optional';
				if( isset( $plugin['required'] ) && ( $plugin['required'] == true ) ) {
					$plugin_dependency = 'Required';
					if( $plugin_status['status_text'] == 'Active' ) {
						$num_of_req_plugins_installed++;
					}
				}
				$already_active_class = '';
				if( $plugin_status['status_text'] == 'Active' ) {
					$already_active_class = 'already-active';
				}
				?>
				<div class="wbcom-req-plugin-card">
					<div class="plugin-container">
						<h3><?php echo $plugin['name']; ?></h3>
						<p class="plugin-status <?php echo $already_active_class; ?>"><?php echo $plugin_status['status_text']; ?></p>
						<p class="plugin-dependency"><?php echo $plugin_dependency; ?></p>
						<p class="plugin-description"><?php echo $plugin['description']; ?></p>
						<input type="hidden" class="plugin-slug" name="plugin-slug" value="<?php echo $plugin['slug']; ?>">
						<input type="hidden" class="plugin-action" name="plugin-action" value="<?php echo $plugin_status['action']; ?>">
						<button class="plugin-action-button button <?php echo $already_active_class; ?>"><?php echo $plugin_status['action_text']; ?></button>
					</div>
				</div>
				<?php
			}
			?>
			<div class="demo_listing_modal"></div>
			<input type="hidden" id="num_of_req_plugins_installed" name="num_of_req_plugins_installed" value="<?php echo $num_of_req_plugins_installed; ?>">
			<?php
		}
		else {
			delete_option( 'wbcom_theme_demo_import_data' );
			delete_option( 'wbcom_theme_demo_req_plugins' );

			$current_url = $this->get_demo_installer_page_url();
			$url_to_request = WBCOM_Theme_Demo_Installer_URL_TO_REQUEST;
			$response = wp_remote_post( $url_to_request, array(
				'method' => 'POST',
				'timeout' => 45,
				'headers' => array(),
				'body' => array( 
					'theme_name' => $theme_info['Name'],
				)
			) );
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				echo "Something went wrong: $error_message";
			} else {
				if ( isset( $response['response']['code'] ) &&  ( $response['response']['code'] == 200 ) ) {
					$response = isset( $response['body'] ) ? $response['body'] : '';
					if( !empty( $response ) ) {
						$response = json_decode( $response, true );
					}
					if( !empty( $response ) && is_array( $response ) ) {
						foreach ( $response as $key => $value ) {
							$href = $this->get_demo_installer_page_url(
								array(
									'theme_slug' => $value['theme_slug'],
									'demo_slug' => $value['demo_slug'],
									'step' => 'plugins_manager',
								) );
							?>
							<div class='wbcom-demo-importer'>
								<div class="container">
									<form method="get" action="<?php echo $current_url; ?>">
										<img src="<?php echo $value['screenshot']; ?>" alt="Avatar" class="image" style="width:100%">
										<div class="middle">
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Import'; ?></a>
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Preview'; ?></a>
										</div>
									</form>
									<div class="demo-title">
										<?php echo $value['demo_name']; ?>
									</div>
								</div>
							</div>
							<div class='wbcom-demo-importer'>
								<div class="container">
									<form method="get" action="<?php echo $current_url; ?>">
										<img src="<?php echo $value['screenshot']; ?>" alt="Avatar" class="image" style="width:100%">
										<div class="middle">
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Import'; ?></a>
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Preview'; ?></a>
										</div>
									</form>
									<div class="demo-title">
										<?php echo $value['demo_name']; ?>
									</div>
								</div>
							</div>
							<div class='wbcom-demo-importer'>
								<div class="container">
									<form method="get" action="<?php echo $current_url; ?>">
										<img src="<?php echo $value['screenshot']; ?>" alt="Avatar" class="image" style="width:100%">
										<div class="middle">
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Import'; ?></a>
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Preview'; ?></a>
										</div>
									</form>
									<div class="demo-title">
										<?php echo $value['demo_name']; ?>
									</div>
								</div>
							</div>
							<div class='wbcom-demo-importer'>
								<div class="container">
									<form method="get" action="<?php echo $current_url; ?>">
										<img src="<?php echo $value['screenshot']; ?>" alt="Avatar" class="image" style="width:100%">
										<div class="middle">
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Import'; ?></a>
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Preview'; ?></a>
										</div>
									</form>
									<div class="demo-title">
										<?php echo $value['demo_name']; ?>
									</div>
								</div>
							</div>
							<div class='wbcom-demo-importer'>
								<div class="container">
									<form method="get" action="<?php echo $current_url; ?>">
										<img src="<?php echo $value['screenshot']; ?>" alt="Avatar" class="image" style="width:100%">
										<div class="middle">
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Import'; ?></a>
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Preview'; ?></a>
										</div>
									</form>
									<div class="demo-title">
										<?php echo $value['demo_name']; ?>
									</div>
								</div>
							</div>
							<div class='wbcom-demo-importer'>
								<div class="container">
									<form method="get" action="<?php echo $current_url; ?>">
										<img src="<?php echo $value['screenshot']; ?>" alt="Avatar" class="image" style="width:100%">
										<div class="middle">
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Import'; ?></a>
											<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo 'Preview'; ?></a>
										</div>
									</form>
									<div class="demo-title">
										<?php echo $value['demo_name']; ?>
									</div>
								</div>
							</div>	
							<?php
						}
					}
					else {
						_e( 'No Theme Demo Available', WBCOM_Theme_Demo_Installer_TEXT_DOMAIN );
					}
				}
			}
		}
		echo '</div>';
		echo '</div>';

		echo '</div>';
	}

	public function admin_enqueue_scripts() {
		$screen = get_current_screen();
		if ( $screen->id != 'toplevel_page_wbcom-theme-demo-installer' ) { return; }

		if( empty( get_option( 'wbcom_theme_demo_req_plugins', array() ) ) ) {
			$url_to_request = WBCOM_Theme_Demo_Installer_URL_TO_REQUEST;
			$response = wp_remote_post( $url_to_request, array(
				'method' => 'POST',
				'timeout' => 45,
				'headers' => array(),
				'body' => array(
					'theme_slug'	=> $_GET['theme_slug'],
					'demo_slug'	=> $_GET['demo_slug'],
					'plugins_list' => 'get_plugins_list',
				)
			) );
			if ( !is_wp_error( $response ) ) {
				if ( isset( $response['response']['code'] ) &&  ( $response['response']['code'] == 200 ) ) {
					$response = isset( $response['body'] ) ? $response['body'] : '';
					if( !empty( $response ) ) {
						$response = json_decode( $response, true );
					}
					if( !empty( $response ) && is_array( $response ) ) {
						update_option( 'wbcom_theme_demo_req_plugins', $response );
					}
				}
			}
		}
		$required_plugins_to_activate = 0;
		$plugins_list = get_option( 'wbcom_theme_demo_req_plugins', array() );
		foreach ( $plugins_list as $key => $value ) {
			if( $value['required'] ) {
				$required_plugins_to_activate++;
			}
		}

		wp_register_script(
			$handle		=	'wbcom_theme_demo_installer_js',
			$src		=	WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'assets/js/importer.js',
			$deps		=	array( 'jquery' ),
			$ver		=	time(),
			$in_footer	=	true
		);
		wp_localize_script(
			'wbcom_theme_demo_installer_js',
			'wbcom_theme_demo_installer_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'success_url' => $this->get_demo_installer_page_url( array( 'success' => 'success' ) ),
				'required_plugins_to_activate'	=> $required_plugins_to_activate
			)
		);
		wp_enqueue_script( 'wbcom_theme_demo_installer_js' );

		wp_register_style(
			$handle		=	'wbcom-demo-listing-css',
			$src		=	WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'assets/css/demo-listing.css',
			$deps		=	array(),
			$ver		=	time(),
			$media		=	'all'
		);
		wp_enqueue_style( 'wbcom-demo-listing-css' );
	}

	public function get_demo_installer_page_url( $args = array() ) {
		$current_url = admin_url();
		$installer_page_url = $current_url . 'admin.php?page=wbcom-theme-demo-installer';
		if( !empty( $args ) ) {
			$installer_page_url = add_query_arg(
				$args,
				$installer_page_url
			);
		}
		return $installer_page_url;
	}

}

endif;

/**
 * Main instance of WBCOM_TDI_ADMIN_SETTINGS.
 * @since  1.0.0
 * @return WBCOM_TDI_ADMIN_SETTINGS
 */
WBCOM_TDI_ADMIN_SETTINGS::instance();
?>