/*
* Enhanced Demo Importer - Sequential File Processing 
* Processes files one by one without folder structure dependency
* Version: 3.0.1 Fixed
*/
(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Configuration
        const CONFIG = {
            maxConcurrentDownloads: 3,
            maxConcurrentProcessing: 1, // Process one file at a time
            downloadTimeout: 180000,
            processingTimeout: 300000,
            retryAttempts: 2,
            batchSize: 5,
            // User experience settings
            showOptimisticProgress: true,
            hideMinorIssues: true,
            emphasizeSuccess: true
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
        var processedFiles = [];
        var userChoices = {
            continueOnErrors: true,
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
            
            // Update UI with positive messaging
            button.prop('disabled', true).text('Starting Import...');
            $('#progress-bar-container').show();
            updateProgress(0, 'Preparing your demo content...');
            
            // Show encouraging message
            showUserMessage('Starting demo import. This will take a few minutes...', 'info');
            
            // Start import process
            startBatchImport();
        });

        function resetStats() {
            downloadStats = { total: 0, completed: 0, failed: 0, inProgress: 0 };
            processingStats = { total: 0, completed: 0, failed: 0, inProgress: 0, skipped: 0 };
            failedFiles = [];
            skippedFiles = [];
            processedFiles = [];
        }

        function startBatchImport() {
            updateProgress(2, 'Setting up secure workspace...');
            
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
                        console.log('✓ Workspace created:', response.data);
                        getDemoManifest();
                    } else {
                        handleError('Setup issue detected. Let\'s try again...', response.data?.message);
                    }
                },
                error: function(xhr, status) {
                    var debugMsg = 'Failed to initialize import: ' + (status === 'timeout' ? 'Timeout' : 'Connection error');
                    console.error('Setup Error:', debugMsg, xhr);
                    handleError('Connection issue. Please check your internet and try again.');
                }
            });
        }

        function getPluginsJsonKey() {
            var urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('plugins_json_key') || $('#plugins_json_key').val() || '';
        }

        function getDemoManifest() {
            updateProgress(5, 'Analyzing demo content structure...');
            
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
                        // SIMPLIFIED: Just prepare files by their names - no complex path handling needed
                        downloadQueue = response.data.files.map((file, index) => ({
                            id: index,
                            name: file.name, // Simple filename like "options1.json" or "07-break-1.zip"
                            url: file.url,
                            type: file.type,
                            action_for: file.action_for,
                            attempts: 0,
                            status: 'pending',
                            criticality: determineFileCriticality(file.name, file.action_for)
                        }));
                        
                        downloadStats.total = downloadQueue.length;
                        
                        console.log('📁 Files prepared for processing:', {
                            total: downloadStats.total,
                            database_files: downloadQueue.filter(f => f.action_for === 'database_tables').length,
                            upload_files: downloadQueue.filter(f => f.action_for === 'upload_folders').length,
                            file_samples: downloadQueue.slice(0, 5).map(f => f.name)
                        });
                        
                        updateProgress(10, `Found ${downloadStats.total} files. Starting secure download...`);
                        showUserMessage(`Downloading ${downloadStats.total} demo files...`, 'info');
                        
                        startParallelDownloads();
                    } else {
                        var debugMsg = 'Failed to get demo files: ' + (response.data?.message || 'No files found');
                        console.error('Manifest Error:', debugMsg, response);
                        handleError('Demo content unavailable. Please try again in a moment.');
                    }
                },
                error: function(xhr, status) {
                    var debugMsg = 'Failed to connect to demo server: ' + (status === 'timeout' ? 'Server timeout' : 'Network error');
                    console.error('Manifest Request Error:', debugMsg, xhr);
                    handleError('Demo server connection issue. Please try again.');
                }
            });
        }

        function determineFileCriticality(fileName, actionFor) {
            const baseName = fileName.replace(/\d+\.json$/, '').replace(/\.json$/, '');
            
            if (FILE_CRITICALITY[baseName]) {
                return FILE_CRITICALITY[baseName];
            }
            
            if (actionFor === 'database_tables') {
                return 'important';
            } else if (actionFor === 'upload_folders') {
                return 'optional';
            }
            
            return 'optional';
        }

        function startParallelDownloads() {
            updateProgress(15, 'Downloading demo files securely...');
            
            for (let i = 0; i < CONFIG.maxConcurrentDownloads; i++) {
                processNextDownload();
            }
            
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
                    
                    let logMessage = `✓ Downloaded: ${nextFile.name} (${downloadStats.completed}/${downloadStats.total})`;
                    if (result.data?.cached) {
                        logMessage = logMessage.replace('Downloaded:', 'Using cached:');
                    }
                    console.log(logMessage);
                } else {
                    nextFile.attempts++;
                    
                    let errorMessage = `⚠ Download failed: ${nextFile.name} - ${result.error}`;
                    
                    if (nextFile.attempts < CONFIG.retryAttempts) {
                        nextFile.status = 'pending';
                        console.log(errorMessage + ` (attempt ${nextFile.attempts + 1})`);
                    } else {
                        nextFile.status = 'failed';
                        downloadStats.failed++;
                        failedFiles.push({...nextFile, stage: 'download', error: result.error});
                        console.error(errorMessage);
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
                        action_for: file.action_for,
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
                let statusText = `Downloading files... ${downloadStats.completed}/${downloadStats.total}`;
                
                if (downloadStats.completed > 0) {
                    const percentage = Math.round((downloadStats.completed / downloadStats.total) * 100);
                    statusText += ` (${percentage}% complete)`;
                }
                
                if (activeDownloads > 0) {
                    statusText += ` - ${activeDownloads} active`;
                }
                
                updateProgress(currentProgress, statusText);
                
                if (downloadStats.completed + downloadStats.failed >= downloadStats.total) {
                    clearInterval(progressInterval);
                    
                    if (downloadStats.failed > 0) {
                        handleDownloadFailures();
                    } else {
                        showUserMessage('All files downloaded successfully! Processing content...', 'success');
                        startSequentialProcessing();
                    }
                }
                
                // Handle stalled downloads
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
                showUserMessage('Some essential files need attention. Let\'s try to continue...', 'warning');
                console.error('Critical download failures:', criticalFailures);
                
                setTimeout(() => {
                    startSequentialProcessing();
                }, 2000);
            } else {
                const skipMsg = downloadStats.failed > 0 ? 
                    `Continuing with ${downloadStats.completed} files (${downloadStats.failed} optional files skipped)...` :
                    'All essential files ready! Processing content...';
                
                showUserMessage(skipMsg, 'info');
                console.log(`Download completed with ${downloadStats.failed} optional failures`);
                startSequentialProcessing();
            }
        }

        // FIXED: Sequential processing instead of parallel
        function startSequentialProcessing() {
            if (processingQueue.length === 0) {
                completeImport();
                return;
            }
            
            processingStats.total = processingQueue.length;
            updateProgress(70, `Processing ${processingStats.total} files...`);
            showUserMessage('Setting up your demo content...', 'info');
            
            // Sort files by priority: database files first, then uploads
            processingQueue.sort((a, b) => {
                const priorityA = a.action_for === 'database_tables' ? 1 : 2;
                const priorityB = b.action_for === 'database_tables' ? 1 : 2;
                
                if (priorityA !== priorityB) {
                    return priorityA - priorityB;
                }
                
                // Within same type, critical files first
                const criticalityOrder = { 'critical': 1, 'important': 2, 'optional': 3 };
                return (criticalityOrder[a.criticality] || 3) - (criticalityOrder[b.criticality] || 3);
            });
            
            console.log('📋 Processing order:', processingQueue.map(f => `${f.name} (${f.action_for}, ${f.criticality})`));
            
            processNextFileSequentially();
        }

        // FIXED: Process files one by one
        function processNextFileSequentially() {
            const nextFile = processingQueue.find(file => !file.processing && !file.processed && !file.skipProcessing);
            
            if (!nextFile) {
                // All files processed
                completeImport();
                return;
            }

            nextFile.processing = true;
            processingStats.inProgress = 1;
            
            const currentIndex = processedFiles.length + processingStats.failed + processingStats.skipped + 1;
            updateProgress(70 + Math.round((currentIndex / processingStats.total) * 25), 
                          `Processing ${nextFile.name} (${currentIndex}/${processingStats.total})...`);
            
            console.log(`🔄 Processing file ${currentIndex}/${processingStats.total}: ${nextFile.name}`);
            
            processFile(nextFile).then(result => {
                processingStats.inProgress = 0;
                nextFile.processing = false;
                nextFile.processed = true;
                
                if (result.success) {
                    processingStats.completed++;
                    processedFiles.push(nextFile);
                    console.log(`✓ Processed: ${nextFile.name} (${processingStats.completed}/${processingStats.total})`);
                } else {
                    processingStats.failed++;
                    failedFiles.push({...nextFile, stage: 'processing', error: result.error});
                    console.error(`✗ Processing failed: ${nextFile.name} - ${result.error}`);
                    
                    handleProcessingFailure(nextFile, result.error);
                }
                
                // Small delay between files to prevent overwhelming
                setTimeout(() => {
                    processNextFileSequentially();
                }, 500);
                
            }).catch(error => {
                processingStats.inProgress = 0;
                nextFile.processing = false;
                nextFile.processed = true;
                processingStats.failed++;
                failedFiles.push({...nextFile, stage: 'processing', error: error.message});
                console.error(`✗ Processing exception: ${nextFile.name}`, error);
                
                handleProcessingFailure(nextFile, error.message);
                
                setTimeout(() => {
                    processNextFileSequentially();
                }, 500);
            });
        }

        function handleProcessingFailure(file, error) {
            // Auto-handle optional files silently
            if (file.criticality === 'optional') {
                console.log(`🔄 Auto-continuing after optional file issue: ${file.name}`);
                skippedFiles.push({...file, reason: 'Optional file - continuing import'});
                processingStats.skipped++;
                return;
            }
            
            // For critical files, log but still try to continue
            if (file.criticality === 'critical') {
                console.error(`❌ Critical file failed: ${file.name} - ${error}`);
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
                        } else if (xhr.responseJSON?.data?.message) {
                            error = xhr.responseJSON.data.message;
                        }
                        resolve({ success: false, error: error });
                    }
                });
            });
        }

        function completeImport() {
            updateProgress(95, 'Finalizing your demo...');
            
            const duration = Math.round((Date.now() - startTime) / 1000);
            const downloadSuccessRate = Math.round((downloadStats.completed / downloadStats.total) * 100);
            const processingSuccessRate = processingStats.total > 0 ? 
                Math.round(((processingStats.completed + processingStats.skipped) / processingStats.total) * 100) : 100;
            
            // Generate detailed file reports
            const fileReports = generateFileReports();
            
            // Detailed console summary for debugging
            console.log(`🎉 Import Summary:
                Duration: ${duration}s
                Downloads: ${downloadStats.completed}/${downloadStats.total} (${downloadSuccessRate}%)
                Processing: ${processingStats.completed}/${processingStats.total} (${processingSuccessRate}%)
                Skipped: ${processingStats.skipped + skippedFiles.length}
                Failed: ${failedFiles.length}
            `);
            
            // Log detailed file reports
            logDetailedFileReports(fileReports);
            
            // Store import summary for success page
            const importSummary = {
                duration: duration,
                downloads: { completed: downloadStats.completed, total: downloadStats.total },
                processing: { completed: processingStats.completed, total: processingStats.total },
                skipped: processingStats.skipped + skippedFiles.length,
                failed: failedFiles.length,
                skippedFiles: skippedFiles,
                failedFiles: failedFiles,
                fileReports: fileReports
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
            
            // Cleanup temp folder
            $.ajax({
                url: reignDemoInstaller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wbcom_cleanup_temp_folder',
                    temp_folder_id: tempFolderId,
                    keep_cache: true,
                    nonce: reignDemoInstaller.nonce
                },
                timeout: 30000,
                complete: function() {
                    updateProgress(100, `Demo imported successfully in ${duration}s!`);
                    
                    $(window).off('beforeunload');
                    
                    if (downloadSuccessRate >= 80 && processingSuccessRate >= 80) {
                        showUserMessage('Demo imported successfully! Redirecting...', 'success');
                    } else {
                        showUserMessage('Demo imported! Checking final details...', 'success');
                    }
                    
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

        function handleError(userMessage, debugDetails) {
            importInProgress = false;
            console.error('Import Error:', userMessage, debugDetails || '');
            
            $('#wbcom_get_theme_demo_data').prop('disabled', false).text('Try Again');
            updateProgress(0, 'Ready to try again');
            
            if (tempFolderId) {
                $.ajax({
                    url: reignDemoInstaller.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wbcom_cleanup_temp_folder',
                        temp_folder_id: tempFolderId,
                        keep_cache: true,
                        nonce: reignDemoInstaller.nonce
                    }
                });
            }
            
            showUserMessage(userMessage, 'error');
        }

        function showUserMessage(message, type) {
            $('.user-message').remove();
            
            var notification = $(`
                <div class="user-message ${type}">
                    <p>${message}</p>
                    <button type="button" class="dismiss">×</button>
                </div>
            `);
            
            $('.demo-listing-wrap').prepend(notification);
            
            if (type === 'info' || type === 'success') {
                setTimeout(function() {
                    notification.fadeOut();
                }, type === 'success' ? 3000 : 5000);
            }
            
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

        // Plugin management functionality
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
                        showUserMessage('Plugin installed successfully!', 'success');
                    } else {
                        thisRef.text('Install Now');
                        var errorMsg = response.data?.message || 'Installation had an issue. Please try again.';
                        showUserMessage(errorMsg, 'warning');
                    }
                },
                error: function(xhr, status) {
                    _hide_plugin_installer_loader();
                    thisRef.prop('disabled', false).text('Install Now');
                    
                    var errorMsg = status === 'timeout' ? 'Installation timeout. Please try again.' : 'Connection issue. Please check your internet.';
                    showUserMessage(errorMsg, 'warning');
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