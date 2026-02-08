@props(['locale' => app()->getLocale()])

<div x-data="cookieConsent()"
     x-show="showBanner"
     x-cloak
     class="fixed bottom-0 left-0 right-0 z-50 bg-gray-900/95 backdrop-blur-sm border-t border-gray-700"
     style="contain: layout;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex-1 text-gray-300 text-sm">
                <p class="mb-2">
                    @if($locale === 'id')
                        Kami menggunakan cookies untuk meningkatkan pengalaman Anda dan menganalisis trafik. Dengan melanjutkan, Anda menyetujui penggunaan cookies kami.
                    @else
                        We use cookies to improve your experience and analyze traffic. By continuing, you agree to our use of cookies.
                    @endif
                </p>
                <a href="{{ route('privacy-policy') }}" class="text-blue-400 hover:text-blue-300 underline text-xs">
                    @if($locale === 'id')
                        Baca Kebijakan Privasi
                    @else
                        Read our Privacy Policy
                    @endif
                </a>
            </div>
            <div class="flex gap-3 shrink-0">
                <button @click="declineCookies()"
                        class="px-4 py-2 text-sm font-medium text-gray-300 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors">
                    @if($locale === 'id')
                        Tolak
                    @else
                        Decline
                    @endif
                </button>
                <button @click="acceptCookies()"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                    @if($locale === 'id')
                        Terima Semua
                    @else
                        Accept All
                    @endif
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function cookieConsent() {
    return {
        showBanner: false,
        init() {
            const consent = localStorage.getItem('cookieConsent');
            const consentTimestamp = localStorage.getItem('cookieConsentTimestamp');

            if (!consent || this.isExpired(consentTimestamp)) {
                this.showBanner = true;
            }
        },
        acceptCookies() {
            localStorage.setItem('cookieConsent', 'accepted');
            localStorage.setItem('cookieConsentTimestamp', Date.now());
            this.showBanner = false;

            window.dispatchEvent(new CustomEvent('cookieConsentUpdated', {
                detail: { consent: 'accepted' }
            }));
        },
        declineCookies() {
            localStorage.setItem('cookieConsent', 'declined');
            localStorage.setItem('cookieConsentTimestamp', Date.now());
            this.showBanner = false;

            window.dispatchEvent(new CustomEvent('cookieConsentUpdated', {
                detail: { consent: 'declined' }
            }));
        },
        isExpired(timestamp) {
            if (!timestamp) return true;
            const oneYear = 365 * 24 * 60 * 60 * 1000;
            return (Date.now() - parseInt(timestamp)) > oneYear;
        }
    }
}
</script>
