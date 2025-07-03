/*
* Enhanced Demo Importer with Simplified User Experience
* Removes technical file classifications from user interface
*/
(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Configuration
        const CONFIG = {
            maxConcurrentDownloads: 3,
            maxConcurrentProcessing: 2,
            downloadTimeout: 180000,
            processingTimeout: 300000,
            retryAttempts: 2,
            batchSize: 5,
            // Error handling options (internal use only)
            autoSkipOptional: true,
            showUserChoiceDialog: true
        };

        // File criticality mapping (internal use - hidden from user)
        const FILE_CRITICALITY = {
            'options': 'critical',
            'users': 'critical',
            'usermeta': 'critical',
            'posts': 'important',
            'postmeta': 'important',
            'theme_mods': 'important',
            'terms': 'optional',
            'term_taxonomy': 'optional',
            'term_relationships': 'optional',
            'comments': 'optional',
            'commentmeta': 'optional',
            'widgets': 'optional',
            'menus': 'optional',
            'nav_menu_items': 'optional',
            'customizer': 'optional',
            'woocommerce': 'optional',
            'bp_activity': 'optional',
            'bp_groups': 'optional',
            'bp_messages': 'optional',
            'bp_notifications': 'optional',
            'bp_xprofile': 'optional',
            'learndash': 'optional',
            'lifterlms': 'optional'
        };

        // Global state
        var importInProgress = false;
        var startTime = 0;
        var tempFolderId = null;
        var downloadQueue = [];
        var processingQueue = [];
        var failedFiles = [];
        var skippedFiles = [];
        var userChoices = {
            continueOnErrors: null,
            skipOptionalFiles: true,
            retryFailedFiles: false
        };
        
        var downloadStats = {
            total: 0,
            completed: 0,
            failed: 0,
            inProgress: 0
        };
        
        var processingStats = {
            total: 0,
            completed: 0,
            failed: 0,
            inProgress: 0,
            skipped: 0
        };

        // Demo import button click
        $(document).on('click', '#wbcom_get_theme_demo_data', function(event) {
            event.preventDefault();
            
            if (importInProgress) return;
            
            var button = $(this);
            importInProgress = true;
            startTime = Date.now();
            
            // Reset stats
            resetStats();
            
            // Update UI
            button.prop('disabled', true).text('Starting Import...');
            $('#progress-bar-container').show();
            updateProgress(0, 'Preparing demo import...');
            
            // Start import process
            startBatchImport();
        });

        function resetStats() {
            downloadStats = { total: 0, completed: 0, failed: 0, inProgress: 0 };
            processingStats = { total: 0, completed: 0, failed: 0, inProgress: 0, skipped: 0 };
            failedFiles = [];
            skippedFiles = [];
        }

        function startBatchImport() {
            updateProgress(2, 'Creating secure workspace...');
            
            $.ajax({
                url: reignDemoInstaller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbcom_create_temp_folder',
                    demo_slug: $('#demo_slug').val(),
                    plugins_json_key: getPluginsJsonKey(),
                    theme_slug: $('#theme_slug').val(),
                    nonce: reignDemoInstaller.nonce
                },
                timeout: 30000,
                success: function(response) {
                    if (response.success) {
                        tempFolderId = response.data.folder_id;
                        
                        // Show cache info if resuming
                        if (response.data.resumable && response.data.existing_files) {
                            var cacheCount = Object.keys(response.data.existing_files).length;
                            if (cacheCount > 0) {
                                showSimpleNotification(
                                    `Found ${cacheCount} cached files. Resuming previous import...`, 
                                    'info'
                                );
                            }
                        }
                        
                        getDemoManifest();
                    } else {
                        handleError('Failed to create workspace: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status) {
                    handleError('Failed to initialize import: ' + (status === 'timeout' ? 'Timeout' : 'Connection error'));
                }
            });
        }

        function getPluginsJsonKey() {
            // Get plugins_json_key from URL params or hidden field
            var urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('plugins_json_key') || $('#plugins_json_key').val() || '';
        }

        function getDemoManifest() {
            updateProgress(5, 'Analyzing demo content...');
            
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
                timeout: 60000,
                success: function(response) {
                    if (response.success && response.data.files) {
                        downloadQueue = response.data.files.map((file, index) => ({
                            ...file,
                            id: index,
                            attempts: 0,
                            status: 'pending',
                            criticality: determineFileCriticality(file.name, file.action_for)
                        }));
                        
                        downloadStats.total = downloadQueue.length;
                        updateProgress(10, `Found ${downloadStats.total} files. Starting download...`);
                        
                        // Start parallel downloads (no technical summary shown to user)
                        startParallelDownloads();
                    } else {
                        handleError('Failed to get demo files: ' + (response.data?.message || 'No files found'));
                    }
                },
                error: function(xhr, status) {
                    handleError('Failed to connect to demo server: ' + (status === 'timeout' ? 'Server timeout' : 'Network error'));
                }
            });
        }

        function determineFileCriticality(fileName, actionFor) {
            // Extract table name from filename
            const baseName = fileName.replace(/\d+\.json$/, '').replace(/\.json$/, '');
            
            // Check specific mappings
            if (FILE_CRITICALITY[baseName]) {
                return FILE_CRITICALITY[baseName];
            }
            
            // Default based on action type
            if (actionFor === 'database_tables') {
                return 'important';
            } else if (actionFor === 'upload_folders') {
                return 'optional';
            }
            
            return 'optional';
        }

        function startParallelDownloads() {
            updateProgress(15, 'Downloading demo files...');
            
            // Start multiple concurrent downloads
            for (let i = 0; i < CONFIG.maxConcurrentDownloads; i++) {
                processNextDownload();
            }
            
            // Monitor download progress
            monitorDownloadProgress();
        }

        function processNextDownload() {
            const nextFile = downloadQueue.find(file => file.status === 'pending');
            
            if (!nextFile) {
                return;
            }

            nextFile.status = 'downloading';
            downloadStats.inProgress++;
            
            downloadFile(nextFile).then(result => {
                downloadStats.inProgress--;
                
                if (result.success) {
                    nextFile.status = 'completed';
                    downloadStats.completed++;
                    processingQueue.push(nextFile);
                    
                    // Only log cache info, no detailed file info
                    if (result.data?.cached) {
                        console.log(`✓ Using cached: ${nextFile.name}`);
                    } else {
                        console.log(`✓ Downloaded: ${nextFile.name} (${downloadStats.completed}/${downloadStats.total})`);
                    }
                } else {
                    nextFile.attempts++;
                    
                    if (nextFile.attempts < CONFIG.retryAttempts) {
                        nextFile.status = 'pending';
                        console.log(`⚠ Retrying download: ${nextFile.name} (attempt ${nextFile.attempts + 1})`);
                    } else {
                        nextFile.status = 'failed';
                        downloadStats.failed++;
                        failedFiles.push({...nextFile, stage: 'download', error: result.error});
                        console.error(`✗ Failed download: ${nextFile.name} - ${result.error}`);
                    }
                }
                
                processNextDownload();
                
            }).catch(error => {
                downloadStats.inProgress--;
                nextFile.status = 'failed';
                downloadStats.failed++;
                failedFiles.push({...nextFile, stage: 'download', error: error.message});
                console.error(`✗ Download exception: ${nextFile.name}`, error);
                processNextDownload();
            });
        }

        function downloadFile(file) {
            return new Promise((resolve) => {
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
                    timeout: CONFIG.downloadTimeout,
                    success: function(response) {
                        if (response.success) {
                            resolve({ success: true, data: response.data });
                        } else {
                            resolve({ success: false, error: response.data?.message || 'Download failed' });
                        }
                    },
                    error: function(xhr, status) {
                        let error = 'Network error';
                        if (status === 'timeout') {
                            error = 'Download timeout - file may be large';
                        } else if (xhr.responseJSON?.data?.message) {
                            error = xhr.responseJSON.data.message;
                        }
                        resolve({ success: false, error: error });
                    }
                });
            });
        }

        function monitorDownloadProgress() {
            const progressInterval = setInterval(() => {
                const downloadProgress = Math.round((downloadStats.completed / downloadStats.total) * 50);
                const currentProgress = 15 + downloadProgress;
                
                const activeDownloads = downloadStats.inProgress;
                const statusText = `Downloading files... ${downloadStats.completed}/${downloadStats.total} complete${activeDownloads > 0 ? ` (${activeDownloads} active)` : ''}`;
                
                updateProgress(currentProgress, statusText);
                
                if (downloadStats.completed + downloadStats.failed >= downloadStats.total) {
                    clearInterval(progressInterval);
                    
                    if (downloadStats.failed > 0) {
                        handleDownloadFailures();
                    } else {
                        startParallelProcessing();
                    }
                }
                
                if (downloadStats.inProgress === 0 && downloadStats.completed + downloadStats.failed < downloadStats.total) {
                    const stalledFiles = downloadQueue.filter(f => f.status === 'pending').length;
                    if (stalledFiles > 0) {
                        console.log(`Restarting ${stalledFiles} stalled downloads`);
                        for (let i = 0; i < Math.min(CONFIG.maxConcurrentDownloads, stalledFiles); i++) {
                            processNextDownload();
                        }
                    }
                }
            }, 1000);
        }

        function handleDownloadFailures() {
            const criticalFailures = failedFiles.filter(f => f.stage === 'download' && f.criticality === 'critical');
            
            if (criticalFailures.length > 0) {
                // Only show simplified error for critical failures
                showErrorDialog('download', criticalFailures.length, downloadStats.failed - criticalFailures.length);
            } else {
                // Only non-critical failures, can proceed
                const errorMsg = `Some files couldn't be downloaded, but the import can continue. Your site will work normally.`;
                showSimpleNotification(errorMsg, 'warning');
                startParallelProcessing();
            }
        }

        function startParallelProcessing() {
            if (processingQueue.length === 0) {
                completeImport();
                return;
            }
            
            processingStats.total = processingQueue.length;
            updateProgress(70, `Processing ${processingStats.total} files...`);
            
            for (let i = 0; i < CONFIG.maxConcurrentProcessing; i++) {
                processNextFile();
            }
            
            monitorProcessingProgress();
        }

        function processNextFile() {
            const nextFile = processingQueue.find(file => !file.processing && !file.processed && !file.skipProcessing);
            
            if (!nextFile) {
                return;
            }

            nextFile.processing = true;
            processingStats.inProgress++;
            
            processFile(nextFile).then(result => {
                processingStats.inProgress--;
                nextFile.processing = false;
                nextFile.processed = true;
                
                if (result.success) {
                    processingStats.completed++;
                    console.log(`✓ Processed: ${nextFile.name} (${processingStats.completed}/${processingStats.total})`);
                } else {
                    processingStats.failed++;
                    failedFiles.push({...nextFile, stage: 'processing', error: result.error});
                    console.error(`✗ Processing failed: ${nextFile.name} - ${result.error}`);
                    
                    // Handle processing failure based on criticality
                    handleProcessingFailure(nextFile, result.error);
                }
                
                processNextFile();
                
            }).catch(error => {
                processingStats.inProgress--;
                nextFile.processing = false;
                nextFile.processed = true;
                processingStats.failed++;
                failedFiles.push({...nextFile, stage: 'processing', error: error.message});
                console.error(`✗ Processing exception: ${nextFile.name}`, error);
                handleProcessingFailure(nextFile, error.message);
                processNextFile();
            });
        }

        function handleProcessingFailure(file, error) {
            if (file.criticality === 'optional' && CONFIG.autoSkipOptional) {
                console.log(`🔄 Auto-skipping optional file: ${file.name}`);
                skippedFiles.push({...file, reason: 'Auto-skipped optional file'});
                processingStats.skipped++;
                return;
            }
            
            if (file.criticality === 'critical') {
                // Critical failure - may need user intervention
                setTimeout(() => {
                    const criticalFailures = failedFiles.filter(f => f.stage === 'processing' && f.criticality === 'critical');
                    if (criticalFailures.length > 0 && processingStats.inProgress === 0) {
                        checkForProcessingFailures();
                    }
                }, 1000);
            }
        }

        function processFile(file) {
            return new Promise((resolve) => {
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
                        allow_partial: true,
                        file_criticality: file.criticality,
                        nonce: reignDemoInstaller.nonce
                    },
                    timeout: CONFIG.processingTimeout,
                    success: function(response) {
                        if (response.success) {
                            resolve({ success: true, data: response.data });
                        } else {
                            resolve({ success: false, error: response.data?.message || 'Processing failed' });
                        }
                    },
                    error: function(xhr, status) {
                        let error = 'Processing error';
                        if (status === 'timeout') {
                            error = 'Processing timeout - file may be complex';
                        }
                        resolve({ success: false, error: error });
                    }
                });
            });
        }

        function monitorProcessingProgress() {
            const progressInterval = setInterval(() => {
                const processingProgress = Math.round(((processingStats.completed + processingStats.skipped) / processingStats.total) * 25);
                const currentProgress = 70 + processingProgress;
                
                const activeProcessing = processingStats.inProgress;
                const statusText = `Processing files... ${processingStats.completed}/${processingStats.total} complete (${processingStats.skipped} skipped)${activeProcessing > 0 ? ` (${activeProcessing} active)` : ''}`;
                
                updateProgress(currentProgress, statusText);
                
                if (processingStats.completed + processingStats.failed + processingStats.skipped >= processingStats.total) {
                    clearInterval(progressInterval);
                    checkForProcessingFailures();
                }
                
                if (processingStats.inProgress === 0 && processingStats.completed + processingStats.failed + processingStats.skipped < processingStats.total) {
                    const stalledFiles = processingQueue.filter(f => !f.processing && !f.processed && !f.skipProcessing).length;
                    if (stalledFiles > 0) {
                        console.log(`Restarting ${stalledFiles} stalled processing tasks`);
                        for (let i = 0; i < Math.min(CONFIG.maxConcurrentProcessing, stalledFiles); i++) {
                            processNextFile();
                        }
                    }
                }
            }, 1000);
        }

        function checkForProcessingFailures() {
            const criticalFailures = failedFiles.filter(f => f.stage === 'processing' && f.criticality === 'critical');
            const importantFailures = failedFiles.filter(f => f.stage === 'processing' && f.criticality === 'important');
            
            if (criticalFailures.length > 0) {
                showErrorDialog('processing', criticalFailures.length, importantFailures.length);
            } else if (importantFailures.length > 0 && CONFIG.showUserChoiceDialog) {
                showErrorDialog('processing', 0, importantFailures.length);
            } else {
                completeImport();
            }
        }

        function showErrorDialog(stage, criticalCount, otherCount) {
            const isCritical = criticalCount > 0;
            const totalFailed = criticalCount + otherCount;
            
            const dialogHtml = `
                <div class="error-dialog-overlay">
                    <div class="error-dialog">
                        <h3>${isCritical ? '🚨' : '⚠️'} Import Issue Detected</h3>
                        
                        <div class="error-summary">
                            <p><strong>${totalFailed} files couldn't be ${stage === 'download' ? 'downloaded' : 'processed'}.</strong></p>
                            ${isCritical ? `<p class="critical-error">Some essential files failed, which may affect core functionality.</p>` : `<p class="warning-error">Some optional files failed, but your site should work normally.</p>`}
                        </div>
                        
                        <div class="dialog-actions">
                            ${isCritical ? `
                                <button class="btn-retry" data-action="retry">🔄 Try Again</button>
                                <button class="btn-continue" data-action="continue">⚠️ Continue Anyway</button>
                                <button class="btn-abort" data-action="abort">❌ Cancel Import</button>
                            ` : `
                                <button class="btn-retry" data-action="retry">🔄 Try Again</button>
                                <button class="btn-continue" data-action="continue">✅ Continue Import</button>
                            `}
                        </div>
                        
                        <div class="dialog-note">
                            ${isCritical ? 
                                '<p class="warning-note">⚠️ Continuing without essential files may result in missing features or content.</p>' :
                                '<p class="info-note">ℹ️ Your website will work normally without these optional files.</p>'
                            }
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(dialogHtml);
            
            // Handle dialog actions
            $('.error-dialog .btn-retry').on('click', function() {
                retryFailedFiles(stage);
                $('.error-dialog-overlay').remove();
            });
            
            $('.error-dialog .btn-continue').on('click', function() {
                $('.error-dialog-overlay').remove();
                if (stage === 'download') {
                    startParallelProcessing();
                } else {
                    completeImport();
                }
            });
            
            $('.error-dialog .btn-abort').on('click', function() {
                $('.error-dialog-overlay').remove();
                abortImport();
            });
        }

        function retryFailedFiles(stage) {
            const stageFailures = failedFiles.filter(f => f.stage === stage);
            
            // Reset failed files for retry
            stageFailures.forEach(file => {
                const originalFile = stage === 'download' ? 
                    downloadQueue.find(f => f.id === file.id) :
                    processingQueue.find(f => f.id === file.id);
                
                if (originalFile) {
                    originalFile.status = 'pending';
                    originalFile.attempts = 0;
                    originalFile.processing = false;
                    originalFile.processed = false;
                }
            });
            
            // Remove from failed files
            failedFiles = failedFiles.filter(f => f.stage !== stage);
            
            // Restart processing
            if (stage === 'download') {
                downloadStats.failed = 0;
                for (let i = 0; i < CONFIG.maxConcurrentDownloads; i++) {
                    processNextDownload();
                }
            } else {
                processingStats.failed = 0;
                for (let i = 0; i < CONFIG.maxConcurrentProcessing; i++) {
                    processNextFile();
                }
            }
            
            showSimpleNotification(`Retrying ${stageFailures.length} failed files...`, 'info');
        }

        function abortImport() {
            importInProgress = false;
            $('#wbcom_get_theme_demo_data').prop('disabled', false).text('Install Demo');
            updateProgress(0, 'Import cancelled by user');
            
            if (tempFolderId) {
                $.ajax({
                    url: reignDemoInstaller.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wbcom_cleanup_temp_folder',
                        temp_folder_id: tempFolderId,
                        keep_cache: true, // Keep for future attempts
                        nonce: reignDemoInstaller.nonce
                    }
                });
            }
            
            showSimpleNotification('Import cancelled. You can try again when ready.', 'info');
        }

        function completeImport() {
            updateProgress(95, 'Finalizing import...');
            
            const duration = Math.round((Date.now() - startTime) / 1000);
            const downloadSuccessRate = Math.round((downloadStats.completed / downloadStats.total) * 100);
            const processingSuccessRate = processingStats.total > 0 ? 
                Math.round(((processingStats.completed + processingStats.skipped) / processingStats.total) * 100) : 100;
            
            console.log(`Import Summary:
                Duration: ${duration}s
                Downloads: ${downloadStats.completed}/${downloadStats.total} (${downloadSuccessRate}%)
                Processing: ${processingStats.completed}/${processingStats.total} (${processingSuccessRate}%)
                Skipped: ${processingStats.skipped + skippedFiles.length}
                Failed: ${failedFiles.length}
            `);
            
            // Store import summary for success page
            const importSummary = {
                duration: duration,
                downloads: { completed: downloadStats.completed, total: downloadStats.total },
                processing: { completed: processingStats.completed, total: processingStats.total },
                skipped: processingStats.skipped + skippedFiles.length,
                failed: failedFiles.length,
                skippedFiles: skippedFiles,
                failedFiles: failedFiles
            };
            
            // Send summary to server
            $.ajax({
                url: reignDemoInstaller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbcom_store_import_summary',
                    summary: JSON.stringify(importSummary),
                    nonce: reignDemoInstaller.nonce
                }
            });
            
            // Cleanup temp folder but keep cache for future use
            $.ajax({
                url: reignDemoInstaller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbcom_cleanup_temp_folder',
                    temp_folder_id: tempFolderId,
                    keep_cache: true, // Preserve for future imports
                    nonce: reignDemoInstaller.nonce
                },
                timeout: 30000,
                complete: function() {
                    updateProgress(100, `Import completed in ${duration}s. Redirecting...`);
                    
                    $(window).off('beforeunload');
                    
                    if (failedFiles.length === 0 && skippedFiles.length === 0) {
                        showSimpleNotification('Demo imported successfully!', 'success');
                    } else if (failedFiles.length === 0) {
                        showSimpleNotification(`Demo imported successfully!`, 'success');
                    } else {
                        showSimpleNotification(`Demo imported with some minor issues.`, 'warning');
                    }
                    
                    setTimeout(function() {
                        window.location.href = reignDemoInstaller.successUrl;
                    }, 3000);
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
            $('#wbcom_get_theme_demo_data').prop('disabled', false).text('Install Demo');
            updateProgress(0, 'Import failed: ' + message);
            
            if (tempFolderId) {
                $.ajax({
                    url: reignDemoInstaller.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wbcom_cleanup_temp_folder',
                        temp_folder_id: tempFolderId,
                        keep_cache: true, // Keep for retry
                        nonce: reignDemoInstaller.nonce
                    }
                });
            }
            
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
            
            setTimeout(function() {
                notification.fadeOut();
            }, 10000);
            
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

        // Plugin management functionality (existing code)
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

    }); // End document ready

})(jQuery);

// Enhanced CSS for simplified user interface
jQuery(document).ready(function($) {
    if (!$('#simplified-ui-css').length) {
        $('<style id="simplified-ui-css">').text(`
            /* Simplified Error Dialog */
            .error-dialog {
                background: white;
                border-radius: 8px;
                padding: 25px;
                max-width: 500px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                animation: slideUp 0.3s ease;
            }
            
            .error-dialog h3 {
                margin: 0 0 15px;
                color: #dc3545;
                font-size: 18px;
                text-align: center;
            }
            
            .error-summary {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                padding: 15px;
                margin-bottom: 20px;
                text-align: center;
            }
            
            .critical-error {
                color: #721c24;
                font-weight: bold;
                margin: 10px 0;
            }
            
            .warning-error {
                color: #856404;
                margin: 10px 0;
            }
            
            .dialog-actions {
                display: flex;
                gap: 10px;
                justify-content: center;
                margin: 20px 0 15px;
                flex-wrap: wrap;
            }
            
            .dialog-actions button {
                padding: 12px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-weight: bold;
                transition: all 0.2s;
                min-width: 130px;
                font-size: 14px;
            }
            
            .btn-retry {
                background: #007bff;
                color: white;
            }
            
            .btn-retry:hover {
                background: #0056b3;
            }
            
            .btn-continue {
                background: #28a745;
                color: white;
            }
            
            .btn-continue:hover {
                background: #1e7e34;
            }
            
            .btn-abort {
                background: #6c757d;
                color: white;
            }
            
            .btn-abort:hover {
                background: #545b62;
            }
            
            .dialog-note {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                padding: 12px;
                text-align: center;
            }
            
            .warning-note {
                color: #856404;
                margin: 0;
                font-size: 13px;
            }
            
            .info-note {
                color: #0c5460;
                margin: 0;
                font-size: 13px;
            }
            
            /* Hide technical file summary from users */
            .import-file-summary {
                display: none !important;
            }
            
            /* Enhanced progress bar */
            #progress-bar-container .completed {
                transition: width 0.3s ease;
                background: linear-gradient(90deg, #28a745 0%, #20c997 50%, #17a2b8 100%);
            }
            
            /* Simplified notifications */
            .simple-notification {
                margin: 15px 0;
                padding: 15px;
                border-radius: 6px;
                position: relative;
                animation: slideDown 0.4s ease;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                font-weight: 500;
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
            
            .simple-notification.warning {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                color: #856404;
            }
            
            .simple-notification.info {
                background: #d1ecf1;
                border-left: 4px solid #17a2b8;
                color: #0c5460;
            }
            
            .simple-notification p {
                margin: 0;
                padding-right: 35px;
                line-height: 1.4;
            }
            
            .simple-notification .dismiss {
                position: absolute;
                top: 12px;
                right: 15px;
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                color: inherit;
                opacity: 0.7;
                transition: opacity 0.2s;
            }
            
            .simple-notification .dismiss:hover {
                opacity: 1;
            }
        `).appendTo('head');
    }
});