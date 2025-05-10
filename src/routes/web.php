<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/admin/login', [AdminAttendanceController::class, 'login'])->name('admin.login');
Route::post('/admin/login', [AdminAttendanceController::class, 'authenticate'])->name('admin.authenticate');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth:web', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'attendance'])->name('attendance');
    Route::post('/attendance/work-start', [AttendanceController::class, 'workStart'])->name('attendance.workstart');
    Route::post('/attendance/work-end', [AttendanceController::class, 'workEnd'])->name('attendance.workend');
    Route::post('/attendance/rest-start', [AttendanceController::class, 'restStart'])->name('attendance.reststart');
    Route::post('/attendance/rest-end', [AttendanceController::class, 'restEnd'])->name('attendance.restend');
    Route::get('/attendance/list/{yearMonth?}', [AttendanceController::class, 'attendance_list'])
        ->name('attendance.list')
        ->where('yearMonth', '[0-9]{4}-[0-9]{2}');
});

Route::middleware('admin')->group(function () {
    Route::get('/admin/attendance/list/{date?}', [AdminAttendanceController::class, 'attendance_list'])
        ->name('admin.attendance.list')
        ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
    Route::get('/admin/staff/list', [AdminStaffController::class, 'staff_list'])->name('admin.staff.list');
    Route::get('/admin/attendance/staff/{id}/{yearMonth?}', [AdminStaffController::class, 'staff_attendance_list'])
        ->name('admin.staff.attendance.list')
        ->where('id', '[0-9]+')
        ->where('yearMonth', '[0-9]{4}-[0-9]{2}');
    Route::get('/admin/attendance/staff/{id}/{yearMonth}/export', [AdminStaffController::class, 'exportCsv'])
        ->name('admin.staff.attendance.export')
        ->where('id', '[0-9]+')
        ->where('yearMonth', '[0-9]{4}-[0-9]{2}');
    Route::get('/stamp_correction_request/approve/{attendance_correct_request}', [AdminApplicationController::class, 'application_approval'])
        ->name('admin.application.show.legacy')
        ->where('attendance_correct_request', '[0-9]+');
    Route::patch('/stamp_correction_request/approve/{attendance_correct_request}', [AdminApplicationController::class, 'approve'])
        ->name('admin.application.approve.legacy')
        ->where('attendance_correct_request', '[0-9]+');
});

Route::middleware(['auth', 'check.role'])->group(function () {
    Route::get('/attendance/{id}', function (Request $request, $id) {
        if ($request->attributes->get('is_admin')) {
            $controller = app()->make(\App\Http\Controllers\Admin\AttendanceController::class);
            return $controller->callAction('attendance_detail', [$id]);
        } else {
            $controller = app()->make(\App\Http\Controllers\AttendanceController::class);
            return $controller->callAction('attendance_detail', [$id]);
        }
    })->name('attendance.detail');
    Route::post('/attendance/{id}/request', [AttendanceController::class, 'requestCorrection'])
        ->name('attendance.request_correction')
        ->where('id', '[0-9]+');
    Route::post('/admin/attendance/{id}/request', [AdminAttendanceController::class, 'requestCorrection'])
        ->name('admin.attendance.request_correction')
        ->where('id', '[0-9]+');
    Route::get('/stamp_correction_request/list', function (Request $request) {
        if ($request->attributes->get('is_admin')) {
            $controller = app()->make(AdminApplicationController::class);
            return app()->call([$controller, 'application_list']);
        } else {
            $controller = app()->make(ApplicationController::class);
            return app()->call([$controller, 'application_list']);
        }
    })->name('application.list');
});