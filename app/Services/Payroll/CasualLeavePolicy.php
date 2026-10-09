<?php

namespace App\Services\Payroll;

use App\Models\AcademicSession;
use App\Models\SalarySlip;
use App\Models\SchoolSetting;
use Illuminate\Validation\ValidationException;

/**
 * School-wide Casual Leave (CL) policy + balance derived from saved salary slips.
 * Balance is not stored separately — creating/editing/deleting a slip adjusts used CL automatically.
 */
class CasualLeavePolicy
{
    /** @return array<string, mixed> */
    public static function settings(?SchoolSetting $setting = null): array
    {
        $s = $setting ?? SchoolSetting::current();

        return [
            'yearly_limit' => (float) ($s->cl_yearly_limit ?? 12),
            'monthly_limit' => (float) ($s->cl_monthly_limit ?? 2),
            'leave_year' => in_array($s->cl_leave_year ?? 'calendar', ['calendar', 'academic'], true)
                ? ($s->cl_leave_year ?? 'calendar')
                : 'calendar',
            'allow_carry_forward' => (bool) ($s->cl_allow_carry_forward ?? false),
            'max_carry_forward' => (float) ($s->cl_max_carry_forward ?? 0),
            'allow_half_day' => (bool) ($s->cl_allow_half_day ?? false),
        ];
    }

    /**
     * CL balance for one staff member for the leave year that contains $period (Y-m).
     *
     * @return array{
     *   yearly_entitlement: float,
     *   yearly_limit: float,
     *   carry_forward: float,
     *   yearly_used: float,
     *   yearly_remaining: float,
     *   monthly_limit: float,
     *   monthly_used: float,
     *   monthly_remaining: float,
     *   allow_half_day: bool,
     *   leave_year: string,
     *   leave_year_start: string,
     *   leave_year_end: string,
     *   period: string
     * }
     */
    public static function balance(string $employeeType, int $employeeId, string $period, ?int $excludeSlipId = null): array
    {
        $settings = self::settings();
        [$yearStart, $yearEnd] = self::leaveYearBounds($period, $settings['leave_year']);

        $yearlyUsed = self::sumCl($employeeType, $employeeId, $yearStart, $yearEnd, $excludeSlipId);
        $monthlyUsed = self::sumCl($employeeType, $employeeId, $period, $period, $excludeSlipId);

        $carryForward = 0.0;
        if ($settings['allow_carry_forward'] && $settings['max_carry_forward'] > 0) {
            $carryForward = self::carryForwardDays($employeeType, $employeeId, $yearStart, $settings);
        }

        $entitlement = round($settings['yearly_limit'] + $carryForward, 2);
        $yearlyRemaining = max(0, round($entitlement - $yearlyUsed, 2));
        $monthlyRemaining = max(0, round($settings['monthly_limit'] - $monthlyUsed, 2));

        return [
            'yearly_entitlement' => $entitlement,
            'yearly_limit' => $settings['yearly_limit'],
            'carry_forward' => $carryForward,
            'yearly_used' => $yearlyUsed,
            'yearly_remaining' => $yearlyRemaining,
            'monthly_limit' => $settings['monthly_limit'],
            'monthly_used' => $monthlyUsed,
            'monthly_remaining' => $monthlyRemaining,
            'allow_half_day' => $settings['allow_half_day'],
            'leave_year' => $settings['leave_year'],
            'leave_year_start' => $yearStart,
            'leave_year_end' => $yearEnd,
            'period' => $period,
        ];
    }

    /**
     * Validate CL for create/update salary slip. Throws ValidationException on failure.
     */
    public static function assertValid(
        string $employeeType,
        int $employeeId,
        string $period,
        float $cl,
        int $daysInMonth,
        ?int $excludeSlipId = null,
    ): void {
        $settings = self::settings();

        if ($cl < 0) {
            throw ValidationException::withMessages(['cl' => 'CL cannot be negative.']);
        }

        if ($cl > $daysInMonth + 0.001) {
            throw ValidationException::withMessages([
                'cl' => "CL cannot be greater than the {$daysInMonth} days in this month.",
            ]);
        }

        if (! $settings['allow_half_day'] && abs($cl - round($cl)) > 0.001) {
            throw ValidationException::withMessages([
                'cl' => 'Half-day CL is not allowed. Enter a whole number of CL days.',
            ]);
        }

        if ($settings['allow_half_day'] && abs(($cl * 2) - round($cl * 2)) > 0.001) {
            throw ValidationException::withMessages([
                'cl' => 'CL may be entered in half-day steps only (e.g. 0.5, 1, 1.5).',
            ]);
        }

        if ($cl < 0.001) {
            return;
        }

        $balance = self::balance($employeeType, $employeeId, $period, $excludeSlipId);

        if ($cl > $balance['monthly_remaining'] + 0.001) {
            $max = self::formatDays($balance['monthly_limit']);
            throw ValidationException::withMessages([
                'cl' => "Monthly CL limit exceeded. Maximum {$max} CL days are allowed in this month.",
            ]);
        }

        if ($cl > $balance['yearly_remaining'] + 0.001) {
            throw ValidationException::withMessages([
                'cl' => 'Yearly CL balance exhausted. No CL days are remaining for this leave year.',
            ]);
        }
    }

    /**
     * Inclusive Y-m bounds for the leave year containing $period.
     *
     * @return array{0: string, 1: string}
     */
    public static function leaveYearBounds(string $period, ?string $mode = null): array
    {
        $mode = $mode ?? self::settings()['leave_year'];
        [$year, $month] = array_map('intval', explode('-', $period));

        if ($mode === 'academic') {
            $session = AcademicSession::query()
                ->whereDate('start_date', '<=', sprintf('%04d-%02d-15', $year, $month))
                ->whereDate('end_date', '>=', sprintf('%04d-%02d-15', $year, $month))
                ->orderByDesc('is_current')
                ->first()
                ?? AcademicSession::query()->where('is_current', true)->first();

            if ($session?->start_date && $session?->end_date) {
                return [
                    $session->start_date->format('Y-m'),
                    $session->end_date->format('Y-m'),
                ];
            }
        }

        return [sprintf('%04d-01', $year), sprintf('%04d-12', $year)];
    }

    private static function carryForwardDays(string $employeeType, int $employeeId, string $currentYearStart, array $settings): float
    {
        [$y, $m] = array_map('intval', explode('-', $currentYearStart));
        $prevCursor = sprintf('%04d-%02d', $m === 1 ? $y - 1 : $y, $m === 1 ? 12 : $m - 1);
        [$prevStart, $prevEnd] = self::leaveYearBounds($prevCursor, $settings['leave_year']);

        // Avoid double-counting if bounds somehow equal current year.
        if ($prevStart === $currentYearStart) {
            return 0.0;
        }

        $prevUsed = self::sumCl($employeeType, $employeeId, $prevStart, $prevEnd, null);
        $prevRemaining = max(0, $settings['yearly_limit'] - $prevUsed);

        return round(min($prevRemaining, $settings['max_carry_forward']), 2);
    }

    private static function sumCl(string $employeeType, int $employeeId, string $fromPeriod, string $toPeriod, ?int $excludeSlipId): float
    {
        $sum = SalarySlip::query()
            ->where('employee_type', $employeeType)
            ->where('employee_id', $employeeId)
            ->where('period', '>=', $fromPeriod)
            ->where('period', '<=', $toPeriod)
            ->when($excludeSlipId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->sum('cl');

        return round((float) $sum, 2);
    }

    private static function formatDays(float $days): string
    {
        return fmod($days, 1.0) < 0.001 ? (string) (int) round($days) : rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.');
    }
}
