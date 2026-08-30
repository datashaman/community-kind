# Contributing to CommunityKind

CommunityKind welcomes contributions that preserve client dignity, tenant
isolation, accessibility, and the project's open-source intent.

## Before contributing

- Never use real tenant, client, donor, volunteer, credential, incident, or
  vulnerability-exploit data in issues, pull requests, tests, or fixtures.
- Read the [PRD](PRD-community-impact-platform.md) and applicable architecture
  decisions before changing product behavior.
- Discuss significant product or architecture changes publicly unless a
  time-bounded security embargo or adopter confidentiality requires a
  sanitized decision record.

## Developer Certificate of Origin

Contributions use [Developer Certificate of Origin 1.1](DCO) sign-off rather
than copyright assignment. Sign every commit using your real contribution
identity:

```text
Signed-off-by: Your Name <your-email@example.com>
```

Git can add this line with `git commit --signoff`. The sign-off identity and
contribution history are public and retained. Contributors keep their
copyright and submit contributions under the repository's indicated licence.

## Development checks

Follow the setup in the [README](README.md), then run:

```bash
composer ci:check
npm test
npm run build
npm run licenses:check
composer audit --locked --no-dev
npm audit --omit=dev --audit-level=high
```

Changes must include proportionate automated tests and must preserve keyboard,
screen-reader, authorization, and tenant-isolation behavior.

## AI-assisted contributions

Disclose material AI assistance in the pull request. The contributor remains
responsible for authorship rights, correctness, security, tests, licensing,
and review of every submitted line.

## Security reports

Do not open a public issue for a suspected vulnerability or sensitive incident.
Follow [SECURITY.md](SECURITY.md) instead.
