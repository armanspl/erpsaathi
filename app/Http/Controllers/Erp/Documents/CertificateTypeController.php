<?php

namespace App\Http\Controllers\Erp\Documents;

use App\Http\Controllers\Controller;
use App\Models\CertificateType;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CertificateTypeController extends Controller
{
    public const ROLES = ['student', 'teacher', 'parent', 'staff', 'driver', 'manager', 'accountant'];

    private const DEFAULTS = [
        ['label' => 'Bonafide', 'prefix' => 'BON'],
        ['label' => 'Transfer Certificate', 'prefix' => 'TC'],
        ['label' => 'Character Certificate', 'prefix' => 'CC'],
        ['label' => 'Migration Certificate', 'prefix' => 'MC'],
    ];

    public function index(Request $request)
    {
        $this->ensureSeeded();

        $query = CertificateType::query();

        if ($request->filled('role')) {
            $role = $request->string('role');
            $query->whereJsonContains('roles', (string) $role);
        }

        return response()->json($query->orderByDesc('is_system')->orderBy('label')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'roles' => 'required|array|min:1',
            'roles.*' => [Rule::in(self::ROLES)],
            'custom_fields' => 'nullable|array',
            'custom_fields.*.key' => 'nullable|string|max:100',
            'custom_fields.*.label' => 'nullable|string|max:255',
            'custom_fields.*.type' => 'nullable|string|in:text,number,date',
        ]);

        $customFields = collect($data['custom_fields'] ?? [])
            ->filter(fn ($f) => trim($f['key'] ?? '') !== '' && trim($f['label'] ?? '') !== '')
            ->map(fn ($f) => ['key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'] ?: 'text'])
            ->values()
            ->all();

        $type = CertificateType::create([
            'label' => $data['label'],
            'prefix' => $this->nextPrefix($data['label']),
            'roles' => array_values($data['roles']),
            'custom_fields' => $customFields,
            'is_system' => false,
        ]);

        return response()->json($type, 201);
    }

    /** Assigns (or clears, when template_id is null) the template used to print/download this certificate type. */
    public function assignTemplate(Request $request, CertificateType $certificateType)
    {
        $data = $request->validate([
            'template_id' => ['nullable', Rule::exists('templates', 'id')->where('category', 'certificate')],
        ]);

        $certificateType->update(['template_id' => $data['template_id'] ?? null]);

        return response()->json($certificateType->fresh());
    }

    public function destroy(CertificateType $certificateType)
    {
        $issuedCount = $certificateType->certificates()->count();
        abort_if(
            $issuedCount > 0,
            422,
            "Can't delete \"{$certificateType->label}\" — {$issuedCount} certificate(s) have already been issued under this type."
        );

        $certificateType->delete();

        return response()->json(['success' => true]);
    }

    private function ensureSeeded(): void
    {
        if (CertificateType::where('is_system', true)->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $default) {
            CertificateType::create([
                'label' => $default['label'],
                'prefix' => $default['prefix'],
                'roles' => ['student'],
                'custom_fields' => [],
                'is_system' => true,
            ]);
        }
    }

    private function nextPrefix(string $label): string
    {
        $letters = preg_replace('/[^A-Za-z\s]/', '', $label);
        $initials = collect(preg_split('/\s+/', trim($letters)))->filter()->map(fn ($w) => strtoupper($w[0]))->implode('');
        $base = strlen($initials) >= 2 ? $initials : strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $label), 0, 3));
        $base = $base !== '' ? $base : 'CT';

        $prefix = $base;
        $suffix = 1;
        while (CertificateType::where('prefix', $prefix)->exists()) {
            $suffix++;
            $prefix = $base.$suffix;
        }

        return $prefix;
    }
}
