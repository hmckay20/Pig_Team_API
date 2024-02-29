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

        $correctDate = FALSE;
        $correctCount = FALSE;

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
            // DO NOT SEND DATA IF NOT FROM YESTERDAY
            // sendPreviousIncremental($yesterdayDate);    
        } else {
            $correctDate = TRUE;
        }
            
        // This is the table from yesterday.
        // Check how many records were inserted in feeder_data_new yesterday
        $data_newServername = "servername"; // feeder_data_new credentials
        $data_newUsername = "username";
        $data_newPassword = "password";
        $data_newDatabase = "feeder_data_new";

        $data_newConn = new mysqli($data_newServername, $data_newUsername, $data_newPassword, $data_newDatabase);
        if ($data_newConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new failed: " . $data_newConn->connect_error);
        }
        $sql = "SELECT * FROM feeder_data_new WHERE date = '$yesterdayDate'";
        $result = $data_newConn->query($sql);
        
        $data_newConn->close();

        if ($result) {
            $data_newCount = $result->num_rows;
        } else {
            die("Error executing query in feeder_data_new: " . $data_newConn->connect_error);
        }

        // Now check how many records feeder_data_new_incremental contains
        $sqlDump = file_get_contents('sql_dump_file.sql'); // TODO: Change 'sql_dump_file.sql' to the path for the file

        preg_match_all("/INSERT INTO `feeder_data_new_incremental` VALUES \((.*?)\);/", $sqlDump, $matches);
        
        $recordCount = 0;
        foreach ($matches[1] as $insertValues) {
            $values = explode('),(', $insertValues);
            $recordCount += count($values);
        }

        // If there is no data in incremental, try populating it first
        if ($recordCount == 0 && data_newCount != 0) {
            $data_newServername = "servername"; // feeder_data_new credentials
            $data_newUsername = "username";
            $data_newPassword = "password";
            $data_newDatabase = "feeder_data_new";
    
            $data_newConn = new mysqli($data_newServername, $data_newUsername, $data_newPassword, $data_newDatabase);
            if ($data_newConn->connect_error) { // Verify connection
                die("Connection to feeder_data_new failed: " . $data_newConn->connect_error);
            }

            $incrementalServername = "servername"; // feeder_data_new_incremental credentials
            $incrementalUsername = "username";
            $incrementalPassword = "password";
            $incrementalDatabase = "feeder_data_new_incremental";
    
            $incrementalConn = new mysqli($incrementalServername, $incrementalUsername, $incrementalPassword, $incrementalDatabase);
            if ($incrementalConn->connect_error) { // Verify connection
                die("Connection to feeder_data_new_incremental failed: " . $incrementalConn->connect_error);
            }

            $sql = "INSERT INTO feeder_data_new_incremental SELECT * FROM feeder_data_new WHERE date LIKE '%$yesterdayDate%'";
            $result = $incrementalConn->query($sql);
            
            $data_newConn->close();
            $incrementalConn->close();

            if ($result) {
                echo "Data copied successfully.";
            } else {
                die("Error copying data: " . $incrementalConn->error);
            }
        }

        // Compare the counts
        if ($recordCount == $data_newCount) {
            $correctCount = TRUE;
        }

        if ($correctDate && $correctCount) { // SEND AWAY - Add data to respective tables
                   

        }

        // Return a response based on validation and storing result
        return response()->json(['message' => $message, 'records' => $recordCount, 'path' => $zipPath]); //TODO: Add a $zipPath if necessary
    }

    private function checkYesterdayData($yesterdayDate) 
    {
        // Check if yesterday’s incremental table is there. 
        
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

        if ($result->num_rows == 0) { // Yesterday's data was not sent and received
            // sendIncrementalToMaster($yesterdayDate); Do we need to do that first?
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