<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentUdiseDetail extends Model
{
    protected $fillable = [
        'student_id',
        'student_type',
        'minority_group',
        'bpl_beneficiary',
        'ews_disadvantaged',
        'cwsn',
        'type_of_impairments',
        'indian_national',
        'mother_tongue',
        'aay_beneficiary',
        'rte_ews_admission',
        'guardian_name',
        'alternate_mobile',
        'stoppage',
        'route',
        'vehicle',
        'hostel',
        'hostel_room_no',
        'hostel_bed_no',
        'uses_transport',
        'admission_type',
        'clsl',
        'name_as_per_aadhaar',
        'father_name',
        'mother_name',
        'student_state_code',
        'is_repeater',
        'entry_status',
        'student_pen',
        'is_in_udise',
        'out_of_school_child',
        'mainstreamed_when',
        'disability_certificate',
        'disability_percentage',
        'medium_of_instruction',
        'languages_group',
        'academic_stream',
        'subjects_group',
        'rte_amount_claimed',
        'facilities_provided',
        'cwsn_facilities',
        'olympiads',
        'ncc',
        'nss',
        'scouts_guides',
        'distance_to_school',
        'parents_education',
    ];

    protected function casts(): array
    {
        return [
            'bpl_beneficiary' => 'boolean',
            'ews_disadvantaged' => 'boolean',
            'cwsn' => 'boolean',
            'indian_national' => 'boolean',
            'aay_beneficiary' => 'boolean',
            'hostel' => 'boolean',
            'uses_transport' => 'boolean',
            'is_repeater' => 'boolean',
            'is_in_udise' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
