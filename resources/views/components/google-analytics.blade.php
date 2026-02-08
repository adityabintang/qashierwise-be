@if(config('services.google_analytics.measurement_id'))
<script>
    window.addEventListener('cookieConsentUpdated', function(e) {
        if (e.detail.consent === 'accepted') {
            loadGoogleAnalytics();
        }
    });

    if (localStorage.getItem('cookieConsent') === 'accepted') {
        loadGoogleAnalytics();
    }

    function loadGoogleAnalytics() {
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id={{ config("services.google_analytics.measurement_id") }}';
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        window.gtag = gtag;

        gtag('js', new Date());
        gtag('config', '{{ config("services.google_analytics.measurement_id") }}', {
            'anonymize_ip': true,
            'cookie_flags': 'SameSite=None;Secure'
        });

        console.log('Google Analytics 4 loaded successfully');
    }

    window.trackSignup = function(method = 'email') {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'sign_up', {
                'method': method,
                'page_location': window.location.href
            });
            console.log('GA4: signup event tracked', { method });
        }
    };

    window.trackSubscription = function(plan, price, currency = 'IDR') {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'purchase', {
                'transaction_id': Date.now(),
                'value': price,
                'currency': currency,
                'items': [{
                    'item_name': plan,
                    'item_category': 'Subscription'
                }]
            });
            console.log('GA4: purchase event tracked', { plan, price, currency });
        }
    };

    window.trackQRISGeneration = function(amount) {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'generate_qris', {
                'event_category': 'Payment',
                'event_label': 'QRIS Code Generated',
                'value': amount
            });
            console.log('GA4: generate_qris event tracked', { amount });
        }
    };

    window.trackChatbotInteraction = function(action, label = '') {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'chatbot_interaction', {
                'event_category': 'Chatbot',
                'event_action': action,
                'event_label': label
            });
            console.log('GA4: chatbot_interaction event tracked', { action, label });
        }
    };

    document.addEventListener('securitypolicyviolation', function(e) {
        console.error('CSP Violation:', {
            blockedURI: e.blockedURI,
            violatedDirective: e.violatedDirective,
            originalPolicy: e.originalPolicy,
            sourceFile: e.sourceFile,
            lineNumber: e.lineNumber,
            columnNumber: e.columnNumber
        });
    });
</script>
@endif
