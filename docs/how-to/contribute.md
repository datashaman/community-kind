<!-- reviewed: 2026-09-01 -->

# Contribute a change

1. Follow the [non-Docker local setup](local-setup.md).
2. Read [CONTEXT.md](../../CONTEXT.md), applicable project guidance, and the [contribution policy](../../CONTRIBUTING.md).
3. Never use real service-user, supporter, credential, or incident data.
4. Add proportionate tests and preserve keyboard, screen-reader, authorization, and tenant-isolation behaviour.
5. Run `composer ci:check`, `npm test`, `npm run build:ssr`, `npm run docs:check`, and licence/security checks.
6. Review the diff against its base branch and fix actionable findings.
7. Sign every commit cryptographically and add DCO sign-off with `git commit -S -s`.

Disclose material AI assistance in the pull request. Report vulnerabilities through [SECURITY.md](../../SECURITY.md), not a public issue.
