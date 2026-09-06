<?php

namespace App\Http\Controllers\Erp\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public const TEMPLATES = [
        'midnight-gold',
        'harbor-navy',
        'forest-ledger',
        'ivory-studio',
        'terracotta-sand',
        'velvet-plum',
    ];

    public function update(Request $request)
    {
        $data = $request->validate([
            'theme_color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'ui_template' => ['nullable', 'string', Rule::in(self::TEMPLATES)],
        ]);

        $user = Auth::guard('erp')->user();
        $payload = [];

        if (array_key_exists('theme_color', $data)) {
            $payload['theme_color'] = $data['theme_color'];
        }
        if (array_key_exists('ui_template', $data)) {
            $payload['ui_template'] = $data['ui_template'];
        }

        if ($payload) {
            $user->update($payload);
        }

        return response()->json([
            'success' => true,
            'theme_color' => $user->theme_color,
            'ui_template' => $user->ui_template,
        ]);
    }
}
