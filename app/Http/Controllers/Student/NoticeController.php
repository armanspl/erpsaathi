<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\ErpNotice;

class NoticeController extends Controller
{
    use EnforcesPortalVisibility;

    public function index()
    {
        $this->abortIfModuleDisabled('notices');

        $today = now()->toDateString();

        $notices = ErpNotice::query()
            ->whereIn('audience', ['All', 'Students'])
            ->where('status', 'Published')
            ->where(fn ($q) => $q->whereNull('publish_date')->orWhere('publish_date', '<=', $today))
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', $today))
            ->orderByDesc('publish_date')
            ->orderByDesc('id')
            ->get(['id', 'title', 'content', 'type', 'publish_date', 'expiry_date']);

        return response()->json($notices->map(fn (ErpNotice $n) => [
            'id' => $n->id,
            'title' => $n->title,
            'content' => $n->content,
            'type' => $n->type,
            'publish_date' => $n->publish_date?->format('Y-m-d'),
            'expiry_date' => $n->expiry_date?->format('Y-m-d'),
        ])->values());
    }
}
