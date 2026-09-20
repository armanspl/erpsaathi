<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\IdCard;
use App\Models\Student;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IdCardController extends Controller
{
    use EnforcesPortalVisibility;

    public function download(DocumentDataBuilder $dataBuilder, DocumentRenderService $renderer): StreamedResponse
    {
        $this->abortIfModuleDisabled('id_card');

        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $idCard = IdCard::query()
            ->where('holder_type', 'student')
            ->where('holder_id', $student->id)
            ->orderByDesc('issued_date')
            ->first();

        abort_if(! $idCard, 404, 'No ID card has been issued yet.');

        return $renderer->streamPdf('id_card', $dataBuilder->idCard($idCard), "id-card-{$student->admission_no}.pdf");
    }
}
