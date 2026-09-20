<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\StudentPortalCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Student self-service portal auth. Login is admission_no + date of birth — there is no
 * password on Student, so this never uses Auth::attempt()/credentials arrays, only a manual
 * lookup + Auth::guard('student')->login(). See app/Models/Student.php for why Student
 * implements Authenticatable.
 */
class StudentAuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'admission_no' => 'required|string',
            'dob' => 'required|date',
        ]);

        $student = Student::query()
            ->where('admission_no', trim($data['admission_no']))
            ->where('status', 'Active')
            ->first();

        if (! $student || ! $student->dob || $student->dob->format('Y-m-d') !== date('Y-m-d', strtotime($data['dob']))) {
            return response()->json([
                'success' => false,
                'message' => 'The admission number and date of birth do not match our records.',
            ], 422);
        }

        Auth::guard('student')->login($student);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'redirect' => route('student.dashboard'),
        ]);
    }

    public function dashboard()
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();
        $student->loadMissing(['schoolClass:id,name', 'section:id,name', 'branch:id,name']);
        $school = SchoolSetting::current();

        return view('student.dashboard', [
            'studentUser' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'class' => $student->schoolClass?->name,
                'section' => $student->section?->name,
                'gender' => $student->gender,
            ],
            'studentSchool' => [
                'school_name' => $school->school_name,
                'school_code' => $school->school_code,
                'logo_url' => $school->logo_url,
                'favicon_url' => $school->favicon_url,
                'browser_title' => $school->browser_title,
                'compact_sidebar' => (bool) $school->compact_sidebar,
            ],
            'studentPortalVisibility' => StudentPortalCatalog::resolve($school),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('student.login');
    }
}
