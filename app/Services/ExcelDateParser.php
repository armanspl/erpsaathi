<?php

namespace App\Services;

class ExcelDateParser
{
    /**
     * Parses a date cell from the client's spreadsheets, which mixes DD-MM-YYYY, DD/MM/YYYY,
     * DD.MM.YYYY, ISO YYYY-MM-DD, and occasionally raw Excel serial numbers, into 'Y-m-d'.
     * Day-first is assumed for ambiguous DD/MM vs MM/DD (this is Indian school data), and
     * anything that doesn't cleanly match a known format returns null rather than guessing
     * or crashing — Eloquent's date cast throws on unparseable strings, which is the bug
     * this replaces (e.g. Carbon choking on "25/04/1992").
     */
    public static function parse(null|string|int|float $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})$/', $value, $m)) {
            [, $day, $month, $year] = $m;
            if (strlen($year) === 2) {
                $year = ((int) $year >= 70 ? '19' : '20') . $year;
            }

            return checkdate((int) $month, (int) $day, (int) $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        if (preg_match('/^(\d{1,2})-([A-Za-z]{3})-(\d{4})$/', $value, $m)) {
            [, $day, $monthName, $year] = $m;
            $month = date_parse($monthName)['month'] ?? null;

            return ($month && checkdate((int) $month, (int) $day, (int) $year))
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            [, $year, $month, $day] = $m;

            return checkdate((int) $month, (int) $day, (int) $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
