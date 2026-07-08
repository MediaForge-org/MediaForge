// Theme mechanics (design-system.md §Theme-Mechanik):
// light/dark via a `data-theme` attribute on <html>; `prefers-color-scheme`
// is the default, a manual choice is persisted per browser and (when logged in)
// mirrored to the user profile server-side.

export type Theme = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'mediaforge.theme';

function systemPrefersDark(): boolean {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function resolve(theme: Theme): 'light' | 'dark' {
    if (theme === 'system') {
        return systemPrefersDark() ? 'dark' : 'light';
    }
    return theme;
}

export function getStoredTheme(): Theme {
    const value = localStorage.getItem(STORAGE_KEY);
    return value === 'light' || value === 'dark' || value === 'system' ? value : 'system';
}

export function applyTheme(theme: Theme): void {
    document.documentElement.setAttribute('data-theme', resolve(theme));
}

export function setTheme(theme: Theme): void {
    localStorage.setItem(STORAGE_KEY, theme);
    applyTheme(theme);
}

export function initTheme(): void {
    applyTheme(getStoredTheme());
    // React to OS-level changes while the user is on "system".
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (getStoredTheme() === 'system') {
            applyTheme('system');
        }
    });
}
