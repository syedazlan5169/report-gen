<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportGeneratorController;
use App\Http\Controllers\ReportTemplateController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route('generator.index');
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/generator', [ReportGeneratorController::class, 'index'])->name('generator.index');
    Route::post('/generator', [ReportGeneratorController::class, 'generate'])->name('generator.generate');

    Route::resource('staff', StaffController::class)->except(['show']);
    Route::resource('shifts', ShiftController::class)->except(['show']);
    Route::resource('report-templates', ReportTemplateController::class)->except(['show']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
