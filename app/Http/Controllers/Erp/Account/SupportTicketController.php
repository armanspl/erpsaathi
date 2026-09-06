<?php

namespace App\Http\Controllers\Erp\Account;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index()
    {
        return response()->json(
            SupportTicket::where('erp_user_id', Auth::guard('erp')->id())
                ->withCount('replies')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function show(SupportTicket $supportTicket)
    {
        $this->authorizeOwner($supportTicket);

        return response()->json($supportTicket->load('replies.erpUser:id,name'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'priority' => ['required', Rule::in(['Low', 'Medium', 'High'])],
        ]);

        $ticket = SupportTicket::create([...$data, 'erp_user_id' => Auth::guard('erp')->id(), 'status' => 'Open']);

        return response()->json($ticket, 201);
    }

    public function reply(Request $request, SupportTicket $supportTicket)
    {
        $this->authorizeOwner($supportTicket);

        $data = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $supportTicket->replies()->create([...$data, 'erp_user_id' => Auth::guard('erp')->id()]);

        if ($supportTicket->status === 'Resolved') {
            $supportTicket->update(['status' => 'Open']);
        }

        return response()->json($supportTicket->load('replies.erpUser:id,name'), 201);
    }

    public function destroy(SupportTicket $supportTicket)
    {
        $this->authorizeOwner($supportTicket);

        $supportTicket->delete();

        return response()->json(['success' => true]);
    }

    private function authorizeOwner(SupportTicket $ticket): void
    {
        if ($ticket->erp_user_id !== Auth::guard('erp')->id()) {
            abort(403);
        }
    }
}
