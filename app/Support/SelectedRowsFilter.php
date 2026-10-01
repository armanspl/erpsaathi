<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Narrows a result-row list down to a specific set of students — used by the Exam Results page's
 * "Download" / "Download ZIP" buttons when the user has checked individual rows instead of
 * downloading everyone currently listed. No `student_ids` in the request (or an empty one) means
 * "no selection was made", so every row is kept — exactly today's behaviour.
 */
class SelectedRowsFilter
{
    /**
     * @param  array<int, array<string, mixed>>  $rows  each row must have a 'student_id' key
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $rows, Request $request): array
    {
        $ids = $request->input('student_ids');
        if (! is_array($ids) || $ids === []) {
            return $rows;
        }

        $ids = array_map('intval', $ids);

        return array_values(array_filter($rows, fn ($row) => in_array((int) $row['student_id'], $ids, true)));
    }
}
