
import shutil
import requests
import pandas as pd
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy import URL, create_engine, text
from datetime import datetime, timedelta
import zipfile
import os
import schedule
import time
import json
import psutil
from datetime import datetime, timedelta

api_url_data = "http://127.0.0.1:8000/api/hourly-status"
api_url_file = "http://127.0.0.1:8000/api/upload-incremental"  # Change to your file upload API URL
api_url_ics = "http://127.0.0.1:8000/api/image-capture-status"
api_url_log = "http://127.0.0.1:8000/api/send-log-files"

file_path = "C:/Users/hmmmc/OneDrive/Desktop/(Y-M-D).zip"
file_path_log = "C:/Users/hmmmc/oneDrive/Desktop/LogFile.zip"
file_path_hello = "C:/Users/hmmmc/oneDrive/Desktop/hello.zip"
file_path_upload = "C:/Users/hmmmc/oneDrive/Desktop/zip_file.zip"
total_reads_today = 0

url_object = URL.create(
    "mysql+mysqlconnector",  # Specify the database system and driver
    username="root",
    password="Sun84Mus",
    host="localhost",
    database="pig_team_server_sql"
)



def rerun_if_different_date():
    yesterday = (datetime.now() - timedelta(days=1)).date()
    last_date_log = check_data_send_log_last_date()
    last_date_dt = pd.to_datetime(last_date_log['data_date'].iloc[0]).date()

    print("start of program")
    if (last_date_dt == yesterday):
        print("-------------------------------------------------------------------------------------")
        print("We are aleady up to date")
        print("Yesterday's date was:", yesterday, "The last Log Sent was: ", last_date_dt)
        print("-------------------------------------------------------------------------------------")
    difference = yesterday - last_date_dt
    if (difference.days > 1):
        print("-------------------------------------------------------------------------------------")
        print("The master table table is behind my ", difference.days, "days")
        print("Performing update now")
        print("-------------------------------------------------------------------------------------")
    while(last_date_dt < yesterday):
        df, next_day_str = fetch_data_from_db(last_date_log)
        sql_file_path = save_data_to_sql(df)
        zip_file_path, zip_file_name  = zip_sql_file(sql_file_path, last_date_log)
        print("-------------------------------------------------------------------------------------")
        print("Updating master table from ", next_day_str)
        success = upload_incremental(zip_file_path, "zip_file", next_day_str)
        if success:
            print("Successfully updated master from ", next_day_str)
            update_data_send_log(next_day_str)
            print("Updated data_sent_log table")
            last_date_log = check_data_send_log_last_date()
            last_date_dt = pd.to_datetime(last_date_log['data_date'].iloc[0]).date()
    print("-------------------------------------------------------------------------------------")
    print("We are aleady up to date")
    print("Yesterday's date was:", yesterday, "The last Log Sent was: ", last_date_dt)
    print("-------------------------------------------------------------------------------------")



def update_data_send_log(next_day_str):
    engine = create_engine(url_object)
    today_date = datetime.now().date()

    # Correctly formatted query using an f-string, wrapped with text() for security and compatibility
    query = text(f"INSERT INTO data_sent_log (data_date, sent_date, data_sent) VALUES ('{next_day_str}', '{today_date}', 1)")

    # Using a transaction
    try:
        with engine.connect() as connection:
            # Begin a transaction
            trans = connection.begin()
            connection.execute(query)
            trans.commit()  # Commit if all is well
    except SQLAlchemyError as e:
        # Rollback the transaction on error
        if 'trans' in locals():
            trans.rollback()
        print("Failed to update data_sent_log for", {next_day_str})

def check_data_send_log_last_date():
    engine = create_engine(url_object)

    query = f"SELECT data_date FROM data_sent_log WHERE data_sent = 1 ORDER BY data_date DESC LIMIT 1"
    last_date_log = pd.read_sql(query, engine)

    if last_date_log.empty:
        while True:
            user_input = input("The table data_sent_log is empty. Please input a date to start from (YYYY-MM-DD): ")
            try:
                # Attempt to convert the input to a date, expecting a specific format
                user_date = pd.to_datetime(user_input, format='%Y-%m-%d')
                break  # Exit loop if successful
            except ValueError:
                print("Invalid date format. Please use YYYY-MM-DD format.")

        # Create a DataFrame similar to what would have been retrieved by the query
        last_date_log = pd.DataFrame({'data_date': [user_date]})

    return last_date_log

def fetch_data_from_db(last_date_log):
    last_date_dt = pd.to_datetime(last_date_log['data_date'].iloc[0])

    next_day = last_date_dt + timedelta(days=1)
    next_day_str = next_day.strftime('%Y-%m-%d')


    query = f"SELECT * FROM feeder_data_new_lower WHERE DATE(readtime) = '{next_day_str}'"

    engine = create_engine(url_object)
    df = pd.read_sql(query, engine)
    if(df.empty):
        print("The table feeder_data_new is empty on ", next_day_str)
    return df, next_day_str

def save_data_to_sql(df):
    sql_file_path = 'C:/Users/hmmmc/oneDrive/Desktop/Pig_Team.SQL'

        # Create a zip file and add the SQL file
    with open(sql_file_path, 'w') as file:
        for index, row in df.iterrows():
            sql_statement = f"INSERT INTO feeder_data_new_incremental (pen, reader, antenna, rfid, readtime, identifier) VALUES ({row['pen']}, '{row['reader']}', '{row['antenna']}', '{row['rfid']}', '{row['readtime']}', '{row['identifier']}');\n"
            file.write(sql_statement)

    return sql_file_path

def zip_sql_file(sql_file_path, last_date_log):
    # Convert the date from last_date_log DataFrame to a string format for the zip file name
    
  
    try:
        zip_base_name = pd.to_datetime(last_date_log['data_date']).iloc[0].strftime('%Y-%m-%d')
    except Exception as e:
        print(f"Error processing date for zip file name: {e}")
        return None

    # The directory where the SQL file is located
    directory = os.path.dirname(sql_file_path)
    # The base name of the file without the path
    base_name = os.path.basename(sql_file_path)

    # Full path for the zip file, including the '.zip' extension
    zip_file_name = os.path.join(directory, zip_base_name)

    
    try:
        # Create a zip archive that includes the SQL file
        shutil.make_archive(zip_file_name, 'zip', directory, base_name)
    except Exception as e:
        print(f"An error occurred while creating the zip archive: {e}")
        return None
    final_zip_path = f"{zip_file_name}.zip".replace('\\', '/')

    return (final_zip_path, f"{zip_base_name}.zip")


# def send_ics():

#     with open(file_path_hello, 'rb') as file:
#         files = {'file': file}
#         response = requests.post(api_url_ics, files=files)
#         print(response.text)


# def send_log_files():
#     files = {'file': open(file_path_log, 'rb')}
#     response = requests.post(api_url_log, files=files)
#     print(response.text)

def  upload_incremental(sql_file_path, zip_file_name, date_str):
    retries = 5
    attempt = 0
    delay = 30

    while attempt < retries:
        try:
            with open(sql_file_path, 'rb') as file:
                files = {zip_file_name: file}  # Ensure the form field matches the API's expected field
                data = {'date': date_str}
                response = requests.post(api_url_file, files=files, data=data)

                if response.status_code == 200:
                    return True
                else:
                    print("Upload Failed with status code ", response.status_code, "retrying....")
                    
                    
        except FileNotFoundError:
            print("The file was not found at the specified path.")
            return False
        except Exception as e:
            print("An error occurred:", e)

        
        attempt += 1
        time.sleep(delay)
        return False



def send_data():
    global total_reads_today
    total_reads_today += 1

    memory = psutil.virtual_memory()

    memory_usage = memory.percent

    memory_usage =psutil.virtual_memory().percent

    storage_used = psutil.disk_usage('/').percent
   
    diskstation_use = 30

    data = {
        "time_of_status": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "total_reads_today": total_reads_today,
        "reads_for_every_reader": None,  # Example of sending JSON data
        "memory_usage": memory_usage,
        "storage_usage": storage_used,
        "diskstation_use": diskstation_use
    }
    headers = {'Content-Type': 'application/json'}
    response = requests.post(api_url_data, json=data)
    print(response.text)
    print("Status code:", response.status_code)




def main():
    rerun_if_different_date()

if __name__ == '__main__':
    main()

