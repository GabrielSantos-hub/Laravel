const STORAGE = {
    theme: 'gueass-theme',
    font: 'gueass-font-scale',
    contrast: 'gueass-high-contrast',
    sidebar: 'sidebar-collapsed',
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

function storedSidebarCollapsed() {
    try {
        return localStorage.getItem(STORAGE.sidebar) === '1';
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

    const state = document.getElementById('theme-toggle-state');
    if (state) {
        state.textContent = isDark ? 'On' : 'Off';
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

function initPreferences() {
    applyTheme(preferredTheme());
    applyFontScale(storedFontScale());
    applyHighContrast(storedHighContrast());

    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        applyTheme(next);
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
}

function syncSidebarToggleLabel(desktop, mobileOpen) {
    const toggle = document.getElementById('sidebar-toggle');
    if (!toggle) {
        return;
    }

    if (desktop) {
        const collapsed = document.documentElement.classList.contains('sidebar-collapsed');
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.setAttribute('aria-label', collapsed ? 'Expandir barra lateral' : 'Recolher barra lateral');
        return;
    }

    toggle.setAttribute('aria-expanded', mobileOpen ? 'true' : 'false');
    toggle.setAttribute('aria-label', mobileOpen ? 'Fechar menu de navegação' : 'Abrir menu de navegação');
}

function applySidebarCollapsed(collapsed) {
    document.documentElement.classList.toggle('sidebar-collapsed', collapsed);

    try {
        localStorage.setItem(STORAGE.sidebar, collapsed ? '1' : '0');
    } catch {
        // ignore
    }
}

function initSidebar() {
    const sidebar = document.getElementById('app-sidebar');
    const overlay = document.getElementById('app-overlay');
    const toggle = document.getElementById('sidebar-toggle');
    const closeBtn = document.getElementById('nav-close');

    if (!sidebar || !overlay || !toggle) {
        return;
    }

    const desktopQuery = window.matchMedia('(min-width: 768px)');

    const setMobileOpen = (open) => {
        sidebar.classList.toggle('is-open', open);
        overlay.classList.toggle('is-open', open);
        overlay.hidden = !open;
        document.body.classList.toggle('drawer-locked', open);
        sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
        syncSidebarToggleLabel(false, open);

        if (open) {
            (closeBtn ?? sidebar.querySelector('a'))?.focus();
        } else {
            toggle.focus();
        }
    };

    const syncViewport = () => {
        if (desktopQuery.matches) {
            sidebar.classList.remove('is-open');
            overlay.classList.remove('is-open');
            overlay.hidden = true;
            document.body.classList.remove('drawer-locked');
            sidebar.setAttribute('aria-hidden', 'false');
            applySidebarCollapsed(storedSidebarCollapsed());
            syncSidebarToggleLabel(true, false);
            return;
        }

        setMobileOpen(false);
    };

    toggle.addEventListener('click', () => {
        if (desktopQuery.matches) {
            applySidebarCollapsed(!document.documentElement.classList.contains('sidebar-collapsed'));
            syncSidebarToggleLabel(true, false);
            return;
        }

        setMobileOpen(!sidebar.classList.contains('is-open'));
    });

    closeBtn?.addEventListener('click', () => setMobileOpen(false));
    overlay.addEventListener('click', () => setMobileOpen(false));

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (!desktopQuery.matches) {
                setMobileOpen(false);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
            setMobileOpen(false);
        }
    });

    if (desktopQuery.addEventListener) {
        desktopQuery.addEventListener('change', syncViewport);
    } else {
        desktopQuery.addListener(syncViewport);
    }

    syncViewport();
}

function initModals() {
    const openModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.hidden = false;
        document.body.classList.add('drawer-locked');

        const preferred = modal.querySelector('input:not([type="hidden"]), textarea, select')
            ?? modal.querySelector('.app-modal-panel');
        preferred?.focus();
    };

    const closeModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        if (!document.querySelector('.app-modal:not([hidden])')) {
            document.body.classList.remove('drawer-locked');
        }
    };

    document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const modal = document.getElementById(trigger.getAttribute('data-modal-open'));
            if (!modal) {
                return;
            }

            if (trigger.dataset.resetAction) {
                const form = modal.querySelector('form');
                if (form) {
                    form.action = trigger.dataset.resetAction;
                    form.querySelectorAll('input[type="password"]').forEach((input) => {
                        input.value = '';
                    });
                }

                const nameEl = modal.querySelector('[data-reset-user-name]');
                if (nameEl) {
                    nameEl.textContent = trigger.dataset.resetName ?? '';
                }
            }

            openModal(modal);
        });
    });

    document.querySelectorAll('.app-modal').forEach((modal) => {
        modal.querySelectorAll('[data-modal-close]').forEach((closer) => {
            closer.addEventListener('click', () => closeModal(modal));
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('.app-modal:not([hidden])').forEach((modal) => closeModal(modal));
    });
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
            document.getElementById('theme-toggle')?.focus();
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
    initPreferences();
    initSidebar();
    initUserMenu();
    initModals();
});
