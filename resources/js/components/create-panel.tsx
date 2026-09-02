import {
    useCallback,
    useEffect,
    useId,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { Button } from '@/components/ui/button';

/*
 * The pattern behind every staff index page: the records come first, and the
 * form that creates one appears in place above them only when it is asked for.
 * A form that sits on the page permanently makes reading the list — which is
 * what the page is for — mean scrolling past it every time.
 *
 * It is a panel rather than a modal. The list stays readable behind it and
 * dismissing it does not lose the page.
 */
export function useCreatePanel() {
    const [open, setOpen] = useState(false);
    const trigger = useRef<HTMLButtonElement>(null);
    const returnFocus = useRef(false);

    /*
     * The trigger is disabled while the panel is open, so it cannot take focus
     * until the panel has gone and this render has landed. Restoring focus
     * inside the dismiss handler silently does nothing.
     */
    useEffect(() => {
        if (open || !returnFocus.current) return;
        returnFocus.current = false;
        trigger.current?.focus();
    }, [open]);

    const dismiss = useCallback(() => {
        returnFocus.current = true;
        setOpen(false);
    }, []);

    return {
        open,
        trigger,
        dismiss,
        show: useCallback(() => setOpen(true), []),
    };
}

export function CreatePanelTrigger({
    panel,
    children,
}: {
    panel: ReturnType<typeof useCreatePanel>;
    children: ReactNode;
}) {
    return (
        <Button
            ref={panel.trigger}
            type="button"
            onClick={panel.show}
            disabled={panel.open}
        >
            {children}
        </Button>
    );
}

export function CreatePanel({
    heading,
    description,
    onDismiss,
    children,
}: {
    heading: string;
    description?: string;
    onDismiss: () => void;
    children: ReactNode;
}) {
    const headingId = useId();
    const panel = useRef<HTMLElement>(null);

    /*
     * The panel is not on the page until it is asked for, so opening it has to
     * take the caret with it. Without this the keyboard user is left at the
     * trigger and has to tab back into a region that appeared behind them.
     */
    useEffect(() => {
        panel.current
            ?.querySelector<HTMLElement>(
                'input:not([type="hidden"]), select, textarea',
            )
            ?.focus();
    }, []);

    /* Escape dismisses, the way it would if this were a dialog. */
    useEffect(() => {
        const close = (event: KeyboardEvent) => {
            if (event.key === 'Escape') onDismiss();
        };
        document.addEventListener('keydown', close);

        return () => document.removeEventListener('keydown', close);
    }, [onDismiss]);

    return (
        <section
            ref={panel}
            aria-labelledby={headingId}
            className="bg-muted/30 space-y-4 rounded-xl border p-5"
        >
            <div className="space-y-1">
                <h2 id={headingId} className="font-medium">
                    {heading}
                </h2>
                {description ? (
                    <p className="text-muted-foreground text-sm">
                        {description}
                    </p>
                ) : null}
            </div>
            {children}
        </section>
    );
}

export function CreatePanelActions({
    onDismiss,
    children,
}: {
    onDismiss: () => void;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {children}
            <Button type="button" variant="ghost" onClick={onDismiss}>
                Cancel
            </Button>
        </div>
    );
}
