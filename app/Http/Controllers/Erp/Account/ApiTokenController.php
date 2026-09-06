<?php

namespace App\Http\Controllers\Erp\Account;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiTokenController extends Controller
{
    public function index()
    {
        return response()->json(
            ApiToken::where('erp_user_id', Auth::guard('erp')->id())->orderByDesc('id')->get()
        );
    }

    /** The plaintext token is only ever visible in this one response — only its hash is stored. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $plainText = Str::random(64);

        $token = ApiToken::create([
            'erp_user_id' => Auth::guard('erp')->id(),
            'name' => $data['name'],
            'token' => Hash::make($plainText),
        ]);

        return response()->json([
            'id' => $token->id,
            'name' => $token->name,
            'plain_text_token' => $plainText,
            'created_at' => $token->created_at,
        ], 201);
    }

    public function destroy(ApiToken $apiToken)
    {
        if ($apiToken->erp_user_id !== Auth::guard('erp')->id()) {
            abort(403);
        }

        $apiToken->delete();

        return response()->json(['success' => true]);
    }
}
