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

        $data_newServername = "127.0.0.1"; // feeder_data_new credentials
        $data_newUsername = "root";
        $data_newPassword = "Sun84Mus";
        $data_newDatabase = "pig_team_server_sql";

        $Conn = new \MySQLi($data_newServername, $data_newUsername, $data_newPassword, $data_newDatabase);
        if ($Conn->connect_error) { // Verify connection
            die("Connection to feeder_data_new_lower failed: " . $Conn->connect_error);
        }

        $sql_queries = explode(';', $sqlDump);
        foreach ($sql_queries as $sql) {
            $sql = trim($sql);  // Trim to remove any extraneous whitespace
            if ($sql != '') {
                echo ($sql . "<br>");  // Display the SQL command to be executed
                if ($Conn->query($sql) !== TRUE) {
                    die("Error executing query while updating feeder_data_new_issncremental: " . $Conn->error);
                }
            }
        }
        $Conn->close();

        // Now check how many records feeder_data_new_incremental contains
        $pattern = "/INSERT INTO\s+`[^`]*`\s+\(([^)]*)\)\s+VALUES\s+\(([^)]*)\)/";
        preg_match_all($pattern, $sqlDump, $matches);

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
        $sql = "SELECT * FROM feeder_data_new_lower WHERE DATE(readtime) = '$yesterdayDate'";
        $result = $data_newConn->query($sql);

        $data_newConn->close();

        if ($result) {
            $data_newCount = $result->num_rows;
        } else {
            die("Error executing query in feeder_data_new_lower: " . $data_newConn->connect_error);
        }



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
            $incrementalDatabase = "pig_team_server_sql";

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
            $recordCount = $this->sendIncrementalToMaster($sqlDump, $message);
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

        if (!($result)) {
            die("FAULT 2: Update to data_sent_log failed.");
        }

    }

    function getSQLCredentials(){
        $truthServername = "127.0.0.1"; // data_sent_log credentials
        $truthUsername = "root";
        $truthPassword = "Sun84Mus";
        $truthDatabase = "pig_team_server_sql";

        $truthConn = new \MySQLi($truthServername, $truthUsername, $truthPassword, $truthDatabase);
        if ($truthConn->connect_error) {
            die("Connection to data_sent_log failed: " . $truthConn->connect_error);
        }else{
            return $truthConn;
        }
    }

    private function checkPreviousData($message, $date, $matches, $recordCount, $sqlDump)
    {

        $truthConn = $this->getSQLCredentials();

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


        $masterConn = $this->getSQLCredentials();



        // Assuming $sqlDump is your SQL dump string
        $sql_new_dump = str_replace('`feeder_data_new_incremental`', '`feeder_data_new`', $sqlDump);

        $sql_queries = explode(';', $sql_new_dump);
        $count = 0;
        foreach ($sql_queries as $sql) {
            $sql = trim($sql);  // Trim to remove any extraneous whitespace
            if ($sql != '') {
                if ($masterConn->query($sql) != TRUE) {
                    die("Error executing query while adding incremental data to master: " . $masterConn->error);
                }else{
                     $count = $count + 1;
                }
            }
        }
        $masterConn->close();

        echo "Successfully sent incremental data to master.";

        return $count;

    }

    private function runQueries($sqlDump, $Conn, $errorString)
    {
        $sql_queries = explode(';', $sqlDump);
        foreach ($sql_queries as $sql) {
            $sql = trim($sql);  // Trim to remove any extraneous whitespace
            if ($sql != '') {
                echo ($sql . "<br>");  // Display the SQL command to be executed
                if ($Conn->query($sql) !== TRUE) {
                    die($errorString . $Conn->error);
                }
            }
        }
    }


    private function populateFeederDataIds($message, $yesterdayDate, $matches, $recordCount, $sqlDump)
    {

        $idsResult = $this->getIdsForTable();

        if (isset($idsResult['idFirst'], $idsResult['idLast'])) {
            $id_first = $idsResult['idFirst'];
            $id_last = $idsResult['idLast'];
            echo "----\n";
            echo "ID First: " . $id_first . "\n";
            echo "ID Last: " . $id_last . "\n";
            echo "----\n";
        } else {
            echo "ID values are not available in the result.\n";
            var_dump($idsResult);  // This will show the structure and content of idsResult
        }

        echo ("----");

        echo ($id_first);
        echo ($id_last);
        echo ("----");
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


        $idsConn = $this->getSQLCredentials();

        $sql = "SELECT * FROM feeder_data_ids WHERE date = '$yesterdayDate'";

        $result = $idsConn->query($sql);

        if ($result) {
            $message .= "Successfully updated feeder_data_ids. ";

        } else {
            die("Update to feeder_data_ids failed: " . $idsConn->connect_error);
        }

        //   echo nl2br( $id_last);
        //echo ("<br>");
         //  echo nl2br( $id_first);
       // echo ("<br>");
         // echo nl2br( $recordCount);
       // echo ("<br>");
        // echo nl2br( $cnt_rfids);
       // echo ("<br>");
        // echo nl2br( $updated);
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

    function getIdsForTable() {

        $Conn = $this->getSQLCredentials();

        $idFirst = null;
        $idLast = null;

        $sql = "SELECT id FROM feeder_data_new_incremental";

        $result = $Conn->query($sql);
        if ($result) {
            echo ("result is true");
            if ($result->num_rows > 0) {
                echo ("result num_rows above 0");
                $ids = array();
                while ($row = $result->fetch_assoc()) {
                    $ids[] = $row['id'];
                }
                $idFirst = reset($ids);
                $idLast = end($ids);
            } else {
                echo "No ids found in feeder_data_new_incremental.";
            }
        } else {
            die("Error executing query in feeder_data_new_incremental: " . $Conn->connect_error);
        }

        $Conn->close();

        echo($idLast);
        echo ($idFirst);
        return array('idFirst' => $idFirst, 'idLast' => $idLast);
    }


    private function clearIncremental()
    {

        $incrementalConn = $this->getSQLCredentials();

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
        $conn = $this->getSQLCredentials();

        $sql = "INSERT INTO feeder_data_new SELECT * FROM feeder_data_new_incremental WHERE DATE(readtime) = '%$date%'";
        $result = $conn->query($sql);

        $conn->close();

        if ($result) {
            echo "Server updated successfully.";
        } else {
            die("Error copying feeder_data_new_incremental to server " . $conn->error);
        }

    }

    private function copyDataToIncremental($date)
    {
        $conn = $this->getSQLCredentials();
        echo ("This is date in copy Data to Incremental ______");
        echo ($date);
        $sql = "INSERT INTO feeder_data_new_incremental SELECT * FROM feeder_data_new_lower WHERE DATE(readtime) = '%$date%'";
        $result = $conn->query($sql);
       // echo ($result->num_rows);
        $conn->close();


        if ($result) {
            echo "Incremental table updated successfully.";
        } else {
            die("Error copying data from feeder_data_new to feeder_data_new_incremental: " . $conn->error);
        }
    }

}

