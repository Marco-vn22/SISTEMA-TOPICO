<?php

namespace App\Http\Controllers;

use App\Http\Resources\TopicoResource;
use App\Models\AccessToken;
use App\Models\AuditLog;
use App\Models\CareEvent;
use App\Models\CareRequest;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TopicoController extends Controller
{
    private function roles(Request $request, array $roles): void
    {
        abort_unless(in_array($request->user()->role, $roles, true), 403, 'No tienes permiso para realizar esta acción.');
    }

    private function audit(Request $request, string $action, string $entity, ?int $id = null): void
    {
        AuditLog::query()->create(['user_id' => $request->user()?->id, 'action' => $action, 'entity' => $entity, 'entity_id' => $id]);
    }

    private function resource(Request $request, mixed $model, int $status = 200): JsonResponse
    {
        return response()->json((new TopicoResource($model))->resolve($request), $status);
    }

    private function collection(Request $request, mixed $models): JsonResponse
    {
        return response()->json(TopicoResource::collection($models)->resolve($request));
    }

    private function scopedRequests(Request $request): Builder
    {
        $user = $request->user();

        return CareRequest::query()
            ->when($user->role === 'estudiante', fn (Builder $q) => $q->whereHas('patient', fn (Builder $p) => $p->where('user_id', $user->id)))
            ->when($user->role === 'docente', fn (Builder $q) => $q->where('teacher_id', $user->id));
    }

    private function requestRelations(Request $request): array
    {
        return $request->user()->role === 'encargado' ? ['patient', 'teacher', 'encounter.patient', 'encounter.clinician', 'encounter.careRequest'] : ['patient', 'teacher'];
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            'password' => 'required|string|max:255',
        ]);
        $key = 'topico-login:'.hash('sha256', $data['dni'].'|'.$request->ip());
        $accountKey = 'topico-login-account:'.hash('sha256', $data['dni']);
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Demasiados intentos. Espera un minuto e inténtalo nuevamente.');
        abort_if(RateLimiter::tooManyAttempts($accountKey, 20), 429, 'Demasiados intentos. Espera unos minutos e inténtalo nuevamente.');
        $user = User::query()->where('dni', $data['dni'])->first();
        if (! $user || ! $user->active || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit($accountKey, 300);

            return response()->json(['message' => 'El DNI o la contraseña no son correctos.'], 422);
        }
        RateLimiter::clear($key);
        RateLimiter::clear($accountKey);
        $token = Str::random(80);
        AccessToken::query()->where('expires_at', '<=', now())->delete();
        AccessToken::query()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(config('topico.session_hours'))]);
        $request->setUserResolver(fn () => $user);
        $this->audit($request, 'login', 'user', $user->id);

        return response()->json(['token' => $token, 'user' => (new TopicoResource($user->load('patient')))->resolve($request)]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->resource($request, $request->user()->load('patient'));
    }

    public function logout(Request $request): JsonResponse
    {
        AccessToken::query()->whereKey($request->attributes->get('access_token_id'))->delete();
        $this->audit($request, 'logout', 'user', $request->user()->id);

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate(['current_password' => 'required|string|max:255', 'password' => 'required|string|min:10|max:255|confirmed']);
        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless(Hash::check($data['current_password'], $user->password), 422, 'La contraseña actual no es correcta.');
            abort_if($data['password'] === $user->dni || Hash::check($data['password'], $user->password), 422, 'Elige una contraseña personal diferente de tu DNI y de tu contraseña actual.');
            $user->update(['password' => $data['password'], 'uses_initial_password' => false]);
            AccessToken::query()->where('user_id', $request->user()->id)->whereKeyNot($request->attributes->get('access_token_id'))->delete();
            $this->audit($request, 'password_changed', 'user', $request->user()->id);
        });

        return response()->json(['message' => 'Contraseña actualizada.']);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $query = $this->scopedRequests($request);
        $alerts = null;
        if (in_array($request->user()->role, ['coordinador', 'encargado'], true)) {
            $products = Product::query()->where('active', true)->withSum(['lots as available_stock' => fn (Builder $q) => $q->where('expires_at', '>=', today()->toDateString())], 'quantity')->get();
            $alerts = [
                'low_stock' => $products->filter(fn (Product $p) => ($p->available_stock ?? 0) <= $p->minimum_stock)->count(),
                'expiring' => StockLot::query()->where('quantity', '>', 0)->whereBetween('expires_at', [today()->toDateString(), today()->addDays(30)->toDateString()])->distinct()->count('product_id'),
                'expired' => StockLot::query()->where('quantity', '>', 0)->where('expires_at', '<', today()->toDateString())->distinct()->count('product_id'),
                'pending_referrals' => $request->user()->role === 'encargado' ? Encounter::query()->where('outcome', 'derivada')->where('followup_status', '!=', 'cerrado')->count() : 0,
                'pending_registry' => $request->user()->role === 'coordinador' ? Encounter::query()->where('status', 'cerrada')->whereNull('coordinator_reviewed_at')->count() : 0,
            ];
        }

        return response()->json([
            'alerts' => $alerts,
            'total_patients' => in_array($request->user()->role, ['encargado', 'coordinador'], true) ? Patient::query()->count() : 0,
            'today' => (clone $query)->whereDate('created_at', today())->count(),
            'waiting' => (clone $query)->whereIn('status', ['autorizada', 'en_espera'])->count(),
            'pending' => (clone $query)->where('status', 'pendiente')->count(),
            'completed' => (clone $query)->whereIn('status', ['finalizada', 'derivada'])->whereDate('closed_at', today())->count(),
            'referrals' => (clone $query)->where('status', 'derivada')->whereDate('closed_at', today())->count(),
            'recent' => TopicoResource::collection((clone $query)->with($this->requestRelations($request))->latest()->limit(6)->get())->resolve($request),
        ]);
    }

    public function directory(Request $request): JsonResponse
    {
        $patients = Patient::query()->select(['id', 'name']);
        if ($request->user()->role === 'estudiante') {
            $patients->where('user_id', $request->user()->id);
        }

        return response()->json([
            'patients' => $patients->orderBy('name')->get(),
            'teachers' => User::query()->where('active', true)->where('role', 'docente')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->roles($request, ['coordinador']);

        return $this->collection($request, User::query()->with('patient')->orderBy('name')->get());
    }

    public function saveUser(Request $request, ?User $user = null): JsonResponse
    {
        $this->roles($request, ['coordinador']);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/', Rule::unique('users')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', Rule::in(['coordinador', 'encargado', 'docente', 'estudiante'])],
            'password' => 'prohibited',
            'reset_password' => 'sometimes|boolean',
            'active' => 'required|boolean',
            'patient_id' => ['nullable', 'required_if:role,estudiante', 'integer', 'exists:patients,id'],
        ]);
        $data['email'] = ! empty($data['email']) ? Str::lower(trim($data['email'])) : null;
        if ($user?->id === $request->user()->id) {
            abort_unless($data['active'] && $data['role'] === 'coordinador', 422, 'No puedes desactivar tu cuenta ni cambiar tu propio rol.');
            abort_if($data['reset_password'] ?? false, 422, 'Utiliza Cambiar contraseña en Configuración.');
        }
        if ($user && (! $data['active'] || $data['role'] !== $user->role)) {
            abort_if(CareRequest::query()->where('teacher_id', $user->id)->where('status', 'pendiente')->exists(), 422, 'Este docente tiene permisos pendientes. Resuélvelos antes de desactivar o cambiar su rol.');
        }
        $saved = DB::transaction(function () use ($request, $data, $user): User {
            $patient = $data['role'] === 'estudiante' ? Patient::query()->lockForUpdate()->findOrFail($data['patient_id']) : null;
            abort_if($patient && $patient->user_id && $patient->user_id !== $user?->id, 422, 'El paciente ya está vinculado a otra cuenta.');
            abort_if($patient && Patient::query()->where('document', $data['dni'])->whereKeyNot($patient->id)->exists(), 422, 'El DNI pertenece a otra ficha de paciente.');
            $attributes = collect($data)->except(['patient_id', 'password', 'reset_password'])->all();
            if (! $user || ($data['reset_password'] ?? false) || ($user->uses_initial_password && $user->dni !== $data['dni'])) {
                $attributes['password'] = $data['dni'];
                $attributes['uses_initial_password'] = true;
            }
            $saved = $user ?? new User;
            $saved->fill($attributes)->save();
            Patient::query()->where('user_id', $saved->id)->where('id', '!=', $patient?->id ?? 0)->update(['user_id' => null, 'updated_by' => $request->user()->id]);
            if ($patient) {
                $patient->update(['user_id' => $saved->id, 'document' => $saved->dni, 'updated_by' => $request->user()->id]);
            }
            AccessToken::query()->where('user_id', $saved->id)->delete();
            $this->audit($request, $user ? 'updated' : 'created', 'user', $saved->id);
            if ($data['reset_password'] ?? false) {
                $this->audit($request, 'initial_password_reset', 'user', $saved->id);
            }

            return $saved;
        });

        return $this->resource($request, $saved->load('patient'), $user ? 200 : 201);
    }

    public function patients(Request $request): JsonResponse
    {
        $this->roles($request, ['coordinador', 'encargado']);

        return $this->collection($request, Patient::query()->orderBy('name')->get());
    }

    public function savePatient(Request $request, ?Patient $patient = null): JsonResponse
    {
        $this->roles($request, ['coordinador', 'encargado']);
        $rules = [
            'name' => 'required|string|max:255',
            'document' => ['required', 'string', 'max:30', Rule::unique('patients')->ignore($patient?->id)],
            'birth_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'phone' => 'nullable|string|max:30',
            'emergency_contact' => 'nullable|string|max:255', 'emergency_phone' => 'nullable|string|max:30',
        ];
        if ($request->user()->role === 'encargado') {
            $rules += ['allergies' => 'nullable|string|max:5000', 'background' => 'nullable|string|max:10000'];
        }
        $data = $request->validate($rules);
        if ($patient?->user_id) {
            $accountDni = User::query()->whereKey($patient->user_id)->value('dni');
            abort_if($accountDni && $data['document'] !== $accountDni, 422, 'El DNI está vinculado a una cuenta. El coordinador puede actualizarlo desde Usuarios y permisos.');
        }
        $saved = DB::transaction(function () use ($request, $data, $patient): Patient {
            $saved = $patient ?? new Patient;
            if (! $saved->exists) {
                $saved->created_by = $request->user()->id;
            }
            $saved->fill($data);
            $saved->updated_by = $request->user()->id;
            $saved->save();
            $saved->ensureAccessAccount($request->user());
            $this->audit($request, $patient ? 'updated' : 'created', 'patient', $saved->id);

            return $saved;
        });

        return $this->resource($request, $saved, $patient ? 200 : 201);
    }

    public function requests(Request $request): JsonResponse
    {
        return $this->collection($request, $this->scopedRequests($request)->with($this->requestRelations($request))->latest()->get());
    }

    public function createRequest(Request $request): JsonResponse
    {
        $this->roles($request, ['estudiante', 'encargado']);
        $role = $request->user()->role;
        $data = $request->validate([
            'patient_id' => [$role === 'estudiante' ? 'prohibited' : 'required', 'integer', 'exists:patients,id'],
            'teacher_id' => ['nullable', Rule::requiredIf($role === 'estudiante'), 'integer', Rule::exists('users', 'id')->where('role', 'docente')->where('active', true)],
            'reason' => [$role === 'estudiante' ? 'prohibited' : 'required', 'string', 'max:5000'],
            'emergency' => [$role === 'estudiante' ? 'prohibited' : 'sometimes', 'boolean'],
            'laboratory' => [$role === 'estudiante' ? 'prohibited' : 'nullable', 'string', 'max:150'],
            'schedule_reference' => [$role === 'estudiante' ? 'prohibited' : 'nullable', 'string', 'max:100'],
            'created_at' => 'prohibited', 'authorized_at' => 'prohibited', 'received_at' => 'prohibited', 'closed_at' => 'prohibited', 'updated_at' => 'prohibited',
        ]);
        if ($role === 'estudiante') {
            $data['reason'] = 'Pendiente de registrar por el encargado del tópico.';
        }
        $patient = $role === 'estudiante' ? Patient::query()->where('user_id', $request->user()->id)->first() : Patient::query()->find($data['patient_id'] ?? 0);
        abort_unless($patient, 422, 'Selecciona un paciente registrado y vincula su cuenta si es estudiante.');
        $emergency = $role === 'encargado' && ($data['emergency'] ?? false);
        $saved = DB::transaction(function () use ($request, $data, $role, $patient, $emergency): CareRequest {
            Patient::query()->whereKey($patient->id)->lockForUpdate()->firstOrFail();
            $existing = CareRequest::query()->where('patient_id', $patient->id)->whereIn('status', ['pendiente', 'autorizada', 'en_espera', 'en_atencion'])->lockForUpdate()->first();
            if ($existing) {
                if ($emergency && $existing->status === 'pendiente') {
                    $existing->update(['status' => 'en_espera', 'priority' => 'emergencia', 'emergency' => true, 'received_at' => now(), 'reason' => $existing->reason."\nIngreso de emergencia: ".$data['reason'], 'laboratory' => $data['laboratory'] ?? $existing->laboratory, 'schedule_reference' => $data['schedule_reference'] ?? $existing->schedule_reference, 'updated_by' => $request->user()->id]);
                    CareEvent::record($request->user(), $existing->id, 'ingreso_emergencia', 'pendiente', 'en_espera');
                    $this->audit($request, 'emergency_received', 'care_request', $existing->id);

                    return $existing;
                }
                abort(409, 'Este paciente ya tiene una solicitud o atención en curso (#'.$existing->id.'). Continúa con ese registro.');
            }
            $saved = CareRequest::query()->create([
                'patient_id' => $patient->id, 'teacher_id' => $role === 'estudiante' ? $data['teacher_id'] : null,
                'reason' => $data['reason'], 'status' => $role === 'estudiante' ? 'pendiente' : 'en_espera',
                'laboratory' => $data['laboratory'] ?? null, 'schedule_reference' => $data['schedule_reference'] ?? null,
                'priority' => $emergency ? 'emergencia' : 'normal', 'emergency' => $emergency,
                'authorized_at' => null,
                'received_at' => $role === 'encargado' ? now() : null,
                'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
            ]);
            $this->audit($request, 'created', 'care_request', $saved->id);
            CareEvent::record($request->user(), $saved->id, $emergency ? 'ingreso_emergencia' : 'solicitud_registrada', null, $saved->status);
            if ($role === 'encargado') {
                CareEvent::record($request->user(), $saved->id, 'paciente_recibido', null, 'en_espera');
            }

            return $saved;
        });

        return $this->resource($request, $saved->load($this->requestRelations($request)), 201);
    }

    public function updateRequest(Request $request, CareRequest $careRequest): JsonResponse
    {
        $this->roles($request, ['docente', 'encargado']);
        $clinical = $request->user()->role === 'encargado';
        abort_unless($this->scopedRequests($request)->whereKey($careRequest->id)->exists(), 404, 'Solicitud no encontrada.');
        $data = $request->validate([
            'status' => ['required', Rule::in(['autorizada', 'en_espera', 'en_atencion', 'cancelada'])],
            'priority' => ['sometimes', Rule::in(['normal', 'urgente', 'emergencia'])],
            'authorization_note' => 'nullable|string|max:1000',
            'reason' => $clinical ? ['sometimes', 'required', 'string', 'max:5000'] : ['prohibited'],
            'laboratory' => [$clinical ? 'nullable' : 'prohibited', 'string', 'max:150'],
            'schedule_reference' => [$clinical ? 'nullable' : 'prohibited', 'string', 'max:100'],
            'created_at' => 'prohibited', 'authorized_at' => 'prohibited', 'received_at' => 'prohibited', 'closed_at' => 'prohibited', 'updated_at' => 'prohibited',
        ]);
        if (! $clinical) {
            abort_unless($data['status'] === 'autorizada' && ! $request->hasAny(['priority', 'authorization_note']), 422, 'El docente solo puede conceder el permiso.');
        }
        $saved = DB::transaction(function () use ($request, $data, $careRequest): CareRequest {
            $record = CareRequest::query()->lockForUpdate()->findOrFail($careRequest->id);
            $previousStatus = $record->status;
            $previousPriority = $record->priority;
            $role = $request->user()->role;
            $status = $data['status'];
            if ($role === 'docente') {
                abort_unless($record->teacher_id === $request->user()->id && $record->status === 'pendiente' && $status === 'autorizada', 409, 'Este permiso ya fue resuelto o no te corresponde.');
                if ($status === 'autorizada') {
                    $record->authorized_at ??= now();
                }
            } elseif ($role === 'encargado') {
                abort_unless(in_array($record->status, ['autorizada', 'en_espera', 'en_atencion'], true) && in_array($status, ['en_espera', 'en_atencion'], true), 409, 'La solicitud debe estar autorizada o recibida antes de atenderse.');
                abort_if($record->status === 'en_atencion' && $status !== 'en_atencion', 409, 'La atención ya está iniciada.');
                $record->priority = $data['priority'] ?? $record->priority;
                $record->received_at ??= now();
                $record->fill(collect($data)->only(['reason', 'laboratory', 'schedule_reference'])->all());
                abort_if($status === 'en_atencion' && $record->reason === 'Pendiente de registrar por el encargado del tópico.', 422, 'Registra el motivo de atención antes de iniciar la atención.');
            } else {
                abort(403, 'No tienes permiso para cambiar el estado de la solicitud.');
            }
            if ($status === 'cancelada') {
                abort_if(empty($data['authorization_note']), 422, 'Indica el motivo de la cancelación o rechazo.');
                $record->closed_at = now();
            }
            $record->status = $status;
            if (array_key_exists('authorization_note', $data)) {
                $record->authorization_note = $data['authorization_note'];
            }
            $record->updated_by = $request->user()->id;
            $record->save();
            $this->audit($request, 'status:'.$status, 'care_request', $record->id);
            if ($previousStatus !== $status) {
                $action = match ($status) {
                    'autorizada' => 'permiso_autorizado',
                    'en_espera' => 'paciente_recibido',
                    'en_atencion' => 'atencion_iniciada',
                    default => 'solicitud_cancelada',
                };
                CareEvent::record($request->user(), $record->id, $action, $previousStatus, $status);
            } elseif ($previousPriority !== $record->priority) {
                CareEvent::record($request->user(), $record->id, 'prioridad_actualizada');
            }

            return $record;
        });

        return $this->resource($request, $saved->load($this->requestRelations($request)));
    }

    public function encounters(Request $request): JsonResponse
    {
        $this->roles($request, ['encargado']);
        $this->audit($request, 'read', 'encounter_list');

        return $this->collection($request, Encounter::query()->with(['patient', 'clinician', 'careRequest'])->latest()->get());
    }

    public function history(Request $request, Patient $patient): JsonResponse
    {
        $this->roles($request, ['encargado']);
        $this->audit($request, 'history_read', 'patient', $patient->id);

        return $this->collection($request, Encounter::query()->where('patient_id', $patient->id)->where('status', 'cerrada')->with(['patient', 'clinician', 'careRequest'])->latest()->get());
    }
}
