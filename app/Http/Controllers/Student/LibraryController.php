<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\BookIssue;
use App\Models\LibraryMember;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;

class LibraryController extends Controller
{
    use EnforcesPortalVisibility;

    public function index()
    {
        $this->abortIfModuleDisabled('library');

        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $member = LibraryMember::query()
            ->where('member_type', 'student')
            ->where('member_id', $student->id)
            ->first();

        if (! $member) {
            return response()->json(['is_member' => false, 'issues' => []]);
        }

        $issues = BookIssue::where('library_member_id', $member->id)
            ->with(['book:id,title,isbn,author_id', 'book.author:id,name'])
            ->orderByDesc('issue_date')
            ->get();

        return response()->json([
            'is_member' => true,
            'library_card_no' => $member->library_card_no,
            'issues' => $issues->map(fn (BookIssue $issue) => [
                'id' => $issue->id,
                'book_title' => $issue->book?->title,
                'author' => $issue->book?->author?->name,
                'issue_date' => $issue->issue_date?->format('Y-m-d'),
                'due_date' => $issue->due_date?->format('Y-m-d'),
                'return_date' => $issue->return_date?->format('Y-m-d'),
                'status' => $issue->status,
                'fine_amount' => (float) $issue->fine_amount,
                'fine_status' => $issue->fine_status,
            ])->values(),
        ]);
    }
}
