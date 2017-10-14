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

	public function render_page_for_added_menu() {
		
		if( isset( $_GET['success'] ) && ( $_GET['success'] == 'success' ) ) {
			delete_option( 'wbcom_theme_demo_import_data' );
			include_once 'success.php';
			return;
		}

		if( isset( $_GET['theme_slug'] ) && isset( $_GET['demo_slug'] ) ) {
			$wbcom_theme_demo_import_data = get_option( 'wbcom_theme_demo_import_data', array() );
			if( isset( $wbcom_theme_demo_import_data['plugins_installed'] ) && ( $wbcom_theme_demo_import_data['plugins_installed'] == 'OK' ) ) {
				echo "<div class='wrap wbcom-demo-importer'>";
					?>
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
			else {
				global $tgmpa;
				$tgmpa->install_plugins_page();
			}
			return;
		}


		$current_url = $this->get_demo_installer_page_url();
		$url_to_request = WBCOM_Theme_Demo_Installer_URL_TO_REQUEST;
		$response = wp_remote_post( $url_to_request, array(
			'method' => 'POST',
			'timeout' => 45,
			'headers' => array(),
			'body' => array( 
				'theme_name' => 'Reign',
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
						$href = $current_url . '&theme_slug=' . $value['theme_slug'] . '&demo_slug=' . $value['demo_slug'];
						?>
						<div class='wrap wbcom-demo-importer'>
							<div class="container">
								<form method="get" action="<?php echo $current_url; ?>">
									<img src="<?php echo $value['screenshot']; ?>" alt="Avatar" class="image" style="width:100%">
									<div class="middle">
										<a href="<?php echo $href; ?>" class="wbcom-button"><?php echo $value['demo_name']; ?></a>
									</div>
								</form>	
							</div>
						</div>	
						<?php
					}
				}
			}
		}
	}

	public function admin_enqueue_scripts() {
		$screen = get_current_screen();
		if ( $screen->id != 'toplevel_page_wbcom-theme-demo-installer' ) { return; }

		wp_register_script(
			$handle		=	'wbcom_theme_demo_installer_js',
			$src		=	WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'assets/js/importer.js',
			$deps		=	array( 'jquery' ),
			$ver		=	false,
			$in_footer	=	true
		);
		wp_localize_script(
			'wbcom_theme_demo_installer_js',
			'wbcom_theme_demo_installer_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'success_url' => $this->get_demo_installer_page_url( array( 'success' => 'success' ) )
			)
		);
		wp_enqueue_script( 'wbcom_theme_demo_installer_js' );

		wp_register_style(
			$handle		=	'wbcom-demo-listing-css',
			$src		=	WBCOM_Theme_Demo_Installer_PLUGIN_DIR_URL . 'assets/css/demo-listing.css',
			$deps		=	array(),
			$ver		=	false,
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