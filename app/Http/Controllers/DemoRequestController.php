<?php

namespace App\Http\Controllers;

use App\Mail\DemoRequestMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Homepage "Request a Demo" form: emails the school name and phone to the ERPSaathi team. */
class DemoRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9][0-9 \-]{8,16}$/'],
            'website' => ['nullable', 'max:0'], // honeypot: real visitors never see or fill this
        ], [
            'phone.regex' => 'Please enter a valid phone number.',
            'website.max' => 'Could not send your request.',
        ]);

        $details = [
            'school_name' => trim($data['school_name']),
            'phone' => trim($data['phone']),
            'submitted_at' => now()->timezone('Asia/Kolkata')->format('d-M-Y h:i A').' IST',
            'page' => (string) ($request->headers->get('referer') ?: $request->getSchemeAndHttpHost()),
            'ip' => $request->ip(),
        ];

        try {
            Mail::to(config('services.demo_request.to'))->send(new DemoRequestMail($details));
        } catch (\Throwable $e) {
            Log::error('Demo request email failed', ['error' => $e->getMessage(), 'school' => $details['school_name'], 'phone' => $details['phone']]);

            return response()->json(['message' => 'Could not send your request right now. Please call or WhatsApp us.'], 500);
        }

        return response()->json(['message' => 'Thank you! We will call you soon to set up your demo.']);
    }
}
