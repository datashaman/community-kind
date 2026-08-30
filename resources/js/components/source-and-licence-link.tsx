import type { AnchorHTMLAttributes } from 'react';

export default function SourceAndLicenceLink(
    props: Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'href'>,
) {
    return (
        <a href="/source-and-licence" {...props}>
            Source and licence
        </a>
    );
}
