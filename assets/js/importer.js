/*
* Plugin Installer Manager Code
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
* Demo Importer Manager Code
*/
jQuery(document).ready(function($) {
    'use strict';

    var reignThemeDemoData = '';
    var thisRef = '';
    var importStartTime = 0;

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
        
        // Show confirmation dialog
        if (!confirm('Are you sure you want to import this demo? This will overwrite your existing content. Make sure you have a backup!')) {
            return;
        }
        
        // Disable button and show loading
        thisRef.prop('disabled', true).text('Importing...');
        thisRef.siblings('div.loader').show();
        
        // Start the import process
        _reignReadThemeDemoPackageFile();
    });

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
            nonce: thisRef.siblings('#demo_nonce').val() || reignDemoInstaller.nonce
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
            nonce: reignDemoInstaller.nonce
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
            _reignDemoImportDone();
        }
    }

    /**
     * Demo import completed
     */
    function _reignDemoImportDone() {
        var importDuration = (Date.now() - importStartTime) / 1000;
        
        _reignTddShowCurrentActivity('Import completed successfully! Redirecting...');
        
        // Log success
        console.log('Demo import completed in ' + importDuration + ' seconds');
        
        setTimeout(function() {
            window.location = reignDemoInstaller.successUrl;
        }, 2000);
    }

    /**
     * Show import error
     */
    function _reignShowImportError(message) {
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

    // Handle page unload during import
    $(window).on('beforeunload', function() {
        if (thisRef && thisRef.prop('disabled')) {
            return 'Demo import is in progress. Leaving this page will cancel the import.';
        }
    });

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