<?php
/**
 * Enhanced Success page for Reign Demo Installer with detailed import summary
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

// Get current demo info and import summary
$current_demo = get_option( 'reign_current_demo_info', array() );
$demo_name = isset( $current_demo['demo_name'] ) ? $current_demo['demo_name'] : __( 'Reign Demo', 'reign-demo-installer' );
$import_summary = get_option( 'reign_demo_import_summary', array() );

// Determine import status based on summary
$import_status = 'success';
$status_message = __( 'Demo imported successfully!', 'reign-demo-installer' );
$status_icon = 'yes-alt';
$status_color = '#46b450';

if ( ! empty( $import_summary ) ) {
    $failed_count = isset( $import_summary['failed'] ) ? $import_summary['failed'] : 0;
    $skipped_count = isset( $import_summary['skipped'] ) ? $import_summary['skipped'] : 0;
    
    if ( $failed_count > 0 && $skipped_count > 0 ) {
        $import_status = 'partial';
        $status_message = __( 'Demo imported with some issues', 'reign-demo-installer' );
        $status_icon = 'warning';
        $status_color = '#fd7e14';
    } elseif ( $skipped_count > 0 ) {
        $import_status = 'success_with_skips';
        $status_message = __( 'Demo imported successfully', 'reign-demo-installer' );
        $status_icon = 'yes-alt';
        $status_color = '#46b450';
    } elseif ( $failed_count > 0 ) {
        $import_status = 'partial';
        $status_message = __( 'Demo imported with warnings', 'reign-demo-installer' );
        $status_icon = 'warning';
        $status_color = '#ffc107';
    }
}

?>
<div class="success-msg">
	<div class="success-icon">
		<span class="dashicons dashicons-<?php echo esc_attr( $status_icon ); ?>" 
			  style="font-size: 48px; color: <?php echo esc_attr( $status_color ); ?>;"></span>
	</div>
	
	<h2 style="color: <?php echo esc_attr( $status_color ); ?>;">
		<?php esc_html_e( 'Congratulations!', 'reign-demo-installer' ); ?>
	</h2>
	
	<p class="success-description">
		<?php echo esc_html( $status_message ); ?><br>
		<?php 
		printf( 
			esc_html__( '%s has been imported to your website.', 'reign-demo-installer' ),
			'<strong>' . esc_html( $demo_name ) . '</strong>'
		); 
		?>
	</p>

	<?php if ( ! empty( $import_summary ) ) : ?>
	<div class="import-summary-detailed">
		<h3><?php esc_html_e( 'Import Summary', 'reign-demo-installer' ); ?></h3>
		
		<div class="summary-stats-grid">
			<?php if ( isset( $import_summary['duration'] ) ) : ?>
				<div class="stat-card time">
					<div class="stat-icon">⏱️</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html( $import_summary['duration'] ); ?>s</div>
						<div class="stat-label"><?php esc_html_e( 'Import Time', 'reign-demo-installer' ); ?></div>
					</div>
				</div>
			<?php endif; ?>
			
			<?php if ( isset( $import_summary['downloads'] ) ) : ?>
				<div class="stat-card downloads">
					<div class="stat-icon">📥</div>
					<div class="stat-content">
						<div class="stat-number">
							<?php echo esc_html( $import_summary['downloads']['completed'] ); ?>/<?php echo esc_html( $import_summary['downloads']['total'] ); ?>
						</div>
						<div class="stat-label"><?php esc_html_e( 'Files Downloaded', 'reign-demo-installer' ); ?></div>
					</div>
				</div>
			<?php endif; ?>
			
			<?php if ( isset( $import_summary['processing'] ) ) : ?>
				<div class="stat-card processing">
					<div class="stat-icon">⚙️</div>
					<div class="stat-content">
						<div class="stat-number">
							<?php echo esc_html( $import_summary['processing']['completed'] ); ?>/<?php echo esc_html( $import_summary['processing']['total'] ); ?>
						</div>
						<div class="stat-label"><?php esc_html_e( 'Files Processed', 'reign-demo-installer' ); ?></div>
					</div>
				</div>
			<?php endif; ?>
			
			<?php if ( isset( $import_summary['skipped'] ) && $import_summary['skipped'] > 0 ) : ?>
				<div class="stat-card skipped">
					<div class="stat-icon">⏭️</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html( $import_summary['skipped'] ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Files Skipped', 'reign-demo-installer' ); ?></div>
					</div>
				</div>
			<?php endif; ?>
			
			<?php if ( isset( $import_summary['failed'] ) && $import_summary['failed'] > 0 ) : ?>
				<div class="stat-card failed">
					<div class="stat-icon">❌</div>
					<div class="stat-content">
						<div class="stat-number"><?php echo esc_html( $import_summary['failed'] ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Files Failed', 'reign-demo-installer' ); ?></div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		
		<?php
		// Show detailed information for skipped and failed files
		if ( isset( $import_summary['skippedFiles'] ) && ! empty( $import_summary['skippedFiles'] ) ||
			 isset( $import_summary['failedFiles'] ) && ! empty( $import_summary['failedFiles'] ) ) :
		?>
		<div class="detailed-results">
			<details class="import-details">
				<summary><?php esc_html_e( 'View Detailed Results', 'reign-demo-installer' ); ?></summary>
				<div class="details-content">
					
					<?php if ( isset( $import_summary['skippedFiles'] ) && ! empty( $import_summary['skippedFiles'] ) ) : ?>
					<div class="file-results skipped-files">
						<h4><?php esc_html_e( 'Skipped Files', 'reign-demo-installer' ); ?></h4>
						<p class="result-explanation">
							<?php esc_html_e( 'These files were skipped because they were optional and encountered issues. Your site will work normally without them.', 'reign-demo-installer' ); ?>
						</p>
						<ul class="file-list">
							<?php foreach ( $import_summary['skippedFiles'] as $file ) : ?>
								<li class="file-item">
									<div class="file-name"><?php echo esc_html( $file['name'] ?? 'Unknown file' ); ?></div>
									<div class="file-criticality <?php echo esc_attr( $file['criticality'] ?? 'optional' ); ?>">
										<?php echo esc_html( ucfirst( $file['criticality'] ?? 'Optional' ) ); ?>
									</div>
									<div class="file-reason"><?php echo esc_html( $file['reason'] ?? 'No reason provided' ); ?></div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<?php endif; ?>
					
					<?php if ( isset( $import_summary['failedFiles'] ) && ! empty( $import_summary['failedFiles'] ) ) : ?>
					<div class="file-results failed-files">
						<h4><?php esc_html_e( 'Failed Files', 'reign-demo-installer' ); ?></h4>
						<p class="result-explanation">
							<?php esc_html_e( 'These files failed to process. Depending on their importance, some features may not work as expected.', 'reign-demo-installer' ); ?>
						</p>
						<ul class="file-list">
							<?php foreach ( $import_summary['failedFiles'] as $file ) : ?>
								<li class="file-item">
									<div class="file-name"><?php echo esc_html( $file['name'] ?? 'Unknown file' ); ?></div>
									<div class="file-criticality <?php echo esc_attr( $file['criticality'] ?? 'optional' ); ?>">
										<?php echo esc_html( ucfirst( $file['criticality'] ?? 'Optional' ) ); ?>
									</div>
									<div class="file-stage"><?php echo esc_html( ucfirst( $file['stage'] ?? 'unknown' ) ); ?> failed</div>
									<div class="file-error"><?php echo esc_html( $file['error'] ?? 'No error message' ); ?></div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<?php endif; ?>
					
				</div>
			</details>
		</div>
		<?php endif; ?>
		
	</div>
	<?php endif; ?>

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

	<?php 
	// Show troubleshooting section if there were issues
	if ( $import_status === 'partial' || ( isset( $import_summary['failed'] ) && $import_summary['failed'] > 0 ) ) :
	?>
	<div class="troubleshooting-info">
		<h4><?php esc_html_e( 'Troubleshooting', 'reign-demo-installer' ); ?></h4>
		<div class="troubleshooting-content">
			<p><?php esc_html_e( 'Some files encountered issues during import. Here\'s what you can do:', 'reign-demo-installer' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Check your site - most functionality should work normally', 'reign-demo-installer' ); ?></li>
				<li><?php esc_html_e( 'Optional files that failed won\'t affect core functionality', 'reign-demo-installer' ); ?></li>
				<li><?php esc_html_e( 'You can try importing the demo again if needed', 'reign-demo-installer' ); ?></li>
				<li><?php 
					printf(
						esc_html__( 'Contact %ssupport%s if critical features aren\'t working', 'reign-demo-installer' ),
						'<a href="https://wbcomdesigns.com/support/" target="_blank">',
						'</a>'
					);
				?></li>
			</ul>
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

	<div class="cleanup-notice">
		<p class="description">
			<?php esc_html_e( 'You can safely remove the demo installer plugin if you won\'t be importing more demos.', 'reign-demo-installer' ); ?>
		</p>
	</div>
</div>

<style type="text/css">
.success-msg {
	max-width: 900px;
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
	margin-bottom: 15px;
}

.success-description {
	font-size: 18px;
	margin-bottom: 30px;
	color: #555;
}

/* Import Summary Styles */
.import-summary-detailed {
	background: #f8f9fa;
	border-radius: 8px;
	padding: 25px;
	margin: 30px 0;
	text-align: left;
}

.import-summary-detailed h3 {
	text-align: center;
	font-size: 24px;
	margin-bottom: 25px;
	color: #333;
}

.summary-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
	margin-bottom: 25px;
}

.stat-card {
	background: white;
	border-radius: 8px;
	padding: 20px;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	display: flex;
	align-items: center;
	gap: 15px;
}

.stat-icon {
	font-size: 24px;
	min-width: 40px;
}

.stat-content {
	flex: 1;
}

.stat-number {
	font-size: 24px;
	font-weight: bold;
	color: #333;
	line-height: 1;
}

.stat-label {
	font-size: 14px;
	color: #666;
	margin-top: 4px;
}

.stat-card.time { border-left: 4px solid #17a2b8; }
.stat-card.downloads { border-left: 4px solid #28a745; }
.stat-card.processing { border-left: 4px solid #007bff; }
.stat-card.skipped { border-left: 4px solid #ffc107; }
.stat-card.failed { border-left: 4px solid #dc3545; }

/* Detailed Results */
.detailed-results {
	margin-top: 20px;
}

.import-details {
	background: white;
	border-radius: 6px;
	overflow: hidden;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.import-details summary {
	background: #e9ecef;
	padding: 15px 20px;
	cursor: pointer;
	font-weight: bold;
	color: #495057;
	border-bottom: 1px solid #dee2e6;
}

.import-details summary:hover {
	background: #dee2e6;
}

.details-content {
	padding: 20px;
}

.file-results {
	margin-bottom: 25px;
}

.file-results h4 {
	color: #333;
	margin-bottom: 10px;
	font-size: 18px;
}

.result-explanation {
	color: #666;
	font-size: 14px;
	margin-bottom: 15px;
	line-height: 1.5;
}

.file-list {
	list-style: none;
	padding: 0;
	margin: 0;
}

.file-item {
	background: #f8f9fa;
	border-left: 4px solid #6c757d;
	padding: 12px 15px;
	margin-bottom: 8px;
	border-radius: 0 4px 4px 0;
}

.failed-files .file-item {
	border-left-color: #dc3545;
}

.skipped-files .file-item {
	border-left-color: #ffc107;
}

.file-name {
	font-family: monospace;
	font-weight: bold;
	color: #333;
	font-size: 14px;
}

.file-criticality {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 12px;
	font-size: 11px;
	font-weight: bold;
	text-transform: uppercase;
	margin: 5px 0;
}

.file-criticality.critical {
	background: #dc3545;
	color: white;
}

.file-criticality.important {
	background: #fd7e14;
	color: white;
}

.file-criticality.optional {
	background: #6c757d;
	color: white;
}

.file-stage {
	font-size: 12px;
	color: #dc3545;
	font-weight: bold;
	margin: 3px 0;
}

.file-reason,
.file-error {
	font-size: 12px;
	color: #666;
	margin: 3px 0;
	line-height: 1.3;
}

/* Existing styles */
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

.troubleshooting-info {
	background: #fff3cd;
	border-radius: 6px;
	padding: 20px;
	margin: 30px 0;
	border-left: 4px solid #ffc107;
	text-align: left;
}

.troubleshooting-info h4 {
	margin: 0 0 15px;
	color: #856404;
}

.troubleshooting-content {
	color: #856404;
}

.troubleshooting-content ul {
	margin: 10px 0 0;
	padding-left: 20px;
}

.troubleshooting-content li {
	margin-bottom: 8px;
	line-height: 1.4;
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
	
	.summary-stats-grid {
		grid-template-columns: 1fr;
	}
	
	.stat-card {
		text-align: center;
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
			'event_label': '<?php echo esc_js( $demo_name ); ?>',
			'import_status': '<?php echo esc_js( $import_status ); ?>'
		});
	}
	
	// Smooth scroll for details
	$('.import-details').on('toggle', function() {
		if (this.open) {
			setTimeout(function() {
				$('.import-details')[0].scrollIntoView({ 
					behavior: 'smooth', 
					block: 'nearest' 
				});
			}, 100);
		}
	});
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
	'import_timestamp' => time(),
	'import_status' => $import_status,
	'summary' => $import_summary
) );

// Clean up import summary after displaying
delete_option( 'reign_demo_import_summary' );

// Log cleanup completion
if ( class_exists( 'Reign_Demo_Installer_Logger' ) ) {
	Reign_Demo_Installer_Logger::info( 'Import cleanup completed successfully' );
}
?>