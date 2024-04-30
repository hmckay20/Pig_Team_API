import requests
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

def send_ics():

    with open(file_path_hello, 'rb') as file:
        files = {'file': file}
        response = requests.post(api_url_ics, files=files)
        print(response.text)


def send_log_files():
    files = {'file': open(file_path_log, 'rb')}
    response = requests.post(api_url_log, files=files)
    print(response.text)

def upload_incremental():
    try:
        with open(file_path_upload, 'rb') as file:
            files = {'zip_file': file}  # Ensure the form field matches the API's expected field
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


while True:
    schedule.run_pending()
    time.sleep(1)











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