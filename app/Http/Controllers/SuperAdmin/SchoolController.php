<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Master\School;
use App\Models\Master\SchoolDomain;
use App\Services\Tenancy\SchoolProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SchoolController extends Controller
{
    public function index()
    {
        $schools = School::query()->with('domains')->orderByDesc('id')->get();

        return response()->json(['schools' => $schools]);
    }

    public function show(School $school)
    {
        $school->load('domains');

        return response()->json(['school' => $school]);
    }

    public function store(Request $request, SchoolProvisioner $provisioner)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', 'unique:master.schools,slug'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_name' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'min:8'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $school = $provisioner->provision($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        if (! empty($data['notes'])) {
            $school->update(['notes' => $data['notes']]);
        }

        return response()->json([
            'message' => 'School provisioned successfully.',
            'school' => $school->fresh(['domains']),
            'admin_password' => $school->getAttribute('generated_admin_password'),
        ], 201);
    }

    public function update(Request $request, School $school)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'admin_email' => ['sometimes', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
        ]);

        $school->fill(collect($data)->only(['name', 'admin_email', 'notes'])->all());
        $school->save();

        if (array_key_exists('custom_domain', $data)) {
            $domain = $data['custom_domain'] ? strtolower(trim($data['custom_domain'])) : null;
            SchoolDomain::query()
                ->where('school_id', $school->id)
                ->where('type', 'custom')
                ->delete();
            if ($domain) {
                SchoolDomain::query()->create([
                    'school_id' => $school->id,
                    'domain' => $domain,
                    'type' => 'custom',
                    'is_primary' => false,
                ]);
            }
        }

        return response()->json(['school' => $school->fresh(['domains'])]);
    }

    public function setStatus(Request $request, School $school, SchoolProvisioner $provisioner)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($school->is_first_school && $data['status'] === 'inactive') {
            // Allow deactivate but warn — still valid per plan.
        }

        $school = $provisioner->setStatus($school, $data['status']);

        return response()->json(['school' => $school]);
    }

    public function resetAdmin(School $school, SchoolProvisioner $provisioner)
    {
        $password = $provisioner->resetSchoolAdmin($school);

        return response()->json([
            'message' => 'School admin password reset.',
            'admin_email' => $school->admin_email,
            'admin_password' => $password,
        ]);
    }

    public function destroy(School $school)
    {
        if ($school->is_first_school) {
            return response()->json(['message' => 'Cannot delete the first school tenant.'], 422);
        }

        // Soft-disable instead of dropping the database.
        $school->update(['status' => 'inactive', 'notes' => trim(($school->notes ?? '')."\nDisabled at ".now()->toDateTimeString())]);

        return response()->json(['message' => 'School disabled.', 'school' => $school->fresh()]);
    }
}
