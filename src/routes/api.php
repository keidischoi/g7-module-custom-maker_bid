<?php

use Illuminate\Support\Facades\Route;
use Modules\Custom\MakerBids\Http\Controllers\Admin\BidAdminController;
use Modules\Custom\MakerBids\Http\Controllers\Admin\CompanyAdminController;
use Modules\Custom\MakerBids\Http\Controllers\Admin\JobAdminController;
use Modules\Custom\MakerBids\Http\Controllers\Admin\JobTypeAdminController;
use Modules\Custom\MakerBids\Http\Controllers\Admin\SettingsAdminController;
use Modules\Custom\MakerBids\Http\Controllers\AssetController;
use Modules\Custom\MakerBids\Http\Controllers\BidController;
use Modules\Custom\MakerBids\Http\Controllers\CompanyController;
use Modules\Custom\MakerBids\Http\Controllers\JobController;
use Modules\Custom\MakerBids\Http\Controllers\JobFileController;
use Modules\Custom\MakerBids\Http\Controllers\JobTypeController;
use Modules\Custom\MakerBids\Http\Controllers\SettingsController;

Route::get('assets/nav.js', [AssetController::class, 'nav'])
    ->middleware(['throttle:600,1'])
    ->name('assets.nav');
Route::get('assets/form.js', [AssetController::class, 'form'])
    ->middleware(['throttle:600,1'])
    ->name('assets.form');
Route::get('assets/page.js', [AssetController::class, 'page'])
    ->middleware(['throttle:600,1'])
    ->name('assets.page');
Route::get('assets/form.css', [AssetController::class, 'formCss'])
    ->middleware(['throttle:600,1'])
    ->name('assets.form.css');
Route::get('assets/admin.css', [AssetController::class, 'adminCss'])
    ->middleware(['throttle:600,1'])
    ->name('assets.admin.css');
Route::get('assets/admin.js', [AssetController::class, 'adminJs'])
    ->middleware(['throttle:600,1'])
    ->name('assets.admin.js');

Route::get('job-types', [JobTypeController::class, 'index'])
    ->middleware(['throttle:600,1'])
    ->name('job-types.index');
Route::get('files/{hash}', [JobFileController::class, 'download'])
    ->middleware(['throttle:600,1'])
    ->name('files.download');

Route::get('jobs', [JobController::class, 'index'])
    ->middleware(['optional.sanctum', 'throttle:600,1'])
    ->name('jobs.index');
Route::get('jobs/{id}', [JobController::class, 'show'])
    ->whereNumber('id')
    ->middleware(['optional.sanctum', 'throttle:600,1'])
    ->name('jobs.show');

Route::get('companies', [CompanyController::class, 'index'])
    ->middleware(['throttle:600,1'])
    ->name('companies.index');
Route::get('settings', [SettingsController::class, 'show'])
    ->middleware(['throttle:600,1'])
    ->name('settings.show');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('jobs/mine', [JobController::class, 'mine'])->name('jobs.mine');
    Route::get('jobs/form-defaults', [JobController::class, 'formDefaults'])->name('jobs.form-defaults');
    Route::get('jobs/{id}/viewer', [JobController::class, 'viewer'])
        ->whereNumber('id')
        ->name('jobs.viewer');
    Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');
    Route::patch('jobs/{id}', [JobController::class, 'update'])
        ->whereNumber('id')
        ->name('jobs.update');
    Route::post('uploads', [JobFileController::class, 'store'])->name('uploads.store');
    Route::post('jobs/{id}/files', [JobFileController::class, 'store'])
        ->whereNumber('id')
        ->name('jobs.files.store');
    Route::delete('uploads/{hash}', [JobFileController::class, 'destroy'])->name('uploads.destroy');
    Route::get('bids/mine', [BidController::class, 'mine'])->name('bids.mine');
    Route::post('jobs/{id}/bids', [BidController::class, 'store'])
        ->whereNumber('id')
        ->name('jobs.bids.store');
    Route::patch('jobs/{id}/bids/{bidId}', [BidController::class, 'update'])
        ->whereNumber('id')
        ->whereNumber('bidId')
        ->name('jobs.bids.update');
    Route::post('jobs/{id}/award', [JobController::class, 'award'])
        ->whereNumber('id')
        ->name('jobs.award');

    Route::get('companies/form-defaults', [CompanyController::class, 'formDefaults'])->name('companies.form-defaults');
    Route::post('companies', [CompanyController::class, 'store'])->name('companies.apply');
    Route::get('companies/me', [CompanyController::class, 'me'])->name('companies.me');
});
