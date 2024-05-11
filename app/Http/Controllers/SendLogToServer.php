<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataSentLog; // Assuming this is your Eloquent model

class SendLogToServer extends Controller
{

    public function store(Request $request)
    {
        $data = $request->validate([
            'data_date' => 'required|date',
            'sent_date' => 'required|date',
            'data_sent' => 'required|boolean',
        ]);

        // Create a new log entry in the database
        $log = DataSentLog::create([
            'data_date' => $data['data_date'],
            'sent_date' => $data['sent_date'],
            'data_sent' => $data['data_sent'],
        ]);

        // Check if the log was successfully created
        if ($log) {
            return response()->json([
                'message' => 'Log entry created successfully',
                'log' => $log
            ], 201);
        }

        // Handle the error if the log entry was not created
        return response()->json([
            'message' => 'Failed to create log entry'
        ], 500);
    }
}
