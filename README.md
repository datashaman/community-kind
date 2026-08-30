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
APP_URL=http://localhost:8000
DB_HOST=127.0.0.1
DB_DATABASE=community_kind
DB_USERNAME=community_kind
DB_PASSWORD=your-local-password
REDIS_HOST=127.0.0.1
MAIL_MAILER=log
```

Install the dependencies and prepare the application:

```bash
composer install
npm ci
php artisan key:generate
php artisan migrate
```

Start the Laravel application, queue worker, and frontend development server:

```bash
composer run dev
```

Open `http://localhost:8000`. To inspect email locally, replace the log mailer
with an SMTP catcher such as Mailpit and update the `MAIL_*` values in `.env`.

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
