<?php

namespace App\Http\Controllers\Erp\People;

use App\Http\Controllers\Controller;
use App\Models\ErpRole;
use App\Models\ErpUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(
            ErpUser::select('id', 'name', 'email', 'role', 'is_active', 'created_at')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:erp_users,email',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in($this->roleSlugs())],
            'is_active' => 'boolean',
        ]);

        $user = ErpUser::create($data);

        return response()->json($user->only(['id', 'name', 'email', 'role', 'is_active']), 201);
    }

    public function update(Request $request, ErpUser $user)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('erp_users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => ['required', Rule::in($this->roleSlugs())],
            'is_active' => 'boolean',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($user->only(['id', 'name', 'email', 'role', 'is_active']));
    }

    public function destroy(Request $request, ErpUser $user)
    {
        $this->authorizeAdmin();

        if ($user->id === Auth::guard('erp')->id()) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->delete();

        return response()->json(['success' => true]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::guard('erp')->user()->role === 'admin', 403, 'Only admins can manage user accounts.');
    }

    private function roleSlugs(): array
    {
        return ErpRole::pluck('slug')->push('admin', 'staff')->unique()->values()->all();
    }
}
