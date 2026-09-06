<?php

namespace App\Http\Controllers\Erp\Library;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\Library\Concerns\ResolvesLibraryMemberType;
use App\Models\LibraryMember;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryMemberController extends Controller
{
    use ResolvesLibraryMemberType;

    public function __construct(
        private DocumentRenderService $renderer,
        private DocumentDataBuilder $dataBuilder,
    ) {}

    public function index()
    {
        return response()->json(
            LibraryMember::with('member')->orderByDesc('id')->get()->map(fn (LibraryMember $m) => [
                ...$m->toArray(),
                'member_name' => $m->member->name ?? '—',
                'member_code' => $m->member->admission_no ?? $m->member->employee_id ?? null,
            ])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_type' => ['required', Rule::in(['student', 'teacher', 'staff'])],
            'member_id' => 'required|integer',
            'max_books' => 'nullable|integer|min:1',
            'joined_date' => 'required|date',
        ]);

        $modelClass = $this->libraryMemberModelClass($data['member_type']);
        if (! $modelClass::where('id', $data['member_id'])->exists()) {
            abort(404, 'Member not found.');
        }

        if (LibraryMember::where('member_type', $data['member_type'])->where('member_id', $data['member_id'])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['member_id' => 'This person is already a library member.']);
        }

        $member = LibraryMember::create([
            'member_type' => $data['member_type'],
            'member_id' => $data['member_id'],
            'library_card_no' => $this->nextCardNo(),
            'status' => 'Active',
            'max_books' => $data['max_books'] ?? 3,
            'joined_date' => $data['joined_date'],
        ]);

        return response()->json($member->load('member'), 201);
    }

    public function update(Request $request, LibraryMember $libraryMember)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['Active', 'Blocked'])],
            'max_books' => 'required|integer|min:1',
        ]);

        $libraryMember->update($data);

        return response()->json($libraryMember->load('member'));
    }

    public function destroy(LibraryMember $libraryMember)
    {
        $libraryMember->delete();

        return response()->json(['success' => true]);
    }

    public function downloadPdf(LibraryMember $libraryMember): StreamedResponse
    {
        $libraryMember->loadMissing('member');

        return $this->renderer->streamPdf('library_card', $this->dataBuilder->libraryCard($libraryMember), "library-card-{$libraryMember->library_card_no}.pdf");
    }

    private function nextCardNo(): string
    {
        $count = LibraryMember::count() + 1;

        return sprintf('LIB-%04d', $count);
    }
}
