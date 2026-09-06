<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = ['certificate_no', 'student_id', 'type', 'certificate_type_id', 'issue_date', 'reason', 'remarks', 'issued_by_id'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(ErpUser::class, 'issued_by_id');
    }
}
