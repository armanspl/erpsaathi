<?php

namespace App\Http\Controllers\Erp\Hostel;

use App\Http\Controllers\Controller;
use App\Models\HostelAllocation;
use App\Models\HostelFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HostelFeeController extends Controller
{
    public function index(Request $request)
    {
        $query = HostelFee::with(['allocation.student:id,name,admission_no', 'allocation.bed:id,room_id,bed_no', 'allocation.bed.room:id,room_no']);

        if ($request->filled('period')) {
            $query->where('period', $request->string('period'));
        }

        return response()->json($query->orderByDesc('period')->orderBy('id')->get());
    }

    /** Generate Pending fees for every Active allocation for a given period, using the bed's room rate. Idempotent. */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $allocations = HostelAllocation::with('bed.room')->where('status', 'Active')->get();
        $existing = HostelFee::where('period', $data['period'])->pluck('hostel_allocation_id');

        $created = 0;
        foreach ($allocations as $allocation) {
            if ($existing->contains($allocation->id)) {
                continue;
            }

            HostelFee::create([
                'hostel_allocation_id' => $allocation->id,
                'period' => $data['period'],
                'amount' => $allocation->bed->room->monthly_fee,
            ]);
            $created++;
        }

        return response()->json(['success' => true, 'generated' => $created]);
    }

    public function markPaid(Request $request, HostelFee $hostelFee)
    {
        $data = $request->validate([
            'payment_mode' => ['required', Rule::in(['Cash', 'Bank'])],
            'paid_on' => 'nullable|date',
        ]);

        $hostelFee->update([
            'status' => 'Paid',
            'payment_mode' => $data['payment_mode'],
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
            'collected_by_id' => Auth::guard('erp')->id(),
        ]);

        return response()->json($hostelFee);
    }
}
