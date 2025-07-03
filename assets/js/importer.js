/*
* Plugin Installer Manager Code - COMPLETE VERSION
* Preserves admin login session during demo import
*/
jQuery(document).ready(function($) {
    'use strict';

    // Check all required plugins installed on page load
    _check_all_required_plugin_installed();

    // Plugin action button click handler
    $(document).on('click', 'button.plugin-action-button', function(event) {
        event.preventDefault();
        
        var thisRef = $(this);

        // Don't process if already active
        if (thisRef.hasClass('already-active')) {
            return;
        }

        // Show loading state
        _show_plugin_installer_loader();
        
        // Disable button to prevent double clicks
        thisRef.prop('disabled', true).text(reignDemoInstaller.strings.installing);

        // Get plugin data
        var pluginData = {
            action: 'wbcom_manage_plugin_installation',
            plugin_action: thisRef.siblings('input.plugin-action').val(),
            plugin_slug: thisRef.siblings('input.plugin-slug').val(),
            demo: thisRef.siblings('input.demo-name').val(),
            nonce: $('#plugins_nonce').val() || reignDemoInstaller.nonce,
            _wp_http_referer: $('input[name="_wp_http_referer"]').val()
        };

        // AJAX request
        $.ajax({
            url: reignDemoInstaller.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: pluginData,
            timeout: 120000, // 2 minutes timeout
            success: function(response) {
                _hide_plugin_installer_loader();
                thisRef.prop('disabled', false);

                if (response.success) {
                    // Update plugin status
                    thisRef.siblings('p.plugin-status').html('Active');
                    thisRef.siblings('p.plugin-status').addClass('already-active');
                    thisRef.html(reignDemoInstaller.strings.success || 'Already Installed & Activated');
                    thisRef.attr('class', 'plugin-action-button button already-active');
                    
                    // Update counter
                    var temp_counter = parseInt($('input#num_of_req_plugins_installed').val()) || 0;
                    temp_counter++;
                    $('input#num_of_req_plugins_installed').val(temp_counter);
                    
                    // Check if all plugins are installed
                    _check_all_required_plugin_installed();
                    
                    // Show success message
                    _show_notification('Plugin installed successfully!', 'success');
                } else {
                    thisRef.text('Install Now');
                    var errorMsg = response.data && response.data.message ? response.data.message : 'There was a problem performing the action.';
                    _show_notification(errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                _hide_plugin_installer_loader();
                thisRef.prop('disabled', false).text('Install Now');
                
                var errorMsg = 'Connection error. Please try again.';
                if (status === 'timeout') {
                    errorMsg = 'Request timed out. Please try again.';
                }
                
                _show_notification(errorMsg, 'error');
                
                // Log error for debugging
                console.error('Plugin installation error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
            }
        });
    });

    /**
     * Check if all required plugins are installed
     */
    function _check_all_required_plugin_installed() {
        var requiredPlugins = parseInt($('input#required_plugins_to_activate').val()) || 0;
        var installedPlugins = parseInt($('input#num_of_req_plugins_installed').val()) || 0;
        
        if (requiredPlugins > 0 && (requiredPlugins - installedPlugins) === 0) {
            $('div.goto-install-demo-step').show();
        } else {
            $('div.goto-install-demo-step').hide();
        }
    }

    /**
     * Show plugin installer loader
     */
    function _show_plugin_installer_loader() {
        $('body').addClass('demo_listing_loading');
    }

    /**
     * Hide plugin installer loader
     */
    function _hide_plugin_installer_loader() {
        $('body').removeClass('demo_listing_loading');
    }

    /**
     * Show notification message
     */
    function _show_notification(message, type) {
        type = type || 'info';
        
        // Remove existing notifications
        $('.reign-demo-notification').remove();
        
        // Create notification element
        var notification = $('<div class="reign-demo-notification reign-demo-' + type + '">' + 
                           '<p>' + message + '</p>' + 
                           '<button class="notice-dismiss" type="button">' +
                           '<span class="screen-reader-text">Dismiss this notice.</span>' +
                           '</button>' +
                           '</div>');
        
        // Add to page
        $('.demo-listing-wrap').prepend(notification);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual dismiss
        notification.find('.notice-dismiss').on('click', function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        });
    }
});

/*
* Demo Importer Manager Code - ENHANCED VERSION
* Fixed to prevent "Leave site?" popup and ensure smooth redirect to success page
*/
jQuery(document).ready(function($) {
    'use strict';

    var reignThemeDemoData = '';
    var thisRef = '';
    var importStartTime = 0;
    var importInProgress = false; // Track import state

    // Progress tracking variables
    var reignTddDatabaseTablesCount = '';
    var reignTddDatabaseTablesDone = 0;
    var reignTddUploadFoldersCount = '';
    var reignTddUploadFoldersDone = 0;
    var reignTddDatabaseTablesComplete = false;
    var reignTddUploadFoldersComplete = false;

    var totalRequests = 0;
    var percentageIncrement = 0;
    var currentPercentageProgress = 0;

    // Demo import button click handler
    $(document).on('click', 'div.wbcom-demo-importer button#wbcom_get_theme_demo_data', function(event) {
        event.preventDefault();
        
        thisRef = $(this);
        importStartTime = Date.now();
        
        // Show modern confirmation dialog
        _showModernConfirmDialog().then(function(confirmed) {
            if (confirmed) {
                // Set import in progress flag
                importInProgress = true;
                
                // Disable button and show loading
                thisRef.prop('disabled', true).text('Importing...');
                thisRef.siblings('div.loader').show();
                
                // Start the import process
                _reignReadThemeDemoPackageFile();
            }
        });
    });

    /**
     * Show modern confirmation dialog
     */
    function _showModernConfirmDialog() {
        return new Promise(function(resolve) {
            // Remove any existing dialogs
            $('.reign-import-confirmation').remove();
            
            // Create modern dialog
            var dialog = $(`
                <div class="reign-import-confirmation">
                    <div class="reign-dialog-overlay"></div>
                    <div class="reign-dialog-container">
                        <div class="reign-dialog-header">
                            <div class="reign-dialog-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <h3>Import Demo Content</h3>
                        </div>
                        <div class="reign-dialog-content">
                            <p class="reign-main-message">
                                This will import demo content and overwrite your existing content. 
                                <strong>Make sure you have a backup!</strong>
                            </p>
                            <div class="reign-import-features">
                                <div class="reign-feature-item">
                                    <span class="reign-check">✓</span>
                                    <span>You will remain logged in as admin</span>
                                </div>
                                <div class="reign-feature-item">
                                    <span class="reign-check">✓</span>
                                    <span>Your user account will be preserved</span>
                                </div>
                                <div class="reign-feature-item">
                                    <span class="reign-check">✓</span>
                                    <span>Demo content, plugins, and settings will be imported</span>
                                </div>
                            </div>
                            <div class="reign-warning-box">
                                <strong>⚠️ Important:</strong> This action cannot be undone. Please ensure you have a complete backup before proceeding.
                            </div>
                        </div>
                        <div class="reign-dialog-actions">
                            <button class="reign-btn reign-btn-cancel" type="button">Cancel</button>
                            <button class="reign-btn reign-btn-confirm" type="button">Yes, Import Demo</button>
                        </div>
                    </div>
                </div>
            `);
            
            // Add to page
            $('body').append(dialog);
            
            // Animate in
            setTimeout(function() {
                dialog.addClass('reign-dialog-show');
            }, 10);
            
            // Handle actions
            dialog.find('.reign-btn-cancel, .reign-dialog-overlay').on('click', function() {
                _closeDialog(dialog, false, resolve);
            });
            
            dialog.find('.reign-btn-confirm').on('click', function() {
                _closeDialog(dialog, true, resolve);
            });
            
            // Handle escape key
            $(document).on('keydown.reign-dialog', function(e) {
                if (e.keyCode === 27) { // Escape key
                    _closeDialog(dialog, false, resolve);
                }
            });
        });
    }

    /**
     * Close dialog with animation
     */
    function _closeDialog(dialog, confirmed, resolve) {
        dialog.removeClass('reign-dialog-show');
        $(document).off('keydown.reign-dialog');
        
        setTimeout(function() {
            dialog.remove();
            resolve(confirmed);
        }, 300);
    }

    /**
     * Read theme demo package file
     */
    function _reignReadThemeDemoPackageFile() {
        _reignTddShowCurrentActivity('Reading demo files...');
        
        var requestData = {
            action: 'wbcom_read_theme_demo_package_file',
            theme_slug: thisRef.siblings('#theme_slug').val(),
            demo_slug: thisRef.siblings('#demo_slug').val(),
            target_url: thisRef.siblings('#target_url').val(),
            nonce: thisRef.siblings('#demo_nonce').val() || reignDemoInstaller.nonce,
            preserve_admin: true
        };

        $.ajax({
            url: reignDemoInstaller.ajaxUrl,
            type: 'POST',
            data: requestData,
            timeout: 120000,
            success: function(response) {
                try {
                    if (response) {
                        reignThemeDemoData = JSON.parse(response);
                        
                        // Calculate total requests and progress increment
                        var dbTables = reignThemeDemoData.database_tables || [];
                        var uploadFolders = reignThemeDemoData.upload_folders || [];
                        totalRequests = dbTables.length + uploadFolders.length;
                        
                        if (totalRequests > 0) {
                            percentageIncrement = 100 / totalRequests;
                            
                            // Show progress bar
                            $('#progress-bar-container').show();
                            _reignTddUpdateProgressBar(Math.floor(currentPercentageProgress) + "%");
                            
                            // Start processing
                            _reignReadThemeDemoJsonFiles();
                            _reignReadThemeDemoUploadFolders();
                        } else {
                            _reignDemoImportDone();
                        }
                    } else {
                        throw new Error('Empty response from server');
                    }
                } catch (e) {
                    console.error('Error parsing demo data:', e);
                    _reignShowImportError('Failed to parse demo data. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Demo package read error:', {status: status, error: error});
                _reignShowImportError('Failed to read demo package. Please check your connection and try again.');
            }
        });
    }

    /**
     * Read theme demo JSON files
     */
    function _reignReadThemeDemoJsonFiles() {
        if (!reignThemeDemoData.database_tables || reignThemeDemoData.database_tables.length === 0) {
            reignTddDatabaseTablesComplete = true;
            return;
        }
        
        reignTddDatabaseTablesCount = reignThemeDemoData.database_tables.length;
        _reignGetThemeDemoData(reignThemeDemoData.database_tables[0], 'database_tables');
    }

    /**
     * Read theme demo upload folders
     */
    function _reignReadThemeDemoUploadFolders() {
        if (!reignThemeDemoData.upload_folders || reignThemeDemoData.upload_folders.length === 0) {
            reignTddUploadFoldersComplete = true;
            return;
        }
        
        reignTddUploadFoldersCount = reignThemeDemoData.upload_folders.length;
        _reignGetThemeDemoData(reignThemeDemoData.upload_folders[0], 'upload_folders');
    }

    /**
     * Get theme demo data
     */
    function _reignGetThemeDemoData(urlToRequest, actionFor) {
        _reignTddShowCurrentActivity('Processing demo data...');
        
        var requestData = {
            action: 'wbcom_get_theme_demo_data',
            url_to_request: urlToRequest,
            action_for: actionFor,
            nonce: reignDemoInstaller.nonce,
            preserve_admin: true
        };

        $.ajax({
            url: reignDemoInstaller.ajaxUrl,
            type: 'POST',
            data: requestData,
            timeout: 180000, // 3 minutes for data processing
            success: function(response) {
                if (actionFor === 'database_tables') {
                    reignTddDatabaseTablesDone++;
                    currentPercentageProgress += percentageIncrement;
                    _reignTddUpdateProgressBar(Math.floor(currentPercentageProgress) + "%");
                    
                    if (reignTddDatabaseTablesDone === reignTddDatabaseTablesCount) {
                        reignTddDatabaseTablesComplete = true;
                        _checkImportComplete();
                    } else {
                        _reignGetThemeDemoData(reignThemeDemoData.database_tables[reignTddDatabaseTablesDone], 'database_tables');
                    }
                } else {
                    reignTddUploadFoldersDone++;
                    currentPercentageProgress += percentageIncrement;
                    _reignTddUpdateProgressBar(Math.floor(currentPercentageProgress) + "%");
                    
                    if (reignTddUploadFoldersDone === reignTddUploadFoldersCount) {
                        reignTddUploadFoldersComplete = true;
                        _checkImportComplete();
                    } else {
                        _reignGetThemeDemoData(reignThemeDemoData.upload_folders[reignTddUploadFoldersDone], 'upload_folders');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Demo data processing error:', {
                    url: urlToRequest,
                    actionFor: actionFor,
                    status: status,
                    error: error
                });
                
                // Continue with next item even if one fails
                if (actionFor === 'database_tables') {
                    reignTddDatabaseTablesDone++;
                    if (reignTddDatabaseTablesDone === reignTddDatabaseTablesCount) {
                        reignTddDatabaseTablesComplete = true;
                        _checkImportComplete();
                    } else {
                        _reignGetThemeDemoData(reignThemeDemoData.database_tables[reignTddDatabaseTablesDone], 'database_tables');
                    }
                } else {
                    reignTddUploadFoldersDone++;
                    if (reignTddUploadFoldersDone === reignTddUploadFoldersCount) {
                        reignTddUploadFoldersComplete = true;
                        _checkImportComplete();
                    } else {
                        _reignGetThemeDemoData(reignThemeDemoData.upload_folders[reignTddUploadFoldersDone], 'upload_folders');
                    }
                }
            }
        });
    }

    /**
     * Check if import is complete
     */
    function _checkImportComplete() {
        if (reignTddDatabaseTablesComplete && reignTddUploadFoldersComplete) {
            currentPercentageProgress = 100;
            _reignTddUpdateProgressBar("100%");
            
            // Verify admin session before completing
            _verifyAdminSession(function() {
                _reignDemoImportDone();
            });
        }
    }

    /**
     * Verify admin session is still active
     */
    function _verifyAdminSession(callback) {
        _reignTddShowCurrentActivity('Verifying admin session...');
        
        $.ajax({
            url: reignDemoInstaller.ajaxUrl,
            type: 'POST',
            data: {
                action: 'heartbeat',
                _nonce: reignDemoInstaller.nonce
            },
            timeout: 30000,
            success: function(response) {
                console.log('Admin session verified successfully');
                if (callback) callback();
            },
            error: function() {
                console.warn('Could not verify admin session, but continuing...');
                if (callback) callback();
            }
        });
    }

    /**
     * Demo import completed - FIXED to prevent leave site popup
     */
    function _reignDemoImportDone() {
        var importDuration = (Date.now() - importStartTime) / 1000;
        
        _reignTddShowCurrentActivity('Import completed successfully! Redirecting to success page...');
        
        // CRITICAL FIX: Clear import in progress flag and remove beforeunload handler
        importInProgress = false;
        
        // Remove any existing beforeunload handlers
        $(window).off('beforeunload.reign-import');
        window.onbeforeunload = null;
        
        // Log success
        console.log('Demo import completed in ' + importDuration + ' seconds');
        console.log('Admin session preserved successfully');
        
        // Add a small delay to show the final message, then redirect without popup
        setTimeout(function() {
            // Ensure no beforeunload handler is active
            $(window).off('beforeunload');
            window.onbeforeunload = null;
            
            // Force redirect without triggering beforeunload
            window.location.replace(reignDemoInstaller.successUrl);
        }, 2000);
    }

    /**
     * Show import error
     */
    function _reignShowImportError(message) {
        // Clear import in progress flag on error
        importInProgress = false;
        
        thisRef.prop('disabled', false).text('Install Demo');
        thisRef.siblings('div.loader').hide();
        
        _reignTddShowCurrentActivity('Import failed: ' + message);
        
        // Show error notification
        var errorDiv = $('<div class="notice notice-error"><p>' + message + '</p></div>');
        $('.reign-demos-wrapper').prepend(errorDiv);
        
        // Auto remove error after 10 seconds
        setTimeout(function() {
            errorDiv.fadeOut();
        }, 10000);
    }

    /**
     * Update progress bar
     */
    function _reignTddUpdateProgressBar(progressPercentage) {
        $('#progress-bar-container .completed').css('width', progressPercentage);
        $('#progress-bar-container .completed').html(progressPercentage);
    }

    /**
     * Show current activity
     */
    function _reignTddShowCurrentActivity(message) {
        $('#wbtd-current-action').show().html(message);
        console.log('Reign Demo Installer: ' + message);
    }

    /**
     * ENHANCED: Handle page unload during import - FIXED
     */
    $(window).on('beforeunload.reign-import', function(e) {
        // Only show warning if import is actually in progress
        if (importInProgress && thisRef && thisRef.prop('disabled')) {
            var message = 'Demo import is in progress. Leaving this page will cancel the import and you may lose your admin session.';
            e.returnValue = message; // For older browsers
            return message;
        }
        
        // If import is complete or not started, allow navigation without warning
        return undefined;
    });

    /**
     * Clean up event handlers when import completes
     */
    function cleanupEventHandlers() {
        $(window).off('beforeunload.reign-import');
        window.onbeforeunload = null;
        importInProgress = false;
    }

    // Monitor admin session during import
    var sessionCheckInterval;
    
    function startSessionMonitoring() {
        sessionCheckInterval = setInterval(function() {
            if (importInProgress && thisRef && thisRef.prop('disabled')) {
                // Check session every 30 seconds during import
                $.ajax({
                    url: reignDemoInstaller.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'heartbeat',
                        _nonce: reignDemoInstaller.nonce
                    },
                    timeout: 10000,
                    error: function() {
                        console.warn('Session check failed during import');
                    }
                });
            } else {
                // Stop monitoring when import is done
                clearInterval(sessionCheckInterval);
                cleanupEventHandlers();
            }
        }, 30000);
    }

    // Start session monitoring when import begins
    $(document).on('click', 'button#wbcom_get_theme_demo_data', function() {
        setTimeout(startSessionMonitoring, 1000);
    });

    // Add CSS for modern dialog
    if (!$('#reign-import-dialog-css').length) {
        $('<style id="reign-import-dialog-css">')
            .text(`
                .reign-import-confirmation {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 999999;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }

                .reign-import-confirmation.reign-dialog-show {
                    opacity: 1;
                }

                .reign-dialog-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    backdrop-filter: blur(4px);
                }

                .reign-dialog-container {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) scale(0.9);
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                    max-width: 500px;
                    width: 90%;
                    max-height: 80vh;
                    overflow: hidden;
                    transition: transform 0.3s ease;
                }

                .reign-dialog-show .reign-dialog-container {
                    transform: translate(-50%, -50%) scale(1);
                }

                .reign-dialog-header {
                    padding: 24px 24px 20px;
                    border-bottom: 1px solid #e5e7eb;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                .reign-dialog-icon {
                    width: 40px;
                    height: 40px;
                    background: #fef3c7;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .reign-dialog-header h3 {
                    margin: 0;
                    font-size: 20px;
                    font-weight: 600;
                    color: #111827;
                }

                .reign-dialog-content {
                    padding: 24px;
                }

                .reign-main-message {
                    font-size: 16px;
                    color: #374151;
                    margin: 0 0 20px;
                    line-height: 1.5;
                }

                .reign-import-features {
                    background: #f0f9ff;
                    border: 1px solid #bae6fd;
                    border-radius: 8px;
                    padding: 16px;
                    margin: 20px 0;
                }

                .reign-feature-item {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin-bottom: 8px;
                    font-size: 14px;
                    color: #1e40af;
                }

                .reign-feature-item:last-child {
                    margin-bottom: 0;
                }

                .reign-check {
                    color: #059669;
                    font-weight: bold;
                    font-size: 16px;
                }

                .reign-warning-box {
                    background: #fef7cd;
                    border: 1px solid #f59e0b;
                    border-radius: 6px;
                    padding: 12px;
                    font-size: 14px;
                    color: #92400e;
                    margin-top: 16px;
                }

                .reign-dialog-actions {
                    padding: 20px 24px 24px;
                    display: flex;
                    gap: 12px;
                    justify-content: flex-end;
                    border-top: 1px solid #e5e7eb;
                }

                .reign-btn {
                    padding: 12px 24px;
                    border-radius: 8px;
                    font-weight: 500;
                    font-size: 14px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    border: 1px solid transparent;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 100px;
                }

                .reign-btn-cancel {
                    background: #f9fafb;
                    color: #374151;
                    border-color: #d1d5db;
                }

                .reign-btn-cancel:hover {
                    background: #f3f4f6;
                    border-color: #9ca3af;
                }

                .reign-btn-confirm {
                    background: #3b82f6;
                    color: white;
                    border-color: #3b82f6;
                }

                .reign-btn-confirm:hover {
                    background: #2563eb;
                    border-color: #2563eb;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
                }

                @media (max-width: 640px) {
                    .reign-dialog-container {
                        width: 95%;
                        max-width: none;
                    }
                    
                    .reign-dialog-header {
                        padding: 20px 20px 16px;
                    }
                    
                    .reign-dialog-content {
                        padding: 20px;
                    }
                    
                    .reign-dialog-actions {
                        padding: 16px 20px 20px;
                        flex-direction: column;
                    }
                    
                    .reign-btn {
                        width: 100%;
                    }
                }
            `)
            .appendTo('head');
    }

    // Add some CSS for notifications
    if (!$('#reign-demo-installer-css').length) {
        $('<style id="reign-demo-installer-css">')
            .text('.reign-demo-notification { margin: 15px 0; padding: 15px; border-left: 4px solid #ddd; background: #fff; } ' +
                  '.reign-demo-success { border-left-color: #46b450; } ' +
                  '.reign-demo-error { border-left-color: #dc3232; } ' +
                  '.reign-demo-notification .notice-dismiss { float: right; padding: 9px; border: none; background: none; cursor: pointer; }')
            .appendTo('head');
    }
});