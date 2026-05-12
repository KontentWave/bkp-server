<?php

use App\Http\Controllers\Api\FlatGalleryController;
use App\Http\Controllers\Api\AdminLogController;
use App\Http\Controllers\Api\AdminTokenController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\LandlordTokenController;
use App\Http\Controllers\Api\VerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/admins/tokens', [AdminTokenController::class, 'store']);
Route::post('/landlords/tokens', [LandlordTokenController::class, 'store']);
Route::post('/verify', [VerificationController::class, 'store']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/admin/logs', [AdminLogController::class, 'index']);
    Route::post('/browser/invitations', [InvitationController::class, 'storeFromBrowser']);
});

Route::middleware(['auth:sanctum', 'hardware.signature'])->group(function (): void {
    Route::post('/invitations', [InvitationController::class, 'store']);
    Route::get('/flats', [FlatGalleryController::class, 'index']);
    Route::get('/reported-escorts-summary', [FlatGalleryController::class, 'reportedEscortSummary']);
    Route::post('/flats', [FlatGalleryController::class, 'store']);
    Route::post('/flats/{flat}/photos', [FlatGalleryController::class, 'storePhoto']);
    Route::get('/photos/{photo}/content', [FlatGalleryController::class, 'showPhotoContent']);
    Route::delete('/photos/{photo}', [FlatGalleryController::class, 'destroyPhoto']);
    Route::post('/flats/{flat}/vote', [FlatGalleryController::class, 'vote']);
    Route::post('/flats/{flat}/report-landlord', [FlatGalleryController::class, 'reportLandlord']);
    Route::post('/flats/{flat}/report-escort', [FlatGalleryController::class, 'reportEscort']);

    Route::post('/protected/ping', function () {
        return response()->json(['ok' => true]);
    });
});
