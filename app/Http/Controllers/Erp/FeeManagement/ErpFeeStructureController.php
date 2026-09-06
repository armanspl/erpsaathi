<?php

namespace App\Http\Controllers\Erp\FeeManagement;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ErpFeeStructure;
use App\Models\ErpFeeStructurePlan;
use App\Models\ErpFeeStructurePlanItem;
use App\Models\ErpFeeStructurePlanScope;
use App\Models\TransportRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ErpFeeStructureController extends Controller
{
    public function index(Request $request)
    {
        // Legacy flat rows — kept for Fee Heads usage count and old data consumers.
        if ($request->boolean('flat')) {
            return response()->json(
                ErpFeeStructure::with(['academicSession:id,name', 'schoolClass:id,name', 'feeHead:id,name'])
                    ->orderByDesc('academic_session_id')
                    ->get()
            );
        }

        $query = ErpFeeStructurePlan::with([
            'academicSession:id,name',
            'scopes.branch:id,name',
            'scopes.schoolClass:id,name',
            'scopes.section:id,name,school_class_id',
            'items',
        ])->orderByDesc('id');

        if ($request->filled('branch_id')) {
            $branchId = (int) $request->query('branch_id');
            $query->whereHas('scopes', fn ($q) => $q->where('branch_id', $branchId));
        }
        if ($request->filled('school_class_id')) {
            $classId = (int) $request->query('school_class_id');
            $query->whereHas('scopes', fn ($q) => $q->where('school_class_id', $classId));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }
        if ($request->filled('academic_session_id')) {
            $query->where('academic_session_id', $request->query('academic_session_id'));
        } else {
            AcademicSession::applySessionIdFilter($query, $request);
        }

        $plans = $query->get()->map(fn (ErpFeeStructurePlan $plan) => $this->present($plan));
        $transport = $this->presentTransportRoutes($request);

        return response()->json($plans->concat($transport)->values());
    }

    public function store(Request $request)
    {
        $data = $this->validatedPlan($request);

        $plan = DB::transaction(function () use ($data) {
            $plan = ErpFeeStructurePlan::create([
                'academic_session_id' => $data['academic_session_id'],
                'type' => $data['type'],
                'status' => $data['status'] ?? 'active',
            ]);
            $this->syncScopes($plan, $data['scopes']);
            $this->syncItems($plan, $data['items']);

            return $plan->fresh($this->eager());
        });

        return response()->json($this->present($plan), 201);
    }

    public function update(Request $request, ErpFeeStructurePlan $plan)
    {
        $data = $this->validatedPlan($request);

        $plan = DB::transaction(function () use ($plan, $data) {
            $plan->update([
                'academic_session_id' => $data['academic_session_id'],
                'type' => $data['type'],
                'status' => $data['status'] ?? $plan->status,
            ]);
            $this->syncScopes($plan, $data['scopes']);
            $this->syncItems($plan, $data['items']);

            return $plan->fresh($this->eager());
        });

        return response()->json($this->present($plan));
    }

    public function destroy(ErpFeeStructurePlan $plan)
    {
        $plan->delete();

        return response()->json(['success' => true]);
    }

    public function duplicate(ErpFeeStructurePlan $plan)
    {
        $plan->load(['scopes', 'items']);

        $copy = DB::transaction(function () use ($plan) {
            $newPlan = ErpFeeStructurePlan::create([
                'academic_session_id' => $plan->academic_session_id,
                'type' => $plan->type,
                'status' => $plan->status,
            ]);

            foreach ($plan->scopes as $scope) {
                $newPlan->scopes()->create([
                    'branch_id' => $scope->branch_id,
                    'school_class_id' => $scope->school_class_id,
                    'section_id' => $scope->section_id,
                ]);
            }
            foreach ($plan->items as $item) {
                $newPlan->items()->create([
                    'label' => $item->label,
                    'amount' => $item->amount,
                    'frequency' => $item->frequency,
                    'sort_order' => $item->sort_order,
                ]);
            }

            return $newPlan->fresh($this->eager());
        });

        return response()->json($this->present($copy), 201);
    }

    /** @return list<string> */
    private function eager(): array
    {
        return [
            'academicSession:id,name',
            'scopes.branch:id,name',
            'scopes.schoolClass:id,name',
            'scopes.section:id,name,school_class_id',
            'items',
        ];
    }

    private function validatedPlan(Request $request): array
    {
        return $request->validate([
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'type' => ['required', Rule::in(['Faculty', 'Transport'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'scopes' => 'required|array|min:1',
            'scopes.*.branch_id' => 'nullable|exists:branches,id',
            'scopes.*.school_class_id' => 'required|exists:school_classes,id',
            'scopes.*.section_id' => 'nullable|exists:sections,id',
            'items' => 'required|array|min:1',
            'items.*.label' => 'required|string|max:150',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.frequency' => ['required', Rule::in(['one_time', 'monthly', 'quarterly', 'annual'])],
        ]);
    }

    private function syncScopes(ErpFeeStructurePlan $plan, array $scopes): void
    {
        $plan->scopes()->delete();
        $seen = [];
        foreach ($scopes as $scope) {
            $key = implode(':', [
                $scope['branch_id'] ?? 'null',
                $scope['school_class_id'],
                $scope['section_id'] ?? 'null',
            ]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $plan->scopes()->create([
                'branch_id' => $scope['branch_id'] ?? null,
                'school_class_id' => $scope['school_class_id'],
                'section_id' => $scope['section_id'] ?? null,
            ]);
        }
    }

    private function syncItems(ErpFeeStructurePlan $plan, array $items): void
    {
        $plan->items()->delete();
        foreach (array_values($items) as $index => $item) {
            $plan->items()->create([
                'label' => $item['label'],
                'amount' => $item['amount'],
                'frequency' => $item['frequency'],
                'sort_order' => $index,
            ]);
        }
    }

    private function present(ErpFeeStructurePlan $plan): array
    {
        $scopes = $plan->scopes->map(function (ErpFeeStructurePlanScope $scope) {
            $branch = $scope->branch?->name;
            $class = $scope->schoolClass?->name;
            $section = $scope->section?->name;
            $label = trim(($branch ? $branch.' / ' : '').($class ?? ''), ' /');
            if ($section) {
                $label .= ' · '.$section;
            }

            return [
                'id' => $scope->id,
                'branch_id' => $scope->branch_id,
                'school_class_id' => $scope->school_class_id,
                'section_id' => $scope->section_id,
                'branch_name' => $branch,
                'class_name' => $class,
                'section_name' => $section,
                'label' => $label !== '' ? $label : '—',
            ];
        })->values();

        $items = $plan->items->map(fn (ErpFeeStructurePlanItem $item) => [
            'id' => $item->id,
            'label' => $item->label,
            'amount' => (float) $item->amount,
            'frequency' => $item->frequency,
            'sort_order' => $item->sort_order,
        ])->values();

        $amount = (float) $plan->items->sum('amount');

        return [
            'id' => $plan->id,
            'academic_session_id' => $plan->academic_session_id,
            'academic_session' => $plan->academicSession ? [
                'id' => $plan->academicSession->id,
                'name' => $plan->academicSession->name,
            ] : null,
            'type' => $plan->type,
            'status' => $plan->status,
            'title' => $plan->type.' Fee',
            'scope_count' => $scopes->count(),
            'scopes' => $scopes,
            'scope_summary' => $scopes->pluck('label')->filter()->take(6)->implode(', '),
            'items' => $items,
            'amount' => $amount,
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at,
        ];
    }

    /** Transport routes with stop fares — shown alongside faculty plans in the list. */
    private function presentTransportRoutes(Request $request): \Illuminate\Support\Collection
    {
        $query = TransportRoute::query()
            ->with(['branch:id,name', 'stops', 'vehicle:id,vehicle_no'])
            ->withCount('stops')
            ->orderBy('name');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        return $query->get()
            ->filter(fn (TransportRoute $route) => $route->stops->contains(fn ($s) => (float) $s->fare > 0))
            ->map(function (TransportRoute $route) {
            $stops = $route->stops;
            $fares = $stops->pluck('fare')->map(fn ($f) => (float) $f)->filter(fn ($f) => $f > 0);
            $minFare = $fares->min();
            $maxFare = $fares->max();

            $scopeParts = array_filter([
                $route->branch?->name,
                $route->vehicle?->vehicle_no ? 'Bus '.$route->vehicle->vehicle_no : null,
                ($route->start_point && $route->end_point) ? $route->start_point.' → '.$route->end_point : null,
            ]);

            $items = $stops->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->stop_name,
                'amount' => (float) $s->fare,
                'frequency' => 'monthly',
                'km' => $s->km !== null ? (float) $s->km : null,
            ])->values();

            return [
                'id' => 'transport-'.$route->id,
                'transport_route_id' => $route->id,
                'is_transport' => true,
                'academic_session_id' => null,
                'academic_session' => null,
                'type' => 'Transport',
                'status' => strtolower((string) $route->status) === 'active' ? 'active' : 'inactive',
                'title' => $route->name,
                'scope_count' => (int) $route->stops_count,
                'scopes' => [],
                'scope_summary' => implode(' · ', $scopeParts) ?: '—',
                'items' => $items,
                'amount' => (float) ($maxFare ?? 0),
                'amount_range' => $fares->count() > 1 && $minFare !== $maxFare
                    ? ['min' => (float) $minFare, 'max' => (float) $maxFare]
                    : null,
                'created_at' => $route->created_at,
                'updated_at' => $route->updated_at,
            ];
        });
    }
}
