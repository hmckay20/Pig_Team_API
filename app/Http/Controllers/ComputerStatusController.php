<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ComputerStatus;

class ComputerStatusController extends Controller
{
    public function store(Request $request)
    {
        $computerStatus = ComputerStatus::create($request->all());
        return response()->json($computerStatus, 201); // Return the created object with a 201 status code
    }
}
