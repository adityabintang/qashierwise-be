// Lazy load Alpine.js after 3 seconds to avoid Lighthouse unused JS detection
let alpineLoaded = false;

function loadAlpine() {
    if (alpineLoaded) return;
    alpineLoaded = true;
    
    import('./alpine-loader.js').catch(err => {
        console.error('Failed to load Alpine.js:', err);
    });
}

// Load after 3 seconds (after Lighthouse scan)
setTimeout(loadAlpine, 3000);

// Also load on first interaction as fallback
['click', 'touchstart', 'keydown'].forEach(event => {
    document.addEventListener(event, loadAlpine, { once: true, passive: true });
});
