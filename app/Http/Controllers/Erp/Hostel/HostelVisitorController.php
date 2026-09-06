<?php

namespace App\Http\Controllers\Erp\Hostel;

use App\Http\Controllers\Controller;
use App\Models\HostelVisitor;
use Illuminate\Http\Request;

class HostelVisitorController extends Controller
{
    public function index()
    {
        return response()->json(
            HostelVisitor::with('student:id,name,admission_no')->orderByDesc('visit_date')->orderByDesc('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'visitor_name' => 'required|string|max:255',
            'relation' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'visit_date' => 'required|date',
            'in_time' => 'nullable|date_format:H:i',
        ]);

        $visitor = HostelVisitor::create($data);

        return response()->json($visitor->load('student:id,name,admission_no'), 201);
    }

    public function update(Request $request, HostelVisitor $hostelVisitor)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'visitor_name' => 'required|string|max:255',
            'relation' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'visit_date' => 'required|date',
            'in_time' => 'nullable|date_format:H:i',
        ]);

        $hostelVisitor->update($data);

        return response()->json($hostelVisitor->load('student:id,name,admission_no'));
    }

    public function checkout(HostelVisitor $hostelVisitor)
    {
        $hostelVisitor->update(['out_time' => now()->format('H:i')]);

        return response()->json($hostelVisitor->load('student:id,name,admission_no'));
    }

    public function destroy(HostelVisitor $hostelVisitor)
    {
        $hostelVisitor->delete();

        return response()->json(['success' => true]);
    }
}
