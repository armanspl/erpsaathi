<?php

namespace App\Http\Controllers\Erp\Library;

use App\Http\Controllers\Controller;
use App\Models\BookIssue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FineCollectionController extends Controller
{
    public function index()
    {
        return response()->json(
            BookIssue::where('fine_status', 'Pending')
                ->with(['book:id,title', 'member.member'])
                ->orderBy('return_date')
                ->get()
        );
    }

    public function collect(BookIssue $bookIssue)
    {
        if ($bookIssue->fine_status !== 'Pending') {
            throw ValidationException::withMessages(['fine_status' => 'There is no pending fine for this issue.']);
        }

        $bookIssue->update([
            'fine_status' => 'Paid',
            'fine_paid_at' => now(),
            'collected_by_id' => Auth::guard('erp')->id(),
        ]);

        return response()->json($bookIssue->load(['book:id,title', 'member.member']));
    }
}
