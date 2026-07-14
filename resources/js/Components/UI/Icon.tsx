import type { SVGProps } from 'react';

/**
 * A small hand-rolled line-icon set (feather-style) so we get product polish
 * without adding an icon dependency. All icons inherit `currentColor` and a
 * 24×24 viewBox; size them with `className` (e.g. "size-5").
 */
type IconProps = SVGProps<SVGSVGElement>;

function base(props: IconProps) {
    return {
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 1.75,
        strokeLinecap: 'round' as const,
        strokeLinejoin: 'round' as const,
        'aria-hidden': true,
        ...props,
    };
}

export function DashboardIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <rect x="3" y="3" width="7" height="9" rx="1.5" />
            <rect x="14" y="3" width="7" height="5" rx="1.5" />
            <rect x="14" y="12" width="7" height="9" rx="1.5" />
            <rect x="3" y="16" width="7" height="5" rx="1.5" />
        </svg>
    );
}

export function ConnectorsIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 2v6" />
            <path d="M8 8h8v3a4 4 0 0 1-8 0Z" />
            <path d="M12 15v3a3 3 0 0 1-3 3H7" />
        </svg>
    );
}

export function SettingsIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M4 6h10" />
            <path d="M18 6h2" />
            <circle cx="16" cy="6" r="2" />
            <path d="M4 12h4" />
            <path d="M12 12h8" />
            <circle cx="10" cy="12" r="2" />
            <path d="M4 18h10" />
            <path d="M18 18h2" />
            <circle cx="16" cy="18" r="2" />
        </svg>
    );
}

export function LibraryIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M4 5v14" />
            <path d="M8 5v14" />
            <path d="m12 5 4 14" />
            <path d="M4 5h4" />
            <path d="M4 19h4" />
        </svg>
    );
}

export function CheckIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="m5 12 4.5 4.5L19 7" />
        </svg>
    );
}

export function AlertIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 8v5" />
            <path d="M12 16.5h.01" />
            <path d="M10.3 3.7 2.4 17.6A2 2 0 0 0 4.1 20.6h15.8a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z" />
        </svg>
    );
}

export function InfoIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 11v5" />
            <path d="M12 8h.01" />
        </svg>
    );
}

export function CloseIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M6 6 18 18" />
            <path d="M18 6 6 18" />
        </svg>
    );
}

export function ArrowLeftIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M19 12H5" />
            <path d="m12 19-7-7 7-7" />
        </svg>
    );
}

export function ShieldIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M12 3 5 6v5c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-3Z" />
            <path d="m9 12 2 2 4-4" />
        </svg>
    );
}

export function ServerIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <rect x="3" y="4" width="18" height="7" rx="2" />
            <rect x="3" y="13" width="18" height="7" rx="2" />
            <path d="M7 7.5h.01" />
            <path d="M7 16.5h.01" />
        </svg>
    );
}

export function SearchIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
        </svg>
    );
}

export function SunIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2" />
            <path d="M12 20v2" />
            <path d="m4.9 4.9 1.4 1.4" />
            <path d="m17.7 17.7 1.4 1.4" />
            <path d="M2 12h2" />
            <path d="M20 12h2" />
            <path d="m6.3 17.7-1.4 1.4" />
            <path d="m19.1 4.9-1.4 1.4" />
        </svg>
    );
}

export function MoonIcon(props: IconProps) {
    return (
        <svg {...base(props)}>
            <path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z" />
        </svg>
    );
}

export function SpinnerIcon(props: IconProps) {
    return (
        <svg {...base(props)} className={`animate-spin ${props.className ?? ''}`}>
            <path d="M12 3a9 9 0 1 0 9 9" />
        </svg>
    );
}
