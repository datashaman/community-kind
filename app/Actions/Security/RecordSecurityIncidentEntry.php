<?php

namespace App\Actions\Security;

use App\Enums\SecurityIncidentEntryType;
use App\Models\SecurityIncident;
use App\Models\SecurityIncidentEntry;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class RecordSecurityIncidentEntry
{
    public function handle(
        SecurityIncident $incident,
        SecurityIncidentEntryType $type,
        string $summary,
        ?User $actor = null,
        ?string $reference = null,
        ?string $status = null,
        ?CarbonInterface $dueAt = null,
    ): SecurityIncidentEntry {
        if ($summary === '' || ($type === SecurityIncidentEntryType::EvidenceReference && $reference === null)) {
            throw new InvalidArgumentException('Incident entries require a summary and evidence entries require an opaque reference.');
        }

        return $incident->entries()->create([
            'actor_user_id' => $actor?->id,
            'type' => $type,
            'summary' => $summary,
            'reference' => $reference,
            'status' => $status,
            'due_at' => $dueAt,
            'occurred_at' => now(),
        ]);
    }
}
