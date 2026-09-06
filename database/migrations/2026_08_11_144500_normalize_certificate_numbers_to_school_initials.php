<?php

use App\Models\Certificate;
use App\Models\SchoolSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rewrite legacy certificate numbers (TC-2026-0004) to school-initial format (GAS/TC/2026/0004).
     */
    public function up(): void
    {
        $initials = $this->schoolInitials();

        Certificate::query()
            ->orderBy('id')
            ->each(function (Certificate $certificate) use ($initials) {
                $no = (string) $certificate->certificate_no;
                if (! preg_match('/^(BON|TC|CC|MC)-(\d{4})-(\d+)$/', $no, $m)) {
                    return;
                }

                $candidate = sprintf('%s/%s/%s/%04d', $initials, $m[1], $m[2], (int) $m[3]);

                // Avoid unique collisions if a new-format row already owns that number.
                if (Certificate::where('certificate_no', $candidate)->where('id', '!=', $certificate->id)->exists()) {
                    $year = $m[2];
                    $prefix = $m[1];
                    $next = $this->nextAvailable($initials, $prefix, $year);
                    $candidate = sprintf('%s/%s/%s/%04d', $initials, $prefix, $year, $next);
                }

                DB::table('certificates')->where('id', $certificate->id)->update([
                    'certificate_no' => $candidate,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Irreversible: old PREFIX-YEAR-NNNN values are not restored.
    }

    private function schoolInitials(): string
    {
        $name = trim((string) (SchoolSetting::query()->value('school_name') ?? ''));
        if ($name === '') {
            return 'GAS';
        }

        $initials = collect(preg_split('/\s+/', $name) ?: [])
            ->filter()
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'GAS';
    }

    private function nextAvailable(string $initials, string $prefix, string $year): int
    {
        $max = 0;
        $rows = Certificate::query()
            ->where(function ($q) use ($initials, $prefix, $year) {
                $q->where('certificate_no', 'like', "{$initials}/{$prefix}/{$year}/%")
                    ->orWhere('certificate_no', 'like', "{$prefix}-{$year}-%");
            })
            ->pluck('certificate_no');

        foreach ($rows as $no) {
            if (preg_match('/(\d+)$/', (string) $no, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }
};
