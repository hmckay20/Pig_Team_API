<?php

use App\Models\ImageCaptureStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HourlyStatusController;
use App\Http\Controllers\ImageCaptureStatusController;
use App\Http\Controllers\FeederIncrementalDataController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::post('/hourly-status', [HourlyStatusController::class, 'store']);
Route::post('/image-capture-status', [ImageCaptureStatusController::class, 'store']);
Route::post('/upload-file', [FeederIncrementalDataController::class, 'upload']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
