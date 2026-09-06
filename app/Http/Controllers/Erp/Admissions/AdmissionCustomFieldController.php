<?php

namespace App\Http\Controllers\Erp\Admissions;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCustomField;
use App\Support\AdmissionsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class AdmissionCustomFieldController extends Controller
{
    public function index()
    {
        $payload = Cache::remember(AdmissionsCache::CUSTOM_FIELDS, AdmissionsCache::TTL, function () {
            return AdmissionCustomField::orderBy('sort_order')->orderBy('id')->get();
        });

        return response()->json($payload);
    }

    /** Active fields only — used by admission/registration/student forms. */
    public function active()
    {
        $payload = Cache::remember(AdmissionsCache::CUSTOM_FIELDS_ACTIVE, AdmissionsCache::TTL, function () {
            return AdmissionCustomField::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });

        return response()->json($payload);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = $data['sort_order'] ?? ((int) AdmissionCustomField::max('sort_order') + 1);

        $field = AdmissionCustomField::create($data);
        AdmissionsCache::forget();

        return response()->json($field, 201);
    }

    public function update(Request $request, AdmissionCustomField $customField)
    {
        $customField->update($this->validated($request, true));
        AdmissionsCache::forget();

        return response()->json($customField->fresh());
    }

    public function destroy(AdmissionCustomField $customField)
    {
        $customField->delete();
        AdmissionsCache::forget();

        return response()->json(['success' => true]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'label' => [$required, 'string', 'max:255'],
            'type' => [$required, Rule::in(['text', 'number', 'date', 'textarea'])],
            'placeholder' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }
}
