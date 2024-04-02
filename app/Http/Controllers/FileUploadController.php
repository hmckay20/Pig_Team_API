<?php

namespace App\Http\Controllers;

use ZipArchive;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;



class FileUploadController extends Controller
{
    public function incrementalUpload(Request $request)
    {
        echo "Incremental table cleared. ";
        Log::info('Start of incremental');
        $message = 'File uploaded successfully';
        $sqlFileName = NULL;
        $correctDate = FALSE;
        $correctCount = FALSE;

       //$request->validate([ // Quick validate
       //     'file' => 'required|mimes:zip',
        //]);

        Log::info('I have validated the file ');
        if ($request->hasFile('zip_file')) {
            $uploadedZipFile = $request->file('zip_file');
            $zip = new ZipArchive();

            if ($zip->open($uploadedZipFile->getRealPath()) === TRUE) {
                if ($zip->numFiles == 1) {
                    // Retrieve the name of the file inside the zip
                    $sqlFileName = $zip->getNameIndex(0);
                    Log::info("Zip file opened successfully. SQL file name: $sqlFileName");

                    // Read the content of the SQL file from the zip
                    $sqlDump = $zip->getFromName($sqlFileName);
                    if ($sqlDump === false) {
                        Log::error("Failed to read the content of the SQL file.");
                        return response()->json(['error' => 'Failed to read the content of the SQL file.'], 400);
                    }
                } else {
                    Log::error("Expected one file in the zip archive, found {$zip->numFiles} files.");
                    return response()->json(['error' => "Expected one file in the zip archive, found {$zip->numFiles} files."], 400);
                }
                $zip->close();
            } else {
                Log::error("Failed to open the zip file.");
                return response()->json(['error' => 'Failed to open the zip file.'], 400);
            }
        } else {
            Log::error('No zip file uploaded or file does not meet the validation criteria.');
            return response()->json(['error' => 'No zip file uploaded or file does not meet the validation criteria.'], 400);
        }
        Log::info('fully validated the file ');
        // Is this the table from yesterday?
        $yesterdayDate = date("Y-m-d", strtotime("-1 day"));
        // This is NOT the table from yesterday.
        if (strstr($sqlFileName, $yesterdayDate) == false) {
            // DO NOT SEND DATA IF NOT FROM YESTERDAY
            // Do action
            $correctDate = TRUE;
        } else {
            $correctDate = TRUE;
        }
        Log::info('I have validated that this file is from yesterday');
        // This is the table from yesterday.
        // Check how many records were inserted in feeder_data_new yesterday
        $data_newServername = "127.0.0.1"; // feeder_data_new credentials
        $data_newUsername = "root";
        $data_newPassword = "Sun84Mus";
        $data_newDatabase = "pig_team_server_sql";

        $data_newConn = new \MySQLi($data_newServername, $data_newUsername, $data_newPassword, $data_newDatabase);
        if ($data_newConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new failed: " . $data_newConn->connect_error);
        }
        $sql = "SELECT * FROM feeder_data_new WHERE id = '$yesterdayDate'";
        $result = $data_newConn->query($sql);

        $data_newConn->close();

        if ($result) {
            $data_newCount = $result->num_rows;
        } else {

            die("Error executing query in feeder_data_new: " . $data_newConn->connect_error);
        }

        // Now check how many records feeder_data_new_incremental contains
  // TODO: Change 'sql_dump_file.sql' to the path for the file?

        preg_match_all("/INSERT INTO `feeder_data_new_incremental` VALUES \((.*?)\);/", $sqlDump, $matches);
        $matchCount = count($matches[1]); // $matches[0] contains the full pattern matches
        Log::info("Number of matches found: {$matchCount}");
        $recordCount = 0;
        foreach ($matches[1] as $insertValues) {
            $values = explode('),(', $insertValues);
            $recordCount += count($values);
        }

        // If there is no data in incremental, try populating it first

        if ($matchCount == 0 && $data_newCount != 0) {
            $data_newServername = "127.0.0.1"; // feeder_data_new credentials
            $data_newUsername = "root";
            $data_newPassword = "Sun84Mus";
            $data_newDatabase = "feeder_data_new";

            $data_newConn = new \MySQLi($data_newServername, $data_newUsername, $data_newPassword, $data_newDatabase);
            if ($data_newConn->connect_error) { // Verify connection
                die("Connection to feeder_data_new failed: " . $data_newConn->connect_error);
            }

            $incrementalServername = "127.0.0.1"; // feeder_data_new_incremental credentials
            $incrementalUsername = "root";
            $incrementalPassword = "Sun84Mus";
            $incrementalDatabase = "feeder_data_new_incremental";

            $incrementalConn = new \MySQLi($incrementalServername, $incrementalUsername, $incrementalPassword, $incrementalDatabase);
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

        if ($matchCount == 3) { // Compare the counts
            $correctCount = TRUE;
        }

        if ($correctDate && $correctCount) { // SEND AWAY - Add data to respective tables
          $this->sendIncrementalToMaster($sqlDump, $message);
          $this->populateFeederDataIds($message, $yesterdayDate, $matches, $recordCount, $sqlDump);
          $this->clearIncremental();
        }
        Log::info('right before I send response');
        // Return a response based on validation and storing result
     //  return response()->json(['message' => $message, 'records' => $recordCount]); //TODO: Add a $zipPath if necessary
        return response()->json(['I worked']);
    }


    private function checkPreviousData($previousDate) // FIX
    {
        // Check if the previous day's incremental table is there.

        $masterServername = "127.0.0.1"; // master credentials
        $masterUsername = "root";
        $masterPassword = "Sun84Mus";
        $masterDatabase = "feeder_data_new";

        $masterConn = new \MySQLi($masterServername, $masterUsername, $masterPassword, $masterDatabase);
        if ($masterConn->connect_error) {
            die("Connection to master failed: " . $masterConn->connect_error);
        }

        $sql = "SELECT * FROM feeder_data_new WHERE date = '$previousDate'";
        $result = $masterConn->query($sql);

        $masterConn->close();

        if ($result->num_rows == 0) { // Yesterday's data was not sent and received
            // ACTION
        }
        return response()->json(['Check previous data done']);
    }


    private function sendIncrementalToMaster($sqlDump, $message)
    {

        $masterServername = "127.0.0.1"; // master credentials
        $masterUsername = "root";
        $masterPassword = "Sun84Mus";
        $masterDatabase = "pig_team_server_sql";

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
        $message .= "Successfully sent incremental data to master. ";

        return response()->json(['sent incremental to master']);
    }


    private function populateFeederDataIds($message, $yesterdayDate, $matches, $recordCount, $sqlDump)
    {

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
            // Check if there are at least 5 elements to avoid the undefined array key error
            if (isset($values[1][4])) {
                $rfid = $values[1][4];
                if (!in_array($rfid, $unique_rfid_values)) {
                    $unique_rfid_values[] = $rfid;
                }
            } else {
                // Handle the case where there are not enough values
                // You might want to log this or take other appropriate action
                Log::warning("An INSERT statement does not contain enough values to extract an RFID: $insert_values");
            }
        }

        $cnt_rfids = count($unique_rfid_values);

        $idsServername = "127.0.0.1"; // feeder_data_ids credentials
        $idsUsername = "root";
        $idsPassword = "Sun84Mus";
        $idsDatabase = "pig_team_server_sql";

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

        return response()->json(['populated ids']);
    }


    private function clearIncremental()
    {
        $incrementalServername = "127.0.0.1"; // feeder_data_new_incremental credentials
        $incrementalUsername = "root";
        $incrementalPassword = "Sun84Mus";
        $incrementalDatabase = "feeder_data_new_incremental";

        $incrementalConn = new \MySQLi($incrementalServername, $incrementalUsername, $incrementalPassword, $incrementalDatabase);
        if ($incrementalConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new_incremental failed: " . $incrementalConn->connect_error);
        }

        $sql = "DELETE FROM feeder_data_new_incremental";

        if ($incrementalConn->query($sql) != TRUE) {
            die("Error clearing incremental table: " . $incrementalConn->error);
        }

        $incrementalConn->close();

        return response()->json(['cleared incremental']);
    }

}

