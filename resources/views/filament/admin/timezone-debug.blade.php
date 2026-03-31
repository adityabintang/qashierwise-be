<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('=== BLOG POST TIMEZONE DEBUG ===');
        
        // Function to log timezone info
        function logTimezoneInfo() {
            const now = new Date();
            const jakartaTime = new Intl.DateTimeFormat('id-ID', {
                timeZone: 'Asia/Jakarta',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }).format(now);
            
            const utcTime = new Intl.DateTimeFormat('en-US', {
                timeZone: 'UTC',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }).format(now);
            
            console.log('📅 Waktu Saat Ini:');
            console.log('  - Browser Local:', now.toString());
            console.log('  - Asia/Jakarta (WIB):', jakartaTime);
            console.log('  - UTC:', utcTime);
            console.log('  - Timestamp:', now.getTime());
            console.log('  - ISO String:', now.toISOString());
        }
        
        // Log initial timezone info
        logTimezoneInfo();
        
        // Watch for published_at input changes
        const publishedAtInput = document.querySelector('input[id*="published_at"]');
        
        if (publishedAtInput) {
            console.log('✅ Published At input found');
            
            // Log on input change
            publishedAtInput.addEventListener('change', function(e) {
                console.log('\n📝 Published At Changed:');
                console.log('  - Input Value:', e.target.value);
                
                if (e.target.value) {
                    try {
                        // Parse as Jakarta time
                        const inputDate = new Date(e.target.value);
                        
                        console.log('  - Parsed Date:', inputDate.toString());
                        console.log('  - ISO String:', inputDate.toISOString());
                        console.log('  - Timestamp:', inputDate.getTime());
                        
                        // Convert to UTC
                        const utcTime = new Intl.DateTimeFormat('en-US', {
                            timeZone: 'UTC',
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: false
                        }).format(inputDate);
                        
                        console.log('  - UTC Time:', utcTime);
                        
                        // Compare with now
                        const now = new Date();
                        const diff = inputDate.getTime() - now.getTime();
                        const diffMinutes = Math.round(diff / 1000 / 60);
                        
                        console.log('\n⏰ Comparison:');
                        console.log('  - Now:', now.toISOString());
                        console.log('  - Published At:', inputDate.toISOString());
                        console.log('  - Difference:', diffMinutes, 'minutes');
                        
                        if (diff > 0) {
                            console.warn('⚠️ WARNING: Published date is in the FUTURE!');
                        } else {
                            console.log('✅ OK: Published date is in the PAST');
                        }
                    } catch (error) {
                        console.error('❌ Error parsing date:', error);
                    }
                }
            });
            
            // Log on blur
            publishedAtInput.addEventListener('blur', function(e) {
                console.log('\n👁️ Published At Blur - Current Value:', e.target.value);
                logTimezoneInfo();
            });
        } else {
            console.warn('⚠️ Published At input NOT found');
        }
        
        // Watch for status select changes
        const statusSelect = document.querySelector('select[id*="status"]');
        
        if (statusSelect) {
            console.log('✅ Status select found');
            
            statusSelect.addEventListener('change', function(e) {
                console.log('\n📊 Status Changed:', e.target.value);
                
                if (e.target.value === 'published') {
                    console.log('⚠️ Status is PUBLISHED - Date validation will be enforced');
                    logTimezoneInfo();
                }
            });
        }
        
        // Watch for form submission
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                console.log('\n🚀 FORM SUBMITTING...');
                logTimezoneInfo();
                
                if (publishedAtInput && publishedAtInput.value) {
                    console.log('📤 Submitting Published At:', publishedAtInput.value);
                }
                
                if (statusSelect) {
                    console.log('📤 Submitting Status:', statusSelect.value);
                }
            });
        }
        
        // Log every 10 seconds for reference
        setInterval(function() {
            console.log('\n⏱️ Time Check (every 10s):');
            logTimezoneInfo();
        }, 10000);
    });
</script>
