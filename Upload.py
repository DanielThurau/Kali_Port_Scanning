import datetime
import dropbox
import os

with open(os.path.dirname(os.path.realpath(__file__)) + '/dropbox.txt', 'r') as f:
    for line in f:
        DROP_BOX_API = line.strip(' \n\t\r')

dbx = dropbox.Dropbox(DROP_BOX_API)

dbx.users_get_current_account()

def uploadToDropbox(files, folder_dest):
  #  assert
  returnLinks = []
  CHUNKSIZE = 2 * 1024 *1024

  for file in files:
    fileSize = os.path.getsize(file)
    full_db_path = folder_dest + '{:%Y-%m-%d_%H:%M:%S}'.format(datetime.datetime.now()) + "_" + os.path.basename(file)
    
    if fileSize <= CHUNKSIZE:
      with open(file, "rb") as f:
        dbx.files_upload(f.read(), full_db_path, mute = True)
      result = dbx.files_get_temporary_link(full_db_path)
      returnLinks.append(result.link)
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
        result = dbx.files_get_temporary_link(full_db_path)
        returnLinks.append(result.link)


  
  
  
  
  
  return returnLinks 
