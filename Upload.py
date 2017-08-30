import arrow
import dropbox
import os
import requests

utc = arrow.utcnow()
local = utc.to('US/Pacific')



DROP_BOX_API = os.environ['dropbox_key']
GOOGLE_API = os.environ['google_key']

dbx = dropbox.Dropbox(DROP_BOX_API)

dbx.users_get_current_account()

def uploadToDropbox(files, folder_dest):
  print(files)
  print(folder_dest)
  #  assert
  returnLinks = []
  CHUNKSIZE = 2 * 1024 *1024

  for file in files:
    fileSize = os.path.getsize(file)
    full_db_path = folder_dest + local.format('YYYY-MM-DD_HH:mm:ss')  + "_" + os.path.basename(file)
    
    if fileSize <= CHUNKSIZE:
      with open(file, "rb") as f:
        dbx.files_upload(f.read(), full_db_path, mute = True)
        test='Bearer ' + DROP_BOX_API
        headers = {
            'Authorization': test, 
            'Content-Type': 'application/json',
        }
        data = '{"path": "%s","settings": {"requested_visibility": "public"}}' % full_db_path

        result = requests.post('https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings', headers=headers, data=data)
        if result.status_code == 200:
            returnLinks.append(result.json()['url'])
    else:
        print("IN THE RIGHT ONE")
        with open(file, "rb") as f:
            upload_session_start_result = dbx.files_upload_session_start(f.read(CHUNKSIZE))
            cursor = dropbox.files.UploadSessionCursor(session_id=upload_session_start_result.session_id, offset=f.tell())

            commit = dropbox.files.CommitInfo(path=full_db_path)

            while f.tell() < fileSize:
                if((fileSize - f.tell()) <= CHUNKSIZE):
                    print(dbx.files_upload_session_finish(f.read(CHUNKSIZE), cursor, commit))
                else:
                    dbx.files_upload_session_append(f.read(CHUNKSIZE), cursor.session_id, cursor.offset)

                    cursor.offset = f.tell()

        test='Bearer ' + DROP_BOX_API
        headers = {
            'Authorization': test, 
            'Content-Type': 'application/json',
        }
        data = '{"path": "%s","settings": {"requested_visibility": "public"}}' % full_db_path

        result = requests.post('https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings', headers=headers, data=data)
        if result.status_code == 200:
            returnLinks.append(result.json()['url'])
#        returnLinks.append(result.link)
  for i in range(0, len(returnLinks)):
      headers = {
                  'Content-Type': 'application/json',
                }
      params = (
                  ('key', GOOGLE_API),
               )

      data = '{"longUrl": "%s"}' % returnLinks[i]

      r = requests.post('https://www.googleapis.com/urlshortener/v1/url', headers=headers, params=params, data=data)
      print(r.json())
      returnLinks[i] = r.json()["id"]
  return returnLinks 
