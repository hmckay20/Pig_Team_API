
<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use App\Models\DataSentLog; // Assuming you have a model for your table
use Illuminate\Support\Facades\Http;

class SendLocalSentLogController extends Controller
{
    public function sendLocalSentLog(Request $request)
    {
        // Fetch data from the local table
        $logs = DataSentLog::where('data_sent', false)->get();

        // Prepare the data to be sent
        $dataToSend = $logs->map(function ($log) {
            return [
                'data_date' => $log->data_date,
                'sent_date' => $log->set_date,
                'data_sent' => $log->data_sent,
            ];
        });

        // Send data to the server
        $response = Http::post('http://server-api-url/api/data_sent_log', [
            'logs' => $dataToSend
        ]);

        // Handle the response
        if ($response->successful()) {
            // Optionally mark logs as sent if the server acknowledges
            foreach ($logs as $log) {
                $log->data_sent = true;
                $log->save();
            }

            return response()->json(['message' => 'Data sent successfully', 'status' => true]);
        }

        return response()->json(['message' => 'Failed to send data', 'status' => false], 500);
    }
}
