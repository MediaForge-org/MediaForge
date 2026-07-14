// Design-preset mechanics. Independent of light/dark appearance (see theme.ts):
// the visual "skin" is chosen via a `data-design-preset` attribute on <html> and
// persisted locally. No backend, no user data.

export type DesignPreset =
    | 'neon-command'
    | 'streaming-os'
    | 'glass-workspace'
    | 'holographic-console'
    | 'hybrid';

export interface PresetMeta {
    id: DesignPreset;
    name: string;
    description: string;
    /** Two swatch colours for the selector preview. */
    swatch: [string, string];
}

export const PRESETS: PresetMeta[] = [
    { id: 'neon-command', name: 'Neon Command Center', description: 'Sci-fi control room with grid + neon rails.', swatch: ['#22d3ee', '#f59e0b'] },
    { id: 'streaming-os', name: 'Premium Streaming OS', description: 'Cinematic media platform, soft blobs.', swatch: ['#0f2033', '#f59e0b'] },
    { id: 'glass-workspace', name: 'Glassmorphism Workspace', description: 'Clean frosted glass, Apple/Arc-like.', swatch: ['#e2e8f0', '#38bdf8'] },
    { id: 'holographic-console', name: 'Holographic Console', description: 'Aurora gradients + prismatic edges.', swatch: ['#a855f7', '#22d3ee'] },
    { id: 'hybrid', name: 'Hybrid Media OS', description: 'Streaming OS + command center blend.', swatch: ['#f59e0b', '#22d3ee'] },
];

const STORAGE_KEY = 'mediaforge.preset';
const DEFAULT_PRESET: DesignPreset = 'hybrid';

function isPreset(value: string | null): value is DesignPreset {
    return PRESETS.some((p) => p.id === value);
}

export function getStoredPreset(): DesignPreset {
    const value = localStorage.getItem(STORAGE_KEY);
    return isPreset(value) ? value : DEFAULT_PRESET;
}

export function applyPreset(preset: DesignPreset): void {
    document.documentElement.setAttribute('data-design-preset', preset);
}

export function setPreset(preset: DesignPreset): void {
    localStorage.setItem(STORAGE_KEY, preset);
    applyPreset(preset);
}

export function initPreset(): void {
    applyPreset(getStoredPreset());
}
