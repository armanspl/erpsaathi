<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Short, stable discriminators for the polymorphic Attendance / LeaveRequest
        // relations, instead of leaking fully-qualified class names into the DB/API.
        Relation::enforceMorphMap([
            'student' => Student::class,
            'teacher' => Teacher::class,
            'staff' => Staff::class,
            'driver' => Driver::class,
        ]);

        $this->registerAuditListener();
    }

    /**
     * A single global listener gives System > Audit Logs comprehensive coverage of every
     * model in the app without touching each module's controllers. Scoped to requests made
     * through an authenticated ERP session, so seeding/console activity never pollutes it.
     */
    private function registerAuditListener(): void
    {
        foreach (['created', 'updated', 'deleted'] as $action) {
            Event::listen("eloquent.{$action}: *", function (string $eventName, array $data) use ($action) {
                $model = $data[0] ?? null;
                if (! $model instanceof Model || $model instanceof AuditLog) {
                    return;
                }

                $performedById = Auth::guard('erp')->id();
                if (! $performedById) {
                    return;
                }

                // Laravel only syncs getChanges() in finishSave(), which runs after the
                // "created" event fires — so on create the dirty-diff is always empty.
                // Fall back to the full attribute set in that one case.
                $changes = match ($action) {
                    'created' => $model->getAttributes(),
                    'updated' => $model->getChanges(),
                    'deleted' => null,
                };

                AuditLog::create([
                    'action' => $action,
                    'auditable_type' => class_basename($model),
                    'auditable_id' => $model->getKey(),
                    'changes' => $changes,
                    'performed_by_id' => $performedById,
                ]);
            });
        }
    }
}
