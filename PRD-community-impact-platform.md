# Product Requirements Document: Community Impact Platform

**Status:** Spec-build brief
**Working title:** CommunityKind
**Product type:** Multi-tenant bespoke responsive web application
**Primary users:** Community-service staff, engagement/fundraising staff, managers, volunteers, donors, and local business partners
**Build context:** Independent concept product using a fictional organisation and synthetic data

## 1. Executive summary

CommunityKind is a secure, integrated platform for a locally focused nonprofit that delivers support services while mobilising donors, volunteers, goods donors, and business partners.

The product combines:

1. client intake and case management;
2. a unified supporter database;
3. donations, volunteering, events, and in-kind contribution workflows;
4. segmented, automated communications; and
5. operational and impact reporting.

Its purpose is to replace fragmented records and manual follow-up with a shared system that connects every act of local support to a measurable community outcome—without exposing sensitive client information to fundraising or engagement users.

## 2. Source and interpretation

This PRD is inspired by the LocalKind case study published by CiviCRM and LocalKind's public website. The case study describes a staged transformation involving case management, audience segmentation, supporter journeys, automation, internal capability-building, and impact analytics. It reports that donation revenue more than doubled and the supporter database grew significantly.

This document translates that operating model into requirements for a new bespoke product. Requirements below are proposed product decisions, not claims that LocalKind uses each feature exactly as specified.

No participating nonprofit or subject-matter expert is assumed. The initial product is therefore a convincing reference implementation, not production-ready case-management software. Any deployment involving real clients or sensitive records would require validation with practitioners, safeguarding/privacy specialists, and the relevant jurisdiction's legal requirements.

The product is jurisdiction-neutral. Australian OAIC material may be cited as conservative privacy-design guidance because the source case study is Australian, but the product is not assumed to operate in Australia and must not claim compliance with the Australian Privacy Act or Australian Privacy Principles. Applicable law, regulation, safeguarding rules, retention, fundraising obligations, and data residency must be determined with a real adopter during M5.

### Market position and audience

Integrated nonprofit platforms already combine fundraising, program/case management, outcomes, and volunteer capabilities. CommunityKind is therefore not positioned as a new software category. Its spec-build differentiation is an opinionated, approachable workflow for smaller community organisations, explicit separation between service-client and supporter data, and a lower-complexity product experience.

**Primary customer hypothesis:** locally or regionally focused community-service nonprofits with approximately 10–75 staff, two to six service programs, active donation/community-engagement work, and no dedicated enterprise CRM engineering team.

**Primary buyer:** executive director, chief operating officer, or head of services/operations.
**Operational champions:** program manager and engagement/fundraising lead.
**Daily MVP users:** case workers, program managers, engagement officers, Organisation administrators, and executive viewers.
**Secondary audience:** nonprofit implementation partners and technical evaluators assessing an adaptable Laravel product.

CommunityKind is not initially designed for clinical records, emergency dispatch, large international NGO operations, government benefits adjudication, grantmaking-first organisations, or fundraising-only institutions with enterprise advancement requirements.

### Open-source and sustainability model

CommunityKind is an open-source product, not merely source-available and not restricted by organisation size, revenue, sector, production use, or hosted-service competition. The application, migrations, tests, first-party command-line/operations tooling, and the preferred form of first-party build/deployment source are released under **GNU Affero General Public License v3.0 only (`AGPL-3.0-only`)**. Documentation and original non-brand visual assets use **CC BY-SA 4.0**; deliberately synthetic demonstration data/exports use **CC0 1.0**, while the generators and seed implementation remain AGPL software. Project names and marks are excluded from those content grants and governed separately below. Third-party dependencies and assets retain their own compatible licences. A qualified lawyer must review the initial notices and dependency conclusions before the first public release, but legal review may not silently replace the approved open-source intent with a source-available restriction.

AGPL is selected because this is primarily network software. Anyone may use, study, modify, redistribute, or commercially host the system under its terms. A party operating a modified network version must provide the corresponding source required by AGPL to its users. Every running interface therefore provides an accessible **Source and licence** link containing the exact deployed commit/release, copyright and warranty notices, licence text, and a durable route to the corresponding source. Build and deployment scripts necessary to install and run the covered version are not hidden as a substitute for releasing usable source; secrets, tenant data, incident evidence, private infrastructure credentials, and adopter-specific content are not source code and remain private.

There is no “large-company fee” in the software licence: an open-source licence cannot discriminate by person, group, field, revenue, or organisation size. A large organisation may self-host without paying if it complies with AGPL. CommunityKind's commercial opportunity is instead the value of an accountable official service:

- managed multi-tenant hosting with upgrades, backups, monitoring, incident response, security maintenance, and defined service levels;
- paid implementation, migration, configuration, integration, training, accessibility, privacy/security review, and data-quality work;
- support subscriptions, response-time commitments, procurement/security documentation, and enterprise account management;
- sponsored roadmap work, grants and voluntary project sponsorship; and
- custom development whose reusable first-party product improvements are released upstream under the same open-source licence.

The initial spec/demo service may be self-funded and carries no real-data hosting or availability promise. Pricing begins only with an M5 production offering and may vary by hosted usage, support coverage, implementation effort, storage, integrations, and contractual risk—not by charging for rights already granted by AGPL. The hosted service may have free or subsidised tiers when affordable, but these are commercial-policy choices rather than separate software editions. No proprietary “enterprise edition,” delayed-open licence, commercial-use prohibition, dual-licence exception, or closed generic feature set is planned. This means revenue is possible but not guaranteed: a capable organisation or competing provider may lawfully self-host or offer services under the licence.

The official hosted service separates tenant authority from payment authority. A provider-independent **Billing Account** represents an individual or organisation paying for one or more independently isolated Organisations. Billing Account Owners, Administrators, Viewers, and non-authoritative Billing Contacts receive no Organisation access merely by paying; Organisation Owners receive no billing authority unless separately appointed. A Billing Account never reuses a tenant-owned Party record.

Each officially hosted Organisation is provisioned with Subscription history under a versioned **Service Offering** and **Access Policy**, including paid, trial, free, subsidised, or operator-sponsored arrangements. One Organisation has at most one current Subscription. Moving funding requires Organisation Owner approval and acceptance by the new Billing Account, ends the old Subscription at the agreed boundary, and creates a successor without rewriting invoices or payment history. Stopping funding never archives, transfers, or deletes the Organisation.

Subscription Status is `pending_activation`, `trialing`, `active`, `past_due`, or `ended`. Scheduled cancellation sets an end date while entitlement continues. Hosted Access is derived separately as full, read-only, recovery-only, or denied; grace and recovery periods come from the accepted Access Policy and never silently delete tenant data. Self-hosted Installations have no CommunityKind Billing Accounts, Subscriptions, Service Invoices, or Hosted Access decisions.

CommunityKind keeps canonical Service Invoices and Service Payments, reconciled idempotently with replaceable external-provider references. These records are categorically separate from an Organisation's donations, donation payments, refunds, and receipts. Payment methods remain externally hosted; CommunityKind stores only provider references and safe display metadata, while manual, grant-funded, and zero-cost arrangements may have no payment method.

Contributions use Developer Certificate of Origin 1.1 sign-off rather than copyright assignment or a contributor licence agreement. Contributors retain copyright and certify that they can submit their work under the repository's indicated licence; contribution guidance explains that their sign-off identity and contribution history are public and retained. This deliberately prevents the maintainer from later relicensing community contributions into a proprietary edition without each relevant copyright holder's permission. `CONTRIBUTING.md` documents DCO sign-off, coding/testing/documentation requirements, accessibility and tenant-isolation expectations, review, authorship/attribution, AI-assisted contribution disclosure, and the private security-reporting route. Public issues and pull requests must never contain real tenant, client, donor, credential, incident, or vulnerability-exploit data.

Governance begins with a named lead maintainer accountable for releases, security response, roadmap clarity, and fair review. The public repository includes `LICENSE`, `NOTICE`, `README`, `CONTRIBUTING.md`, `GOVERNANCE.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, the DCO text, architecture decisions, a changelog, support/version policy, dependency attributions, and reproducible setup/test instructions. Maintainer rights are earned through sustained reviewed contribution and may be revoked through the documented governance process. Significant product/architecture proposals are discussed publicly except during a time-bounded security embargo or when adopter confidentiality requires a sanitized decision record.

The software licence does not grant rights to imply that a fork or third-party service is the official CommunityKind service. Before public release, the project name and logo require clearance and a separate, narrow trademark policy that prevents confusion while permitting truthful nominative references, unbranded forks, and community discussion. The policy cannot be implemented as code that forces official branding or a phone-home dependency.

Positioning statement:

> For local community nonprofits that both deliver support and mobilise supporters, CommunityKind is a unified operations platform that connects service delivery, engagement, and impact reporting while keeping sensitive client information deliberately separate from fundraising workflows.

This audience definition is a product hypothesis for the spec build, not validated market demand.

## 3. Spec-build premise and assumptions

The demo organisation is **HarbourKind**, a fictional local nonprofit serving one metropolitan area through three programs:

1. community drop-in and material relief;
2. housing and homelessness support; and
3. newcomer settlement support.

HarbourKind also accepts donations, recruits volunteers, runs local events, receives offers of goods, and works with local businesses. All people, organisations, addresses, case histories, transactions, and outcomes shown in the product must be clearly synthetic.

The spec build will:

- demonstrate one coherent vertical slice from community support to measurable impact;
- use opinionated workflows instead of attempting to model every nonprofit variation;
- simulate external services such as payments and bulk messaging unless a sandbox integration adds clear demo value;
- include realistic role-based access and auditing even though the data is fictional;
- label impact figures as demo data and avoid implying that they are LocalKind's results; and
- document which capabilities require real-world validation before production use.

HarbourKind is the primary demonstration tenant. A second, smaller fictional organisation named **NeighbourLink** exists solely to exercise Organisation switching and prove tenant isolation.

## 4. Problem statement

Community organisations often hold client, donor, volunteer, event, and communications data in separate spreadsheets and tools. This creates five problems:

- frontline teams cannot consistently record services and outcomes;
- engagement teams lack a reliable view of supporters and their interests;
- manual onboarding and follow-up limit growth;
- leadership cannot connect resources, activities, and outcomes; and
- sensitive client information may be copied into unsuitable systems.

The organisation needs one operational platform with strict data boundaries, reliable workflows, and a measurable relationship between community contributions and local impact.

## 5. Product vision

Enable a local nonprofit to deliver coordinated support, grow a committed community of supporters, and demonstrate impact from one trustworthy system.

### Product principles

- **Local and personal:** Tailor opportunities and messages to geography, interests, and prior engagement.
- **Client dignity first:** Collect only necessary information and restrict it by role and program.
- **One constituent, multiple roles:** A person may be a donor, volunteer, partner contact, event attendee, or—with explicit safeguards—a service client.
- **Outcomes over activity:** Record what changed, not only what staff did.
- **Automation with human control:** Staff can preview, pause, override, and audit automated actions.
- **Configurable without developers:** Administrators can manage programs, forms, segments, templates, and outcome measures.

### Tenancy model

The application adapts Laravel Starter Kit's Teams feature as its initial tenancy primitive, but the product's canonical domain term and first-party code identifier is **Organisation**:

- one **Organisation** represents one nonprofit organisation and is the tenant boundary;
- a User may belong to multiple Organisations through separate Memberships;
- each Membership links to exactly one tenant-local person Party and may have multiple independently scoped Role Assignments;
- Organisations do not contain one another or inherit access, even when they share members or a Billing Account;
- every organisation-owned record belongs to exactly one Organisation, directly or through an explicitly enforced parent relationship;
- the active Organisation determines branding, configuration, permissions, dashboards, integrations, and visible data;
- switching Organisations must clear or recompute cached navigation, permissions, dashboard data, and search context;
- constituent identities are tenant-local—a person appearing in two organisations is represented by two unrelated records, with no cross-tenant deduplication;
- Installation Operator authority is separate from ordinary Organisation Membership, grants no routine tenant-data access, and is fully audited; and
- deleting or leaving an Organisation must never orphan or expose its records to another Organisation.

The Laravel Starter Kit Teams feature supplies membership and current-Organisation context, but does not by itself guarantee data isolation. Every query, route binding, policy, queued job, notification, export, search index entry, cache key, file path, audit event, and analytics query must carry and enforce the Organisation boundary.

### Public tenant resolution

Each Organisation receives a unique, immutable-or-deliberately-renamed slug and a canonical public subdomain:

`https://{organisation-slug}.communitykind.example`

HarbourKind therefore appears at `https://harbourkind.communitykind.example`. An Organisation may later attach one verified custom domain, such as `https://give.harbourkind.example`.

Resolution rules:

1. An exact, active, verified custom-domain match resolves the Organisation.
2. Otherwise, a valid non-reserved platform subdomain resolves the Organisation by slug.
3. Unknown, unverified, access-held, or malformed hosts return a neutral not-found page and never fall back to another or a “current” Organisation.
4. A custom-domain request is served directly; the canonical subdomain remains available for recovery and administration. Public canonical-link and redirect policy must prevent duplicate indexing without breaking signed links.
5. Public forms, unsubscribe links, receipts, and supporter journeys resolve the Organisation from the validated host plus signed identifiers where applicable—not from an authenticated user's current Organisation.
6. Incoming provider webhooks resolve the Organisation through a stored provider-account/integration identifier and signature validation, never from an untrusted `Host` header alone.
7. Staff administration remains on the platform application domain and uses the authenticated user's active Organisation context.

Organisation slugs and custom domains are globally unique. Reserved subdomains include platform, administration, authentication, API, asset, and status endpoints. Domain changes are audited, and stale domains must not become claimable until an appropriate quarantine period has elapsed.

### Organisation onboarding and lifecycle

Organisation creation is controlled provisioning, not a general permission granted by authentication or ordinary Membership. On the official hosted Installation, an authorised Billing Account administrator or Installation Operator provisions an Organisation together with its first Subscription and nominates at least one initial Owner. Self-hosted Installations choose their own provisioning policy without CommunityKind billing. Personal Organisations are disabled: every Organisation represents a nonprofit tenant.

The initial Owner must explicitly accept responsibility. Provisioning records the Organisation name, reserves a globally unique slug, creates a pending Subscription under an accepted Service Offering on the official hosted Installation, and enters the Organisation into `pending` status. The paid term or trial clock begins only when ownership is accepted and minimum activation requirements are complete, unless explicit contracted terms specify another start date.

```text
pending → active → archived → scheduled_for_deletion → deleted
            ↑         ↑                 │
            └─────────┴─────────────────┘  permitted restoration paths
```

| Status | Staff behavior | Public behavior |
|---|---|---|
| `pending` | Owner and Organisation administrator may complete setup | Neutral not-found page; public forms disabled |
| `active` | Normal role- and program-scoped access | Tenant pages and enabled public forms available |
| `archived` | Owner and Organisation administrator may view tenant metadata and request an export; operational records are read-only | Neutral not-found response |
| `scheduled_for_deletion` | Same as archived during the recovery window | Neutral not-found response |
| `deleted` | No application access; retained backups expire under the documented backup schedule | No resolution |

Lifecycle rules:

- activation requires a valid Organisation name and slug plus completion of required setup fields;
- permitted transitions are `pending → active`, `active → archived`, `archived → active|scheduled_for_deletion`, and `scheduled_for_deletion → archived|deleted`; an owner may also schedule an abandoned `pending` Organisation for deletion;
- the last owner cannot leave or be removed without transferring ownership;
- ownership transfer requires recent authentication and explicit acceptance by the new owner;
- ownership conveys tenant administration and recovery authority, not operational data access;
- archival, deletion scheduling, restoration, ownership transfer, and slug changes are audited;
- deletion scheduling requires owner confirmation, MFA/recent authentication, and a 30-day recovery window;
- restoration during the recovery window returns the Organisation to `archived`; reactivation is a separate deliberate action;
- each state transition invalidates or recomputes relevant sessions, authorization state, caches, queued work, signed links, public forms, and search documents;
- queued jobs must verify Organisation status at execution time and safely stop if the intended action is no longer allowed;
- a slug change creates a time-limited redirect from the old platform subdomain, after which the old slug enters quarantine before it can be claimed.

An **Access Hold** is a separate, reason-coded overlay for security, safeguarding, legal, abuse, or operational incidents. It records issuer, scope, timestamps, review time, and audit history, and may produce read-only, recovery-only, or denied access without changing Organisation Status. Subscription state is also independent of Organisation Status.

### Application surfaces

| Surface | Canonical host | Authentication and tenant context | MVP |
|---|---|---|---|
| Platform/marketing | `communitykind.example` | Public; no tenant data or implicit tenant fallback | M0 |
| Staff application | `app.communitykind.example` | Authenticated User plus explicitly selected active Organisation | M0 |
| Disposable demo access | `demo.communitykind.example` | Opaque, time-limited access token for an isolated synthetic sandbox pair | M3 |
| Tenant public experience | `{organisation-slug}.communitykind.example` | Public Organisation resolved from validated host | M2 |
| Tenant custom domain | Verified custom hostname | Public Organisation resolved from exact verified hostname | M5 |
| Supporter self-service | Tenant public host under `/account` | Authenticated User plus verified Portal Access Grant for the resolved Organisation | M4 |
| Central API/webhooks | `api.communitykind.example` | Signed token or verified provider integration resolves Organisation; never current-Organisation session state | M5 |
| Platform operations | No tenant-data UI in the spec MVP | Infrastructure and aggregate service metadata only | Deferred |

Staff routes never run on tenant custom domains. Public tenant routes never use a staff user's current Organisation as fallback. Authentication may use the central application host and return to a validated tenant URL, but return targets must be allowlisted and signed to prevent open redirects. Cookies, CSRF protection, CORS, caches, and content-security policy must be configured per surface rather than assuming all subdomains share one trust boundary.

### Spec-demo access

The main evaluation experience is a mutable, isolated, disposable sandbox—not a shared public Organisation and not a production trial.

- An operator creates a sandbox through a guarded command, which clones the pinned HarbourKind and NeighbourLink scenarios into a uniquely sluggified Organisation pair.
- The command creates synthetic demo Users/personas only and returns an opaque access link. It collects no evaluator name, email address, telephone number, or organisation data.
- The bootstrap access token is single-use, stored only as a hash, scoped to one sandbox pair and demo generation, expires no later than 24 hours after provisioning, and cannot authenticate against non-demo Organisations.
- Opening the link exchanges the token for a secure demo-only session, removes the token from the browser URL, applies a no-referrer policy, and presents an explicit role selector for the seeded Organisation administrator, program manager, case worker, engagement officer, and executive viewer personas. An operator may issue a replacement token for the same unexpired sandbox.
- Role selection assumes the selected synthetic User inside that sandbox only, is visibly indicated, and writes an audit event. This demo impersonation mechanism is unavailable outside `local` and `demo` environments.
- The evaluator can mutate synthetic records, run all four showcases, switch between the paired Organisations where the selected persona permits it, and reset the sandbox.
- Uploads, external messaging, real payments, custom domains, arbitrary invitations, and changes to the sandbox's synthetic-only status are disabled.
- Expiry revokes the token and sessions, invalidates queued work, and schedules the sandbox pair and their synthetic Users for dependency-safe deletion.

Public product pages may contain a short walkthrough, screenshots, and architecture notes, but expose no mutable tenant data. Local evaluators can instead seed the same scenarios and use documented synthetic credentials. Anonymous self-provisioning and a general free trial are explicitly out of scope.

The core guided demonstration targets 15 minutes: four minutes for request-to-outcome, three minutes for donor-to-welcome, three minutes for impact reporting, three minutes for privacy/tenant separation, and two minutes for orientation and questions.

## 6. Spec-build goals and success measures

The spec build cannot prove organisational outcomes such as increased donation revenue. Its success is measured by product completeness, usability, credibility, and the ability to communicate the concept.

| Goal | Metric | Acceptance target |
|---|---|---:|
| Demonstrate the concept | Complete scripted end-to-end demo journeys | 4 of 4 |
| Make the product understandable | First-time evaluator completes the core staff scenario without guidance | ≥80% in lightweight usability tests |
| Show credible access control | Automated tests covering the critical client/supporter boundary | 100% of defined permission cases |
| Prove tenant isolation | Cross-Organisation feature tests for reads, writes, search, files, exports, jobs, and reports | 100% of defined isolation cases |
| Provide believable analytics | Dashboard totals reconcile with seeded underlying records | 100% |
| Deliver a polished experience | Critical accessibility issues in tested core flows | 0 |
| Make the project reusable | Fresh local setup from documented instructions | ≤15 minutes |
| Protect trust | Real personal, client, donor, or payment data in demo environments | 0 |
| Make evaluation safe | Expired or cross-sandbox access-token tests that expose another sandbox or non-demo Organisation | 0 |
| Deliver within the approved effort | M0–M3 engineering effort, including hardening contingency | ≤900 hours |

Real-world outcome metrics—service completion, supporter conversion, retention, recurring giving, administration time, and data completeness—remain hypotheses to validate with a future partner organisation.

### Fixed delivery budget and non-negotiable centrepiece

M0–M3 has a hard cap of **900 focused engineering hours**. This is an effort budget rather than an assumed calendar commitment: it is equivalent to 22.5 weeks at 40 hours per week, 45 weeks at 20 hours per week, or 90 weeks at 10 hours per week. Actual elapsed time will also depend on availability and external lead times.

The cap includes implementation, automated tests, documentation, CI/CD, deterministic seed data, infrastructure setup, accessibility and security QA, demo preparation, release evidence, review, and rework. It excludes provider waiting time, third-party fees, ongoing operations, formal legal/privacy review, practitioner or adopter research, and all M4/M5 implementation. Production launch and adopter validation require a separate approved budget.

The non-negotiable centrepiece is showcase scenario 1, **From request to outcome**: a fictional referral and intake proceeds through consent and duplicate handling, triage, assignment, case delivery, goal, service/referral, outcome, and closure. It must exercise safe-contact handling, confidential/highly restricted authorization, auditing, and the document lifecycle where applicable. Scenario 4, **Privacy and tenant boundary**, is a mandatory acceptance overlay on that journey rather than optional showcase breadth: a Party's client status remains hidden from fundraising/engagement access, and HarbourKind data remains inaccessible from NeighbourLink.

The centrepiece must be demonstrable by the end of M1, at approximately 500 cumulative hours. The donor journey and impact dashboard remain required for a complete M0–M3 spec release, but their secondary variants and presentation breadth are the first areas reduced if the cap is threatened. An incomplete build at the cap is labelled a **technical preview**, not a spec MVP or launch-ready release.

## 7. Personas and authorization

| Persona | Primary need | Access level |
|---|---|---|
| Intake worker | Capture a request quickly and safely | Assigned programs and clients |
| Case worker | Coordinate plans, interactions, referrals, and outcomes | Assigned caseload/program |
| Program manager | Monitor demand, workloads, quality, and outcomes | Program-level aggregate and records |
| Engagement officer | Segment and communicate with supporters | Supporter data only |
| Fundraiser | Manage gifts, campaigns, acknowledgements, and relationships | Financial/supporter data only |
| Volunteer coordinator | Recruit, screen, schedule, and retain volunteers | Volunteer data only |
| Finance user | Reconcile payments and issue receipts | Financial records; no case notes |
| Executive/reporting user | View organisation-wide trends and impact | Aggregated data by default |
| Organisation administrator | Configure the organisation, memberships, workflows, permissions, and integrations | Current Organisation only |
| Installation operator | Maintain the service without routine access to tenant records | Platform metadata; audited break-glass access only if later implemented |
| Supporter | Donate, volunteer, register, offer goods, and manage preferences | Own profile and activity |

### MVP workforce roles

The spec MVP uses five operational roles. Organisation ownership is a separate administrative relationship and is not an operational role.

| Role | Purpose |
|---|---|
| Organisation administrator | Memberships, tenant settings, programs, and configuration; no case access by default |
| Program manager | Program-wide service operations, assignments, outcomes, and service dashboards |
| Case worker | Intake plus assigned-case delivery |
| Engagement officer | Supporter-safe contacts, donations, segments, simulated communications, and fundraising dashboards |
| Executive viewer | De-identified aggregate service and fundraising dashboards only |

Volunteer coordinator, finance user, intake-only worker, and finer-grained custom roles are deferred until their corresponding workflows are introduced. An Organisation Membership may hold multiple Operational Roles. Each Role Assignment carries its own Organisation-wide or Program-specific scope so, for example, one person may manage one Program while performing case work in another.

### Permission model

Authorization is deny-by-default and evaluated in this order:

1. Resolve an active Organisation from the trusted staff context or validated public host.
2. For staff routes, require an authenticated User with active membership in that Organisation.
3. Authorize the requested action through a Laravel policy; hidden navigation is never treated as access control.
4. Apply the union of the User's scoped Role Assignments and explicit permissions.
5. For service records, require a Role Assignment scoped to the record's Program.
6. For case workers, require active case assignment unless a specifically permitted intake action applies.
7. Apply field-level projection so supporter-facing roles cannot infer service participation, risk flags, safe-contact instructions, or case data.

Requests for records belonging to another Organisation return not found to avoid confirming their existence. Same-Organisation requests that fail role, program, assignment, or sensitivity checks return forbidden. Every export is a distinct permission and audited action.

Organisation ownership permits ownership transfer, recovery of tenant administration, and appointment of Organisation administrators. It does not bypass operational policies or confer billing authority. An Owner who needs operational access must also hold the relevant scoped Role Assignment. Only an Owner may nominate another Owner, the nominee must explicitly accept, and the last Owner cannot leave or be removed.

### MVP authorization matrix

Legend: **Manage** = create/read/update/delete as appropriate; **Program** = records in assigned programs; **Assigned** = assigned cases only; **Safe view** = supporter projection that excludes any service/client signal; **Aggregate** = privacy-safe totals only; **—** = denied.

| Resource or action | Organisation administrator | Program manager | Case worker | Engagement officer | Executive viewer |
|---|---|---|---|---|---|
| Organisation settings and branding | Manage | View | — | — | — |
| Membership invitations and roles | Manage | — | — | — | — |
| Programs and outcome definitions | Manage configuration only | Program | View | — | — |
| General People and Organisations | Administrative metadata only | Program | Assigned | Safe view | — |
| Intake and referrals | — | Program | Create; view assigned | — | — |
| Cases, goals, interactions, and services | — | Program | Assigned | — | — |
| Risk flags and safe-contact instructions | — | Program with explicit sensitive-data permission | Assigned with explicit sensitive-data permission | — | — |
| Case documents | — | Program | Assigned | — | — |
| Donations and supporter activity | — | — | — | Manage | Aggregate |
| Segments and simulated communications | — | — | — | Manage | Aggregate |
| Service dashboards | — | Program | Own caseload | — | Aggregate |
| Fundraising dashboards | — | — | — | Manage | Aggregate |
| Identifiable exports | — | Explicit permission | — | Explicit permission for supporter data only | — |
| Aggregate exports | — | Program | — | Manage | Aggregate |
| Audit history | Tenant configuration events only | Program service events | Own actions | Own domain actions | — |

“Administrative metadata” means record counts, configuration state, and data-quality status without names, contact details, client status, notes, donations, or other operational content. Organisation administrators may manage retention configuration in later milestones but do not gain routine access to protected records.

## 8. Core user journeys

### 8.1 Client support journey

1. A staff member or approved referral partner records a request for help.
2. The system checks for likely duplicates without exposing unrelated sensitive records.
3. Intake captures consent, presenting needs, urgency, risk flags, program eligibility, and safe contact preferences.
4. The request is triaged and assigned to a program and worker.
5. The worker creates goals and a support plan, records interactions, referrals, services, documents, and outcome measures.
6. Supervisors monitor overdue actions, risk, demand, and caseload.
7. The case is closed with an outcome and optional follow-up date.
8. De-identified activity and outcome data feeds impact reporting.

### 8.2 New supporter journey

1. A visitor chooses to donate money, volunteer time, offer goods, attend an event, or represent a local business.
2. A short, channel-specific form captures the minimum information and communication consent.
3. The platform creates or updates one supporter profile and records source/campaign attribution.
4. The supporter receives an immediate confirmation, receipt where relevant, and a tailored welcome journey.
5. Engagement score, interests, location, and actions place the supporter into dynamic segments.
6. Staff see follow-up tasks and journey performance; supporters can update preferences or opt out.

### 8.3 Donation-to-impact journey

1. A donor makes a one-off or recurring donation against a campaign, appeal, or general fund.
2. Payment status, fees, designation, consent, and attribution are recorded.
3. The system sends an acknowledgement and compliant receipt.
4. Aggregate funds/resources may be associated with programs and reporting periods; the product must not claim direct causality between a specific gift and a client outcome unless the organisation can substantiate it.
5. The donor later receives an impact update based on consent, interests, and campaign context.

## 9. Functional requirements

Every requirement has two labels:

- **Milestone (M0–M5):** when the capability is built;
- **Priority (Must/Should/Could):** whether it is required within that milestone.

The spec MVP comprises **M0 through M3**. A requirement labelled **M4 · Must** is mandatory for M4, but is not part of the spec MVP.

### 9.1 Identity, profiles, and consent

- **M0 · Must:** Keep Installation-wide authentication Users separate from tenant-owned Parties and Organisation Memberships.
- **M0 · Must:** Scope every profile and related record to the active Organisation; matching and deduplication never cross Organisation boundaries.
- **M1 · Must:** Maintain a canonical person/organisation profile with multiple roles and a complete engagement timeline.
- **M1 · Should:** Detect and merge likely duplicates using controlled, reversible review.
- **M1 · Must:** Record service, referral, and safe-contact consent, including source, wording/version, timestamp, and withdrawal.
- **M2 · Must:** Record supporter communication consent by purpose and channel, including source, wording/version, timestamp, and withdrawal.
- **M1 · Must:** Store safe-contact instructions separately from general communication preferences.
- **M1 · Must:** Support organisations, households, relationships, addresses, service areas, and supporter interests.
- **M4 · Should:** Provide supporter self-service through an explicitly verified User-to-Party link for contact details, preferences, recurring gifts, and registrations.

### 9.2 Intake and case management

- **M1 · Must:** Support the three predefined HarbourKind service programs with distinct intake fields, statuses, and outcome measures.
- **M1 · Must:** Create referrals, cases, assignments, goals, tasks, appointments, interactions, services, and case notes.
- **M1 · Must:** Enforce explicit intake, case, assignment, goal, service, external-referral, task, and appointment transitions through transactional domain services with immutable transition history.
- **M1 · Must:** Record risk flags and safe-contact guidance with tighter permissions than ordinary case records.
- **M1 · Must:** Classify all case content as confidential or highly restricted, with sensitivity inheritance, explicit permission checks, and audited reclassification.
- **M1 · Must:** Upload PDF, JPEG, and PNG documents through a fail-closed quarantine, validation, ClamAV scanning, and private-object release lifecycle with classification and Organisation/program access controls.
- **M5 · Must:** Validate production document retention and deletion rules, reassess scanner isolation/capacity, and evaluate additional file types, content disarm and reconstruction, secure preview, public-form attachments, and a managed scanning provider.
- **M1 · Must:** Provide caseload, waitlist, overdue-action, and unresolved-risk views.
- **M1 · Must:** Restrict case data by role, program, Organisation, and assignment; supporter-facing users must not see case participation or notes.
- **M1 · Must:** Record case closure reason and structured outcomes.
- **M1 · Should:** Support referrals to external providers and track referral status.
- **M4 · Should:** Provide configurable intake templates, eligibility rules, and required-field checks by case stage.
- **M5 · Could:** Offer offline-friendly draft capture for outreach workers, subject to a separate security review.

### 9.3 Donations and fundraising

- **M2 · Must:** Complete simulated one-off and recurring donations without collecting or transmitting real payment details.
- **M2 · Must:** Support basic campaigns, source attribution, and fund/designation selection.
- **M2 · Must:** Generate clearly marked demo receipts and acknowledgements and simulate failed-payment states.
- **M2 · Must:** Represent donation intent, payment attempts, recurring mandates, refunds, and receipts as separate records with deterministic, idempotent transitions.
- **M5 · Must:** Accept real one-off and recurring donations through a hosted/tokenised payment provider.
- **M5 · Must:** Generate production receipts, acknowledgements, refunds, and failed-payment follow-up.
- **M5 · Must:** Import offline gifts and reconcile payment status without storing raw card data.
- **M5 · Should:** Support recurring-gift upgrades, soft credits, pledges, matching campaigns, dedications, and business donations.
- **M5 · Should:** Export transactions in a finance-system-compatible format.

### 9.4 Volunteering, events, goods, and partners

- **M4 · Must:** Capture volunteer applications, interests, availability, onboarding status, checks/qualifications, shifts, and hours.
- **M4 · Must:** Publish opportunities and allow registration, confirmation, cancellation, and attendance recording.
- **M4 · Must:** Record in-kind offers by category, quantity/value estimate, condition, status, and fulfilment outcome.
- **M4 · Must:** Manage local business and community-hub partner profiles, contacts, commitments, and engagement history.
- **M4 · Should:** Support event capacity, waitlists, reminders, attendance, and post-event follow-up.
- **M4 · Should:** Alert staff before a volunteer credential expires.

### 9.5 Segmentation and communications

- **M2 · Must:** Build saved segments from consent, role, geography, interest, donation activity, and campaign source.
- **M2 · Must:** Create reusable email templates with personalisation, preview, and simulated send.
- **M2 · Must:** Simulate donation acknowledgement and one welcome journey.
- **M2 · Must:** Enforce suppression, unsubscribe, consent, frequency-cap, and safe-contact rules before every simulated send.
- **M2 · Must:** Record simulated deliveries, bounces, unsubscribes, and meaningful actions on the supporter timeline.
- **M2 · Must:** Use per-recipient delivery records, re-check consent/suppression at dispatch time, and prevent all outbound delivery beyond the local demo transport.
- **M4 · Must:** Add dynamic segments using recency, frequency, value, event activity, and volunteer activity.
- **M4 · Must:** Add scheduling, approval, SMS templates, re-engagement, event, and volunteer onboarding journeys.
- **M4 · Should:** Run basic message or subject-line experiments with a declared success metric.
- **M4 · Should:** Let staff pause a journey globally or for an individual.
- **M5 · Must:** Connect production messaging providers with observable delivery and failure handling.

### 9.6 Dashboards and impact reporting

- **M3 · Must:** Provide role-specific dashboards for service demand, cases, outputs, outcomes, donations, supporter growth, and campaign performance.
- **M3 · Must:** Filter by date, program, service area, location, cohort, and campaign where privacy thresholds allow.
- **M3 · Must:** Separate inputs, activities, outputs, and outcomes in the impact model.
- **M3 · Must:** Prevent small-cohort reporting that could re-identify clients.
- **M3 · Must:** Export accessible CSV and presentation-ready charts with metric definitions, fictional-data labels, and data-as-of dates.
- **M3 · Must:** Calculate every dashboard value from a fixed, versioned metric registry and reconcile it to deterministic seeded records.
- **M4 · Must:** Add volunteer contribution and event reporting.
- **M4 · Should:** Support board, grant/funder, and annual-impact report packs.
- **M4 · Should:** Compare cohorts and periods while displaying missing-data rates.
- **M4 · Could:** Provide a public impact page backed only by approved aggregate metrics.

### 9.7 Administration and governance

- **M0 · Must:** Disable public staff registration and implement hashed, single-use, expiring Organisation Invitations with verified-email acceptance, explicit person-Party selection/creation, and Membership creation only on acceptance.
- **M0 · Must:** Require confirmed TOTP MFA and acknowledged recovery codes before any staff User can access operational Organisation routes.
- **M0 · Must:** Enforce Installation-wide User-level MFA, database-session revocation, authentication throttles, and step-up authentication independently of Organisation roles.
- **M0 · Must:** Keep password/MFA recovery under audited platform authority; Organisation administrators may manage membership but never another User's global credentials.
- **M0 · Must:** Establish local/test and isolated staging/demo environment configurations, with automated assertions preventing shared database, Redis/Horizon, cookie, key, domain, or object-storage namespaces.
- **M0 · Must:** Establish a dedicated, versioned application-data encryption key ring and a separate contact-index key ring, with tested recovery and rotation procedures independent of Laravel's `APP_KEY`.
- **M0 · Must:** Create separate tenant-audit and platform-security event streams with versioned allowlisted payloads, transactional writes for protected mutations, and database-level append-only privileges for runtime roles.
- **M0 · Must:** Create a restricted platform incident register with explicit alert/incident separation, severity, lifecycle, Organisation-impact projection, decisions, actions, evidence references, recovery gates, and corrective-action tracking.
- **M0 · Must:** Provide guarded, audited incident-containment commands for session/token revocation, Organisation Access Holds or Installation-wide write freezes, queue/outbox pausing, upload/form shutdown, and credential-rotation coordination without requiring routine access to tenant content.
- **M0 · Must:** Publish a monitored RFC 9116 `/.well-known/security.txt` and coordinated vulnerability-reporting policy on every hosted public/staff surface before it becomes internet-accessible.
- **M0 · Must:** Apply `AGPL-3.0-only` to all first-party software from the first public commit, add the approved documentation/demo-data licences and notices, expose the deployed source/release link, require DCO sign-off, and fail CI on incompatible or unknown production dependency licences.
- **M0 · Must:** Provision, invite to, leave, switch, and administer Organisations using the adapted Laravel Starter Kit membership lifecycle; authenticated Membership alone never grants Organisation provisioning authority.
- **M0 · Must:** Implement the Organisation state machine and separate Access Holds, enforcing the most restrictive applicable result consistently across staff routes, public hosts, jobs, forms, caches, search, and signed links.
- **M0 · Must:** Assign multiple independently scoped Role Assignments per Organisation Membership; Membership in one Organisation grants no access to another.
- **M0 · Must:** Treat Organisation ownership as administrative control rather than a wildcard operational permission.
- **M0 · Must:** Require deliberate handling of owned records before an Organisation owner can leave or transfer ownership.
- **M0 · Must:** Enforce and test Organisation isolation across routes, queries, files, search, caches, jobs, exports, audits, and reports.
- **M0 · Must:** Enforce tenant ownership and cross-record integrity with non-null `organisation_id` columns, Organisation-scoped unique indexes, and composite foreign keys on tenant-owned relationships.
- **M0 · Must:** Generate HarbourKind and NeighbourLink from a pinned seed version, fixed clock, explicit Organisation context, and deterministic scenario builders.
- **M0 · Must:** Provision a globally unique Organisation slug and resolve public tenant context from the platform subdomain, rejecting unknown and reserved hosts without fallback.
- **M1 · Must:** Configure basic program labels, stages, outcome measures, and taxonomies without code changes.
- **M1 · Must:** Use role-based access control with program and case-assignment restrictions.
- **M1 · Must:** Apply field-level supporter-safe projections that reveal neither client participation nor service-only attributes.
- **M3 · Must:** Provide policy-filtered audit views and audit authentication, highly restricted views, exports, edits, merges, permission changes, simulated automation, demo impersonation, reset, deployment, and backup events.
- **M3 · Must:** Generate and verify chained daily audit digest manifests in the offsite recovery set and alert on gaps or mismatches.
- **M3 · Must:** Exercise the incident runbook against synthetic cross-tenant exposure, privileged-account compromise, malicious-file release, key disclosure, and audit/backup-integrity scenarios; preserve a redacted evidence pack and track corrective actions.
- **M3 · Must:** Publish the reproducible source repository with governance, contribution, security, support/version, trademark, attribution, changelog and release documentation plus a complete source archive for the demonstrated release.
- **M3 · Must:** Reset one demo Organisation safely and deterministically without truncating global or other Organisations' records or allowing stale jobs to mutate the new generation.
- **M3 · Must:** Provision and expire an isolated HarbourKind/NeighbourLink sandbox pair through hashed, time-limited access links and demo-only synthetic role selection without collecting evaluator personal data.
- **M4 · Must:** Configure forms, templates, journeys, and richer workflow rules without code changes.
- **M5 · Must:** Add time-limited elevated access plus retention, archival, legal-hold, and defensible deletion workflows by record class.
- **M5 · Must:** Validate audit/network retention periods, introduce the dedicated retention role and deletion ledger, and prove that restore does not resurrect expired audit or subject data.
- **M5 · Must:** Establish adopter-specific incident roles, 24/7 critical-response coverage, security contacts, contractual controller/processor responsibilities, legal notification rules/deadlines, an independent evidence store and communications fallback, and a completed pre-launch tabletop exercise.
- **M5 · Must:** Define the official hosted-service pricing, support/SLA tiers, implementation offer, terms, data-processing responsibilities, cost controls and subsidy policy without changing the open-source rights or withholding generic product capabilities.
- **M5 · Must:** Provision every officially hosted Organisation with an accepted initial Owner, Billing Account, versioned Service Offering, Access Policy, and `pending_activation` Subscription while leaving self-hosted provisioning independent of CommunityKind billing.
- **M5 · Must:** Separate Billing Account ownership, administration, viewing, invitations, contacts, and closure from Organisation Membership and data access; allow one Billing Account to fund multiple otherwise unrelated Organisations.
- **M5 · Must:** Maintain canonical Service Usage, Service Invoices, Service Payments, safe Payment Method references, and idempotent provider reconciliation separately from tenant fundraising records.
- **M5 · Must:** Enforce successor-Subscription funding transfers, scheduled cancellation, past-due grace, full/read-only/recovery-only/denied Hosted Access, notification, export/recovery, and retention behavior without changing Organisation Status or silently deleting data.
- **M5 · Must:** Allow an Organisation administrator to attach, verify, activate, replace, and remove a custom domain with automated TLS and an audited domain lifecycle.
- **M5 · Must:** Offer controlled import with validation, dry run, error report, and rollback.
- **M5 · Should:** Include a data-quality dashboard for duplicates, invalid contacts, missing consent, and incomplete required fields.

## 10. Data model

Core entities:

- **CommunityKind Installation:** independently operated deployment and identity boundary; the official hosted service is one Installation and each self-hosted deployment is another;
- **User:** Installation-wide authentication identity; does not itself represent a client, donor, volunteer, staff profile, organisation, or billing payer;
- **Organisation:** nonprofit tenant, globally unique slug, status, branding, settings, and feature configuration;
- **Organisation Membership:** accepted User tenure in one Organisation, linked to exactly one tenant-local person Party; ownership, Membership Holds, and ended tenure are retained independently of operational Roles;
- **Organisation Invitation:** expiring proposal for Membership, initial scoped Role Assignments, and optional Owner responsibility; it explicitly selects an existing person Party or creates one and never auto-links by email;
- **Operational Role and Role Assignment:** permission bundle plus its Organisation-wide or Program-specific scope; a Membership may hold multiple assignments;
- **Organisation domain:** hostname, type (platform subdomain or custom), verification state, verification evidence, activation timestamps, and Organisation;
- **Party:** tenant-owned person, household, or organisation that may exist without a login;
- **Party role:** client, donor, volunteer, partner contact, or event attendee; a business classification, not an authorization role;
- **Portal Access Grant:** Organisation-scoped, explicitly verified self-service relationship between a User and a person Party, including access type, verification, and revocation state; it is not staff Membership;
- **Relationship:** household member, employer, referral relationship, business affiliation;
- **Consent and contact preference;**
- **Program, referral, intake, case, assignment, goal, interaction, service, referral-out, outcome measure;**
- **Campaign, appeal, donation, recurring mandate, payment, receipt, fund;**
- **Volunteer application, credential, opportunity, shift, attendance, hours;**
- **Event, registration, attendance;**
- **In-kind offer and fulfilment;**
- **Segment, message, template, journey, journey enrollment, delivery event;**
- **Task, note, file, audit event, and reporting period.**

Official-hosted-service entities, absent from self-hosted Installations, are:

- **Billing Account:** provider-independent individual or organisation that pays for one or more Organisations without owning or accessing tenant data;
- **Billing Account Membership:** accepted billing tenure with separate Owner, Administrator, and Viewer responsibilities; billing invitations and contacts are separate from authority;
- **Service Offering and Access Policy:** versioned commercial terms, included usage, support level, payment grace, recovery periods, notifications, and eventual Hosted Access restrictions without withholding generic self-hosted capabilities;
- **Subscription:** one Organisation's time-bounded hosted entitlement through one Billing Account; changing payer creates a successor Subscription rather than rewriting history;
- **Service Usage:** billing-safe aggregate consumption that excludes operational content and staff identities except designated billing contacts;
- **Service Invoice and Service Payment:** canonical hosted-service financial records reconciled idempotently with replaceable provider references and never mixed with Organisation fundraising; and
- **Hosted Access:** derived full, read-only, recovery-only, or denied access based on Organisation Status, Subscription Status, Access Policy, and Access Holds without altering Memberships or Roles.

### Critical separation rule

The platform may use one underlying party identity for deduplication, but client-service data must live in a separately authorised domain. A supporter user may see that a person is a donor or volunteer but must not be able to infer that the person is or was a service client. Cross-domain matching, search results, exports, notifications, and analytics must preserve this boundary.

This canonical identity exists only inside one Organisation. No global constituent table, email lookup, search result, aggregate, or administrator screen may reveal that the same person appears in another tenant.

### User and Party identity rules

- A **User** proves who can sign in; an **Organisation Membership** determines staff access; a **Party** records whom the nonprofit knows and what relationship they have with it.
- Most Parties never need a User account. Anonymous donations, referrals, imports, and staff-created contacts create tenant-local Parties only.
- Matching email addresses never automatically connect a User to a Party. Organisation Invitations explicitly select an existing person Party or create a new one. Self-service separately requires a verified invitation or account-claim flow that creates a Portal Access Grant.
- A User may have one active person-Party link per Organisation and separate, unrelated links in other Organisations. Linking and matching never cross tenant boundaries.
- Every Organisation Membership links to one person Party in that Organisation, but Membership does not grant self-service access to that Party, reveal client status, or bypass case permissions.
- Organisation Parties do not authenticate. Human contacts authenticate as Users linked to their person Party and related to the organisation Party.
- Deleting or disabling a User removes authentication and active self-service links but does not automatically delete Organisation-owned Party, donation, volunteer, or case records; those follow the applicable retention workflow.
- The staff interface uses **People and Organisations** or **Contacts** rather than exposing the technical term “Party.”

The Portal Access Grant must store `organisation_id`, `party_id`, `user_id`, access type, verification timestamp, revocation timestamp, and audit metadata. Database and application rules must prove that the Party belongs to the same Organisation and that ordinary self-service grants target person Parties only. Organisation Membership carries its own same-Organisation person-Party reference and never substitutes for a Portal Access Grant.

### Database tenancy enforcement

The product uses **shared-schema multi-tenancy**: all Organisations share one application database, while layered application and database controls enforce isolation. Separate databases per Organisation and database row-level security are not part of the spec MVP.

Tables are classified as follows:

- **Installation-wide:** `users`, `organisations`, Organisation Memberships, Role Assignments, Organisation domains, authentication/session infrastructure, and immutable platform reference data.
- **Official-hosted only:** Billing Accounts and Memberships, Service Offerings, Access Policies, Subscriptions, Service Usage, Service Invoices, Service Payments, and external-provider references.
- **Tenant-owned:** Parties, programs, cases, donations, campaigns, consents, documents, engagement data, audit events, and all other organisation records.

Every tenant-owned table—including child and pivot tables—has an immutable, non-null `organisation_id`, even when the Organisation could be inferred through a parent. This deliberate duplication enables consistent filtering, indexing, auditing, and database-enforced relationship integrity.

For a tenant-owned parent, `(organisation_id, id)` is unique. Tenant-owned children use composite foreign keys so that, for example:

```text
cases(organisation_id, party_id)   → parties(organisation_id, id)
cases(organisation_id, program_id) → programs(organisation_id, id)
```

The database must therefore reject a HarbourKind case that references a NeighbourLink Party or program, regardless of application behavior.

Additional invariants:

- `organisation_id` is guarded from mass assignment and cannot change after creation;
- tenant-local unique indexes start with `organisation_id`, while Organisation slugs and domain hostnames remain globally unique;
- pivots carry `organisation_id` and use Organisation-consistent foreign keys;
- sensitive associations prefer typed relationships over unconstrained polymorphic relationships;
- email and phone blind indexes are Organisation-bound but not unique because people may share contact details;
- Organisation deletion is restrictive at the database level and runs through the deliberate lifecycle/purge workflow rather than a broad cascade; and
- soft deletion never disables tenant scoping or makes a record available to another Organisation.

### Laravel tenant context

HTTP requests, queued jobs, and tenant commands establish an explicit request/job-scoped `TenantContext`. Tenant-owned Eloquent models implement a common `BelongsToTeam` contract and trait that:

- fail closed when no tenant context exists;
- assign the current `organisation_id` when creating a record;
- reject an explicitly supplied or related Organisation mismatch;
- apply the ordinary tenant query scope;
- prevent later mutation of `organisation_id`; and
- expose Organisation-aware relationships that are eager-loaded where appropriate.

The Eloquent tenant scope is defence in depth, not the sole boundary. Laravel policies, tenant-aware route-model binding, composite database constraints, field projections, and adversarial tests remain mandatory. Tenant-scope bypass is restricted to a narrowly defined internal service and must not appear in ordinary controllers, jobs, exports, or commands.

Tenant-owned indexes begin with `organisation_id` where it is the leading filter. Initial examples include `(organisation_id, email_blind_index)`, `(organisation_id, phone_blind_index)`, `(organisation_id, program_id, status)`, `(organisation_id, assigned_user_id, status)`, `(organisation_id, campaign_id, status, donated_at)`, and `(organisation_id, occurred_at)` for audit events.

Each queued job carries an immutable Organisation identifier, restores the `TenantContext`, checks current Organisation status, and then fetches its records through the tenant scope. Console commands either require an explicit Organisation or deliberately iterate active Organisations one at a time. Failures and logs must not include sensitive payloads.

PostgreSQL row-level security may be reconsidered during M5 security architecture review, but it is explicitly deferred because its per-connection context and operational complexity would impede the spec build. It must not be treated as a missing prerequisite for M0–M3.

### Encryption architecture and searchable sensitive data

Encryption is layered according to the threat it addresses:

- TLS protects data crossing network boundaries between browsers, Cloudflare, the Forge origin, PostgreSQL, Redis, Resend, Sentry, and S3-compatible providers; same-host PostgreSQL/Redis connections use Unix sockets or TLS rather than an unprotected network listener;
- the selected server/database host must provide encrypted volumes and encrypted snapshots where the hosting plan supports them, and each application-object bucket must enforce provider-side encryption, blocked public access, and versioning; verifiable volume/snapshot encryption is mandatory before hosting real data;
- Restic encrypts the offsite backup repository independently of the storage provider; and
- application-layer encryption adds defence in depth if a database dump, read-only database credential, snapshot, or application-object inventory is exposed without the application keys.

Application-layer encryption is not end-to-end encryption and does not protect plaintext from a fully compromised running application or server, because the application must be able to decrypt it. The design therefore still depends on authorization, host hardening, least privilege, logging exclusion, short-lived access, and incident response.

Laravel's `APP_KEY` remains dedicated to framework concerns such as cookies, sessions, signed/encrypted framework payloads, and Fortify-managed secrets. Domain data uses an independent, versioned data-encryption key ring; deterministic contact matching uses a second independent key ring. Keys are generated cryptographically, never derived from passwords or each other, and never stored in PostgreSQL, Git, GitHub Actions, logs, Sentry, fixtures, or documentation.

A custom typed Eloquent cast and value object wrap Laravel's maintained `Encrypter` rather than implementing cryptography directly or coupling domain ciphertext to Laravel's default `encrypted` cast. Each randomized encrypted envelope stores a non-secret key version and binds the Organisation UUID, record UUID, and field name as verified context. Decryption fails closed if ciphertext integrity, context, or key version is invalid; errors never log the plaintext or ciphertext. Encrypted columns use `text` or another capacity-tested type and are not sortable, filterable, or indexable.

Application-layer encryption is required for:

- case-note and addendum bodies, intake narratives, presenting-needs text, risk-assessment details, referral details, and safe-contact banners/instructions;
- contact email addresses, telephone numbers, street addresses, and address-detail fields;
- document display names and sensitive document metadata, while object keys remain opaque UUID-based paths with no personal data;
- free-text supporter/donation notes and any later provider credential or integration secret that a validated workflow requires storing in the application database; and
- exact birth dates, identity-document values, protected locations, or similarly identifying fields if a validated adopter workflow later justifies collecting them.

Party names remain plaintext under infrastructure-level encryption because authorized staff need ordinary name search, sort, duplicate review, and human recognition. A Installation-wide User's normalized login email also remains plaintext because Laravel/Fortify requires deterministic identity lookup and uniqueness; it is a credential identifier, never reused as an automatic Party link. These are deliberate confidentiality limitations, not assertions that names or login addresses are harmless. UUIDs, Organisation and relationship keys, workflow/status and reason codes, program/taxonomy identifiers, timestamps, monetary minor-unit amounts/currency, and approved reporting dimensions also remain queryable. A broad locality, service area, or age band may be stored separately only when its purpose and reporting safety are approved; it must not be reversible to the encrypted source value.

Email and telephone equality matching uses blind indexes rather than plaintext normalized columns. The application normalizes the value, then calculates HMAC-SHA-256 over `organisation_uuid | field_type | normalized_value` using the contact-index key. Organisation binding prevents the same contact value from producing a cross-tenant correlatable digest. These indexes support exact within-Organisation lookup and duplicate suggestions only: prefix, substring, fuzzy, and cross-Organisation contact search are unavailable. Shared contact details may produce multiple candidates and never justify an automatic Portal Access Grant or automatic merge.

Contact-index rotation temporarily dual-writes and queries the current and immediately previous index versions while an idempotent, Organisation-scoped Horizon `bulk` job rebuilds stored indexes. Data-key rotation writes with the current key, reads explicitly supported previous versions, and re-encrypts old rows through an idempotent Organisation-scoped job. Rotation records counts and key-version identifiers—but no values—in the platform security stream. An old key leaves the runtime key ring only after a complete live-data scan and queue reconciliation; it remains in restricted recovery custody until every backup encrypted with it has expired or been safely rebased. A representative restore must prove the new recovery set before any key is destroyed. `APP_KEY` rotation is a separate procedure.

Scout indexes names and explicitly approved nonsensitive fields only. Encrypted values, blind indexes, case participation, narratives, safe-contact data, and restricted document metadata never enter Scout, cache values, audit payloads, Sentry, logs, notifications, or analytics facts. Queue payloads carry opaque record IDs rather than decrypted contact or narrative values; an authorized job resolves and decrypts only at execution time, and any provider call receives only the minimum plaintext it necessarily needs. Authorized contact detail pages decrypt only the fields they display; list views mask contact values. Reporting stores approved categorical facts at write time and cannot reconstruct encrypted narrative source data.

S3-compatible uploads and downloads are streamed where practical. Temporary local copies use restrictive permissions and are removed promptly. Client-side or per-document envelope encryption, a KMS/HSM, and searchable encryption beyond exact blind indexes are deferred to M5, when the hosting provider, jurisdiction, adopter threat model, recovery ownership, and cost are known.

### Case confidentiality and sensitive records

All case participation and content is confidential. The spec MVP uses two levels:

| Classification | Typical content | Access |
|---|---|---|
| `confidential` | Intake, needs, goals, interactions, services, referrals, and outcomes | Assigned case worker; program manager for assigned program |
| `highly_restricted` | Detailed risk information, unsafe-contact details, protected locations, legal/identity documents, and especially sensitive notes | Assigned case worker or program manager who also holds explicit sensitive-data permission |

`sealed` records, named-person access lists, and break-glass access are deferred to M5. They must not be approximated with an insecure MVP shortcut.

Confidentiality rules:

- every case defaults to `confidential`; a program may default new cases to `highly_restricted`;
- a note or document may be more restricted than its parent case, never less;
- lowering sensitivity requires a reason and produces an audit event;
- closing or archiving a case does not reduce its confidentiality;
- Organisation ownership and Organisation administration grant no case-content access;
- engagement staff, executive viewers, supporters, and unauthorized service staff cannot infer case existence through search, counts, timelines, recent items, notifications, exports, or error behavior;
- search uses an authorized service-only projection and does not place case narratives or risk details in the general index;
- notifications and logs contain task-oriented references rather than client names, case narratives, filenames, risk details, or safe-contact content; and
- dashboards and executive exports expose privacy-safe aggregates only.

Safe-contact data is separated into:

- a concise **contact safety banner**, such as “Do not leave voicemail,” visible only to authorized service staff before initiating contact; and
- a **detailed risk assessment**, treated as highly restricted content.

Case files use private Organisation-scoped storage and application-streamed authorized downloads. Document sensitivity inherits from the case and may be raised. Upload, scan outcome, download, replacement, reclassification, and deletion events are audited. Arbitrary uploads are disabled in the public demo; it contains only deterministic, harmless fixtures that pass through the same document-state and authorization model.

Case workers cannot bulk-export identifiable case data. Program managers require explicit export permission, and highly restricted narratives and documents are excluded from bulk exports in the spec MVP. Supporter self-service exposes no case data. A future personal-information access request is a human-reviewed workflow rather than a direct database export because a record may contain information about multiple people.

Audited sensitive-data events include case-detail views, restricted-note/risk views, document access, exports, classification changes, assignments, permission changes, and suspicious failed access. Audit records store actor, Organisation, resource identifier, action, timestamp, and context—not the sensitive content itself.

### Document upload, quarantine and malware scanning

Antivirus scanning is one defence layer, not proof that a file is safe. M1 therefore combines strict type and size allowlists, server-generated names, quarantine outside the web root, malware scanning, private storage, authorization on every retrieval, forced download, monitoring, and prompt cleanup.

M1 accepts staff uploads only. The initial allowlist is PDF (`.pdf`), JPEG (`.jpg`/`.jpeg`), and PNG (`.png`), with a maximum uploaded file size of 20 MiB and a maximum decoded image size of 40 megapixels. SVG, HTML/XML, Office documents, archives, executables, scripts, audio, and video are rejected regardless of their claimed MIME type. Nginx, PHP, Laravel validation, scanner stream limits, job timeouts, and S3 client limits use compatible values so no lower layer fails ambiguously. Initial abuse limits are configurable and default to five upload attempts per User per minute, 100 MiB per Organisation per hour, and 25 non-terminal scans per Organisation.

The upload endpoint requires authenticated, policy-authorized case access and an active Organisation. It decodes and validates the submitted filename, rejects path components, control/null characters and deceptive/double extensions, and treats the name as display metadata only. It checks the extension, client-declared type, server-detected MIME type and file signature against the same allowlist; no one check is sufficient alone. After the first clean malware result, images must decode successfully within dimension/resource limits, are re-encoded to a canonical JPEG or PNG to strip metadata/extraneous content, and the resulting bytes are scanned again before release. The server allocates the document UUID and local filename, encrypts the sanitized display name, and never uses a submitted name in a path, log, event, header without safe encoding, or S3 key.

The `Document` record has an optimistic version/generation and the following explicit scan lifecycle:

| State | Allowed next state | Binary availability |
|---|---|---|
| `awaiting_upload` | `quarantined`, `deleted` | None |
| `quarantined` | `scanning`, `deleted` | Local quarantine only |
| `scanning` | `clean`, `rejected`, `scan_failed`, `deleted` | Local quarantine only |
| `scan_failed` | `scanning`, `deleted` | Local quarantine only; retry window applies |
| `clean` | `deleted` | Private Organisation-scoped S3 object |
| `rejected` | None | Binary destroyed; safe tombstone only |
| `deleted` | None | Unavailable; retained copies follow approved backup/retention rules |

The request creates `awaiting_upload`, streams the bytes to an environment-specific local quarantine directory outside the release and web roots, then atomically advances to `quarantined`. The directory uses server-generated UUID paths, restrictive ownership and permissions, and a `noexec`, `nodev`, `nosuid` filesystem where the host permits it. It is excluded from deployments, application backups, public storage links, web-server aliases, file watchers, indexers, and support tools. A failed or abandoned request cleans its partial file; reconciliation removes orphaned files and `awaiting_upload` records after one hour. Startup and recovery reconciliation marks a database record whose expected local bytes are missing as failed and ultimately asks for re-upload; quarantine is intentionally never restored from backup.

An after-commit, unique Horizon `security` job receives only the Organisation UUID, document UUID, and expected generation. It restores tenant context, obtains a per-document overlap lock, rechecks authorization-independent Organisation/document state, and transitions `quarantined` or retryable `scan_failed` to `scanning`. The scanner is accessed through a `MalwareScanner` interface; the Forge adapter uses a maintained ClamAV `clamd` service running as a dedicated unprivileged account over a permission-restricted Unix socket. The daemon receives bytes through `INSTREAM`, has no public TCP listener, cannot read application secrets or credentials, and runs with system-service filesystem, privilege, memory and CPU restrictions. The application never executes or renders the uploaded content.

FreshClam checks for supported signature updates hourly. Scanner health records engine version, signature version/age, socket availability, last successful scan, queue age and local quarantine usage without filenames or file contents. Upload intake is temporarily disabled before receiving bytes when the scanner is known unhealthy or signatures are more than 24 hours old. A timeout, stale definitions, malformed response, daemon error, resource-limit result, or unavailable scanner produces `scan_failed`, never `clean`; the job retries with bounded exponential backoff and alerts the operator. A file still unscanned after 24 hours is deleted and the uploader is asked to upload it again. There is no Organisation-administrator or application-operator bypass that marks a failed or rejected file clean.

While scanning, the worker calculates the size and SHA-256 integrity checksum. A clean result uploads the same bytes to the environment's private application bucket using an opaque key containing environment, Organisation UUID and document UUID—but no name or Party/case identifier—then verifies the stored size/checksum before atomically marking the record `clean`. It deletes the local quarantine copy only after verification. If persistence or verification fails, no document is released and the partial S3 object is removed. The protected checksum, detected MIME type, scanner engine/signature version, scan timestamp and result are retained as document security metadata; raw checksums and scanner details are not exposed in tenant audit views.

A positive or policy-rejected result immediately deletes the local bytes and records a content-free `rejected` tombstone with reason category, scanner version, timestamps and opaque identifiers. It does not retain or upload the suspected malware for investigation in M1. Audit and telemetry exclude the submitted filename, checksum, scanner signature name and content. Operators see aggregate failure/rejection counts and redaction-safe diagnostics; users receive a neutral rejection message that does not reveal scanner internals.

Only `clean` files can be retrieved. The Laravel download controller re-establishes Organisation context, checks current Organisation/case/document state, role, program, assignment, sensitivity permission and required audit persistence on every request, then streams the private object with an ASCII-safe generated attachment filename, allowlisted `Content-Type`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, private/no-store caching, and no inline rendering. S3 objects have no public ACL and no permanent or client-constructable URL. Inline PDF/image preview and direct presigned download are deferred to M5 pending a separate-origin/sandbox and auditing design.

Replacing a document creates and scans a new immutable version; the current clean version remains available until the replacement becomes `clean`, after which the pointer switches transactionally. A rejected or failed replacement never removes the last clean version. Deletion or case/Organisation lifecycle changes invalidate pending generations so stale jobs cannot release a file, remove local quarantine bytes promptly, and process any clean S3 object through the approved deletion and backup-retention workflow.

Local development supplies ClamAV through the documented container profile. Fast tests use a deterministic fake `MalwareScanner`; a separate integration test exercises real ClamAV with the standard harmless EICAR test file and never commits or uploads that fixture to S3. Staging permits synthetic upload testing. Public demo, sandbox bootstrap, and unauthenticated/public forms cannot accept arbitrary file bytes in M0–M4.

### Audit architecture, tamper evidence and retention

Audit evidence is distinct from application logs, Sentry telemetry, domain events, workflow transition history, and the transactional outbox. Those systems may reference the same correlation identifier, but none substitutes for another and the product is not event-sourced.

| Stream | Scope | Examples | Visibility |
|---|---|---|---|
| Tenant audit events | Non-null immutable `organisation_id` | Membership/role changes, Organisation lifecycle, case and donation transitions, consent changes, merges, exports, restricted views, document access, automation | Policy-filtered tenant audit UI |
| Platform security events | Global, with optional Organisation context | Login/MFA/recovery, suspicious denials, demo impersonation, domain resolution, deployment, backup, operator actions, integrity-check failure | Installation operator only; no tenant UI |
| Short-lived network context | One security-event identifier | Encrypted source IP and raw user agent needed for investigation | Platform security process only; purged quickly |

Every durable event contains a globally unique ID, schema version, stream, immutable Organisation context where applicable, action code, outcome, actor kind, nullable Installation-wide User ID, initiating User ID for system work, subject type and opaque subject ID, request and correlation IDs, source, `occurred_at`, server-controlled `recorded_at`, and an allowlisted metadata object. Actor kinds include User, synthetic demo persona, system job, verified provider, and Installation Operator.

Audit payload rules:

- events record what happened and why, not complete before/after record snapshots;
- metadata uses versioned per-action schemas and rejects unknown keys rather than accepting arbitrary arrays;
- case narratives, note bodies, risk details, safe-contact content, names, email addresses, phone numbers, postal addresses, filenames, message bodies, tokens, credentials, signed URLs, payment details, raw request/response bodies, and SQL bindings are prohibited;
- state transitions point to their immutable transition record; audit metadata may include safe state codes, changed field names, reason codes, counts, and policy identifiers;
- deleted Users and subjects remain referable by opaque ID without preserving a display-name snapshot; and
- suspicious cross-tenant requests record the attempted resource type and a keyed identifier hash, never another tenant's identifier or content.

Write and failure behavior:

- an authorized domain mutation and its tenant audit event are inserted synchronously in the same PostgreSQL transaction; if required audit insertion fails, the protected mutation rolls back;
- access to highly restricted content, downloads, exports, step-up actions, and operator operations fails closed when its required audit event cannot be persisted;
- authentication failures and denied requests write a separate security event after the denial without changing the denied resource;
- queued jobs restore Organisation and initiating-actor context and reuse the request/correlation chain; retries do not duplicate the logical audit event; and
- ordinary low-sensitivity list/detail reads are not individually audited, avoiding unusable noise, while restricted views, bulk access and exports always are.

The migration/owner role owns the audit tables. The normal web and Horizon database roles may `INSERT` and read only the policy-approved projection; they receive no `UPDATE`, `DELETE`, `TRUNCATE`, ownership, or trigger-management privileges. Database triggers reject row updates/deletes outside the dedicated retention procedure, and the Eloquent audit models expose no update, soft-delete, or ordinary delete path. A separately credentialed maintenance role performs approved retention purges; the application runtime cannot grant itself that role.

These controls make the streams append-only for the normal application, not absolutely immutable against a PostgreSQL owner, server root user, or provider administrator. To make later tampering detectable, a scheduled process canonicalizes each closed day's events, computes a digest chained to the previous manifest, and copies the signed manifest and event export to the offsite Restic/S3 recovery set. Bucket versioning and object lock are enabled if the selected S3 provider supports them. Integrity verification runs regularly and alerts through a channel independent of the audited database. The PRD does not claim cryptographic non-repudiation.

Retention starts with the following product defaults, all explicitly provisional rather than jurisdictional requirements:

- disposable sandbox tenant events live with the sandbox; purge leaves a content-free platform tombstone for 180 days identifying the sandbox UUID, generation, purge outcome and timestamp;
- staging/demo platform security events remain searchable for 180 days;
- a future production baseline keeps platform security events searchable for 12 months and archived for a further 12 months;
- production tenant audit events follow the subject record's approved retention class and default to 24 months after that subject is defensibly deleted when no longer period or legal hold applies; and
- encrypted raw network context expires after 30 days, while the durable event retains only a keyed network indicator suitable for correlation.

M5 must validate these periods with the adopter and target jurisdiction. Legal hold suspends eligible purge without exposing held content to unauthorized users. Retention runs are dry-runnable, reason-coded, counted, manifested and themselves audited. After restoring an older backup, the system reapplies the retained deletion ledger before reopening access so recovery does not silently resurrect data whose retention period already expired.

Tenant audit views use the permission matrix and field projection: administrators see tenant-configuration history, program managers see authorized program events, workers and engagement staff see their own relevant domain actions, and executive viewers receive none. Viewing or exporting audit evidence is itself audited. Audit events are queried directly through tenant-aware services and are never placed in Scout or general search.

### Incident response and breach-decision workflow

An alert, defect, policy violation, or failed control is not automatically a confirmed security incident. The response process preserves that distinction while defaulting uncertain high-impact situations to the higher severity. It follows continuous preparation, detection, response, recovery, and improvement rather than treating incident response as a one-time technical cleanup.

Activation triggers include suspected cross-tenant access; unauthorized access to confidential or highly restricted data; unexpected bulk reads/exports; privileged User, Forge, GitHub, Cloudflare, S3, database, Resend, Sentry, 1Password, or Bitwarden compromise; disclosed application or index keys; unrecognized deployment; audit-digest or backup-integrity failure; ransomware/destructive activity; evidence that a rejected file was released; provider notification; or a credible report from a tenant or researcher. A routine blocked attack or rejected malware upload remains a security event unless triage finds compromise, persistence, control failure, or material impact.

Every central staff host, platform-owned public host, tenant subdomain, and later custom domain serves an RFC 9116 `/.well-known/security.txt` scoped to that host. It contains current `Contact`, `Expires`, `Canonical`, and `Policy` fields plus an encryption method when supported; an external check alerts before expiry, disappearance, unexpected redirect/content change, or contact failure. The contact reaches a monitored channel independent of the application and explains how to submit encrypted technical details without case data, credentials, live malware, or other people's personal information. Reports are acknowledged within two business days, triaged into the restricted alert register, and assigned severity under the same model as internal detection. Vulnerabilities are never reported through public GitHub issues; a private repository channel or security advisory may be added if the source later becomes public. The disclosure policy defines scope, prohibited destructive/privacy-invasive testing, expected coordination and response behavior, and makes no unreviewed bug-bounty, safe-harbour, or testing-authorization promise.

The platform uses the following initial production severity model. Acknowledgement means a qualified human has assumed responsibility, not that the incident is resolved:

| Severity | Typical threshold | Initial production response target |
|---|---|---|
| `S1 critical` | Active or credible multi-tenant exposure; platform-privileged compromise; key theft; destructive/ransomware activity; widespread unavailability; or credible immediate risk to a person's safety | Page immediately; human acknowledgement within 15 minutes; named incident commander within 30 minutes; continuous response and at least hourly stakeholder cadence while active |
| `S2 high` | Probable sensitive-data exposure limited to one Organisation; privileged tenant-account takeover; malicious file released; or material integrity failure with bounded scope | Page immediately; acknowledgement within one hour; commander and containment plan within two hours; updates at least every four hours while active |
| `S3 medium` | Contained control failure or suspicious activity with no current evidence of sensitive access, persistence, or material harm | Triage within one business day and assign a dated remediation/monitoring plan |
| `S4 low` | Security weakness or policy deviation with no evidence of exploitation and low immediate impact | Triage within three business days and route to the security backlog |

These are internal service objectives, not legal notification periods. M0–M4 host synthetic data only and make no 24/7 support claim. A real-data production launch is blocked unless a named primary and backup can meet the S1/S2 targets, directly or through a contracted responder. Severity considers confidentiality, integrity, availability, safety impact, affected data classes, number of Organisations/people, privilege, persistence, recoverability, and regulatory/contractual exposure; every severity change records its evidence and approver.

Alerts and incidents use immutable UUIDs and separate state. The primary incident lifecycle is:

```text
reported → triaging → confirmed → contained → eradicated → recovering → monitoring → closed
               ↘ false_positive
false_positive|closed → reopened → confirmed
```

Containment, analysis, eradication, and recovery may overlap. Backward transitions are allowed when new evidence expands scope; they never erase prior decisions or timestamps. `false_positive` and `closed` retain the investigation record. Closure requires explicit approval rather than elapsed time or disappearance of alerts.

Each incident record contains the incident UUID, status, severity, detection source, `detected_at`, first-human-awareness time, confirmation time, commander, response roles, affected environments and Organisations, potentially affected data classes and time window, known/unknown facts, hypotheses with confidence, indicators, decisions, containment actions, evidence references, provider cases, recovery gates, communications, breach-assessment records, and corrective actions. It stores opaque subject/resource references and minimum necessary metadata, never copied case narratives, credentials, raw tokens, full request bodies, or unrestricted evidence. One platform incident may relate to many Organisations through a restricted incident–Organisation link; each tenant projection exposes only that Organisation's confirmed impact and approved communications.

The minimum response roles are incident commander, technical/containment lead, evidence custodian and scribe, privacy/legal decision owner, tenant-communications lead, provider liaison, and—where client safety may be affected—a safeguarding/business owner. One person may fill several roles for a synthetic exercise, but every production role has a named primary and backup and conflicts are documented. The incident commander coordinates and records decisions but cannot self-approve unrestricted tenant-content access or a legal notification decision.

Platform operators may execute pre-authorized containment that does not disclose tenant content:

- revoke one User's sessions, remember tokens, invitations, recovery grants and relevant provider tokens globally across Organisations;
- place one Organisation, an environment, or the platform into a reason-coded write freeze while preserving safe read-only or unavailable behavior;
- disable public forms, uploads, custom domains, signed links, webhooks, exports, Resend delivery, payment/message adapters, or other affected capabilities;
- pause selected Horizon queues and the transactional outbox before unsafe work dispatches, while retaining jobs for investigation rather than silently deleting them;
- invalidate caches, rotate scoped credentials/keys, block indicators at Cloudflare or the origin, and isolate a host/provider integration; and
- prevent owner/admin reactivation until the incident commander releases the containment lock.

Incident containment uses Access Holds rather than another Organisation lifecycle status; the most restrictive applicable Organisation Status, Subscription-derived Hosted Access, and Access Hold wins. Access Holds use explicit scope, reason, incident UUID, expiry/review time, two-person approval where production access allows it, and a tested rollback/recovery command. Emergency action must not wait for perfect evidence or second approval when delay would increase harm; the responder records the exception and obtains retrospective review. Disabling or rotating a shared Installation-wide User credential must account for every Organisation Membership. Potential evidence is captured first when safe, but evidence preservation never takes priority over stopping ongoing harm.

Routine platform operations still have no case-content access. If an investigation requires tenant content, M5's time-limited break-glass design must require incident scope, named approver independent of the investigator where staffing permits, least-privilege field projection, MFA step-up, automatic expiry, visible access logging, and after-action review. A tenant administrator cannot grant platform-wide access, and break-glass cannot weaken safe-contact, unrelated-Organisation, retention, or legal-hold boundaries. Until that design and adopter authorization exist, M0–M4 incident exercises use synthetic data only.

Evidence handling follows minimum-necessary collection and documented chain of custody. Every evidence item records an opaque evidence ID, incident UUID, source, collector, acquisition time in UTC, method/tool and version, original and working-copy cryptographic hashes, storage location, classification, retention/legal-hold state, and every access/copy/transfer. Investigators work from verified copies and preserve originals where feasible. They do not paste sensitive evidence into GitHub issues, ordinary email, chat, Sentry, application logs, screenshots, or the tenant audit stream.

Before production, a separate private incident-evidence bucket or equivalent independent repository is provisioned outside application credentials and ordinary backups. Evidence bundles are encrypted before or at upload with recovery material kept in a restricted 1Password incident item plus an encrypted offline recovery copy, versioned/object-locked where supported, access-logged, and available through a restricted incident-response credential—not the web or Horizon runtime. Its retention is set by the applicable legal/contractual process; closing an incident does not itself delete evidence. The normal database incident register exports a signed, redacted timeline to this store so a compromised application database is not the sole record. M0–M4 exercises retain synthetic, content-free evidence only and do not add this bucket to the existing three-bucket non-production topology.

Communications use a single approved incident narrative with timestamped known facts, unknowns, affected service/Organisation scope, actions taken, user actions if any, contact path, and next-update time. Responders avoid speculation, blame, unsupported attribution, security details that enable exploitation, or disclosure of another Organisation. A multi-tenant incident can have one platform statement plus separate Organisation-specific impact notices. Resend and the application UI may be used only if their integrity and availability are trusted; production requires an independent status/communications channel and an offline encrypted copy of verified primary and backup tenant security contacts. Contact details are encrypted in the application, verified before activation, and reconfirmed at least quarterly.

The platform does not automatically decide that an incident is a legally reportable personal-data breach and does not hard-code a universal notification deadline. For each affected jurisdiction, contract, and Organisation, the privacy/legal owner records the applicable controller/processor roles, what starts any legal clock, calculated deadline and source, affected data/people, risk/harm assessment, advisor consulted, regulator/insurer/law-enforcement obligations, notification decision and rationale, approvals, and actual dispatch time. The system may surface configured deadlines but cannot send regulator, tenant, media, or affected-person notices without human approval.

The platform normally informs the affected tenant security contact; the tenant and its qualified advisers decide communications to clients, supporters, staff, regulators, or other people unless contract or law assigns that duty differently. Direct communication involving service clients is practitioner-reviewed and respects safe-contact instructions: a breach notice must not reveal client participation or use an unsafe channel. Urgent safety protection can precede complete forensic certainty, with uncertainty stated honestly. Legal advice, insurer contact, or law-enforcement involvement does not justify concealing facts required for safe and lawful tenant action.

Recovery is staged and never consists solely of restoring availability. Before reopening affected capabilities, the incident commander records evidence that the attack path is contained; persistence is removed; vulnerable code/configuration is fixed; exposed credentials, sessions, keys and provider tokens are rotated or revoked; dependencies and hosts are verified; known-good backups pass integrity and malware checks; tenant boundaries, audit chains and deletion ledgers reconcile; restored data does not resurrect expired records; paused queues/outbox items are reviewed for unsafe or duplicate effects; and security/tenant smoke tests pass. Recovery proceeds through a limited canary where practical, followed by enhanced monitoring and explicit business/safeguarding approval. Exfiltrated data is never described as “recovered” merely because access was restored.

Every `S1`/`S2` and any cross-tenant incident receives a blameless post-incident review with chronology, root and contributing causes, detection/response gaps, harm and cost where knowable, control effectiveness, what worked, and corrective actions with owner, priority, due date and verification evidence. High-value lessons are applied as soon as safe rather than waiting for closure. Production runs at least two tabletop exercises per year, including one provider/account-compromise scenario and one confidentiality/tenant-isolation scenario; contacts, access, containment commands, evidence capture, communications fallback, restoration gates, and overdue corrective actions are tested. A failed exercise blocks production readiness or creates an explicitly accepted, time-bounded risk owned by the adopter—not an undocumented exception.

### M1 service-delivery state machines

Workflow state, triage priority, risk, confidentiality, and assignment are separate concerns. Status changes occur only through authorized domain actions—not generic record updates—and write an immutable transition containing Organisation, entity, from/to state, actor, effective time, recorded time, and reason where required.

#### Intake request

| From | Allowed next state |
|---|---|
| `draft` | `submitted`, `withdrawn` |
| `submitted` | `under_review`, `withdrawn` |
| `under_review` | `waitlisted`, `accepted`, `redirected`, `declined`, `withdrawn` |
| `waitlisted` | `under_review`, `accepted`, `redirected`, `withdrawn` |
| `accepted`, `redirected`, `declined`, `withdrawn` | None |

Accepting an intake creates exactly one `open` case in the selected program and links it to the intake in the same transaction. Replayed submissions or commands cannot create duplicate cases. Redirect and decline require a reason; redirect may record a consented external referral destination. Triage priority (`routine`, `priority`, `urgent`) and risk flags do not change the workflow state. “Urgent” is a work-prioritization signal, not emergency-dispatch functionality, and the interface states this limitation clearly.

#### Case

| From | Allowed next state |
|---|---|
| `open` | `active`, `on_hold`, `closed`, `cancelled` |
| `active` | `on_hold`, `closed` |
| `on_hold` | `active`, `closed` |
| `closed`, `cancelled` | None |

`open`, `active`, and `on_hold` are non-terminal and count as active cases at `as_of`. A case may be `cancelled` only when created in error or ended before substantive service; otherwise it is `closed`. Returning support creates a new linked case rather than reopening or rewriting a terminal case.

Case closure requires:

- a closure reason and structured case outcome;
- every draft or active goal to reach a terminal state;
- every planned or scheduled service and every scheduled appointment to be completed, cancelled, or marked not delivered/no-show;
- every open task to be completed or cancelled; and
- every non-terminal referral either to reach a terminal outcome or be explicitly carried forward with a recorded reason.

Closure executes atomically and records the checklist result. It does not lower confidentiality or erase assignments, notes, services, referrals, or outcomes.

#### Case assignment

| From | Allowed next state |
|---|---|
| `active` | `ended` |
| `ended` | None |

A case has at most one active primary case-worker assignment and may have active collaborator assignments. An `open` case may remain unassigned in the program work queue; entering `active` or `on_hold` requires exactly one active primary assignment. Transfer ends the existing primary assignment and creates the replacement atomically, preserving history. The replacement worker must be an active Organisation member with the case-worker role, access to the program, and any sensitivity permission required by the case.

#### Goal

| From | Allowed next state |
|---|---|
| `draft` | `active`, `cancelled` |
| `active` | `achieved`, `not_achieved`, `cancelled`, `withdrawn` |
| `achieved`, `not_achieved`, `cancelled`, `withdrawn` | None |

Terminal goals record an effective date and outcome/reason. Corrections append an audited correction rather than returning the goal to an earlier state. Cancelled and withdrawn goals remain excluded from the achievement-rate denominator.

#### Service

| From | Allowed next state |
|---|---|
| `planned` | `scheduled`, `completed`, `cancelled` |
| `scheduled` | `completed`, `cancelled`, `not_delivered` |
| `completed`, `cancelled`, `not_delivered` | None |

Only `completed` services have `delivered_at` and contribute to services-delivered or unique-people-supported metrics. A terminal record is corrected through an append-only correction/reversal event rather than destructive editing.

#### External referral

| From | Allowed next state |
|---|---|
| `draft` | `sent`, `cancelled` |
| `sent` | `acknowledged`, `connected`, `not_connected`, `cancelled` |
| `acknowledged` | `connected`, `not_connected`, `cancelled` |
| `connected`, `not_connected`, `cancelled` | None |

Sending a referral requires destination, purpose, minimum necessary information, and a recorded sharing authority suitable for the synthetic demo, such as service consent. `not_connected` and `cancelled` require a reason. `sent` and `acknowledged` count as pending; only `connected` and `not_connected` enter the referral-completion-rate denominator.

#### Task and appointment

| Entity | From | Allowed next state |
|---|---|---|
| Task | `open` | `completed`, `cancelled` |
| Appointment | `scheduled` | `completed`, `cancelled`, `no_show` |

Overdue is derived from an open task's due date, not stored as a state. Appointment completion may atomically create or link a completed service. Cancellation and no-show require a reason where configured.

#### Notes, corrections, and concurrency

Case notes may be drafted and then finalized. Finalized notes are not overwritten; a correction or addendum links back to the prior note, preserves both versions, and records author and timestamps. Deletion follows the later retention workflow and is not used to correct ordinary mistakes.

All transitions enforce Organisation, role, program, assignment, confidentiality, and Organisation-status rules. Concurrent transitions use optimistic version checks or database locking so two valid requests cannot create an invalid combined result. Effective timestamps are stored in UTC and displayed in the Organisation timezone; `recorded_at` is immutable and distinct from a permitted backdated `occurred_at`. Backdating and correction rules are audited.

### M3 metric registry and reporting rules

M3 uses fixed, code-owned metric definitions rather than a configurable report builder. Every chart and export references a metric code and version.

Reporting conventions:

- use the Organisation's timezone and single configured reporting currency;
- use half-open periods: `start ≤ event < end`;
- calculate current-state metrics at an explicit `as_of` timestamp;
- compare with the immediately preceding equal-length period;
- store money in minor units;
- display `0` for a valid calculation with no events and `N/A` when calculation is not possible;
- show numerator, denominator, and excluded-record count for every rate; and
- display data freshness and metric-definition version on each dashboard/export.

#### Service metrics

| Metric | Definition |
|---|---|
| Requests received | Count of intake requests received during the period |
| Cases opened | Cases whose `opened_at` falls within the period |
| Active cases | Cases open at the report's `as_of` timestamp |
| Cases closed | Cases whose `closed_at` falls within the period |
| Unique people supported | Distinct person Parties receiving at least one completed service during the period |
| Services delivered | Completed service records whose `delivered_at` falls within the period |
| Median time to first service | Median duration from request receipt to first completed service |
| Goal achievement rate | Achieved goals divided by goals reaching achieved/not-achieved terminal outcomes during the period |
| Referral completion rate | External referrals marked connected divided by referrals reaching a terminal outcome during the period |
| Current caseload per worker | Active assigned cases grouped by case worker at `as_of` |

Cancelled or withdrawn goals are excluded from the achievement denominator and counted separately. Pending referrals are excluded from the referral-completion denominator and counted separately.

#### Fundraising and engagement metrics

| Metric | Definition |
|---|---|
| Gross donation value | Sum of successful simulated payments during the period |
| Refunded value | Sum of simulated refunds processed during the period |
| Net donation value | Gross donation value minus refunded value |
| Successful donations | Count of successful payment transactions |
| Unique donors | Distinct person or organisation Parties with a successful donation |
| Average gift | Gross donation value divided by successful donations |
| Active recurring donors | Distinct Parties with an active simulated recurring mandate at `as_of` |
| New supporters | Parties whose first successful donation or valid supporter opt-in occurs during the period |
| Welcome completion rate | Completed welcome journeys divided by eligible welcome enrollments |
| Campaign action rate | Unique delivered recipients completing the campaign's declared action divided by delivered recipients |
| Bounce rate | Bounced simulated deliveries divided by attempted deliveries |
| Unsubscribe rate | Unique campaign-attributed unsubscribes divided by delivered recipients |

Provider fees and accounting-recognised revenue are deferred until production integrations exist.

#### Impact presentation

| Layer | M3 measures |
|---|---|
| Inputs | Net donation value |
| Activities | Services delivered and referrals made |
| Outputs | Unique people supported and cases closed |
| Outcomes | Goals achieved and successful referral connections |

The interface must state that displaying these layers together does not prove that donations caused individual outcomes. Donation-to-client drill-down and combined donor/case dimensions are prohibited.

#### Data-quality measures

- closed cases missing a terminal outcome;
- completed services missing a service category;
- goals overdue without an updated status;
- supporter records with unknown consent status;
- potential duplicate Parties;
- dashboard data freshness; and
- records excluded from each rate.

Data-quality measures appear beside the relevant operational dashboards rather than only in an administrator area.

#### Dimensions and privacy thresholds

Allowed service dimensions are date, program, broad service area, case status, service category, and outcome category. Allowed fundraising dimensions are date, campaign, fund/designation, one-off versus recurring, person versus organisation donor, and acquisition source. Precise client addresses are not reporting dimensions.

The spec-build minimum cohort is five:

- service counts from one to four display as `<5`;
- rates are suppressed if numerator or denominator is below five;
- filter combinations that would expose a suppressed cohort are rejected;
- executive viewers cannot drill down to Parties or cases;
- program-manager drill-down remains limited by operational permissions;
- highly restricted detail still requires sensitive-data permission; and
- exports preserve the same suppression and dimension rules.

The threshold is a conservative product assumption, not a legal-compliance claim, and must be validated with a real adopter and target jurisdiction.

Each metric definition records its code, name, version, category, description, unit, formula, event date, included/excluded records, allowed dimensions, minimum cohort, and last-calculated timestamp. The synthetic dataset uses a fixed reporting date and known expected totals for every M3 metric.

### M2 payment and communication simulation

M2 uses deterministic fake provider adapters behind the same internal interfaces expected for production providers. Simulated provider identifiers are prefixed with `sim_`, all screens and artifacts identify the transaction as a simulation, and no card number, bank credential, real email address, real telephone number, or external recipient is collected or contacted.

#### Payment domain

A **Donation** records gift intent and attribution. A **Payment attempt** records one attempt to collect money. A **Recurring mandate** records the simulated recurring instruction. A **Refund** is an immutable adjustment linked to a successful payment. A **Receipt** is generated only from settled simulated value and is clearly marked “Demo—Not a tax receipt.”

Payment-attempt states:

| From | Allowed next state |
|---|---|
| `created` | `pending`, `cancelled` |
| `pending` | `succeeded`, `failed`, `cancelled` |
| `succeeded` | `partially_refunded`, `refunded` |
| `partially_refunded` | `partially_refunded`, `refunded` |
| `failed`, `cancelled`, `refunded` | None |

Rules:

- a settled `succeeded` attempt cannot return to collection states but may receive refund adjustments; `failed`, `cancelled`, and `refunded` are terminal; a collection retry creates a new attempt rather than rewriting history;
- `partially_refunded` may receive additional refunds until the original settled amount is reached;
- total refunds cannot exceed the successful payment amount;
- amount and currency are immutable after an attempt enters `pending`;
- only a `succeeded` attempt creates a receipt and fundraising value;
- refunds reduce net donation value on the refund event date but do not rewrite the original gross donation event;
- each transition records an immutable provider event and timeline event;
- duplicate provider events are ignored through an Organisation-scoped idempotency key; and
- invalid or out-of-order transitions fail without partially mutating donation, receipt, or dashboard state.

Recurring-mandate states:

| From | Allowed next state |
|---|---|
| `pending` | `active`, `cancelled` |
| `active` | `payment_failed`, `cancelled` |
| `payment_failed` | `active`, `cancelled` |
| `cancelled` | None |

Each recurring installment creates a separate payment attempt and, when successful, a separate demo receipt. Recovery from `payment_failed` requires a new successful attempt. Cancellation prevents future attempts but does not alter settled history.

The deterministic simulator supports `success`, `decline`, `timeout_then_success`, `partial_refund`, and `recurring_failure` scenarios. Scenario selection is available only through demo fixtures or an authenticated demo control—not through card-like fields on the public donation form.

#### Communication domain

A **Campaign** defines purpose, audience, template version, declared action, and approval. A **Message** represents one recipient. A **Delivery event** records simulated transport behavior. A **Journey enrollment** controls the welcome journey.

Campaign states:

| From | Allowed next state |
|---|---|
| `draft` | `approved`, `cancelled` |
| `approved` | `queued`, `cancelled` |
| `queued` | `processing`, `cancelled` |
| `processing` | `completed`, `partially_failed`, `failed`, `cancelled` |
| `completed`, `partially_failed`, `failed`, `cancelled` | None |

Per-recipient message states:

| From | Allowed next state |
|---|---|
| `pending` | `suppressed`, `queued` |
| `queued` | `suppressed`, `delivered`, `bounced`, `failed` |
| `suppressed`, `delivered`, `bounced`, `failed` | None; retries create delivery attempts without changing Message identity |

Opens, declared actions, and unsubscribes are append-only engagement events rather than message states. The demo reports declared actions rather than treating opens as a reliable success measure.

Journey-enrollment states:

| From | Allowed next state |
|---|---|
| `eligible` | `enrolled`, `suppressed` |
| `enrolled` | `active`, `cancelled`, `suppressed` |
| `active` | `completed`, `cancelled`, `suppressed` |
| `completed`, `cancelled`, `suppressed` | None |

Communication rules:

- approval freezes the template version, audience definition, purpose, and declared action used for that campaign run;
- recipient membership is materialized at queue time for reproducibility;
- consent, safe-contact restrictions, Organisation status, global suppression, channel suppression, and frequency caps are re-evaluated immediately before each message is queued;
- a suppressed recipient produces a reason-coded event but no rendered outbound payload;
- unsubscribing updates consent/suppression before any remaining queued message can be processed;
- retrying a failed delivery creates another delivery attempt without duplicating the Message;
- cancellation stops unprocessed messages but preserves completed delivery history;
- templates, previews, local mail-viewer content, notifications, jobs, and logs must not contain case status or service data; and
- all processing restores and verifies the immutable Organisation context.

#### Simulation architecture and controls

- `PaymentGateway` and `MessageTransport` interfaces isolate domain workflows from fake and future real providers.
- Domain changes and their queued work use a transactional outbox or equivalent atomic pattern so committed state cannot silently lose its follow-up work.
- Provider-event and command idempotency keys are unique within an Organisation/provider combination.
- The simulator uses the fixed demo clock and deterministic scenario inputs so receipts, timelines, and dashboards have reproducible totals.
- A local mail viewer may display messages addressed only to synthetic recipients. Environment-level guards block external transports and non-allowlisted destinations throughout M0–M3.
- Resend is reserved for platform-generated account and security mail such as verification, password reset, Organisation Invitations, and operational alerts. Tenant campaign, donation, journey, and bulk-message workflows continue to use `MessageTransport` simulation in M0–M3 and cannot reach Laravel's default mailer.
- Demo controls are authenticated, Organisation-scoped, audited, visibly marked, and unavailable when the environment is configured for production.

### Synthetic dataset and demo reset

The synthetic dataset is a versioned product contract used for demonstrations, authorization tests, metric reconciliation, performance checks, and deterministic resets. It must not be generated from, copied from, or presented as LocalKind or any real person's data.

#### Fixed context

- `DEMO_SEED_VERSION` identifies the dataset structure and expected results.
- `DEMO_AS_OF` is fixed at `2026-06-30T23:59:59` in each Organisation's timezone; seed generation never depends on the real current time.
- HarbourKind uses `Africa/Johannesburg` and ZAR; NeighbourLink uses `Europe/London` and GBP to exercise tenant-local timezone and currency behavior.
- Reporting fixtures include records immediately before, exactly on, and immediately after period boundaries, plus daylight-saving boundaries for NeighbourLink.
- Generated identifiers, dates, state transitions, narratives, and expected totals derive from a fixed pseudorandom seed and explicit scenario builders.

#### Dataset contract

| Entity | HarbourKind | NeighbourLink |
|---|---:|---:|
| Staff Users | 9, covering every MVP role and permission boundary | 3, including one User shared with HarbourKind |
| Programs | 3 predefined service programs | 1 minimal isolation-test program |
| Parties | 2,000: 1,750 people, 150 organisations, 100 households | 30 |
| Intake requests | 300 | 8 |
| Cases | 250: 20 open, 70 active, 10 on hold, 140 closed, 10 cancelled | 6 |
| Highly restricted cases | 25 | 2 |
| Goals | 500: 300 achieved, 100 not achieved, 50 active, 30 cancelled, 20 withdrawn | 10 |
| Completed services | 1,200 | 20 |
| External referrals | 300: 160 connected, 60 not connected, 80 pending | 8 |
| Payment attempts | 1,000: 850 succeeded, 80 failed, 20 cancelled, 30 partially refunded, 20 refunded | 12 |
| Active/failed/cancelled recurring mandates | 90 / 20 / 10 | 3 / 1 / 1 |
| Campaigns | 6 | 1 |
| Per-recipient Messages | At least 2,500 | 25 |
| Engagement and delivery events | At least 10,000 | At least 100 |

Current payment-attempt states sum to the listed attempt total; refunds and prior transitions exist as separate immutable events. Exact report-window totals are generated into a machine-readable expected-metrics manifest and asserted in tests rather than inferred from the current-state counts above.

#### Named scenario fixtures

Stable scenario identifiers—not database auto-increment values—anchor the four showcases:

1. a housing-support request progresses through triage, assignment, service, referral, goal outcome, and closure;
2. a synthetic donor completes a successful simulated gift, receives a demo receipt, and completes the welcome journey;
3. an executive views reconciled input/activity/output/outcome metrics with a deliberately suppressed small cohort; and
4. one synthetic person is both a HarbourKind supporter and client, while an unrelated NeighbourLink Party deliberately shares the same normalized email to prove supporter projection and cross-tenant separation.

Additional fixtures cover an unassigned case, cross-program access attempt, highly restricted case with and without sensitive-data permission, safe-contact banner, restricted document, owner without operational access, failed and retried payment, partial/full refund, failed recurring installment and recovery, suppressed recipient, unsubscribe during queued processing, duplicate provider event, and out-of-order event.

#### Synthetic-data safety

- every environment displaying seed data shows a persistent “Synthetic demo data” banner;
- receipts, exports, downloads, emails, and screenshots carry a demo marker where practical;
- email addresses use reserved example domains, telephone numbers are intentionally non-routable, and addresses are clearly fictional;
- narrative templates are concise, non-graphic, and independently authored rather than copied from real client stories;
- coincidental name similarity never includes matching real contact details or biography; and
- external payment and messaging transports remain technically blocked, not merely disabled in the interface.

#### Reset model

The application supports an Organisation-scoped demo reset and an optional scheduled full-demo reset. Reset behavior:

1. require an authenticated Organisation owner/administrator, recent authentication, explicit Organisation slug confirmation, and `local` or `demo` environment;
2. place the Organisation into a temporary maintenance lock and stop new writes;
3. increment an immutable `demo_generation` value so jobs from the prior dataset fail closed;
4. cancel or invalidate outstanding Organisation jobs, signed links, caches, search documents, and simulated provider events;
5. delete and recreate only that Organisation's tenant-owned synthetic records in dependency-safe transactions/batches;
6. reseed from the pinned seed version and clock;
7. rebuild search and derived metrics;
8. reconcile all expected entity and metric totals before releasing the lock; and
9. write a tenant-level audit event without retaining deleted synthetic case content.

Reset routes and commands are not registered when the environment is configured for production. A reset failure leaves the Organisation locked, reports a recoverable error, and never exposes a partially seeded tenant. The reset mechanism must never truncate shared global or cross-Organisation tables.

#### Hosted sandbox lifecycle

| From | Allowed next state |
|---|---|
| `provisioning` | `ready`, `failed` |
| `ready` | `active`, `expired`, `failed` |
| `active` | `expired`, `failed` |
| `expired` | `purging` |
| `purging` | `purged`, `failed` |
| `failed` | `purging` or an idempotent retry of the failed operation |
| `purged` | None |

Provisioning and purge are idempotent. A sandbox becomes `ready` only after both Organisations, synthetic Users, search documents, and expected metrics reconcile successfully. `active` begins on first token use. Expiry or manual revocation terminates sessions and blocks further mutations immediately. A failed provisioning or purge remains inaccessible and emits an operational alert without exposing synthetic case content.

## 11. Non-functional requirements

### Technical architecture decision

Laravel Forge, PostgreSQL 18, and Laravel Horizon are fixed project constraints. The remaining entries are selected architecture decisions for the spec build:

| Concern | Decision |
|---|---|
| Runtime | Laravel 13 on PHP 8.4 |
| Starter kit | Official React starter kit with Laravel's built-in authentication and Organisations enabled |
| Staff/public UI | Inertia 3, React 19, strict TypeScript, Tailwind CSS 4, and shadcn/ui |
| Asset build | Vite on pinned Node.js 24 LTS |
| Database | PostgreSQL 18 using the shared-schema tenancy model |
| Data encryption | Verified host volume/snapshot encryption where available, encrypted S3 objects, dedicated Laravel application-data encryption, and Organisation-bound HMAC blind indexes for exact contact matching; provider-volume verification is a production gate |
| Cache, locks, rate limiting | Redis 7 with environment- and Organisation-prefixed keys |
| Sessions | Database-backed Laravel sessions for queryable revocation |
| Queues | Laravel Horizon over a non-clustered Redis 7-compatible queue connection |
| Search | Laravel Scout database engine backed by PostgreSQL full-text search |
| Audit evidence | Append-only PostgreSQL tenant/security event streams with restricted database privileges and chained offsite digest manifests |
| Incident response | Restricted platform incident register, guarded containment commands, independent production evidence store, and adopter/jurisdiction-approved communications workflow |
| Licensing and governance | `AGPL-3.0-only` software, CC BY-SA 4.0 documentation/non-brand assets, CC0 synthetic datasets, DCO 1.1 contributions, public governance, and separate narrow trademark policy |
| Transactional email | Resend through the official `resend/resend-laravel` package, configured as Laravel's `resend` mailer |
| Files | One private S3-compatible application-object bucket per hosted environment; local fake/storage adapter in tests |
| File scanning | Local quarantine outside the web root plus ClamAV `clamd` over a private Unix socket; only verified-clean objects reach S3 |
| Backups | A separate private S3-compatible Restic backup bucket for non-production; PostgreSQL is captured through verified logical dumps, not by copying its live data directory |
| Authentication | Invite-only Laravel/Fortify session authentication with mandatory TOTP MFA for staff; no WorkOS dependency in M0–M3 |
| Testing | Pest for unit, feature, console, authorization, and browser flows; Vitest/Testing Library for isolated React components |
| Code quality | Laravel Pint, Larastan/PHPStan, strict TypeScript, ESLint, and production dependency auditing |
| Source and CI/CD | Public AGPL GitHub repository with GitHub Actions for required checks, licence/DCO enforcement, reproducible source releases, and gated Forge deployments |
| Human secrets and recovery | 1Password as the authoritative project vault; Bitwarden as a deliberately limited break-glass store |
| Error and performance monitoring | Sentry through the official `sentry/sentry-laravel` SDK, using a Laravel-13-compatible 4.x release |
| Environment topology | Local/test run off-host; staging and demo are isolated sites on one non-production Forge server; any future production environment uses a separate server and data plane |
| Spec hosting | One Laravel Forge-managed non-production Linux server with Nginx/PHP-FPM, PostgreSQL 18, Redis, separate staging/demo sites, and three private S3-compatible buckets |
| Tenant DNS/edge/TLS | Cloudflare authoritative DNS and proxy for a wildcard `*.communitykind.example` record; Full (strict) TLS to the Forge origin for M0–M3 |
| Custom domains | Cloudflare for SaaS custom hostnames in M5, subject to a plan/cost and origin-SNI validation gate before implementation |

Laravel dependencies use compatible constraints such as `^13.0`, while lockfiles, PHP, Node, database, and service versions make builds reproducible. Dependency updates are automated but merged only after the complete test suite passes.

#### Frontend and routing

- Laravel routes, middleware, Form Requests, policies, and domain actions remain authoritative; React does not duplicate authorization or business-state decisions.
- Inertia supplies typed page props from explicit resource/view models. Sensitive models are never serialized directly to the client.
- Server-side pagination, filtering, sorting, and allowlisted query parameters are used for dense operational tables.
- Charts are paired with accessible summaries/data tables and do not replace the source metric definition.
- Client-side validation improves usability, while Laravel validation remains authoritative.
- Inertia server-side rendering, a separate public SPA, and a general public API are not required for M0–M3.
- The simple platform marketing surface may use server-rendered Blade; authenticated and tenant workflow surfaces use Inertia/React.
- The Laravel Starter Kit Teams feature's automatic personal-Team behavior is replaced by the approved Organisation provisioning lifecycle and Organisation terminology throughout first-party code, schema, routes, tests, and documentation.
- Adapted Membership and current-Organisation behavior is retained only where consistent with the host-based public tenant resolver and the application's stricter policies.

React/Inertia is preferred over Livewire for the spec because the product contains dense tables, multi-step workflows, dashboard interactions, typed role-preview tooling, and reusable client components. Livewire remains a viable alternative but is not part of this implementation to avoid maintaining two interactive UI stacks.

#### Authentication, MFA and account recovery

- Public staff registration is disabled. Staff enter through a single-use Organisation Invitation bound to a normalized email address, Organisation, explicitly selected or newly created person Party, proposed scoped Role Assignments, inviter, and 72-hour expiry. Invitations are stored as hashes, become invalid after acceptance/revocation, never authenticate a different email address, and create Membership only on acceptance.
- Laravel Fortify provides password reset, email verification, password confirmation, login throttling, and TOTP two-factor authentication with both `confirm` and `confirmPassword` enabled.
- Any Installation-wide User with an active staff membership in any Organisation, or with Installation Operator authority, must use TOTP MFA for every login. MFA belongs to the User rather than an Organisation; adding or removing memberships cannot create separate or conflicting MFA states.
- After accepting an invitation and verifying the email address, a new staff User may access only MFA enrollment, recovery-code acknowledgement, profile security, and logout until TOTP enrollment is confirmed. Operational Organisation routes remain blocked.
- TOTP secrets are encrypted at rest. Recovery codes are shown once, stored using Fortify's protected representation, individually single-use, and replaced as a set when regenerated. Regeneration requires recent password confirmation and a valid current TOTP code.
- Passwords have a minimum length of 14 characters, permit password-manager-generated values and paste, and are checked against Laravel's configured compromised-password rule when the required external service is available without logging or retaining the password. Arbitrary periodic password changes and composition tricks are not required.
- Login, password-reset, invitation, email-verification, TOTP-challenge, and recovery endpoints use neutral responses and Redis-backed rate limits. The initial login/TOTP limit is five failed attempts per minute per normalized account/IP key, supplemented by an IP-level Cloudflare limit and reviewed against observed abuse.
- Password-reset links are single-use and expire after 30 minutes. Successful password, email, or MFA recovery revokes every existing session, rotates relevant remember/session tokens, emits an audit event, and sends a security notification through Resend. Persistent “remember me” login is disabled for staff.
- Staff sessions use the database session store, a host-only Secure/HttpOnly/SameSite cookie on the central staff host, a two-hour inactivity limit, and a 12-hour absolute limit. Membership, Organisation Status, Membership Hold, Role Assignment, Program, Case Assignment, Restricted Access Grant, Access Hold, and Hosted Access authorization is re-evaluated on every request rather than frozen into the session.
- Exports, membership/role changes, ownership transfer, Organisation deletion, MFA/recovery changes, custom-domain changes, and any future break-glass operation require step-up authentication: recent password confirmation and a fresh MFA challenge no more than 15 minutes old.
- An Organisation administrator may resend or revoke an Organisation Invitation and apply a reasoned Membership Hold, but cannot change an Installation-wide User's password, email, MFA, recovery codes, or relationships in other Organisations or Billing Accounts.
- A User who loses both the authenticator and recovery codes cannot be recovered by email alone. Recovery is an audited platform-level process requiring documented out-of-band identity verification, session revocation, a short-lived one-time MFA-reset grant, and security notification. The spec exposes this only for synthetic accounts through a guarded operator command; a staffed support and escalation process is mandatory before real adoption.
- The demo bootstrap token and synthetic role selector are a separate, environment-gated authentication mechanism. They cannot authenticate real Users or non-demo Organisations and do not weaken staff MFA requirements.
- Passkeys, social login, SSO, SCIM, and tenant-specific identity providers are deferred to M5 evaluation. Supporter-only accounts introduced in M4 may use a proportionate policy, but a User who also has any staff membership remains subject to staff MFA globally.

#### Application architecture

- Use a modular monolith organized by bounded capabilities: Tenancy, Identity, Service Delivery, Supporters, Giving, Communications, Reporting, Demo, Audit, and Platform Operations.
- Controllers and Inertia actions remain thin; state transitions live in typed application/domain action classes injected through Laravel's service container.
- PHP backed enums define workflow states, confidentiality, Organisation status, roles, and transition reasons.
- Database transactions protect multi-record invariants; domain events and the transactional outbox trigger asynchronous work after commit.
- Eloquent models contain relationships, casts, and local invariants but not cross-capability orchestration.
- API Resources or dedicated view models expose explicit policy-approved fields to Inertia and any later API.
- Avoid a repository abstraction over every Eloquent model; introduce query/action services where tenant scoping, reporting, authorization projection, or orchestration requires them.
- No microservices, event-sourcing framework, GraphQL layer, or separate frontend deployment is introduced for M0–M3.

#### Queue and cache topology

Horizon manages four Redis queues:

- `critical`: tenant lifecycle invalidation, security-sensitive revocation, and outbox dispatch;
- `security`: isolated document-malware scanning with bounded concurrency, timeouts, retries, and operator alerts;
- `default`: simulated payment/message processing, search synchronization, and ordinary asynchronous work; and
- `bulk`: sandbox provisioning/reset, metric rebuilds, exports, and batch operations.

Queue routing, retry, timeout, uniqueness, and backoff are defined centrally. Jobs contain identifiers and Organisation/generation context rather than serialized sensitive payloads, dispatch after commit, and are idempotent. The Horizon dashboard is restricted to authorized platform operations and unavailable through tenant hosts.

Redis stores cache values, locks, rate-limit counters, and Horizon/queue data but not authoritative domain state. Cache keys include environment, Organisation, resource, permission/role projection where relevant, and version. Sensitive case narratives are not cached unless a narrowly justified encrypted design is approved later.

Horizon requires a non-clustered Redis-compatible deployment for this spec architecture. Redis Cluster and queue sharding are deferred until scale evidence requires them.

#### Search

Laravel Scout's database engine uses PostgreSQL full-text and ordinary indexed constraints, avoiding a separate search service. Search queries always include Organisation and authorization projections. General contact search excludes service/client-only fields; service search excludes narratives and highly restricted content as already specified. Names and approved nonsensitive fields support ordinary search; email and telephone searches use the separate exact-match blind-index path and do not expose those digests to Scout.

Meilisearch, Typesense, Algolia, semantic/vector search, and cross-tenant search are out of scope. A dedicated engine may be evaluated later if measured relevance, typo tolerance, or scale requirements exceed the database engine.

#### Files and local development

- Production-like environments use private S3-compatible storage with application-streamed authorized downloads; tests use Laravel's fake storage.
- Local development uses a documented Docker Compose/Sail-compatible setup for PHP, PostgreSQL, Redis, object storage, ClamAV, and a local mail viewer.
- Local wildcard tenant routing uses `*.communitykind.localhost` or an equivalent documented loopback domain without editing production DNS.
- A single bootstrap command installs dependencies, creates configuration, migrates, seeds the pinned scenarios, builds assets, and starts required services with a target fresh-setup time of 15 minutes.

#### Deployment and environments

- `local` runs on the developer workstation and `test` runs in GitHub Actions. Neither depends on hosted services or credentials.
- One non-production Forge server hosts two separate Laravel sites: access-controlled `staging` and publicly demonstrable `demo`. Both contain synthetic data only and are treated as a shared failure and capacity boundary, not as security-equivalent to separate servers.
- Staging and demo use different Laravel `APP_KEY` values, environment files, site and local-quarantine directories, Unix/process configuration, databases and least-privilege database roles, session cookies, Redis logical databases/prefixes, Horizon prefixes and daemons, queue names, application S3 buckets and credentials, Resend keys/sender controls, Sentry environment tags, domains, and backup tags.
- The non-production S3 topology provisions three private buckets: staging application objects, demo application objects, and a non-production Restic repository. Staging credentials cannot access demo objects, demo credentials cannot access staging objects, and neither application credential can access the backup bucket.
- Staging uses `*.staging.communitykind.example` plus its central staff host and is protected from public discovery and unauthorised access. Demo uses the canonical spec hosts and exposes only disposable synthetic sandboxes.
- A production environment is not provisioned during M0–M4. If M5 real-adopter validation authorizes production, it must use a separate Forge server or provider project, PostgreSQL data plane, Redis service, application, backup and restricted incident-evidence buckets, credentials, encryption keys, Resend key, Sentry environment, and backup/recovery exercise. Production data is never copied into staging, demo, local, tests, fixtures, or support tooling.
- Destructive synthetic reset and role-selection capabilities are registered only in `local` and `demo`; staging may reseed through a guarded deployment/maintenance command, and production contains none of these routes or commands.
- Forge configures each site's wildcard Nginx host and origin certificate, deployment script, scheduler, Horizon supervisors, private quarantine directory, ClamAV/FreshClam services, health checks, and zero-downtime release steps. Cloudflare provides authoritative DNS, proxied public TLS, and the edge connection to that origin.
- Forge quick deployment is disabled. GitHub Actions triggers the current supported Forge deployment mechanism only after the approved commit passes every required check, then waits for and verifies the deployment result and deployed commit SHA.
- Database migrations run once per release before traffic reaches code that requires the new schema; destructive changes use expand/migrate/contract sequencing.
- The web runtime uses PHP-FPM. Laravel Octane is deferred because it is unnecessary for the expected load and would increase the importance of proving that request-scoped tenant state never leaks between long-lived requests.
- Restic, backup scheduling, restore tests, log retention, security monitoring, and incident runbooks must meet the non-functional requirements before production adoption. Forge's paid backup service and `spatie/laravel-backup` are not dependencies.

This hosting choice is optimized for documented wildcard-domain control during the spec. The application remains twelve-factor and S3/Redis/PostgreSQL portable so a later move to Laravel Cloud, Vapor, containers, or another managed platform does not change the domain model.

#### GitHub workflow and release controls

- Pull requests into `main` run a GitHub Actions CI workflow using PHP 8.4, Node.js 24, PostgreSQL 18, and Redis 7. Required jobs cover Composer validation/install, migration from an empty database, Pint, Larastan/PHPStan, Pest, `npm ci`, ESLint, strict TypeScript, Vitest, the production asset build, and dependency audits.
- CI verifies human commit DCO sign-offs, repository licence/notice presence, generated dependency attributions, permitted production dependency licences, the deployed Source/licence link, and absence of committed secrets or prohibited real/sensitive fixtures. Bot-generated dependency commits follow a documented provenance exception rather than impersonating a human sign-off.
- Tenant-isolation, authorization, state-transition, queue, and metric-reconciliation tests are required on every pull request. Targeted browser smoke tests run on pull requests that affect critical workflows and on every `main` build.
- `main` is protected from direct pushes and merging requires current CI checks. Dependency-update pull requests are created automatically but never auto-merge without the same checks.
- A successful `main` build deploys automatically to staging. Promotion of the exact verified commit SHA to the public demo environment is a separate manual workflow after staging smoke checks pass. Production deployment, if introduced, is another manual workflow tied to a protected GitHub environment when the account plan supports approvals; otherwise it requires a documented manual confirmation step and protected production secret.
- Deployment concurrency allows only one deployment per environment. A newer queued deployment supersedes an older queued run but does not interrupt an in-progress database migration.
- The deploy job holds only the scoped Forge deployment credential, Sentry release/source-map credential, and environment identifiers it needs. Application runtime secrets remain on Forge and are not copied into GitHub Actions.
- Workflow-level `GITHUB_TOKEN` permissions default to `contents: read`; additional permissions are granted per job. Pull-request workflows do not expose deployment or environment secrets, and `pull_request_target` is not used to execute untrusted code.
- Third-party actions are allowlisted and pinned to immutable commit SHAs. Dependabot or Renovate proposes action updates for review.
- A successful deployment records the Git commit SHA in the application, creates the matching Sentry release/deploy, uploads production source maps, runs health and tenant-resolution smoke checks, and gracefully terminates Horizon so Forge's supervisor restarts workers on the new release.
- Each tagged release publishes checksummed source and build-provenance archives, an SBOM and third-party attribution report. Hosted interfaces resolve their Source/licence link to the exact deployed tag or commit, not merely the repository's moving default branch.
- A failed migration, health check, commit verification, or Sentry-independent application smoke test fails the deployment and alerts the operator. Roll-forward is the default recovery method; database rollback is used only when the migration explicitly supports it and data safety has been assessed.
- Deployment smoke checks assert that staging and demo database names, Redis databases/prefixes, Horizon prefixes, cookie names/domains, bucket names, local-quarantine paths, and `APP_KEY` fingerprints do not overlap. A mismatch fails closed before migrations or workers start.

#### Secrets and recovery custody

- A dedicated 1Password project vault is the authoritative human-readable inventory for runtime, deployment, provider, recovery, and MFA-recovery credentials. Each item identifies its environment, purpose, scope, owner, storage locations, last review, and revocation procedure.
- Bitwarden is not a synchronized mirror of the project vault. Its break-glass collection contains only the minimum material required to regain control after loss of 1Password or the Forge server: the Laravel `APP_KEY`, application-data and contact-index key rings, Restic repository password and location, essential account-recovery material, and the recovery runbook. Ordinary API tokens and application credentials are excluded.
- The 1Password and Bitwarden accounts use different strong account passwords and independent MFA/recovery material. Recovery information for either vault is also retained offline in a physically protected form so neither online vault is the sole path back into the other.
- Any change to `APP_KEY`, either application key ring, the Restic repository password, backup location, or account-recovery material is incomplete until the authoritative 1Password item, limited Bitwarden break-glass item, offline recovery material, and recovery checklist agree. This small duplicated set is reviewed quarterly.
- Forge holds application runtime secrets; GitHub holds only deployment-time secrets. Neither is treated as the recovery source of truth, and GitHub never receives database, Laravel `APP_KEY`, application-data/contact-index keys, Resend, Restic, or application-bucket credentials.
- 1Password CLI may inject local-development secrets without committing plaintext `.env` files. Hosted runtime and deployment availability must not depend on a live 1Password API or CLI session; Forge and GitHub receive their required values through an explicit provisioning/rotation procedure.
- No secret value appears in Git, issue trackers, pull requests, fixtures, screenshots, logs, Sentry events, shell history, or documentation. A committed `.env.example` contains names and safe placeholders only.
- Provider credentials are unique per environment and least-privileged. Rotation is mandatory after suspected disclosure, access changes, or provider warnings; quarterly review removes unused credentials and verifies the documented recovery path.
- The audit-manifest signing/keyed-digest secret is held by the restricted backup/integrity process and recovered through 1Password; it is not available to the web or Horizon runtime. Rotation starts a new explicitly linked signing epoch rather than invalidating prior manifests.

#### Cloudflare edge and tenant domains

- A proxied wildcard DNS record routes default tenant hosts to the Forge origin. Cloudflare SSL/TLS mode is Full (strict), and the Forge origin presents an unexpired matching wildcard certificate issued by a publicly trusted CA or Cloudflare Origin CA.
- Cloudflare API automation uses scoped tokens limited to the required zone and DNS/certificate operations; the Global API Key is not used.
- Cloudflare cache rules bypass authenticated, personalized, signed-download, and tenant HTML responses. Only immutable public assets are cacheable by default, preventing one tenant's response from being served on another hostname.
- Laravel trusts Cloudflare forwarding headers only when the connection originates from Cloudflare's published proxy ranges. Host validation and tenant resolution remain application controls; Cloudflare routing is not authorization.
- WAF and edge rate limits supplement Laravel throttles but do not replace per-Organisation limits, abuse controls, policies, or audit records.
- M5 uses Cloudflare for SaaS with a dedicated proxied fallback origin and CNAME target. A custom domain becomes `active` only after both hostname ownership and certificate status are active; failure or removal leaves the canonical Organisation subdomain available.
- Before M5 implementation, a technical spike must confirm the then-current Cloudflare plan/cost, CNAME versus apex support, certificate-validation flow, forwarded Host behavior, and origin SNI/certificate compatibility with the Forge Nginx origin. Unsupported apex onboarding is rejected with guidance to use a subdomain rather than silently weakening TLS.

#### Backup topology

- A Forge-scheduled, least-privileged script creates a PostgreSQL 18 custom-format logical dump with `pg_dump`, checks that `pg_restore` can read its catalogue, and only then includes the dump in a Restic snapshot sent to the provisioned offsite S3 backup bucket.
- Restic does not copy PostgreSQL's live data directory. A failed dump, failed snapshot, or missed schedule raises an operational alert and does not prune the last known-good snapshot.
- Provision three private non-production S3-compatible buckets: one each for staging and demo application objects, plus one for the Restic repository. They use separate least-privilege credentials and must not rely on a shared bucket or prefix as their isolation boundary. Prefer a separate provider account or provider for the backup bucket when affordable so one account failure does not remove both primary copies and backups.
- The Restic repository's encryption password and recovery instructions are held outside the application server; losing the only copy of the password must not make recovery impossible.
- Source code and build artifacts are recovered from version control and the release pipeline. Non-secret operational configuration is backed up where needed; secrets are recovered from the separate secrets/password-management process.
- Each application-object bucket enables encryption, versioning, blocked public access, and lifecycle rules. A scheduled, independently testable export includes staging and demo objects in separately tagged Restic recovery sets stored in the backup bucket; a database-only Restic snapshot is not considered a backup of uploaded files.
- The initial schedule is daily, matching the 24-hour RPO. Retention starts at 7 daily, 4 weekly, and 12 monthly snapshots and may be revised after storage-cost and business-impact review.
- Automation runs `restic check` regularly, performs a representative restore into an isolated environment at least quarterly, and performs a complete recovery exercise at least twice yearly. Restore exercises verify database integrity, tenant/file references, application boot, and documented RTO—not merely snapshot existence.

### Security and privacy

- Treat `organisation_id` as an access-control boundary, not merely a UI filter. Enforce it through tenant-aware route binding, policies, service/query layers, database constraints where practical, and feature tests.
- Include `organisation_id` in tenant-owned unique constraints, cache keys, object-storage paths, search documents, queued-job payloads, exports, audit events, and analytics facts.
- Validate allowed hosts before establishing tenant context; scope cookies appropriately and prevent caches/CDNs from serving one host's tenant response to another.
- Encrypt data in transit, on provider volumes/snapshots and S3-compatible storage, in Restic backups, and at application level for the classified fields above; use the documented independent key rings, recovery copies, and tested rotation procedures.
- Require MFA for staff and stronger step-up authentication for exports or elevated access.
- Apply least privilege, session expiry, rate limiting, secure backups, and environment separation.
- Maintain append-only, tamper-evident security audit records and alerts for integrity failures and bulk access/export anomalies; do not claim absolute immutability against infrastructure administrators.
- Model production-grade controls in the architecture and interface. Threat modelling, privacy impact assessment, penetration testing, incident-response planning, and jurisdiction-specific legal review are mandatory before any use with real data.
- Never store raw payment-card details; use provider-hosted or tokenised payment flows.

### Reliability and recovery

- Target 99.9% monthly availability, excluding announced maintenance.
- Define an initial recovery point objective of 24 hours and recovery time objective of 8 hours, then tighten after business-impact analysis.
- Run representative quarterly restores and a complete recovery exercise at least twice yearly; monitor backup, integration, retry, and dead-letter failures.

### Performance and scale

- Common staff pages should load within 2 seconds at the 95th percentile under the seeded demo workload.
- Search should return within 2 seconds for the expected dataset.
- Demonstrate M0–M3 with at least 2,000 synthetic Parties, 250 cases, 1,000 payment attempts, and 10,000 engagement events in the primary Organisation. Add at least 100 volunteer records when M4 is implemented. Document a plausible scale path rather than prematurely load-testing enterprise volumes.

### Accessibility and usability

- Meet WCAG 2.2 AA for staff and public experiences.
- Support keyboard operation, screen readers, clear error recovery, plain language, and responsive layouts.
- Preserve draft form data and minimise intake steps for crisis or outreach contexts.

### Observability

- Sentry captures unhandled server exceptions, failed queued jobs, sampled performance traces, frontend errors, and release/deploy identifiers. Forge health checks remain the independent availability signal; Horizon remains authoritative for queue operations.
- Redaction-safe operational alerts cover ClamAV daemon/engine health, signatures older than 24 hours, FreshClam failures, growing `security` queue age, quarantine capacity, expired scans, and unusual rejection-rate changes; alert payloads contain no names, checksums, scanner signature names, or document content.
- Sentry Cron Monitoring records check-ins for the Laravel scheduler and critical backup/maintenance schedules where the account plan supports it. A simple external heartbeat or Forge health check is the fallback and must not make backup success depend on Sentry.
- `send_default_pii` is disabled. Before-send scrubbing removes request and response bodies, cookies, authorization headers, form values, email addresses, phone numbers, names, filenames, case narratives, safe-contact data, payment metadata, and signed URLs.
- Telemetry may include environment, release SHA, route name, exception class, job class, duration, response status, and a non-identifying Organisation UUID/tag. It must not include tenant name, subdomain, custom domain, Party/User identifiers, raw SQL bindings, or application payloads.
- Trace sampling is environment-specific and cost-bounded. Sensitive routes and jobs may be excluded entirely; local and automated tests do not send events to the hosted Sentry account.
- Production source maps are uploaded during deployment using a least-privilege CI/deploy token and are not publicly served. Sentry DSNs and auth tokens are environment configuration, never Organisation settings or client-visible secrets beyond the intentionally public browser DSN.
- Alert rules cover new regressions, elevated error rate, critical job failures, and scheduler/backup check-in failures. Every alert has an owner and links to a redaction-safe runbook.
- Security alert routing covers cross-tenant denials/anomalies, unexpected bulk access/export, privileged-account changes, audit-chain failures, unrecognized releases, key/credential disclosure reports, and provider security notices, with deduplication into the restricted incident-triage workflow.
- Monitor availability, latency, errors, queue depth, payment/webhook failures, campaign sends, and data-pipeline freshness.
- Show users actionable integration status without exposing secrets or sensitive payloads.

## 12. Integrations

The spec build should define replaceable provider interfaces but only implement what improves the demonstration. Resend is the selected real provider for platform account and security transactional email. Default to local adapters and visible sandbox/simulated states for:

- payment gateway and recurring mandates;
- tenant campaign, donation, journey, and bulk email;
- SMS;
- accounting/finance export or API;
- calendar and event reminders;
- website forms and content management;
- identity provider/SSO for staff;
- business intelligence or a governed reporting warehouse; and
- optional address validation and geographic/service-area lookup.

Real integrations must be replaceable through internal adapters, idempotent where relevant, and observable. Data-processing agreements are required only when a future implementation sends real data to a provider.

Resend uses a verified platform-owned sending subdomain, separate API keys per hosted environment, and queued Laravel Mailables/Notifications. Tenant-supplied sender domains are not supported in M0–M3. Production and staging have explicit recipient controls; local and test use fake or local mail transports. Resend credentials never enter Organisation configuration, logs, queued payloads, or the client bundle. Provider failures are retryable and observable without logging message bodies or sensitive recipient context.

## 13. Delivery milestones

The 900-hour M0–M3 cap is allocated as a forecasting guardrail:

| Workstream | Budget |
|---|---:|
| M0 — Tenant foundation | 240 hours |
| M1 — Service delivery | 260 hours |
| M2 — Supporter loop | 120 hours |
| M3 — Impact showcase | 160 hours |
| Integrated hardening and release contingency | 120 hours |
| **Total** | **900 hours** |

The total is hard; the workstream allocations may be reforecast without changing scope safeguards. Actual and remaining effort is recorded in the public issue/project ledger once the repository is public. At 450 hours, reforecast every M0–M3 Must criterion and remove or defer Should/Could work. At 720 hours, freeze features and reserve the final 180 hours for Must completion, security, accessibility, documentation, release/recovery evidence, and demo reliability. Any increase above 900 hours requires an explicit PRD budget change.

### M0 — Tenant foundation

**Outcome:** A secure Laravel application in which Organisations represent nonprofit tenants.

Includes controlled Organisation provisioning, lifecycle transitions, independent Access Holds, ownership transfer, invitations, Membership Holds, switching, multiple scoped Role Assignments, unique slug provisioning, public subdomain resolution, explicit Laravel tenant context, Organisation-scoped tables and indexes, composite relationship constraints, synthetic fixtures for HarbourKind and NeighbourLink, and automated isolation tests.

**Exit gate:** A user can belong to both Organisations, switch deliberately, and cannot read, mutate, search, export, associate, or infer another Organisation's records by changing identifiers or stale application state. HarbourKind and NeighbourLink resolve from their respective subdomains, while unknown and reserved hosts reveal no tenant data. Every Organisation status and permitted transition produces the specified staff, public, job, cache, search, and recovery behavior.

### M1 — Service delivery

**Outcome:** HarbourKind staff can complete a fictional support case from referral through outcome.

Includes tenant-local Parties, the three predefined programs, intake, assignments, case records, goals, interactions, services, referrals, outcomes, basic program configuration, case-level authorization, and the complete document quarantine/scan/download lifecycle.

**Exit gate:** The request-to-outcome showcase passes end to end, all service-domain state machines and closure invariants reject invalid transitions atomically, and negative permission tests prove that unauthorised roles cannot discover client participation or sensitive records. Assignment history, confidentiality inheritance, sensitive-data permission, safe-contact presentation, corrections, audit behavior, export exclusions, and fail-closed document scanning and retrieval work as specified.

### M2 — Supporter loop

**Outcome:** A fictional supporter can contribute and enter a safe, measurable engagement journey.

Includes consent, campaign attribution, simulated one-off and recurring donations, demo receipts, saved segments, email preview/simulation, suppression rules, and one welcome journey.

**Exit gate:** The donor-to-retained-supporter showcase passes without collecting real payment data or contacting a real person. Payment, refund, receipt, campaign, message, suppression, unsubscribe, and welcome-journey transitions are deterministic and idempotent; invalid transitions fail atomically; and all timeline and dashboard events reconcile to seeded records.

### M3 — Impact showcase

**Outcome:** The M0–M2 data forms a polished, credible demonstration of local impact.

Includes role-specific dashboards, metric definitions, de-identification thresholds, accessible exports, audit views, deterministic reset, isolated time-limited sandbox provisioning, demo walkthrough, a synthetic incident-response tabletop/evidence pack, and final accessibility/security checks.

**Exit gate:** All four showcase scenarios pass from a fresh installation; every metric reconciles exactly to its seeded fixture total; suppression, dimensions, rate exclusions, reporting periods, currency, timezone, freshness, and definition versions behave as specified; the synthetic incident exercise demonstrates containment, evidence, recovery and communications decisions without exposing content; and the spec-release acceptance criteria are satisfied.

**M0–M3 together constitute the spec MVP.**

### M4 — Engagement breadth

**Outcome:** The product expands beyond the core demonstration into a broader engagement platform.

Includes volunteering, events, in-kind goods, business partners, verified User-to-Party supporter self-service, richer dynamic segmentation, configurable forms and journeys, re-engagement, messaging experiments, and extended reporting.

**Exit gate:** Each added channel has a complete registration-to-follow-up journey, respects Organisation and consent boundaries, and appears in reconciled reporting.

### M5 — Production readiness

**Outcome:** The product is prepared for validation and controlled adoption by a real organisation.

Includes practitioner and lived-experience validation, jurisdiction-specific review, optional verified custom domains with managed TLS, official-hosted Billing Accounts, Service Offerings, Subscriptions, billing-safe usage, canonical Service Invoices and Service Payments, Hosted Access policies, real donation-payment/messaging/accounting integrations, data migration, production file controls, retention and legal hold, operational runbooks, staff training, and a support model.

**Exit gate:** Production readiness is approved against security, privacy, safeguarding, accessibility, migration, integration, recovery, and operational checklists. Completion of M5 does not itself constitute legal or regulatory certification.

## 14. Explicitly out of scope for spec MVP

- clinical diagnosis, medical records, or emergency-dispatch functionality;
- benefits eligibility decisions made solely by algorithms;
- unrestricted cross-program access to client records;
- a general-purpose accounting ledger or payroll system;
- native mobile applications;
- generative AI acting on case notes or sending messages without human approval;
- claims that an individual donation caused a specific client outcome without evidence;
- real client, donor, volunteer, or payment data;
- production certification or claims of regulatory compliance; and
- configurable workflows for every kind of nonprofit.

## 15. Showcase scenarios

The finished spec build must support four repeatable demonstrations:

1. **From request to outcome:** An intake worker accepts a housing-support referral, triages it, assigns a case, records a service and referral, and closes a goal with an outcome.
2. **From local donor to retained supporter:** A person makes a simulated donation, receives a receipt, enters a welcome journey, and appears in the correct supporter segment.
3. **From contribution to impact:** An executive moves from an aggregate dashboard into metric definitions and de-identified program results without seeing restricted case notes.
4. **Privacy and tenant boundary:** An engagement officer searches for a known supporter who is also a fictional HarbourKind client; they can see supporter activity but cannot discover the person's client status. The same user switches to NeighbourLink and cannot find any HarbourKind record, file, search result, dashboard aggregate, or cached navigation state. Authorised access paths are audited against the correct Organisation.

Scenario 1 is the centrepiece and must become demonstrable during M1. Scenario 4 is enforced throughout scenario 1 and every other scenario as a release gate; it is not a separable demonstration that may be dropped. Scenarios 2 and 3 support the complete product story, while alternate configurations, report variants, and secondary dashboard polish may be reduced before any safeguard or core tracer bullet is weakened.

## 16. Acceptance criteria for spec release

The MVP is launch-ready when:

1. each fictional service program can complete intake, triage, assignment, support recording, outcome capture, and closure;
2. permission tests prove fundraising and engagement roles cannot discover or access client-service participation or records;
3. tenant-isolation tests prove a user cannot read, create, update, associate, search, export, or infer records outside the active Organisation, including by guessing identifiers;
4. public-host tests prove each platform subdomain resolves only its Organisation and that unknown, reserved, malformed, and cross-Organisation hosts reveal no tenant data;
5. policy tests cover every MVP role/resource/action combination, including Organisation owners without operational access, program boundaries, assignment boundaries, supporter-safe field projection, exports, and identifier tampering;
6. lifecycle tests cover all allowed and forbidden Organisation transitions, independent Access Holds, ownership transfer, last-owner protection, state-specific access, job cancellation, deletion recovery, and slug quarantine;
7. database tests prove tenant-owned models require context, `organisation_id` is immutable, cross-Organisation foreign-key associations fail, relationships remain scoped, and soft-deleted records remain isolated;
8. job and command tests prove missing or mismatched tenant context fails closed and tenant iteration does not leak state between Organisations;
9. confidentiality tests cover default classification, inheritance, attempted downgrade, sensitive-data permissions, hidden case existence, safe-contact separation, document access, logs, notifications, audits, and export exclusions;
10. service-workflow tests cover every valid and invalid intake, case, assignment, goal, service, referral, task, and appointment transition, including idempotent acceptance, closure prerequisites, assignment transfer, correction history, concurrency, and metric event dates;
11. payment tests cover every valid and invalid transition, retries, duplicate/out-of-order events, recurring recovery/cancellation, refund limits, immutable money fields, receipt eligibility, and dashboard timing;
12. communication tests cover approval freezing, audience materialization, dispatch-time suppression, consent withdrawal, frequency caps, cancellation, retry, idempotency, local-only transport, and forbidden case data;
13. authentication tests cover invitation binding/expiry/revocation, verified email, mandatory TOTP enrollment, recovery-code single use, global MFA across Organisations, neutral responses, throttling, session expiry/revocation, step-up windows, forbidden tenant-admin credential reset, and the guarded synthetic recovery path;
14. a supporter can complete a simulated donation and receive the correct demo acknowledgement and receipt, including recurring-payment lifecycle events;
15. staff can segment opted-in supporters and simulate an approved campaign with suppression rules applied;
16. operational and fundraising dashboards reconcile to the seeded test dataset;
17. metric tests cover exact formulas, half-open periods, Organisation timezone, `as_of` state, comparison periods, zero versus unavailable values, rate exclusions, cohort suppression, forbidden dimensions, and export parity;
18. seeded records are deterministic, internally consistent, explicitly fictional, and resettable;
19. reset tests cover environment gating, authorization, recent authentication, Organisation locking, generation invalidation, failure recovery, cross-Organisation preservation, search/cache rebuild, audit, and exact post-reset reconciliation;
20. sandbox tests cover token hashing, Organisation/generation binding, expiry, revocation, role selection, session termination, cross-sandbox isolation, failed provisioning, idempotent purge, and absence of evaluator personal data;
21. core flows pass automated accessibility checks and keyboard/screen-reader spot checks;
22. the repository contains architecture notes, data definitions, setup instructions, test instructions, assumptions, and a production-readiness disclaimer;
23. the full showcase can be run from a fresh installation without hidden manual data fixes;
24. environment-isolation checks prove staging and demo cannot share application keys, sessions, databases, Redis/Horizon namespaces, queues, application buckets, mail controls, or tenant hosts, and that production-only configuration cannot enable demo tooling;
25. audit tests prove required events are atomic with protected mutations, failed sensitive-view auditing denies access, payload schemas reject sensitive or unknown fields, runtime roles cannot update/delete/truncate events, tenant projections do not leak events, retries are idempotent, digest-chain tampering is detected, retention respects legal hold, and restore reapplies the deletion ledger;
26. encryption tests prove classified database fields contain randomized ciphertext rather than plaintext, ciphertext cannot be swapped across Organisations/records/fields, missing or wrong keys fail closed, names remain authorized-searchable, contact matching is exact and Organisation-bound, identical cross-Organisation contacts produce different blind indexes, unsupported partial contact search reveals nothing, contact-index rotation dual-reads/writes while data-key rotation re-encrypts idempotently, plaintext classified values and blind indexes never enter Scout/logs/queue payloads/Sentry/audits, object keys contain no personal data, and a representative restore succeeds only with the documented recovery keys;
27. document tests prove unauthorized uploads fail, allowlist and size/dimension/rate limits agree across layers, spoofed MIME/signature and deceptive names fail, quarantine is private and excluded from backups, no object reaches S3 before a clean result, stale/unavailable/error scanner states fail closed, retries and concurrent jobs are idempotent, EICAR is rejected and destroyed, accepted images are metadata-stripped/re-encoded and rescanned, stale generations cannot release files, clean size/checksum verification precedes release, only currently authorized users can download with forced safe headers, replacement preserves the last clean version until success, rejected details remain redacted, orphan/missing/expired quarantine reconciliation works, and public demo and public forms reject arbitrary upload bytes;
28. incident-response tests and tabletop evidence prove `security.txt` is valid/current on every host and reaches monitored intake, alerts remain distinct from confirmed incidents, severity and backward lifecycle transitions are reasoned and immutable, containment scope is enforceable and reversible, Installation-wide User compromise covers every Organisation, stale jobs/outbox work cannot escape a freeze, routine operators cannot inspect tenant content and no content break-glass path exists before M5, emergency containment exceptions are independently reviewable, evidence hashes/chain-of-custody and tenant projections do not leak content or other Organisations, communications work without the application/Resend, legal notification remains a recorded human decision with configured jurisdictional deadlines, safe-contact restrictions govern affected-person notices, recovery gates prevent premature reopening or duplicate work, and production configuration fails readiness without named critical-response coverage and verified contacts;
29. open-source release checks prove all first-party software and necessary build/deployment source is present under `AGPL-3.0-only`, documentation/assets and synthetic data carry their approved notices, incompatible or unknown production dependency licences fail CI, every human contribution has DCO sign-off, the exact deployed source remains obtainable from every interface, release archives/SBOM/attributions reproduce the tagged build without secrets or tenant data, forks can remove official branding without disabling the software, and no production feature or organisation-size check imposes a proprietary or commercial-use restriction; and
30. delivery evidence reconciles actual effort to the 900-hour cap, records the 450- and 720-hour reviews and resulting scope changes, excludes M4/M5 implementation, proves no mandatory safeguard was traded away, and labels the artifact a technical preview if any other spec-release acceptance criterion remains unmet when the cap is reached.

## 17. Build approach

Build the request-to-outcome centrepiece as the first tracer bullet: identity and permissions, intake, consent and duplicate handling, case delivery, outcome, privacy boundaries, auditing, and applicable document handling. Make it demonstrable by the end of M1 and approximately 500 cumulative hours. Then connect one simulated donation, one automated welcome, and one reconciled dashboard before adding variants or presentation breadth.

Use the 450- and 720-hour reviews as genuine scope gates. Reduce scope in this order: remove M0–M3 Should/Could work; reduce alternate configurations, report variants, and secondary dashboard polish; then retain one complete tracer bullet per supporting domain instead of broad coverage. Do not cut tenant isolation, authentication/MFA, permissions and confidentiality, encryption and key recovery, audit/incident/backup controls, workflow and data integrity, core accessibility, or deterministic tests. Do not silently overrun the cap: explicitly rebudget the PRD or release an accurately labelled technical preview.

The spec-build evidence pack should include:

- explicit assumptions and a fictional-organisation profile;
- service and supporter journey maps;
- information classification and permission matrix;
- canonical data dictionary and duplicate/identity rules;
- provisional consent and retention model marked for expert review;
- provisional outcomes framework linking inputs, activities, outputs, and outcomes;
- synthetic-data generation and reset documentation;
- architecture and integration decisions;
- open-source licence, notice, DCO, governance, contribution, trademark, support/version, attribution, SBOM and release-process documentation;
- incident-response runbook, contact/role placeholders, tabletop evidence, and corrective-action register;
- public effort ledger, checkpoint forecasts, approved scope changes, and final budget reconciliation; and
- short usability-test findings from representative evaluators, clearly noting they are not domain validation.

## 18. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Sensitive client data leaks into supporter workflows | Separate authorisation domain, negative permission tests, audited exports, privacy review |
| A missing Organisation scope leaks tenant data | Layered enforcement, tenant-aware route binding and policies, Organisation-scoped constraints, adversarial isolation tests |
| A tenant-state change leaves stale access or work active | Central transition service, status checks at request and job execution, cache/session invalidation, lifecycle tests |
| One system becomes too complex for staff | Phased rollout, role-specific views, configurable defaults, embedded training |
| Fictional workflows appear authoritative | Label assumptions, cite inspiration, publish validation gaps, avoid compliance claims |
| Synthetic data feels artificial or exposes real people | Generate deterministic fictional records; prohibit copied production/personal data |
| Demo reset damages shared or production data | Do not register reset capability in production; require Organisation scope, generation invalidation, lock/reconcile workflow, and cross-Organisation preservation tests |
| Public demo access is abused or crosses sandboxes | Operator-issued expiring tokens, isolated Organisation pairs, no outbound integrations/uploads, rate limits, generation binding, immediate revocation, automated purge |
| An uploaded file attacks staff or infrastructure | Staff-only allowlisted uploads, private local quarantine, ClamAV with fresh signatures, fail-closed state machine, forced downloads, no public-demo uploads, monitoring, and no manual release bypass |
| A security incident spreads or is mishandled | Severity-based activation, named command roles, pre-authorized containment, independent evidence, tenant-scoped communications, jurisdiction-specific breach decisions, recovery gates, and recurring exercises |
| Open-source users do not fund the official service | Treat self-hosting as a legitimate outcome; differentiate through trusted hosting, support, implementation, SLAs and stewardship; publish costs; seek sponsorship/grants; keep the service viable before subsidising usage |
| Product is too broad to finish | Protect the request-to-outcome centrepiece and privacy overlay; use the 450/720-hour gates; cut Should/Could work and secondary breadth before mandatory controls |
| The 900-hour budget is exhausted before acceptance | Do not weaken safeguards or imply launch readiness; explicitly approve a new budget or publish the incomplete artifact as a technical preview |
| Automation sends inappropriate messages | Consent/safe-contact gates, preview and approval, frequency caps, per-person pause |
| Impact reporting overstates causality | Defined theory of change, metric definitions, aggregate attribution, methodology notes |
| Bespoke build creates vendor/key-person dependency | Documented APIs, automated tests, infrastructure as code, runbooks, maintainable architecture |
| Scope expands into every nonprofit workflow | Protect MVP boundary and evaluate extensions against measurable goals |

## 19. Spec-build decision status

No unresolved M0–M3 product decision is recorded at PRD approval. New scope or a forecast above 900 hours requires an explicit PRD change rather than an implicit extension.

## 20. Production validation backlog

Before a real organisation could adopt the product, validate:

- terminology and daily workflows with frontline staff, program managers, engagement staff, and finance users;
- intake, safeguarding, escalation, and safe-contact patterns with qualified practitioners;
- accessibility and dignity with relevant service users and people with lived experience, using ethical research practices;
- privacy, consent, retention, fundraising, tax-receipt, and breach obligations in the target jurisdiction;
- integrations, migration quality, operational ownership, support capacity, and total cost; and
- whether the proposed outcomes are meaningful, collectable, and safe to report.

## 21. Source references

- CiviCRM, “LocalKind: A Digital Strategy for Local Kindness at Scale,” updated 27 May 2026: https://civicrm.com/success-story/localkind-a-digital-strategy-for-local-kindness-at-scale/
- LocalKind Northern Beaches public website: https://www.localkind.org.au/
- Salesforce Agentforce Nonprofit documentation, used only to understand the current integrated nonprofit-platform category: https://help.salesforce.com/s/articleView?id=sfdo.nonprofit_cloud.htm&language=en_US&type=5
- Blackbaud Enterprise Fundraising CRM product page, used only to distinguish enterprise fundraising positioning from the spec-build hypothesis: https://www.blackbaud.com/products/blackbaud-CRM
- OWASP Authorization Cheat Sheet, used as general deny-by-default and least-privilege engineering guidance: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html
- OWASP Logging Cheat Sheet, used for security-event selection, sensitive-data exclusion, integrity protection, and tamper-detection guidance: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html
- OWASP Cryptographic Storage Cheat Sheet, used for threat-based encryption layers, maintained cryptographic libraries, independent keys, rotation, and recovery guidance: https://cheatsheetseries.owasp.org/cheatsheets/Cryptographic_Storage_Cheat_Sheet.html
- OWASP Key Management Cheat Sheet, used for key lifecycle and recovery planning: https://cheatsheetseries.owasp.org/cheatsheets/Key_Management_Cheat_Sheet.html
- OWASP File Upload Cheat Sheet, used for allowlisting, type/signature validation, generated storage names, size limits, private storage, authorization, antivirus, and defence-in-depth guidance: https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
- NIST SP 800-61 Rev. 3, used as jurisdiction-neutral guidance for integrating preparation, detection, incident management, response, recovery, communications, and continuous improvement into cybersecurity risk management: https://csrc.nist.gov/pubs/sp/800/61/r3/final
- RFC 9116, used for the public `security.txt` vulnerability-reporting contact and policy discovery mechanism: https://www.rfc-editor.org/rfc/rfc9116.html
- Open Source Initiative Open Source Definition, used for the prohibition on discriminating by person, group or field of endeavour: https://opensource.org/osd
- Open Source Initiative's approved GNU AGPL v3 licence text and metadata: https://opensource.org/license/agpl-3-0
- GNU Project explanation of AGPL's network-source provision: https://www.gnu.org/licenses/why-affero-gpl.en.html
- Developer Certificate of Origin 1.1, used for contribution sign-off without copyright assignment: https://developercertificate.org/
- Creative Commons BY-SA 4.0 and CC0 1.0 legal tools used for documentation/assets and synthetic datasets respectively: https://creativecommons.org/licenses/by-sa/4.0/ and https://creativecommons.org/publicdomain/zero/1.0/
- Office of the Australian Information Commissioner privacy guidance, used only as non-jurisdictional privacy-design guidance—not as the product's law or compliance target: https://www.oaic.gov.au/privacy/privacy-guidance-for-organisations-and-government-agencies/handling-personal-information/guide-to-securing-personal-information
- Laravel 13 release and support policy: https://laravel.com/docs/13.x/releases
- Laravel 13 starter-kit documentation, including React/Inertia and Teams behavior: https://github.com/laravel/docs/blob/13.x/starter-kits.md
- Laravel 13 Fortify authentication, TOTP, recovery-code, password-confirmation, and passkey guidance: https://github.com/laravel/docs/blob/13.x/fortify.md
- Laravel 13 session authentication and session-revocation guidance: https://github.com/laravel/docs/blob/13.x/authentication.md
- Laravel 13 encryption implementation and multi-key decryption support: https://github.com/laravel/framework/blob/13.x/src/Illuminate/Encryption/Encrypter.php
- Laravel 13 Eloquent cast guidance: https://github.com/laravel/docs/blob/13.x/eloquent-mutators.md
- Laravel 13 validation and filesystem guidance used for upload validation and private storage adapters: https://github.com/laravel/docs/blob/13.x/validation.md and https://github.com/laravel/docs/blob/13.x/filesystem.md
- Laravel 13 image-processing guidance used for resource-bounded image decoding and canonical re-encoding: https://github.com/laravel/docs/blob/13.x/images.md
- Laravel Scout database search guidance: https://laravel.com/docs/13.x/scout
- Laravel Horizon queue guidance: https://laravel.com/docs/13.x/horizon
- ClamAV `clamd` protocol guidance, including Unix sockets and streamed scanning: https://docs.clamav.net/manual/Usage/ClamdProtocol.html
- ClamAV FreshClam signature-update guidance: https://docs.clamav.net/manual/Usage/SignatureManagement.html
- Laravel Forge wildcard-site API documentation: https://forge.laravel.com/api-documentation
- Laravel Forge server and site provisioning guidance: https://forge.laravel.com/docs/servers/the-basics
- GitHub Actions deployment environments and concurrency guidance: https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments and https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/control-deployments
- GitHub Actions security guidance: https://docs.github.com/en/actions/how-tos/secure-your-work
- Laravel Forge Horizon process guidance: https://forge.laravel.com/docs/sites/queues.html
- 1Password Emergency Kit and CLI secret-injection guidance: https://support.1password.com/emergency-kit/ and https://developer.1password.com/docs/cli/secrets-scripts
- Bitwarden Emergency Access guidance: https://bitwarden.com/help/emergency-access/
- Cloudflare Full (strict) origin TLS guidance: https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/
- Cloudflare for SaaS custom-hostname and fallback-origin guidance: https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/start/getting-started/
- Official Resend Laravel SDK and mailer configuration: https://github.com/resend/resend-laravel
- Resend verified-domain guidance: https://resend.com/docs/dashboard/domains/introduction
- Official Sentry Laravel SDK: https://github.com/getsentry/sentry-laravel
- Sentry release/deploy and Cron Monitoring APIs: https://docs.sentry.io/api/releases/create-a-deploy/ and https://docs.sentry.io/api/crons/
- Restic documentation, including S3 repositories, integrity checks, and restore workflow: https://restic.readthedocs.io/en/stable/
- PostgreSQL 18 `pg_dump` and custom-format archive guidance: https://www.postgresql.org/docs/18/app-pgdump.html
- PostgreSQL 18 privilege and `REVOKE` guidance used for append-only runtime access: https://www.postgresql.org/docs/18/ddl-priv.html and https://www.postgresql.org/docs/18/sql-revoke.html
