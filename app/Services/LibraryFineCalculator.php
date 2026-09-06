<?php

namespace App\Services;

use App\Models\BookIssue;
use Carbon\Carbon;

class LibraryFineCalculator
{
    private const FINE_PER_DAY = 2.0;

    /** Fine owed for an issue, based on how many days past the due date the given return date falls. */
    public static function forReturn(BookIssue $issue, string $returnDate): float
    {
        $due = Carbon::parse($issue->due_date)->startOfDay();
        $returned = Carbon::parse($returnDate)->startOfDay();

        $daysLate = max(0, $due->diffInDays($returned, false));

        return round($daysLate * self::FINE_PER_DAY, 2);
    }
}
