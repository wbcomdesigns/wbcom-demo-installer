/*
* Streamlined Demo Importer - No Popups, Just Works
* Downloads all files first, then processes locally
*/
jQuery(document).ready(function($) {
    'use strict';

    // Global state
    var importInProgress = false;
    var startTime = 0;
    var tempFolderId = null;
    var downloadQueue = [];
    var downloadedCount = 0;
    var totalFiles = 0;

    // Demo import button click
    $(document).on('click', '#wbcom_get_theme_demo_data', function(event) {
        event.preventDefault();
        
        if (importInProgress) return;
        
        var button = $(this);
        importInProgress = true;
        startTime = Date.now();
        
        // Update UI
        button.prop('disabled', true).text('Starting Import...');
        $('#progress-bar-container').show();
        updateProgress(0, 'Initializing import...');
        
        // Start import process
        startBatchImport();
    });

    function startBatchImport() {
        // Step 1: Create temp folder
        $.ajax({
            url: reignDemoInstaller.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wbcom_create_temp_folder',
                nonce: reignDemoInstaller.nonce
            },
            success: function(response) {
                if (response.success) {
                    tempFolderId = response.data.folder_id;
                    getDemoManifest();
                } else {
                    handleError('Failed to create temp folder: ' + (response.data?.message || 'Unknown error'));
                }
            },
            error: function() {
                handleError('Failed to initialize import. Please try again.');
            }
        });
    }

    function getDemoManifest() {
        updateProgress(5, 'Getting demo files list...');
        
        $.ajax({
            url: reignDemoInstaller.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wbcom_get_demo_manifest',
                theme_slug: $('#theme_slug').val(),
                demo_slug: $('#demo_slug').val(),
                target_url: $('#target_url').val(),
                nonce: reignDemoInstaller.nonce
            },
            success: function(response) {
                if (response.success && response.data.files) {
                    downloadQueue = response.data.files;
                    totalFiles = downloadQueue.length;
                    updateProgress(10, `Found ${totalFiles} files to download`);
                    startDownloadPhase();
                } else {
                    handleError('Failed to get demo files list: ' + (response.data?.message || 'No files found'));
                }
            },
            error: function() {
                handleError('Failed to connect to demo server. Please check your connection.');
            }
        });
    }

    function startDownloadPhase() {
        updateProgress(15, 'Downloading demo files...');
        downloadNextFile();
    }

    function downloadNextFile() {
        if (downloadedCount >= totalFiles) {
            // All files downloaded, start processing
            startProcessingPhase();
            return;
        }

        var file = downloadQueue[downloadedCount];
        var progressPercent = 15 + Math.round((downloadedCount / totalFiles) * 50); // 15-65%
        
        updateProgress(progressPercent, `Downloading ${file.name}... (${downloadedCount + 1}/${totalFiles})`);

        $.ajax({
            url: reignDemoInstaller.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wbcom_download_demo_file',
                temp_folder_id: tempFolderId,
                file_url: file.url,
                file_name: file.name,
                file_type: file.type,
                nonce: reignDemoInstaller.nonce
            },
            timeout: 120000, // 2 minutes per file
            success: function(response) {
                if (response.success) {
                    downloadedCount++;
                    // Continue with next file
                    setTimeout(downloadNextFile, 500); // Small delay to prevent overwhelming
                } else {
                    handleError(`Failed to download ${file.name}: ` + (response.data?.message || 'Download failed'));
                }
            },
            error: function(xhr, status) {
                if (status === 'timeout') {
                    handleError(`Download timeout for ${file.name}. File may be too large or server is slow.`);
                } else {
                    handleError(`Network error downloading ${file.name}. Please check your connection.`);
                }
            }
        });
    }

    function startProcessingPhase() {
        updateProgress(70, 'Processing downloaded files...');
        
        var processedCount = 0;
        
        function processNextFile() {
            if (processedCount >= downloadedCount) {
                // All files processed, complete import
                completeImport();
                return;
            }

            var file = downloadQueue[processedCount];
            var progressPercent = 70 + Math.round((processedCount / downloadedCount) * 25); // 70-95%
            
            updateProgress(progressPercent, `Processing ${file.name}... (${processedCount + 1}/${downloadedCount})`);

            $.ajax({
                url: reignDemoInstaller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbcom_process_local_demo_file',
                    temp_folder_id: tempFolderId,
                    file_name: file.name,
                    file_type: file.type,
                    action_for: file.action_for,
                    preserve_admin: true,
                    nonce: reignDemoInstaller.nonce
                },
                timeout: 180000, // 3 minutes per file
                success: function(response) {
                    if (response.success) {
                        processedCount++;
                        // Continue with next file
                        setTimeout(processNextFile, 200); // Faster processing since local
                    } else {
                        handleError(`Failed to process ${file.name}: ` + (response.data?.message || 'Processing failed'));
                    }
                },
                error: function(xhr, status) {
                    if (status === 'timeout') {
                        handleError(`Processing timeout for ${file.name}. This file may require manual import.`);
                    } else {
                        handleError(`Error processing ${file.name}. Import may be incomplete.`);
                    }
                }
            });
        }

        processNextFile();
    }

    function completeImport() {
        updateProgress(95, 'Cleaning up...');
        
        // Cleanup temp folder
        $.ajax({
            url: reignDemoInstaller.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wbcom_cleanup_temp_folder',
                temp_folder_id: tempFolderId,
                nonce: reignDemoInstaller.nonce
            },
            complete: function() {
                // Always redirect to success, even if cleanup fails
                var duration = Math.round((Date.now() - startTime) / 1000);
                updateProgress(100, `Import completed in ${duration} seconds. Redirecting...`);
                
                // Clear page unload warning
                $(window).off('beforeunload');
                
                // Redirect after brief delay
                setTimeout(function() {
                    window.location.href = reignDemoInstaller.successUrl;
                }, 2000);
            }
        });
    }

    function updateProgress(percent, message) {
        $('#progress-bar-container .completed').css('width', percent + '%');
        $('#progress-bar-container .completed').text(percent + '%');
        $('#wbtd-current-action').text(message).show();
        
        console.log(`[${percent}%] ${message}`);
    }

    function handleError(message) {
        importInProgress = false;
        
        console.error('Import Error:', message);
        
        // Reset button
        $('#wbcom_get_theme_demo_data').prop('disabled', false).text('Install Demo');
        
        // Show error message
        updateProgress(0, 'Import failed: ' + message);
        
        // Cleanup temp folder if it exists
        if (tempFolderId) {
            $.ajax({
                url: reignDemoInstaller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbcom_cleanup_temp_folder',
                    temp_folder_id: tempFolderId,
                    nonce: reignDemoInstaller.nonce
                }
            });
        }
        
        // Show simple error notification
        showSimpleNotification(message, 'error');
    }

    function showSimpleNotification(message, type) {
        $('.simple-notification').remove();
        
        var notification = $(`
            <div class="simple-notification ${type}">
                <p>${message}</p>
                <button type="button" class="dismiss">×</button>
            </div>
        `);
        
        $('.demo-listing-wrap').prepend(notification);
        
        // Auto dismiss after 10 seconds
        setTimeout(function() {
            notification.fadeOut();
        }, 10000);
        
        // Manual dismiss
        notification.find('.dismiss').on('click', function() {
            notification.fadeOut();
        });
    }

    // Prevent page unload during import
    $(window).on('beforeunload', function(e) {
        if (importInProgress) {
            var message = 'Demo import is in progress. Leaving will cancel the import.';
            e.returnValue = message;
            return message;
        }
    });

    // Plugin management (existing functionality)
    _check_all_required_plugin_installed();

    $(document).on('click', 'button.plugin-action-button', function(event) {
        event.preventDefault();
        
        var thisRef = $(this);
        if (thisRef.hasClass('already-active')) return;

        _show_plugin_installer_loader();
        thisRef.prop('disabled', true).text('Installing...');

        var pluginData = {
            action: 'wbcom_manage_plugin_installation',
            plugin_action: thisRef.siblings('input.plugin-action').val(),
            plugin_slug: thisRef.siblings('input.plugin-slug').val(),
            demo: thisRef.siblings('input.demo-name').val(),
            nonce: $('#plugins_nonce').val() || reignDemoInstaller.nonce
        };

        $.ajax({
            url: reignDemoInstaller.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: pluginData,
            timeout: 120000,
            success: function(response) {
                _hide_plugin_installer_loader();
                thisRef.prop('disabled', false);

                if (response.success) {
                    thisRef.siblings('p.plugin-status').html('Active').addClass('already-active');
                    thisRef.html('Installed & Active').addClass('already-active');
                    
                    var temp_counter = parseInt($('input#num_of_req_plugins_installed').val()) || 0;
                    temp_counter++;
                    $('input#num_of_req_plugins_installed').val(temp_counter);
                    
                    _check_all_required_plugin_installed();
                    showSimpleNotification('Plugin installed successfully', 'success');
                } else {
                    thisRef.text('Install Now');
                    var errorMsg = response.data?.message || 'Installation failed';
                    showSimpleNotification(errorMsg, 'error');
                }
            },
            error: function(xhr, status) {
                _hide_plugin_installer_loader();
                thisRef.prop('disabled', false).text('Install Now');
                
                var errorMsg = status === 'timeout' ? 'Installation timeout' : 'Connection error';
                showSimpleNotification(errorMsg, 'error');
            }
        });
    });

    function _check_all_required_plugin_installed() {
        var requiredPlugins = parseInt($('input#required_plugins_to_activate').val()) || 0;
        var installedPlugins = parseInt($('input#num_of_req_plugins_installed').val()) || 0;
        
        if (requiredPlugins > 0 && (requiredPlugins - installedPlugins) === 0) {
            $('div.goto-install-demo-step').fadeIn(500);
        } else {
            $('div.goto-install-demo-step').fadeOut(300);
        }
    }

    function _show_plugin_installer_loader() {
        $('body').addClass('demo_listing_loading');
    }

    function _hide_plugin_installer_loader() {
        $('body').removeClass('demo_listing_loading');
    }
});

// Simple notification CSS
if (!$('#simple-notification-css').length) {
    $('<style id="simple-notification-css">').text(`
        .simple-notification {
            margin: 15px 0;
            padding: 15px;
            border-radius: 4px;
            position: relative;
            animation: slideDown 0.3s ease;
        }
        .simple-notification.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .simple-notification.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .simple-notification p {
            margin: 0;
            padding-right: 30px;
        }
        .simple-notification .dismiss {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
        }
        .simple-notification .dismiss:hover {
            opacity: 1;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `).appendTo('head');
}