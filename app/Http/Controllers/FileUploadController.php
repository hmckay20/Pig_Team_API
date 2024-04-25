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
        $this->clearIncremental(); // Ensure that the incremental table is cleared.

        Log::info('Start of incremental');
        $message = 'File uploaded successfully';
        $sqlFileName = NULL;
        $correctDate = FALSE;
        $correctCount = FALSE;

        // .zip file validation, opening, and retrieving data
        Log::info('I have validated the file ');
        if ($request->hasFile('zip_file')) {
            $uploadedZipFile = $request->file('zip_file');
            $zip = new ZipArchive();

            if ($zip->open($uploadedZipFile->getRealPath()) === TRUE) {
                if ($zip->numFiles == 1) {
                    $sqlFileName = $zip->getNameIndex(0); // Retrieve the name of the file inside the zip
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


        // We only want to send the new data if yesterday's data has been sent.
        $yesterdayDate = date("Y-m-d", strtotime("-1 day"));

        // Now check how many records feeder_data_new_incremental contains
        preg_match_all("/INSERT INTO `feeder_data_new_incremental` VALUES \((.*?)\);/", $sqlDump, $matches);
        $matchCount = count($matches[1]); // $matches[0] contains the full pattern matches
        Log::info("Number of matches found: {$matchCount}");
        $recordCount = 0;
        foreach ($matches[1] as $insertValues) {
            $values = explode('),(', $insertValues);
            $recordCount += count($values);
        }
        echo ("I am checking previous data");
        if (!($this->checkPreviousData($message ,$yesterdayDate, $matches, $recordCount, $sqlDump))) {
            echo "This is nSot yesterday's table.";
            die($yesterdayDate);
        } else {
            $correctDate = TRUE;
            echo ("I returned true");
        }

        // The table is from yesterday.
        Log::info('I have validated that this file is from yesterday');

        // Check how many records were inserted in feeder_data_new yesterday
        $data_newServername = "127.0.0.1"; // feeder_data_new credentials
        $data_newUsername = "root";
        $data_newPassword = "Sun84Mus";
        $data_newDatabase = "pig_team_server_sql";

        $data_newConn = new \MySQLi($data_newServername, $data_newUsername, $data_newPassword, $data_newDatabase);
        if ($data_newConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new_lower failed: " . $data_newConn->connect_error);
        }
        $sql = "SELECT * FROM feeder_data_new_lower WHERE id = '$yesterdayDate'";
        $result = $data_newConn->query($sql);

        $data_newConn->close();

        if ($result) {
            $data_newCount = $result->num_rows;
            echo ($data_newCount);
        } else {
            die("Error executing query in feeder_data_new_lower: " . $data_newConn->connect_error);
        }


        echo ("line 104");
        // If there is no data in incremental, try populating it.
        if ($matchCount == 0 && $data_newCount != 0) {
            echo ("match count 0 data_newCOunt not 0");
            $data_newServername = "127.0.0.1"; // feeder_data_new credentials
            $data_newUsername = "root";
            $data_newPassword = "Sun84Mus";
            $data_newDatabase = "pig_team_server_sql";

            $data_newConn = new \MySQLi($data_newServername, $data_newUsername, $data_newPassword, $data_newDatabase);
            if ($data_newConn->connect_error) { // Verify connection
                die("Connection to feeder_data_new failed: " . $data_newConn->connect_error);
            }

            $incrementalServername = "127.0.0.1"; // feeder_data_new_incremental credentials
            $incrementalUsername = "root";
            $incrementalPassword = "Sun84Mus";
            $incrementalDatabase = "pig_team_server_sqll";

            $incrementalConn = new \MySQLi($incrementalServername, $incrementalUsername, $incrementalPassword, $incrementalDatabase);
            if ($incrementalConn->connect_error) { // Verify connection
                die("Connection to feeder_data_new_incremental failed: " . $incrementalConn->connect_error);
            }

            $sql = "INSERT INTO feeder_data_new_incremental SELECT * FROM feeder_data_new WHERE id LIKE '%$yesterdayDate%'";
            $result = $incrementalConn->query($sql);

            $data_newConn->close();
            $incrementalConn->close();

            if ($result) {
                echo "Data copied successfully.";
            } else {
                die("Error copying data: " . $incrementalConn->error);
            }
        }
        echo ("we passed this ");
        echo ($matchCount);
        if ($matchCount == $data_newCount) { // Compare the counts
            $correctCount = TRUE;
        }

        if ($correctDate && $correctCount) { // SEND AWAY - Add data to respective tables
            echo ("I am about to send inc to master");
            $this->sendIncrementalToMaster($sqlDump, $message);
            $this->populateFeederDataIds($message, $yesterdayDate, $matches, $recordCount, $sqlDump);
            $this->clearIncremental();
            $this->updateDataSentLog($yesterdayDate); // The tables should be updated now, so update the log to reflect that.
        } else {
            Log::info('There is no new data to send from day ' . $yesterdayDate);
            $message .= "There is no new data to send from day " . $yesterdayDate . ". ";
        }

        Log::info('right before I send response');
        return response()->json(['message' => $message, 'records' => $recordCount]);
    }


    private function updateDataSentLog($yesterdayDate)
    {
        $today = date("Y-m-d");

        $truthServername = "127.0.0.1"; // data_sent_log credentials
        $truthUsername = "root";
        $truthPassword = "Sun84Mus";
        $truthDatabase = "pig_team_server_sql";

        $truthConn = new \MySQLi($truthServername, $truthUsername, $truthPassword, $truthDatabase);
        if ($truthConn->connect_error) {
            die("Connection to table data_sent_log failed: " . $truthConn->connect_error);
        }

        $sql = "INSERT INTO data_sent_log (data_date, sent_date, data_sent) VALUES ('$yesterdayDate', '$today', 1)";
        $result = $truthConn->query($sql);

        $truthConn->close();

        echo "FAULT 2: Update to data_sent_log failed.";
        die("FAULT 2: Update to data_sent_log failed.");
    }


    private function checkPreviousData($message, $date, $matches, $recordCount, $sqlDump)
    {
        // Check if the previous day's incremental table has been updated.
        $truthServername = "127.0.0.1"; // data_sent_log credentials
        $truthUsername = "root";
        $truthPassword = "Sun84Mus";
        $truthDatabase = "pig_team_server_sql";

        $truthConn = new \MySQLi($truthServername, $truthUsername, $truthPassword, $truthDatabase);
        if ($truthConn->connect_error) {
            die("Connection to data_sent_log failed: " . $truthConn->connect_error);
        }

        $sql = "SELECT * FROM data_sent_log WHERE data_date = '$date' AND data_sent = 1";
        $result = $truthConn->query($sql);

        $truthConn->close();

        if ($result->num_rows == 0) {

            echo "Data from $date not found. ";
            $message .= "Data from $date has been found missing. ";

            $previousDate = date('Y-m-d', strtotime($date . ' -1 day'));

            $validity = $this->checkPreviousData($message, $previousDate, $matches, $recordCount, $sqlDump );
            if ($validity) {
                $this->updatePreviousData($message, $previousDate, $matches, $recordCount, $sqlDump);
                return TRUE;
            }

        } else {

            return TRUE;
        }
        return FALSE;
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
            echo ($sql);
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

        $idsResult = $this->getIdsForTable($sqlDump);
        $id_first = $idsResult['idFirst'];
        $id_last = $idsResult['idLast'];

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

        if ($result) {
            $message .= "Successfully updated feeder_data_ids. ";

        } else {
            die("Update to feeder_data_ids failed: " . $idsConn->connect_error);
        }

        //   echo ($id_last);
        //   echo ($id_first);
        //  echo ($recordCount);
        // echo ($cnt_rfids);
        // echo ($updated);
        $sql2 = "INSERT INTO feeder_data_ids (date, id_first, id_last, totalreads, cnt_rfids, updated) VALUES ('$yesterdayDate', '$id_first', '$id_last', '$recordCount', '$cnt_rfids', '$updated')";
        $result2 = $idsConn->query($sql2);


        if ($result2) {
            $message .= "Successfully updated feeder_data_ids. ";
            echo ("we passed");
        } else {
            die("Update to feeder_data_ids failed: " . $idsConn->connect_error);
        }

        $idsConn->close();
    }

    function getIdsForTable($sqlDump) {
        $sqlStatements = explode(';', $sqlDump);

        $idFirst = null;
        $idLast = null;

        foreach ($sqlStatements as $sql) {
            $sql = trim($sql);

            if (!empty($sql)) {
                preg_match('/INSERT INTO\s+`.*?`\s+\((.*?)\)\s+VALUES\s+\((.*?)\)/', $sql, $matches);


                $columns = explode(',', $matches[1]);

                
                $values = explode(',', $matches[2]);

                $idIndex = array_search('`id`', $columns);

                if ($idIndex != false && isset($values[$idIndex])) {
                    $idValue = trim($values[$idIndex], '`\'"');
   
                    if ($idLast == null) {
                        $idLast = $idValue;
                    }
                    $idLast = $idValue;
                }
            }
        }


        return array('idFirst' => $idFirst, 'idLast' => $idLast);
    }


    private function clearIncremental()
    {
        $incrementalServername = "127.0.0.1"; // feeder_data_new_incremental credentials
        $incrementalUsername = "root";
        $incrementalPassword = "Sun84Mus";
        $incrementalDatabase = "pig_team_server_sql";

        $incrementalConn = new \MySQLi($incrementalServername, $incrementalUsername, $incrementalPassword, $incrementalDatabase);
        if ($incrementalConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new_incremental failed: " . $incrementalConn->connect_error);
        }

        $sql = "DELETE FROM feeder_data_new_incremental";

        if ($incrementalConn->query($sql) != TRUE) {
            die("Error clearing incremental table: " . $incrementalConn->error);
        }

        $incrementalConn->close();
    }

    // These functions deal with fault management.
    private function updatePreviousData($message, $date, $matches, $recordCount, $sqlDump)
    {
        $this->clearIncremental();
        $this->copyDataToIncremental($date);
        $this->sendPreviousIncrementalToMaster($date);
        $this->populateFeederDataIds($message, $date, $matches, $recordCount, $sqlDump);
        $this->clearIncremental();
        echo "Data from $date updated to master. ";
        $message .= "Data from $date updated to master. ";
    }

    private function sendPreviousIncrementalToMaster($date)
    {
        $incrementalServername = "127.0.0.1"; // feeder_data_new_incremental credentials
        $incrementalUsername = "root";
        $incrementalPassword = "Sun84Mus";
        $incrementalDatabase = "pig_team_server_sql";

        $incrementalConn = new \MySQLi($incrementalServername, $incrementalUsername, $incrementalPassword, $incrementalDatabase);
        if ($incrementalConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new_incremental failed: " . $incrementalConn->connect_error);
        }

        $masterServername = "127.0.0.1"; // master credentials
        $masterUsername = "root";
        $masterPassword = "Sun84Mus";
        $masterDatabase = "pig_team_server_sql";

        $masterConn = new \MySQLi($masterServername, $masterUsername, $masterPassword, $masterDatabase);
        if ($masterConn->connect_error) {
            die("Connection to master failed: " . $masterConn->connect_error);
        }

        $sql = "INSERT INTO master SELECT * FROM feeder_data_new_incremental WHERE id LIKE '%$date%'";
        $result = $incrementalConn->query($sql);

        $incrementalConn->close();
        $masterConn->close();

    }

    private function copyDataToIncremental($date)
    {
        $data_newServername = "127.0.0.1"; // feeder_data_new credentials
        $data_newUsername = "root";
        $data_newPassword = "Sun84Mus";
        $data_newDatabase = "pig_team_server_sql";

        $data_newConn = new \MySQLi($data_newServername, $data_newUsername, $data_newPassword, $data_newDatabase);
        if ($data_newConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new_lower failed: " . $data_newConn->connect_error);
        }

        $incrementalServername = "127.0.0.1"; // feeder_data_new_incremental credentials
        $incrementalUsername = "root";
        $incrementalPassword = "Sun84Mus";
        $incrementalDatabase = "pig_team_server_sql";

        $incrementalConn = new \MySQLi($incrementalServername, $incrementalUsername, $incrementalPassword, $incrementalDatabase);
        if ($incrementalConn->connect_error) { // Verify connection
            die("Connection to feeder_data_new_incremental failed: " . $incrementalConn->connect_error);
        }

        $sql = "INSERT INTO feeder_data_new_incremental SELECT * FROM feeder_data_new_lower WHERE id LIKE '%$date%'";
        $result = $incrementalConn->query($sql);

        $data_newConn->close();
        $incrementalConn->close();

        if ($result) {
            echo "Incremental table updated successfully.";
        } else {
            die("Error copying data from feeder_data_new to feeder_data_new_incremental: " . $incrementalConn->error);
        }
    }

}
