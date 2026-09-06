<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\BookExpense;
use App\Models\Student;
use App\Models\StoreBook;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookExpenseController extends Controller
{
    private const RELATIONS = ['student:id,name,admission_no', 'branch:id,name', 'schoolClass:id,name', 'section:id,name', 'items'];

    public function __construct(
        private DocumentRenderService $renderer,
        private DocumentDataBuilder $dataBuilder,
    ) {}


    public function index(Request $request)
    {
        $query = BookExpense::with(self::RELATIONS);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }
        if ($request->filled('school_class_id')) {
            $query->where('school_class_id', $request->integer('school_class_id'));
        }
        if ($request->filled('section_id')) {
            $query->where('section_id', $request->integer('section_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }
        if (! $request->filled('from') && ! $request->filled('to')) {
            AcademicSession::applyDateWindow($query, $request, 'date');
        }
        if ($request->filled('search')) {
            $term = '%' . trim($request->string('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('expense_no', 'like', $term)
                    ->orWhereHas('student', fn ($s) => $s->where('name', 'like', $term)->orWhere('admission_no', 'like', $term));
            });
        }

        return response()->json($query->orderByDesc('date')->orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $expense = DB::transaction(fn () => $this->persist($data));

        return response()->json($expense->fresh()->load(self::RELATIONS), 201);
    }

    public function update(Request $request, BookExpense $bookExpense)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $bookExpense) {
            $student = Student::findOrFail($data['student_id']);
            $books = StoreBook::whereIn('id', $data['item_ids'])->get();

            $bookExpense->items()->delete();
            $items = $books->map(fn (StoreBook $b) => [
                'store_book_id' => $b->id,
                'title' => $b->title,
                'price' => $b->price,
            ]);

            $bookExpense->update([
                'student_id' => $student->id,
                'branch_id' => $student->branch_id,
                'school_class_id' => $student->school_class_id,
                'section_id' => $student->section_id,
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
                'total_amount' => $books->sum('price'),
            ]);
            $bookExpense->items()->createMany($items);
        });

        return response()->json($bookExpense->fresh()->load(self::RELATIONS));
    }

    public function destroy(BookExpense $bookExpense)
    {
        $bookExpense->delete();

        return response()->json(['success' => true]);
    }

    public function downloadPdf(BookExpense $bookExpense): StreamedResponse
    {
        $bookExpense->loadMissing(self::RELATIONS);

        return $this->renderer->streamPdf('book_expense', $this->dataBuilder->bookExpense($bookExpense), "{$bookExpense->expense_no}.pdf");
    }

    private function persist(array $data): BookExpense
    {
        $student = Student::findOrFail($data['student_id']);
        $books = StoreBook::whereIn('id', $data['item_ids'])->get();

        $expense = BookExpense::create([
            'expense_no' => $this->nextExpenseNo(),
            'student_id' => $student->id,
            'branch_id' => $student->branch_id,
            'school_class_id' => $student->school_class_id,
            'section_id' => $student->section_id,
            'date' => $data['date'],
            'notes' => $data['notes'] ?? null,
            'total_amount' => $books->sum('price'),
            'created_by_id' => Auth::guard('erp')->id(),
        ]);

        $expense->items()->createMany($books->map(fn (StoreBook $b) => [
            'store_book_id' => $b->id,
            'title' => $b->title,
            'price' => $b->price,
        ]));

        return $expense;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'student_id' => 'required|exists:students,id',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:store_books,id',
        ]);
    }

    private function nextExpenseNo(): string
    {
        $count = BookExpense::count() + 1;

        return sprintf('BKX-%05d', $count);
    }
}
