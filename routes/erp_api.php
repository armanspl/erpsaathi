<?php

use App\Http\Controllers\Erp\AcademicSessionController;
use App\Http\Controllers\Erp\AiAssistantController;
use App\Http\Controllers\Erp\Academics\AcademicsLookupController;
use App\Http\Controllers\Erp\Academics\BranchController;
use App\Http\Controllers\Erp\Academics\ClassSubjectController;
use App\Http\Controllers\Erp\Academics\HomeworkController;
use App\Http\Controllers\Erp\Academics\SchoolClassController;
use App\Http\Controllers\Erp\Academics\SectionController;
use App\Http\Controllers\Erp\Academics\SubjectController;
use App\Http\Controllers\Erp\Account\ApiTokenController;
use App\Http\Controllers\Erp\Account\PasswordController;
use App\Http\Controllers\Erp\Account\ProfileController;
use App\Http\Controllers\Erp\Account\SessionController;
use App\Http\Controllers\Erp\Account\SupportTicketController;
use App\Http\Controllers\Erp\Account\ThemeController;
use App\Http\Controllers\Erp\Admissions\AdmissionCustomFieldController;
use App\Http\Controllers\Erp\Admissions\AdmissionEnquiryController;
use App\Http\Controllers\Erp\Admissions\AdmissionFollowUpController;
use App\Http\Controllers\Erp\Admissions\AdmissionsLookupController;
use App\Http\Controllers\Erp\Attendance\AttendanceController;
use App\Http\Controllers\Erp\Attendance\AttendanceLookupController;
use App\Http\Controllers\Erp\DashboardController;
use App\Http\Controllers\Erp\DatabaseBackupController;
use App\Http\Controllers\Erp\Attendance\AttendanceReportController;
use App\Http\Controllers\Erp\Attendance\HolidayController;
use App\Http\Controllers\Erp\Attendance\LeaveRequestController;
use App\Http\Controllers\Erp\Attendance\WorkingDayConfigController;
use App\Http\Controllers\Erp\Communication\ErpNoticeController;
use App\Http\Controllers\Erp\Communication\EventController;
use App\Http\Controllers\Erp\Communication\MessageController;
use App\Http\Controllers\Erp\Documents\CertificateController;
use App\Http\Controllers\Erp\Documents\CertificateTypeController;
use App\Http\Controllers\Erp\Documents\IdCardController;
use App\Http\Controllers\Erp\Documents\TemplateController;
use App\Http\Controllers\Erp\Documents\TransportCardController;
use App\Http\Controllers\Erp\ErpDepartmentController;
use App\Http\Controllers\Erp\ErpRoleController;
use App\Http\Controllers\Erp\Exams\AcademicTermController;
use App\Http\Controllers\Erp\Exams\AdmitCardController;
use App\Http\Controllers\Erp\Exams\AnnualReportController;
use App\Http\Controllers\Erp\Exams\ExamController;
use App\Http\Controllers\Erp\Exams\ExamReportController;
use App\Http\Controllers\Erp\Exams\ExamResultController;
use App\Http\Controllers\Erp\Exams\ExamScheduleController;
use App\Http\Controllers\Erp\Exams\ExamScheduleSheetController;
use App\Http\Controllers\Erp\Exams\ExamTypeController;
use App\Http\Controllers\Erp\Exams\GradeSystemController;
use App\Http\Controllers\Erp\Exams\MarkController;
use App\Http\Controllers\Erp\Exams\QuestionController;
use App\Http\Controllers\Erp\Exams\SeatPlanController;
use App\Http\Controllers\Erp\FeeManagement\ErpFeeStructureController;
use App\Http\Controllers\Erp\FeeManagement\FeeDiscountController;
use App\Http\Controllers\Erp\FeeManagement\FeeDueController;
use App\Http\Controllers\Erp\FeeManagement\FeeHistoryController;
use App\Http\Controllers\Erp\FeeManagement\FeePaidController;
use App\Http\Controllers\Erp\FeeManagement\FeeHeadController;
use App\Http\Controllers\Erp\FeeManagement\FeeLookupController;
use App\Http\Controllers\Erp\FeeManagement\FeePaymentController;
use App\Http\Controllers\Erp\FeeManagement\FeeSettingController;
use App\Http\Controllers\Erp\FeeManagement\FineRuleController;
use App\Http\Controllers\Erp\FinancePayroll\BankAccountController;
use App\Http\Controllers\Erp\FinancePayroll\BankTransactionController;
use App\Http\Controllers\Erp\FinancePayroll\BookExpenseController;
use App\Http\Controllers\Erp\FinancePayroll\BookStoreController;
use App\Http\Controllers\Erp\FinancePayroll\CashBookController;
use App\Http\Controllers\Erp\FinancePayroll\ExpenseCategoryController;
use App\Http\Controllers\Erp\FinancePayroll\ExpenseController;
use App\Http\Controllers\Erp\FinancePayroll\IncomeController;
use App\Http\Controllers\Erp\FinancePayroll\SalaryMonthlySheetController;
use App\Http\Controllers\Erp\FinancePayroll\SalaryReportController;
use App\Http\Controllers\Erp\FinancePayroll\SalarySlipController;
use App\Http\Controllers\Erp\FinancePayroll\SalaryStructureController;
use App\Http\Controllers\Erp\Hostel\BedController;
use App\Http\Controllers\Erp\Hostel\HostelAllocationController;
use App\Http\Controllers\Erp\Hostel\HostelFeeController;
use App\Http\Controllers\Erp\Hostel\HostelReportController;
use App\Http\Controllers\Erp\Hostel\HostelVisitorController;
use App\Http\Controllers\Erp\Hostel\RoomController;
use App\Http\Controllers\Erp\ImportExport\ExportController;
use App\Http\Controllers\Erp\ImportExport\FailedRecordController;
use App\Http\Controllers\Erp\ImportExport\ImportExportLogController;
use App\Http\Controllers\Erp\ImportExport\StudentMasterImportController;
use App\Http\Controllers\Erp\ImportExport\StudentPenImportController;
use App\Http\Controllers\Erp\ImportExport\AttendanceImportController;
use App\Http\Controllers\Erp\ImportExport\ClassTermMarksImportController;
use App\Http\Controllers\Erp\ImportExport\GlobalWorkbookImportController;
use App\Http\Controllers\Erp\ImportExport\SalaryMonthlyImportController;
use App\Http\Controllers\Erp\ImportExport\EmployeeMasterImportController;
use App\Http\Controllers\Erp\Inventory\InventoryReportController;
use App\Http\Controllers\Erp\Inventory\LowStockAlertController;
use App\Http\Controllers\Erp\Inventory\ProductController;
use App\Http\Controllers\Erp\Inventory\PurchaseController;
use App\Http\Controllers\Erp\Inventory\StockController;
use App\Http\Controllers\Erp\Inventory\SupplierController;
use App\Http\Controllers\Erp\Library\AuthorController;
use App\Http\Controllers\Erp\Library\BookCategoryController;
use App\Http\Controllers\Erp\Library\BookController;
use App\Http\Controllers\Erp\Library\BookIssueController;
use App\Http\Controllers\Erp\Library\FineCollectionController;
use App\Http\Controllers\Erp\Library\LibraryMemberController;
use App\Http\Controllers\Erp\Library\LibraryReportController;
use App\Http\Controllers\Erp\Library\PublisherController;
use App\Http\Controllers\Erp\Meetings\MeetingController;
use App\Http\Controllers\Erp\Meetings\MeetingReportController;
use App\Http\Controllers\Erp\People\DriverController;
use App\Http\Controllers\Erp\People\ParentController;
use App\Http\Controllers\Erp\People\PeopleLookupController;
use App\Http\Controllers\Erp\People\StaffController;
use App\Http\Controllers\Erp\People\StudentController;
use App\Http\Controllers\Erp\People\UdisePlusController;
use App\Http\Controllers\Erp\People\UdisePlusS02Controller;
use App\Http\Controllers\Erp\People\TeacherController;
use App\Http\Controllers\Erp\People\UserController;
use App\Http\Controllers\Erp\People\VisitorController;
use App\Http\Controllers\Erp\Reports\AdmissionReportController;
use App\Http\Controllers\Erp\Reports\FeeReportController;
use App\Http\Controllers\Erp\Reports\FinanceReportController;
use App\Http\Controllers\Erp\Reports\RegisterReportController;
use App\Http\Controllers\Erp\Reports\UdiseReportController;
use App\Http\Controllers\Erp\Reports\StudentReportController;
use App\Http\Controllers\Erp\SchoolSettingController;
use App\Http\Controllers\Erp\System\AuditLogController;
use App\Http\Controllers\Erp\System\CacheManagerController;
use App\Http\Controllers\Erp\System\LoginHistoryController;
use App\Http\Controllers\Erp\System\QueueMonitorController;
use App\Http\Controllers\Erp\System\ScheduledJobController;
use App\Http\Controllers\Erp\System\SystemHealthController;
use App\Http\Controllers\Erp\Transport\FuelLogController;
use App\Http\Controllers\Erp\Transport\RouteController;
use App\Http\Controllers\Erp\Transport\RouteStopController;
use App\Http\Controllers\Erp\Transport\StudentTransportController;
use App\Http\Controllers\Erp\Transport\TransportReportController;
use App\Http\Controllers\Erp\Transport\VehicleController;
use App\Http\Controllers\Erp\Transport\VehicleDocumentController;
use App\Http\Controllers\Erp\Transport\VehicleMaintenanceController;
use Illuminate\Support\Facades\Route;

// All routes here are already wrapped in the 'erp.auth' middleware group by routes/web.php.

// The landing dashboard summary — open to every authenticated ERP user regardless of role.
Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('notifications', [DashboardController::class, 'notifications'])->name('notifications.index');

Route::post('help/assistant', [AiAssistantController::class, 'chat'])
    ->middleware('throttle:20,1')
    ->name('help.assistant');

Route::prefix('settings')->name('settings.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('/school', [SchoolSettingController::class, 'show'])->name('school.show');
    Route::get('/school/assets/{filename}', [SchoolSettingController::class, 'asset'])->name('school.asset');
    Route::get('academic-sessions', [AcademicSessionController::class, 'index'])->name('academic-sessions.index');
    Route::get('roles', [ErpRoleController::class, 'index'])->name('roles.index');
    Route::get('permissions/catalog', [ErpDepartmentController::class, 'catalog'])->name('permissions.catalog');
    Route::get('permissions/me', [ErpDepartmentController::class, 'mine'])->name('permissions.me');
    Route::get('departments', [ErpDepartmentController::class, 'index'])->name('departments.index');
    Route::get('departments/suggestions', [ErpDepartmentController::class, 'suggestions'])->name('departments.suggestions');
    Route::get('database-backups', [DatabaseBackupController::class, 'index'])->name('database-backups.index');

    // Writes — page-level keys (legacy settings.manage still grants via PermissionResolver).
    Route::middleware('erp.permission:settings.school-settings.edit')->put('/school', [SchoolSettingController::class, 'update'])->name('school.update');
    Route::middleware('erp.permission:settings.school-settings.upload')->post('/school/upload', [SchoolSettingController::class, 'upload'])->name('school.upload');

    Route::middleware('erp.permission:settings.academic-sessions.create')->post('academic-sessions', [AcademicSessionController::class, 'store'])->name('academic-sessions.store');
    Route::middleware('erp.permission:settings.academic-sessions.edit')->put('academic-sessions/{academicSession}', [AcademicSessionController::class, 'update'])->name('academic-sessions.update');
    Route::middleware('erp.permission:settings.academic-sessions.delete')->delete('academic-sessions/{academicSession}', [AcademicSessionController::class, 'destroy'])->name('academic-sessions.destroy');

    Route::middleware('erp.permission:settings.roles-and-permissions.create')->post('roles', [ErpRoleController::class, 'store'])->name('roles.store');
    Route::middleware('erp.permission:settings.roles-and-permissions.edit')->put('roles/{erpRole}', [ErpRoleController::class, 'update'])->name('roles.update');
    Route::middleware('erp.permission:settings.roles-and-permissions.delete')->delete('roles/{erpRole}', [ErpRoleController::class, 'destroy'])->name('roles.destroy');

    Route::middleware('erp.permission:settings.roles-and-permissions.manage')->group(function () {
        Route::post('departments', [ErpDepartmentController::class, 'store'])->name('departments.store');
        Route::put('departments/{erpDepartment}', [ErpDepartmentController::class, 'update'])->name('departments.update');
        Route::delete('departments/{erpDepartment}', [ErpDepartmentController::class, 'destroy'])->name('departments.destroy');
    });

    Route::middleware('erp.permission:settings.database-backup.create')->post('database-backups', [DatabaseBackupController::class, 'store'])->name('database-backups.store');
    Route::middleware('erp.permission:settings.database-backup.download')->get('database-backups/{filename}/download', [DatabaseBackupController::class, 'download'])
        ->where('filename', '[A-Za-z0-9._-]+\.sql')
        ->name('database-backups.download');
    Route::middleware('erp.permission:settings.database-backup.delete')->delete('database-backups/{filename}', [DatabaseBackupController::class, 'destroy'])
        ->where('filename', '[A-Za-z0-9._-]+\.sql')
        ->name('database-backups.destroy');
});

Route::prefix('academics')->name('academics.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('lookups', AcademicsLookupController::class)->name('lookups');
    Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('classes', [SchoolClassController::class, 'index'])->name('classes.index');
    Route::get('sections', [SectionController::class, 'index'])->name('sections.index');
    Route::get('subjects', [SubjectController::class, 'index'])->name('subjects.index');
    Route::get('homeworks', [HomeworkController::class, 'index'])->name('homeworks.index');
    Route::get('homework-items/{homeworkItem}/attachment', [HomeworkController::class, 'downloadAttachment'])->name('homework-items.attachment');

    // Writes — page-level (legacy academics.manage still grants via PermissionResolver).
    Route::middleware('erp.permission:academics.branches.create')->post('branches', [BranchController::class, 'store'])->name('branches.store');
    Route::middleware('erp.permission:academics.branches.edit')->put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::middleware('erp.permission:academics.branches.delete')->delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

    Route::middleware('erp.permission:academics.classes-and-sections.create')->group(function () {
        Route::post('classes', [SchoolClassController::class, 'store'])->name('classes.store');
        Route::post('sections', [SectionController::class, 'store'])->name('sections.store');
    });
    Route::middleware('erp.permission:academics.classes-and-sections.edit')->group(function () {
        Route::put('classes/{schoolClass}', [SchoolClassController::class, 'update'])->name('classes.update');
        Route::put('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
    });
    Route::middleware('erp.permission:academics.classes-and-sections.assign')->put('classes/{schoolClass}/subjects', [ClassSubjectController::class, 'sync'])->name('classes.subjects.sync');
    Route::middleware('erp.permission:academics.classes-and-sections.delete')->group(function () {
        Route::delete('classes/{schoolClass}', [SchoolClassController::class, 'destroy'])->name('classes.destroy');
        Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
    });

    Route::middleware('erp.permission:academics.subjects.create')->post('subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::middleware('erp.permission:academics.subjects.edit')->put('subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::middleware('erp.permission:academics.subjects.delete')->delete('subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

    Route::middleware('erp.permission:academics.homework.create')->post('homeworks', [HomeworkController::class, 'store'])->name('homeworks.store');
    Route::middleware('erp.permission:academics.homework.create')->post('homeworks/{homework}', [HomeworkController::class, 'update'])->name('homeworks.update');
    Route::middleware('erp.permission:academics.homework.delete')->delete('homeworks/{homework}', [HomeworkController::class, 'destroy'])->name('homeworks.destroy');
});

Route::prefix('people')->name('people.')->group(function () {
    // Lookups stay open for authenticated users (shared by many screens).
    Route::get('lookups', PeopleLookupController::class)->name('lookups');

    Route::middleware('erp.permission:people.users.view')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
    });
    Route::middleware('erp.permission:people.students.view')->group(function () {
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
    });
    Route::middleware('erp.permission:people.students.download')->get('students/{student}/documents/{type}', [StudentController::class, 'downloadDocument'])->name('students.documents.download');
    Route::middleware('erp.permission:people.udiseplus-s03.view|people.udise-plus.view')->group(function () {
        Route::get('udise-plus', [UdisePlusController::class, 'index'])->name('udise-plus.index');
        Route::get('udise-plus/{student}', [UdisePlusController::class, 'show'])->name('udise-plus.show');
    });
    Route::middleware('erp.permission:people.udiseplus-s03.export|people.udise-plus.export')->get('udise-plus/zip', [UdisePlusController::class, 'downloadZip'])->name('udise-plus.zip');
    Route::middleware('erp.permission:people.udiseplus-s03.download|people.udise-plus.download')->get('udise-plus/{student}/s03-pdf', [UdisePlusController::class, 'downloadS03'])->name('udise-plus.s03-pdf');

    Route::middleware('erp.permission:people.udiseplus-s02.view')->get('udise-plus-s02', [UdisePlusS02Controller::class, 'index'])->name('udise-plus-s02.index');
    Route::middleware('erp.permission:people.udiseplus-s02.download')->post('udise-plus-s02/pdf', [UdisePlusS02Controller::class, 'downloadPdf'])->name('udise-plus-s02.pdf');

    Route::middleware('erp.permission:people.parents.view')->get('parents', [ParentController::class, 'index'])->name('parents.index');
    Route::middleware('erp.permission:people.teachers.view')->group(function () {
        Route::get('teachers', [TeacherController::class, 'index'])->name('teachers.index');
        Route::get('teachers/{teacher}/signature', [TeacherController::class, 'signature'])->name('teachers.signature');
    });
    Route::middleware('erp.permission:people.staff.view')->get('staff', [StaffController::class, 'index'])->name('staff.index');
    Route::middleware('erp.permission:people.drivers.view')->get('drivers', [DriverController::class, 'index'])->name('drivers.index');
    Route::middleware('erp.permission:people.visitor-records.view')->get('visitors', [VisitorController::class, 'index'])->name('visitors.index');
    Route::middleware('erp.permission:people.employee-master-import.view')->post('employee-master/preview', [EmployeeMasterImportController::class, 'preview'])->name('employee-master.preview');

    Route::middleware('erp.permission:people.visitor-records.create')->post('visitors', [VisitorController::class, 'store'])->name('visitors.store');
    Route::middleware('erp.permission:people.visitor-records.edit')->post('visitors/{visitor}/check-out', [VisitorController::class, 'checkOut'])->name('visitors.check-out');

    Route::middleware('erp.permission:people.users.create')->post('users', [UserController::class, 'store'])->name('users.store');
    Route::middleware('erp.permission:people.users.edit')->put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::middleware('erp.permission:people.users.delete')->delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::middleware('erp.permission:people.students.create')->post('students', [StudentController::class, 'store'])->name('students.store');
    Route::middleware('erp.permission:people.students.edit')->group(function () {
        Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::patch('students/{student}/admission-status', [StudentController::class, 'updateAdmissionStatus'])->name('students.admission-status');
    });
    Route::middleware('erp.permission:people.students.delete')->group(function () {
        Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
        Route::delete('students/{student}/documents/{type}', [StudentController::class, 'deleteDocument'])->name('students.documents.destroy');
    });

    Route::middleware('erp.permission:people.parents.create')->post('parents', [ParentController::class, 'store'])->name('parents.store');
    Route::middleware('erp.permission:people.parents.edit')->put('parents/{parent}', [ParentController::class, 'update'])->name('parents.update');
    Route::middleware('erp.permission:people.parents.delete')->delete('parents/{parent}', [ParentController::class, 'destroy'])->name('parents.destroy');

    Route::middleware('erp.permission:people.teachers.create')->post('teachers', [TeacherController::class, 'store'])->name('teachers.store');
    Route::middleware('erp.permission:people.teachers.edit')->group(function () {
        Route::put('teachers/{teacher}', [TeacherController::class, 'update'])->name('teachers.update');
        Route::put('teachers/{teacher}/assignments', [TeacherController::class, 'syncAssignments'])->name('teachers.assignments.sync');
        Route::put('teachers/{teacher}/subjects', [TeacherController::class, 'syncSubjects'])->name('teachers.subjects.sync');
        Route::put('teachers/{teacher}/custom-fields', [TeacherController::class, 'updateCustomFields'])->name('teachers.custom-fields.update');
    });
    Route::middleware('erp.permission:people.teachers.delete')->delete('teachers/{teacher}', [TeacherController::class, 'destroy'])->name('teachers.destroy');
    Route::middleware('erp.permission:people.teachers.upload')->post('teachers/{teacher}/signature', [TeacherController::class, 'uploadSignature'])->name('teachers.signature.upload');
    Route::middleware('erp.permission:people.teachers.upload')->delete('teachers/{teacher}/signature', [TeacherController::class, 'deleteSignature'])->name('teachers.signature.destroy');

    Route::middleware('erp.permission:people.drivers.create')->post('drivers', [DriverController::class, 'store'])->name('drivers.store');
    Route::middleware('erp.permission:people.drivers.edit')->put('drivers/{driver}', [DriverController::class, 'update'])->name('drivers.update');
    Route::middleware('erp.permission:people.drivers.delete')->delete('drivers/{driver}', [DriverController::class, 'destroy'])->name('drivers.destroy');

    Route::middleware('erp.permission:people.visitor-records.delete')->delete('visitors/{visitor}', [VisitorController::class, 'destroy'])->name('visitors.destroy');
    Route::middleware('erp.permission:people.employee-master-import.import')->post('employee-master/import', [EmployeeMasterImportController::class, 'import'])->name('employee-master.import');

    Route::middleware('erp.permission:people.staff.create')->post('staff', [StaffController::class, 'store'])->name('staff.store');
    Route::middleware('erp.permission:people.staff.edit')->put('staff/{staffMember}', [StaffController::class, 'update'])->name('staff.update');
    Route::middleware('erp.permission:people.staff.delete')->delete('staff/{staffMember}', [StaffController::class, 'destroy'])->name('staff.destroy');
});

Route::prefix('admissions')->name('admissions.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('lookups', AdmissionsLookupController::class)->name('lookups');
    Route::get('enquiries', [AdmissionEnquiryController::class, 'index'])->name('enquiries.index');
    Route::get('enquiries/{enquiry}/follow-ups', [AdmissionFollowUpController::class, 'index'])->name('enquiries.follow-ups.index');
    Route::get('custom-fields', [AdmissionCustomFieldController::class, 'index'])->name('custom-fields.index');
    Route::get('custom-fields/active', [AdmissionCustomFieldController::class, 'active'])->name('custom-fields.active');

    // Writes require the admissions.manage permission (see Settings > Roles & Permissions).
    Route::middleware('erp.permission:admissions.manage')->group(function () {
        Route::post('enquiries', [AdmissionEnquiryController::class, 'store'])->name('enquiries.store');
        Route::put('enquiries/{enquiry}', [AdmissionEnquiryController::class, 'update'])->name('enquiries.update');
        Route::delete('enquiries/{enquiry}', [AdmissionEnquiryController::class, 'destroy'])->name('enquiries.destroy');
        Route::patch('enquiries/{enquiry}/stage', [AdmissionEnquiryController::class, 'updateStage'])->name('enquiries.stage');

        Route::post('enquiries/{enquiry}/follow-ups', [AdmissionFollowUpController::class, 'store'])->name('enquiries.follow-ups.store');

        Route::post('custom-fields', [AdmissionCustomFieldController::class, 'store'])->name('custom-fields.store');
        Route::put('custom-fields/{customField}', [AdmissionCustomFieldController::class, 'update'])->name('custom-fields.update');
        Route::delete('custom-fields/{customField}', [AdmissionCustomFieldController::class, 'destroy'])->name('custom-fields.destroy');
    });
});

Route::prefix('fee-management')->name('fee-management.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('lookups', FeeLookupController::class)->name('lookups');
    Route::get('settings', [FeeSettingController::class, 'show'])->name('settings.show');
    Route::get('tally/preview', [FeeSettingController::class, 'tallyPreview'])->name('tally.preview');
    Route::get('tally/export', [FeeSettingController::class, 'tallyExport'])->name('tally.export');
    Route::get('heads', [FeeHeadController::class, 'index'])->name('heads.index');
    Route::get('structures', [ErpFeeStructureController::class, 'index'])->name('structures.index');
    Route::get('transport-routes', [RouteController::class, 'index'])->name('transport-routes.index');
    Route::get('discounts', [FeeDiscountController::class, 'index'])->name('discounts.index');
    Route::get('fine-rules', [FineRuleController::class, 'index'])->name('fine-rules.index');
    Route::get('due', [FeeDueController::class, 'index'])->name('due.index');
    Route::get('due/meta', [FeeDueController::class, 'meta'])->name('due.meta');
    Route::get('fee-paid', [FeePaidController::class, 'index'])->name('fee-paid.index');
    Route::middleware('erp.permission:fee-management.fee-paid.export')->get('fee-paid/export', [FeePaidController::class, 'export'])->name('fee-paid.export');
    Route::get('fee-history/meta', [FeeHistoryController::class, 'meta'])->name('fee-history.meta');
    Route::get('fee-history', [FeeHistoryController::class, 'index'])->name('fee-history.index');
    Route::get('fee-history/export', [FeeHistoryController::class, 'export'])->name('fee-history.export');
    Route::get('due/automatic/export', [FeeDueController::class, 'exportAutomatic'])->name('due.automatic.export');
    Route::get('due/manual/export', [FeeDueController::class, 'exportManual'])->name('due.manual.export');
    Route::get('due/manual', [FeeDueController::class, 'manualIndex'])->name('due.manual.index');
    Route::get('due/manual/{student}/receipt', [FeeDueController::class, 'manualReceipt'])->name('due.manual.receipt');
    Route::get('due/manual/{student}/pdf', [FeeDueController::class, 'downloadManualPdf'])->name('due.manual.pdf');
    Route::get('due/automatic/{student}/receipt', [FeeDueController::class, 'automaticReceipt'])->name('due.automatic.receipt');
    Route::get('due/automatic/{student}/pdf', [FeeDueController::class, 'downloadAutomaticPdf'])->name('due.automatic.pdf');
    Route::get('due/automatic/{student}/detail', [FeeDueController::class, 'automaticDetail'])->name('due.automatic.detail');
    Route::get('payments', [FeePaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/report', [FeePaymentController::class, 'report'])->name('payments.report');
    Route::get('payments/export', [FeePaymentController::class, 'export'])->name('payments.export');
    Route::get('payments/{payment}/receipt', [FeePaymentController::class, 'receipt'])->name('payments.receipt');
    Route::get('payments/{payment}/pdf', [FeePaymentController::class, 'downloadPdf'])->name('payments.pdf');
    Route::get('students/{student}/due', [FeePaymentController::class, 'due'])->name('students.due');

    // Writes — page-level (legacy fee.manage still grants via PermissionResolver).
    Route::middleware('erp.permission:fee-management.fee-settings.edit')->group(function () {
        Route::put('settings/preferences', [FeeSettingController::class, 'updatePreferences'])->name('settings.preferences');
        Route::post('heads', [FeeHeadController::class, 'store'])->name('heads.store');
        Route::put('heads/{feeHead}', [FeeHeadController::class, 'update'])->name('heads.update');
        Route::delete('heads/{feeHead}', [FeeHeadController::class, 'destroy'])->name('heads.destroy');
        Route::post('discounts', [FeeDiscountController::class, 'store'])->name('discounts.store');
        Route::put('discounts/{feeDiscount}', [FeeDiscountController::class, 'update'])->name('discounts.update');
        Route::delete('discounts/{feeDiscount}', [FeeDiscountController::class, 'destroy'])->name('discounts.destroy');
        Route::post('fine-rules', [FineRuleController::class, 'store'])->name('fine-rules.store');
        Route::put('fine-rules/{fineRule}', [FineRuleController::class, 'update'])->name('fine-rules.update');
        Route::delete('fine-rules/{fineRule}', [FineRuleController::class, 'destroy'])->name('fine-rules.destroy');
    });
    Route::middleware('erp.permission:fee-management.tally-accounting.edit')->put('settings/tally', [FeeSettingController::class, 'updateTally'])->name('settings.tally');

    Route::middleware('erp.permission:fee-management.fee-structure.create')->post('structures', [ErpFeeStructureController::class, 'store'])->name('structures.store');
    Route::middleware('erp.permission:fee-management.fee-structure.edit')->group(function () {
        Route::post('transport-routes/fees', [RouteController::class, 'updateFees'])->name('transport-routes.fees');
        Route::delete('transport-routes/{route}/fees', [RouteController::class, 'clearFees'])->name('transport-routes.fees.clear');
        Route::post('transport-routes/sync', [RouteController::class, 'sync'])->name('transport-routes.sync');
        Route::put('structures/{plan}', [ErpFeeStructureController::class, 'update'])->name('structures.update');
    });
    Route::middleware('erp.permission:fee-management.fee-structure.duplicate')->post('structures/{plan}/duplicate', [ErpFeeStructureController::class, 'duplicate'])->name('structures.duplicate');
    Route::middleware('erp.permission:fee-management.fee-structure.delete')->delete('structures/{plan}', [ErpFeeStructureController::class, 'destroy'])->name('structures.destroy');

    Route::middleware('erp.permission:fee-management.pay-fee.create')->post('payments', [FeePaymentController::class, 'store'])->name('payments.store');
    Route::middleware('erp.permission:fee-management.fee-receipt.edit')->patch('payments/{payment}', [FeePaymentController::class, 'update'])->name('payments.update');
    Route::middleware('erp.permission:fee-management.fee-receipt.rollback')->group(function () {
        Route::post('payments/{payment}/refund', [FeePaymentController::class, 'refund'])->name('payments.refund');
        Route::post('payments/{payment}/rollback', [FeePaymentController::class, 'rollback'])->name('payments.rollback');
    });
    Route::middleware('erp.permission:fee-management.fee-receipt.view')->get('payments/{payment}/audits', [FeePaymentController::class, 'audits'])->name('payments.audits');

    Route::middleware('erp.permission:fee-management.fee-due.create')->post('due/manual', [FeeDueController::class, 'storeManual'])->name('due.manual.store');
    Route::middleware('erp.permission:fee-management.fee-due.edit')->put('due/manual/{manualFeeDue}', [FeeDueController::class, 'updateManual'])->name('due.manual.update');
    Route::middleware('erp.permission:fee-management.fee-due.delete')->delete('due/manual/{manualFeeDue}', [FeeDueController::class, 'destroyManual'])->name('due.manual.destroy');
    Route::middleware('erp.permission:fee-management.pay-fee.create')->post('due/manual/collect', [FeeDueController::class, 'collectManual'])->name('due.manual.collect');
});

Route::prefix('attendance')->name('attendance.')->group(function () {
    // Literal segments are registered before the {type} wildcard below so
    // "leave-requests"/"holidays"/etc. can never be mistaken for a person type.
    Route::get('lookups', AttendanceLookupController::class)->name('lookups');
    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::get('leave-requests/my-profile', [LeaveRequestController::class, 'myProfile'])->name('leave-requests.my-profile');
    Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::get('working-days', [WorkingDayConfigController::class, 'show'])->name('working-days.show');
    Route::get('reports', [AttendanceReportController::class, 'index'])->name('reports.index');
    Route::get('mine', [AttendanceController::class, 'mine'])->name('mine');
    Route::get('student/sessions', [AttendanceController::class, 'studentSessions'])->name('student.sessions');
    Route::get('student/monthly-summaries', [AttendanceController::class, 'studentMonthlySummaries'])->name('student.monthly-summaries');
    Route::get('student/sheet', [AttendanceController::class, 'studentSheet'])->name('student.sheet');
    Route::get('student/sheet/export', [AttendanceController::class, 'exportStudentSheet'])->name('student.sheet.export');
    Route::get('student/month', [AttendanceController::class, 'studentMonthGrid'])->name('student.month.grid');
    Route::get('student/{student}/history', [AttendanceController::class, 'studentHistory'])->name('student.history');
    Route::get('driver/history', [AttendanceController::class, 'driverHistory'])->name('driver.history');
    Route::get('driver/route-students', [AttendanceController::class, 'driverRouteStudents'])->name('driver.route-students');
    Route::get('staff/mine', [AttendanceController::class, 'staffMine'])->name('staff.mine');
    Route::get('staff/history', [AttendanceController::class, 'staffHistory'])->name('staff.history');
    // Self check-in: writes only the caller's own linked Staff record (resolved server-side), so it
    // is intentionally NOT behind attendance.manage — any logged-in staff member can check themselves in.
    Route::post('staff/check-in', [AttendanceController::class, 'staffCheckIn'])->name('staff.check-in');

    // Reads are available to any authenticated ERP user.
    Route::get('{type}', [AttendanceController::class, 'index'])->whereIn('type', ['student', 'teacher', 'staff', 'driver'])->name('marking.index');

    // Writes require the attendance.manage permission (see Settings > Roles & Permissions).
    Route::middleware('erp.permission:attendance.manage')->group(function () {
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
        Route::patch('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::patch('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
        Route::patch('leave-requests/{leaveRequest}/rejoin', [LeaveRequestController::class, 'rejoin'])->name('leave-requests.rejoin');
        Route::delete('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->name('leave-requests.destroy');

        Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::put('working-days', [WorkingDayConfigController::class, 'update'])->name('working-days.update');

        Route::post('driver/check-in', [AttendanceController::class, 'driverCheckIn'])->name('driver.check-in');
        Route::post('driver/route-students', [AttendanceController::class, 'storeDriverRouteStudents'])->name('driver.route-students.store');
        Route::post('student/month', [AttendanceController::class, 'storeStudentMonth'])->name('student.month.store');
        Route::post('student/monthly-summaries', [AttendanceController::class, 'storeStudentMonthlySummaries'])->name('student.monthly-summaries.store');
        Route::post('{type}', [AttendanceController::class, 'store'])->whereIn('type', ['student', 'teacher', 'staff', 'driver'])->name('marking.store');
    });
});

Route::prefix('exams')->name('exams.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('types', [ExamTypeController::class, 'index'])->name('types.index');
    Route::get('schedules', [ExamScheduleController::class, 'index'])->name('schedules.index');
    Route::get('grade-system', [GradeSystemController::class, 'index'])->name('grade-system.index');
    Route::get('marks/sheet', [MarkController::class, 'sheet'])->name('marks.sheet');
    Route::get('marks/template', [MarkController::class, 'downloadTemplate'])->name('marks.template');
    Route::get('marks/export', [MarkController::class, 'export'])->name('marks.export');
    Route::get('seat-plans', [SeatPlanController::class, 'index'])->name('seat-plans.index');
    Route::get('seat-plans/{seatPlanSheet}', [SeatPlanController::class, 'show'])->name('seat-plans.show');
    Route::get('seat-plans/rooms/{seatPlanRoom}/export', [SeatPlanController::class, 'exportRoomCsv'])->name('seat-plans.rooms.export');
    Route::get('questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::get('/', [ExamController::class, 'index'])->name('index');
    Route::get('schedule-sheets', [ExamScheduleSheetController::class, 'index'])->name('schedule-sheets.index');
    Route::get('schedule-sheets/{examScheduleSheet}', [ExamScheduleSheetController::class, 'show'])->name('schedule-sheets.show');
    Route::get('schedule-sheets/{examScheduleSheet}/pdf', [ExamScheduleSheetController::class, 'downloadPdf'])->name('schedule-sheets.pdf');
    Route::get('students/{student}/results', [ExamResultController::class, 'forStudent'])->name('students.results');

    Route::get('terms', [AcademicTermController::class, 'index'])->name('terms.index');
    Route::get('terms/{term}/results', [AcademicTermController::class, 'results'])->name('terms.results');
    Route::get('terms/{term}/results/pdf', [AcademicTermController::class, 'downloadSheetPdf'])->name('terms.results.pdf');
    Route::get('terms/{term}/results/zip', [AcademicTermController::class, 'downloadZip'])->name('terms.results.zip');
    Route::get('terms/{term}/results/{student}/pdf', [AcademicTermController::class, 'downloadStudentPdf'])->name('terms.results.student-pdf');
    Route::get('terms/{term}/anchor-exam', [AcademicTermController::class, 'anchorExamInfo'])->name('terms.anchor-exam');
    Route::get('annual-report', [AnnualReportController::class, 'results'])->name('annual-report.results');
    Route::get('annual-report/zip', [AnnualReportController::class, 'downloadZip'])->name('annual-report.zip');
    Route::get('annual-report/anchor-exam', [AnnualReportController::class, 'anchorExamInfo'])->name('annual-report.anchor-exam');
    Route::get('annual-report/{student}/pdf', [AnnualReportController::class, 'downloadStudentPdf'])->name('annual-report.student-pdf');

    Route::get('{exam}/results', [ExamResultController::class, 'index'])->name('results.index');
    Route::get('{exam}/results/pdf', [ExamResultController::class, 'downloadSheetPdf'])->name('results.pdf');
    Route::get('{exam}/results/zip', [ExamResultController::class, 'downloadZip'])->name('results.zip');
    Route::get('{exam}/results/{student}/pdf', [ExamResultController::class, 'downloadStudentPdf'])->name('results.student-pdf');
    Route::get('{exam}/results/{student}', [ExamResultController::class, 'show'])->name('results.show');
    Route::get('{exam}/report', [ExamReportController::class, 'index'])->name('report.index');

    Route::get('{exam}/admit-cards', [AdmitCardController::class, 'index'])->name('admit-cards.index');
    Route::get('{exam}/admit-cards/instructions', [AdmitCardController::class, 'instructions'])->name('admit-cards.instructions.show');
    Route::get('{exam}/admit-cards/zip', [AdmitCardController::class, 'downloadZip'])->name('admit-cards.zip');
    Route::post('{exam}/admit-cards/print', [AdmitCardController::class, 'downloadPrintPdf'])->name('admit-cards.print');
    Route::get('{exam}/admit-cards/{student}/pdf', [AdmitCardController::class, 'downloadStudentPdf'])->name('admit-cards.student-pdf');

    // Writes require the exam.manage permission (see Settings > Roles & Permissions).
    Route::middleware('erp.permission:exam.manage')->group(function () {
        Route::post('terms', [AcademicTermController::class, 'store'])->name('terms.store');
        Route::put('terms/{term}', [AcademicTermController::class, 'update'])->name('terms.update');
        Route::delete('terms/{term}', [AcademicTermController::class, 'destroy'])->name('terms.destroy');
        Route::post('terms/setup-session', [AcademicTermController::class, 'setupSession'])->name('terms.setup-session');
        Route::post('annual-report/co-scholastic', [AnnualReportController::class, 'saveCoScholastic'])->name('annual-report.co-scholastic');
        Route::post('annual-report/remarks', [AnnualReportController::class, 'saveRemarks'])->name('annual-report.remarks');

        Route::post('types', [ExamTypeController::class, 'store'])->name('types.store');
        Route::put('types/{examType}', [ExamTypeController::class, 'update'])->name('types.update');
        Route::delete('types/{examType}', [ExamTypeController::class, 'destroy'])->name('types.destroy');

        Route::post('schedules', [ExamScheduleController::class, 'store'])->name('schedules.store');
        Route::put('schedules/{examSchedule}', [ExamScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('schedules/{examSchedule}', [ExamScheduleController::class, 'destroy'])->name('schedules.destroy');

        Route::post('grade-system', [GradeSystemController::class, 'store'])->name('grade-system.store');
        Route::put('grade-system/{gradeSystem}', [GradeSystemController::class, 'update'])->name('grade-system.update');
        Route::delete('grade-system/{gradeSystem}', [GradeSystemController::class, 'destroy'])->name('grade-system.destroy');

        Route::post('marks/subjects', [MarkController::class, 'addSubject'])->name('marks.subjects.store');
        Route::delete('marks/subjects/{examSchedule}', [MarkController::class, 'removeSubject'])->name('marks.subjects.destroy');
        Route::post('marks/save', [MarkController::class, 'save'])->name('marks.save');
        Route::delete('marks/sheet', [MarkController::class, 'deleteSheet'])->name('marks.sheet.destroy');
        Route::post('marks/import', [MarkController::class, 'import'])->name('marks.import');

        Route::post('seat-plans', [SeatPlanController::class, 'store'])->name('seat-plans.store');
        Route::delete('seat-plans/{seatPlanSheet}', [SeatPlanController::class, 'destroy'])->name('seat-plans.destroy');

        Route::post('questions', [QuestionController::class, 'store'])->name('questions.store');
        Route::put('questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
        Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');

        Route::post('schedule-sheets', [ExamScheduleSheetController::class, 'store'])->name('schedule-sheets.store');
        Route::put('schedule-sheets/{examScheduleSheet}', [ExamScheduleSheetController::class, 'update'])->name('schedule-sheets.update');
        Route::delete('schedule-sheets/{examScheduleSheet}', [ExamScheduleSheetController::class, 'destroy'])->name('schedule-sheets.destroy');
        Route::post('schedule-sheets/import-preview', [ExamScheduleSheetController::class, 'importPreview'])->name('schedule-sheets.import-preview');

        Route::post('/', [ExamController::class, 'store'])->name('store');
        Route::put('{exam}', [ExamController::class, 'update'])->name('update');
        Route::delete('{exam}', [ExamController::class, 'destroy'])->name('destroy');
        Route::patch('{exam}/publish', [ExamController::class, 'publish'])->name('publish');
        Route::patch('{exam}/pdf-color', [ExamController::class, 'updatePdfColor'])->name('pdf-color');

        Route::put('{exam}/admit-cards/instructions', [AdmitCardController::class, 'saveInstructions'])->name('admit-cards.instructions.update');
    });
});

Route::prefix('finance-payroll')->name('finance-payroll.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
    Route::get('expense-categories/tree', [ExpenseCategoryController::class, 'tree'])->name('expense-categories.tree');
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('incomes', [IncomeController::class, 'index'])->name('incomes.index');
    Route::get('bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::get('bank-transactions', [BankTransactionController::class, 'index'])->name('bank-transactions.index');
    Route::get('salary-structures', [SalaryStructureController::class, 'index'])->name('salary-structures.index');
    Route::get('salary-slips', [SalarySlipController::class, 'index'])->name('salary-slips.index');
    Route::get('salary-slips/{salarySlip}/pdf', [SalarySlipController::class, 'downloadPdf'])->name('salary-slips.pdf');
    Route::get('salary-reports', [SalaryReportController::class, 'index'])->name('salary-reports.index');
    Route::get('salary-monthly/export', [SalaryMonthlySheetController::class, 'export'])->name('salary-monthly.export');
    Route::post('salary-monthly/preview', [SalaryMonthlyImportController::class, 'preview'])->name('salary-monthly.preview');
    Route::get('cash-book', [CashBookController::class, 'index'])->name('cash-book.index');
    Route::get('book-store', [BookStoreController::class, 'index'])->name('book-store.index');
    Route::get('book-expenses', [BookExpenseController::class, 'index'])->name('book-expenses.index');
    Route::get('book-expenses/{bookExpense}/pdf', [BookExpenseController::class, 'downloadPdf'])->name('book-expenses.pdf');

    // Writes require the finance.manage permission (see Settings > Roles & Permissions).
    Route::middleware('erp.permission:finance.manage')->group(function () {
        Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
        Route::put('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
        Route::patch('expense-categories/{expenseCategory}/toggle', [ExpenseCategoryController::class, 'toggleActive'])->name('expense-categories.toggle');
        Route::delete('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');

        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        Route::post('incomes', [IncomeController::class, 'store'])->name('incomes.store');
        Route::put('incomes/{income}', [IncomeController::class, 'update'])->name('incomes.update');
        Route::delete('incomes/{income}', [IncomeController::class, 'destroy'])->name('incomes.destroy');

        Route::post('bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
        Route::put('bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->name('bank-accounts.update');
        Route::delete('bank-accounts/{bankAccount}', [BankAccountController::class, 'destroy'])->name('bank-accounts.destroy');

        Route::post('bank-transactions', [BankTransactionController::class, 'store'])->name('bank-transactions.store');
        Route::put('bank-transactions/{bankTransaction}', [BankTransactionController::class, 'update'])->name('bank-transactions.update');
        Route::delete('bank-transactions/{bankTransaction}', [BankTransactionController::class, 'destroy'])->name('bank-transactions.destroy');

        Route::post('salary-structures', [SalaryStructureController::class, 'store'])->name('salary-structures.store');

        Route::post('salary-slips', [SalarySlipController::class, 'store'])->name('salary-slips.store');
        Route::put('salary-slips/{salarySlip}', [SalarySlipController::class, 'update'])->name('salary-slips.update');
        Route::delete('salary-slips/{salarySlip}', [SalarySlipController::class, 'destroy'])->name('salary-slips.destroy');
        Route::post('salary-slips/generate', [SalarySlipController::class, 'generate'])->name('salary-slips.generate');
        Route::patch('salary-slips/{salarySlip}/pay', [SalarySlipController::class, 'markPaid'])->name('salary-slips.pay');
        Route::post('salary-monthly/import', [SalaryMonthlyImportController::class, 'import'])->name('salary-monthly.import');

        Route::post('book-store', [BookStoreController::class, 'store'])->name('book-store.store');
        Route::put('book-store/{storeBook}', [BookStoreController::class, 'update'])->name('book-store.update');
        Route::delete('book-store/{storeBook}', [BookStoreController::class, 'destroy'])->name('book-store.destroy');

        Route::post('book-expenses', [BookExpenseController::class, 'store'])->name('book-expenses.store');
        Route::put('book-expenses/{bookExpense}', [BookExpenseController::class, 'update'])->name('book-expenses.update');
        Route::delete('book-expenses/{bookExpense}', [BookExpenseController::class, 'destroy'])->name('book-expenses.destroy');
    });
});

Route::prefix('transport')->name('transport.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
    Route::get('stops', [RouteStopController::class, 'index'])->name('stops.index');
    Route::get('maintenance', [VehicleMaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('fuel-logs', [FuelLogController::class, 'index'])->name('fuel-logs.index');
    Route::get('documents', [VehicleDocumentController::class, 'index'])->name('documents.index');
    Route::get('student-transport', [StudentTransportController::class, 'index'])->name('student-transport.index');
    Route::get('reports', [TransportReportController::class, 'index'])->name('reports.index');
    Route::get('routes', [RouteController::class, 'index'])->name('routes.index');

    // Writes require the transport.manage permission (see Settings > Roles & Permissions).
    Route::middleware('erp.permission:transport.manage')->group(function () {
        Route::post('vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::put('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');

        Route::post('stops', [RouteStopController::class, 'store'])->name('stops.store');
        Route::put('stops/{routeStop}', [RouteStopController::class, 'update'])->name('stops.update');
        Route::delete('stops/{routeStop}', [RouteStopController::class, 'destroy'])->name('stops.destroy');

        Route::post('maintenance', [VehicleMaintenanceController::class, 'store'])->name('maintenance.store');
        Route::put('maintenance/{vehicleMaintenance}', [VehicleMaintenanceController::class, 'update'])->name('maintenance.update');
        Route::delete('maintenance/{vehicleMaintenance}', [VehicleMaintenanceController::class, 'destroy'])->name('maintenance.destroy');

        Route::post('fuel-logs', [FuelLogController::class, 'store'])->name('fuel-logs.store');
        Route::put('fuel-logs/{fuelLog}', [FuelLogController::class, 'update'])->name('fuel-logs.update');
        Route::delete('fuel-logs/{fuelLog}', [FuelLogController::class, 'destroy'])->name('fuel-logs.destroy');

        Route::post('documents', [VehicleDocumentController::class, 'store'])->name('documents.store');
        Route::put('documents/{vehicleDocument}', [VehicleDocumentController::class, 'update'])->name('documents.update');
        Route::delete('documents/{vehicleDocument}', [VehicleDocumentController::class, 'destroy'])->name('documents.destroy');

        Route::post('student-transport', [StudentTransportController::class, 'store'])->name('student-transport.store');
        Route::put('student-transport/{studentTransport}', [StudentTransportController::class, 'update'])->name('student-transport.update');
        Route::delete('student-transport/{studentTransport}', [StudentTransportController::class, 'destroy'])->name('student-transport.destroy');

        Route::post('routes/sync', [RouteController::class, 'sync'])->name('routes.sync');
        Route::post('routes', [RouteController::class, 'store'])->name('routes.store');
        Route::put('routes/{route}', [RouteController::class, 'update'])->name('routes.update');
        Route::delete('routes/{route}', [RouteController::class, 'destroy'])->name('routes.destroy');
    });
});

Route::prefix('library')->name('library.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('categories', [BookCategoryController::class, 'index'])->name('categories.index');
    Route::get('authors', [AuthorController::class, 'index'])->name('authors.index');
    Route::get('publishers', [PublisherController::class, 'index'])->name('publishers.index');
    Route::get('books', [BookController::class, 'index'])->name('books.index');
    Route::get('members', [LibraryMemberController::class, 'index'])->name('members.index');
    Route::get('members/{libraryMember}/pdf', [LibraryMemberController::class, 'downloadPdf'])->name('members.pdf');
    Route::get('issues', [BookIssueController::class, 'index'])->name('issues.index');
    Route::get('fines', [FineCollectionController::class, 'index'])->name('fines.index');
    Route::get('reports', [LibraryReportController::class, 'index'])->name('reports.index');

    // Writes require the library.manage permission (see Settings > Roles & Permissions).
    Route::middleware('erp.permission:library.manage')->group(function () {
        Route::post('categories', [BookCategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{bookCategory}', [BookCategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{bookCategory}', [BookCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::post('authors', [AuthorController::class, 'store'])->name('authors.store');
        Route::put('authors/{author}', [AuthorController::class, 'update'])->name('authors.update');
        Route::delete('authors/{author}', [AuthorController::class, 'destroy'])->name('authors.destroy');

        Route::post('publishers', [PublisherController::class, 'store'])->name('publishers.store');
        Route::put('publishers/{publisher}', [PublisherController::class, 'update'])->name('publishers.update');
        Route::delete('publishers/{publisher}', [PublisherController::class, 'destroy'])->name('publishers.destroy');

        Route::post('books', [BookController::class, 'store'])->name('books.store');
        Route::put('books/{book}', [BookController::class, 'update'])->name('books.update');
        Route::delete('books/{book}', [BookController::class, 'destroy'])->name('books.destroy');

        Route::post('members', [LibraryMemberController::class, 'store'])->name('members.store');
        Route::put('members/{libraryMember}', [LibraryMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{libraryMember}', [LibraryMemberController::class, 'destroy'])->name('members.destroy');

        Route::post('issues', [BookIssueController::class, 'store'])->name('issues.store');
        Route::patch('issues/{bookIssue}/return', [BookIssueController::class, 'returnBook'])->name('issues.return');

        Route::patch('fines/{bookIssue}/collect', [FineCollectionController::class, 'collect'])->name('fines.collect');
    });
});

Route::prefix('inventory')->name('inventory.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('stock/adjustments', [StockController::class, 'adjustments'])->name('stock.adjustments');
    Route::get('low-stock-alerts', [LowStockAlertController::class, 'index'])->name('low-stock-alerts.index');
    Route::get('reports', [InventoryReportController::class, 'index'])->name('reports.index');

    // Writes require the inventory.manage permission (see Settings > Roles & Permissions).
    Route::middleware('erp.permission:inventory.manage')->group(function () {
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');

        Route::post('stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
    });
});

Route::prefix('hostel')->name('hostel.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('beds', [BedController::class, 'index'])->name('beds.index');
    Route::get('allocations', [HostelAllocationController::class, 'index'])->name('allocations.index');
    Route::get('visitors', [HostelVisitorController::class, 'index'])->name('visitors.index');
    Route::get('fees', [HostelFeeController::class, 'index'])->name('fees.index');
    Route::get('reports', [HostelReportController::class, 'index'])->name('reports.index');

    // Writes require the hostel.manage permission (see Settings > Roles & Permissions).
    Route::middleware('erp.permission:hostel.manage')->group(function () {
        Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::put('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');

        Route::post('beds', [BedController::class, 'store'])->name('beds.store');
        Route::put('beds/{bed}', [BedController::class, 'update'])->name('beds.update');
        Route::delete('beds/{bed}', [BedController::class, 'destroy'])->name('beds.destroy');

        Route::post('allocations', [HostelAllocationController::class, 'store'])->name('allocations.store');
        Route::put('allocations/{hostelAllocation}', [HostelAllocationController::class, 'update'])->name('allocations.update');
        Route::patch('allocations/{hostelAllocation}/vacate', [HostelAllocationController::class, 'vacate'])->name('allocations.vacate');

        Route::post('visitors', [HostelVisitorController::class, 'store'])->name('visitors.store');
        Route::put('visitors/{hostelVisitor}', [HostelVisitorController::class, 'update'])->name('visitors.update');
        Route::patch('visitors/{hostelVisitor}/checkout', [HostelVisitorController::class, 'checkout'])->name('visitors.checkout');
        Route::delete('visitors/{hostelVisitor}', [HostelVisitorController::class, 'destroy'])->name('visitors.destroy');

        Route::post('fees/generate', [HostelFeeController::class, 'generate'])->name('fees.generate');
        Route::patch('fees/{hostelFee}/pay', [HostelFeeController::class, 'markPaid'])->name('fees.pay');
    });
});

Route::prefix('documents')->name('documents.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('certificate-types', [CertificateTypeController::class, 'index'])->name('certificate-types.index');
    Route::get('certificates/recipients', [CertificateController::class, 'recipients'])->name('certificates.recipients');
    Route::get('certificates/{certificateType}/zip', [CertificateController::class, 'downloadZip'])->name('certificates.zip');
    Route::get('certificates/{certificateType}/{student}/pdf', [CertificateController::class, 'downloadStudentPdf'])->name('certificates.student-pdf');
    Route::get('certificates/{certificateType}/{student}/prepare', [CertificateController::class, 'prepareData'])->name('certificates.prepare');
    Route::get('id-cards', [IdCardController::class, 'index'])->name('id-cards.index');
    Route::get('id-cards/{idCard}/pdf', [IdCardController::class, 'downloadPdf'])->name('id-cards.pdf');
    Route::get('transport-cards', [TransportCardController::class, 'index'])->name('transport-cards.index');
    Route::get('transport-cards/zip', [TransportCardController::class, 'downloadZip'])->name('transport-cards.zip');
    Route::get('transport-cards/{student}/pdf', [TransportCardController::class, 'downloadStudentPdf'])->name('transport-cards.pdf');

    Route::get('templates/categories', [TemplateController::class, 'categories'])->name('templates.categories');
    Route::get('templates/page-presets', [TemplateController::class, 'pagePresets'])->name('templates.page-presets');
    Route::get('templates/fields', [TemplateController::class, 'fields'])->name('templates.fields');
    Route::get('templates/sample-data', [TemplateController::class, 'sampleData'])->name('templates.sample-data');
    Route::get('templates/designs', [TemplateController::class, 'designs'])->name('templates.designs');
    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('templates/{template}', [TemplateController::class, 'show'])->name('templates.show');
    Route::get('templates/{template}/preview-pdf', [TemplateController::class, 'previewPdf'])->name('templates.preview-pdf');
    Route::get('templates/{template}/download-json', [TemplateController::class, 'downloadJson'])->name('templates.download-json');
    Route::get('templates/{template}/assets/{filename}', [TemplateController::class, 'asset'])->name('templates.asset');
    Route::get('templates/{template}/code', [TemplateController::class, 'code'])->name('templates.code');

    // Writes require the documents.manage permission (see Settings > Roles & Permissions).
    Route::middleware('erp.permission:documents.manage')->group(function () {
        Route::post('certificate-types', [CertificateTypeController::class, 'store'])->name('certificate-types.store');
        Route::patch('certificate-types/{certificateType}/template', [CertificateTypeController::class, 'assignTemplate'])->name('certificate-types.assign-template');
        Route::delete('certificate-types/{certificateType}', [CertificateTypeController::class, 'destroy'])->name('certificate-types.destroy');

        Route::post('id-cards', [IdCardController::class, 'store'])->name('id-cards.store');
        Route::put('id-cards/{idCard}', [IdCardController::class, 'update'])->name('id-cards.update');
        Route::patch('id-cards/{idCard}/reissue', [IdCardController::class, 'reissue'])->name('id-cards.reissue');
        Route::delete('id-cards/{idCard}', [IdCardController::class, 'destroy'])->name('id-cards.destroy');

        Route::post('templates/import', [TemplateController::class, 'import'])->name('templates.import');
        Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::put('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
        Route::post('templates/{template}/duplicate', [TemplateController::class, 'duplicate'])->name('templates.duplicate');
        Route::patch('templates/{template}/favorite', [TemplateController::class, 'toggleFavorite'])->name('templates.favorite');
        Route::patch('templates/{template}/default', [TemplateController::class, 'setDefault'])->name('templates.default');
        Route::post('templates/{template}/assets', [TemplateController::class, 'uploadAsset'])->name('templates.assets.store');
        Route::put('templates/{template}/code', [TemplateController::class, 'updateCode'])->name('templates.code.update');
        Route::patch('templates/{template}/render-mode', [TemplateController::class, 'switchRenderMode'])->name('templates.render-mode');
    });
});

Route::prefix('communication')->name('communication.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('notices', [ErpNoticeController::class, 'index'])->name('notices.index');
    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');

    // Writes require the communication.manage permission (see Settings > Roles & Permissions).
    Route::middleware('erp.permission:communication.manage')->group(function () {
        Route::post('notices', [ErpNoticeController::class, 'store'])->name('notices.store');
        Route::put('notices/{notice}', [ErpNoticeController::class, 'update'])->name('notices.update');
        Route::delete('notices/{notice}', [ErpNoticeController::class, 'destroy'])->name('notices.destroy');

        Route::post('events', [EventController::class, 'store'])->name('events.store');
        Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

        Route::post('messages', [MessageController::class, 'store'])->name('messages.store');
        Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
    });
});

Route::prefix('meetings')->name('meetings.')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('/', [MeetingController::class, 'index'])->name('index');
    Route::get('reports', [MeetingReportController::class, 'index'])->name('reports.index');

    // Writes require the meetings.manage permission (see Settings > Roles & Permissions).
    Route::middleware('erp.permission:meetings.manage')->group(function () {
        Route::post('/', [MeetingController::class, 'store'])->name('store');
        Route::put('{meeting}', [MeetingController::class, 'update'])->name('update');
        Route::delete('{meeting}', [MeetingController::class, 'destroy'])->name('destroy');
    });
});

// All reports below are cross-module read-only summaries, gated behind reports.view
// rather than left open — report data (fee/finance figures especially) is sensitive.
Route::prefix('reports')->name('reports.')->middleware('erp.permission:reports.view')->group(function () {
    Route::get('students', [StudentReportController::class, 'index'])->name('students.index');
    Route::get('admissions', [AdmissionReportController::class, 'index'])->name('admissions.index');
    Route::get('fees', [FeeReportController::class, 'index'])->name('fees.index');
    Route::get('finance', [FinanceReportController::class, 'index'])->name('finance.index');

    // "Register" reports — flat student lists (JSON for the screen, ?format=csv|xlsx|pdf to download).
    Route::get('register/class-wise', [RegisterReportController::class, 'classWise'])->name('register.class-wise');
    Route::get('register/area-wise', [RegisterReportController::class, 'areaWise'])->name('register.area-wise');
    Route::get('register/father-wise', [RegisterReportController::class, 'fatherWise'])->name('register.father-wise');
    Route::get('register/vehicle-wise', [RegisterReportController::class, 'vehicleWise'])->name('register.vehicle-wise');
    Route::get('udise', [UdiseReportController::class, 'index'])->name('udise');
    Route::put('udise/{student}', [UdiseReportController::class, 'update'])->name('udise.update');
});

Route::prefix('import-export')->name('import-export.')->middleware('demo.no_import')->group(function () {
    // Reads are available to any authenticated ERP user.
    Route::get('export/{entity}', [ExportController::class, 'download'])->name('export.download');
    Route::get('logs', [ImportExportLogController::class, 'index'])->name('logs.index');
    Route::get('logs/{importExportLog}/rows', [ImportExportLogController::class, 'rows'])->name('logs.rows');
    Route::get('failed-records', [FailedRecordController::class, 'index'])->name('failed-records.index');

    // Writes require the import.manage permission (see Settings > Roles & Permissions).
    // Student Import (StudentImportController) has been retired in favor of Student Master
    // Import below — the old controller is kept in the codebase (unrouted) for its
    // transport/hostel/health-field logic, which the new importer doesn't replicate.
    Route::middleware('erp.permission:import.manage')->group(function () {
        Route::post('import/student-master', [StudentMasterImportController::class, 'store'])->name('import.student-master');
        Route::post('import/student-pen', [StudentPenImportController::class, 'store'])->name('import.student-pen');
        Route::post('import/global-workbook', [GlobalWorkbookImportController::class, 'store'])->name('import.global-workbook');
        Route::post('import/attendance', [AttendanceImportController::class, 'store'])->name('import.attendance');
        Route::post('import/class-term-marks', [ClassTermMarksImportController::class, 'store'])->name('import.class-term-marks');
    });
});

// Every route below is gated behind system.manage rather than left open — audit trails,
// login history (IPs/emails) and infrastructure internals are not for every role to see.
Route::prefix('system')->name('system.')->middleware('erp.permission:system.manage')->group(function () {
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('login-history', [LoginHistoryController::class, 'index'])->name('login-history.index');
    Route::get('queue-monitor', [QueueMonitorController::class, 'index'])->name('queue-monitor.index');
    Route::get('scheduled-jobs', [ScheduledJobController::class, 'index'])->name('scheduled-jobs.index');
    Route::get('cache-manager', [CacheManagerController::class, 'index'])->name('cache-manager.index');
    Route::post('cache-manager/clear', [CacheManagerController::class, 'clear'])->name('cache-manager.clear');
    Route::get('health', [SystemHealthController::class, 'index'])->name('health.index');
});

// Account is self-service only — every authenticated ERP user manages their own profile,
// password, theme, sessions, tokens and tickets, so no erp.permission gate applies here.
Route::prefix('account')->name('account.')->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::put('theme', [ThemeController::class, 'update'])->name('theme.update');

    Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');

    Route::get('api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    Route::get('support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::post('support-tickets', [SupportTicketController::class, 'store'])->name('support-tickets.store');
    Route::get('support-tickets/{supportTicket}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
    Route::post('support-tickets/{supportTicket}/reply', [SupportTicketController::class, 'reply'])->name('support-tickets.reply');
    Route::delete('support-tickets/{supportTicket}', [SupportTicketController::class, 'destroy'])->name('support-tickets.destroy');
});
