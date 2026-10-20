<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CareRequest;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    private function authorize(Request $request, bool $clinical = false): void
    {
        abort_unless(in_array($request->user()->role, $clinical ? ['encargado'] : ['coordinador', 'encargado'], true), 403, 'No tienes acceso a este módulo.');
    }

    private function audit(Request $request, string $action, string $entity, ?int $id = null): void
    {
        AuditLog::query()->create(['user_id' => $request->user()->id, 'action' => $action, 'entity' => $entity, 'entity_id' => $id]);
    }

    public function products(Request $request): JsonResponse
    {
        $this->authorize($request);
        $today = today()->toDateString();
        $near = today()->addDays(30)->toDateString();
        $products = Product::query()->with(['lots' => fn ($query) => $query->orderBy('expires_at')])->orderBy('name')->get()->map(function (Product $p) use ($today, $near): array {
            return $p->toArray() + [
                'stock' => $p->lots->where('expires_at', '>=', $today)->sum('quantity'),
                'expired_stock' => $p->lots->where('expires_at', '<', $today)->sum('quantity'),
                'expiring_stock' => $p->lots->whereBetween('expires_at', [$today, $near])->sum('quantity'),
            ];
        });

        return response()->json($products);
    }

    public function saveProduct(Request $request, ?Product $product = null): JsonResponse
    {
        $this->authorize($request);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'code' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($product?->id)],
            'kind' => ['required', Rule::in(['medicamento', 'insumo'])], 'unit' => 'required|string|max:50',
            'minimum_stock' => 'required|integer|min:0|max:1000000', 'active' => 'required|boolean',
        ]);
        $saved = DB::transaction(function () use ($request, $data, $product): Product {
            $saved = $product ? Product::query()->lockForUpdate()->findOrFail($product->id) : new Product;
            if ($saved->exists && $saved->kind !== $data['kind']) {
                abort_if($saved->lots()->exists() || Prescription::query()->where('product_id', $saved->id)->exists(), 422, 'No se puede cambiar el tipo de un producto con lotes o recetas.');
            }
            if (! $saved->exists) {
                $saved->created_by = $request->user()->id;
            }
            $saved->fill($data);
            $saved->updated_by = $request->user()->id;
            $saved->save();
            $this->audit($request, $product ? 'updated' : 'created', 'product', $saved->id);

            return $saved;
        });

        return response()->json($saved, $product ? 200 : 201);
    }

    public function movements(Request $request): JsonResponse
    {
        $this->authorize($request);

        return response()->json(StockMovement::query()->with(['lot.product', 'creator:id,name'])->latest()->limit(500)->get()->map(fn (StockMovement $m): array => [
            'id' => $m->id, 'product' => $m->lot->product->name, 'batch' => $m->lot->batch, 'quantity' => $m->quantity,
            'kind' => $m->kind, 'reason' => $m->reason, 'created_at' => $m->created_at->toIso8601String(), 'creator' => $m->creator->name,
        ]));
    }

    public function movement(Request $request): JsonResponse
    {
        $this->authorize($request);
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id', 'kind' => ['required', Rule::in(['entrada', 'salida', 'baja'])],
            'quantity' => 'required|integer|min:1|max:1000000', 'reason' => 'required|string|max:255',
            'batch' => 'nullable|required_if:kind,entrada|string|max:80',
            'expires_at' => 'nullable|required_if:kind,entrada|date_format:Y-m-d|after_or_equal:today',
            'stock_lot_id' => 'nullable|required_unless:kind,entrada|integer|exists:stock_lots,id',
        ]);
        DB::transaction(function () use ($request, $data): void {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            abort_unless($product->active || $data['kind'] === 'baja', 422, 'El producto está inactivo.');
            if ($data['kind'] === 'entrada') {
                $lot = StockLot::query()->firstOrNew(['product_id' => $product->id, 'batch' => $data['batch']]);
                abort_if($lot->exists && $lot->expires_at !== $data['expires_at'], 422, 'El vencimiento no coincide con el lote existente.');
                $lot->expires_at = $data['expires_at'];
                $lot->quantity = ($lot->quantity ?? 0) + $data['quantity'];
                $delta = $data['quantity'];
            } else {
                abort_if($product->kind === 'medicamento' && $data['kind'] === 'salida', 422, 'Los medicamentos se entregan desde una receta vinculada a una atención. Para descartarlos utiliza una baja.');
                $lot = StockLot::query()->where('product_id', $product->id)->lockForUpdate()->findOrFail($data['stock_lot_id']);
                abort_if($data['kind'] === 'salida' && $lot->expires_at < today()->toDateString(), 422, 'No se pueden utilizar productos vencidos.');
                abort_if($lot->quantity < $data['quantity'], 422, 'La cantidad supera las existencias del lote.');
                $delta = -$data['quantity'];
                $lot->quantity += $delta;
            }
            $lot->save();
            $movement = StockMovement::query()->create(['stock_lot_id' => $lot->id, 'kind' => $data['kind'], 'quantity' => $delta, 'reason' => $data['reason'], 'created_by' => $request->user()->id]);
            $this->audit($request, $data['kind'], 'stock_movement', $movement->id);
        });

        return response()->json(['message' => 'Movimiento registrado.'], 201);
    }

    public function prescriptions(Request $request): JsonResponse
    {
        $this->authorize($request, true);

        return response()->json(Prescription::query()->with(['product', 'encounter.patient'])->latest()->get()->map(fn (Prescription $p): array => [
            'id' => $p->id, 'encounter_id' => $p->encounter_id, 'patient' => $p->encounter->patient->name,
            'product' => $p->product->name, 'product_id' => $p->product_id, 'unit' => $p->product->unit,
            'instructions' => $p->instructions, 'quantity' => $p->quantity, 'dispensed' => $p->dispensed,
            'created_at' => $p->created_at->toIso8601String(),
        ]));
    }

    public function prescribe(Request $request): JsonResponse
    {
        $this->authorize($request, true);
        $data = $request->validate([
            'encounter_id' => 'required|integer|exists:encounters,id',
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('kind', 'medicamento')->where('active', true)],
            'quantity' => 'required|integer|min:1|max:1000000', 'instructions' => 'required|string|max:5000',
        ]);
        $saved = DB::transaction(function () use ($request, $data): Prescription {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            abort_unless($product->active && $product->kind === 'medicamento', 422, 'Selecciona un medicamento activo.');
            $saved = Prescription::query()->create($data + ['created_by' => $request->user()->id]);
            $this->audit($request, 'created', 'prescription', $saved->id);

            return $saved;
        });

        return response()->json(['id' => $saved->id], 201);
    }

    public function dispense(Request $request, Prescription $prescription): JsonResponse
    {
        $this->authorize($request, true);
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:1000000']);
        DB::transaction(function () use ($request, $data, $prescription): void {
            $product = Product::query()->lockForUpdate()->findOrFail($prescription->product_id);
            $rx = Prescription::query()->lockForUpdate()->findOrFail($prescription->id);
            abort_unless($product->active, 422, 'El medicamento está inactivo.');
            abort_if($data['quantity'] > $rx->quantity - $rx->dispensed, 422, 'La entrega supera la cantidad pendiente de la receta.');
            $lots = StockLot::query()->where('product_id', $product->id)->where('expires_at', '>=', today()->toDateString())->where('quantity', '>', 0)->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
            abort_if($lots->sum('quantity') < $data['quantity'], 422, 'No hay existencias vigentes suficientes.');
            $remaining = $data['quantity'];
            foreach ($lots as $lot) {
                if ($remaining === 0) {
                    break;
                }
                $take = min($remaining, $lot->quantity);
                $lot->decrement('quantity', $take);
                StockMovement::query()->create(['stock_lot_id' => $lot->id, 'prescription_id' => $rx->id, 'kind' => 'entrega', 'quantity' => -$take, 'reason' => 'Entrega de receta #'.$rx->id, 'created_by' => $request->user()->id]);
                $remaining -= $take;
            }
            $rx->increment('dispensed', $data['quantity']);
            $this->audit($request, 'dispensed', 'prescription', $rx->id);
        });

        return response()->json(['message' => 'Entrega registrada.']);
    }

    public function followup(Request $request, Encounter $encounter): JsonResponse
    {
        $this->authorize($request, true);
        abort_unless($encounter->outcome === 'derivada', 422, 'La atención no tiene una derivación externa.');
        $data = $request->validate([
            'followup_status' => ['required', Rule::in(['pendiente', 'contactado', 'cerrado'])],
            'followup_notes' => 'required|string|max:10000', 'referral_contact' => 'nullable|string|max:255',
        ]);
        DB::transaction(function () use ($request, $data, $encounter): void {
            $encounter->update($data + ['followup_by' => $request->user()->id]);
            $this->audit($request, 'followup:'.$data['followup_status'], 'encounter', $encounter->id);
        });

        return response()->json(['message' => 'Seguimiento actualizado.']);
    }

    public function reports(Request $request): JsonResponse
    {
        $this->authorize($request);
        $data = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $from = $data['from'].' 00:00:00';
        $to = $data['to'].' 23:59:59';
        $statuses = CareRequest::query()->whereBetween('created_at', [$from, $to])->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $consumption = StockMovement::query()->join('stock_lots', 'stock_lots.id', '=', 'stock_movements.stock_lot_id')->join('products', 'products.id', '=', 'stock_lots.product_id')->whereBetween('stock_movements.created_at', [$from, $to])->whereIn('stock_movements.kind', ['salida', 'entrega'])->selectRaw('products.name, products.unit, SUM(-stock_movements.quantity) as quantity')->groupBy('products.id', 'products.name', 'products.unit')->orderBy('quantity', 'desc')->get();
        $this->audit($request, 'report_read', 'report');

        return response()->json([
            'from' => $data['from'], 'to' => $data['to'], 'statuses' => $statuses, 'consumption' => $consumption,
            'patients_attended' => Encounter::query()->whereBetween('created_at', [$from, $to])->distinct()->count('patient_id'),
            'attentions' => Encounter::query()->whereBetween('created_at', [$from, $to])->count(),
            'referrals' => Encounter::query()->whereBetween('created_at', [$from, $to])->where('outcome', 'derivada')->count(),
        ]);
    }

    public function importPatients(Request $request): JsonResponse
    {
        $this->authorize($request);
        $data = $request->validate([
            'rows' => 'required|array|min:1|max:200', 'rows.*.name' => 'required|string|max:255',
            'rows.*.document' => 'required|string|max:30|distinct', 'rows.*.classroom' => 'nullable|string|max:100',
            'rows.*.phone' => 'nullable|string|max:30',
        ]);
        $counts = DB::transaction(function () use ($request, $data): array {
            $created = 0;
            $updated = 0;
            foreach ($data['rows'] as $row) {
                $patient = Patient::query()->firstOrNew(['document' => $row['document']]);
                if ($patient->exists) {
                    $updated++;
                } else {
                    $created++;
                    $patient->created_by = $request->user()->id;
                }
                $patient->fill(collect($row)->only(['name', 'document', 'classroom', 'phone'])->all());
                $patient->updated_by = $request->user()->id;
                $patient->save();
                $this->audit($request, 'imported', 'patient', $patient->id);
            }

            return ['created' => $created, 'updated' => $updated];
        });

        return response()->json($counts);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'coordinador', 403, 'Acceso restringido al coordinador.');

        return response()->json(AuditLog::query()->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->orderByDesc('audit_logs.id')->limit(300)->get(['audit_logs.id', 'audit_logs.action', 'audit_logs.entity', 'audit_logs.entity_id', 'audit_logs.created_at', 'users.name as user']));
    }
}
