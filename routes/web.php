<?php

use App\Http\Controllers\AccrualManagementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentManagementController;
use App\Http\Controllers\EmployeeManagementController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveApplicationController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeManagementController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\PrintLeaveFormController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SignatorySettingsController;
use App\Http\Controllers\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::redirect('/views/login.php', '/login');
Route::redirect('/views/dashboard.php', '/dashboard');
Route::redirect('/views/apply_leave.php', '/leave/apply');
Route::redirect('/views/leave_requests.php', '/leave/requests');
Route::redirect('/views/calendar.php', '/calendar');
Route::redirect('/views/reports.php', '/reports');
Route::redirect('/views/holidays.php', '/holidays');
Route::redirect('/views/manage_departments.php', '/manage-departments');
Route::redirect('/views/manage_leave_types.php', '/manage-leave-types');
Route::redirect('/views/manage_accruals.php', '/manage-accruals');
Route::redirect('/views/manage_employees.php', '/manage-employees');
Route::redirect('/views/change_password.php', '/change-password');
Route::redirect('/views/signatories_settings.php', '/signatories-settings');
Route::redirect('/views/statistics.php', '/statistics');
Route::get('/views/edit_employee.php', function () {
    $id = request()->query('id');
    return redirect()->route('manage-employees', $id ? ['edit' => $id] : []);
});
Route::get('/views/employee_profile.php', function (Illuminate\Http\Request $request) { return redirect()->route('employee-profile', array_filter(['employee' => $request->query('id')])); });
Route::get('/views/print_leave_form.php', function (Illuminate\Http\Request $request) { $id = (int) $request->query('id', 0); abort_if($id <= 0, 404); return redirect()->route('leave.print', ['leave' => $id]); });

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.perform');
    Route::get('/register', PlaceholderController::class)->defaults('title', 'Register')->name('register');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/leave/apply', [LeaveApplicationController::class, 'create'])->name('leave.apply');
    Route::post('/leave/apply', [LeaveApplicationController::class, 'store'])->name('leave.apply.store');
    Route::get('/api/calc-days', [LeaveApplicationController::class, 'calculate'])->name('api.calc-days');

    Route::get('/leave/requests', [LeaveRequestController::class, 'index'])->name('leave.requests');
    Route::post('/leave/requests/{leave}/action', [LeaveRequestController::class, 'action'])->name('leave.requests.action');

    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports');
    Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics');
    Route::get('/employee-profile', [EmployeeProfileController::class, 'show'])->name('employee-profile');
    Route::post('/employee-profile/photo', [EmployeeProfileController::class, 'updatePhoto'])->name('employee-profile.photo.update');
    Route::get('/leave/requests/{leave}/print', [PrintLeaveFormController::class, 'show'])->name('leave.print');
    Route::post('/leave/requests/{leave}/print-signatories', [PrintLeaveFormController::class, 'saveSignatories'])->name('leave.print.signatories');
    Route::get('/change-password', [ChangePasswordController::class, 'edit'])->name('change-password');
    Route::post('/change-password', [ChangePasswordController::class, 'update'])->name('change-password.update');

    Route::get('/manage-employees', [EmployeeManagementController::class, 'index'])->name('manage-employees');
    Route::post('/manage-employees', [EmployeeManagementController::class, 'store'])->name('manage-employees.store');
    Route::put('/manage-employees/{employee}', [EmployeeManagementController::class, 'update'])->name('manage-employees.update');

    Route::get('/manage-departments', [DepartmentManagementController::class, 'index'])->name('manage-departments');
    Route::post('/manage-departments', [DepartmentManagementController::class, 'store'])->name('manage-departments.store');
    Route::put('/manage-departments/{department}', [DepartmentManagementController::class, 'update'])->name('manage-departments.update');
    Route::delete('/manage-departments/{department}', [DepartmentManagementController::class, 'destroy'])->name('manage-departments.destroy');

    Route::get('/holidays', [HolidayController::class, 'index'])->name('holidays');
    Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
    Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    Route::get('/manage-accruals', [AccrualManagementController::class, 'index'])->name('manage-accruals');
    Route::post('/manage-accruals/manual', [AccrualManagementController::class, 'storeManual'])->name('manage-accruals.manual');
    Route::post('/manage-accruals/bulk', [AccrualManagementController::class, 'storeBulk'])->name('manage-accruals.bulk');

    Route::get('/manage-leave-types', [LeaveTypeManagementController::class, 'index'])->name('manage-leave-types');
    Route::post('/manage-leave-types', [LeaveTypeManagementController::class, 'store'])->name('manage-leave-types.store');
    Route::put('/manage-leave-types/{leaveType}', [LeaveTypeManagementController::class, 'update'])->name('manage-leave-types.update');
    Route::delete('/manage-leave-types/{leaveType}', [LeaveTypeManagementController::class, 'destroy'])->name('manage-leave-types.destroy');

    Route::get('/signatories-settings', [SignatorySettingsController::class, 'index'])->name('signatories-settings');
    Route::post('/signatories-settings', [SignatorySettingsController::class, 'update'])->name('signatories-settings.update');
});
