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

        // Was yesterday's incremental table sent and received? If not, do that first.
        checkPreviousData($message, $yesterdayDate);

        // Is this the table from yesterday?
        $yesterdayDate = date("Y-m-d", strtotime("-1 day"));
        // This is NOT the table from yesterday.
        if (strstr($sqlFileName, $yesterdayDate) == false) { 
            // DO NOT SEND DATA IF NOT FROM YESTERDAY. The .zip submitted to this API contains the wrong day's data.
            die("The incremental data inside this .zip file is from a date before yesterday.");
        } else {
            $correctDate = TRUE;
        }
            
        // This is the table from yesterday.
        // Check how many records were inserted in feeder_data_new yesterday
        $data_newServername = "servername"; // feeder_data_new credentials
        $data_newUsername = "username";
        $data_newPassword = "password";
        $data_newDatabase = "feeder_data_new";

        $data_newConn = new \MySQLi($data_newServername, $data_newUsername, $data_newPassword, $data_newDatabase);
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
        $sqlDump = file_get_contents('sql_dump_file.sql'); // TODO: Change 'sql_dump_file.sql' to the path for the file?

        preg_match_all("/INSERT INTO `feeder_data_new_incremental` VALUES \((.*?)\);/", $sqlDump, $matches);
        
        $recordCount = 0;
        foreach ($matches[1] as $insertValues) {
            $values = explode('),(', $insertValues);
            $recordCount += count($values);
        }

        // If there is no data in incremental, try populating it first
        if ($recordCount == 0 && data_newCount != 0) {
            copyDataToIncremental($yesterdayDate);
        }

        if ($recordCount == $data_newCount) { // Compare the counts
            $correctCount = TRUE;
        }

        if ($correctDate && $correctCount) { // SEND AWAY - Add data to respective tables
            sendCurrentToMaster($sqlDump);
            populateFeederDataIds($message, $yesterdayDate, $matches, $recordCount);
            clearIncremental();
        }

        // Return a response based on validation and storing result
        return response()->json(['message' => $message, 'records' => $recordCount]);
    }


    private function updatePreviousData($message, $date){

        copyDataToIncremental($date);
        sendPreviousIncrementalToMaster($date);
        populateFeederDataIds($message, $yesterdayDate, $matches, $recordCount);
        clearIncremental();
        echo "Data from $date updated to master. ";
        $message .= "Data from $date updated to master. ";

    }


    private function sendPreviousIncrementalToMaster($date){

        $incrementalServername = "servername"; // feeder_data_new_incremental credentials
        $incrementalUsername = "username";
        $incrementalPassword = "password";
        $incrementalDatabase = "feeder_data_new_incremental";

        $incrementalConn = new \MySQLi($incrementalServername, $incrementalUsername, $incrementalPassword, $incrementalDatabase);
        if ($incrementalConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new_incremental failed: " . $incrementalConn->connect_error);
        }

        $masterServername = "servername"; // master credentials
        $masterUsername = "username";
        $masterPassword = "password";
        $masterDatabase = "feeder_data_new";

        $masterConn = new \MySQLi($masterServername, $masterUsername, $masterPassword, $masterDatabase);
        if ($masterConn->connect_error) {
            die("Connection to master failed: " . $masterConn->connect_error);
        }

        $sql = "INSERT INTO feeder_data_new SELECT * FROM feeder_data_new_incremental WHERE date LIKE '%$date%'";
        $result = $incrementalConn->query($sql);
        
        $incrementalConn->close();
        $masterConn->close();

    }


    private function checkPreviousData($message, $date)
    {
        // Check if the previous day's incremental table has been updated. 
        
        $truthServername = "servername"; // master credentials
        $truthUsername = "username";
        $truthPassword = "password";
        $truthDatabase = "data_update_true";

        $truthConn = new \MySQLi($truthServername, $truthUsername, $truthPassword, $truthDatabase);
        if ($truthConn->connect_error) {
            die("Connection to data_update_true failed: " . $truthConn->connect_error);
        }

        $sql = "SELECT * FROM data_update_true WHERE date = '$date' AND updated = 1";
        $result = $truthConn->query($sql);

        $truthConn->close();

        if ($result->num_rows == 0) {
            echo "Data from $date not found. ";
            $message .= "Data from $date has been found missing. ";
            
            $previousDate = date('Y-m-d', strtotime($date . ' -1 day'));
            
            $validity = checkPreviousData($message, $previousDate);
            if ($validity) {
                updatePreviousData($message, $previousDate);
                return TRUE;
            }

        } else {
            echo "Data last updated on $date. ";
            $message .= "Data last updated on $date. ";
            return TRUE;
        }
        return FALSE;
    }


    private function copyDataToIncremental($date) {

        $data_newServername = "servername"; // feeder_data_new credentials
        $data_newUsername = "username";
        $data_newPassword = "password";
        $data_newDatabase = "feeder_data_new";

        $data_newConn = new \MySQLi($data_newServername, $data_newUsername, $data_newPassword, $data_newDatabase);
        if ($data_newConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new failed: " . $data_newConn->connect_error);
        }

        $incrementalServername = "servername"; // feeder_data_new_incremental credentials
        $incrementalUsername = "username";
        $incrementalPassword = "password";
        $incrementalDatabase = "feeder_data_new_incremental";

        $incrementalConn = new \MySQLi($incrementalServername, $incrementalUsername, $incrementalPassword, $incrementalDatabase);
        if ($incrementalConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new_incremental failed: " . $incrementalConn->connect_error);
        }

        $sql = "INSERT INTO feeder_data_new_incremental SELECT * FROM feeder_data_new WHERE date LIKE '%$date%'";
        $result = $incrementalConn->query($sql);
        
        $data_newConn->close();
        $incrementalConn->close();

        if ($result) {
            echo "Incremental table updated successfully.";
        } else {
            die("Error copying data from feeder_data_new to feeder_data_new_incremental: " . $incrementalConn->error);
        }

    }


    private function sendCurrentToMaster($sqlDump, $message) {

        $masterServername = "servername"; // master credentials
        $masterUsername = "username";
        $masterPassword = "password";
        $masterDatabase = "feeder_data_new";

        $masterConn = new \MySQLi($masterServername, $masterUsername, $masterPassword, $masterDatabase);
        if ($masterConn->connect_error) {
            die("Connection to master failed: " . $masterConn->connect_error);
        }

        $sql_queries = explode(';', $sqlDump);
        foreach ($sql_queries as $sql) {
            if (trim($sql) != '') { // If there is nothing to add, do not.

                if ($masterConn->query($sql) != TRUE) {
                    die("Error executing query while adding incremental data to master: " . $masterConn->error);
                }

            }
        }
        $masterConn->close();
        $message = "Successfully sent incremental data to master. ";
    }


    private function populateFeederDataIds($message, $yesterdayDate, $matches, $recordCount, $sqlDump) {

        $sqlStatements = explode(';', $sqlDump); // Find id_first and id_last
        $ids = array();

        foreach ($sqlStatements as $sql) {
            if (trim($sql) != '') {
                $parts = explode(',', $sql);
                $id = trim($parts[0]);
                $ids[] = $id;
            }
        }

        $id_first = reset($ids);
        $id_last = end($ids);

        $updated = date('Y-m-d H:i:s');

        $unique_rfid_values = []; // Find unique rfid values

        foreach ($matches[1] as $insert_values) {
            preg_match_all('/"([^"]+)"/', $insert_values, $values);
            $rfid = $values[1][4];
            if (!in_array($rfid, $unique_rfid_values)) {
                $unique_rfid_values[] = $rfid;
            }
        }

        $cnt_rfids = count($unique_rfid_values);

        $idsServername = "servername"; // feeder_data_ids credentials
        $idsUsername = "username";
        $idsPassword = "password";
        $idsDatabase = "feeder_data_ids";

        $idsConn = new \MySQLi($idsServername, $idsUsername, $idsPassword, $idsDatabase);
        if ($idsConn->connect_error) { // Verify connection
            die("Connection to feeder_data_ids failed: " . $idsConn->connect_error);
        }
        $sql = "SELECT * FROM feeder_data_ids WHERE date = '$yesterdayDate'";
        $result = $idsConn->query($sql);

        $sql = "INSERT INTO feeder_data_ids (`date`, `id_first`, `id_last`, `totalreads`, `cnt_rfids`, `updated`) VALUES ('$yesterdayDate', '$id_first', '$id_last', '$recordCount', '$cnt_rfids', '$updated')";
    
        if ($idsConn->query($sql) === TRUE) {
            $message .= "Successfully updated feeder_data_ids. ";
        } else {
            die("Update to feeder_data_ids failed: " . $idsConn->connect_error);
        }
        
        $idsConn->close();
    }


    private function clearIncremental() {
        $incrementalServername = "servername"; // feeder_data_new_incremental credentials
        $incrementalUsername = "username";
        $incrementalPassword = "password";
        $incrementalDatabase = "feeder_data_new_incremental";

        $incrementalConn = new \MySQLi($incrementalServername, $incrementalUsername, $incrementalPassword, $incrementalDatabase);
        if ($incrementalConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new_incremental failed: " . $incrementalConn->connect_error);
        }

        $sql = "DELETE FROM feeder_data_new_incremental";
        
        if ($incrementalConn->query($sql) != TRUE) {
            die("Error clearing incremental table: " . $incrementalConn->error);
        }
        
        echo "Incremental table cleared. ";

        $incrementalConn->close();
    }

}