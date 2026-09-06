<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAdditionalDetail extends Model
{
    protected $fillable = [
        'student_id',
        'house',
        'caste',
        'height',
        'weight',
        'vision_left',
        'vision_right',
        'dental_hygiene',
        'family',
        'medical_conditions',
        'allergies',
        'emergency_contact_name',
        'emergency_contact_phone',
        'discontinue_date',
        'scholarship_no',
        'report_card_received',
        'cc_received',
        'tc_received',
        'dob_certificate_received',
        'form_no',
        'remarks_1',
        'remarks_2',
        'last_school_name',
        'previous_class',
        'class_admitted',
        'tc_number',
        'tc_date',
        'last_class_studied',
        'last_exam',
        'last_exam_year',
        'last_exam_status',
        'last_exam_marks',
        'last_exam_board',
        'parents_anniversary_date',
        'student_ref_id',
        'biometric_card_no',
        'child_uid',
        'gr_no',
        'pen_no',
        'opening_balance',
        'fees_balance',
        'additional_field_1',
        'additional_field_2',
        'additional_field_3',
        'additional_field_4',
        'additional_field_5',
        'additional_field_6',
        'additional_field_7',
        'additional_field_8',
        'additional_field_9',
        'additional_field_10',
    ];

    protected function casts(): array
    {
        return [
            'discontinue_date' => 'date',
            'parents_anniversary_date' => 'date',
            'tc_date' => 'date',
            'report_card_received' => 'boolean',
            'cc_received' => 'boolean',
            'tc_received' => 'boolean',
            'dob_certificate_received' => 'boolean',
            'height' => 'decimal:2',
            'weight' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'fees_balance' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
