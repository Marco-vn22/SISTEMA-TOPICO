<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Patient extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['allergies' => 'encrypted', 'background' => 'encrypted'];
    }

    public function ensureAccessAccount(User $actor): ?User
    {
        return DB::transaction(function () use ($actor): ?User {
            $patient = self::query()->lockForUpdate()->findOrFail($this->id);
            if ($patient->user_id) {
                return User::query()->findOrFail($patient->user_id);
            }
            if (! preg_match('/^[0-9]{8}$/', $patient->document)) {
                return null;
            }
            $account = User::query()->where('dni', $patient->document)->lockForUpdate()->first();
            if ($account) {
                abort_if(self::query()->where('user_id', $account->id)->whereKeyNot($patient->id)->exists(), 422, 'La cuenta de este DNI ya está vinculada a otra ficha de paciente.');
            } else {
                $account = User::query()->create([
                    'name' => $patient->name,
                    'dni' => $patient->document,
                    'email' => null,
                    'password' => $patient->document,
                    'role' => 'estudiante',
                    'active' => true,
                    'uses_initial_password' => true,
                ]);
                AuditLog::query()->create(['user_id' => $actor->id, 'action' => 'created', 'entity' => 'user', 'entity_id' => $account->id]);
            }
            $patient->update(['user_id' => $account->id, 'updated_by' => $actor->id]);
            $this->setAttribute('user_id', $account->id);
            AuditLog::query()->create(['user_id' => $actor->id, 'action' => 'account_linked', 'entity' => 'patient', 'entity_id' => $patient->id]);

            return $account;
        });
    }
}
