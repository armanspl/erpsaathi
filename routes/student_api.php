<?php

use App\Http\Controllers\Student\AttendanceController;
use App\Http\Controllers\Student\CalendarController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\DocumentController;
use App\Http\Controllers\Student\EventController;
use App\Http\Controllers\Student\ExamController;
use App\Http\Controllers\Student\FeeController;
use App\Http\Controllers\Student\HomeworkController;
use App\Http\Controllers\Student\IdCardController;
use App\Http\Controllers\Student\LibraryController;
use App\Http\Controllers\Student\NoticeController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\TransportController;
use Illuminate\Support\Facades\Route;

// All routes here are already wrapped in the 'student.auth' middleware group by routes/web.php.
// Every action scopes to Auth::guard('student')->user() — no student id is ever request-supplied,
// and every action also enforces the admin-configurable Student Portal visibility toggle
// (Settings → Student Portal) via the EnforcesPortalVisibility trait.

Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');

Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');

Route::prefix('exams')->name('exams.')->group(function () {
    Route::get('schedule', [ExamController::class, 'schedule'])->name('schedule');
    Route::get('admit-card/{exam}', [ExamController::class, 'admitCard'])->name('admit-card');
    Route::get('marks', [ExamController::class, 'marks'])->name('marks');
    Route::get('report-card/{exam}', [ExamController::class, 'reportCard'])->name('report-card');
    Route::get('subject-performance', [ExamController::class, 'subjectPerformance'])->name('subject-performance');
    Route::get('previous-results', [ExamController::class, 'previousResults'])->name('previous-results');
});

Route::prefix('fees')->name('fees.')->group(function () {
    Route::get('summary', [FeeController::class, 'summary'])->name('summary');
    Route::get('paid', [FeeController::class, 'paid'])->name('paid');
    Route::get('pending', [FeeController::class, 'pending'])->name('pending');
    Route::get('history', [FeeController::class, 'history'])->name('history');
    Route::get('receipt/{feePayment}', [FeeController::class, 'receipt'])->name('receipt');
});

Route::get('transport', [TransportController::class, 'show'])->name('transport.show');

Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
Route::get('notices', [NoticeController::class, 'index'])->name('notices.index');
Route::get('events', [EventController::class, 'index'])->name('events.index');

Route::get('homework', [HomeworkController::class, 'index'])->name('homework.index');
Route::get('homework/{homeworkItem}/download', [HomeworkController::class, 'download'])->name('homework.download');
Route::get('id-card', [IdCardController::class, 'download'])->name('id-card.download');
Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
Route::get('documents/{type}', [DocumentController::class, 'download'])->name('documents.download');
Route::get('library', [LibraryController::class, 'index'])->name('library.index');
