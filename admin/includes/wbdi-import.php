<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Check if its the next step in the installing process, the step that check the required plugins.
 */
if( isset( $_POST['wbdi_action'] ) && $_POST['wbdi_action'] == 'wbdi_import_demo_data' ) {
	$import_file = sanitize_text_field( $_POST['wbdi_import_file'] );
	?>
	<h4>
	<?php
		echo sprintf(__('You\'re going to import the file: %1$s', WBDI_TEXT_DOMAIN ), '<strong>' . esc_html($import_file) . '</strong>');
	?></h4>
	<div class="wbdi-final-confirmation">
		<p><?php _e( 'Just a final comfirmation required, as this import will wipe off all the existing data of your site and will install completely new data. Press the button below to start importing the demo data.', WBDI_TEXT_DOMAIN );?></p>
		<button class="wbdi-import button button-secondary" data-fileurl="<?php echo $import_file;?>"><?php _e( 'Import', WBDI_TEXT_DOMAIN );?></button>
	</div>
	<!-- <div id="progress-div">
		<div id="progress-bar"></div>
	</div> -->
	<?php
} else {
	$installers_pg_link = '<a href="'.admin_url( 'admin.php?page=wb-demo-installer' ).'" title="">'.__( 'Installers', WBDI_TEXT_DOMAIN ).'</a>';
	echo '<div class="wbdi-error"><p>'
	. sprintf(__('You need to select the demo from the %1$s tab and then check the plugins required to install the demo.', WBDI_TEXT_DOMAIN), '<strong>' . $installers_pg_link . '</strong>')
	. '</p></div>';
}
?>