<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionEnquiry extends Model
{
    protected $appends = ['status'];

    protected $fillable = [
        'enquiry_no',
        'branch_id',
        'student_name',
        'parent_name',
        'phone',
        'whatsapp_optin',
        'email',
        'class_applying_for_id',
        'present_school',
        'address_line_1',
        'city',
        'state',
        'pincode',
        'gender',
        'dob',
        'source',
        'preferred_contact_time',
        'preferred_mode',
        'preferred_contact_date',
        'transport_required',
        'consent_given',
        'stage',
        'lead_status',
        'remarks',
        'next_follow_up_date',
        'fee_amount',
        'fee_paid',
        'documents_submitted',
        'admitted_student_id',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'next_follow_up_date' => 'date',
            'preferred_contact_date' => 'date',
            'fee_amount' => 'decimal:2',
            'fee_paid' => 'boolean',
            'whatsapp_optin' => 'boolean',
            'transport_required' => 'boolean',
            'consent_given' => 'boolean',
            'documents_submitted' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function classApplyingFor(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_applying_for_id');
    }

    public function admittedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'admitted_student_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(AdmissionFollowUp::class)->orderByDesc('follow_up_date');
    }

    /** Pipeline badge for all admissions pages: New → Registered → Admitted. */
    public function getStatusAttribute(): string
    {
        return match ($this->stage) {
            'admitted' => 'Admitted',
            'registered' => 'Registered',
            default => 'New',
        };
    }
}
