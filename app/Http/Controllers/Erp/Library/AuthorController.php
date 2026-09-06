<?php

namespace App\Http\Controllers\Erp\Library;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    public function index()
    {
        return response()->json(Author::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string|max:500',
        ]);

        $author = Author::create($data);

        return response()->json($author, 201);
    }

    public function update(Request $request, Author $author)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string|max:500',
        ]);

        $author->update($data);

        return response()->json($author);
    }

    public function destroy(Author $author)
    {
        $author->delete();

        return response()->json(['success' => true]);
    }
}
