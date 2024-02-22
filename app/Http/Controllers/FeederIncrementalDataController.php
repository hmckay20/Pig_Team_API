<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
// use Illuminate\Routing\Controller as BaseController;

class FileUploadController extends Controller
{
    public function incrementalUpload(Request $request)
    {
        $message = 'File uploaded successfully';
        $sqlFileName = NULL;

        $request->validate([ // Quick validate
            'file' => 'required|mimes:zip',
        ]);
        
        // Validate the uploaded file
        $uploadedZipFile = $_FILES['zip_file']; // TODO: Change 'zip_file' to the real name of the file?
        $zip = new ZipArchive();

        if ($zip->open($uploadedZipFile['tmp_name']) == TRUE) {
            if ($zip->numFiles == 1) {
                $sqlFileName = $zip->getNameIndex(0);
            } else {
                die("Expected one file in the zip archive, found {$zip->numFiles} files.");
            }
            $zip->close();
        } else {
            die("Failed to open the zip file.");
        }

        // Is this the table from yesterday?
        $yesterdayDate = date("Y-m-d", strtotime("-1 day"));
        if (strstr($sqlFileName, $yesterdayDate) == false) { // This is NOT the table from yesterday.
            
            sendPreviousIncremental($yesterdayDate);
            
        }
            
        // This is the table from yesterday.
        // Check how many records were inserted in feeder_data_new yesterday
        $sqlDump = file_get_contents('sql_dump_file.sql'); // TODO: Change 'sql_dump_file.sql' to the path for the file

        preg_match_all("/INSERT INTO `feeder_data_new_incremental` VALUES \((.*?)\);/", $sqlDump, $matches);
        
        $recordCount = 0;
        foreach ($matches[1] as $insertValues) {
            $values = explode('),(', $insertValues);
            $recordCount += count($values);
        }

        // If there is no data, populate it first
        if ($recordCount == 0) {
            // TODO: Implement code to populate incremental table
        }

        // SEND AWAY


   

        // Add data to respective tables
        

        // Return a response based on validation and storing result
        return response()->json(['message' => $message, 'records' => $recordCount, 'path' => $zipPath]); //TODO: Add a $zipPath if necessary
    }

    private function sendPreviousIncremental($yesterdayDate) 
    {
        // Send yesterday’s incremental table if it is not there. 
        
        $idsServername = "servername"; // feeder_data_ids credentials
        $idsUsername = "username";
        $idsPassword = "password";
        $idsDatabase = "feeder_data_ids";

        $idsConn = new mysqli($idsServername, $idsUsername, $idsPassword, $idsDatabase);
        if ($idsConn->connect_error) { // Verify connection
            die("Connection to feeder_data_ids failed: " . $idsConn->connect_error);
        }
        $sql = "SELECT * FROM feeder_data_ids WHERE date = '$yesterdayDate'";
        $result = $idsConn->query($sql);

        $idsConn->close();

        if ($result->num_rows == 0) { // Yesterday's data was not sent and received, you need to do that first
            sendIncrementalToMaster($yesterdayDate);
        }
    }

    private function sendIncrementalToMaster($date) {
        

    }

    private function populateFeederDataIds() {

        $idsServername = "servername"; // feeder_data_ids credentials
        $idsUsername = "username";
        $idsPassword = "password";
        $idsDatabase = "feeder_data_ids";

        $idsConn = new mysqli($idsServername, $idsUsername, $idsPassword, $idsDatabase);
        if ($idsConn->connect_error) { // Verify connection
            die("Connection to feeder_data_ids failed: " . $idsConn->connect_error);
        }
        $sql = "SELECT * FROM feeder_data_ids WHERE date = '$yesterdayDate'";
        $result = $idsConn->query($sql);

        $idsConn->close();

        // Implementation to populate feeder_data_ids table with the given date
    }

}