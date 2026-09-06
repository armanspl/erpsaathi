<?php

namespace App\Http\Controllers\Erp\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\HostelAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HostelAllocationController extends Controller
{
    public function index()
    {
        return response()->json(
            HostelAllocation::with([
                'student:id,name,admission_no,school_class_id',
                'student.schoolClass:id,name',
                'bed:id,room_id,bed_no',
                'bed.room:id,room_no,monthly_fee',
            ])->orderByDesc('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'bed_id' => 'required|exists:beds,id',
            'start_date' => 'required|date',
        ]);

        if (HostelAllocation::where('student_id', $data['student_id'])->where('status', 'Active')->exists()) {
            throw ValidationException::withMessages(['student_id' => 'This student already has an active hostel allocation.']);
        }

        $bed = Bed::findOrFail($data['bed_id']);
        if ($bed->status !== 'Available') {
            throw ValidationException::withMessages(['bed_id' => 'This bed is not available.']);
        }

        $allocation = DB::transaction(function () use ($data, $bed) {
            $bed->update(['status' => 'Occupied']);

            return HostelAllocation::create([...$data, 'status' => 'Active']);
        });

        return response()->json($allocation->load(['student:id,name,admission_no', 'bed:id,room_id,bed_no', 'bed.room:id,room_no']), 201);
    }

    public function update(Request $request, HostelAllocation $hostelAllocation)
    {
        $data = $request->validate([
            'bed_id' => 'required|exists:beds,id',
            'start_date' => 'required|date',
        ]);

        DB::transaction(function () use ($hostelAllocation, $data) {
            if ($data['bed_id'] != $hostelAllocation->bed_id) {
                $newBed = Bed::findOrFail($data['bed_id']);
                if ($newBed->status !== 'Available') {
                    throw ValidationException::withMessages(['bed_id' => 'This bed is not available.']);
                }
                $hostelAllocation->bed()->update(['status' => 'Available']);
                $newBed->update(['status' => 'Occupied']);
            }

            $hostelAllocation->update($data);
        });

        return response()->json($hostelAllocation->fresh()->load(['student:id,name,admission_no', 'bed:id,room_id,bed_no', 'bed.room:id,room_no']));
    }

    public function vacate(Request $request, HostelAllocation $hostelAllocation)
    {
        if ($hostelAllocation->status !== 'Active') {
            throw ValidationException::withMessages(['status' => 'This allocation is already inactive.']);
        }

        $data = $request->validate([
            'end_date' => 'nullable|date|after_or_equal:' . $hostelAllocation->start_date->toDateString(),
        ]);

        DB::transaction(function () use ($hostelAllocation, $data) {
            $hostelAllocation->update([
                'status' => 'Inactive',
                'end_date' => $data['end_date'] ?? now()->toDateString(),
            ]);
            $hostelAllocation->bed()->update(['status' => 'Available']);
        });

        return response()->json($hostelAllocation->fresh()->load(['student:id,name,admission_no', 'bed:id,room_id,bed_no', 'bed.room:id,room_no']));
    }
}
