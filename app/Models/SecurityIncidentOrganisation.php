<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['incident_uuid', 'organisation_id', 'impact_status', 'impact_summary', 'approved_communication'])]
class SecurityIncidentOrganisation extends Model
{
    /** @return BelongsTo<SecurityIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(SecurityIncident::class, 'incident_uuid');
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
