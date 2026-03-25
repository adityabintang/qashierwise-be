<script>
(() => {
    try {
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (!timezone) {
            return;
        }

        const cookieName = 'viewer_timezone=';
        const existingCookie = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith(cookieName));

        const existingValue = existingCookie ? decodeURIComponent(existingCookie.slice(cookieName.length)) : null;

        if (existingValue !== timezone) {
            document.cookie = `viewer_timezone=${encodeURIComponent(timezone)}; path=/; max-age=31536000; samesite=lax`;
        }
    } catch (error) {
        // Ignore timezone detection errors in unsupported browsers.
    }
})();
</script>
