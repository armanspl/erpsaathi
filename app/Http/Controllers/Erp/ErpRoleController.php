<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\ErpRole;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ErpRoleController extends Controller
{
    public function index()
    {
        return response()->json(ErpRole::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $data['slug'] = Str::slug($data['name']);

        if (ErpRole::where('slug', $data['slug'])->exists()) {
            return response()->json(['message' => 'A role with this name already exists.'], 422);
        }

        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        $data['is_system'] = false;

        $role = ErpRole::create($data);

        return response()->json($role, 201);
    }

    public function update(Request $request, ErpRole $erpRole)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $slug = Str::slug($data['name']);
        if (ErpRole::where('slug', $slug)->where('id', '!=', $erpRole->id)->exists()) {
            return response()->json(['message' => 'A role with this name already exists.'], 422);
        }

        // Keep system role identity stable so middleware lookups (admin/staff) don't break.
        if ($erpRole->is_system) {
            unset($data['name']);
            $data['slug'] = $erpRole->slug;
        } else {
            $data['slug'] = $slug;
        }

        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));

        $erpRole->update($data);

        return response()->json($erpRole->fresh());
    }

    public function destroy(ErpRole $erpRole)
    {
        if ($erpRole->is_system) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }

        $erpRole->delete();

        return response()->json(['success' => true]);
    }
}
