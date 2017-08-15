import os
import logging
import datetime
import time


class BusinessUnit:
  def __init__(self, p_name, p_path, p_log):
    # Set up logging
    logging.basicConfig(filename=p_log, level=logging.INFO)

    # Provided input
    self.send_log("Scan started on " + p_name)
    self.business_unit = p_name
    self.path = p_path
    self.log = p_log

    # Object populates this
    self.machineCount = 0;
    self.emails = []
    self.network_list = []
    self.ip_list = []
    self.exclude_list = []

    # immediatley populated by checkDeps()
    self.config_dir = self.ports_file = self.ip_file = self.nmap_dir = self.ports = ""
    self.CheckDeps()

  # Check that all neccessary configuration dependancies exist  
  def CheckDeps(self):
    self.config_dir = self.path + "config/"
    self.checkExist(self.config_dir)

    self.ports_file = self.config_dir + "ports_bad_" + self.business_unit
    self.checkExist(self.ports_file)

    self.ip_file = self.config_dir + "ports_baseline_" + self.business_unit + ".conf"
    self.checkExist(self.ip_file)

    self.nmap_dir = self.path + "nmap-" + self.business_unit + "/"
    if not os.path.exists(self.nmap_dir):
      self.send_log(self.nmap_dir + " does not exist... creating now")
      os.system("mkdir " + self.nmap_dir)

  def checkExist(self, file):
    if not os.path.exists(file):
      print(file + " does not exist. Exiting...")
      self.send_log(file+ " does not exist.")
      exit(0)

  def send_log(self, message):
    logging.info(datetime.datetime.fromtimestamp(time.time()).strftime('%Y-%m-%d %H:%M:%S') + " " + message)

  def read_file_ports(self):
    try:
      with open(self.ports_file, 'r') as f:
        for line in f:
          # Comment removal
          if line[0] == '#':
            continue
          elif '#' in line:
            line = line.split('#')[0]
          self.ports = self.ports + line.strip(' \t\n\r')
          if self.ports[len(self.ports)-1] != ',':
            self.ports = self.ports + ','
    except IOError:
      self.send_log("Unable to open " + self.ports_file)
      exit(1)

  def read_file_base(self):
      

    



