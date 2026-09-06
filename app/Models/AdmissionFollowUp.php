<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionFollowUp extends Model
{
    protected $fillable = [
        'admission_enquiry_id',
        'note',
        'follow_up_date',
        'next_follow_up_date',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_date' => 'date',
            'next_follow_up_date' => 'date',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(AdmissionEnquiry::class, 'admission_enquiry_id');
    }
}
