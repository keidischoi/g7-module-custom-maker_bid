<?php

use Illuminate\Support\Facades\Route;
use Modules\Custom\MakerBid\Http\Controllers\JobController;

Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('jobs/{id}', [JobController::class, 'show'])->name('jobs.show');
Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');
Route::post('jobs/{id}/bids', [JobController::class, 'bid'])->name('jobs.bid');
Route::post('jobs/{id}/award', [JobController::class, 'award'])->name('jobs.award');

Route::prefix('admin')->group(function () {
    Route::get('jobs', [JobController::class, 'index'])->name('admin.jobs.index');
});
