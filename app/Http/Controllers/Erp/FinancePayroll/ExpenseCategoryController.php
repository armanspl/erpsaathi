<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryController extends Controller
{
    /** Flat, active, top-level categories — feeds the Create Office Expense dropdown. */
    public function index()
    {
        return response()->json(
            ExpenseCategory::whereNull('parent_id')->where('is_active', true)->orderBy('name')->get()
        );
    }

    /** Full hierarchy (active + inactive) for the Category Types manager. Transport's children are computed live from active vehicles, never stored. */
    public function tree()
    {
        $categories = ExpenseCategory::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        $vehicleChildren = Vehicle::where('status', 'Active')
            ->orderBy('vehicle_no')
            ->get()
            ->map(fn (Vehicle $v) => [
                'id' => "vehicle-{$v->id}",
                'name' => $v->vehicle_no . ($v->type ? " ({$v->type})" : ''),
                'type' => 'transport',
                'is_active' => true,
                'synced' => true,
            ]);

        return response()->json(
            $categories->map(function (ExpenseCategory $c) use ($vehicleChildren) {
                $children = $c->children->map(fn (ExpenseCategory $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'type' => $child->type,
                    'is_active' => $child->is_active,
                    'synced' => false,
                ]);

                if ($c->type === 'transport') {
                    $children = $vehicleChildren;
                }

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'type' => $c->type,
                    'is_active' => $c->is_active,
                    'children' => $children->values(),
                ];
            })->values()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories,name',
            'description' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:expense_categories,id',
        ]);

        if (! empty($data['parent_id'])) {
            $parent = ExpenseCategory::findOrFail($data['parent_id']);
            if ($parent->type === 'transport') {
                throw ValidationException::withMessages(['parent_id' => 'Transport sub-categories sync automatically from active vehicles and cannot be added manually.']);
            }
            $data['type'] = $parent->type;
        }

        $category = ExpenseCategory::create($data);

        return response()->json($category, 201);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('expense_categories', 'name')->ignore($expenseCategory->id)],
            'description' => 'nullable|string|max:255',
        ]);

        $expenseCategory->update($data);

        return response()->json($expenseCategory);
    }

    public function toggleActive(ExpenseCategory $expenseCategory)
    {
        $expenseCategory->update(['is_active' => ! $expenseCategory->is_active]);

        return response()->json($expenseCategory);
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        if ($expenseCategory->children()->exists()) {
            throw ValidationException::withMessages(['name' => 'Remove this category\'s sub-categories first.']);
        }

        $expenseCategory->delete();

        return response()->json(['success' => true]);
    }
}
