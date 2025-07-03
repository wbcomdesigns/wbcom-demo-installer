<?php
/**
 * Success page for Reign Demo Installer
 *
 * @package Reign_Demo_Installer
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Log successful import
if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
	Reign_Demo_Installer_Logger::log_import_success( 'Demo import completed' );
}

// Get current demo info for display
$current_demo = get_option( 'reign_current_demo_info', array() );
$demo_name = isset( $current_demo['demo_name'] ) ? $current_demo['demo_name'] : __( 'Reign Demo', 'reign-demo-installer' );

?>
<div class="success-msg">
	<div class="success-icon">
		<span class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #46b450;"></span>
	</div>
	
	<h2><?php esc_html_e( 'Congratulations!', 'reign-demo-installer' ); ?></h2>
	
	<p class="success-description">
		<?php 
		printf( 
			esc_html__( '%s has been successfully imported to your website.', 'reign-demo-installer' ),
			'<strong>' . esc_html( $demo_name ) . '</strong>'
		); 
		?>
	</p>

	<div class="success-details">
		<p><?php esc_html_e( 'Your website is now ready with:', 'reign-demo-installer' ); ?></p>
		<ul>
			<li><?php esc_html_e( '✓ Demo content and pages', 'reign-demo-installer' ); ?></li>
			<li><?php esc_html_e( '✓ Required plugins installed and activated', 'reign-demo-installer' ); ?></li>
			<li><?php esc_html_e( '✓ Theme customizations applied', 'reign-demo-installer' ); ?></li>
			<li><?php esc_html_e( '✓ Menus and widgets configured', 'reign-demo-installer' ); ?></li>
		</ul>
	</div>

	<div class="success-actions">
		<a href="<?php echo esc_url( get_home_url() ); ?>" 
		   title="<?php esc_attr_e( 'Visit your new website', 'reign-demo-installer' ); ?>" 
		   class="button button-primary button-hero" 
		   target="_blank">
			<span class="dashicons dashicons-external" style="margin-right: 5px;"></span>
			<?php esc_html_e( 'Visit Your Site', 'reign-demo-installer' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>" 
		   title="<?php esc_attr_e( 'Customize your website', 'reign-demo-installer' ); ?>" 
		   class="button button-secondary">
			<span class="dashicons dashicons-admin-customizer" style="margin-right: 5px;"></span>
			<?php esc_html_e( 'Customize', 'reign-demo-installer' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url() ); ?>" 
		   title="<?php esc_attr_e( 'Go to WordPress dashboard', 'reign-demo-installer' ); ?>" 
		   class="button button-secondary">
			<span class="dashicons dashicons-dashboard" style="margin-right: 5px;"></span>
			<?php esc_html_e( 'Dashboard', 'reign-demo-installer' ); ?>
		</a>
	</div>

	<?php if ( get_template() === 'reign' ) : ?>
	<div class="next-steps">
		<h3><?php esc_html_e( 'Next Steps', 'reign-demo-installer' ); ?></h3>
		<div class="next-steps-grid">
			<div class="next-step-item">
				<h4><?php esc_html_e( '1. Customize Your Content', 'reign-demo-installer' ); ?></h4>
				<p><?php esc_html_e( 'Replace demo content with your own text, images, and information.', 'reign-demo-installer' ); ?></p>
			</div>
			<div class="next-step-item">
				<h4><?php esc_html_e( '2. Configure Settings', 'reign-demo-installer' ); ?></h4>
				<p><?php esc_html_e( 'Adjust theme settings, colors, and layout options to match your brand.', 'reign-demo-installer' ); ?></p>
			</div>
			<div class="next-step-item">
				<h4><?php esc_html_e( '3. Set Up Your Community', 'reign-demo-installer' ); ?></h4>
				<p><?php esc_html_e( 'Configure community features, user registration, and member permissions.', 'reign-demo-installer' ); ?></p>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<div class="support-info">
		<h4><?php esc_html_e( 'Need Help?', 'reign-demo-installer' ); ?></h4>
		<p>
			<?php 
			printf(
				esc_html__( 'Check out our %1$sdocumentation%2$s or %3$scontact support%4$s if you need assistance.', 'reign-demo-installer' ),
				'<a href="https://wbcomdesigns.com/docs/reign-theme/" target="_blank">',
				'</a>',
				'<a href="https://wbcomdesigns.com/support/" target="_blank">',
				'</a>'
			);
			?>
		</p>
	</div>

	<?php
	// Display import summary if available
	$import_summary = get_option( 'reign_demo_import_summary', array() );
	if ( ! empty( $import_summary ) ) :
	?>
	<div class="import-summary">
		<h4><?php esc_html_e( 'Import Summary', 'reign-demo-installer' ); ?></h4>
		<div class="summary-stats">
			<?php if ( isset( $import_summary['posts_imported'] ) ) : ?>
				<span class="stat-item">
					<strong><?php echo esc_html( $import_summary['posts_imported'] ); ?></strong>
					<?php esc_html_e( 'Posts', 'reign-demo-installer' ); ?>
				</span>
			<?php endif; ?>
			
			<?php if ( isset( $import_summary['pages_imported'] ) ) : ?>
				<span class="stat-item">
					<strong><?php echo esc_html( $import_summary['pages_imported'] ); ?></strong>
					<?php esc_html_e( 'Pages', 'reign-demo-installer' ); ?>
				</span>
			<?php endif; ?>
			
			<?php if ( isset( $import_summary['plugins_activated'] ) ) : ?>
				<span class="stat-item">
					<strong><?php echo esc_html( $import_summary['plugins_activated'] ); ?></strong>
					<?php esc_html_e( 'Plugins', 'reign-demo-installer' ); ?>
				</span>
			<?php endif; ?>

			<?php if ( isset( $import_summary['duration'] ) ) : ?>
				<span class="stat-item">
					<strong><?php echo esc_html( round( $import_summary['duration'], 1 ) ); ?>s</strong>
					<?php esc_html_e( 'Import Time', 'reign-demo-installer' ); ?>
				</span>
			<?php endif; ?>
		</div>
	</div>
	<?php 
	// Clean up import summary after displaying
	delete_option( 'reign_demo_import_summary' );
	endif; 
	?>

	<div class="cleanup-notice">
		<p class="description">
			<?php esc_html_e( 'You can safely remove the demo installer plugin if you won\'t be importing more demos.', 'reign-demo-installer' ); ?>
		</p>
	</div>
</div>

<style type="text/css">
.success-msg {
	max-width: 800px;
	margin: 40px auto;
	padding: 40px;
	background-color: #fff;
	border-radius: 8px;
	text-align: center;
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
	border-top: 4px solid #46b450;
}

.success-icon {
	margin-bottom: 20px;
}

.success-msg h2 {
	font-size: 32px;
	color: #46b450;
	margin-bottom: 15px;
}

.success-description {
	font-size: 18px;
	margin-bottom: 30px;
	color: #555;
}

.success-details {
	background: #f8f9fa;
	border-radius: 6px;
	padding: 20px;
	margin: 30px 0;
	text-align: left;
}

.success-details ul {
	margin: 15px 0 0;
	padding-left: 0;
	list-style: none;
}

.success-details li {
	margin-bottom: 8px;
	font-size: 15px;
	color: #555;
}

.success-actions {
	margin: 30px 0;
}

.success-actions .button {
	margin: 0 10px 10px;
	display: inline-flex;
	align-items: center;
	font-size: 16px;
	padding: 12px 24px;
	height: auto;
	line-height: 1.4;
}

.success-actions .button-hero {
	font-size: 18px;
	padding: 15px 30px;
}

.next-steps {
	margin: 40px 0;
	text-align: left;
}

.next-steps h3 {
	text-align: center;
	font-size: 24px;
	margin-bottom: 25px;
	color: #333;
}

.next-steps-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.next-step-item {
	background: #f8f9fa;
	padding: 20px;
	border-radius: 6px;
	border-left: 4px solid #1d76da;
}

.next-step-item h4 {
	margin: 0 0 10px;
	color: #1d76da;
	font-size: 16px;
}

.next-step-item p {
	margin: 0;
	color: #555;
	font-size: 14px;
	line-height: 1.5;
}

.support-info {
	background: #e7f3ff;
	border-radius: 6px;
	padding: 20px;
	margin: 30px 0;
	border-left: 4px solid #2196f3;
}

.support-info h4 {
	margin: 0 0 10px;
	color: #1976d2;
}

.support-info p {
	margin: 0;
	color: #555;
}

.support-info a {
	color: #1976d2;
	text-decoration: none;
}

.support-info a:hover {
	text-decoration: underline;
}

.import-summary {
	background: #fff8e5;
	border-radius: 6px;
	padding: 20px;
	margin: 30px 0;
	border-left: 4px solid #ffa726;
}

.import-summary h4 {
	margin: 0 0 15px;
	color: #f57c00;
	text-align: center;
}

.summary-stats {
	display: flex;
	justify-content: center;
	flex-wrap: wrap;
	gap: 20px;
}

.stat-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	font-size: 14px;
	color: #666;
}

.stat-item strong {
	font-size: 18px;
	color: #f57c00;
	margin-bottom: 2px;
}

.cleanup-notice {
	margin-top: 30px;
	padding-top: 20px;
	border-top: 1px solid #eee;
}

.cleanup-notice .description {
	color: #666;
	font-style: italic;
}

/* Responsive design */
@media (max-width: 768px) {
	.success-msg {
		margin: 20px;
		padding: 30px 20px;
	}
	
	.success-actions .button {
		display: block;
		margin: 0 0 10px;
		width: 100%;
		justify-content: center;
	}
	
	.next-steps-grid {
		grid-template-columns: 1fr;
	}
	
	.summary-stats {
		flex-direction: column;
		gap: 10px;
	}
}

/* Animation */
.success-msg {
	animation: slideUp 0.6s ease-out;
}

@keyframes slideUp {
	from {
		opacity: 0;
		transform: translateY(20px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Add some interactive feedback
	$('.success-actions .button').hover(
		function() {
			$(this).css('transform', 'translateY(-2px)');
		},
		function() {
			$(this).css('transform', 'translateY(0)');
		}
	);

	// Auto-focus on primary action
	setTimeout(function() {
		$('.button-primary').focus();
	}, 1000);

	// Track successful import (if analytics available)
	if (typeof gtag !== 'undefined') {
		gtag('event', 'demo_import_success', {
			'event_category': 'reign_demo_installer',
			'event_label': '<?php echo esc_js( $demo_name ); ?>'
		});
	}
});
</script>

<?php
// Clean up temporary data after successful import
$cleanup_options = array(
	'reign_theme_demo_import_data',
	'reign_theme_demo_req_plugins',
	'reign_current_demo_info'
);

foreach ( $cleanup_options as $option ) {
	delete_option( $option );
}

// Set a flag to indicate successful import
update_option( 'reign_demo_installer_last_import', array(
	'demo_name' => $demo_name,
	'import_date' => current_time( 'mysql' ),
	'import_timestamp' => time()
) );

// Log cleanup completion
if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
	Reign_Demo_Installer_Logger::info( 'Import cleanup completed successfully' );
}
?>