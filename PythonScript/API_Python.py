import shutil
import requests
import pandas as pd
from sqlalchemy import URL, create_engine
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
    print(last_date_dt)
    print(yesterday)
    while(last_date_dt < yesterday):
        df = fetch_data_from_db(last_date_log)
        sql_file_path = save_data_to_sql(df)
        zip_file_path, zip_file_name  = zip_sql_file(sql_file_path, last_date_log)
        print("this is the path", zip_file_path)
        print("this is zip file name",zip_file_name)
        upload_incremental(zip_file_path, "zip_file")
        last_date_log = check_data_send_log_last_date()
        last_date_dt = pd.to_datetime(last_date_log['data_date'].iloc[0]).date()




def check_data_send_log_last_date():
    engine = create_engine(url_object)

    query = f"SELECT data_date FROM data_sent_log WHERE data_sent = 1 ORDER BY data_date DESC LIMIT 1"
    last_date_log = pd.read_sql(query, engine)

    return last_date_log

def fetch_data_from_db(last_date_log):
    last_date_dt = pd.to_datetime(last_date_log['data_date'].iloc[0])

    next_day = last_date_dt + timedelta(days=1)
    next_day_str = next_day.strftime('%Y-%m-%d')
    print("this is next day string", next_day_str)

    query = f"SELECT * FROM feeder_data_new_lower WHERE DATE(readtime) = '{next_day_str}'"

    engine = create_engine(url_object)
    df = pd.read_sql(query, engine)
    return df

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

    print(f"Creating zip archive at '{zip_file_name}.zip' containing the file '{base_name}'")
    
    try:
        # Create a zip archive that includes the SQL file
        shutil.make_archive(zip_file_name, 'zip', directory, base_name)
        print("Archive created successfully.")
    except Exception as e:
        print(f"An error occurred while creating the zip archive: {e}")
        return None
    final_zip_path = f"{zip_file_name}.zip".replace('\\', '/')

    return (final_zip_path, f"{zip_base_name}.zip")

# Example use of the function:
# zip_sql_file("/path/to/data_from_yesterday.sql", last_date_log)

# def send_ics():

#     with open(file_path_hello, 'rb') as file:
#         files = {'file': file}
#         response = requests.post(api_url_ics, files=files)
#         print(response.text)


# def send_log_files():
#     files = {'file': open(file_path_log, 'rb')}
#     response = requests.post(api_url_log, files=files)
#     print(response.text)

def upload_incremental(sql_file_path, zip_file_name):
    try:
        with open(sql_file_path, 'rb') as file:
            files = {zip_file_name: file}  # Ensure the form field matches the API's expected field
            response = requests.post(api_url_file, files=files)
            print("Response text:", response.text)
            print("Status code:", response.status_code)
    except FileNotFoundError:
        print("The file was not found at the specified path.")
    except Exception as e:
        print("An error occurred:", e)



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


# schedule.every().hour.do(send_ics)
# schedule.every().hour.do(send_log_files)
# schedule.every(15).minutes.do(send_data)

#schedule.every(2).minutes.do(send_ics)
#schedule.every(2).minutes.do(send_log_files)
#schedule.every(2).minutes.do(send_data)
schedule.every(1).minute.do(upload_incremental)


# while True:
#     schedule.run_pending()
#     time.sleep(1)


def main():
    rerun_if_different_date()

if __name__ == '__main__':
    main()







# def consume_api():
#     url = 'http://your-api-url.com/incrementalUpload'  # Replace this with the actual URL of your API
#     files = {'file': open('path/to/your/zip/file.zip', 'rb')}  # Replace this with the path to your zip file
#     response = requests.post(url, files=files)

#     if response.status_code == 200:
#         print("API call successful!")
#         print("Response:", response.json())
#     else:
#         print("API call failed with status code:", response.status_code)
#         print("Response:", response.text)
#         handle_error(response.text)


# def handle_error(error):
#   return 0