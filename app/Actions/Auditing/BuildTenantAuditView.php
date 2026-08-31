<?php

namespace App\Actions\Auditing;

use App\Enums\OrganisationRole;
use App\Enums\TenantAuditEventType;
use App\Models\CaseDocument;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\RestrictedAccessGrant;
use App\Models\ServiceCase;
use App\Models\TenantAuditEvent;
use App\Models\User;

class BuildTenantAuditView
{
    /** @return list<array{id: string, event: string, domain: string, actor: string, subject: string, occurredAt: string}> */
    public function handle(User $user, Organisation $organisation): array
    {
        $role = $user->organisationRole($organisation);

        $events = [];
        $candidates = TenantAuditEvent::query()
            ->latest('occurred_at')
            ->latest('id')
            ->cursor();

        foreach ($candidates as $event) {
            if (! $this->visible($event, $user, $role)) {
                continue;
            }

            $events[] = [
                'id' => $event->id,
                'event' => str($event->type->value)->replace('_', ' ')->headline()->toString(),
                'domain' => $this->domain($event->type),
                'actor' => $event->actor_user_id === null ? 'System' : ($event->actor_user_id === $user->id ? 'You' : 'Another staff user'),
                'subject' => str($event->subject_type)->replace('_', ' ')->headline()->toString(),
                'occurredAt' => $event->occurred_at->toAtomString(),
            ];

            if (count($events) === 100) {
                break;
            }
        }

        return $events;
    }

    private function visible(TenantAuditEvent $event, User $user, ?OrganisationRole $role): bool
    {
        return match ($role) {
            OrganisationRole::OrganisationAdministrator => in_array($event->type, [
                TenantAuditEventType::ProgramUpdated,
                TenantAuditEventType::AuditViewAccessed,
                TenantAuditEventType::DemoPersonaSelected,
                TenantAuditEventType::DemoOrganisationReset,
            ], true),
            OrganisationRole::ProgramManager => in_array($this->domain($event->type), ['service', 'configuration'], true)
                && ($this->programIsVisible($event, $user) || ($event->actor_user_id === $user->id && $event->type === TenantAuditEventType::ServiceOperationsExported)),
            OrganisationRole::CaseWorker => $event->actor_user_id === $user->id
                && $this->domain($event->type) === 'service'
                && $this->programIsVisible($event, $user),
            OrganisationRole::EngagementOfficer => $event->actor_user_id === $user->id
                && in_array($this->domain($event->type), ['fundraising', 'engagement'], true),
            default => false,
        };
    }

    private function programIsVisible(TenantAuditEvent $event, User $user): bool
    {
        $program = $this->programFor($event);

        return $program !== null && $user->hasProgramAccess($program);
    }

    private function programFor(TenantAuditEvent $event): ?Program
    {
        $programId = $event->payload['program_id'] ?? null;
        if (is_int($programId)) {
            return Program::query()->find($programId);
        }

        $caseId = $event->payload['case_id'] ?? null;
        if (is_string($caseId)) {
            return ServiceCase::query()->find($caseId)?->program;
        }

        $documentId = $event->payload['document_id'] ?? null;
        if (is_string($documentId)) {
            return CaseDocument::query()->find($documentId)?->serviceCase->program;
        }

        $grantId = $event->payload['grant_id'] ?? null;
        if (is_string($grantId)) {
            return RestrictedAccessGrant::query()->find($grantId)?->serviceCase->program;
        }

        return null;
    }

    private function domain(TenantAuditEventType $type): string
    {
        return match ($type) {
            TenantAuditEventType::ProgramUpdated,
            TenantAuditEventType::AuditViewAccessed,
            TenantAuditEventType::DemoPersonaSelected,
            TenantAuditEventType::DemoOrganisationReset => 'configuration',
            TenantAuditEventType::DonationCreated,
            TenantAuditEventType::DonationPaymentTransitioned,
            TenantAuditEventType::DonationRefunded,
            TenantAuditEventType::RecurringMandateTransitioned => 'fundraising',
            TenantAuditEventType::AudienceSegmentCreated,
            TenantAuditEventType::ImpactReportExported => 'engagement',
            default => 'service',
        };
    }
}
