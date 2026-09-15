<?php

use Illuminate\Support\Facades\Route;
use Modules\Custom\MakerBid\Http\Controllers\Admin\CompanyAdminController;
use Modules\Custom\MakerBid\Http\Controllers\Admin\JobAdminController;
use Modules\Custom\MakerBid\Http\Controllers\JobController;

Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('jobs/{id}', [JobController::class, 'show'])->name('jobs.show');
Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');
Route::post('jobs/{id}/bids', [JobController::class, 'bid'])->name('jobs.bid');
Route::post('jobs/{id}/award', [JobController::class, 'award'])->name('jobs.award');

Route::prefix('admin')->group(function () {
    Route::get('jobs', [JobAdminController::class, 'index'])->name('admin.jobs.index');
    Route::patch('jobs/{id}', [JobAdminController::class, 'update'])->name('admin.jobs.update');
    Route::post('jobs/{id}/hold', [JobAdminController::class, 'hold'])->name('admin.jobs.hold');
    Route::delete('jobs/{id}', [JobAdminController::class, 'destroy'])->name('admin.jobs.destroy');
    Route::get('companies', [CompanyAdminController::class, 'index'])->name('admin.companies.index');
    Route::post('companies', [CompanyAdminController::class, 'store'])->name('admin.companies.store');
    Route::post('companies/{id}/approve', [CompanyAdminController::class, 'approve'])->name('admin.companies.approve');
    Route::delete('companies/{id}', [CompanyAdminController::class, 'destroy'])->name('admin.companies.destroy');
});
