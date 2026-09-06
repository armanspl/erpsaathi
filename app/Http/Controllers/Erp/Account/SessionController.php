<?php

namespace App\Http\Controllers\Erp\Account;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    public function index()
    {
        return response()->json(
            LoginHistory::where('erp_user_id', Auth::guard('erp')->id())
                ->orderByDesc('id')
                ->limit(50)
                ->get()
        );
    }
}
