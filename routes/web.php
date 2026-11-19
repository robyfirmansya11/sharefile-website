<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShareController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified', \App\Http\Middleware\RecordFileActivity::class])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('share/activity', [\App\Http\Controllers\FileActivityController::class, 'index'])->name('share.activity');
    Route::get('share/trash', [\App\Http\Controllers\RecycleBinController::class, 'index'])->name('share.trash');
    Route::post('share/trash', [\App\Http\Controllers\RecycleBinController::class, 'update'])->middleware('throttle:60,1')->name('share.trash.update');
    Route::get('share/entries', [ShareController::class, 'index'])->name('share.entries');
    Route::get('share/search', \App\Http\Controllers\ShareSearchController::class)->middleware('throttle:20,1')->name('share.search');
    Route::get('share/download', [ShareController::class, 'download'])->name('share.download');
    Route::get('share/preview', [ShareController::class, 'preview'])->name('share.preview');
    Route::post('share/operations', [ShareController::class, 'mutate'])->middleware('throttle:60,1')->name('share.operations');
    Route::post('share/upload', [ShareController::class, 'upload'])->middleware('throttle:30,1')->name('share.upload');
});

require __DIR__.'/settings.php';

Route::middleware(['auth', 'verified', \App\Http\Middleware\RequireItAdmin::class])->prefix('admin')->group(function () {
    Route::get('staff', [\App\Http\Controllers\StaffRegistrationController::class, 'index'])->name('admin.staff.index');
    Route::post('staff', [\App\Http\Controllers\StaffRegistrationController::class, 'store'])->middleware('throttle:10,1')->name('admin.staff.store');
});
