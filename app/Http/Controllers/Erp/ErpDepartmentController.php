<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\ErpDepartment;
use App\Models\Staff;
use App\Services\PermissionCatalog;
use App\Services\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ErpDepartmentController extends Controller
{
    public function __construct(private PermissionResolver $resolver) {}

    public function catalog()
    {
        return response()->json([
            'tree' => PermissionCatalog::tree(),
            'keys' => PermissionCatalog::allKeys(),
            'legacy' => PermissionCatalog::legacyModuleKeys(),
        ]);
    }

    /** Effective permissions for the logged-in user (for Vue menu/button gating). */
    public function mine()
    {
        $user = Auth::guard('erp')->user();

        return response()->json([
            'permissions' => $this->resolver->forUser($user),
            'role' => $user?->role,
        ]);
    }

    public function index()
    {
        return response()->json(
            ErpDepartment::orderBy('name')->get()
        );
    }

    /** Distinct department names already used on staff (for quick create). */
    public function suggestions()
    {
        $fromStaff = Staff::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        $existing = ErpDepartment::pluck('name');

        return response()->json(
            $fromStaff->merge($existing)->unique()->values()
        );
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:erp_departments,name',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'is_active' => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        $data['is_active'] = $data['is_active'] ?? true;

        $department = ErpDepartment::create($data);

        return response()->json($department, 201);
    }

    public function update(Request $request, ErpDepartment $erpDepartment)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('erp_departments', 'name')->ignore($erpDepartment->id)],
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'is_active' => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));

        $erpDepartment->update($data);

        return response()->json($erpDepartment->fresh());
    }

    public function destroy(ErpDepartment $erpDepartment)
    {
        $this->authorizeManage();
        $erpDepartment->delete();

        return response()->json(['success' => true]);
    }

    private function authorizeManage(): void
    {
        $user = Auth::guard('erp')->user();
        abort_unless(
            $user && $this->resolver->allows($user, 'settings.roles-and-permissions.manage'),
            403,
            'Only administrators can manage department permissions.'
        );
    }
}
