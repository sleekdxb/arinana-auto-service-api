<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientAuthController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\FileUploadController;


//->middleware('verify.token')

// Clients Authenticate & authorize Endpoints
Route::prefix('client')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register']);
    Route::put('/reset_password', [ClientAuthController::class, 'reset_password']);
    Route::post('/login', [ClientAuthController::class, 'login']);
    Route::post('/logout', [ClientAuthController::class, 'logout'])->middleware('verify.token');
    Route::get('/getProfile', [ClientAuthController::class, 'getProfile']);
    Route::put('/updateProfile', [ClientAuthController::class, 'updateProfile']);
});



Route::prefix('twoFactor')->group(function () {
    Route::post('/generateOtp', [OtpController::class, 'generateOtp']);
    Route::post('/verifyOtp', [OtpController::class, 'verifyOtp']);
});


// Vehicle Clients Endpoints
Route::prefix('vehicles')->group(function () {
    Route::get('/getVehicle', [VehicleController::class, 'getVehicle']);
    Route::post('/addVehicle', [VehicleController::class, 'addVehicle']);
    Route::put('/updateVehicle', [VehicleController::class, 'updateVehicle']);
    Route::delete('/deleteVehicle', [VehicleController::class, 'deleteVehicle']);
});

Route::prefix('media')->group(function () {
    Route::post('/uploadFiles', [FileUploadController::class, 'uploadFiles']);
});




// Team Authenticate & authorize Endpoints


// OTP Services Endpoints

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
