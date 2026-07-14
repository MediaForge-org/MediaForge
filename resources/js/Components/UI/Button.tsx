import type { ButtonHTMLAttributes, ReactNode } from 'react';

import { SpinnerIcon } from '@/Components/UI/Icon';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md';

const VARIANTS: Record<ButtonVariant, string> = {
    primary: 'mf-button-primary',
    secondary: 'mf-button-secondary',
    ghost: 'mf-button-ghost',
    danger: 'mf-button-danger',
};

const SIZES: Record<ButtonSize, string> = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2.5 text-sm',
};

/** Shared class string so an Inertia <Link> can look identical to a <Button>. */
export function buttonClasses(variant: ButtonVariant = 'primary', size: ButtonSize = 'md', extra = ''): string {
    return ['mf-button', VARIANTS[variant], SIZES[size], extra].join(' ');
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
    icon?: ReactNode;
}

export default function Button({
    variant = 'primary',
    size = 'md',
    loading = false,
    icon,
    disabled,
    className = '',
    children,
    type = 'button',
    ...props
}: ButtonProps) {
    return (
        <button className={buttonClasses(variant, size, className)} disabled={disabled || loading} type={type} {...props}>
            {loading ? <SpinnerIcon className="size-4" /> : icon}
            {children}
        </button>
    );
}
