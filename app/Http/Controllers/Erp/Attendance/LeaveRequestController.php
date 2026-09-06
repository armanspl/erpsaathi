<?php

namespace App\Http\Controllers\Erp\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\Attendance\Concerns\ResolvesAttendableType;
use App\Models\AcademicSession;
use App\Models\Driver;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\Teacher;
use App\Support\AttendanceCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LeaveRequestController extends Controller
{
    use ResolvesAttendableType;

    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => 'nullable|in:student,teacher,staff,driver',
            'status' => 'nullable|in:Pending,Approved,Rejected',
            'search' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'awaiting_rejoin' => 'nullable|boolean',
            'rejoined' => 'nullable|boolean',
        ]);

        $query = LeaveRequest::with('attendable')->orderByDesc('from_date')->orderByDesc('id');

        if (! empty($data['type'])) {
            $query->where('attendable_type', $data['type']);
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if ($request->boolean('awaiting_rejoin')) {
            $query->where('status', 'Approved')->whereNull('rejoined_at');
        }
        if ($request->boolean('rejoined')) {
            $query->whereNotNull('rejoined_at');
            if (! empty($data['from'])) {
                $query->whereDate('rejoined_at', '>=', $data['from']);
            }
            if (! empty($data['to'])) {
                $query->whereDate('rejoined_at', '<=', $data['to']);
            }
        } elseif (! empty($data['from']) || ! empty($data['to'])) {
            if (! empty($data['from'])) {
                $query->whereDate('to_date', '>=', $data['from']);
            }
            if (! empty($data['to'])) {
                $query->whereDate('from_date', '<=', $data['to']);
            }
        } elseif (! AcademicSession::requestWantsAll($request)) {
            $session = AcademicSession::fromRequest($request);
            if ($session?->start_date && $session?->end_date) {
                $query->whereDate('to_date', '>=', $session->start_date->toDateString())
                    ->whereDate('from_date', '<=', $session->end_date->toDateString());
            }
        }

        $limit = min(max($request->integer('limit', 400), 1), 1000);
        $rows = $query->limit($limit)->get()->map(fn (LeaveRequest $l) => $this->present($l));

        if (! empty($data['search'])) {
            $term = mb_strtolower(trim($data['search']));
            $rows = $rows->filter(function (array $row) use ($term) {
                $haystack = mb_strtolower(implode(' ', [
                    $row['attendable_name'] ?? '',
                    $row['attendable_code'] ?? '',
                    $row['leave_type'] ?? '',
                    strip_tags((string) ($row['reason'] ?? '')),
                    $row['status'] ?? '',
                ]));

                return str_contains($haystack, $term);
            })->values();
        }

        return response()->json($rows->values());
    }

    /** Linked teacher/staff/driver profile for the logged-in ERP user (self-apply). */
    public function myProfile()
    {
        $user = Auth::guard('erp')->user();
        if (! $user?->email) {
            return response()->json(['profile' => null]);
        }

        $teacher = Teacher::where('email', $user->email)->first();
        if ($teacher) {
            return response()->json([
                'profile' => [
                    'attendable_type' => 'teacher',
                    'attendable_id' => $teacher->id,
                    'name' => $teacher->name,
                ],
            ]);
        }

        $staff = Staff::where('email', $user->email)->first();
        if ($staff) {
            return response()->json([
                'profile' => [
                    'attendable_type' => 'staff',
                    'attendable_id' => $staff->id,
                    'name' => $staff->name,
                ],
            ]);
        }

        $driver = Driver::where('phone', $user->email)->orWhere('employee_id', $user->email)->first();

        return response()->json([
            'profile' => $driver ? [
                'attendable_type' => 'driver',
                'attendable_id' => $driver->id,
                'name' => $driver->name,
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'attendable_type' => ['required', Rule::in(['student', 'teacher', 'staff', 'driver'])],
            'attendable_id' => 'required|integer',
            'leave_type' => 'nullable|string|max:100',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        $table = (new ($this->attendableModelClass($data['attendable_type']))())->getTable();
        $request->validate(['attendable_id' => [Rule::exists($table, 'id')]]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leave-requests', 'local');
        }

        $leave = LeaveRequest::create([
            'attendable_type' => $data['attendable_type'],
            'attendable_id' => $data['attendable_id'],
            'leave_type' => $data['leave_type'] ?: 'Casual',
            'from_date' => $data['from_date'],
            'to_date' => $data['to_date'],
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $attachmentPath,
        ]);

        AttendanceCache::forget();

        return response()->json($this->present($leave->load('attendable')), 201);
    }

    public function approve(LeaveRequest $leaveRequest)
    {
        $leaveRequest->update(['status' => 'Approved', 'approved_by_id' => Auth::guard('erp')->id(), 'approved_at' => now()]);
        AttendanceCache::forget();

        return response()->json($this->present($leaveRequest->fresh('attendable')));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $data = $request->validate(['rejection_reason' => 'required|string|max:255']);

        $leaveRequest->update([
            'status' => 'Rejected',
            'rejection_reason' => $data['rejection_reason'],
            'approved_by_id' => Auth::guard('erp')->id(),
            'approved_at' => now(),
        ]);
        AttendanceCache::forget();

        return response()->json($this->present($leaveRequest->fresh('attendable')));
    }

    public function rejoin(Request $request, LeaveRequest $leaveRequest)
    {
        $data = $request->validate(['rejoined_at' => 'nullable|date']);

        $leaveRequest->update(['rejoined_at' => $data['rejoined_at'] ?? now()->toDateString()]);
        AttendanceCache::forget();

        return response()->json($this->present($leaveRequest->fresh('attendable')));
    }

    public function destroy(LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->attachment_path && Storage::disk('local')->exists($leaveRequest->attachment_path)) {
            Storage::disk('local')->delete($leaveRequest->attachment_path);
        }
        $leaveRequest->delete();
        AttendanceCache::forget();

        return response()->json(['success' => true]);
    }

    private function present(LeaveRequest $leave): array
    {
        $from = $leave->from_date;
        $to = $leave->to_date;
        $days = null;
        if ($from && $to) {
            $days = $from->diffInDays($to) + 1;
        }

        return [
            ...$leave->toArray(),
            'attendable_name' => $leave->attendable?->name,
            'attendable_code' => $leave->attendable?->admission_no ?? $leave->attendable?->employee_id,
            'total_days' => $days,
            'has_attachment' => (bool) $leave->attachment_path,
            'reason_text' => trim(preg_replace('/\s+/', ' ', strip_tags((string) $leave->reason))) ?: null,
            'is_overdue' => $leave->status === 'Approved'
                && ! $leave->rejoined_at
                && $leave->to_date
                && $leave->to_date->lt(now()->startOfDay()),
        ];
    }
}
