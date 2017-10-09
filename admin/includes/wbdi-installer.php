<?php
if( ! defined( 'ABSPATH' ) ) exit(); //Exit if accessed directly

/**
 * Connect to the common server to access the directory
 * to access all the export files created.
 */
$ftp_server = 'ftp.wbcom.biz';
$ftp_usrnm = 'wbcomftp@wbcom.biz';
$ftp_passwrd = 'T]z4tcdZ!,E~';
$conn_id = ftp_connect( $ftp_server );
$login_result = ftp_login( $conn_id, $ftp_usrnm, $ftp_passwrd );
ftp_pasv( $conn_id, true );
$export_files = ftp_nlist( $conn_id, "/wb-demo-exporter/wp-content/uploads/wb-demo-exporter/" );

/**
 * Export only the JSON files.
 */
foreach( $export_files as $index => $file ) {
	$file_ext = pathinfo( basename( $file ), PATHINFO_EXTENSION );
	if( $file_ext != 'json' ) {
		unset( $export_files[$index] );
	}
}
?>
<div class="theme-browser rendered wbdi-installer-panel">
	<h4><?php _e( 'Just a click away to import your demo.', WBDI_TEXT_DOMAIN );?></h4>
	<div class="themes wp-clearfix wb-demo-installer">
		<?php if( ! empty( $export_files ) ) {?>
			<?php foreach( $export_files as $index => $file ) {?>
				<?php
				$file_url = 'https://wbcom.biz/wb-demo-exporter/wp-content/uploads/wb-demo-exporter/'.basename( $file );
				$response = wp_remote_get( esc_url_raw( $file_url ) );
				$response_code = wp_remote_retrieve_response_code( $response );
				if( $response_code == 200 ) {
					$file_data = json_decode( $response['body'] );
					?>
					<form action="<?php echo admin_url( 'admin.php?page=wb-demo-installer&tab=wb-demo-installer-plugins-required' );?>" method="POST">
						<div class="theme" tabindex="0">
							<div class="theme-screenshot">
								<img src="<?php echo $file_data->logo;?>" alt="">
							</div>
							<div class="theme-author">By BuddyBoss.com</div>
							<h2 class="theme-name" id="boss-name"><?php echo $file_data->title;?></h2>
							<div class="theme-actions">
								<input type="hidden" name="wbdi_file_url" value="<?php echo $file_url;?>">
								<input type="hidden" name="wbdi_req_plugins" value='<?php echo $file_data->req_plugins;?>'>
								<input type="hidden" name="wbdi_action" value="wbdi_check_plugins_required">
								<input type="submit" class="button button-primary" name="wbdi_select_demo" value="<?php _e( 'Select', WBDI_TEXT_DOMAIN );?>">
							</div>
						</div>
					</form>
				<?php }?>
			<?php }?>
		<?php } else {?>
			<div class="wbdi-no-exports">
				<p><?php _e( 'No Demo Import Data Available!', WBDI_TEXT_DOMAIN );?></p>
			</div>
		<?php }?>
	</div>
</div>
