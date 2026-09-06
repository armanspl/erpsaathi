<?php

namespace App\Http\Controllers\Erp\Communication;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Message;
use App\Models\ParentGuardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $query = Message::with('sentBy:id,name');

        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }

        AcademicSession::applyDateWindow($query, $request, 'created_at');

        return response()->json($query->orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['SMS', 'Email', 'WhatsApp', 'Push'])],
            'audience' => ['required', Rule::in(['All', 'Students', 'Teachers', 'Staff', 'Parents'])],
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            ...$data,
            'recipient_count' => $this->audienceCount($data['audience']),
            'status' => 'Sent',
            'sent_at' => now(),
            'sent_by_id' => Auth::guard('erp')->id(),
        ]);

        return response()->json($message->load('sentBy:id,name'), 201);
    }

    public function destroy(Message $message)
    {
        $message->delete();

        return response()->json(['success' => true]);
    }

    private function audienceCount(string $audience): int
    {
        return match ($audience) {
            'Students' => Student::where('status', 'Active')->count(),
            'Teachers' => Teacher::count(),
            'Staff' => Staff::count(),
            'Parents' => ParentGuardian::count(),
            'All' => Student::where('status', 'Active')->count() + Teacher::count() + Staff::count() + ParentGuardian::count(),
        };
    }
}
