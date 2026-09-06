<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use Illuminate\Http\Request;

class AcademicSessionController extends Controller
{
    public function index()
    {
        return response()->json(
            AcademicSession::orderByDesc('start_date')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|in:current,upcoming,closed',
        ]);

        $session = AcademicSession::create($data);

        if ($data['status'] === 'current') {
            $this->makeCurrent($session);
        }

        return response()->json($session, 201);
    }

    public function update(Request $request, AcademicSession $academicSession)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'required|in:current,upcoming,closed',
        ]);

        $academicSession->update($data);

        if ($data['status'] === 'current') {
            $this->makeCurrent($academicSession);
        } elseif ($academicSession->is_current) {
            $academicSession->update(['is_current' => false]);
        }

        return response()->json($academicSession);
    }

    public function destroy(AcademicSession $academicSession)
    {
        $academicSession->delete();

        return response()->json(['success' => true]);
    }

    private function makeCurrent(AcademicSession $session): void
    {
        // Demote any other session still marked current — both the boolean flag
        // and the status label — so exactly one session is ever "current".
        AcademicSession::where('id', '!=', $session->id)
            ->where('status', 'current')
            ->update(['status' => 'closed']);
        AcademicSession::where('id', '!=', $session->id)->update(['is_current' => false]);

        $session->update(['is_current' => true]);
    }
}
