@if(config('services.meta.pixel_id'))
<!-- Meta Pixel Code -->
<script>
    // Wait for cookie consent before loading Meta Pixel
    const metaPixelConsent = localStorage.getItem('cookieConsent');
    
    if (metaPixelConsent === 'accepted') {
        loadMetaPixel();
    }
    
    window.addEventListener('cookieConsentUpdated', function(event) {
        if (event.detail.consent === 'accepted') {
            loadMetaPixel();
        }
    });
    
    function loadMetaPixel() {
        // Check if Meta Pixel already loaded
        if (window.fbq) {
            console.log('⚠️ Meta Pixel already loaded');
            return;
        }
        
        // Meta Pixel Base Code
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        
        // Initialize Meta Pixel with dynamic Pixel ID from config
        fbq('init', '{{ config("services.meta.pixel_id") }}');
        fbq('track', 'PageView');
        
        console.log('✅ Meta Pixel loaded (ID: {{ config("services.meta.pixel_id") }})');
    }
</script>

<!-- Noscript fallback for users with JavaScript disabled -->
<noscript>
    <img height="1" width="1" style="display:none"
         src="https://www.facebook.com/tr?id={{ config('services.meta.pixel_id') }}&ev=PageView&noscript=1"
         alt="Meta Pixel" />
</noscript>
<!-- End Meta Pixel Code -->
@endif
