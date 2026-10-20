<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CareRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['emergency' => 'boolean', 'reason' => 'encrypted'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function encounter(): HasOne
    {
        return $this->hasOne(Encounter::class, 'request_id');
    }
}
