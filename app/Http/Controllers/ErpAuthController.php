<?php

namespace App\Http\Controllers;

use App\Models\ErpUser;
use App\Models\LoginHistory;
use App\Models\SchoolSetting;
use App\Mail\ErpResetPasswordMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ErpAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('erp')->attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::guard('erp')->user();
            if (! $user->is_active) {
                Auth::guard('erp')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->json([
                    'success' => false,
                    'message' => 'This account is inactive. Contact your administrator.',
                ], 422);
            }

            $request->session()->regenerate();

            LoginHistory::create([
                'erp_user_id' => Auth::guard('erp')->id(),
                'email' => $credentials['email'],
                'status' => 'Success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'redirect' => route('erp.dashboard'),
            ]);
        }

        LoginHistory::create([
            'erp_user_id' => null,
            'email' => $credentials['email'],
            'status' => 'Failed',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'The provided credentials do not match our records.',
        ], 422);
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = ErpUser::where('email', $request->email)->first();

        // Always return success to prevent email enumeration
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'If an account exists with that email, a reset link has been sent.',
            ]);
        }

        // Generate a simple reset token
        $token = Str::random(60);
        
        // Store the token (using a simple approach - store in DB or cache)
        // For production, use password_reset_tokens table
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Build reset URL
        $resetUrl = route('erp.reset-password') . '?token=' . $token . '&email=' . urlencode($user->email);

        // Send email
        Mail::to($user->email)->send(new ErpResetPasswordMail($user, $resetUrl));

        return response()->json([
            'success' => true,
            'message' => 'If an account exists with that email, a reset link has been sent.',
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Verify the token
        $record = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset link. Please request a new one.',
            ], 422);
        }

        // Check token expiry (2 hours)
        $expiry = now()->subHours(2);
        if ($record->created_at < $expiry) {
            \DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'success' => false,
                'message' => 'This reset link has expired. Please request a new one.',
            ], 422);
        }

        // Update the password
        $user = ErpUser::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 422);
        }

        $user->password = $request->password;
        $user->save();

        // Delete the used token
        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    public function dashboard()
    {
        $user = Auth::guard('erp')->user();
        $school = SchoolSetting::current();

        return view('erp.dashboard', [
            'erpUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'theme_color' => $user->theme_color,
                'ui_template' => $user->ui_template,
            ],
            'erpSchool' => [
                'school_name' => $school->school_name,
                'school_code' => $school->school_code,
                'logo_url' => $school->logo_url,
                'favicon_url' => $school->favicon_url,
                'browser_title' => $school->browser_title,
                'watermark_text' => $school->watermark_text,
                'compact_sidebar' => (bool) $school->compact_sidebar,
                'is_demo' => app(\App\Services\Tenancy\DemoSchoolService::class)->isDemoRequest(),
                'demo_hidden_import_export' => app(\App\Services\Tenancy\DemoSchoolService::class)->hiddenImportExportKeys(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('erp')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('erp.login');
    }
}