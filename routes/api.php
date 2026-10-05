<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientAuthController;
use App\Http\Controllers\OtpController;


//->middleware('verify.token')

// Clients Authenticate & authorize Endpoints
Route::prefix('client')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register']);
    Route::put('/reset_password', [ClientAuthController::class, 'reset_password']);
    Route::post('/login', [ClientAuthController::class, 'login']);
    Route::post('/logout', [ClientAuthController::class, 'logout'])->middleware('verify.token');
});



Route::prefix('twoFactor')->group(function () {
    Route::post('/generateOtp', [OtpController::class, 'generateOtp']);
    Route::post('/verifyOtp', [OtpController::class, 'verifyOtp']);
});




// Team Authenticate & authorize Endpoints


// OTP Services Endpoints

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
