<?php

namespace App\Http\Controllers\Erp\Library;

use App\Http\Controllers\Controller;
use App\Models\BookCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookCategoryController extends Controller
{
    public function index()
    {
        return response()->json(BookCategory::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:book_categories,name',
            'description' => 'nullable|string|max:255',
        ]);

        $category = BookCategory::create($data);

        return response()->json($category, 201);
    }

    public function update(Request $request, BookCategory $bookCategory)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('book_categories', 'name')->ignore($bookCategory->id)],
            'description' => 'nullable|string|max:255',
        ]);

        $bookCategory->update($data);

        return response()->json($bookCategory);
    }

    public function destroy(BookCategory $bookCategory)
    {
        $bookCategory->delete();

        return response()->json(['success' => true]);
    }
}
