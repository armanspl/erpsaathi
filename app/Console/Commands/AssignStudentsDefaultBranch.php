<?php

namespace App\Console\Commands;

use App\Services\DefaultSchoolBranchService;
use Illuminate\Console\Command;

class AssignStudentsDefaultBranch extends Command
{
    protected $signature = 'students:assign-default-branch';

    protected $description = 'Create the default school branch (if missing) and assign all students without a branch';

    public function handle(): int
    {
        $branch = DefaultSchoolBranchService::ensure();
        $updated = DefaultSchoolBranchService::assignUnassignedStudents($branch);

        $this->info("Branch: {$branch->name} (id {$branch->id})");
        $this->info("Students assigned: {$updated}");

        return self::SUCCESS;
    }
}
