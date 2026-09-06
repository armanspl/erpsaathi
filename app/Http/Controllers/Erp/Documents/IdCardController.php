<?php

namespace App\Http\Controllers\Erp\Documents;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\Documents\Concerns\ResolvesCardHolderType;
use App\Models\IdCard;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IdCardController extends Controller
{
    use ResolvesCardHolderType;

    public function __construct(
        private DocumentRenderService $renderer,
        private DocumentDataBuilder $dataBuilder,
    ) {}

    public function index(Request $request)
    {
        $query = IdCard::query()
            ->with(['holder' => function (MorphTo $morphTo) {
                // Slim holder columns only — list UI + edit drawer need these fields, not full graphs.
                $morphTo->constrain([
                    Student::class => fn ($q) => $q->select([
                        'id', 'name', 'admission_no', 'roll_no', 'gender', 'dob', 'blood_group', 'mobile',
                        'school_class_id', 'section_id', 'status',
                    ]),
                    Teacher::class => fn ($q) => $q->select([
                        'id', 'name', 'employee_id', 'phone', 'email', 'status', 'school_class_id',
                    ]),
                    Staff::class => fn ($q) => $q->select([
                        'id', 'name', 'employee_id', 'department', 'phone', 'email', 'status',
                    ]),
                ]);
            }]);

        if ($request->filled('holder_type')) {
            $query->where('holder_type', $request->string('holder_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'holder_type' => ['required', Rule::in(['student', 'teacher', 'staff'])],
            'holder_id' => 'required|integer',
            'issued_date' => 'required|date',
            'valid_until' => 'nullable|date|after:issued_date',
        ]);

        $modelClass = $this->cardHolderModelClass($data['holder_type']);
        if (! $modelClass::where('id', $data['holder_id'])->exists()) {
            abort(404, 'Holder not found.');
        }

        if (IdCard::where('holder_type', $data['holder_type'])->where('holder_id', $data['holder_id'])->where('status', 'Active')->exists()) {
            throw ValidationException::withMessages(['holder_id' => 'This person already has an active ID card.']);
        }

        $card = IdCard::create([...$data, 'card_no' => $this->nextCardNo(), 'status' => 'Active']);

        return response()->json($card->load('holder'), 201);
    }

    /** Corrects a mistaken entry (wrong holder/date). card_no and status are untouched — use Reissue for a fresh card instead. */
    public function update(Request $request, IdCard $idCard)
    {
        $data = $request->validate([
            'holder_type' => ['required', Rule::in(['student', 'teacher', 'staff'])],
            'holder_id' => 'required|integer',
            'issued_date' => 'required|date',
            'valid_until' => 'nullable|date|after:issued_date',
        ]);

        $modelClass = $this->cardHolderModelClass($data['holder_type']);
        if (! $modelClass::where('id', $data['holder_id'])->exists()) {
            abort(404, 'Holder not found.');
        }

        $idCard->update($data);

        return response()->json($idCard->load('holder'));
    }

    public function reissue(IdCard $idCard)
    {
        $idCard->update(['status' => 'Reissued']);

        $newCard = IdCard::create([
            'card_no' => $this->nextCardNo(),
            'holder_type' => $idCard->holder_type,
            'holder_id' => $idCard->holder_id,
            'issued_date' => now()->toDateString(),
            'valid_until' => $idCard->valid_until,
            'status' => 'Active',
        ]);

        return response()->json($newCard->load('holder'), 201);
    }

    public function destroy(IdCard $idCard)
    {
        $idCard->delete();

        return response()->json(['success' => true]);
    }

    public function downloadPdf(IdCard $idCard): StreamedResponse
    {
        $idCard->loadMissing('holder');

        return $this->renderer->streamPdf('id_card', $this->dataBuilder->idCard($idCard), "id-card-{$idCard->card_no}.pdf");
    }

    private function nextCardNo(): string
    {
        $year = now()->format('Y');
        $count = IdCard::where('card_no', 'like', "IDC-{$year}-%")->count() + 1;

        return sprintf('IDC-%s-%04d', $year, $count);
    }
}
