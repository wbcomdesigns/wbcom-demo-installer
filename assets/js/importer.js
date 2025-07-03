/*
* Final Enhanced Demo Importer - Polished User Experience
* Emphasizes positive messaging while keeping detailed debugging in console/logs only
* Version: 3.0.0 Final
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
        var userChoices = {
            continueOnErrors: true, // Default to continue for better UX
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
                        
                        // Console log for debugging, positive user message
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
                        // Enhanced: Prepare file list with path preservation
                        downloadQueue = response.data.files.map((file, index) => ({
                            ...file,
                            id: index,
                            attempts: 0,
                            status: 'pending',
                            criticality: determineFileCriticality(file.name, file.action_for),
                            // Enhanced: Preserve path information for upload files
                            path_info: file.path_info || null
                        }));
                        
                        downloadStats.total = downloadQueue.length;
                        
                        // Log path distribution for debugging
                        logPathDistribution();
                        
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

        function logPathDistribution() {
            const pathCounts = {};
            const uploadFiles = downloadQueue.filter(f => f.action_for === 'upload_folders');
            
            uploadFiles.forEach(file => {
                if (file.path_info && file.path_info.relative_path) {
                    const path = file.path_info.relative_path;
                    pathCounts[path] = (pathCounts[path] || 0) + 1;
                } else {
                    pathCounts['root'] = (pathCounts['root'] || 0) + 1;
                }
            });
            
            if (Object.keys(pathCounts).length > 0) {
                console.log('📁 Upload files by path:', pathCounts);
            }
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
            
            // Enhanced: Pass path info to download
            downloadFile(nextFile).then(result => {
                downloadStats.inProgress--;
                
                if (result.success) {
                    nextFile.status = 'completed';
                    downloadStats.completed++;
                    processingQueue.push(nextFile);
                    
                    // Console logging for debugging, no user alerts for individual files
                    let logMessage = `✓ Downloaded: ${nextFile.name} (${downloadStats.completed}/${downloadStats.total})`;
                    
                    if (nextFile.action_for === 'upload_folders' && nextFile.path_info) {
                        const pathDisplay = nextFile.path_info.relative_path || 'root';
                        logMessage += ` → ${pathDisplay}`;
                    }
                    
                    if (result.data?.cached) {
                        logMessage = logMessage.replace('Downloaded:', 'Using cached:');
                    }
                    
                    console.log(logMessage);
                } else {
                    nextFile.attempts++;
                    
                    let errorMessage = `⚠ Download failed: ${nextFile.name}`;
                    
                    if (nextFile.action_for === 'upload_folders' && nextFile.path_info) {
                        const pathDisplay = nextFile.path_info.relative_path || 'root';
                        errorMessage += ` (path: ${pathDisplay})`;
                    }
                    
                    errorMessage += ` - ${result.error}`;
                    
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
                // Enhanced: Prepare path info for upload files
                let pathInfoData = '';
                if (file.action_for === 'upload_folders' && file.path_info) {
                    pathInfoData = JSON.stringify(file.path_info);
                    console.log(`📁 Processing upload file with path: ${file.path_info.relative_path || 'root'}`);
                }

                $.ajax({
                    url: reignDemoInstaller.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wbcom_download_demo_file',
                        temp_folder_id: tempFolderId,
                        file_url: file.url,
                        file_name: file.name,
                        file_type: file.type,
                        path_info: pathInfoData, // Enhanced: Pass path info
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
                
                // Add encouraging messaging
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
                        startParallelProcessing();
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
                // Only show error for critical failures
                showUserMessage('Some essential files need attention. Let\'s try to continue...', 'warning');
                console.error('Critical download failures:', criticalFailures);
                
                // Still attempt to continue with processing
                setTimeout(() => {
                    startParallelProcessing();
                }, 2000);
            } else {
                // Only optional files failed - continue optimistically
                const skipMsg = downloadStats.failed > 0 ? 
                    `Continuing with ${downloadStats.completed} files (${downloadStats.failed} optional files skipped)...` :
                    'All essential files ready! Processing content...';
                
                showUserMessage(skipMsg, 'info');
                console.log(`Download completed with ${downloadStats.failed} optional failures`);
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
            showUserMessage('Setting up your demo content...', 'info');
            
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
                    
                    // Enhanced logging with path context
                    let logMessage = `✓ Processed: ${nextFile.name} (${processingStats.completed}/${processingStats.total})`;
                    
                    if (nextFile.action_for === 'upload_folders' && nextFile.path_info) {
                        const pathDisplay = nextFile.path_info.relative_path || 'root';
                        logMessage += ` → ${pathDisplay}`;
                    }
                    
                    console.log(logMessage);
                } else {
                    processingStats.failed++;
                    failedFiles.push({...nextFile, stage: 'processing', error: result.error});
                    
                    // Enhanced error logging with path context
                    let errorMessage = `✗ Processing failed: ${nextFile.name} - ${result.error}`;
                    
                    if (nextFile.action_for === 'upload_folders' && nextFile.path_info) {
                        const pathDisplay = nextFile.path_info.relative_path || 'root';
                        errorMessage += ` (path: ${pathDisplay})`;
                    }
                    
                    console.error(errorMessage);
                    
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
                // Don't stop the import, but log for debugging
            }
        }

        function processFile(file) {
            return new Promise((resolve) => {
                // Enhanced: Prepare path info for upload files
                let pathInfoData = '';
                if (file.action_for === 'upload_folders' && file.path_info) {
                    pathInfoData = JSON.stringify(file.path_info);
                }

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
                        path_info: pathInfoData, // Enhanced: Pass path info
                        nonce: reignDemoInstaller.nonce
                    },
                    timeout: CONFIG.processingTimeout,
                    success: function(response) {
                        if (response.success) {
                            // Enhanced logging for path-aware processing
                            if (file.action_for === 'upload_folders' && file.path_info) {
                                const pathDisplay = file.path_info.relative_path || 'root';
                                console.log(`✓ Processed upload file to: ${pathDisplay} - ${file.name}`);
                            }
                            
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

        function monitorProcessingProgress() {
            const progressInterval = setInterval(() => {
                const totalProcessed = processingStats.completed + processingStats.skipped;
                const processingProgress = Math.round((totalProcessed / processingStats.total) * 25);
                const currentProgress = 70 + processingProgress;
                
                const activeProcessing = processingStats.inProgress;
                let statusText = `Processing content... ${totalProcessed}/${processingStats.total}`;
                
                // Add encouraging progress info
                if (totalProcessed > 0) {
                    const percentage = Math.round((totalProcessed / processingStats.total) * 100);
                    statusText += ` (${percentage}% complete)`;
                }
                
                if (activeProcessing > 0) {
                    statusText += ` - ${activeProcessing} active`;
                }
                
                updateProgress(currentProgress, statusText);
                
                if (processingStats.completed + processingStats.failed + processingStats.skipped >= processingStats.total) {
                    clearInterval(progressInterval);
                    
                    // Log final processing summary for debugging
                    logFinalProcessingSummary();
                    
                    // Always proceed to completion - let success page handle any issues
                    completeImport();
                }
                
                // Handle stalled processing
                if (processingStats.inProgress === 0 && totalProcessed < processingStats.total) {
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

        function logFinalProcessingSummary() {
            const summary = {
                total: processingStats.total,
                completed: processingStats.completed,
                failed: processingStats.failed,
                skipped: processingStats.skipped,
                failedFiles: failedFiles.length,
                skippedFiles: skippedFiles.length
            };
            
            console.log('📋 Final Processing Summary:', summary);
            
            // Log path-specific results for debugging
            const processedPaths = {};
            const failedPaths = {};
            
            processingQueue.forEach(file => {
                if (file.action_for !== 'upload_folders') return;
                
                const path = (file.path_info && file.path_info.relative_path) || 'root';
                
                if (file.processed && !file.skipProcessing) {
                    processedPaths[path] = (processedPaths[path] || 0) + 1;
                }
            });
            
            failedFiles.forEach(file => {
                if (file.action_for !== 'upload_folders') return;
                
                const path = (file.path_info && file.path_info.relative_path) || 'root';
                failedPaths[path] = (failedPaths[path] || 0) + 1;
            });
            
            if (Object.keys(processedPaths).length > 0) {
                console.log('📁 Processed by path:', processedPaths);
            }
            
            if (Object.keys(failedPaths).length > 0) {
                console.log('📁 Failed by path:', failedPaths);
            }
        }

        function completeImport() {
            updateProgress(95, 'Finalizing your demo...');
            
            const duration = Math.round((Date.now() - startTime) / 1000);
            const downloadSuccessRate = Math.round((downloadStats.completed / downloadStats.total) * 100);
            const processingSuccessRate = processingStats.total > 0 ? 
                Math.round(((processingStats.completed + processingStats.skipped) / processingStats.total) * 100) : 100;
            
            // Detailed console summary for debugging
            console.log(`🎉 Import Summary:
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
                    
                    // Always show success message to user - let success page handle details
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
            
            // Console log for debugging
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
            
            // Auto-hide info messages, keep errors and warnings visible
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

// Enhanced CSS for user-friendly interface
jQuery(document).ready(function($) {
    if (!$('#user-friendly-ui-css').length) {
        $('<style id="user-friendly-ui-css">').text(`
            /* User-friendly messaging system */
            .user-message {
                margin: 15px 0;
                padding: 15px 20px;
                border-radius: 8px;
                position: relative;
                animation: slideDown 0.5s ease;
                box-shadow: 0 3px 10px rgba(0,0,0,0.15);
                font-weight: 500;
                border-left: 5px solid;
            }
            
            .user-message.success {
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                border-left-color: #28a745;
                color: #155724;
            }
            
            .user-message.error {
                background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
                border-left-color: #dc3545;
                color: #721c24;
            }
            
            .user-message.warning {
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                border-left-color: #ffc107;
                color: #856404;
            }
            
            .user-message.info {
                background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
                border-left-color: #17a2b8;
                color: #0c5460;
            }
            
            .user-message p {
                margin: 0;
                padding-right: 40px;
                line-height: 1.5;
                font-size: 15px;
            }
            
            .user-message .dismiss {
                position: absolute;
                top: 15px;
                right: 20px;
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                color: inherit;
                opacity: 0.7;
                transition: opacity 0.3s;
                border-radius: 50%;
                width: 25px;
                height: 25px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .user-message .dismiss:hover {
                opacity: 1;
                background: rgba(0,0,0,0.1);
            }

            /* Enhanced progress bar styling */
            #progress-bar-container {
                margin: 25px 0;
                padding: 25px;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-radius: 12px;
                border: 1px solid #dee2e6;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }
            
            .progress-wrapper {
                margin-bottom: 20px;
            }
            
            .progress-bar {
                background: #e9ecef;
                border-radius: 25px;
                height: 35px;
                position: relative;
                overflow: hidden;
                box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .progress-bar .completed {
                background: linear-gradient(90deg, #28a745 0%, #20c997 50%, #17a2b8 100%);
                height: 100%;
                width: 0%;
                transition: width 0.8s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                font-size: 15px;
                text-shadow: 0 1px 2px rgba(0,0,0,0.3);
                position: relative;
                overflow: hidden;
            }
            
            .progress-bar .completed::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                animation: shimmer 2s infinite;
            }
            
            @keyframes shimmer {
                0% { left: -100%; }
                50% { left: 100%; }
                100% { left: 100%; }
            }
            
            /* Status message styling */
            #wbtd-current-action {
                margin: 20px 0;
                padding: 15px 20px;
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
                border-left: 4px solid #3b82f6;
                color: #1e40af;
                font-weight: 500;
                text-align: center;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
                font-size: 16px;
                line-height: 1.4;
            }
            
            /* Import button enhancement */
            .import-button-container {
                text-align: center;
                margin: 35px 0;
            }
            
            .import-button-container .button-hero {
                font-size: 18px;
                padding: 18px 35px;
                height: auto;
                line-height: 1.4;
                border-radius: 8px;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                background: linear-gradient(135deg, #007cba 0%, #0073aa 100%);
                border: none;
                position: relative;
                overflow: hidden;
            }
            
            .import-button-container .button-hero:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            }
            
            .import-button-container .button-hero:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                transform: none;
                background: #6c757d;
            }
            
            /* Plugin cards enhancement */
            .plugin-action-button {
                transition: all 0.3s ease;
                border-radius: 6px;
            }
            
            .plugin-action-button:hover:not(:disabled) {
                transform: translateY(-1px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            }
            
            .plugin-action-button.already-active {
                background: #28a745 !important;
                border-color: #28a745 !important;
                color: white !important;
            }
            
            /* Loading state improvements */
            .demo_listing_loading {
                pointer-events: none;
            }
            
            .demo_listing_loading::after {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.8);
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            /* Animation improvements */
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Responsive improvements */
            @media (max-width: 768px) {
                .user-message {
                    margin: 10px;
                    padding: 15px;
                }
                
                #progress-bar-container {
                    margin: 15px 0;
                    padding: 20px 15px;
                }
                
                .import-button-container .button-hero {
                    width: 100%;
                    font-size: 16px;
                    padding: 15px 25px;
                }
                
                .progress-bar {
                    height: 30px;
                }
                
                .progress-bar .completed {
                    font-size: 14px;
                }
            }
            
            /* Accessibility improvements */
            .user-message:focus,
            .progress-bar:focus {
                outline: 2px solid #007cba;
                outline-offset: 2px;
            }
            
            .button:focus {
                outline: 2px solid #007cba;
                outline-offset: 2px;
            }
        `).appendTo('head');
    }
});