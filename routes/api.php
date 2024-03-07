<?php

use App\Models\ImageCaptureStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HourlyStatusController;
use App\Http\Controllers\ImageCaptureStatusController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\LogFilesController;

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

Route::post('/hourly-status', [HourlyStatusController::class, 'SendHourlyStatus']);
Route::post('/image-capture-status', [ImageCaptureStatusController::class, 'CaptureStatus']);
Route::post('/send-log-files', [LogFilesController::class, 'SendLogFiles']);
Route::post('/upload-incremental', [FileUploadController::class, 'incrementalUpload']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
