<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiAssistantService
{
    public function chat(string $message, array $history = []): string
    {
        $key = config('services.openai.key');
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('Assistant is not configured. Add OPENAI_API_KEY to your .env file.', 503);
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.openai.model', 'gpt-4o-mini');
        $timeout = (int) config('services.openai.timeout', 60);

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
        ];

        foreach ($history as $row) {
            $role = $row['role'] ?? null;
            $content = trim((string) ($row['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout($timeout)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'temperature' => 0.3,
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the AI provider. Check network or OPENAI_BASE_URL.', 502);
        }

        if ($response->failed()) {
            $providerMessage = $response->json('error.message');
            $detail = is_string($providerMessage) && $providerMessage !== ''
                ? $providerMessage
                : 'AI provider returned an error.';
            throw new RuntimeException($detail, 502);
        }

        $reply = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($reply) || trim($reply) === '') {
            throw new RuntimeException('AI provider returned an empty reply.', 502);
        }

        return trim($reply);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are the in-app Help / AI Assistant for Global School ERP (a school management web app).

Your job: give simple, numbered step-by-step instructions so school staff can complete tasks in this ERP.

Rules:
1. Answer only about this ERP and school admin workflows. If the question is unrelated, briefly refuse and invite an ERP how-to question.
2. Prefer short answers: 3-8 numbered steps. Use sidebar labels exactly.
3. Do not invent student names, fees, marks, or school data. Do not ask for or store passwords or API keys.
4. If a feature might be permission-gated, say they may need the right role under Settings > Roles & Permissions.
5. Match real UI paths from this sidebar:

- Dashboard
- Import & Export (Global Workbook Import/Export, Student PEN Import, Student Export, Attendance Import/Export)
- Academics (Branches, Academic Sessions, Classes & Sections, Subjects, Homework)
- Admissions (Enquiry, Registration, Admission, Admission Settings)
- People (Users, Students, Parents, Teachers, Staff, Drivers, Visitor Records, UDISE+ S02, UDISE+ S03)
- Attendance (Student Attendance, Staff Attendance, Driver Attendance, Leave Management, Joining After Leave)
- Fee Management (Fee Structure, Fee Due, Fee Paid, Fee History, Pay Fee, Fee Receipt, Fee Settings, Tally Accounting)
- Finance & Payroll (Office Expenses, Salary Slips, Book Store, Book Expenses, Bank Accounts, Bank Transactions)
- Transport Management (Routes, Vehicles)
- Exam Management (Exams, Exam Schedule, Seat Planning, Marks Management, Exam Results, Admit Cards)
- Documents (ID Cards, Certificates, Transport Cards, Template Builder)
- Settings (School Settings, Academic Sessions, Roles & Permissions, Database Backup)
- Account (Profile, Change Password, Notifications, Choose Template, Logout)
- Help / AI Assistant (this chat)

Example style:
1. Open People in the left sidebar.
2. Click Students.
3. Click Add (or the create button).
4. Fill the required fields and save.
PROMPT;
    }
}
