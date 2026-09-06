<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\FinancePayroll\Concerns\ResolvesPayrollEmployee;
use App\Models\SalaryStructure;
use App\Models\Staff;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalaryStructureController extends Controller
{
    use ResolvesPayrollEmployee;

    /** Every teacher/staff member merged with their salary structure, if one has been set. */
    public function index()
    {
        $structures = SalaryStructure::all()->keyBy(fn (SalaryStructure $s) => $s->employee_type . ':' . $s->employee_id);

        $teachers = Teacher::with('schoolClass:id,name')->orderBy('name')->get()->map(fn (Teacher $t) => $this->row('teacher', $t, $structures));
        $staff = Staff::orderBy('name')->get()->map(fn (Staff $s) => $this->row('staff', $s, $structures));

        return response()->json($teachers->concat($staff)->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_type' => ['required', Rule::in(['teacher', 'staff'])],
            'employee_id' => 'required|integer',
            'basic_salary' => 'required|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
        ]);

        $modelClass = $this->payrollEmployeeModelClass($data['employee_type']);
        if (! $modelClass::where('id', $data['employee_id'])->exists()) {
            abort(404, 'Employee not found.');
        }

        $structure = SalaryStructure::updateOrCreate(
            ['employee_type' => $data['employee_type'], 'employee_id' => $data['employee_id']],
            ['basic_salary' => $data['basic_salary'], 'allowances' => $data['allowances'] ?? 0, 'deductions' => $data['deductions'] ?? 0]
        );

        return response()->json($structure);
    }

    private function row(string $type, Teacher|Staff $person, \Illuminate\Support\Collection $structures): array
    {
        $structure = $structures->get($type . ':' . $person->id);

        return [
            'employee_type' => $type,
            'employee_id' => $person->id,
            'employee_code' => $person->employee_id,
            'name' => $person->name,
            'meta' => $type === 'teacher' ? ($person->schoolClass->name ?? null) : $person->department,
            'basic_salary' => (float) ($structure->basic_salary ?? 0),
            'allowances' => (float) ($structure->allowances ?? 0),
            'deductions' => (float) ($structure->deductions ?? 0),
            'has_structure' => $structure !== null,
        ];
    }
}
