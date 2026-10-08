<?php

namespace App\Services\Payroll;

use App\Models\Driver;
use App\Models\Staff;
use App\Models\Teacher;

/**
 * Finds the Teacher/Staff/Driver an Excel row belongs to. The school's workbooks write the same
 * person's EMPL_CODE differently from sheet to sheet (121008 in SALARY DETAILS, 12008 in the
 * monthly sheets — the 3rd digit dropped) and that shortened code can belong to someone else
 * (25018 is FILZA RIZWAN in one sheet, the short form of SHAHZAD ANSARI's 252018 in another).
 * So a non-exact code only counts when the name agrees too; otherwise a unique name decides.
 * Ambiguity is never guessed — resolve() returns null with a reason.
 */
class EmployeeMatcher
{
    public const TYPES = ['teacher', 'staff', 'driver'];

    /** Name prefixes ignored when comparing ("MD TAUSEEF AFTAB" == "TAUSEEF AFTAB"). */
    private const NAME_PREFIXES = ['MD', 'MOHD', 'MOHAMMAD', 'MOHAMMED', 'MOHAMAD', 'M'];

    /** @var list<array{type:string, id:int, code:string, name:string}> */
    private array $employees = [];

    public static function fromDatabase(): self
    {
        $matcher = new self();
        foreach (['teacher' => Teacher::class, 'staff' => Staff::class, 'driver' => Driver::class] as $type => $class) {
            foreach ($class::query()->get(['id', 'employee_id', 'name']) as $e) {
                $matcher->add($type, (int) $e->id, (string) $e->employee_id, (string) $e->name);
            }
        }

        return $matcher;
    }

    public function add(string $type, int $id, string $code, string $name): void
    {
        $this->employees[] = ['type' => $type, 'id' => $id, 'code' => $code, 'name' => $name];
    }

    /**
     * @param  string|null  $onlyType  limit to one table (teacher|staff|driver)
     * @param  string|null  $preferType  tie-breaker when two people fit equally (e.g. MUNTAZIR AHMAD is
     *                                   both a teacher and a driver) — usually the designation sheet's type
     * @return array{type:string, id:int, code:string, name:string, matched_by:string}|array{error:string}|null
     *         a match, an ambiguity error, or null when nobody matches
     */
    public function resolve(string $code, string $name, ?string $onlyType = null, ?string $preferType = null): ?array
    {
        $result = $this->resolveIn($code, $name, $onlyType);
        if ($preferType !== null && isset($result['error']) && $onlyType === null) {
            $preferred = $this->resolveIn($code, $name, $preferType);
            if ($preferred !== null && ! isset($preferred['error'])) {
                return $preferred;
            }
        }

        return $result;
    }

    private function resolveIn(string $code, string $name, ?string $onlyType): ?array
    {
        $pool = $onlyType === null ? $this->employees : array_values(array_filter($this->employees, fn ($e) => $e['type'] === $onlyType));

        if ($code !== '') {
            // Codes get reused for new people over the years (202401 was SADIYA KHAN, later GITA
            // DEVI and POONAM DEVI) — so even an exact code needs the name to agree, or at least
            // the first name (no name on the row = trust the code).
            $agrees = fn ($e) => $name === '' || self::similarNames($e['name'], $name) || self::firstName($e['name']) === self::firstName($name);
            $exact = array_values(array_filter($pool, fn ($e) => $e['code'] === $code && $agrees($e)));
            if (count($exact) === 1) {
                return $exact[0] + ['matched_by' => 'code'];
            }
            if (count($exact) > 1) {
                // Same code in two tables — let the full name decide between them.
                $named = array_values(array_filter($exact, fn ($e) => self::similarNames($e['name'], $name)));
                if (count($named) === 1) {
                    return $named[0] + ['matched_by' => 'code'];
                }

                return ['error' => "EMPL_CODE {$code} belongs to more than one employee (".implode(', ', array_column($exact, 'type')).') — resolve manually.'];
            }

            $sameCode = array_values(array_filter($pool, fn ($e) => self::codesMatch($e['code'], $code)));
            $variant = array_values(array_filter($sameCode, fn ($e) => self::similarNames($e['name'], $name)));
            if (count($variant) === 1) {
                return $variant[0] + ['matched_by' => 'code+name'];
            }
            if (count($variant) > 1) {
                $exactName = array_values(array_filter($variant, fn ($e) => self::normalizeName($e['name']) === self::normalizeName($name)));
                if (count($exactName) === 1) {
                    return $exactName[0] + ['matched_by' => 'code+name'];
                }

                return ['error' => "EMPL_CODE {$code} / \"{$name}\" fits more than one employee (".implode(', ', array_map(fn ($e) => "{$e['type']} {$e['code']}", $variant)).') — resolve manually.'];
            }
            // Nickname on the sheet ("ASHRAF SIR" for ASHRAF ALI): same code family + same first name.
            $firstName = array_values(array_filter($sameCode, fn ($e) => self::firstName($e['name']) !== '' && self::firstName($e['name']) === self::firstName($name)));
            if (count($firstName) === 1) {
                return $firstName[0] + ['matched_by' => 'code+first name'];
            }
        }

        if ($name === '') {
            return null;
        }

        $key = self::normalizeName($name);
        $byName = array_values(array_filter($pool, fn ($e) => self::normalizeName($e['name']) === $key));
        if (count($byName) === 1) {
            return $byName[0] + ['matched_by' => 'name'];
        }
        if (count($byName) > 1) {
            return ['error' => "\"{$name}\" matches more than one employee by name (".implode(', ', array_column($byName, 'type')).') and the code does not decide — fix the code or resolve manually.'];
        }

        $similar = array_values(array_filter($pool, fn ($e) => self::similarNames($e['name'], $name)));
        if (count($similar) === 1) {
            return $similar[0] + ['matched_by' => 'similar name'];
        }

        return null;
    }

    /** "121008" and "12008" are the same employee code written two ways (3rd digit dropped). */
    public static function codesMatch(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }

        return $a === $b || self::shortCode($a) === $b || $a === self::shortCode($b);
    }

    public static function shortCode(string $code): ?string
    {
        return preg_match('/^\d{6}$/', $code) ? substr($code, 0, 2).substr($code, 3) : null;
    }

    public static function normalizeName(string $name): string
    {
        $clean = mb_strtoupper(str_replace('.', ' ', $name));

        return preg_replace('/\s+/', ' ', trim($clean)) ?? '';
    }

    public static function firstName(string $name): string
    {
        foreach (explode(' ', self::normalizeName($name)) as $token) {
            if ($token !== '' && ! in_array($token, self::NAME_PREFIXES, true)) {
                return $token;
            }
        }

        return '';
    }

    /** Same person despite spelling drift: NAUSHEEN PARWEEN ~ NOUSHEEN PARVEEN, IBRAHIM ~ IBRAHIM ALI. */
    public static function similarNames(string $a, string $b): bool
    {
        $strip = function (string $name): string {
            $tokens = array_values(array_filter(explode(' ', self::normalizeName($name)), fn ($t) => ! in_array($t, self::NAME_PREFIXES, true)));

            return implode(' ', $tokens);
        };
        $x = $strip($a);
        $y = $strip($b);
        if ($x === '' || $y === '') {
            return false;
        }
        if ($x === $y) {
            return true;
        }
        // "IBRAHIM" ~ "IBRAHIM ALI" (surname added) — but only at the start: "MD IQBAL" is not
        // "JUWERIYA IQBAL".
        if (min(strlen($x), strlen($y)) >= 5 && (str_starts_with($x, $y.' ') || str_starts_with($y, $x.' '))) {
            return true;
        }

        // Different first names are different people, even when the whole names are close
        // (SULTANA PARWEEN vs SHABANA PARWEEN); a one-letter slip is fine (NOUSHEEN / NAUSHEEN).
        $xTokens = explode(' ', $x);
        $yTokens = explode(' ', $y);
        if (count($xTokens) >= 2 && count($yTokens) >= 2 && levenshtein($xTokens[0], $yTokens[0]) > 1) {
            return false;
        }

        // Allowed typos grow with length: short names (SAHIL vs SHAHID) must be nearly exact.
        $shortest = min(strlen($x), strlen($y));

        return levenshtein($x, $y) <= ($shortest >= 14 ? 3 : ($shortest >= 8 ? 2 : 1));
    }

    /** Excel gives whole numbers as floats ("12008.0") — normalize to a plain integer string. */
    public static function normalizeCode($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }
        $code = trim((string) $value);

        return is_numeric($code) && str_contains($code, '.') ? (string) (int) round((float) $code) : $code;
    }
}
