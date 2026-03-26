<script>
    (function() {
        const DEBUG = true;
        const PREFIX = '[R2-Upload-Debug]';

        function log(...args) {
            if (DEBUG) console.log(PREFIX, ...args);
        }

        function logWarn(...args) {
            if (DEBUG) console.warn(PREFIX, ...args);
        }

        function logError(...args) {
            if (DEBUG) console.error(PREFIX, ...args);
        }

        // ==================== Intercept Fetch API ====================
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            const [resource, config] = args;
            
            // Monitor Livewire upload endpoints
            if (typeof resource === 'string' && (resource.includes('livewire') || resource.includes('upload'))) {
                log('📤 Fetch Request Start', {
                    url: resource,
                    method: config?.method || 'GET',
                    headers: config?.headers,
                    body: config?.body ? '(binary)' : undefined,
                });
            }

            return originalFetch.apply(this, args)
                .then(response => {
                    if (typeof resource === 'string' && (resource.includes('livewire') || resource.includes('upload'))) {
                        log('✅ Fetch Response Received', {
                            url: resource,
                            status: response.status,
                            statusText: response.statusText,
                            headers: Object.fromEntries(response.headers.entries()),
                        });
                    }
                    return response;
                })
                .catch(error => {
                    if (typeof resource === 'string' && (resource.includes('livewire') || resource.includes('upload'))) {
                        logError('❌ Fetch Error', {
                            url: resource,
                            error: error.message,
                            stack: error.stack,
                        });
                    }
                    throw error;
                });
        };

        // ==================== Monitor XMLHttpRequest ====================
        const originalXHR = window.XMLHttpRequest.prototype.open;
        window.XMLHttpRequest.prototype.open = function(method, url, ...rest) {
            if (url.includes('livewire') || url.includes('upload')) {
                log('📡 XHR Request Start', { method, url });
                
                this.addEventListener('load', function() {
                    log('✅ XHR Response Loaded', {
                        url,
                        status: this.status,
                        statusText: this.statusText,
                    });
                });

                this.addEventListener('error', function() {
                    logError('❌ XHR Error', {
                        url,
                        status: this.status,
                        statusText: this.statusText,
                    });
                });

                this.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        log('⏳ XHR Upload Progress', {
                            loaded: e.loaded,
                            total: e.total,
                            percent: ((e.loaded / e.total) * 100).toFixed(2) + '%',
                        });
                    }
                });
            }
            return originalXHR.apply(this, [method, url, ...rest]);
        };

        // ==================== Monitor File Inputs ====================
        document.addEventListener('change', function(e) {
            if (e.target.type === 'file') {
                const files = e.target.files;
                if (files.length > 0) {
                    log('📁 File Selected', {
                        field: e.target.name || e.target.id || 'unknown',
                        fileCount: files.length,
                        files: Array.from(files).map(f => ({
                            name: f.name,
                            size: f.size + ' bytes',
                            type: f.type,
                        })),
                    });

                    // Validate file size
                    Array.from(files).forEach(file => {
                        const maxSizeKB = e.target.dataset.maxSize || 5120;
                        const maxSizeBytes = maxSizeKB * 1024;
                        
                        if (file.size > maxSizeBytes) {
                            logWarn('⚠️ File Size Exceeds Limit', {
                                fileName: file.name,
                                fileSize: file.size,
                                maxSize: maxSizeBytes,
                            });
                        }
                    });
                }
            }
        }, true);

        // ==================== Monitor Drag & Drop ====================
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            document.addEventListener(eventName, function(e) {
                if (e.dataTransfer?.types?.includes('Files')) {
                    log(`🎯 Drag & Drop Event: ${eventName}`, {
                        itemCount: e.dataTransfer.items?.length || 0,
                    });

                    if (eventName === 'drop') {
                        const files = e.dataTransfer.files;
                        log('📁 Files Dropped', {
                            count: files.length,
                            files: Array.from(files).map(f => ({
                                name: f.name,
                                size: f.size + ' bytes',
                            })),
                        });
                    }
                }
            }, true);
        });

        // ==================== Monitor Network (DevTools) ====================
        log('🚀 Upload Debug Script Loaded', {
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            url: window.location.href,
            localStorage: {
                viewer_timezone: localStorage.getItem('viewer_timezone'),
                XSRF_TOKEN_exists: !!document.querySelector('meta[name="csrf-token"]'),
            },
        });

        // ==================== Check Livewire Presence ====================
        if (window.Livewire) {
            log('✅ Livewire Detected', {
                version: window.Livewire.version || 'unknown',
            });

            // Monitor Livewire events
            window.Livewire.on('upload:start', () => {
                log('⬆️ Livewire: Upload Start Event');
            });

            window.Livewire.on('upload:finish', () => {
                log('✅ Livewire: Upload Finish Event');
            });

            window.Livewire.on('upload:error', (error) => {
                logError('❌ Livewire: Upload Error Event', error);
            });
        } else {
            logWarn('⚠️ Livewire Not Detected');
        }

        // ==================== Check Filament Presence ====================
        if (window.Alpine) {
            log('✅ Alpine.js Detected');
        } else {
            logWarn('⚠️ Alpine.js Not Detected');
        }

        // ==================== Monitor All Network Requests to R2 ====================
        const observer = new PerformanceObserver((list) => {
            list.getEntries().forEach((entry) => {
                if (entry.name.includes('r2.cloudflarestorage.com')) {
                    log('🔗 R2 Network Request', {
                        url: entry.name,
                        duration: entry.duration + 'ms',
                        transferSize: entry.transferSize + ' bytes',
                    });
                }
            });
        });

        try {
            observer.observe({ entryTypes: ['resource'] });
            log('✅ Performance Observer Started');
        } catch (e) {
            logWarn('⚠️ Performance Observer Not Supported', e.message);
        }

        // ==================== Monitor Livewire Form Submission ====================
        if (window.Livewire) {
            window.Livewire.on('livewire:initialized', () => {
                log('🎯 Livewire Initialized - monitoring form events...');
            });

            window.Livewire.on('livewire:updated', (component) => {
                log('🔄 Livewire Updated', {
                    componentName: component?.name || 'unknown',
                    timestamp: new Date().toISOString(),
                });
            });

            window.Livewire.on('livewire:updated.featured_image', () => {
                log('✅ Featured Image Updated in Livewire', {
                    timestamp: new Date().toISOString(),
                    message: 'Backend is now processing upload to R2...',
                });
            });

            window.Livewire.on('livewire:updated.seo_image', () => {
                log('✅ SEO Image Updated in Livewire', {
                    timestamp: new Date().toISOString(),
                    message: 'Backend is now processing upload to R2...',
                });
            });
        }

        // ==================== Global Error Handler ====================
        window.addEventListener('error', (e) => {
            if (e.message.includes('upload') || e.message.includes('fetch') || e.message.includes('storage')) {
                logError('🔴 Global Error Caught', {
                    message: e.message,
                    filename: e.filename,
                    lineno: e.lineno,
                });
            }
        });

        window.addEventListener('unhandledrejection', (e) => {
            if (e.reason?.message?.includes('upload') || String(e.reason).includes('upload')) {
                logError('🔴 Unhandled Promise Rejection', {
                    reason: e.reason,
                });
            }
        });

        // ==================== Export Helper Functions ====================
        window.R2DebugHelper = {
            checkConfig: () => {
                log('📋 R2 Configuration Check', {
                    r2Endpoint: '{{ config("filesystems.disks.r2.endpoint") }}',
                    r2Bucket: '{{ config("filesystems.disks.r2.bucket") }}',
                    r2Url: '{{ config("filesystems.disks.r2.url") }}',
                    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content?.substring(0, 10) + '...',
                });
            },
            analyzeUploadError: () => {
                log('📊 Upload Error Analysis', {
                    csrfToken: !!document.querySelector('meta[name="csrf-token"]'),
                    livewireExists: !!window.Livewire,
                    alpineExists: !!window.Alpine,
                    r2Accessible: 'Check Network tab for R2 requests',
                    cspHeaders: 'Check Response Headers for Content-Security-Policy',
                });
            },
        };

        log('💡 Available Commands:');
        log('   - window.R2DebugHelper.checkConfig()');
        log('   - window.R2DebugHelper.analyzeUploadError()');

    })();
</script>
