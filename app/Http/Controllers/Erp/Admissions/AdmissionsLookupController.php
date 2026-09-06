<?php

namespace App\Http\Controllers\Erp\Admissions;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCustomField;
use App\Models\AdmissionEnquiry;
use App\Models\Student;
use App\Support\AdmissionsCache;
use Illuminate\Support\Facades\Cache;

/**
 * One lightweight boot payload for Admissions pages (sidebar prefetch + page loads).
 */
class AdmissionsLookupController extends Controller
{
    public function __invoke()
    {
        $payload = Cache::remember(AdmissionsCache::PIPELINE_COUNTS, AdmissionsCache::TTL, function () {
            $counts = ['New' => 0, 'Registered' => 0, 'Admitted' => 0];
            foreach (
                Student::query()
                    ->selectRaw('admission_status, COUNT(*) as aggregate')
                    ->groupBy('admission_status')
                    ->get() as $row
            ) {
                $status = $row->admission_status ?: 'Admitted';
                if (array_key_exists($status, $counts)) {
                    $counts[$status] = (int) $row->aggregate;
                }
            }

            return [
                'pipeline_counts' => $counts,
                'open_enquiries' => AdmissionEnquiry::query()
                    ->whereNotIn('stage', ['admitted', 'rejected'])
                    ->count(),
                'custom_fields_active' => AdmissionCustomField::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'label', 'type', 'placeholder', 'is_active', 'sort_order']),
            ];
        });

        return response()->json($payload);
    }
}
