<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    use EnforcesPortalVisibility;

    /** document type key => [column, label] */
    private const TYPES = [
        'photo' => ['photo_path', 'Photo'],
        'aadhaar' => ['aadhaar_path', 'Aadhaar Card'],
        'pan' => ['pan_path', 'PAN Card'],
        'birth_certificate' => ['birth_certificate_path', 'Birth Certificate'],
        'transfer_certificate' => ['transfer_certificate_path', 'Transfer Certificate'],
        'marksheet' => ['marksheet_path', 'Marksheet'],
        'father_aadhaar' => ['father_aadhaar_path', "Father's Aadhaar"],
        'father_pan' => ['father_pan_path', "Father's PAN"],
        'mother_aadhaar' => ['mother_aadhaar_path', "Mother's Aadhaar"],
        'mother_pan' => ['mother_pan_path', "Mother's PAN"],
        'father_photo' => ['father_photo_path', "Father's Photo"],
        'mother_photo' => ['mother_photo_path', "Mother's Photo"],
    ];

    public function index()
    {
        $this->abortIfModuleDisabled('documents');

        /** @var Student $student */
        $student = Auth::guard('student')->user();
        $doc = StudentDocument::where('student_id', $student->id)->first();

        $rows = collect(self::TYPES)->map(function ($meta, $key) use ($doc) {
            [$column, $label] = $meta;

            return [
                'type' => $key,
                'label' => $label,
                'uploaded' => $doc && ! empty($doc->{$column}),
            ];
        })->values();

        return response()->json($rows);
    }

    public function download(string $type)
    {
        $this->abortIfModuleDisabled('documents');
        abort_unless(array_key_exists($type, self::TYPES), 404);

        /** @var Student $student */
        $student = Auth::guard('student')->user();
        $doc = StudentDocument::where('student_id', $student->id)->first();
        [$column, $label] = self::TYPES[$type];
        $path = $doc?->{$column};

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $label.'-'.$student->admission_no.'.'.pathinfo($path, PATHINFO_EXTENSION));
    }
}
