<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookIssue;
use App\Models\LibraryMember;

class LibraryReportCalculator
{
    /** Library operations snapshot, computed live from the transactional tables. */
    public static function summary(): array
    {
        $issued = BookIssue::where('status', 'Issued')->with('book:id,title')->get();
        $overdue = $issued->filter(fn (BookIssue $i) => $i->due_date->isPast());

        $mostBorrowed = BookIssue::selectRaw('book_id, COUNT(*) as issue_count')
            ->groupBy('book_id')
            ->orderByDesc('issue_count')
            ->with('book:id,title')
            ->limit(5)
            ->get()
            ->map(fn (BookIssue $row) => ['title' => $row->book->title, 'times_issued' => (int) $row->issue_count]);

        return [
            'total_books' => (int) Book::sum('total_copies'),
            'available_books' => (int) Book::sum('available_copies'),
            'total_titles' => Book::count(),
            'total_members' => LibraryMember::count(),
            'currently_issued' => $issued->count(),
            'overdue_count' => $overdue->count(),
            'fine_collected_total' => round((float) BookIssue::where('fine_status', 'Paid')->sum('fine_amount'), 2),
            'fine_pending_total' => round((float) BookIssue::where('fine_status', 'Pending')->sum('fine_amount'), 2),
            'overdue_list' => $overdue->map(fn (BookIssue $i) => [
                'issue_id' => $i->id,
                'book' => $i->book->title,
                'due_date' => $i->due_date->toDateString(),
                'days_late' => (int) $i->due_date->diffInDays(now()->startOfDay(), true),
            ])->values(),
            'most_borrowed' => $mostBorrowed->values(),
        ];
    }
}
