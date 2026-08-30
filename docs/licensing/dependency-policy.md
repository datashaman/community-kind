# Production dependency licence policy

CommunityKind is distributed under `AGPL-3.0-only`. Production Composer and
npm dependencies must declare a licence reviewed as compatible with the
project's approved open-source intent. An unknown or unapproved licence fails
the automated `npm run licenses:check` check.

The allowlist is an engineering safeguard, not legal advice. Adding a licence
to it requires a documented compatibility review. A qualified lawyer must
review the dependency conclusions before the first public release.

Development-only tools are excluded from the production compatibility gate,
but retain their own licences and remain subject to repository distribution
review. `caniuse-lite` contains browser-compatibility data under CC BY 4.0;
its upstream attribution and licence metadata are retained with the package.
