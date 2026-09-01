<!-- reviewed: 2026-09-01 -->

# Use the optional Docker development environment

The repository retains Laravel Sail as an optional environment. It is not the primary local workflow.

## Requirements

- Docker Desktop or a compatible Docker Engine
- Composer 2 and PHP 8.4+ for the initial dependency install

## Start the environment

```bash
cd /path/to/community-kind
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm ci
./vendor/bin/sail npm run dev
```

Open `http://localhost`. Mailpit is at `http://localhost:8025`; MinIO is at `http://localhost:8900`.

Stop services with `./vendor/bin/sail down`. Classified-data keys and demo seeding still follow the [local setup guide](local-setup.md).
