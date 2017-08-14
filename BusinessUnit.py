import os
import logging
import datetime
import time


class BusinessUnit:
  def __init__(self, p_name, p_path, p_log):
    # Provided input
    business_unit = p_name
    path = p_path
    log = p_log

    # Object populates this
    machineCount = 0;
    networks = []
    exclude_list = []

    # Set up logging
    logging.basicConfig(filename=log, level=logging.INFO)

    # immediatley populated by checkDeps()
    config_dir = ports_file = ip_file = nmap_dir = ""
    # CheckDeps()

  def CheckDeps():
    config_dir = path + "config/"
    if not os.path.exists(config_dir):
      print("Config directory does not exist")
      send_log()
      exit(0)

  def send_log(self, message):
    logging.info(datetime.datetime.fromtimestamp(time.time()).strftime('%Y-%m-%d %H:%M:%S') + " " + message)
