<?php

namespace App\Http\Controllers\Erp\Admissions;

use App\Http\Controllers\Controller;
use App\Models\AdmissionEnquiry;
use Illuminate\Http\Request;

class AdmissionFollowUpController extends Controller
{
    public function index(AdmissionEnquiry $enquiry)
    {
        return response()->json($enquiry->followUps()->get());
    }

    public function store(Request $request, AdmissionEnquiry $enquiry)
    {
        $data = $request->validate([
            'note' => 'required|string|max:1000',
            'follow_up_date' => 'nullable|date',
            'next_follow_up_date' => 'nullable|date',
        ]);

        $data['follow_up_date'] ??= now()->toDateString();

        $followUp = $enquiry->followUps()->create($data);

        $enquiry->update([
            'next_follow_up_date' => $data['next_follow_up_date'] ?? $enquiry->next_follow_up_date,
            'stage' => $enquiry->stage === 'enquiry' ? 'follow_up' : $enquiry->stage,
        ]);

        return response()->json($followUp, 201);
    }
}
