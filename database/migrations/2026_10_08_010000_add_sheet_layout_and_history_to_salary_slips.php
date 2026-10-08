<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('salary_slips', 'sheet_name')) {
            Schema::table('salary_slips', function (Blueprint $table) {
                // Where this slip sat in the school's salary workbook ("AUG -26" / "AUG-26-2",
                // block = the group between subtotal rows, row = order inside it) so the export
                // can rebuild the same sheets. Null for slips created by hand.
                $table->string('sheet_name', 60)->nullable()->after('remarks');
                $table->unsignedSmallInteger('sheet_block')->nullable()->after('sheet_name');
                $table->unsignedSmallInteger('sheet_row')->nullable()->after('sheet_block');
            });
        }

        if (! Schema::hasTable('salary_slip_histories')) {
            Schema::create('salary_slip_histories', function (Blueprint $table) {
                $table->id();
                // One batch = one user action (an imported sheet, a manual create/edit/delete,
                // a payment). Rolling back undoes every entry of the batch together.
                $table->uuid('batch_id')->index();
                $table->string('source', 30); // import | manual | payment | generate | bank | other
                $table->string('batch_label')->nullable();
                $table->string('action', 20); // created | updated | deleted
                $table->unsignedBigInteger('salary_slip_id')->nullable()->index();
                $table->string('employee_type', 20);
                $table->unsignedBigInteger('employee_id');
                $table->char('period', 7);
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->unsignedBigInteger('performed_by_id')->nullable();
                $table->timestamp('rolled_back_at')->nullable();
                $table->unsignedBigInteger('rolled_back_by_id')->nullable();
                $table->timestamps();

                $table->index(['employee_type', 'employee_id', 'period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_slip_histories');

        if (Schema::hasColumn('salary_slips', 'sheet_name')) {
            Schema::table('salary_slips', function (Blueprint $table) {
                $table->dropColumn(['sheet_name', 'sheet_block', 'sheet_row']);
            });
        }
    }
};
