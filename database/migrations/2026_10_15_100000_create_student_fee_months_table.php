<?php

use App\Models\AcademicSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * People > Students "Fee Months" / "Transport Fee Months": the calendar months (1–12) a student
 * is charged in, saved per academic session. A NULL column (or no row) means "not chosen yet" —
 * FeeBalanceService then works the months out from the old Fee Start Month / admission date and
 * transport fee start exactly as before, so no existing student's dues change.
 *
 * Existing Discount Months rows (student_fee_discount_months) are converted for their session to
 * Fee Months with those months unticked — but only when no payment touches those months; the
 * original discount rows are kept as they are (nothing is deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_fee_months')) {
            Schema::create('student_fee_months', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
                $table->json('fee_months')->nullable();
                $table->json('transport_months')->nullable();
                $table->timestamps();

                $table->unique(['student_id', 'academic_session_id'], 'student_fee_months_unique');
            });
        }

        if (! Schema::hasTable('student_fee_discount_months')) {
            return;
        }

        foreach (DB::table('student_fee_discount_months')->get() as $row) {
            $discount = array_values(array_filter(array_map('intval', (array) json_decode((string) $row->months, true)), fn ($m) => $m >= 1 && $m <= 12));
            $session = AcademicSession::find($row->academic_session_id);
            $student = DB::table('students')->where('id', $row->student_id)->first(['id', 'fee_start_month', 'admission_date']);
            if ($discount === [] || ! $session || ! $student
                || DB::table('student_fee_months')->where('student_id', $row->student_id)->where('academic_session_id', $row->academic_session_id)->exists()) {
                continue;
            }

            $keys = collect($session->months())->pluck('key')->filter()->values();
            $discountKeys = $keys->filter(fn ($k) => in_array((int) substr($k, 5, 2), $discount, true))->values();

            // A payment (or waiver line) on a discount month would lose its month if unticked —
            // leave that student/session on the old Discount Months behaviour instead.
            $touched = DB::table('fee_payments')->where('student_id', $row->student_id)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhereNotIn('status', ['Refunded', 'Rolled Back']);
                })
                ->pluck('items')
                ->contains(fn ($items) => $discountKeys->contains(fn ($k) => str_contains((string) $items, '"'.$k.'"')));
            if ($touched) {
                Log::warning('student_fee_months: kept Discount Months (payments exist)', ['student_id' => $row->student_id, 'session_id' => $row->academic_session_id]);

                continue;
            }

            // Old rule for this session: months on/after Fee Start Month (or admission month).
            $start = is_string($student->fee_start_month) && preg_match('/^\d{4}-\d{2}/', $student->fee_start_month)
                ? substr($student->fee_start_month, 0, 7)
                : ($student->admission_date ? substr((string) $student->admission_date, 0, 7) : null);
            $feeMonths = $keys
                ->filter(fn ($k) => ! $start || $k >= $start)
                ->map(fn ($k) => (int) substr($k, 5, 2))
                ->reject(fn ($m) => in_array($m, $discount, true))
                ->values()
                ->all();

            // Discount Months waived transport too — carry that over as unticked Transport Fee
            // Months (old transport rule: on/after transport fee start and the fee start).
            $transportMonths = null;
            $transport = DB::table('student_transports')->where('student_id', $row->student_id)->where('status', 'Active')->first(['fee_start_month', 'start_date']);
            if ($transport) {
                $tStart = is_string($transport->fee_start_month) && preg_match('/^\d{4}-\d{2}/', $transport->fee_start_month)
                    ? substr($transport->fee_start_month, 0, 7)
                    : ($transport->start_date ? substr((string) $transport->start_date, 0, 7) : null);
                $transportMonths = $keys
                    ->filter(fn ($k) => (! $tStart || $k >= $tStart) && (! $start || $k >= $start))
                    ->map(fn ($k) => (int) substr($k, 5, 2))
                    ->reject(fn ($m) => in_array($m, $discount, true))
                    ->values()
                    ->all();
            }

            DB::table('student_fee_months')->insert([
                'student_id' => $row->student_id,
                'academic_session_id' => $row->academic_session_id,
                'fee_months' => json_encode($feeMonths),
                'transport_months' => $transportMonths === null ? null : json_encode($transportMonths),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_months');
    }
};
