const STORAGE_KEY = 'qubiq_theme';

function resolveInitialTheme() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'dark' || saved === 'light') {
            return saved;
        }
    } catch {
        // Ignore storage errors in strict privacy browsers
    }
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme) {
    const root = document.documentElement;
    const isDark = theme === 'dark';
    root.classList.toggle('dark', isDark);

    const sun = document.getElementById('theme-icon-sun');
    const moon = document.getElementById('theme-icon-moon');
    if (sun && moon) {
        sun.classList.toggle('hidden', !isDark);
        moon.classList.toggle('hidden', isDark);
    }
}

applyTheme(resolveInitialTheme());

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light');

    const toggleBtn = document.getElementById('theme-toggle-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
            applyTheme(next);
            try {
                localStorage.setItem(STORAGE_KEY, next);
            } catch {
                // Ignore storage quota/privacy restrictions
            }
        });
    }

    const mobileBtn = document.getElementById('mobile-menu-btn');
    const mobileDrawer = document.getElementById('mobile-nav-drawer');
    if (mobileBtn && mobileDrawer) {
        mobileBtn.addEventListener('click', () => {
            const isHidden = mobileDrawer.classList.toggle('hidden');
            mobileBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        });
    }
});

