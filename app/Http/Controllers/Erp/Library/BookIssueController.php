<?php

namespace App\Http\Controllers\Erp\Library;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\LibraryMember;
use App\Services\LibraryFineCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookIssueController extends Controller
{
    public function index(Request $request)
    {
        $query = BookIssue::with(['book:id,title', 'member.member']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('student_id')) {
            $query->whereHas('member', fn ($q) => $q->where('member_type', 'student')->where('member_id', $request->integer('student_id')));
        }

        return response()->json(
            $query->orderByDesc('issue_date')->orderByDesc('id')->get()->map(fn (BookIssue $issue) => [
                ...$issue->toArray(),
                'is_overdue' => $issue->status === 'Issued' && $issue->due_date->isPast(),
            ])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'book_id' => 'required|exists:books,id',
            'library_member_id' => 'required|exists:library_members,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
        ]);

        $book = Book::findOrFail($data['book_id']);
        $member = LibraryMember::findOrFail($data['library_member_id']);

        if ($book->available_copies < 1) {
            throw ValidationException::withMessages(['book_id' => 'No copies of this book are currently available.']);
        }
        if ($member->status !== 'Active') {
            throw ValidationException::withMessages(['library_member_id' => 'This member is blocked and cannot borrow books.']);
        }

        $activeIssues = BookIssue::where('library_member_id', $member->id)->where('status', 'Issued')->count();
        if ($activeIssues >= $member->max_books) {
            throw ValidationException::withMessages(['library_member_id' => "This member has reached their limit of {$member->max_books} books."]);
        }

        $issue = DB::transaction(function () use ($data, $book) {
            $book->decrement('available_copies');

            return BookIssue::create([
                'book_id' => $data['book_id'],
                'library_member_id' => $data['library_member_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'status' => 'Issued',
            ]);
        });

        return response()->json($issue->load(['book:id,title', 'member.member']), 201);
    }

    public function returnBook(Request $request, BookIssue $bookIssue)
    {
        if ($bookIssue->status !== 'Issued') {
            throw ValidationException::withMessages(['status' => 'This book has already been returned.']);
        }

        $data = $request->validate([
            'return_date' => 'nullable|date|after_or_equal:' . $bookIssue->issue_date->toDateString(),
        ]);
        $returnDate = $data['return_date'] ?? now()->toDateString();

        $fine = LibraryFineCalculator::forReturn($bookIssue, $returnDate);

        DB::transaction(function () use ($bookIssue, $returnDate, $fine) {
            $bookIssue->update([
                'return_date' => $returnDate,
                'status' => 'Returned',
                'fine_amount' => $fine,
                'fine_status' => $fine > 0 ? 'Pending' : 'None',
            ]);
            $bookIssue->book()->increment('available_copies');
        });

        return response()->json($bookIssue->fresh()->load(['book:id,title', 'member.member']));
    }
}
