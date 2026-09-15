<?php

use Illuminate\Support\Facades\Route;
use Modules\Custom\MakerBid\Http\Controllers\Admin\BidAdminController;
use Modules\Custom\MakerBid\Http\Controllers\Admin\CompanyAdminController;
use Modules\Custom\MakerBid\Http\Controllers\Admin\JobAdminController;
use Modules\Custom\MakerBid\Http\Controllers\AssetController;
use Modules\Custom\MakerBid\Http\Controllers\BidController;
use Modules\Custom\MakerBid\Http\Controllers\CompanyController;
use Modules\Custom\MakerBid\Http\Controllers\JobController;

/*
|--------------------------------------------------------------------------
| custom-maker_bid API Routes
|--------------------------------------------------------------------------
|
| Prefix is applied by ModuleRouteServiceProvider:
| - URL:  /api/modules/custom-maker_bid
| - Name: api.modules.custom-maker_bid.
|
*/

Route::get('assets/nav.js', [AssetController::class, 'nav'])
    ->middleware(['throttle:600,1'])
    ->name('assets.nav');

Route::get('jobs', [JobController::class, 'index'])
    ->middleware(['throttle:600,1'])
    ->name('jobs.index');
Route::get('jobs/{id}', [JobController::class, 'show'])
    ->whereNumber('id')
    ->middleware(['throttle:600,1'])
    ->name('jobs.show');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('jobs/mine', [JobController::class, 'mine'])->name('jobs.mine');
    Route::get('jobs/{id}/viewer', [JobController::class, 'viewer'])
        ->whereNumber('id')
        ->name('jobs.viewer');
    Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');
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

    Route::post('companies', [CompanyController::class, 'store'])->name('companies.apply');
    Route::get('companies/me', [CompanyController::class, 'me'])->name('companies.me');
});

Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:600,1'])->group(function () {
    Route::get('jobs', [JobAdminController::class, 'index'])
        ->middleware('permission:admin,custom-maker_bid.jobs.read')
        ->name('admin.jobs.index');
    Route::get('jobs/{id}', [JobAdminController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permission:admin,custom-maker_bid.jobs.read')
        ->name('admin.jobs.show');
    Route::patch('jobs/{id}', [JobAdminController::class, 'update'])
        ->whereNumber('id')
        ->middleware('permission:admin,custom-maker_bid.jobs.update')
        ->name('admin.jobs.update');
    Route::post('jobs/{id}/hold', [JobAdminController::class, 'hold'])
        ->whereNumber('id')
        ->middleware('permission:admin,custom-maker_bid.jobs.update')
        ->name('admin.jobs.hold');
    Route::post('jobs/{id}/cancel', [JobAdminController::class, 'cancel'])
        ->whereNumber('id')
        ->middleware('permission:admin,custom-maker_bid.jobs.update')
        ->name('admin.jobs.cancel');
    Route::delete('jobs/{id}', [JobAdminController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('permission:admin,custom-maker_bid.jobs.delete')
        ->name('admin.jobs.destroy');

    Route::get('bids', [BidAdminController::class, 'index'])
        ->middleware('permission:admin,custom-maker_bid.bids.read')
        ->name('admin.bids.index');
    Route::get('bids/{id}', [BidAdminController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permission:admin,custom-maker_bid.bids.read')
        ->name('admin.bids.show');
    Route::patch('bids/{id}', [BidAdminController::class, 'update'])
        ->whereNumber('id')
        ->middleware('permission:admin,custom-maker_bid.bids.update')
        ->name('admin.bids.update');
    Route::delete('bids/{id}', [BidAdminController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('permission:admin,custom-maker_bid.bids.delete')
        ->name('admin.bids.destroy');

    Route::get('companies', [CompanyAdminController::class, 'index'])
        ->middleware('permission:admin,custom-maker_bid.companies.read')
        ->name('admin.companies.index');
    Route::post('companies', [CompanyAdminController::class, 'store'])
        ->middleware('permission:admin,custom-maker_bid.companies.create')
        ->name('admin.companies.store');
    Route::post('companies/{id}/approve', [CompanyAdminController::class, 'approve'])
        ->whereNumber('id')
        ->middleware('permission:admin,custom-maker_bid.companies.update')
        ->name('admin.companies.approve');
    Route::post('companies/{id}/reject', [CompanyAdminController::class, 'reject'])
        ->whereNumber('id')
        ->middleware('permission:admin,custom-maker_bid.companies.update')
        ->name('admin.companies.reject');
    Route::delete('companies/{id}', [CompanyAdminController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('permission:admin,custom-maker_bid.companies.delete')
        ->name('admin.companies.destroy');
});
