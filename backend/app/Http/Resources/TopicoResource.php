<?php

namespace App\Http\Resources;

use App\Models\CareRequest;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

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
            return ['id' => $model->id, 'name' => $model->name, 'dni' => $model->dni, 'email' => $model->email,
                'role' => $model->role, 'active' => $model->active, 'uses_initial_password' => $model->uses_initial_password,
                'patient_id' => $model->patient?->id];
        }
        if ($model instanceof Patient) {
            $data = ['id' => $model->id, 'name' => $model->name];
            if ($staff) {
                $data += $model->only(['user_id', 'document', 'birth_date', 'phone', 'emergency_contact', 'emergency_phone', 'created_by', 'updated_by']);
            }
            if ($clinical) {
                $data += $model->only(['allergies', 'background']);
            }

            return $data;
        }
        if ($model instanceof CareRequest) {
            $data = $model->only(['id', 'patient_id', 'teacher_id', 'priority', 'status', 'emergency', 'authorization_note', 'authorized_at', 'received_at', 'closed_at', 'created_by', 'updated_by', 'laboratory', 'schedule_reference']);
            $data['created_at'] = $model->created_at->toIso8601String();
            $data['authorized_at'] = $model->authorized_at ? Carbon::parse($model->authorized_at)->toIso8601String() : null;
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

            return $model->only(['id', 'request_id', 'patient_id', 'clinician_id', 'symptoms', 'diagnosis', 'indications', 'outcome', 'referral_destination', 'referral_reason', 'followup_status', 'followup_notes', 'referral_contact', 'status', 'care_type', 'no_medication_reason', 'closed_at', 'coordinator_reviewed_at']) + [
                'created_at' => $model->created_at->toIso8601String(),
                'patient' => new self($model->patient),
                'laboratory' => $model->careRequest?->laboratory,
                'schedule_reference' => $model->careRequest?->schedule_reference,
                'clinician' => ['id' => $model->clinician->id, 'name' => $model->clinician->name],
            ];
        }

        return [];
    }
}
