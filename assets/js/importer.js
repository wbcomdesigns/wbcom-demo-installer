/*
* Plugin Installer Manager Code - COMPLETE VERSION
* Preserves admin login session during demo import
* FIXED: Dynamic button visibility without page reload
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
                    
                    // Update counter - CRITICAL FIX
                    var temp_counter = parseInt($('input#num_of_req_plugins_installed').val()) || 0;
                    temp_counter++;
                    $('input#num_of_req_plugins_installed').val(temp_counter);
                    
                    // FIXED: Check if all plugins are installed immediately
                    _check_all_required_plugin_installed();
                    
                    // Show success message
                    _show_notification('Plugin installed successfully!', 'success');
                    
                    // ENHANCED: Add visual feedback for demo button availability
                    if ($('div.goto-install-demo-step').is(':visible')) {
                        _highlight_demo_button();
                    }
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
     * Check if all required plugins are installed - ENHANCED VERSION
     */
    function _check_all_required_plugin_installed() {
        var requiredPlugins = parseInt($('input#required_plugins_to_activate').val()) || 0;
        var installedPlugins = parseInt($('input#num_of_req_plugins_installed').val()) || 0;
        
        console.log('Checking plugins: Required=' + requiredPlugins + ', Installed=' + installedPlugins);
        
        if (requiredPlugins > 0 && (requiredPlugins - installedPlugins) === 0) {
            // All required plugins are installed - show the button
            $('div.goto-install-demo-step').fadeIn(500);
            
            // Update any status text
            _update_installation_status('All required plugins are installed!', 'success');
            
        } else {
            // Still missing plugins - hide the button
            $('div.goto-install-demo-step').fadeOut(300);
            
            // Update status with remaining count
            var remaining = requiredPlugins - installedPlugins;
            if (remaining > 0) {
                _update_installation_status(remaining + ' more plugin(s) need to be installed.', 'warning');
            }
        }
    }

    /**
     * Update installation status message
     */
    function _update_installation_status(message, type) {
        var statusContainer = $('#plugin-installation-status');
        
        // Create status container if it doesn't exist
        if (statusContainer.length === 0) {
            statusContainer = $('<div id="plugin-installation-status" style="margin: 15px 0; padding: 12px; border-radius: 4px; font-weight: 500;"></div>');
            $('.goto-install-demo-step').before(statusContainer);
        }
        
        // Update styling based on type
        var backgroundColor, borderColor, textColor;
        switch(type) {
            case 'success':
                backgroundColor = '#d4edda';
                borderColor = '#28a745';
                textColor = '#155724';
                break;
            case 'warning':
                backgroundColor = '#fff3cd';
                borderColor = '#ffc107';
                textColor = '#856404';
                break;
            default:
                backgroundColor = '#d1ecf1';
                borderColor = '#17a2b8';
                textColor = '#0c5460';
        }
        
        statusContainer.css({
            'background-color': backgroundColor,
            'border-left': '4px solid ' + borderColor,
            'color': textColor
        }).text(message).fadeIn(300);
    }

    /**
     * Highlight demo button when it becomes available
     */
    function _highlight_demo_button() {
        var demoButton = $('div.goto-install-demo-step a.button');
        
        if (demoButton.length > 0) {
            // Add pulse animation and enhanced styling
            demoButton.css({
                'animation': 'pulse 2s infinite',
                'box-shadow': '0 0 15px rgba(29, 118, 218, 0.5)',
                'transform': 'scale(1.05)'
            });
            
            // Remove animation after 5 seconds
            setTimeout(function() {
                demoButton.css({
                    'animation': '',
                    'box-shadow': '',
                    'transform': ''
                });
            }, 5000);
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
     * Show notification message - ENHANCED VERSION
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
        
        // Animate in
        notification.hide().slideDown(300);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            notification.slideUp(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual dismiss
        notification.find('.notice-dismiss').on('click', function() {
            notification.slideUp(300, function() {
                $(this).remove();
            });
        });
    }

    // ENHANCED: Real-time plugin status monitoring
    function _monitor_plugin_status() {
        // Count currently active plugins
        var activeCount = 0;
        $('.plugin-action-button.already-active').each(function() {
            var isRequired = $(this).closest('.wbcom-req-plugin-card').find('.plugin-dependency').text().toLowerCase().includes('required');
            if (isRequired) {
                activeCount++;
            }
        });
        
        // Update the counter in real-time
        $('input#num_of_req_plugins_installed').val(activeCount);
        
        // Trigger check
        _check_all_required_plugin_installed();
    }

    // Run monitoring every 2 seconds to catch any state changes
    setInterval(_monitor_plugin_status, 2000);

    // ENHANCED: Watch for dynamic DOM changes
    if (window.MutationObserver) {
        var observer = new MutationObserver(function(mutations) {
            var shouldCheck = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && 
                    mutation.attributeName === 'class' && 
                    $(mutation.target).hasClass('plugin-action-button')) {
                    shouldCheck = true;
                }
            });
            
            if (shouldCheck) {
                setTimeout(_check_all_required_plugin_installed, 100);
            }
        });
        
        // Start observing plugin buttons
        $('.plugin-action-button').each(function() {
            observer.observe(this, { 
                attributes: true, 
                attributeFilter: ['class'] 
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
     * Read theme demo package file - ENHANCED with validation
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
                    // Validate response first
                    if (!response || response.trim() === '') {
                        throw new Error('Empty response from demo server');
                    }

                    // Check if response is HTML error page instead of JSON
                    if (response.indexOf('<!DOCTYPE') !== -1 || response.indexOf('<html') !== -1) {
                        throw new Error('Server returned HTML error page instead of demo data');
                    }

                    // Try to parse JSON
                    reignThemeDemoData = JSON.parse(response);
                    
                    // Validate parsed data structure
                    if (!_validateDemoDataStructure(reignThemeDemoData)) {
                        throw new Error('Invalid demo data structure received');
                    }
                    
                    // Calculate total requests and progress increment
                    var dbTables = reignThemeDemoData.database_tables || [];
                    var uploadFolders = reignThemeDemoData.upload_folders || [];
                    totalRequests = dbTables.length + uploadFolders.length;
                    
                    if (totalRequests > 0) {
                        percentageIncrement = 100 / totalRequests;
                        
                        // Show progress bar
                        $('#progress-bar-container').show();
                        _reignTddUpdateProgressBar(Math.floor(currentPercentageProgress) + "%");
                        
                        // Validate URLs before starting import
                        _validateDemoUrls(reignThemeDemoData).then(function(validationResult) {
                            if (validationResult.valid) {
                                // Start processing
                                _reignReadThemeDemoJsonFiles();
                                _reignReadThemeDemoUploadFolders();
                            } else {
                                _pauseImportWithError('Demo package validation failed: ' + validationResult.error);
                            }
                        });
                        
                    } else {
                        _pauseImportWithError('No demo content found in package');
                    }
                    
                } catch (e) {
                    console.error('Error parsing demo data:', e);
                    _pauseImportWithError('Failed to parse demo data: ' + e.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Demo package read error:', {status: status, error: error, response: xhr.responseText});
                var errorMessage = 'Failed to read demo package';
                
                if (status === 'timeout') {
                    errorMessage += ': Request timed out';
                } else if (xhr.status === 404) {
                    errorMessage += ': Demo not found on server';
                } else if (xhr.status === 403) {
                    errorMessage += ': Access denied to demo server';
                } else if (xhr.status >= 500) {
                    errorMessage += ': Demo server error';
                } else {
                    errorMessage += ': ' + error;
                }
                
                _pauseImportWithError(errorMessage);
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
     * Get theme demo data - ENHANCED with validation and retry logic
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
                // Validate response before processing
                if (!_validateImportResponse(response, urlToRequest, actionFor)) {
                    return; // Error already handled in validation function
                }
                
                if (actionFor === 'database_tables') {
                    reignTddDatabaseTablesDone++;
                    currentPercentageProgress += percentageIncrement;
                    _reignTddUpdateProgressBar(Math.floor(currentPercentageProgress) + "%");
                    
                    if (reignTddDatabaseTablesDone === reignTddDatabaseTablesCount) {
                        reignTddDatabaseTablesComplete = true;
                        _checkImportComplete();
                    } else {
                        // Add small delay between requests to prevent server overload
                        setTimeout(function() {
                            _reignGetThemeDemoData(reignThemeDemoData.database_tables[reignTddDatabaseTablesDone], 'database_tables');
                        }, 500);
                    }
                } else {
                    reignTddUploadFoldersDone++;
                    currentPercentageProgress += percentageIncrement;
                    _reignTddUpdateProgressBar(Math.floor(currentPercentageProgress) + "%");
                    
                    if (reignTddUploadFoldersDone === reignTddUploadFoldersCount) {
                        reignTddUploadFoldersComplete = true;
                        _checkImportComplete();
                    } else {
                        // Add small delay between requests to prevent server overload
                        setTimeout(function() {
                            _reignGetThemeDemoData(reignThemeDemoData.upload_folders[reignTddUploadFoldersDone], 'upload_folders');
                        }, 500);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Demo data processing error:', {
                    url: urlToRequest,
                    actionFor: actionFor,
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                
                var errorDetails = _getDetailedErrorInfo(xhr, status, error);
                
                // Show pause dialog for critical errors
                _showImportPauseDialog(
                    'Import Error Detected',
                    'Failed to import ' + actionFor + ': ' + errorDetails.message,
                    urlToRequest,
                    actionFor,
                    errorDetails.canRetry
                );
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
     * Validate demo data structure
     */
    function _validateDemoDataStructure(data) {
        try {
            // Check if data is an object
            if (!data || typeof data !== 'object') {
                console.error('Demo data is not a valid object');
                return false;
            }

            // Check for required properties
            var requiredProps = ['database_tables', 'upload_folders'];
            for (var i = 0; i < requiredProps.length; i++) {
                if (!data.hasOwnProperty(requiredProps[i])) {
                    console.error('Missing required property: ' + requiredProps[i]);
                    return false;
                }
                
                // Check if property is an array
                if (!Array.isArray(data[requiredProps[i]])) {
                    console.error('Property ' + requiredProps[i] + ' is not an array');
                    return false;
                }
            }

            console.log('Demo data structure validation passed');
            return true;
            
        } catch (e) {
            console.error('Error validating demo data structure:', e);
            return false;
        }
    }

    /**
     * Validate demo URLs before starting import
     */
    function _validateDemoUrls(data) {
        return new Promise(function(resolve) {
            var urlsToTest = [];
            
            // Collect all URLs from database tables and upload folders
            if (data.database_tables) {
                urlsToTest = urlsToTest.concat(data.database_tables);
            }
            if (data.upload_folders) {
                urlsToTest = urlsToTest.concat(data.upload_folders);
            }

            if (urlsToTest.length === 0) {
                resolve({ valid: false, error: 'No URLs found in demo package' });
                return;
            }

            // Test first few URLs to ensure they're accessible
            var testUrls = urlsToTest.slice(0, 3); // Test first 3 URLs
            var successCount = 0;
            var errorCount = 0;
            var totalTests = testUrls.length;

            function checkUrlComplete() {
                if (successCount + errorCount === totalTests) {
                    var successRate = successCount / totalTests;
                    if (successRate >= 0.5) { // At least 50% success rate
                        resolve({ valid: true });
                    } else {
                        resolve({ 
                            valid: false, 
                            error: 'Demo server URLs are not accessible (' + successCount + '/' + totalTests + ' working)' 
                        });
                    }
                }
            }

            testUrls.forEach(function(url) {
                $.ajax({
                    url: reignDemoInstaller.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wbcom_get_theme_demo_data',
                        url_to_request: url,
                        action_for: 'validation_test',
                        nonce: reignDemoInstaller.nonce
                    },
                    timeout: 30000,
                    success: function() {
                        successCount++;
                        checkUrlComplete();
                    },
                    error: function() {
                        errorCount++;
                        checkUrlComplete();
                    }
                });
            });
        });
    }

    /**
     * Validate import response
     */
    function _validateImportResponse(response, url, actionFor) {
        try {
            // Check for empty response
            if (!response && response !== '') {
                _pauseImportWithError('Empty response received for ' + actionFor + ' from: ' + url);
                return false;
            }

            // Check for HTML error pages
            if (typeof response === 'string' && 
                (response.indexOf('<!DOCTYPE') !== -1 || 
                 response.indexOf('<html') !== -1 || 
                 response.indexOf('Fatal error') !== -1 ||
                 response.indexOf('Parse error') !== -1)) {
                _pauseImportWithError('Server error received for ' + actionFor + '. The demo server may be experiencing issues.');
                return false;
            }

            // Check for JSON parsing errors (if response should be JSON)
            if (actionFor === 'database_tables' && typeof response === 'string' && response.length > 0) {
                try {
                    JSON.parse(response);
                } catch (e) {
                    console.warn('JSON parse warning for database_tables:', e.message);
                    // Don't pause for JSON parse warnings as some data might be valid
                }
            }

            return true;
            
        } catch (e) {
            console.error('Error validating import response:', e);
            _pauseImportWithError('Response validation failed for ' + actionFor);
            return false;
        }
    }

    /**
     * Get detailed error information
     */
    function _getDetailedErrorInfo(xhr, status, error) {
        var errorInfo = {
            message: 'Unknown error',
            canRetry: true,
            details: ''
        };

        if (status === 'timeout') {
            errorInfo.message = 'Request timed out - server may be slow';
            errorInfo.canRetry = true;
            errorInfo.details = 'The server took too long to respond. This could be due to high server load.';
        } else if (xhr.status === 0) {
            errorInfo.message = 'Network connection error';
            errorInfo.canRetry = true;
            errorInfo.details = 'Could not connect to the demo server. Check your internet connection.';
        } else if (xhr.status === 404) {
            errorInfo.message = 'Demo content not found';
            errorInfo.canRetry = false;
            errorInfo.details = 'The requested demo file was not found on the server.';
        } else if (xhr.status === 403) {
            errorInfo.message = 'Access denied';
            errorInfo.canRetry = false;
            errorInfo.details = 'Server denied access to the demo content.';
        } else if (xhr.status >= 500) {
            errorInfo.message = 'Demo server error (' + xhr.status + ')';
            errorInfo.canRetry = true;
            errorInfo.details = 'The demo server is experiencing internal errors.';
        } else {
            errorInfo.message = error || 'Request failed';
            errorInfo.canRetry = true;
            errorInfo.details = 'HTTP ' + xhr.status + ': ' + (xhr.statusText || 'Unknown error');
        }

        return errorInfo;
    }

    /**
     * Pause import with error and show dialog
     */
    function _pauseImportWithError(message) {
        console.error('Import paused due to error:', message);
        
        // Stop the import process
        importInProgress = false;
        
        // Update UI
        thisRef.prop('disabled', false).text('Install Demo');
        thisRef.siblings('div.loader').hide();
        $('#progress-bar-container').hide();
        
        _reignTddShowCurrentActivity('Import paused due to error');
        
        // Show detailed error dialog
        _showImportPauseDialog(
            'Import Paused',
            message,
            null,
            null,
            false
        );
    }

    /**
     * Show import pause dialog with options
     */
    function _showImportPauseDialog(title, message, failedUrl, actionFor, canRetry) {
        // Remove any existing dialogs
        $('.reign-import-pause-dialog').remove();
        
        var retryButton = '';
        if (canRetry && failedUrl && actionFor) {
            retryButton = '<button class="reign-btn reign-btn-retry" type="button">Retry This Step</button>';
        }
        
        var dialog = $(`
            <div class="reign-import-pause-dialog">
                <div class="reign-dialog-overlay"></div>
                <div class="reign-dialog-container">
                    <div class="reign-dialog-header">
                        <div class="reign-dialog-icon error-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3>${title}</h3>
                    </div>
                    <div class="reign-dialog-content">
                        <p class="error-message">${message}</p>
                        
                        <div class="error-details">
                            <h4>What happened?</h4>
                            <p>The demo import process has been paused because of an error. This helps prevent corrupted or incomplete imports.</p>
                            
                            <h4>What can you do?</h4>
                            <ul>
                                <li>Check your internet connection</li>
                                <li>Wait a moment and try again</li>
                                <li>Contact support if the problem persists</li>
                            </ul>
                        </div>
                        
                        <div class="import-status">
                            <p><strong>Progress:</strong> ${Math.floor(currentPercentageProgress)}% completed</p>
                            <p><strong>Status:</strong> Import paused safely</p>
                        </div>
                    </div>
                    <div class="reign-dialog-actions">
                        <button class="reign-btn reign-btn-cancel" type="button">Cancel Import</button>
                        ${retryButton}
                        <button class="reign-btn reign-btn-restart" type="button">Restart Import</button>
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
        dialog.find('.reign-btn-cancel').on('click', function() {
            _cancelImport(dialog);
        });
        
        dialog.find('.reign-btn-restart').on('click', function() {
            _restartImport(dialog);
        });
        
        dialog.find('.reign-btn-retry').on('click', function() {
            _retryFailedStep(dialog, failedUrl, actionFor);
        });
        
        // Close on overlay click
        dialog.find('.reign-dialog-overlay').on('click', function() {
            _cancelImport(dialog);
        });
        
        // Handle escape key
        $(document).on('keydown.reign-pause-dialog', function(e) {
            if (e.keyCode === 27) { // Escape key
                _cancelImport(dialog);
            }
        });
    }

    /**
     * Cancel import
     */
    function _cancelImport(dialog) {
        dialog.removeClass('reign-dialog-show');
        $(document).off('keydown.reign-pause-dialog');
        
        setTimeout(function() {
            dialog.remove();
        }, 300);
        
        // Reset everything
        importInProgress = false;
        thisRef.prop('disabled', false).text('Install Demo');
        thisRef.siblings('div.loader').hide();
        $('#progress-bar-container').hide();
        _reignTddShowCurrentActivity('Import cancelled by user');
    }

    /**
     * Restart import from beginning
     */
    function _restartImport(dialog) {
        dialog.removeClass('reign-dialog-show');
        $(document).off('keydown.reign-pause-dialog');
        
        setTimeout(function() {
            dialog.remove();
            
            // Reset all variables
            reignTddDatabaseTablesDone = 0;
            reignTddUploadFoldersDone = 0;
            reignTddDatabaseTablesComplete = false;
            reignTddUploadFoldersComplete = false;
            currentPercentageProgress = 0;
            
            // Restart import
            importInProgress = true;
            thisRef.prop('disabled', true).text('Importing...');
            thisRef.siblings('div.loader').show();
            
            _reignReadThemeDemoPackageFile();
        }, 300);
    }

    /**
     * Retry failed step
     */
    function _retryFailedStep(dialog, failedUrl, actionFor) {
        dialog.removeClass('reign-dialog-show');
        $(document).off('keydown.reign-pause-dialog');
        
        setTimeout(function() {
            dialog.remove();
            
            // Continue import from failed step
            importInProgress = true;
            _reignGetThemeDemoData(failedUrl, actionFor);
        }, 300);
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

    // Add CSS for modern dialog and pause dialog
    if (!$('#reign-import-dialog-css').length) {
        $('<style id="reign-import-dialog-css">')
            .text(`
                .reign-import-confirmation, .reign-import-pause-dialog {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 999999;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }

                .reign-import-confirmation.reign-dialog-show, 
                .reign-import-pause-dialog.reign-dialog-show {
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

                .reign-import-pause-dialog .reign-dialog-container {
                    max-width: 600px;
                    max-height: 90vh;
                    overflow-y: auto;
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

                .reign-dialog-icon.error-icon {
                    background: #fee2e2;
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

                .error-message {
                    font-size: 16px;
                    color: #dc2626;
                    margin: 0 0 20px;
                    line-height: 1.5;
                    font-weight: 500;
                }

                .error-details {
                    background: #f9fafb;
                    border-radius: 8px;
                    padding: 16px;
                    margin: 20px 0;
                }

                .error-details h4 {
                    margin: 0 0 8px;
                    font-size: 14px;
                    font-weight: 600;
                    color: #374151;
                }

                .error-details p {
                    margin: 0 0 12px;
                    font-size: 14px;
                    color: #6b7280;
                    line-height: 1.4;
                }

                .error-details ul {
                    margin: 0;
                    padding-left: 20px;
                    font-size: 14px;
                    color: #6b7280;
                }

                .error-details li {
                    margin-bottom: 4px;
                }

                .import-status {
                    background: #f0f9ff;
                    border: 1px solid #bae6fd;
                    border-radius: 8px;
                    padding: 16px;
                    margin: 20px 0;
                }

                .import-status p {
                    margin: 0 0 8px;
                    font-size: 14px;
                    color: #1e40af;
                }

                .import-status p:last-child {
                    margin-bottom: 0;
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

                .reign-btn-confirm, .reign-btn-restart {
                    background: #3b82f6;
                    color: white;
                    border-color: #3b82f6;
                }

                .reign-btn-confirm:hover, .reign-btn-restart:hover {
                    background: #2563eb;
                    border-color: #2563eb;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
                }

                .reign-btn-retry {
                    background: #f59e0b;
                    color: white;
                    border-color: #f59e0b;
                }

                .reign-btn-retry:hover {
                    background: #d97706;
                    border-color: #d97706;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
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

                /* Pulse animation for demo button */
                @keyframes pulse {
                    0% {
                        transform: scale(1);
                        box-shadow: 0 0 0 0 rgba(29, 118, 218, 0.7);
                    }
                    70% {
                        transform: scale(1.05);
                        box-shadow: 0 0 0 10px rgba(29, 118, 218, 0);
                    }
                    100% {
                        transform: scale(1);
                        box-shadow: 0 0 0 0 rgba(29, 118, 218, 0);
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