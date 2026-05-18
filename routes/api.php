<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Services\FirebaseService;
use App\Http\Controllers\Api\BloodRequestController;
use App\Http\Controllers\Api\DonorController;

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Blood Connect API is working',
    ]);
});

Route::get('/sdk-firestore-test', function (FirebaseService $firebase) {
    $firebase->firestore()
        ->collection('sdk_test')
        ->document('connection')
        ->set([
            'message' => 'Firestore SDK is working',
            'status' => 'success',
            'created_at' => now()->toDateTimeString(),
        ]);

    return response()->json([
        'success' => true,
        'message' => 'Firestore SDK connected successfully.',
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/blood-requests', [BloodRequestController::class, 'store']);
Route::post('/donor-requests', [DonorController::class, 'store']);