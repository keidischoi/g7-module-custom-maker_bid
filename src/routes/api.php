<?php

use Illuminate\Support\Facades\Route;
use Modules\Custom\MakerBids\Http\Controllers\Admin\BidAdminController;
use Modules\Custom\MakerBids\Http\Controllers\Admin\CompanyAdminController;
use Modules\Custom\MakerBids\Http\Controllers\Admin\JobAdminController;
use Modules\Custom\MakerBids\Http\Controllers\Admin\JobTypeAdminController;
use Modules\Custom\MakerBids\Http\Controllers\Admin\MarketplaceAdminController;
use Modules\Custom\MakerBids\Http\Controllers\Admin\SettingsAdminController;
use Modules\Custom\MakerBids\Http\Controllers\AssetController;
use Modules\Custom\MakerBids\Http\Controllers\BidController;
use Modules\Custom\MakerBids\Http\Controllers\CompanyController;
use Modules\Custom\MakerBids\Http\Controllers\JobController;
use Modules\Custom\MakerBids\Http\Controllers\JobFileController;
use Modules\Custom\MakerBids\Http\Controllers\JobTypeController;
use Modules\Custom\MakerBids\Http\Controllers\MarketplaceController;
use Modules\Custom\MakerBids\Http\Controllers\SettingsController;

Route::get('assets/nav.js', [AssetController::class, 'nav'])->middleware(['throttle:600,1'])->name('assets.nav');
Route::get('assets/form.js', [AssetController::class, 'form'])->middleware(['throttle:600,1'])->name('assets.form');
Route::get('assets/existing-files.js', [AssetController::class, 'existingFiles'])->middleware(['throttle:600,1'])->name('assets.existing');
Route::get('assets/page.js', [AssetController::class, 'page'])->middleware(['throttle:600,1'])->name('assets.page');
Route::get('assets/form.css', [AssetController::class, 'formCss'])->middleware(['throttle:600,1'])->name('assets.form.css');
Route::get('assets/admin.css', [AssetController::class, 'adminCss'])->middleware(['throttle:600,1'])->name('assets.admin.css');
Route::get('assets/admin.js', [AssetController::class, 'adminJs'])->middleware(['throttle:600,1'])->name('assets.admin.js');

Route::get('job-types', [JobTypeController::class, 'index'])->middleware(['throttle:600,1'])->name('job-types.index');
Route::get('files/{hash}', [JobFileController::class, 'download'])->middleware(['throttle:600,1'])->name('files.download');
Route::get('jobs', [JobController::class, 'index'])->middleware(['optional.sanctum', 'throttle:600,1'])->name('jobs.index');
Route::get('jobs/{id}', [JobController::class, 'show'])->whereNumber('id')->middleware(['optional.sanctum', 'throttle:600,1'])->name('jobs.show');
Route::get('jobs/{id}/viewer', [JobController::class, 'viewer'])->whereNumber('id')->middleware(['optional.sanctum', 'throttle:600,1'])->name('jobs.viewer');
Route::post('jobs/{id}/bids', [BidController::class, 'store'])->whereNumber('id')->middleware(['optional.sanctum', 'throttle:60,1'])->name('jobs.bids.store');
Route::patch('jobs/{id}/bids/{bidId}', [BidController::class, 'update'])->whereNumber('id')->whereNumber('bidId')->middleware(['optional.sanctum', 'throttle:60,1'])->name('jobs.bids.update');
Route::get('companies', [CompanyController::class, 'index'])->middleware(['throttle:600,1'])->name('companies.index');
Route::get('settings', [SettingsController::class, 'show'])->middleware(['throttle:600,1'])->name('settings.show');
Route::post('jobs/close-expired', [MarketplaceController::class, 'closeExpired'])->middleware(['throttle:30,1'])->name('jobs.closeExpired');
Route::post('jobs/run-schedule', [MarketplaceController::class, 'runSchedule'])->middleware(['throttle:30,1'])->name('jobs.runSchedule');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('jobs/mine', [JobController::class, 'mine'])->name('jobs.mine');
    Route::get('jobs/form-defaults', [JobController::class, 'formDefaults'])->name('jobs.form-defaults');
    Route::get('jobs/{id}/edit', [JobController::class, 'editData'])->whereNumber('id')->name('jobs.edit');
    Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');
    Route::patch('jobs/{id}', [JobController::class, 'update'])->whereNumber('id')->name('jobs.update');
    Route::post('uploads', [JobFileController::class, 'store'])->name('uploads.store');
    Route::post('jobs/{id}/files', [JobFileController::class, 'store'])->whereNumber('id')->name('jobs.files.store');
    Route::delete('uploads/{hash}', [JobFileController::class, 'destroy'])->name('uploads.destroy');
    Route::get('bids/mine', [BidController::class, 'mine'])->name('bids.mine');
    Route::post('jobs/{id}/award', [JobController::class, 'award'])->whereNumber('id')->name('jobs.award');
    Route::get('companies/form-defaults', [CompanyController::class, 'formDefaults'])->name('companies.form-defaults');
    Route::post('companies', [CompanyController::class, 'store'])->name('companies.apply');
    Route::get('companies/me', [CompanyController::class, 'me'])->name('companies.me');
    Route::get('notices', [MarketplaceController::class, 'notices'])->name('notices.index');
    Route::post('notices/{id}/read', [MarketplaceController::class, 'readNotice'])->whereNumber('id')->name('notices.read');
    Route::get('jobs/{id}/compare', [MarketplaceController::class, 'compare'])->whereNumber('id')->name('jobs.compare');
    Route::post('jobs/{id}/bids/{bidId}/reject', [MarketplaceController::class, 'rejectBid'])->whereNumber('id')->whereNumber('bidId')->name('jobs.bids.reject');
    Route::post('jobs/{id}/complete', [MarketplaceController::class, 'complete'])->whereNumber('id')->name('jobs.complete');
    Route::post('jobs/{id}/work', [MarketplaceController::class, 'work'])->whereNumber('id')->name('jobs.work');
    Route::get('jobs/{id}/messages', [MarketplaceController::class, 'messages'])->whereNumber('id')->name('jobs.messages');
    Route::post('jobs/{id}/messages', [MarketplaceController::class, 'postMessage'])->whereNumber('id')->name('jobs.messages.store');
    Route::post('jobs/{id}/claim', [MarketplaceController::class, 'claim'])->whereNumber('id')->name('jobs.claim');
    Route::post('jobs/{id}/report', [MarketplaceController::class, 'report'])->whereNumber('id')->name('jobs.report');
    Route::get('jobs/{id}/export', [MarketplaceController::class, 'export'])->whereNumber('id')->name('jobs.export');
    Route::get('jobs/{id}/reviews', [MarketplaceController::class, 'reviews'])->whereNumber('id')->name('jobs.reviews');
    Route::get('companies/{id}/reviews', [MarketplaceController::class, 'companyReviews'])->whereNumber('id')->name('companies.reviews');
});
