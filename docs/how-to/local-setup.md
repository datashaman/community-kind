<!-- reviewed: 2026-09-01 -->

# Set up local development without Docker

This is the primary development setup. Docker is optional and not required.

## Requirements

- PHP 8.4+ with `mbstring`, `pdo_pgsql`, and `redis`
- Composer 2, Laravel Valet 4, Node.js 24, and npm 11+
- PostgreSQL 18 and Redis 7

## Configure the application

```bash
cd /path/to/community-kind
cp .env.example .env
composer install
npm ci
php artisan key:generate
```

Create a PostgreSQL database and least-privileged application user. Set `APP_URL=https://community-kind.test`, `ORGANISATION_PUBLIC_DOMAIN=community-kind.test`, the `DB_*` values, `REDIS_HOST=127.0.0.1`, and `MAIL_MAILER=log`.

Generate separate 32-byte classified-data and contact-index keys:

```bash
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Set `CLASSIFIED_DATA_KEY_CURRENT`, `CLASSIFIED_DATA_KEYS`, `CONTACT_INDEX_KEY_CURRENT`, and `CONTACT_INDEX_KEYS` as shown in [.env.example](../../.env.example).

## Prepare services and data

```bash
php artisan migrate
php artisan config:clear
php artisan db:seed
valet link community-kind --secure
```

Valet routes wildcard tenant subdomains to the linked application. Start the frontend and queue worker in separate terminals:

```bash
npm run dev
php artisan horizon
```

Open `https://community-kind.test`. Seeded synthetic users use password `password`; begin with `admin@harbourkind.example.test`, `admin@neighbourlink.example.test`, or `switcher@community-kind.example.test`.

Run `composer ci:check`, `npm test`, and `npm run build:ssr` before proposing a change.
