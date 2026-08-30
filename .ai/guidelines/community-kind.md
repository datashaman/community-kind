# CommunityKind Platform

- Target PHP 8.4, as enforced by Composer's platform configuration and CI. Local tooling may run on a newer PHP version, but application code must not use PHP 8.5-only features.
- Target Node.js 24, PostgreSQL 18, and Redis 7.
- Keep Laravel Boost and its generated guidance development-only; Boost must not be enabled in production.
