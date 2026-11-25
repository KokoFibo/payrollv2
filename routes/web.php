<?php

use App\Http\Controllers\Admin\AttendanceCleanupController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\AttendanceImportController;
use App\Http\Controllers\Admin\CalendarOverrideController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeveloperSettingsController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\NationalHolidayController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SpecialWorkScheduleController;
use App\Http\Controllers\Admin\SuperAdminDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::redirect('/dashboard', '/admin')->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/export', [EmployeeController::class, 'export'])
        ->middleware('can:export-employees')
        ->name('employees.export');
    Route::get('/employees/export-khusus', [EmployeeController::class, 'exportKhusus'])
        ->middleware('can:export-employees-khusus')
        ->name('employees.export-khusus');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{karyawan}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{karyawan}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{karyawan}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::get('/employees/options', [EmployeeController::class, 'searchOptions'])->name('employees.options');

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::post('/import', [AttendanceImportController::class, 'store'])->name('import');
        Route::post('/', [AttendanceController::class, 'store'])->name('store');
        Route::delete('/day', [AttendanceController::class, 'destroyDay'])->name('destroy-day');
        Route::put('/inline', [AttendanceController::class, 'updateInline'])->name('update-inline');
        Route::put('/{attendance}', [AttendanceController::class, 'update'])->name('update');
        Route::delete('/{attendance}', [AttendanceController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:access-payroll-reports')->prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::post('/', [PayrollController::class, 'store'])->name('store');
        Route::get('/{payrollPeriod}', [PayrollController::class, 'show'])->name('show');
        Route::post('/{payrollPeriod}/process', [PayrollController::class, 'process'])->name('process');
        Route::post('/{payrollPeriod}/pay', [PayrollController::class, 'markPaid'])->name('pay');
        Route::delete('/{payrollPeriod}', [PayrollController::class, 'destroy'])->name('destroy');
    });

    Route::get('/reports', ReportController::class)
        ->middleware('can:access-payroll-reports')
        ->name('reports.index');
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    Route::middleware('can:manage-users')->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    Route::get('/super-admin-dashboard', [SuperAdminDashboardController::class, 'index'])
        ->middleware('can:access-super-admin-dashboard')
        ->name('super-admin-dashboard.index');
    Route::get('/super-admin-dashboard/export/department', [SuperAdminDashboardController::class, 'exportDepartment'])
        ->middleware('can:access-super-admin-dashboard')
        ->name('super-admin-dashboard.export-department');
    Route::get('/super-admin-dashboard/export/directorate', [SuperAdminDashboardController::class, 'exportDirectorate'])
        ->middleware('can:access-super-admin-dashboard')
        ->name('super-admin-dashboard.export-directorate');

    Route::middleware('can:access-developer-settings')->group(function () {
        Route::get('/developer-settings', [DeveloperSettingsController::class, 'index'])
            ->name('developer-settings.index');
        Route::post('/developer-settings/migrate', [DeveloperSettingsController::class, 'copy'])
            ->name('developer-settings.copy');
        Route::post('/developer-settings/copy-master-data', [DeveloperSettingsController::class, 'copyMasterData'])
            ->name('developer-settings.copy-master-data');
        Route::delete('/developer-settings/attendance-cleanup', [AttendanceCleanupController::class, 'destroy'])
            ->name('developer-settings.attendance-cleanup.destroy');
        Route::post('/developer-settings/national-holidays', [NationalHolidayController::class, 'store'])
            ->name('developer-settings.national-holidays.store');
        Route::put('/developer-settings/national-holidays/{nationalHoliday}', [NationalHolidayController::class, 'update'])
            ->name('developer-settings.national-holidays.update');
        Route::delete('/developer-settings/national-holidays/{nationalHoliday}', [NationalHolidayController::class, 'destroy'])
            ->name('developer-settings.national-holidays.destroy');
        Route::post('/developer-settings/calendar-overrides', [CalendarOverrideController::class, 'store'])
            ->name('developer-settings.calendar-overrides.store');
        Route::put('/developer-settings/calendar-overrides/{calendarOverride}', [CalendarOverrideController::class, 'update'])
            ->name('developer-settings.calendar-overrides.update');
        Route::delete('/developer-settings/calendar-overrides/{calendarOverride}', [CalendarOverrideController::class, 'destroy'])
            ->name('developer-settings.calendar-overrides.destroy');
        Route::post('/developer-settings/special-work-schedules', [SpecialWorkScheduleController::class, 'store'])
            ->name('developer-settings.special-work-schedules.store');
        Route::put('/developer-settings/special-work-schedules/{specialWorkSchedule}', [SpecialWorkScheduleController::class, 'update'])
            ->name('developer-settings.special-work-schedules.update');
        Route::delete('/developer-settings/special-work-schedules/{specialWorkSchedule}', [SpecialWorkScheduleController::class, 'destroy'])
            ->name('developer-settings.special-work-schedules.destroy');
        Route::put('/developer-settings/admin-salary-limit', [DeveloperSettingsController::class, 'updateAdminSalaryLimit'])
            ->name('developer-settings.admin-salary-limit.update');
        Route::post('/developer-settings/master/{resource}', [MasterDataController::class, 'store'])
            ->name('developer-settings.master.store');
        Route::put('/developer-settings/master/{resource}/{record}', [MasterDataController::class, 'update'])
            ->name('developer-settings.master.update');
        Route::delete('/developer-settings/master/{resource}/{record}', [MasterDataController::class, 'destroy'])
            ->name('developer-settings.master.destroy');
    });
});

require __DIR__.'/auth.php';
