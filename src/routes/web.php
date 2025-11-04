<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\RequestController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\AdminController;



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


Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
    Route::post('/attendance/clockin', [AttendanceController::class, 'clockin'])->name('attendance.clockin');
    Route::post('/attendance/clockout', [AttendanceController::class, 'clockout'])->name('attendance.clockout');
    Route::post('/attendance/breakin', [AttendanceController::class, 'breakin'])->name('attendance.breakin');
    Route::post('/attendance/breakout', [AttendanceController::class, 'breakout'])->name('attendance.breakout');
});

Route::post('/login', [UserController::class, 'login'])->name('login');
Route::post('/register', [UserController::class, 'register']);

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('logout');

Route::get('/email/verify', [VerificationController::class, 'show'])
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/resend', [VerificationController::class, 'resend'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.resend');

Route::get('/attendance/list', [AttendanceController::class, 'list'])
    ->middleware(['auth', 'verified'])
    ->name('attendance.list');

Route::get('/attendance/detail/{id?}', [AttendanceController::class, 'show'])->name('attendance.detail');
Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
Route::put('/attendance/detail/{id}', [AttendanceController::class, 'update'])->name('attendance.detail.update');

Route::get('/attendance/submitted/{id}', [AttendanceController::class, 'submittedList'])->name('attendance.submitted');

Route::get('/stamp_correction_request/list', [RequestController::class, 'index'])->name('request.index');
Route::get('/stamp_correction_request/list/{id}', [RequestController::class, 'show'])->name('request.show');

Route::get('/admin/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

Route::middleware(['auth:admin'])->group(function () {
    Route::get('/admin/attendance/list', [AdminController::class, 'list'])->name('admin.list');
    Route::get('/admin/attendance/detail/{id?}', [AdminController::class, 'detail'])->name('admin.attendance.admin_detail');
});

Route::get('/admin/attendance/detail/{id?}', [AdminAttendanceController::class, 'adminDetail'])
    ->name('admin.attendance.admin_detail');