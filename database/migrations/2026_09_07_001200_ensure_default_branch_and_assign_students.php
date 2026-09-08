<?php

use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\DefaultSchoolBranchService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill students with null branch_id onto a default campus branch.
 * Fresh Super Admin tenants skip creation here — SchoolProvisioner::seedTenantDefaults
 * sets the real school name first, then creates/renames the branch correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches') || ! Schema::hasTable('students')) {
            return;
        }

        $settings = Schema::hasTable('school_settings')
            ? SchoolSetting::query()->first()
            : null;
        $schoolName = trim((string) ($settings?->school_name ?? ''));
        $isPlaceholder = $schoolName === '' || $schoolName === 'Global Access School';
        $needsBackfill = Student::query()->whereNull('branch_id')->exists();

        // New empty tenant still on placeholder settings — leave branch creation to provisioner.
        if ($isPlaceholder && ! $needsBackfill) {
            return;
        }

        DefaultSchoolBranchService::ensure();
        DefaultSchoolBranchService::assignUnassignedStudents();
    }

    public function down(): void
    {
        // Keep assigned branches — clearing them would re-break filters.
    }
};
