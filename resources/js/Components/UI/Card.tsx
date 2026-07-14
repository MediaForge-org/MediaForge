import type { CSSProperties, ReactNode } from 'react';

interface CardProps {
    children: ReactNode;
    className?: string;
    padded?: boolean;
    interactive?: boolean;
    style?: CSSProperties;
}

/** The standard preset-skinned surface. `interactive` adds hover lift. */
export default function Card({ children, className = '', padded = true, interactive = false, style }: CardProps) {
    return (
        <div
            className={[interactive ? 'mf-card' : 'mf-panel', padded ? 'p-5 sm:p-6' : '', className].join(' ')}
            style={style}
        >
            {children}
        </div>
    );
}
