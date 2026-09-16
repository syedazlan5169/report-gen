<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportGeneratorController;
use App\Http\Controllers\ReportTemplateController;
use App\Http\Controllers\ReportTemplateImportExportController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\ShiftImportExportController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffImportExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('generator.index');
    }

    return view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route('generator.index');
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/generator', [ReportGeneratorController::class, 'index'])->name('generator.index');
    Route::post('/generator', [ReportGeneratorController::class, 'generate'])->name('generator.generate');

    Route::get('/staff/export', [StaffImportExportController::class, 'export'])->name('staff.export');
    Route::get('/staff/import', [StaffImportExportController::class, 'create'])->name('staff.import.create');
    Route::post('/staff/import', [StaffImportExportController::class, 'store'])->name('staff.import.store');
    Route::resource('staff', StaffController::class)->except(['show']);

    Route::get('/shifts/export', [ShiftImportExportController::class, 'export'])->name('shifts.export');
    Route::get('/shifts/import', [ShiftImportExportController::class, 'create'])->name('shifts.import.create');
    Route::post('/shifts/import', [ShiftImportExportController::class, 'store'])->name('shifts.import.store');
    Route::resource('shifts', ShiftController::class)->except(['show']);

    Route::get('/report-templates/export', [ReportTemplateImportExportController::class, 'export'])->name('report-templates.export');
    Route::get('/report-templates/import', [ReportTemplateImportExportController::class, 'create'])->name('report-templates.import.create');
    Route::post('/report-templates/import', [ReportTemplateImportExportController::class, 'store'])->name('report-templates.import.store');
    Route::resource('report-templates', ReportTemplateController::class)->except(['show']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
