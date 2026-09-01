<!-- reviewed: 2026-09-01 -->

# Deploy, back up, and upgrade an installation

CommunityKind does not yet ship a production deployment recipe. Treat this as the minimum operator checklist and validate it for the chosen platform and jurisdiction.

## Deploy

1. Provide PHP 8.4, Node.js 24 build tooling, PostgreSQL 18, Redis 7, TLS, durable object storage, and a supervised Horizon process.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `DEMO_SANDBOX_ENABLED=false`, `APP_RELEASE` to the deployed commit, and `APP_SOURCE_REPOSITORY` to durable corresponding source.
3. Use distinct migration and least-privileged application database roles.
4. Run `composer install --no-dev --optimize-autoloader`, build assets, run migrations, cache configuration/routes/views, and restart Horizon with `php artisan horizon:terminate`.
5. Run health, authentication, tenant-isolation, queue, mail, storage, and restore smoke tests.

## Back up and restore

Back up PostgreSQL, object storage, encryption-key material, and deployment configuration on separate retention schedules. Encrypt backups and restrict restore authority. A database backup without the matching classified-data and contact-index keys is incomplete.

Test restores into an isolated environment. Verify row counts, encrypted-field readability, tenant isolation, stored objects, and a representative queued job before declaring the restore usable.

## Upgrade

1. Read the release diff, migrations, dependency advisories, and [decision records](../reference/decisions.md).
2. Take and verify a restorable backup.
3. Run `composer ci:check`, `npm test`, and `npm run build:ssr` against production-like services.
4. Deploy code and assets, run migrations once, clear/rebuild caches, and terminate Horizon gracefully.
5. Verify `APP_RELEASE`, source/licence links, scheduled tasks, queues, logs, and tenant/public hosts.

Rollback must account for irreversible migrations. Prefer forward-compatible expand/migrate/contract changes.
