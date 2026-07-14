import { type InputHTMLAttributes, type ReactNode, useId } from 'react';

interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
    label: string;
    labelSuffix?: ReactNode;
    hint?: ReactNode;
    error?: string;
}

/** Labelled input with hint + error, wired for accessibility (label/aria). */
export default function TextField({ label, labelSuffix, hint, error, className = '', id, ...props }: TextFieldProps) {
    const autoId = useId();
    const fieldId = id ?? autoId;
    const describedBy = error ? `${fieldId}-error` : hint ? `${fieldId}-hint` : undefined;

    return (
        <div className="flex flex-col gap-1.5">
            <label className="flex items-center gap-2 text-sm font-medium text-fg" htmlFor={fieldId}>
                <span>{label}</span>
                {labelSuffix}
            </label>
            <input
                aria-describedby={describedBy}
                aria-invalid={error ? true : undefined}
                className={[
                    'w-full rounded-[--radius-md] border bg-[rgb(var(--surface-hover))] px-3.5 py-2.5 text-sm text-fg outline-none transition',
                    'placeholder:text-fg-subtle focus:border-accent focus:ring-2 focus:ring-accent/25 disabled:opacity-60',
                    error ? 'border-error' : 'border-[var(--panel-border)]',
                    className,
                ].join(' ')}
                id={fieldId}
                {...props}
            />
            {error ? (
                <p className="text-sm text-error" id={`${fieldId}-error`}>
                    {error}
                </p>
            ) : (
                hint && (
                    <p className="text-xs text-fg-muted" id={`${fieldId}-hint`}>
                        {hint}
                    </p>
                )
            )}
        </div>
    );
}
