<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
// use Illuminate\Routing\Controller as BaseController;

class FileUploadController extends Controller
{
    public function incremental_upload(Request $request)
    {
        $message = 'File uploaded successfully';
        $sqlFileName = NULL;

        $request->validate([ // Quick validate
            'file' => 'required|mimes:zip',
        ]);
        
        // Validate the uploaded file
        $uploadedZipFile = $_FILES['zip_file']; // TODO: Change 'zip_file' to the real name of the file.
        $zip = new ZipArchive();

        if ($zip->open($uploadedZipFile['tmp_name']) === TRUE) {
            if ($zip->numFiles === 1) {
                $sqlFileName = $zip->getNameIndex(0);
            } else {
                $message = "Expected one file in the zip archive, found {$zip->numFiles} files.";
            }
            $zip->close();
        } else {
            $message = "Failed to open the zip file.";
        }

        // Is this today's data?
        $currentDate = date("Y-m-d");
        if (strstr($sqlFileName, $currentDate) !== false) {
            // This is today's data
        } else {
            // This is NOT today's data.
        }


        // Add data to respective tables
        

        // Return a response based on validation and storing result
        return response()->json(['message' => $message, 'path' => $zipPath]); //TODO: Add a $zipPath if necessary
    }
}