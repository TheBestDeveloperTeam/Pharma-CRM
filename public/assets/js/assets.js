/**
 * Asset System Runtime
 * Handles theme toggling, SVG sprite injection, and Lottie color binding.
 */
class AssetSystem {
    constructor() {
        this.initTheme();
        this.injectSprite();
    }

    initTheme() {
        // Detect system preference or stored preference
        const storedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (storedTheme) {
            document.documentElement.setAttribute('data-theme', storedTheme);
        } else if (prefersDark) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    }

    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        // Trigger event for Lottie to re-bind colors
        window.dispatchEvent(new Event('themeChanged'));
    }

    async injectSprite() {
        try {
            const res = await fetch('/assets/icons/sprite.svg');
            const text = await res.text();
            const div = document.createElement('div');
            div.innerHTML = text;
            document.body.insertBefore(div, document.body.childNodes[0]);
        } catch (e) {
            console.error('Failed to load SVG sprite', e);
        }
    }

    /**
     * Helper to load a bodymovin animation and bind it to the CSS token tree.
     */
    async loadLottieWithTheme(containerId, jsonPath, bindingPath) {
        // Pseudo-implementation of binding traversal
        const [animData, bindings] = await Promise.all([
            fetch(jsonPath).then(r => r.json()),
            fetch(bindingPath).then(r => r.json())
        ]);

        // Traverse animData and replace colors based on bindings array + getComputedStyle()
        // ... (Runtime parsing logic here) ...

        /*
        lottie.loadAnimation({
            container: document.getElementById(containerId),
            renderer: 'svg',
            loop: true,
            autoplay: true,
            animationData: animData
        });
        */
    }
}

window.AssetSystem = new AssetSystem();
