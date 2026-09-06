<?php

namespace App\Http\Controllers\Erp\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show()
    {
        return response()->json(Auth::guard('erp')->user());
    }

    public function update(Request $request)
    {
        $user = Auth::guard('erp')->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('erp_users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return response()->json($user);
    }
}
