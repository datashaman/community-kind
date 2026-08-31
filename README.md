# CommunityKind

CommunityKind is an open-source, multi-tenant community impact platform for local nonprofits that deliver support services while mobilising donors and supporters.

The project is in its specification-build stage. Its centrepiece is a privacy-preserving request-to-outcome workflow, with strict separation between service-client information, supporter activity, and nonprofit tenants.

Read the [product requirements document](PRD-community-impact-platform.md) for the proposed scope, architecture, delivery milestones, safeguards, and sustainability model.

## Project status

**Foundation only.** The Laravel application and engineering baseline are runnable, but the product workflows are not yet implemented. The requirements use fictional organisations and synthetic data and have not been validated with a real nonprofit, practitioner, or jurisdiction-specific legal adviser. Do not use real data.

## Technology baseline

- Laravel 13 and PHP 8.4
- Official React Teams starter kit, Inertia 3, React 19, strict TypeScript, Tailwind CSS 4, and shadcn/ui
- PostgreSQL 18, Redis 7, Laravel Horizon, and Laravel Scout's database engine
- Pest, Larastan/PHPStan, Pint, Vitest, Testing Library, Vite Plus, and GitHub Actions
- Node.js 24 LTS and npm 11+

The project resolves Composer dependencies as PHP 8.4.1 even when Composer is
run with a newer local PHP release. The supplied Laravel Sail runtime uses PHP
8.4.

## Local setup

Install these services and tools locally:

- PHP 8.4 or newer with the `mbstring`, `pdo_pgsql`, and `redis` extensions
- Composer 2
- Laravel Valet 4
- Node.js 24 and npm 11 or newer
- PostgreSQL 18
- Redis 7

Create a PostgreSQL database and user for the application, then copy the
environment file:

```bash
cp .env.example .env
```

Update these values in `.env` for the locally installed services. Replace the
database credentials with the user and password you created:

```dotenv
APP_URL=https://community-kind.test
ORGANISATION_PUBLIC_DOMAIN=community-kind.test
ORGANISATION_SELF_SERVICE_PROVISIONING=true
DB_HOST=127.0.0.1
DB_DATABASE=community_kind
DB_USERNAME=community_kind
DB_PASSWORD=your-local-password
REDIS_HOST=127.0.0.1
MAIL_MAILER=log
```

Self-service Organisation provisioning is closed by default. Enable it for
local or self-hosted use as shown above; keep it disabled for controlled
hosted staging and production environments.

Install the dependencies and prepare the application:

```bash
composer install
npm ci
php artisan key:generate
php artisan migrate
```

The deterministic demo scenarios contain encrypted synthetic contact details.
Generate two independent 32-byte keys, then add them to `.env` under distinct
local key versions:

```bash
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

```dotenv
CLASSIFIED_DATA_KEY_CURRENT=local-data-v1
CLASSIFIED_DATA_KEYS='{"local-data-v1":"paste-the-first-generated-value-here"}'
CONTACT_INDEX_KEY_CURRENT=local-index-v1
CONTACT_INDEX_KEYS='{"local-index-v1":"paste-the-second-generated-value-here"}'
```

Clear cached configuration and seed the fictional HarbourKind and
NeighbourLink organisations:

```bash
php artisan config:clear
php artisan db:seed
```

Scenario catalog version `2026.3` uses the fixed reporting instant
`2026-06-30 23:59:59` in each Organisation's local timezone: HarbourKind uses
`Africa/Johannesburg` and ZAR, while NeighbourLink uses `Europe/London` and
GBP. It can be seeded repeatedly without duplicating its records. All demo
users use the password `password`; useful starting accounts are:

- `admin@harbourkind.example.test` for HarbourKind administration
- `admin@neighbourlink.example.test` for NeighbourLink administration
- `switcher@community-kind.example.test` for an account belonging to both
  organisations

Link and secure the project with Valet. Valet routes the linked site's wildcard
subdomains to the same Laravel application, so tenant hosts do not need separate
local registrations:

```bash
valet link community-kind --secure
```

Start the frontend development server and Horizon in separate terminals:

```bash
npm run dev
php artisan horizon
```

Open `https://community-kind.test`. Tenant public pages use hosts such as
`https://harbourkind.community-kind.test`. To inspect email locally, replace
the log mailer with an SMTP catcher such as Mailpit and update the `MAIL_*`
values in `.env`.

## Docker setup

The repository retains an optional Laravel Sail environment. It requires
Docker Desktop (or a compatible Docker Engine), Composer 2, and PHP 8.4 or
newer for the initial dependency install.

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm ci
./vendor/bin/sail npm run dev
```

Open `http://localhost`. Mailpit is available at `http://localhost:8025`, and
the MinIO console is available at `http://localhost:8900`.

To stop the environment:

```bash
./vendor/bin/sail down
```

## Verification

Run the same checks exercised by CI:

```bash
composer validate --strict
composer ci:check
npm test
npm run build
npm run licenses:check
composer audit --locked --no-dev
npm audit --omit=dev --audit-level=high
```

Pest uses an isolated in-memory SQLite database for fast tests. CI also runs
the complete migration set against an empty PostgreSQL 18 database.

## Corresponding source

Every application surface links to `/source-and-licence`. Deployments set
`APP_RELEASE` to the exact Git commit or release and
`APP_SOURCE_REPOSITORY` to the durable corresponding-source repository.

## Licensing

First-party software and build/deployment source are licensed under the [GNU Affero General Public License v3.0 only](LICENSE). Documentation and original non-brand visual assets are released under CC BY-SA 4.0, and deliberately synthetic datasets under CC0 1.0, as described in the PRD and [NOTICE](NOTICE).

The project name and marks are not granted under those licences. A separate trademark policy will be prepared before a branded production release.

Contributions require [DCO 1.1](DCO) sign-off. See
[CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md), and
[GOVERNANCE.md](GOVERNANCE.md).
