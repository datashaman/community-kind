export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    /*
     * The default variant is the page title, so it is the document's h1. The
     * small variant labels a section within a page and sits a level below.
     * Rendering both as h2 left most staff pages with no h1 at all.
     */
    const Title = variant === 'small' ? 'h2' : 'h1';

    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-0.5'}>
            <Title
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'font-display text-xl font-semibold'
                }
            >
                {title}
            </Title>
            {description && (
                <p className="text-muted-foreground text-sm">{description}</p>
            )}
        </header>
    );
}
