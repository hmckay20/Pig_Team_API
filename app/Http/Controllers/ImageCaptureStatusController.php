<?php

namespace App\Http\Controllers;

use App\Models\ImageCaptureStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class ImageCaptureStatusController extends Controller
{
    public function CaptureStatus(Request $request)
    {
        \Log::info('File upload request received.');
        \Log::info('Files in request:', $request->allFiles());

        if ($request->hasFile('file')) {
            $validated = $request->validate([
                'file' => 'required|mimes:zip',
            ]);

            $file = $request->file('file');
            $destinationPath = 'C:/Users/hmmmc/OneDrive/Desktop/uploads';
            $filename = $file->getClientOriginalName();
            $fullPath = $destinationPath . '\\' . $filename;

            \Log::info("File found in request: $filename");

            try {
                \Log::info("Attempting to move file to $destinationPath");
                $file->move($destinationPath, $filename);
                \Log::info("File moved successfully.");

                // Process the zip file
                $zip = new ZipArchive;
                if ($zip->open($fullPath) === TRUE) {
                    $zip->extractTo($destinationPath);
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filePath = $zip->getNameIndex($i);
                        // Assuming SQL file has a specific extension or naming convention
                        if (pathinfo($filePath, PATHINFO_EXTENSION) == 'sql') {
                            $sqlContent = file_get_contents($destinationPath . '/' . $filePath);
                            DB::unprepared($sqlContent); // Execute the SQL file
                        }
                    }
                    $zip->close();
                    \Log::info("Zip file processed and SQL executed.");
                } else {
                    \Log::error("Failed to open zip file.");
                    return response()->json(['message' => 'Failed to process zip file'], 500);
                }

                return response()->json([
                    'message' => 'File uploaded and processed successfully',
                    'path' => $fullPath,
                ]);
            } catch (\Exception $e) {
                \Log::error("Error processing file: " . $e->getMessage());
                return response()->json(['message' => 'File upload and processing failed'], 500);
            }
        } else {
            \Log::warning('No file found in the request.');
            return response()->json(['message' => 'No file uploaded'], 400);
        }
    }
}
