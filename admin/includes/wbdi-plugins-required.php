<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * Check if its the next step in the installing process, the step that check the required plugins.
 */
if( isset( $_POST['wbdi_select_demo'] ) && $_POST['wbdi_action'] == 'wbdi_check_plugins_required' ) {
	$import_file = sanitize_text_field( $_POST['wbdi_file_url'] );
	$req_plugins = str_replace( '\\', '', sanitize_text_field( $_POST['wbdi_req_plugins'] ) );
	$req_plugins = unserialize( $req_plugins );
	?>
	<table class="wp-list-table widefat fixed">
		<thead>
			<tr>
				<th class="column-primary">
					<a href="javascript:void(0);">
						<span><?php _e( 'Sr. No.', WBDI_TEXT_DOMAIN );?></span>
					</a>
				</th>
				<th><?php _e( 'Plugin', WBDI_TEXT_DOMAIN );?></th>
				<th>
					<a href="javascript:void(0);">
						<span><?php _e( 'Version', WBDI_TEXT_DOMAIN );?></span>
					</a>
				</th>
				<th><?php _e( 'Status', WBDI_TEXT_DOMAIN );?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach( $req_plugins as $index => $req_plugin ) {?>
				<?php 
				$if_plugin_installed = Wb_Demo_Installer::wbdi_check_if_plugin_is_installed( $req_plugin['plugin_slug'] );
				if( $if_plugin_installed ) {
					$if_plugin_active = Wb_Demo_Installer::wbdi_check_if_plugin_is_active( $req_plugin['plugin_slug'] );
					if( $if_plugin_active ) {
						$plugin_status = __( 'Active', WBDI_TEXT_DOMAIN );
					} else {
						$plugin_status = __( 'Installed But Not Active', WBDI_TEXT_DOMAIN );
					}
				} else {
					$plugin_status = __( 'Not Installed', WBDI_TEXT_DOMAIN );
				}
				?>
				<tr>
					<td class="column-primary"><?php echo ( $index + 1 ).'.';?></td>
					<td><?php echo $req_plugin['plugin_name'];?></td>
					<td><?php echo $req_plugin['plugin_version'];?></td>
					<td><?php echo $plugin_status;?></td>
				</tr>
			<?php }?>
		</tbody>
	</table>

	<?php
	$shall_proceed = '';
	foreach( $req_plugins as $index => $req_plugin ) {
		$if_plugin_installed = Wb_Demo_Installer::wbdi_check_if_plugin_is_installed( $req_plugin['plugin_slug'] );
		if( $if_plugin_installed ) {
			$if_plugin_active = Wb_Demo_Installer::wbdi_check_if_plugin_is_active( $req_plugin['plugin_slug'] );
			if( !$if_plugin_active ) {
				$shall_proceed = 'is-disabled';
				break;
			}
		} else {
			$shall_proceed = 'is-disabled';
			break;
		}
	}
	?>
	<div class="wbdi-proceed <?php echo $shall_proceed;?>">
		<form action="<?php echo admin_url( 'admin.php?page=wb-demo-installer&tab=wb-demo-installer-import' );?>" method="POST">
			<input type="hidden" name="wbdi_import_file" value="<?php echo $import_file;?>">
			<input type="hidden" name="wbdi_action" value="wbdi_import_demo_data">
			<input type="submit" name="wbdi_proceed_to_final_step" class="button button-primary" value="<?php _e( 'Proceed', WBDI_TEXT_DOMAIN );?>">
		</form>
	</div>
	<?php
} else {
	$installers_pg_link = '<a href="'.admin_url( 'admin.php?page=wb-demo-installer' ).'" title="">'.__( 'Installers', WBDI_TEXT_DOMAIN ).'</a>';
	echo '<div class="wbdi-error"><p>'
	. sprintf(__('You need to select the demo from the %1$s tab and then check the plugins required to install the demo.', WBDI_TEXT_DOMAIN), '<strong>' . $installers_pg_link . '</strong>')
	. '</p></div>';
}
?>