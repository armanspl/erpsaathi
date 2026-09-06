<?php

namespace App\Http\Controllers\Erp\People;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max($request->integer('limit', 200), 1), 1000);

        return response()->json(
            Visitor::query()->orderByDesc('check_in_at')->orderByDesc('id')->limit($limit)->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'purpose' => 'nullable|string|max:255',
            'whom_to_meet' => 'nullable|string|max:255',
        ]);

        $data['gate_pass_no'] = 'GP-' . now()->format('ymd') . '-' . str_pad((string) (Visitor::whereDate('created_at', now())->count() + 1), 3, '0', STR_PAD_LEFT);
        $data['check_in_at'] = now();
        $data['status'] = 'checked_in';

        $visitor = Visitor::create($data);

        return response()->json($visitor, 201);
    }

    public function checkOut(Visitor $visitor)
    {
        if ($visitor->status === 'checked_out') {
            return response()->json(['message' => 'Visitor has already checked out.'], 422);
        }

        $visitor->update(['status' => 'checked_out', 'check_out_at' => now()]);

        return response()->json($visitor);
    }

    public function destroy(Visitor $visitor)
    {
        $visitor->delete();

        return response()->json(['success' => true]);
    }
}
