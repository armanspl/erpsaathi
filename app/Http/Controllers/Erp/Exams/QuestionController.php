<?php

namespace App\Http\Controllers\Erp\Exams;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function index()
    {
        return response()->json(Question::with('subject:id,name')->orderByDesc('created_at')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $question = Question::create($data);

        return response()->json($question->load('subject:id,name'), 201);
    }

    public function update(Request $request, Question $question)
    {
        $data = $this->validated($request);

        $question->update($data);

        return response()->json($question->load('subject:id,name'));
    }

    public function destroy(Question $question)
    {
        $question->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'question_text' => 'required|string|max:2000',
            'question_type' => ['required', Rule::in(['MCQ', 'Short Answer', 'Long Answer'])],
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'correct_answer' => 'nullable|string|max:255',
            'marks' => 'required|numeric|min:0.5',
            'difficulty' => ['required', Rule::in(['Easy', 'Medium', 'Hard'])],
        ]);
    }
}
