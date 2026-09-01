<!-- reviewed: 2026-09-01 -->

# Privacy, safety, and demo confinement

CommunityKind treats privacy as an operating boundary, not a presentation setting. Tenant identity, programme scope, role assignment, case assignment, consent, safe-contact context, and restricted grants combine before confidential information is available.

## Separation by purpose

Service delivery can contain sensitive or highly restricted records. Engagement staff work with supporter-safe profiles, donations, audiences, volunteers, events, and journeys. Impact views use authorised operational signals and minimum-cohort suppression rather than opening frontline records to every viewer.

Important changes are audited. Installation operators receive no routine tenant authority; elevated support access is intended to be narrow, time-limited, approved, and auditable.

## Synthetic demo boundary

Self-service demo sandboxes are enabled only in `local` or `demo` environments. Each pair is isolated, generation-bound, rate-limited, and expires within 24 hours. Bootstrap tokens are hashed, single-use, and consumed only by a CSRF-protected POST after a harmless confirmation GET.

Demo mode blocks uploads, invitations, external messages, payments, and custom domains. Reset expires the current pair, invalidates its session, and provisions a fresh pair. Never enter real information: synthetic mode reduces risk but is not consent to process personal data.

See [SECURITY.md](../../SECURITY.md) for responsible disclosure.
