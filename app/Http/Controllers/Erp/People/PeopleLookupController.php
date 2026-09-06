<?php

namespace App\Http\Controllers\Erp\People;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\ParentGuardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Visitor;
use App\Support\PeopleCache;
use Illuminate\Support\Facades\Cache;

/**
 * Lightweight People boot payload for sidebar prefetch and page stats.
 */
class PeopleLookupController extends Controller
{
    public function __invoke()
    {
        $payload = Cache::remember(PeopleCache::LOOKUPS, PeopleCache::TTL, function () {
            return [
                'counts' => [
                    'students' => Student::query()->count(),
                    'parents' => ParentGuardian::query()->count(),
                    'teachers' => Teacher::query()->count(),
                    'staff' => Staff::query()->count(),
                    'drivers' => Driver::query()->count(),
                    'visitors_open' => Visitor::query()->where('status', 'checked_in')->count(),
                ],
                'teachers' => Teacher::query()
                    ->with(['schoolClass:id,name', 'subjects:id,name,code', 'classAssignments.schoolClass:id,name', 'classAssignments.section:id,name'])
                    ->orderBy('name')
                    ->get(['id', 'employee_id', 'name', 'phone', 'email', 'school_class_id', 'status', 'salary', 'custom_field_values']),
                'staff' => Staff::query()
                    ->orderBy('name')
                    ->get(['id', 'employee_id', 'name', 'department', 'phone', 'email', 'status']),
                'drivers' => Driver::query()
                    ->orderBy('name')
                    ->get(['id', 'employee_id', 'name', 'phone', 'license_no', 'vehicle_no', 'status']),
            ];
        });

        return response()->json($payload);
    }
}
