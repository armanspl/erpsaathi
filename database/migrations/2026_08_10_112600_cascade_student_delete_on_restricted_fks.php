<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow deleting students even when certificates / fee payments / book expenses exist.
 * Related rows are removed with the student (admin delete is intentional and permanent).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('certificates')
            ->whereNotIn('student_id', DB::table('students')->select('id'))
            ->delete();
        DB::table('fee_payments')
            ->whereNotIn('student_id', DB::table('students')->select('id'))
            ->delete();
        DB::table('book_expenses')
            ->whereNotIn('student_id', DB::table('students')->select('id'))
            ->delete();

        $this->recreateStudentFk('certificates', 'CASCADE');
        $this->recreateStudentFk('fee_payments', 'CASCADE');
        $this->recreateStudentFk('book_expenses', 'CASCADE');
    }

    public function down(): void
    {
        $this->recreateStudentFk('certificates', 'RESTRICT');
        $this->recreateStudentFk('fee_payments', 'RESTRICT');
        $this->recreateStudentFk('book_expenses', 'RESTRICT');
    }

    private function recreateStudentFk(string $table, string $onDelete): void
    {
        $db = Schema::getConnection()->getDatabaseName();
        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?',
            [$db, $table, 'student_id', 'students']
        );

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }

        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_student_id_foreign`
             FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE {$onDelete}"
        );
    }
};
