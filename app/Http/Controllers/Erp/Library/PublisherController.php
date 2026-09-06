<?php

namespace App\Http\Controllers\Erp\Library;

use App\Http\Controllers\Controller;
use App\Models\Publisher;
use Illuminate\Http\Request;

class PublisherController extends Controller
{
    public function index()
    {
        return response()->json(Publisher::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'contact' => 'nullable|string|max:100',
        ]);

        $publisher = Publisher::create($data);

        return response()->json($publisher, 201);
    }

    public function update(Request $request, Publisher $publisher)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'contact' => 'nullable|string|max:100',
        ]);

        $publisher->update($data);

        return response()->json($publisher);
    }

    public function destroy(Publisher $publisher)
    {
        $publisher->delete();

        return response()->json(['success' => true]);
    }
}
