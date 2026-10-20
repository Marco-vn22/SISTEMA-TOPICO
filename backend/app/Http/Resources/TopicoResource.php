<?php

namespace App\Http\Resources;

use App\Models\CareRequest;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $clinical = $request->user()?->role === 'encargado';
        $staff = in_array($request->user()?->role, ['coordinador', 'encargado'], true);
        $model = $this->resource;
        if ($model instanceof User) {
            return ['id' => $model->id, 'name' => $model->name, 'email' => $model->email,
                'role' => $model->role, 'active' => $model->active,
                'patient_id' => $model->patient?->id];
        }
        if ($model instanceof Patient) {
            $data = ['id' => $model->id, 'name' => $model->name, 'classroom' => $model->classroom];
            if ($staff) {
                $data += $model->only(['user_id', 'document', 'birth_date', 'phone', 'emergency_contact', 'emergency_phone', 'created_by', 'updated_by']);
            }
            if ($clinical) {
                $data += $model->only(['allergies', 'background']);
            }

            return $data;
        }
        if ($model instanceof CareRequest) {
            $data = $model->only(['id', 'patient_id', 'teacher_id', 'priority', 'status', 'emergency', 'authorization_note', 'authorized_at', 'received_at', 'closed_at', 'created_by', 'updated_by']);
            $data['created_at'] = $model->created_at->toIso8601String();
            $data['reason'] = $request->user()?->role === 'coordinador' ? 'Solicitud de atención' : $model->reason;
            $data['patient'] = new self($model->patient);
            $data['teacher'] = $model->teacher ? ['id' => $model->teacher->id, 'name' => $model->teacher->name] : null;
            if ($clinical && $model->relationLoaded('encounter')) {
                $data['encounter'] = $model->encounter ? new self($model->encounter) : null;
            }

            return $data;
        }
        if ($model instanceof Encounter) {
            abort_unless($clinical, 403, 'El historial clínico está restringido al encargado del tópico.');

            return $model->only(['id', 'request_id', 'patient_id', 'clinician_id', 'symptoms', 'diagnosis', 'indications', 'outcome', 'referral_destination', 'referral_reason', 'followup_status', 'followup_notes', 'referral_contact']) + [
                'created_at' => $model->created_at->toIso8601String(),
                'patient' => new self($model->patient),
                'clinician' => ['id' => $model->clinician->id, 'name' => $model->clinician->name],
            ];
        }

        return [];
    }
}
