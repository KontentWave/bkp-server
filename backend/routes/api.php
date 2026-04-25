<?php

use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\LandlordTokenController;
use App\Http\Controllers\Api\VerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/landlords/tokens', [LandlordTokenController::class, 'store']);
Route::post('/verify', [VerificationController::class, 'store']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/browser/invitations', [InvitationController::class, 'storeFromBrowser']);
});

Route::middleware(['auth:sanctum', 'hardware.signature'])->group(function (): void {
    Route::post('/invitations', [InvitationController::class, 'store']);

    Route::post('/protected/ping', function () {
        return response()->json(['ok' => true]);
    });
});
