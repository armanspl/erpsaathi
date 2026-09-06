<?php

namespace App\Http\Controllers\Erp\People;

use App\Http\Controllers\Controller;
use App\Models\ErpUser;
use App\Models\Staff;
use App\Support\PeopleCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index()
    {
        $payload = Cache::remember(PeopleCache::STAFF, PeopleCache::TTL, function () {
            return Staff::orderBy('name')->get();
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|string|max:50|unique:staff,employee_id',
            'name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'required|email|max:255|unique:erp_users,email',
            'password' => 'required|string|min:6',
            'status' => 'required|in:active,inactive',
        ]);

        $password = $data['password'];
        unset($data['password']);

        $staff = DB::transaction(function () use ($data, $password) {
            $staff = Staff::create($data);
            $this->provisionLoginAccount($staff, $password);

            return $staff;
        });

        PeopleCache::forget();

        return response()->json($staff, 201);
    }

    public function update(Request $request, Staff $staffMember)
    {
        $linkedUser = $staffMember->email
            ? ErpUser::where('email', $staffMember->email)->first()
            : null;

        $data = $request->validate([
            'employee_id' => ['required', 'string', 'max:50', Rule::unique('staff', 'employee_id')->ignore($staffMember->id)],
            'name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('erp_users', 'email')->ignore($linkedUser?->id),
            ],
            'password' => 'nullable|string|min:6',
            'status' => 'required|in:active,inactive',
        ]);

        $password = $data['password'] ?? null;
        unset($data['password']);

        DB::transaction(function () use ($staffMember, $data, $password, $linkedUser) {
            $staffMember->update($data);
            $this->syncLoginAccount($staffMember->fresh(), $linkedUser, $password);
        });

        PeopleCache::forget();

        return response()->json($staffMember->fresh());
    }

    public function destroy(Staff $staffMember)
    {
        $staffMember->delete();
        PeopleCache::forget();

        return response()->json(['success' => true]);
    }

    /** Create ERP login so this staff member can sign in with email + password. */
    private function provisionLoginAccount(Staff $staff, string $password): void
    {
        ErpUser::create([
            'name' => $staff->name,
            'email' => $staff->email,
            'password' => $password,
            'role' => 'staff',
            'is_active' => $staff->status === 'active',
        ]);
    }

    /**
     * Keep the linked ERP login in sync when staff details change.
     * Creates an account if one did not exist yet (legacy staff rows).
     */
    private function syncLoginAccount(Staff $staff, ?ErpUser $linkedUser, ?string $password): void
    {
        if (! $staff->email) {
            return;
        }

        if (! $linkedUser) {
            $existing = ErpUser::where('email', $staff->email)->first();
            if ($existing) {
                $linkedUser = $existing;
            } elseif ($password) {
                $this->provisionLoginAccount($staff, $password);

                return;
            } else {
                return;
            }
        }

        $payload = [
            'name' => $staff->name,
            'email' => $staff->email,
            'is_active' => $staff->status === 'active',
        ];
        if ($password) {
            $payload['password'] = $password;
        }
        // Do not overwrite admin accounts that happen to share the email.
        if ($linkedUser->role !== 'admin' && $linkedUser->role !== 'teacher') {
            $payload['role'] = 'staff';
        }

        $linkedUser->update($payload);
    }
}
