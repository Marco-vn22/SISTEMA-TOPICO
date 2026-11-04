<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encounter extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['symptoms' => 'encrypted', 'diagnosis' => 'encrypted', 'indications' => 'encrypted', 'referral_reason' => 'encrypted', 'followup_notes' => 'encrypted', 'no_medication_reason' => 'encrypted', 'closed_at' => 'datetime', 'coordinator_reviewed_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinician_id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_reviewed_by');
    }

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class, 'request_id');
    }
}
