<?php

namespace App\Http\Controllers;

use App\Models\ImageCaptureStatus;
use Illuminate\Http\Request;

class ImageCaptureStatusController extends Controller

{
  public function store(Request $request)
  {
    $imageCaptureStatus = ImageCaptureStatus::create($request->all());
    return response()->json($imageCaptureStatus, 201);
  }
}
