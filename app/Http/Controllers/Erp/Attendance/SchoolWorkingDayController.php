<?php

namespace App\Http\Controllers\Erp\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolWorkingDay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * School-wide working days per month (Mar/26 = 20, Apr/26 = 25, …) for one attendance session.
 * Months follow Student Attendance: March of the start year through March of the next year.
 */
class SchoolWorkingDayController extends Controller
{
    /** [month, year offset from the session start year] — same 13 months as Student Attendance. */
    private const MONTH_ORDER = [
        [3, 0], [4, 0], [5, 0], [6, 0], [7, 0], [8, 0], [9, 0],
        [10, 0], [11, 0], [12, 0], [1, 1], [2, 1], [3, 1],
    ];

    public function index(Request $request)
    {
        $data = $request->validate(['session_start_year' => 'nullable|integer|min:2000|max:2100']);
        $startYear = (int) ($data['session_start_year'] ?? $this->currentStartYear());

        if (! SchoolWorkingDay::tableReady()) {
            return response()->json($this->payload($startYear, collect()) + [
                'ready' => false,
                'message' => 'Working days need a database update — run the migration (php artisan migrate / erp:deploy) first.',
            ]);
        }

        $saved = SchoolWorkingDay::query()
            ->where(fn ($q) => $q->where('year', $startYear)->orWhere(fn ($q) => $q->where('year', $startYear + 1)->where('month', '<=', 3)))
            ->get()
            ->keyBy(fn (SchoolWorkingDay $w) => sprintf('%04d-%02d', $w->year, $w->month));

        return response()->json($this->payload($startYear, $saved) + ['ready' => true]);
    }

    /** Save the months sent; a blank month removes its saved value. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'session_start_year' => 'required|integer|min:2000|max:2100',
            'months' => 'required|array|min:1|max:13',
            'months.*.year' => 'required|integer|min:2000|max:2101',
            'months.*.month' => 'required|integer|min:1|max:12',
            'months.*.working_days' => 'nullable|integer|min:0|max:31',
        ], [
            'months.*.working_days.max' => 'A month can have at most 31 working days.',
        ]);

        if (! SchoolWorkingDay::tableReady()) {
            return response()->json(['message' => 'Working days need a database update — run the migration first.'], 409);
        }

        DB::transaction(function () use ($data) {
            foreach ($data['months'] as $row) {
                $key = ['year' => (int) $row['year'], 'month' => (int) $row['month']];
                if (($row['working_days'] ?? null) === null) {
                    SchoolWorkingDay::query()->where($key)->delete();

                    continue;
                }
                SchoolWorkingDay::query()->updateOrCreate($key, [
                    'working_days' => (int) $row['working_days'],
                    'updated_by_id' => Auth::guard('erp')->id(),
                ]);
            }
        });

        return $this->index(new Request(['session_start_year' => (int) $data['session_start_year']]));
    }

    private function currentStartYear(): int
    {
        $start = AcademicSession::where('is_current', true)->value('start_date');

        return $start ? (int) substr((string) $start, 0, 4) : (int) now()->format('Y');
    }

    /** @param  \Illuminate\Support\Collection<string, SchoolWorkingDay>  $saved */
    private function payload(int $startYear, $saved): array
    {
        $months = [];
        foreach (self::MONTH_ORDER as [$month, $offset]) {
            $year = $startYear + $offset;
            $months[] = [
                'year' => $year,
                'month' => $month,
                'label' => date('M', mktime(0, 0, 0, $month, 1)).'/'.substr((string) $year, -2),
                'working_days' => $saved->get(sprintf('%04d-%02d', $year, $month))?->working_days,
            ];
        }

        $current = (int) now()->format('Y');
        $years = range($current + 1, $current - 3);

        return [
            'session_start_year' => $startYear,
            'session_label' => sprintf('%04d-%02d', $startYear, ($startYear + 1) % 100),
            'available_years' => in_array($startYear, $years, true) ? $years : array_merge([$startYear], $years),
            'months' => $months,
            'total' => array_sum(array_map(fn ($m) => (int) $m['working_days'], $months)),
            'months_filled' => count(array_filter($months, fn ($m) => $m['working_days'] !== null)),
        ];
    }
}
