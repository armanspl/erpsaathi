<?php

namespace App\Http\Controllers\Erp\Documents;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Services\DocumentDataBuilder;
use App\Services\DocumentRenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class TransportCardController extends Controller
{
    private const TRANSPORT_RELATIONS = ['route:id,name,vehicle_id', 'route.vehicle:id,vehicle_no,driver_id', 'route.vehicle.driver:id,name,phone', 'routeStop:id,route_id,stop_name'];

    public function __construct(
        private DocumentRenderService $renderer,
        private DocumentDataBuilder $dataBuilder,
    ) {}

    public function index(Request $request)
    {
        $students = $this->scopedStudents($request);
        $transports = $this->transportsFor($students);

        if ($request->filled('route_id')) {
            $routeId = $request->integer('route_id');
            $students = $students->filter(fn (Student $s) => optional($transports->get($s->id))->route_id === $routeId)->values();
        }

        return response()->json($students->map(fn (Student $s) => $this->presentRow($s, $transports->get($s->id)))->values());
    }

    public function downloadStudentPdf(Student $student): StreamedResponse
    {
        $transport = StudentTransport::where('student_id', $student->id)->with(self::TRANSPORT_RELATIONS)->first();

        return $this->renderer->streamPdf(
            'transport_card',
            $this->dataBuilder->transportCard($student, $transport),
            "transport-card-{$student->admission_no}.pdf"
        );
    }

    public function downloadZip(Request $request): StreamedResponse
    {
        if ($request->filled('student_ids')) {
            $students = Student::whereIn('id', $request->input('student_ids'))->where('status', 'Active')->get();
        } else {
            $students = $this->scopedStudents($request);
            if ($request->filled('route_id')) {
                $transports = $this->transportsFor($students);
                $routeId = $request->integer('route_id');
                $students = $students->filter(fn (Student $s) => optional($transports->get($s->id))->route_id === $routeId)->values();
            }
        }

        abort_if($students->isEmpty(), 404, 'No students found for the selected scope.');

        $zipPath = tempnam(sys_get_temp_dir(), 'transport-cards-zip-');

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        foreach ($students as $student) {
            $transport = StudentTransport::where('student_id', $student->id)->with(self::TRANSPORT_RELATIONS)->first();
            $binary = $this->renderer->pdfBinary('transport_card', $this->dataBuilder->transportCard($student, $transport));
            $zip->addFromString("transport-card-{$student->admission_no}.pdf", $binary);
        }
        $zip->close();

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            unlink($zipPath);
        }, 'transport-cards.zip', ['Content-Type' => 'application/zip']);
    }

    private function scopedStudents(Request $request): Collection
    {
        $query = Student::where('status', 'Active')
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('school_class_id'), fn ($q) => $q->where('school_class_id', $request->integer('school_class_id')))
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.trim($request->string('search')).'%';
                $q->where(fn ($q2) => $q2->where('name', 'like', $term)->orWhere('admission_no', 'like', $term));
            })
            ->with(['schoolClass:id,name', 'section:id,name']);

        AcademicSession::applyStudentSessionFilter($query, $request);

        return $query->orderBy('roll_no')->orderBy('name')->get();
    }

    private function transportsFor(Collection $students): Collection
    {
        return StudentTransport::whereIn('student_id', $students->pluck('id'))
            ->with(self::TRANSPORT_RELATIONS)
            ->get()
            ->keyBy('student_id');
    }

    private function presentRow(Student $student, ?StudentTransport $transport): array
    {
        return [
            'student_id' => $student->id,
            'admission_no' => $student->admission_no,
            'name' => $student->name,
            'school_class_name' => $student->schoolClass->name ?? null,
            'section_name' => $student->section->name ?? null,
            'student_transport_id' => $transport?->id,
            'route_id' => $transport?->route_id,
            'route_name' => $transport?->route->name ?? null,
            'route_stop_id' => $transport?->route_stop_id,
            'stop_name' => $transport?->routeStop->stop_name ?? null,
            'driver_name' => $transport?->route?->vehicle?->driver?->name,
            'start_date' => $transport?->start_date?->format('Y-m-d'),
            'status' => $transport?->status,
        ];
    }
}
