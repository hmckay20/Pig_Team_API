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
        $requestedUploadDate = $request->input('date');
        $message = 'File uploaded successfully';
        $sqlFileName = NULL;

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

        // Populate feeder_data_new_incremental
        $Conn = $this->getSQLCredentials();
        $sql_queries = explode(';', $sqlDump);
        foreach ($sql_queries as $sql) {
            $sql = trim($sql);  // Trim to remove any extraneous whitespace
            if ($sql != '') {
                echo ($sql . "<br>");  // Display the SQL command to be executed
                if ($Conn->query($sql) !== TRUE) {
                    die("Error executing query while updating feeder_data_new_incremental: " . $Conn->error);
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

        // Send that data
        if ($recordCount > 0) {
            echo ("I am about to send inc to master");
            $recordCount = $this->sendIncrementalToMaster($sqlDump, $message);
            $this->populateFeederDataIds($message, $requestedUploadDate, $matches, $recordCount, $sqlDump);
            $this->clearIncremental();
            $this->updateDataSentLog($requestedUploadDate); // The tables should be updated now, so update the log to reflect that.
        } else {
            Log::info('There is no new data to send from day ' . $requestedUploadDate);
            $message .= "There is no new data to send from day " . $requestedUploadDate . ". ";
        }

        Log::info('right before I send response');
        return response()->json(['message' => $message, 'records' => $recordCount]);
    }


    private function updateDataSentLog($requestedUploadDate)
    {
        $today = date("Y-m-d");

        $Conn = $this->getSQLCredentials();

        $sql = "INSERT INTO data_sent_log (data_date, sent_date, data_sent) VALUES ('$requestedUploadDate', '$today', 1)";
        $result = $Conn->query($sql);
        $Conn->close();

        if (!($result)) {
            die("Update to data_sent_log failed.");
        }
    }

    function getSQLCredentials(){
        $Servername = "127.0.0.1"; // data_sent_log credentials
        $Username = "root";
        $Password = "Sun84Mus";
        $Database = "pig_team_server_sql";

        $Conn = new \MySQLi($Servername, $Username, $Password, $Database);
        if ($Conn->connect_error) {
            die("Connection to server failed: " . $Conn->connect_error);
        }else{
            return $Conn;
        }
    }


    private function sendIncrementalToMaster($sqlDump, $message)
    {
        $Conn = $this->getSQLCredentials();
        // Assuming $sqlDump is your SQL dump string
        $sql_new_dump = str_replace('`feeder_data_new_incremental`', '`feeder_data_new`', $sqlDump);

        $sql_queries = explode(';', $sql_new_dump);
        $count = 0;
        foreach ($sql_queries as $sql) {
            $sql = trim($sql);  // Trim to remove any extraneous whitespace
            if ($sql != '') {
                if ($Conn->query($sql) != TRUE) {
                    die("Error executing query while adding incremental data to master: " . $Conn->error);
                }else{
                     $count = $count + 1;
                }
            }
        }
        $Conn->close();
        echo "Successfully sent incremental data to master.";
        return $count;
    }


    private function populateFeederDataIds($message, $requestedUploadDate, $matches, $recordCount, $sqlDump)
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

        $Conn = $this->getSQLCredentials();

        $sql = "SELECT * FROM feeder_data_ids WHERE date = '$requestedUploadDate'";

        $result = $Conn->query($sql);

        if ($result) {
            $message .= "Successfully updated feeder_data_ids. ";

        } else {
            die("Update to feeder_data_ids failed: " . $Conn->connect_error);
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
        $sql2 = "INSERT INTO feeder_data_ids (date, id_first, id_last, totalreads, cnt_rfids, updated) VALUES ('$requestedUploadDate', '$id_first', '$id_last', '$recordCount', '$cnt_rfids', '$updated')";
        $result2 = $Conn->query($sql2);

        if ($result2) {
            $message .= "Successfully updated feeder_data_ids. ";
            echo ("we passed");
        } else {
            die("Update to feeder_data_ids failed: " . $Conn->connect_error);
        }

        $Conn->close();
    }

    function getIdsForTable() 
    {
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

        $Conn = $this->getSQLCredentials();

        $sql = "DELETE FROM feeder_data_new_incremental";

        if ($Conn->query($sql) != TRUE) {
            die("Error clearing incremental table: " . $Conn->error);
        }

        $Conn->close();
    }


}

