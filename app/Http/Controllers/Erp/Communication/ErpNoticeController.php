<?php

namespace App\Http\Controllers\Erp\Communication;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ErpNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ErpNoticeController extends Controller
{
    public function index(Request $request)
    {
        $query = ErpNotice::with('createdBy:id,name');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        AcademicSession::applyDateWindow($query, $request, 'publish_date');

        return response()->json($query->orderByDesc('publish_date')->orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $notice = ErpNotice::create([...$data, 'created_by_id' => Auth::guard('erp')->id()]);

        return response()->json($notice->load('createdBy:id,name'), 201);
    }

    public function update(Request $request, ErpNotice $notice)
    {
        $data = $this->validated($request);

        $notice->update($data);

        return response()->json($notice->load('createdBy:id,name'));
    }

    public function destroy(ErpNotice $notice)
    {
        $notice->delete();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => ['required', Rule::in(['Notice', 'Circular'])],
            'audience' => ['required', Rule::in(['All', 'Students', 'Teachers', 'Staff', 'Parents'])],
            'publish_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:publish_date',
            'status' => ['required', Rule::in(['Draft', 'Published'])],
        ]);
    }
}
