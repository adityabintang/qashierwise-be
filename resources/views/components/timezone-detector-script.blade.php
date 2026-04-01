<script>
(() => {
    try {
        const isAdminBlogForm = /^\/admin\/blog-posts(\/[^/]+\/edit|\/create)?$/i.test(window.location.pathname);
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        const syncViewerTimezoneInput = () => {
            if (!timezone) {
                return;
            }

            document
                .querySelectorAll('input[name*="viewer_timezone"], input[id*="viewer_timezone"]')
                .forEach((input) => {
                    input.value = timezone;
                });
        };

        if (isAdminBlogForm) {
            document.addEventListener('submit', () => {
                syncViewerTimezoneInput();

                const now = new Date();
                const publishedAtInput = document.querySelector('input[id*="published_at"], input[name*="published_at"]');
                const statusInput = document.querySelector('select[id*="status"], select[name*="status"]');
                const viewerTimezoneInput = document.querySelector('input[name*="viewer_timezone"], input[id*="viewer_timezone"]');

                console.log('[Blog Time Check] Now:', now.toString(), '| UTC:', now.toISOString());
                console.log('[Blog Time Check] User Published At:', publishedAtInput?.value ?? '(empty)');
                console.log('[Blog Time Check] Status:', statusInput?.value ?? '(empty)');
                console.log('[Blog Time Check] Browser Timezone:', timezone ?? '(empty)');
                console.log('[Blog Time Check] Hidden viewer_timezone:', viewerTimezoneInput?.value ?? '(empty)');
            }, true);
        }

        if (!timezone) {
            return;
        }

        syncViewerTimezoneInput();

        localStorage.setItem('viewer_timezone', timezone);

        const cookieName = 'viewer_timezone=';
        const existingCookie = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith(cookieName));

        const existingValue = existingCookie ? decodeURIComponent(existingCookie.slice(cookieName.length)) : null;
        const hasChanged = existingValue !== timezone;

        if (hasChanged) {
            document.cookie = `viewer_timezone=${encodeURIComponent(timezone)}; path=/; max-age=31536000; samesite=lax`;

            if (isAdminBlogForm && !sessionStorage.getItem('viewer_timezone_reloaded')) {
                sessionStorage.setItem('viewer_timezone_reloaded', '1');
                window.location.reload();
            }
        }
    } catch (error) {
        // Ignore timezone detection errors in unsupported browsers.
    }
})();
</script>
