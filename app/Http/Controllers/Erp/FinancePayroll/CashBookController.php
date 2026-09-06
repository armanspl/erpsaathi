<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Services\CashBookCalculator;
use Illuminate\Http\Request;

class CashBookController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;

        if (! $from && ! $to && ! AcademicSession::requestWantsAll($request)) {
            $session = AcademicSession::fromRequest($request);
            if ($session?->start_date && $session?->end_date) {
                $from = $session->start_date->toDateString();
                $to = $session->end_date->toDateString();
            }
        }

        $from ??= now()->startOfMonth()->toDateString();
        $to ??= now()->toDateString();

        return response()->json([
            'from' => $from,
            'to' => $to,
            ...CashBookCalculator::forRange($from, $to),
        ]);
    }
}
