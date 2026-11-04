<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareEvent extends Model
{
    protected $guarded = ['id'];

    public static function record(User $user, int $requestId, string $action, ?string $from = null, ?string $to = null): self
    {
        return static::query()->create(['user_id' => $user->id, 'user_role' => $user->role, 'care_request_id' => $requestId, 'action' => $action, 'from_status' => $from, 'to_status' => $to]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
