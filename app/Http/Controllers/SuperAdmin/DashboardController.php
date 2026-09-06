<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Master\School;
use App\Models\Master\SuperAdmin;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('super-admin.app');
    }

    public function summary()
    {
        return response()->json([
            'user' => Auth::guard('super_admin')->user(),
            'stats' => [
                'schools_total' => School::query()->count(),
                'schools_active' => School::query()->where('status', 'active')->count(),
                'schools_inactive' => School::query()->where('status', 'inactive')->count(),
                'schools_failed' => School::query()->where('status', 'failed')->count(),
                'super_admins' => SuperAdmin::query()->count(),
            ],
            'tenancy' => [
                'base_domain' => config('tenancy.base_domain'),
                'central_domains' => config('tenancy.central_domains'),
                'first_school_slug' => config('tenancy.first_school_slug'),
            ],
        ]);
    }
}
