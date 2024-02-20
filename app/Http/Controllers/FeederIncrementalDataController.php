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
        $uploadedZipFile = $_FILES['zip_file']; // TODO: Change 'zip_file' to the real name of the file?
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

        // Is this the table from yesterday?
        $yesterdayDate = date("Y-m-d", strtotime("-1 day"));
        if (strstr($sqlFileName, $yesterdayDate) !== false) {
            // This is
            // Check the number of records
            $sqlDump = file_get_contents('sql_dump_file.sql'); // TODO: Change 'sql_dump_file.sql' to the path for the file

            preg_match_all("/INSERT INTO `feeder_data_new_incremental` VALUES \((.*?)\);/", $sqlDump, $matches);
            
            $recordCount = 0;
            foreach ($matches[1] as $insertValues) {
                $values = explode('),(', $insertValues);
                $recordCount += count($values);
            }

            // If there is no data, populate it first
            // SEND AWAY
        } else {
            // This is NOT
            // Send yesterday’s incremental table first. 


        // Add data to respective tables
        

        // Return a response based on validation and storing result
        return response()->json(['message' => $message, 'records' => $recordCount, 'path' => $zipPath]); //TODO: Add a $zipPath if necessary
    }
}