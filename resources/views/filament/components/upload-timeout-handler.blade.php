<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Upload timeout configuration (30 seconds)
        const UPLOAD_TIMEOUT = 30000;
        
        // Track active uploads
        const activeUploads = new Map();
        
        // Monitor Livewire upload events
        window.addEventListener('livewire-upload-start', event => {
            const uploadId = event.detail.id;
            
            // Set timeout for this upload
            const timeoutId = setTimeout(() => {
                if (activeUploads.has(uploadId)) {
                    // Upload timed out
                    console.error('Upload timeout for:', uploadId);
                    
                    // Show error notification
                    if (window.$wireui) {
                        window.$wireui.notify({
                            title: 'Upload Timeout',
                            description: 'Image upload failed. Please check your connection and try again.',
                            icon: 'error'
                        });
                    } else {
                        alert('Upload timeout! Please check your connection and try again.');
                    }
                    
                    // Cancel the upload
                    window.Livewire.emit('cancelUpload', uploadId);
                    
                    // Clean up
                    activeUploads.delete(uploadId);
                }
            }, UPLOAD_TIMEOUT);
            
            activeUploads.set(uploadId, {
                timeoutId: timeoutId,
                startTime: Date.now()
            });
            
            console.log('Upload started:', uploadId);
        });
        
        // Clear timeout when upload finishes
        window.addEventListener('livewire-upload-finish', event => {
            const uploadId = event.detail.id;
            
            if (activeUploads.has(uploadId)) {
                const upload = activeUploads.get(uploadId);
                clearTimeout(upload.timeoutId);
                
                const duration = Date.now() - upload.startTime;
                console.log(`Upload completed in ${duration}ms:`, uploadId);
                
                activeUploads.delete(uploadId);
            }
        });
        
        // Clear timeout when upload errors
        window.addEventListener('livewire-upload-error', event => {
            const uploadId = event.detail.id;
            
            if (activeUploads.has(uploadId)) {
                const upload = activeUploads.get(uploadId);
                clearTimeout(upload.timeoutId);
                
                console.error('Upload error:', uploadId, event.detail);
                
                // Show error notification
                if (window.$wireui) {
                    window.$wireui.notify({
                        title: 'Upload Failed',
                        description: event.detail.error || 'Failed to upload image. Please try again.',
                        icon: 'error'
                    });
                }
                
                activeUploads.delete(uploadId);
            }
        });
        
        // Monitor upload progress
        window.addEventListener('livewire-upload-progress', event => {
            const uploadId = event.detail.id;
            const progress = event.detail.progress;
            
            if (activeUploads.has(uploadId)) {
                console.log(`Upload progress ${progress}%:`, uploadId);
            }
        });
        
        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            activeUploads.forEach((upload, uploadId) => {
                clearTimeout(upload.timeoutId);
            });
            activeUploads.clear();
        });
    });
</script>
