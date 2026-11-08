<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\OperatorController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::get('/kiosk', [KioskController::class, 'index'])->name('kiosk');
Route::get('/kiosk/state', [KioskController::class, 'state'])->name('kiosk.state');
Route::post('/kiosk/{service}', [KioskController::class, 'store'])->name('kiosk.issue');

Route::get('/display', [DisplayController::class, 'index'])->name('display');
Route::get('/display/state', [DisplayController::class, 'state'])->name('display.state');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('operator', [OperatorController::class, 'index'])->name('operator.index');
    Route::get('operator/state', [OperatorController::class, 'state'])->name('operator.state');
    Route::post('operator/{loket}/call', [OperatorController::class, 'callNext'])->name('operator.call');
    Route::post('operator/ticket/{ticket}/recall', [OperatorController::class, 'recall'])->name('operator.recall');
    Route::post('operator/ticket/{ticket}/skip', [OperatorController::class, 'skip'])->name('operator.skip');
    Route::post('operator/ticket/{ticket}/finish', [OperatorController::class, 'finish'])->name('operator.finish');

    Route::resource('admin/services', Admin\ServiceController::class)->only(['index', 'store', 'update', 'destroy'])->names('admin.services');
    Route::resource('admin/lokets', Admin\LoketController::class)->only(['index', 'store', 'update', 'destroy'])->names('admin.lokets');
    Route::get('admin/branding', [Admin\BrandingController::class, 'edit'])->name('admin.branding.edit');
    Route::post('admin/branding', [Admin\BrandingController::class, 'update'])->name('admin.branding.update');
});

require __DIR__.'/settings.php';
