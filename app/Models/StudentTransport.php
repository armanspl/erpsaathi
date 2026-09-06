<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTransport extends Model
{
    protected $fillable = ['student_id', 'route_id', 'route_stop_id', 'start_date', 'fee_start_month', 'status'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
        ];
    }

    /** First month (Y-m) from which transport fare is billable. Falls back to start_date month. */
    public function feeStartMonthKey(): ?string
    {
        $raw = $this->fee_start_month;
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}/', $raw)) {
            return substr($raw, 0, 7);
        }

        if ($this->start_date) {
            return $this->start_date->format('Y-m');
        }

        return null;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class);
    }
}
