<?php

use Illuminate\Support\Facades\Route;
use Modules\Custom\MakerBids\Http\Controllers\BidController;

Route::middleware(['web'])->group(function () {
    Route::post('/maker-bids/jobs/{id}/bid-save', [BidController::class, 'store'])->whereNumber('id')->name('maker-bids.bid-save');
    Route::post('/maker-bids/jobs/{id}/bids/{bidId}/bid-save', [BidController::class, 'update'])->whereNumber('id')->whereNumber('bidId')->name('maker-bids.bid-update');
});
