<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\ExamType;
use Illuminate\Http\Request;

class ExamTypeController extends Controller
{
    public function index()
    {
        return response()->json(ExamType::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        $type = ExamType::create($data);

        return response()->json($type, 201);
    }

    public function update(Request $request, ExamType $examType)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        $examType->update($data);

        return response()->json($examType);
    }

    public function destroy(ExamType $examType)
    {
        $examType->delete();

        return response()->json(['success' => true]);
    }
}
