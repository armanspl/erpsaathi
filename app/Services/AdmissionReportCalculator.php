<?php

namespace App\Services;

use App\Models\AdmissionEnquiry;

class AdmissionReportCalculator
{
    /** Admission funnel snapshot, computed live from the admission_enquiries table. */
    public static function summary(): array
    {
        $enquiries = AdmissionEnquiry::all();
        $admitted = $enquiries->where('stage', 'admitted')->count();

        $ym = now()->format('Y-m');

        return [
            'total_enquiries' => $enquiries->count(),
            'by_stage' => $enquiries->groupBy('stage')->map(fn ($rows) => $rows->count()),
            'admitted' => $admitted,
            'rejected' => $enquiries->where('stage', 'rejected')->count(),
            'conversion_rate' => $enquiries->count() > 0 ? round(($admitted / $enquiries->count()) * 100, 1) : 0,
            'new_this_month' => $enquiries->filter(fn (AdmissionEnquiry $e) => $e->created_at->format('Y-m') === $ym)->count(),
            'by_source' => $enquiries->groupBy(fn (AdmissionEnquiry $e) => $e->source ?: 'Unknown')->map(fn ($rows) => $rows->count()),
        ];
    }
}
