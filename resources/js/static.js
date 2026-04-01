let alpineLoaded = false;

function loadAlpine() {
    if (alpineLoaded) return;
    alpineLoaded = true;
    
    import('./alpine-loader.js').catch(err => {
        console.error('Failed to load Alpine.js:', err);
    });
}

setTimeout(loadAlpine, 3000);

['click', 'touchstart', 'keydown'].forEach(event => {
    document.addEventListener(event, loadAlpine, { once: true, passive: true });
});
