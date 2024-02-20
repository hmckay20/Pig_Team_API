<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
// use Illuminate\Routing\Controller as BaseController;

class FileUploadController extends Controller
{
    public function capture_status_upload(Request $request)
    {
        $message = 'File uploaded successfully';

        $request->validate([
            'file' => 'required|mimes:zip',
        ]);
        
        $uploadedZipFile = $_FILES['zip_file']; // TODO: Change 'zip_file' to the real name of the file?
        $zip = new ZipArchive();

        if ($zip->open($uploadedZipFile['tmp_name']) === TRUE) {
            if ($zip->numFiles === 1) {
              // We found the capture status table. Now what?


            } else {
                $message = "Expected one file in the zip archive, found {$zip->numFiles} files.";
            }
            $zip->close();
        } else {
            $message = "Failed to open the zip file.";
        }

        return response()->json(['message' => $message, 'path' => $zipPath]); //TODO: Add a $zipPath if necessary
    }
}