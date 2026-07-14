import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { type DesignPreset, getStoredPreset, PRESETS, setPreset } from '@/lib/preset';

interface Position {
    top: number;
    right: number;
    maxHeight: number;
}

function Swatch({ colors }: { colors: [string, string] }) {
    return (
        <span
            aria-hidden
            className="size-5 shrink-0 rounded-full ring-1 ring-black/10"
            style={{ background: `linear-gradient(135deg, ${colors[0]}, ${colors[1]})` }}
        />
    );
}

/**
 * Topbar design-preset picker. The menu is rendered in a body portal with
 * `position: fixed` computed from the trigger's viewport rect, so it always
 * opens directly below the button — free of the topbar's backdrop-filter
 * containing block / stacking context (which previously pinned it to the top).
 */
export default function PresetSelector() {
    const [open, setOpen] = useState(false);
    const [current, setCurrent] = useState<DesignPreset>('hybrid');
    const [pos, setPos] = useState<Position | null>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setCurrent(getStoredPreset());
    }, []);

    function computePosition() {
        const button = buttonRef.current;
        if (!button) {
            return;
        }
        const rect = button.getBoundingClientRect();
        const top = rect.bottom + 10;
        setPos({
            top,
            right: Math.max(8, window.innerWidth - rect.right),
            maxHeight: Math.min(480, window.innerHeight - top - 16),
        });
    }

    useLayoutEffect(() => {
        if (open) {
            computePosition();
        }
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }
        function onDocPointer(event: MouseEvent) {
            const target = event.target as Node;
            if (buttonRef.current?.contains(target) || panelRef.current?.contains(target)) {
                return;
            }
            setOpen(false);
        }
        function onKey(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        }
        function reflow() {
            computePosition();
        }
        document.addEventListener('mousedown', onDocPointer);
        document.addEventListener('keydown', onKey);
        window.addEventListener('resize', reflow);
        window.addEventListener('scroll', reflow, true);
        return () => {
            document.removeEventListener('mousedown', onDocPointer);
            document.removeEventListener('keydown', onKey);
            window.removeEventListener('resize', reflow);
            window.removeEventListener('scroll', reflow, true);
        };
    }, [open]);

    function choose(preset: DesignPreset) {
        setPreset(preset);
        setCurrent(preset);
        setOpen(false);
    }

    const active = PRESETS.find((p) => p.id === current) ?? PRESETS[PRESETS.length - 1];

    return (
        <>
            <button
                aria-expanded={open}
                aria-haspopup="listbox"
                className="mf-button mf-button-secondary gap-2 px-3 py-2 text-xs"
                onClick={() => setOpen((v) => !v)}
                ref={buttonRef}
                type="button"
            >
                <Swatch colors={active.swatch} />
                <span className="hidden sm:inline">{active.name}</span>
                <svg aria-hidden className="size-3.5 text-fg-subtle" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path d="m6 9 6 6 6-6" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </button>

            {open && pos && createPortal(
                <div
                    className="mf-panel overflow-y-auto p-1.5 shadow-2xl"
                    ref={panelRef}
                    role="listbox"
                    style={{
                        position: 'fixed',
                        top: `${pos.top}px`,
                        right: `${pos.right}px`,
                        zIndex: 9999,
                        width: '20rem',
                        maxWidth: 'calc(100vw - 16px)',
                        maxHeight: `${pos.maxHeight}px`,
                    }}
                >
                    {PRESETS.map((preset) => (
                        <button
                            aria-selected={preset.id === current}
                            className={`flex w-full items-start gap-3 rounded-[--radius-md] p-2.5 text-left transition-colors hover:bg-[var(--nav-hover-bg)] ${
                                preset.id === current ? 'bg-[var(--nav-active-bg)]' : ''
                            }`}
                            key={preset.id}
                            onClick={() => choose(preset.id)}
                            role="option"
                            type="button"
                        >
                            <Swatch colors={preset.swatch} />
                            <span className="min-w-0">
                                <span className="block text-sm font-medium text-fg">{preset.name}</span>
                                <span className="block text-xs text-fg-muted">{preset.description}</span>
                            </span>
                        </button>
                    ))}
                </div>,
                document.body,
            )}
        </>
    );
}
