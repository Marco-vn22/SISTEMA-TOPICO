<?php

namespace App\Http\Controllers;

use App\Http\Resources\TopicoResource;
use App\Models\AuditLog;
use App\Models\CareEvent;
use App\Models\CareRequest;
use App\Models\Encounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CareWorkflowController extends Controller
{
    private function clinician(Request $request): void
    {
        abort_unless($request->user()->role === 'encargado', 403, 'Solo el encargado del tópico puede registrar la evaluación clínica.');
    }

    private function audit(Request $request, string $action, string $entity, int $id): void
    {
        AuditLog::query()->create(['user_id' => $request->user()->id, 'action' => $action, 'entity' => $entity, 'entity_id' => $id]);
    }

    public function tracking(Request $request, CareRequest $careRequest): JsonResponse
    {
        $user = $request->user();
        if ($user->role === 'estudiante') {
            abort_unless($careRequest->patient->user_id === $user->id, 404, 'Solicitud no encontrada.');
        } elseif ($user->role === 'docente') {
            abort_unless($careRequest->teacher_id === $user->id, 404, 'Solicitud no encontrada.');
        } else {
            abort_unless(in_array($user->role, ['coordinador', 'encargado'], true), 403);
        }
        $careRequest->load(['patient', 'teacher']);
        if ($user->role === 'encargado') {
            $careRequest->load(['encounter.patient', 'encounter.clinician', 'encounter.careRequest']);
        }
        $data = [
            'request' => (new TopicoResource($careRequest))->resolve($request),
            'events' => CareEvent::query()->where('care_request_id', $careRequest->id)->with('user:id,name')->orderBy('id')->get()->map(fn (CareEvent $event): array => [
                'id' => $event->id, 'action' => $event->action, 'actor' => $event->user?->name,
                'role' => $event->user_role, 'from_status' => $event->from_status, 'to_status' => $event->to_status,
                'created_at' => $event->created_at->toIso8601String(),
            ]),
        ];
        if ($user->role === 'encargado') {
            $data['prescriptions'] = $careRequest->encounter ? $careRequest->encounter->prescriptions()->with('product')->get()->map(fn ($rx): array => [
                'id' => $rx->id, 'product_id' => $rx->product_id, 'product' => $rx->product->name,
                'unit' => $rx->product->unit, 'quantity' => $rx->quantity, 'dispensed' => $rx->dispensed, 'instructions' => $rx->instructions,
            ]) : [];
            $this->audit($request, 'workflow_read', 'care_request', $careRequest->id);
        }

        return response()->json($data);
    }

    public function assessment(Request $request, CareRequest $careRequest): JsonResponse
    {
        $this->clinician($request);
        $data = $request->validate([
            'symptoms' => 'required|string|max:10000', 'diagnosis' => 'required|string|max:10000',
            'indications' => 'required|string|max:10000', 'care_type' => ['required', Rule::in(['urgencia', 'emergencia'])],
            'referral_destination' => 'nullable|string|max:255', 'referral_reason' => 'nullable|string|max:5000',
            'referral_contact' => 'nullable|string|max:255', 'no_medication_reason' => 'nullable|string|max:5000',
        ]);
        $encounter = DB::transaction(function () use ($request, $data, $careRequest): Encounter {
            $care = CareRequest::query()->lockForUpdate()->findOrFail($careRequest->id);
            abort_unless($care->status === 'en_atencion', 409, 'Recibe e inicia la atención antes de registrar el diagnóstico.');
            $encounter = Encounter::query()->where('request_id', $care->id)->lockForUpdate()->first();
            abort_if($encounter && $encounter->status !== 'borrador', 409, 'Esta atención ya está cerrada.');
            $encounter ??= new Encounter;
            $encounter->fill($data + ['request_id' => $care->id, 'patient_id' => $care->patient_id, 'clinician_id' => $request->user()->id, 'status' => 'borrador', 'outcome' => 'en_atencion']);
            $encounter->save();
            CareEvent::record($request->user(), $care->id, 'evaluacion_guardada');
            $this->audit($request, 'assessment_saved', 'encounter', $encounter->id);

            return $encounter;
        });

        return response()->json((new TopicoResource($encounter->load(['patient', 'clinician', 'careRequest'])))->resolve($request));
    }

    public function close(Request $request, Encounter $encounter): JsonResponse
    {
        $this->clinician($request);
        $closed = DB::transaction(function () use ($request, $encounter): Encounter {
            $care = CareRequest::query()->lockForUpdate()->findOrFail($encounter->request_id);
            $record = Encounter::query()->lockForUpdate()->findOrFail($encounter->id);
            abort_unless($care->status === 'en_atencion' && $record->status === 'borrador', 409, 'La atención ya está cerrada o no está en curso.');
            abort_if(blank($record->diagnosis) || blank($record->symptoms) || blank($record->indications), 422, 'Completa y guarda la evaluación, el diagnóstico y las indicaciones.');
            if ($record->care_type === 'emergencia') {
                abort_if(blank($record->referral_destination) || blank($record->referral_reason), 422, 'La emergencia requiere establecimiento de destino y motivo de derivación.');
                $outcome = 'derivada';
                $record->no_medication_reason = null;
            } else {
                abort_if(! $record->prescriptions()->exists() && blank($record->no_medication_reason), 422, 'Registra la receta o el motivo por el que no se requiere medicación.');
                $outcome = 'finalizada';
                $record->referral_destination = null;
                $record->referral_reason = null;
                $record->referral_contact = null;
                if ($record->prescriptions()->exists()) {
                    $record->no_medication_reason = null;
                }
            }
            $record->status = 'cerrada';
            $record->outcome = $outcome;
            $record->closed_at = now();
            $record->clinician_id = $request->user()->id;
            $record->save();
            $care->update(['status' => $outcome, 'closed_at' => now(), 'updated_by' => $request->user()->id]);
            CareEvent::record($request->user(), $care->id, $outcome === 'derivada' ? 'derivacion_registrada' : 'atencion_finalizada', 'en_atencion', $outcome);
            $this->audit($request, 'closed:'.$outcome, 'encounter', $record->id);

            return $record;
        });

        return response()->json((new TopicoResource($closed->load(['patient', 'clinician', 'careRequest'])))->resolve($request));
    }

    private function registryRecord(Encounter $encounter): array
    {
        return [
            'id' => $encounter->id, 'request_id' => $encounter->request_id,
            'patient' => $encounter->patient->only(['id', 'name', 'document']),
            'laboratory' => $encounter->careRequest?->laboratory,
            'schedule_reference' => $encounter->careRequest?->schedule_reference,
            'clinician' => $encounter->clinician->name, 'care_type' => $encounter->care_type,
            'outcome' => $encounter->outcome, 'closed_at' => $encounter->closed_at?->toIso8601String(),
            'prescriptions_count' => $encounter->prescriptions_count ?? 0,
            'reviewed_at' => $encounter->coordinator_reviewed_at?->toIso8601String(),
            'reviewed_by' => $encounter->reviewer?->name,
        ];
    }

    public function registry(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'coordinador', 403, 'Acceso restringido al coordinador.');

        return response()->json(Encounter::query()->where('status', 'cerrada')->with(['patient', 'clinician', 'careRequest', 'reviewer:id,name'])->withCount('prescriptions')->orderByDesc('closed_at')->get()->map(fn (Encounter $record): array => $this->registryRecord($record)));
    }

    public function review(Request $request, Encounter $encounter): JsonResponse
    {
        abort_unless($request->user()->role === 'coordinador', 403, 'Solo el coordinador puede recibir los registros cerrados.');
        $reviewed = DB::transaction(function () use ($request, $encounter): Encounter {
            CareRequest::query()->whereKey($encounter->request_id)->lockForUpdate()->firstOrFail();
            $record = Encounter::query()->lockForUpdate()->findOrFail($encounter->id);
            abort_unless($record->status === 'cerrada', 409, 'La atención debe estar cerrada para recibir su registro.');
            if (! $record->coordinator_reviewed_at) {
                $record->update(['coordinator_reviewed_at' => now(), 'coordinator_reviewed_by' => $request->user()->id]);
                CareEvent::record($request->user(), $record->request_id, 'registro_recibido');
                $this->audit($request, 'registry_received', 'encounter', $record->id);
            }

            return $record;
        });

        return response()->json($this->registryRecord($reviewed->load(['patient', 'clinician', 'careRequest', 'reviewer:id,name'])->loadCount('prescriptions')));
    }
}
