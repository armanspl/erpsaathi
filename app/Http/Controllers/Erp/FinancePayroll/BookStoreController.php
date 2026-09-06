<?php

namespace App\Http\Controllers\Erp\FinancePayroll;

use App\Http\Controllers\Controller;
use App\Models\StoreBook;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookStoreController extends Controller
{
    private const RELATIONS = ['branch:id,name', 'schoolClass:id,name'];

    public function index(Request $request)
    {
        $query = StoreBook::with(self::RELATIONS);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }
        if ($request->filled('school_class_id')) {
            $query->where('school_class_id', $request->integer('school_class_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . trim($request->string('search')) . '%');
        }

        return response()->json(
            $query->orderBy('title')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $book = StoreBook::create($data);

        return response()->json($book->load(self::RELATIONS), 201);
    }

    public function update(Request $request, StoreBook $storeBook)
    {
        $data = $this->validated($request);

        $storeBook->update($data);

        return response()->json($storeBook->load(self::RELATIONS));
    }

    public function destroy(StoreBook $storeBook)
    {
        $storeBook->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'price' => 'required|numeric|min:0',
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }
}
