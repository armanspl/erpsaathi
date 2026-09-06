<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SchoolSettingController extends Controller
{
    public function show()
    {
        $setting = SchoolSetting::current();
        if ($setting->signatures === null) {
            $setting->update([
                'signatures' => [
                    ['id' => (string) Str::uuid(), 'label' => 'Principal', 'image_path' => null],
                    ['id' => (string) Str::uuid(), 'label' => 'Parents', 'image_path' => null],
                    ['id' => (string) Str::uuid(), 'label' => 'Examination Controller', 'image_path' => null],
                ],
                'stamps' => $setting->stamps ?? [],
            ]);
            $setting->refresh();
        }

        return response()->json($this->present($setting));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'school_name' => 'required|string|max:255',
            'school_code' => 'nullable|string|max:50',
            'registration_no' => 'nullable|string|max:100',
            'udise_code' => 'nullable|string|max:50',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'block' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'youtube' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',
            'twitter' => 'nullable|string|max:255',
            'browser_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'watermark_text' => 'nullable|string|max:50',
            'show_watermark' => 'nullable|boolean',
            'compact_sidebar' => 'nullable|boolean',
            'established_year' => 'nullable|integer|min:1800|max:'.(date('Y') + 1),
            'current_branch' => 'nullable|string|max:255',
            'signatures' => 'nullable|array',
            'signatures.*.id' => 'nullable|string|max:64',
            'signatures.*.label' => 'nullable|string|max:100',
            'signatures.*.image_path' => 'nullable|string|max:500',
            'stamps' => 'nullable|array',
            'stamps.*.id' => 'nullable|string|max:64',
            'stamps.*.label' => 'nullable|string|max:100',
            'stamps.*.image_path' => 'nullable|string|max:500',
        ]);

        $setting = SchoolSetting::current();

        if (array_key_exists('signatures', $data)) {
            $previousPaths = collect($setting->signatures ?? [])->pluck('image_path')->filter()->all();
            $data['signatures'] = collect($data['signatures'])->map(fn ($row) => [
                'id' => $row['id'] ?? (string) Str::uuid(),
                'label' => trim((string) ($row['label'] ?? '')) ?: 'Signature',
                'image_path' => $row['image_path'] ?? null,
            ])->values()->all();
            $keptPaths = collect($data['signatures'])->pluck('image_path')->filter()->all();
            $this->deleteOrphanAssets($previousPaths, $keptPaths);
        }
        if (array_key_exists('stamps', $data)) {
            $previousPaths = collect($setting->stamps ?? [])->pluck('image_path')->filter()->all();
            $data['stamps'] = collect($data['stamps'])->map(fn ($row) => [
                'id' => $row['id'] ?? (string) Str::uuid(),
                'label' => trim((string) ($row['label'] ?? '')) ?: 'Stamp',
                'image_path' => $row['image_path'] ?? null,
            ])->values()->all();
            $keptPaths = collect($data['stamps'])->pluck('image_path')->filter()->all();
            $this->deleteOrphanAssets($previousPaths, $keptPaths);
        }

        // Keep legacy address column in sync for older readers.
        if (array_key_exists('address_line_1', $data)) {
            $data['address'] = $data['address_line_1'];
        }

        $setting->update($data);

        return response()->json($this->present($setting->fresh()));
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|image|max:4096',
            'kind' => 'required|in:logo,banner,favicon,signature,stamp',
            'item_id' => 'nullable|string|max:64',
        ]);

        $setting = SchoolSetting::current();
        $path = $request->file('file')->store('school-settings', 'local');

        if ($data['kind'] === 'logo') {
            $setting->update(['logo_path' => $path]);
        } elseif ($data['kind'] === 'banner') {
            $setting->update(['banner_path' => $path]);
        } elseif ($data['kind'] === 'favicon') {
            $setting->update(['favicon_path' => $path]);
        } elseif ($data['kind'] === 'signature') {
            $sigs = collect($setting->signatures ?? []);
            $itemId = $data['item_id'] ?? null;
            if ($itemId && $sigs->contains(fn ($s) => ($s['id'] ?? null) === $itemId)) {
                $sigs = $sigs->map(function ($s) use ($itemId, $path) {
                    if (($s['id'] ?? null) === $itemId) {
                        $s['image_path'] = $path;
                    }

                    return $s;
                });
            } else {
                $sigs->push([
                    'id' => $itemId ?: (string) Str::uuid(),
                    'label' => 'Signature',
                    'image_path' => $path,
                ]);
            }
            $setting->update(['signatures' => $sigs->values()->all()]);
        } elseif ($data['kind'] === 'stamp') {
            $stamps = collect($setting->stamps ?? []);
            $itemId = $data['item_id'] ?? null;
            if ($itemId && $stamps->contains(fn ($s) => ($s['id'] ?? null) === $itemId)) {
                $stamps = $stamps->map(function ($s) use ($itemId, $path) {
                    if (($s['id'] ?? null) === $itemId) {
                        $s['image_path'] = $path;
                    }

                    return $s;
                });
            } else {
                $stamps->push([
                    'id' => $itemId ?: (string) Str::uuid(),
                    'label' => 'Stamp',
                    'image_path' => $path,
                ]);
            }
            $setting->update(['stamps' => $stamps->values()->all()]);
        }

        return response()->json($this->present($setting->fresh()));
    }

    public function asset(string $filename)
    {
        $path = 'school-settings/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function present(SchoolSetting $setting): array
    {
        $mapAssets = function (?array $items) use ($setting) {
            return collect($items ?? [])->map(fn ($row) => [
                ...$row,
                'image_url' => $setting->signatureUrl($row['image_path'] ?? null),
            ])->values()->all();
        };

        return [
            ...$setting->toArray(),
            'signatures' => $mapAssets($setting->signatures),
            'stamps' => $mapAssets($setting->stamps),
        ];
    }

    /** Remove signature/stamp image files that are no longer referenced after a save. */
    private function deleteOrphanAssets(array $previousPaths, array $keptPaths): void
    {
        foreach (array_diff($previousPaths, $keptPaths) as $path) {
            if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
