<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Encounter extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['symptoms' => 'encrypted', 'diagnosis' => 'encrypted', 'indications' => 'encrypted', 'referral_reason' => 'encrypted', 'followup_notes' => 'encrypted'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinician_id');
    }
}
