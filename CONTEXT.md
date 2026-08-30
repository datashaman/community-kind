# CommunityKind

CommunityKind supports nonprofit organisations in coordinating community services, supporters, and impact while keeping each organisation's information separate.

## Language

**CommunityKind Installation**:
One independently operated deployment and identity boundary. The official hosted service is one Installation; every self-hosted deployment is another, with no automatic identity or Organisation sharing between them.
_Avoid_: Organisation, hosted account

**Installation Operator**:
An Installation-level responsibility for provisioning, Service Offerings, Access Holds, infrastructure, and incident response. It grants no routine Organisation membership or tenant-data access.
_Avoid_: Organisation Administrator, Organisation Owner

**Elevated Support Access**:
A time-limited, approved, and audited exception allowing an Installation Operator narrowly scoped access to an Organisation for a stated support or incident purpose.
_Avoid_: Operator access, impersonation, permanent support role

**User**:
A CommunityKind Installation-wide authentication identity used to sign in. Disabling it blocks every Membership and Portal Access Grant in that Installation; it is not a client, donor, volunteer, staff profile, Organisation, or billing customer and is never shared automatically with another Installation.
_Avoid_: Person, constituent, customer

**Organisation**:
A nonprofit operating as one independent tenant and data boundary. Organisations do not contain one another or inherit access, even when they share members or a Billing Account.
_Avoid_: Team, workspace, tenant account

**Party**:
An Organisation-owned contact or participant with exactly one kind: Person, Household, or Organisation. A Party may have multiple relationship classifications without duplicating its identity, and the tenant Organisation is never one of its own Parties.
_Avoid_: User, tenant Organisation

**Organisation Party**:
A tenant-owned contact representing a business, nonprofit, government body, or other organisation that an Organisation interacts with. It is not itself a tenant and cannot contain memberships or authenticate.
_Avoid_: Organisation, Team, customer account

**Household Party**:
An Organisation-owned grouping of people treated as a household for relationships and communications. It cannot authenticate and is not an Organisation or billing payer.
_Avoid_: Organisation, User group

**Billing Account**:
The provider-independent individual or organisation responsible for charges within the official hosted Installation. A Billing Account may fund multiple Organisations but owns none of their operational data, never reuses a Party, and treats payment-processor customer identities as attached external references.
_Avoid_: Organisation, tenant, customer

**Billing Account Status**:
The Billing Account's ability to undertake future billing activity. Closure requires all current Subscriptions to end or gain successors and preserves required financial history without affecting funded Organisations.
_Avoid_: Organisation Status, Subscription Status

**Billing Contact**:
A named destination for invoice, tax, procurement, or renewal communications that need not have a User or Billing Account Membership. Receiving billing communications grants no application authority.
_Avoid_: Billing Account Member, Organisation contact

**Subscription**:
An Organisation's time-bounded entitlement to the official hosted service, funded through one Billing Account under an accepted Service Offering. A hosted Organisation has at most one active Subscription; changing payer creates a successor rather than rewriting history, and a self-hosted Organisation has none.
_Avoid_: Membership, licence

**Service Offering**:
A versioned set of commercial terms for the official hosted service, including price, billing interval, included usage, overage treatment, support level, and Access Policy. It does not define Organisation permissions or restrict the capabilities of self-hosted software.
_Avoid_: Mutable plan, product role, payment-provider price

**Access Policy**:
A versioned hosted-service policy defining payment grace, read-only and recovery-only periods, notifications, and eventual denial. Explicit contractual overrides remain attached to the relevant Subscription.
_Avoid_: Organisation lifecycle, payment-provider retry settings

**Service Invoice**:
A canonical, immutable hosted-service charge issued by CommunityKind to a Billing Account and optionally linked to an external provider invoice. It is never part of an Organisation's fundraising records.
_Avoid_: Donation receipt, Donation Payment

**Service Payment**:
A canonical collection or settlement against a Service Invoice, reconciled idempotently with any external provider event. It belongs to hosted-service billing and is never treated as a donation.
_Avoid_: Donation Payment, donation settlement

**Payment Method**:
A safe reference to an externally hosted payment instrument, with only non-sensitive display metadata retained by CommunityKind. Billing Accounts using manual, grant-funded, or zero-cost arrangements need not have one.
_Avoid_: Card details, bank credentials

**Service Usage**:
Billing-safe, aggregated measurements of hosted resources consumed by an Organisation during a billing period. It excludes operational content, constituent data, and staff identities except designated billing contacts.
_Avoid_: Organisation analytics, impact metrics, audit content

**Donation Payment**:
An Organisation-owned attempt to collect a supporter donation. It is operational tenant data and never settles CommunityKind service charges.
_Avoid_: Service Payment, Subscription payment

**Organisation Provisioning**:
The controlled creation of an Organisation. On the official hosted service it atomically creates the Organisation and its first Subscription under an authorised Billing Account and nominates at least one initial Owner; member invitations do not grant provisioning authority.
_Avoid_: Registration, Team creation

**Organisation Membership**:
A User's accepted tenure with one Organisation, carrying Organisation-specific responsibilities and access and linking to exactly one Person Party there. It is active or ended, preserves historical attribution, and grants no authority over billing; rejoining creates a new tenure.
_Avoid_: Billing Account Membership, Subscription

**Membership Hold**:
A temporary, reasoned restriction on one Organisation Membership that pauses its Roles and Owner actions without ending its tenure or affecting the User elsewhere. Governance must retain at least one capable Owner while a hold is active.
_Avoid_: Ended Membership, disabled User, Organisation Access Hold

**Organisation Invitation**:
A time-limited proposal for a verified User to accept an Organisation Membership, initial scoped Role Assignments, and optionally an explicitly accepted Owner responsibility. It explicitly selects an existing Person Party or creates a new one; email matching never links identities automatically.
_Avoid_: Billing Invitation, automatic Membership

**Person Party**:
An Organisation-owned record representing one human in that Organisation's world. Person Parties are never shared or deduplicated across Organisations, even when linked to the same User.
_Avoid_: User, global person profile

**Portal Access Grant**:
An Organisation-specific relationship allowing a User subject-facing access associated with a Person Party. It is not an Organisation Membership and grants no staff, governance, or administrative authority.
_Avoid_: Organisation Membership, Operational Role

**Organisation Owner**:
A responsibility explicitly accepted through an Organisation Membership for recovery, lifecycle, ownership transfer, and appointment of Organisation Administrators. An Organisation has one or more Owners; ownership grants neither operational-data access nor billing authority, and only an Owner may nominate another Owner.
_Avoid_: Operational role, Billing Account administrator

**Operational Role**:
A named bundle of operational permissions assigned through an Organisation Membership. A Membership may hold multiple Operational Roles; ownership is not one of them.
_Avoid_: Organisation ownership, billing role, job title

**Organisation Administrator**:
A delegated Organisation-wide Operational Role for managing configuration, Memberships, invitations, and ordinary scoped Role Assignments without self-escalation. It is not Organisation ownership and carries no recovery or billing authority.
_Avoid_: Team Administrator, Organisation Owner

**Role Assignment**:
The assignment of one Operational Role to an Organisation Membership at an Organisation-wide or Program-specific scope. Different Roles held by the same Membership may have different scopes.
_Avoid_: Membership role, general Program access

**Program**:
A durable Organisation-owned service-delivery area used to organise services, outcomes, cases, and scoped Role Assignments. It is neither a tenant, staff group, billing offering, nor financial designation and may be archived without erasing its history.
_Avoid_: Organisation, Team, project, fund

**Case**:
An Organisation-owned unit of coordinated service belonging to exactly one Program, with one primary Person or Household Party and optional related participants. Work in another Program uses a related Case rather than sharing one Case across Programs.
_Avoid_: Cross-Program case, client identity

**Case Assignment**:
An explicit responsibility linking an Organisation Membership to one Case for access, workload, and accountability. It operates within Role Assignment scope but is not itself an Operational Role.
_Avoid_: Role Assignment, Program access

**Restricted Access Grant**:
An explicit, narrowly scoped authority to access highly restricted case information beyond ordinary Role and Case Assignment rules.
_Avoid_: Case Assignment, broad sensitive-data permission

**Billing Account Membership**:
A User's accepted tenure with one Billing Account, carrying billing-administrator or billing-viewer authority. It may control funding but grants no Organisation access, ownership, lifecycle, or deletion authority; invitations remain separate proposals.
_Avoid_: Organisation Membership, Organisation ownership

**Billing Account Owner**:
An explicitly accepted Billing Account Membership responsibility for legal payer identity, recovery, closure, and appointment of Billing Administrators. A Billing Account has one or more Owners, and the last Owner cannot leave.
_Avoid_: Organisation Owner, Billing Administrator

**Billing Administrator**:
A Billing Account role for managing payment methods, Subscriptions, accepted Service Offerings, and Service Invoices. It carries no Billing Account ownership or Organisation authority.
_Avoid_: Billing Account Owner, Organisation Administrator

**Billing Viewer**:
A Billing Account role with read-only access to billing-safe metadata, Service Usage, Subscriptions, and financial records.
_Avoid_: Organisation viewer, auditor

**Billing Invitation**:
A time-limited proposal for a verified User to accept a Billing Account Membership and billing role. It grants no Organisation Membership or operational access.
_Avoid_: Organisation Invitation, Organisation Membership

**Organisation Status**:
The Organisation's own lifecycle state: pending, active, archived, scheduled for deletion, or deleted. It is independent of commercial entitlement and temporary access restrictions.
_Avoid_: Subscription Status, Access Hold, payment status

**Access Hold**:
A temporary, reasoned restriction imposed for a security, safeguarding, legal, abuse, or operational incident. It overlays rather than changes Organisation Status and records its issuer, scope, review timing, and audit history.
_Avoid_: Suspended Organisation, past-due Subscription

**Subscription Status**:
The commercial state of an Organisation's hosted-service entitlement: pending activation, trialing, active, past due, or ended. Scheduled cancellation sets an end date while the current entitlement continues; ordinary billing and trial time begin on activation unless explicit contracted terms say otherwise.
_Avoid_: Organisation status, Hosted Access

**Hosted Access**:
The effective level of access the official hosted service permits after considering Organisation Status, Subscription Status, and Access Holds: full, read-only, recovery-only, or denied. Restriction leaves Memberships and Roles intact and follows a defined recovery policy rather than deleting the Organisation; Hosted Access has no meaning for a self-hosted Organisation.
_Avoid_: Organisation status, Subscription

## Example dialogue

> **Domain expert:** Sam belongs to both HarbourKind and NeighbourLink.
>
> **Developer:** Those are two Organisations with isolated records. Sam's User links to a separate Person Party in each. HarbourKind may also record a local business as an Organisation Party, but that contact does not become a tenant.
>
> **Domain expert:** A regional foundation pays for both Organisations on the official hosted service.
>
> **Developer:** The foundation has one Billing Account funding two separate Subscriptions. It gains no access to either Organisation merely by paying.
>
> **Domain expert:** A case worker accepts an invitation to HarbourKind.
>
> **Developer:** That Membership does not let the case worker provision another Organisation.
>
> **Domain expert:** Priya manages payment for HarbourKind but is not a staff member there.
>
> **Developer:** Priya has a Billing Account Membership, not an Organisation Membership, so she cannot inspect HarbourKind's records.
>
> **Domain expert:** Morgan owns HarbourKind but only handles governance.
>
> **Developer:** Morgan can administer the Organisation but cannot read case or supporter records without a separate operational role and scope.
>
> **Domain expert:** Lee manages one program and also works directly on cases.
>
> **Developer:** Lee's Organisation Membership has a Program Manager Role Assignment for one Program and a Case Worker Role Assignment for the relevant case-work Program.
>
> **Domain expert:** HarbourKind's invoice is overdue, but the nonprofit is still operating.
>
> **Developer:** HarbourKind remains an active Organisation. Its Subscription is past due, and Hosted Access applies the agreed grace-period policy.
