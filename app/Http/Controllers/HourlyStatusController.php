<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HourlyStatus;

class HourlyStatusController extends Controller
{
    public function SendHourlyStatus(Request $request)
    {
        $hourlyStatus = HourlyStatus::create($request->all());
        return response()->json($hourlyStatus, 201); // Return the created object with a 201 status code
    }
}
