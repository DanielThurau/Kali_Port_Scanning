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
  for file in files:
      full_db_path = folder_dest + '{:%Y-%m-%d_%H:%M:%S}'.format(datetime.datetime.now()) + os.path.basename(file)
      with open(file, "rb") as f:
          dbx.files_upload(f.read(), full_db_path, mute = True)
      result = dbx.files_get_temporary_link(full_db_path)
      returnLinks.append(result.link)
  return returnLinks 
