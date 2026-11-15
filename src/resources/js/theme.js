// Tema light/dark/system: localStorage['theme'], diterapkan ke <html data-theme>.
const KEY = 'theme';
const MODES = ['light', 'dark', 'system'];
const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

function readMode() {
    try {
        const mode = localStorage.getItem(KEY);
        return MODES.includes(mode) ? mode : 'system';
    } catch {
        return 'system';
    }
}

function applyTheme(mode = readMode()) {
    const theme = mode === 'system' ? (media?.matches ? 'dark' : 'light') : mode;
    const root = document.documentElement;
    root.dataset.theme = theme;
    root.dataset.themeMode = mode;
    root.style.colorScheme = theme;

    document.querySelectorAll('[data-theme-set]').forEach((button) => {
        const active = button.dataset.themeSet === mode;
        button.setAttribute('aria-checked', active ? 'true' : 'false');
        button.tabIndex = active ? 0 : -1;
    });
}

window.setTheme = (mode) => {
    if (!MODES.includes(mode)) return;
    try {
        localStorage.setItem(KEY, mode);
    } catch {
        // Storage diblokir: tetap terapkan untuk halaman ini saja
    }
    applyTheme(mode);
};

media?.addEventListener?.('change', () => readMode() === 'system' && applyTheme('system'));
window.addEventListener('storage', (event) => event.key === KEY && applyTheme());
document.addEventListener('livewire:navigated', () => applyTheme());

// Delegasi event: tanpa handler inline (aman untuk CSP)
document.addEventListener('click', (event) => {
    const button = event.target.closest?.('[data-theme-set]');
    if (button) window.setTheme(button.dataset.themeSet);
});

document.addEventListener('keydown', (event) => {
    const button = event.target.closest?.('[data-theme-set]');
    const step = { ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1 }[event.key];
    if (!button || !step) return;
    event.preventDefault();
    const group = [...button.closest('[role="radiogroup"]').querySelectorAll('[data-theme-set]')];
    const next = group[(group.indexOf(button) + step + group.length) % group.length];
    next.focus();
    window.setTheme(next.dataset.themeSet);
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => applyTheme());
} else {
    applyTheme();
}
