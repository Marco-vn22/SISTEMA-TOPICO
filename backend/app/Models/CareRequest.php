<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class CareRequest extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (CareRequest $care): void {
            if ($care->isDirty('created_at') || ($care->getRawOriginal('authorized_at') !== null && $care->isDirty('authorized_at'))) {
                throw ValidationException::withMessages(['timestamps' => 'Las horas de solicitud y autorización no pueden modificarse.']);
            }
        });
    }

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
