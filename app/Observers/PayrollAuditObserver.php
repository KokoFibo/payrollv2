<?php

namespace App\Observers;

use App\Models\AdminVisibilitySetting;
use App\Models\AttendanceRecord;
use App\Models\Karyawan;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Setting;
use App\Support\PayrollAuditService;
use Illuminate\Database\Eloquent\Model;

class PayrollAuditObserver
{
    public function __construct(
        private PayrollAuditService $auditService,
    ) {}

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(Model $model, string $mode): void
    {
        if ($model instanceof Karyawan) {
            $changes = $this->auditService->changesFor(
                $model,
                PayrollAuditService::EMPLOYEE_FIELDS,
                $mode,
            );

            if ($changes === []) {
                return;
            }

            $this->auditService->record(
                eventType: 'employee_'.$mode,
                subjectType: 'karyawan',
                subjectId: $model->id,
                subjectLabel: "{$model->nip} - {$model->nama}",
                changes: $changes,
                employee: $model,
            );

            return;
        }

        if ($model instanceof AttendanceRecord) {
            $employee = $model->relationLoaded('karyawan')
                ? $model->karyawan
                : Karyawan::query()->find($model->karyawan_id);
            $changes = $this->auditService->changesFor(
                $model,
                PayrollAuditService::ATTENDANCE_FIELDS,
                $mode,
            );

            if ($changes === []) {
                return;
            }

            $this->auditService->record(
                eventType: 'attendance_'.$mode,
                subjectType: 'attendance_record',
                subjectId: $model->id,
                subjectLabel: "Attendance #{$model->id} · "
                    .$model->attendance_date->toDateString(),
                changes: $changes,
                metadata: ['attendance_date' => $model->attendance_date->toDateString()],
                employee: $employee instanceof Karyawan ? $employee : null,
            );

            return;
        }

        if ($model instanceof PayrollPeriod) {
            $changes = $this->auditService->changesFor(
                $model,
                PayrollAuditService::PAYROLL_PERIOD_FIELDS,
                $mode,
            );

            if ($changes === []) {
                return;
            }

            $this->auditService->record(
                eventType: 'payroll_period_'.$mode,
                subjectType: 'payroll_period',
                subjectId: $model->id,
                subjectLabel: $model->name,
                changes: $changes,
                metadata: ['pay_date' => $model->pay_date?->toDateString()],
            );

            return;
        }

        if ($model instanceof PayrollItem) {
            $employee = $model->relationLoaded('karyawan')
                ? $model->karyawan
                : Karyawan::query()->find($model->karyawan_id);
            $changes = $this->auditService->changesFor(
                $model,
                PayrollAuditService::PAYROLL_ITEM_FIELDS,
                $mode,
            );

            if ($changes === []) {
                return;
            }

            $this->auditService->record(
                eventType: 'payroll_item_'.$mode,
                subjectType: 'payroll_item',
                subjectId: $model->id,
                subjectLabel: "Payroll item #{$model->id}",
                changes: $changes,
                metadata: ['payroll_period_id' => $model->payroll_period_id],
                employee: $employee instanceof Karyawan ? $employee : null,
            );

            return;
        }

        if ($model instanceof Setting) {
            if (! in_array($model->group, ['payroll', 'attendance'], true)) {
                return;
            }

            $label = $model->label ?: $model->key;
            $changes = $this->auditService->changesFor(
                $model,
                ['value' => $label],
                $mode,
            );

            if ($changes === []) {
                return;
            }

            $this->auditService->record(
                eventType: 'payroll_setting_'.$mode,
                subjectType: 'setting',
                subjectId: $model->id,
                subjectLabel: $label,
                changes: $changes,
                metadata: ['group' => $model->group, 'key' => $model->key],
            );

            return;
        }

        if ($model instanceof AdminVisibilitySetting) {
            $changes = $this->auditService->changesFor(
                $model,
                PayrollAuditService::VISIBILITY_FIELDS,
                $mode,
            );

            if ($changes === []) {
                return;
            }

            $this->auditService->record(
                eventType: 'payroll_setting_'.$mode,
                subjectType: 'admin_visibility_setting',
                subjectId: $model->id,
                subjectLabel: 'Konfigurasi payroll dan attendance',
                changes: $changes,
            );
        }
    }
}
