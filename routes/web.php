<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeExportController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveExportController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\SessionController;
use App\Http\Middleware\EnsureActive;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login');
});

Route::middleware(['auth', EnsureActive::class])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/kata-sandi', [PasswordController::class, 'edit'])->name('password.edit');
    Route::post('/kata-sandi', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
    Route::get('/cuti', [LeaveController::class, 'index'])->name('leave.index');
    Route::get('/cuti/baru', [LeaveController::class, 'create'])->name('leave.create');
    Route::get('/cuti/persetujuan', [LeaveController::class, 'approvals'])->name('leave.approvals');
    Route::get('/cuti/kelola', [LeaveController::class, 'manage'])->name('leave.manage');
    Route::post('/cuti', [LeaveController::class, 'store'])->name('leave.store');
    Route::get('/cuti/{id}', [LeaveController::class, 'show'])->whereNumber('id')->name('leave.show');
    Route::get('/cuti/{id}/revisi', [LeaveController::class, 'edit'])->whereNumber('id')->name('leave.edit');
    Route::post('/cuti/{id}/revisi', [LeaveController::class, 'update'])->whereNumber('id')->name('leave.update');
    Route::get('/cuti/{id}/ubah-admin', [LeaveController::class, 'adminEdit'])->whereNumber('id')->name('leave.admin-edit');
    Route::post('/cuti/{id}/ubah-admin', [LeaveController::class, 'adminUpdate'])->whereNumber('id')->name('leave.admin-update');
    Route::post('/cuti/{id}/keputusan', [LeaveController::class, 'decide'])->whereNumber('id')->name('leave.decide');
    Route::post('/cuti/{id}/nomor', [LeaveController::class, 'number'])->whereNumber('id')->name('leave.number');
    Route::post('/cuti/{id}/rilis', [LeaveController::class, 'release'])->whereNumber('id')->name('leave.release');
    Route::get('/cuti/{id}/lampiran/{attachmentId}', [LeaveController::class, 'attachment'])->whereNumber(['id', 'attachmentId'])->name('leave.attachment');
    Route::get('/cuti/{id}/surat', [LeaveController::class, 'download'])->whereNumber('id')->name('leave.download');
    Route::get('/admin', [AdministrationController::class, 'index'])->name('admin.index');
    Route::get('/admin/nominatif.xlsx', EmployeeExportController::class)->name('admin.export');
    Route::get('/admin/riwayat-cuti.xlsx', LeaveExportController::class)->name('admin.leave-export');
    Route::post('/admin/impor/pratinjau', [EmployeeImportController::class, 'preview'])->name('admin.import.preview');
    Route::post('/admin/impor/simpan', [EmployeeImportController::class, 'confirm'])->name('admin.import.confirm');
    Route::post('/admin/jabatan', [AdministrationController::class, 'position'])->name('admin.position');
    Route::post('/admin/unit', [AdministrationController::class, 'unit'])->name('admin.unit');
    Route::post('/admin/tautan', [AdministrationController::class, 'socialLink'])->name('admin.social');
    Route::post('/admin/tautan/{id}/hapus', [AdministrationController::class, 'deleteSocialLink'])->whereNumber('id')->name('admin.social.delete');
    Route::post('/admin/pemetaan', [AdministrationController::class, 'route'])->name('admin.route');
    Route::post('/admin/libur', [AdministrationController::class, 'holiday'])->name('admin.holiday');
    Route::post('/admin/libur/{date}/hapus', [AdministrationController::class, 'deleteHoliday'])->name('admin.holiday.delete');
    Route::post('/admin/pegawai', [AdministrationController::class, 'saveUser'])->name('admin.user.create');
    Route::post('/admin/pegawai/{employee}', [AdministrationController::class, 'saveUser'])->name('admin.user.update');
    Route::post('/admin/pegawai/{employee}/saldo', [AdministrationController::class, 'balance'])->name('admin.balance');
    Route::get('/admin/pegawai/{employee}/riwayat', [AdministrationController::class, 'history'])->name('admin.history');
});
