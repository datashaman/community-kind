<!-- reviewed: 2026-09-01 -->

# Configuration reference

| Area            | Variables                                                               | Current requirement                                               |
| --------------- | ----------------------------------------------------------------------- | ----------------------------------------------------------------- |
| Runtime         | `APP_ENV`, `APP_DEBUG`, `APP_URL`                                       | Production uses `APP_DEBUG=false` and HTTPS                       |
| Release/source  | `APP_RELEASE`, `APP_SOURCE_REPOSITORY`                                  | Production release must identify an exact commit or release       |
| Tenancy         | `ORGANISATION_PUBLIC_DOMAIN`, `ORGANISATION_SELF_SERVICE_PROVISIONING`  | Hosted provisioning should remain controlled                      |
| Database        | `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL 18; application role is least-privileged               |
| Queue/cache     | `REDIS_HOST`, `HORIZON_*`                                               | Redis 7 and a supervised Horizon worker                           |
| Classified data | `CLASSIFIED_DATA_KEY_CURRENT`, `CLASSIFIED_DATA_KEYS`                   | Versioned 32-byte keys; retain keys needed by stored ciphertext   |
| Contact indexes | `CONTACT_INDEX_KEY_CURRENT`, `CONTACT_INDEX_KEYS`                       | Separate versioned keys from classified-data encryption           |
| Demo            | `DEMO_SANDBOX_ENABLED`                                                  | Effective only in `local` or `demo`; maximum lifetime is 24 hours |
| Mail/storage    | `MAIL_*`, `FILESYSTEM_DISK`, object-store variables                     | Production providers require TLS, access controls, and monitoring |

Authoritative defaults live in [.env.example](../../.env.example) and `config/`. PHP targets 8.4, Node.js 24, PostgreSQL 18, and Redis 7.
