<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientAuthController;





// Clients Authenticate & authorize Endpoints
Route::prefix('client')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register']);
    Route::put('/reset_password', [ClientAuthController::class, 'reset_password']);
    Route::post('/login', [ClientAuthController::class, 'login']);
    Route::post('/logout', [ClientAuthController::class, 'logout']);
});


// Team Authenticate & authorize Endpoints


// OTP Services Endpoints

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
