<!-- reviewed: 2026-09-01 -->

# Architecture, tenancy, organisations, and billing

One CommunityKind Installation is an independent deployment and identity boundary. Within it, each Organisation is a separate nonprofit tenant and data boundary. Organisations do not contain one another or inherit access, even when they share Users or a Billing Account.

## Identity and operational authority

A User authenticates to one Installation. Organisation Membership records each separate tenure, while Role Assignments grant operational permissions at organisation or programme scope. Organisation ownership is a governance responsibility, not automatic access to case, supporter, or billing data.

Parties are organisation-owned people, households, or external organisations. They are not Users or tenant Organisations and are never deduplicated across tenants.

## Service delivery

Programs organise service delivery. Cases belong to exactly one Program; assignment and role scope determine confidential access. Supporter-safe engagement remains distinct from service-client context even when aggregate signals contribute to impact reporting.

## Tenant configuration is immutable and versioned

Five areas of behaviour are configurable per Organisation: Public Forms, Message Templates, Supporter Journeys, Intake Rules, and Reporting. Each is an Organisation Configuration, held as a numbered series of versions under a configuration key.

A version is never edited after it is written. Changing behaviour adds the next version; the model refuses an update to a version's definition. This is deliberate: an intake accepted under version 2 of a rule set, or a message sent from version 3 of a template, must still be explainable years later, and it cannot be if the definition it ran under has since been rewritten in place.

The status of a single version is draft, active, superseded, or retired, and the transitions are enforced on the model rather than by the caller:

| From       | May become          |
| ---------- | ------------------- |
| draft      | active, retired     |
| active     | superseded, retired |
| superseded | —                   |
| retired    | —                   |

At most one version of a configuration key is active. Activating a version supersedes the one it replaced. Retiring withdraws the configuration itself — every draft and active version of that key moves to retired together, so it stops being offered — while leaving what it governed intact. Superseded versions are left alone; they are already history.

Nothing is deleted. A configuration cannot be removed, only retired, which is why the staff interface offers no delete action.

## Billing is a separate authority plane

A Billing Account represents the payer for the official hosted Installation. It may fund several Organisations through separate Subscriptions but owns none of their operational data. Billing Account Membership grants billing authority only. Service Invoices and Service Payments are platform charges and never become an Organisation's donations.

Self-hosted Organisations have no hosted Subscription. Organisation Status, Subscription Status, incident Access Holds, and effective Hosted Access are deliberately separate state machines.

The canonical vocabulary and invariants are in [CONTEXT.md](../../CONTEXT.md); implemented and planned scope is tracked in the [PRD](../../PRD-community-impact-platform.md).
