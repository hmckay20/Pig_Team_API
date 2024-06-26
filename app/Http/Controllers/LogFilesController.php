<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;

class LogFilesController extends Controller
{
    public function SendLogFiles(Request $request)
    {
        \Log::info('File upload request received.');
        \Log::info('Files in request:', $request->allFiles());

        if ($request->hasFile('file')) {
            if (
                $request->validate([ // Quick validate
                    'file' => 'required|mimes:zip',
                ])
            ) {

                $file = $request->file('file');
                $destinationPath = 'C:/Users/hmmmc/OneDrive/Desktop/uploads';
                $filename = $file->getClientOriginalName();

                \Log::info("File found in request: $filename");

                try {
                    \Log::info("Attempting to move file to $destinationPath");
                    $file->move($destinationPath, $filename);
                    \Log::info("File moved successfully.");

                    return response()->json([
                        'message' => 'File uploaded successfully',
                        'path' => $destinationPath . '\\' . $filename,
                    ]);
                } catch (\Exception $e) {
                    \Log::error("Error moving file: " . $e->getMessage());
                    return response()->json(['message' => 'File upload failed'], 500);
                }
            }
        } else {
            \Log::warning('No file found in the request.');
            return response()->json(['message' => 'No file uploaded'], 400);
        }

    }

}
