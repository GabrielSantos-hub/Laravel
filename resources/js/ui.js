const STORAGE = {
    theme: 'gueass-theme',
    font: 'gueass-font-scale',
    contrast: 'gueass-high-contrast',
};

const FONT_STEPS = [87.5, 100, 112.5, 125, 137.5];
const DEFAULT_FONT = 100;

function preferredTheme() {
    try {
        const stored = localStorage.getItem(STORAGE.theme);
        if (stored === 'dark' || stored === 'light') {
            return stored;
        }
    } catch {
        // localStorage indisponível
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function storedFontScale() {
    try {
        const value = Number.parseFloat(localStorage.getItem(STORAGE.font) ?? '');
        if (FONT_STEPS.includes(value)) {
            return value;
        }
    } catch {
        // localStorage indisponível
    }

    return DEFAULT_FONT;
}

function storedHighContrast() {
    try {
        return localStorage.getItem(STORAGE.contrast) === '1';
    } catch {
        return false;
    }
}

function applyTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);

    try {
        localStorage.setItem(STORAGE.theme, theme);
    } catch {
        // ignore
    }

    const toggle = document.getElementById('theme-toggle');
    if (toggle) {
        toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        toggle.setAttribute('aria-label', isDark ? 'Ativar modo claro' : 'Ativar modo escuro');
    }
}

function applyFontScale(scale) {
    const next = FONT_STEPS.includes(scale) ? scale : DEFAULT_FONT;
    document.documentElement.style.setProperty('--html-font-scale', `${next}%`);
    document.documentElement.style.fontSize = `${next}%`;

    try {
        localStorage.setItem(STORAGE.font, String(next));
    } catch {
        // ignore
    }

    const output = document.getElementById('a11y-font-value');
    if (output) {
        output.textContent = `${Math.round(next)}%`;
    }

    const decrease = document.getElementById('a11y-font-decrease');
    const increase = document.getElementById('a11y-font-increase');
    if (decrease) {
        decrease.disabled = next <= FONT_STEPS[0];
    }
    if (increase) {
        increase.disabled = next >= FONT_STEPS[FONT_STEPS.length - 1];
    }
}

function applyHighContrast(enabled) {
    document.documentElement.classList.toggle('high-contrast', enabled);

    try {
        localStorage.setItem(STORAGE.contrast, enabled ? '1' : '0');
    } catch {
        // ignore
    }

    const toggle = document.getElementById('a11y-contrast-toggle');
    if (toggle) {
        toggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    }
}

function closestFontStep(current, direction) {
    const index = FONT_STEPS.indexOf(current);
    const from = index === -1 ? FONT_STEPS.indexOf(DEFAULT_FONT) : index;
    const nextIndex = Math.min(FONT_STEPS.length - 1, Math.max(0, from + direction));

    return FONT_STEPS[nextIndex];
}

function initThemeToggle() {
    applyTheme(preferredTheme());

    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        applyTheme(next);
    });
}

function initAccessibilityWidget() {
    const root = document.getElementById('a11y-widget');
    const trigger = document.getElementById('a11y-toggle');
    const panel = document.getElementById('a11y-menu');

    if (!root || !trigger || !panel) {
        return;
    }

    applyFontScale(storedFontScale());
    applyHighContrast(storedHighContrast());

    const setOpen = (open) => {
        panel.hidden = !open;
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(panel.hidden);
        if (!panel.hidden) {
            document.getElementById('a11y-font-decrease')?.focus();
        }
    });

    document.getElementById('a11y-font-decrease')?.addEventListener('click', () => {
        applyFontScale(closestFontStep(storedFontScale(), -1));
    });

    document.getElementById('a11y-font-increase')?.addEventListener('click', () => {
        applyFontScale(closestFontStep(storedFontScale(), 1));
    });

    document.getElementById('a11y-contrast-toggle')?.addEventListener('click', () => {
        applyHighContrast(!document.documentElement.classList.contains('high-contrast'));
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            trigger.focus();
        }
    });
}

function initMobileDrawer() {
    const sidebar = document.getElementById('app-sidebar');
    const overlay = document.getElementById('app-overlay');
    const openBtn = document.getElementById('nav-open');
    const closeBtn = document.getElementById('nav-close');

    if (!sidebar || !overlay || !openBtn) {
        return;
    }

    const desktopQuery = window.matchMedia('(min-width: 1024px)');

    const setOpen = (open) => {
        if (desktopQuery.matches) {
            sidebar.classList.remove('is-open');
            overlay.classList.remove('is-open');
            document.body.classList.remove('drawer-locked');
            openBtn.setAttribute('aria-expanded', 'false');
            sidebar.setAttribute('aria-hidden', 'false');
            overlay.hidden = true;
            return;
        }

        sidebar.classList.toggle('is-open', open);
        overlay.classList.toggle('is-open', open);
        overlay.hidden = !open;
        document.body.classList.toggle('drawer-locked', open);
        openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');

        if (open) {
            (closeBtn ?? sidebar.querySelector('a'))?.focus();
        } else {
            openBtn.focus();
        }
    };

    openBtn.addEventListener('click', () => setOpen(true));
    closeBtn?.addEventListener('click', () => setOpen(false));
    overlay.addEventListener('click', () => setOpen(false));

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (!desktopQuery.matches) {
                setOpen(false);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
            setOpen(false);
        }
    });

    const syncDesktop = () => setOpen(false);
    if (desktopQuery.addEventListener) {
        desktopQuery.addEventListener('change', syncDesktop);
    } else {
        desktopQuery.addListener(syncDesktop);
    }

    setOpen(false);
}

function initUserMenu() {
    const root = document.getElementById('user-menu-widget');
    const trigger = document.getElementById('user-menu-toggle');
    const menu = document.getElementById('user-menu');

    if (!root || !trigger || !menu) {
        return;
    }

    const setOpen = (open) => {
        menu.hidden = !open;
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(menu.hidden);
        if (!menu.hidden) {
            menu.querySelector('button, a')?.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !menu.hidden) {
            setOpen(false);
            trigger.focus();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initAccessibilityWidget();
    initMobileDrawer();
    initUserMenu();
});
