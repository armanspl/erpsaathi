<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EnforcesPortalVisibility;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    use EnforcesPortalVisibility;

    public function show()
    {
        $this->abortIfModuleDisabled('profile');

        /** @var Student $student */
        $student = Auth::guard('student')->user();
        $student->loadMissing([
            'branch:id,name',
            'schoolClass:id,name',
            'section:id,name',
            'father:id,name,phone,email,occupation',
            'mother:id,name,phone,email,occupation',
            'guardian:id,name,phone,email,occupation',
            'additionalDetail',
        ]);

        return response()->json([
            'id' => $student->id,
            'admission_no' => $student->admission_no,
            'roll_no' => $student->roll_no,
            'name' => $student->name,
            'gender' => $student->gender,
            'dob' => $student->dob?->format('Y-m-d'),
            'age' => $student->age,
            'blood_group' => $student->blood_group,
            'category' => $student->category,
            'religion' => $student->religion,
            'nationality' => $student->nationality,
            'mobile' => $student->mobile,
            'email' => $student->email,
            'address' => $student->address,
            'address_line_2' => $student->address_line_2,
            'city' => $student->city,
            'state' => $student->state,
            'pincode' => $student->pincode,
            'admission_date' => $student->admission_date?->format('Y-m-d'),
            'status' => $student->status,
            'branch' => $student->branch?->name,
            'school_class' => $student->schoolClass?->name,
            'section' => $student->section?->name,
            'father' => $student->father,
            'mother' => $student->mother,
            'guardian' => $student->guardian,
        ]);
    }
}
