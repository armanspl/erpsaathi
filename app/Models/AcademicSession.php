<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AcademicSession extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * Session history / imports may store either "YYYY-YYYY" (academic_sessions.name)
     * or the short "YYYY-YY" shape from source files — match both when filtering.
     *
     * @return list<string>
     */
    public static function nameAliases(string $name): array
    {
        $name = trim($name);
        $aliases = [$name];

        if (preg_match('/^(\d{4})-(\d{4})$/', $name, $m)) {
            $aliases[] = $m[1].'-'.substr($m[2], -2);
        } elseif (preg_match('/^(\d{4})-(\d{2})$/', $name, $m)) {
            $aliases[] = $m[1].'-'.substr($m[1], 0, 2).$m[2];
        }

        return array_values(array_unique($aliases));
    }

    /**
     * Resolve the ERP header session picker into a concrete session (or null = All).
     * Falls back to the is_current session when the header is missing/invalid and
     * $fallbackToCurrent is true (used for fee computations that need one session).
     */
    public static function fromRequest(?Request $request = null, bool $fallbackToCurrent = false): ?self
    {
        $request ??= request();
        $header = trim((string) $request->header('X-Academic-Session', ''));

        if ($header !== '' && strcasecmp($header, 'all') !== 0) {
            $session = static::where('name', $header)->first();
            if ($session) {
                return $session;
            }
        }

        if ($fallbackToCurrent || $header === '') {
            return static::where('is_current', true)->first();
        }

        return null;
    }

    public static function requestWantsAll(?Request $request = null): bool
    {
        $request ??= request();
        $header = trim((string) $request->header('X-Academic-Session', ''));

        return $header === '' || strcasecmp($header, 'all') === 0;
    }

    /**
     * Scope rows that store an academic_session_id FK to the header picker.
     * No-op when the header is "All Sessions" or the session cannot be resolved.
     */
    public static function applySessionIdFilter($query, ?Request $request = null, string $column = 'academic_session_id'): void
    {
        $request ??= request();
        if (static::requestWantsAll($request)) {
            return;
        }

        $session = static::fromRequest($request);
        if ($session) {
            $query->where($column, $session->id);
        }
    }

    /**
     * Scope date-based ledgers to the selected session's start/end window.
     * No-op when All Sessions, or when the session has no dates.
     */
    public static function applyDateWindow($query, ?Request $request = null, string $column = 'date'): void
    {
        $request ??= request();
        if (static::requestWantsAll($request)) {
            return;
        }

        $session = static::fromRequest($request);
        if (! $session?->start_date || ! $session?->end_date) {
            return;
        }

        $query->whereDate($column, '>=', $session->start_date->toDateString())
            ->whereDate($column, '<=', $session->end_date->toDateString());
    }

    /**
     * Scope students via session history aliases (same rules as StudentController::index).
     * Manually-added students with no history still appear when the session is current.
     */
    public static function applyStudentSessionFilter($query, ?Request $request = null): void
    {
        $request ??= request();
        if (static::requestWantsAll($request)) {
            return;
        }

        $session = static::fromRequest($request);
        if (! $session) {
            return;
        }

        $aliases = static::nameAliases($session->name);

        $query->where(function ($q) use ($aliases, $session) {
            $q->whereHas('sessionHistories', fn ($h) => $h->whereIn('session', $aliases));
            if ($session->is_current) {
                $q->orWhereDoesntHave('sessionHistories');
            }
        });
    }

    /**
     * Month keys (Y-m) + labels from start_date through end_date (inclusive).
     *
     * @return list<array{key: string, label: string, date: string}>
     */
    public function months(): array
    {
        if (! $this->start_date || ! $this->end_date) {
            return [];
        }

        $cursor = $this->start_date->copy()->startOfMonth();
        $end = $this->end_date->copy()->startOfMonth();
        $out = [];
        while ($cursor <= $end) {
            $out[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
                'date' => $cursor->toDateString(),
            ];
            $cursor->addMonth();
        }

        return $out;
    }

    /**
     * Previous / current / next sessions around $center (by start_date).
     *
     * @return array{previous: ?self, current: ?self, next: ?self}
     */
    public static function neighbors(?self $center): array
    {
        if (! $center) {
            $center = static::where('is_current', true)->first();
        }

        if (! $center) {
            return ['previous' => null, 'current' => null, 'next' => null];
        }

        $ordered = static::orderBy('start_date')->get();
        $idx = $ordered->search(fn (self $s) => $s->id === $center->id);

        return [
            'previous' => $idx !== false && $idx > 0 ? $ordered[$idx - 1] : null,
            'current' => $center,
            'next' => $idx !== false && $idx < $ordered->count() - 1 ? $ordered[$idx + 1] : null,
        ];
    }
}
