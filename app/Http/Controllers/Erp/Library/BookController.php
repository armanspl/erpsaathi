<?php

namespace App\Http\Controllers\Erp\Library;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookIssue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookController extends Controller
{
    public function index()
    {
        return response()->json(
            Book::with(['category:id,name', 'author:id,name', 'publisher:id,name'])->orderBy('title')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'isbn' => 'nullable|string|max:32|unique:books,isbn',
            'book_category_id' => 'required|exists:book_categories,id',
            'author_id' => 'required|exists:authors,id',
            'publisher_id' => 'required|exists:publishers,id',
            'total_copies' => 'required|integer|min:1',
            'rack_no' => 'nullable|string|max:50',
        ]);

        $book = Book::create([...$data, 'available_copies' => $data['total_copies']]);

        return response()->json($book->load(['category:id,name', 'author:id,name', 'publisher:id,name']), 201);
    }

    public function update(Request $request, Book $book)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'isbn' => ['nullable', 'string', 'max:32', Rule::unique('books', 'isbn')->ignore($book->id)],
            'book_category_id' => 'required|exists:book_categories,id',
            'author_id' => 'required|exists:authors,id',
            'publisher_id' => 'required|exists:publishers,id',
            'total_copies' => 'required|integer|min:1',
            'rack_no' => 'nullable|string|max:50',
        ]);

        $issuedCount = BookIssue::where('book_id', $book->id)->where('status', 'Issued')->count();
        if ($data['total_copies'] < $issuedCount) {
            throw ValidationException::withMessages(['total_copies' => "Cannot reduce total copies below the {$issuedCount} currently issued."]);
        }

        $book->update([...$data, 'available_copies' => $data['total_copies'] - $issuedCount]);

        return response()->json($book->load(['category:id,name', 'author:id,name', 'publisher:id,name']));
    }

    public function destroy(Book $book)
    {
        $book->delete();

        return response()->json(['success' => true]);
    }
}
