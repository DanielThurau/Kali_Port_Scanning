from log import *
from ScanObject import *


import datetime
import os
import subprocess
import time


class BusinessUnit:
  def __init__(self, p_name, p_path):
    """ BusinessUnit Class Constructor """

    # Provided input
    send_log("Scan started on " + p_name)
    self.business_unit = p_name
    self.path = p_path

    # Object populates this
    self.machineCount = 0;
    self.emails = []
    self.exclude_string = ""
    self.BU_scan_objs = []
    self.stats = {"open":0, "open|filtered":0, "filtered":0, "closed|filtered":0, "closed":0}


    # immediatley populated by checkDeps()
    self.config_dir = self.ports_file = self.ip_file = self.nmap_dir = self.ports = self.outfile = ""
    self.CheckDeps()

  # Check that all neccessary configuration dependancies exist  
  def CheckDeps(self):
    """ Private Method that depends on self.path existing in the object """
    if self.path == "":
      send_log("CheckDeps called on " + self.business_unit + " object but does not contain a self.path defined variable. ")
      exit(1)
    self.config_dir = self.path + "config/"
    self.CheckExist(self.config_dir)

    self.ports_file = self.config_dir + "ports_bad_" + self.business_unit
    self.CheckExist(self.ports_file)

    self.ip_file = self.config_dir + "ports_baseline_" + self.business_unit + ".conf"
    self.CheckExist(self.ip_file)

    self.nmap_dir = self.path + "nmap-" + self.business_unit + "/"
    if not os.path.exists(self.nmap_dir):
      send_log(self.nmap_dir + " does not exist... creating now")
      os.system("mkdir " + self.nmap_dir)

  def CheckExist(self, file):
    """ Helper private method for CheckDeps """
    if not os.path.exists(file):
      print(file + " does not exist. Exiting...")
      send_log(file+ " does not exist.")
      exit(0)

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
          
          # trim any trailing commas and add ONLY one
          # IMPORTANT. DO NOT REMOVE
          while self.ports[-1] == ',':
            self.ports = self.ports[:-1]
          self.ports = self.ports + ','

    except IOError:
      send_log("Unable to open " + self.ports_file)
      exit(1)

  def read_file_base(self):
    try:
      with open(self.ip_file, 'r') as f:
        for line in f:
          # test if line is empty and continue is so
          try:
            line.strip(' \t\n\r')[0]
          except:
            continue

          # Comments and emails
          if line[0] == '#':
            continue
          elif '#' in line:
            line = line.split('#')[0]
          elif '@' in line:
            self.emails.append(line.strip(' \t\n\r'))
            continue

          # Business unit scan object
          if line[0] == "-":
            self.exclude_string = self.exclude_string + line[1:].strip(' \t\n\r') + ","
            continue
          else:
            # create scan object
            BU_SO = ScanObject()
            # populate fields based on line input
            if(BU_SO.populate(line.strip(' \t\n\r'))):
              # from populated fields, create the command using this data
              BU_SO.createCommand(self.exclude_string, self.ports, self.nmap_dir)
              # if not fails append to the scan_obj list
              self.BU_scan_objs.append(BU_SO)
              self.machineCount = self.machineCount + BU_SO.getMachineCount()
    except IOError:
      send_log("Unable to open " + self.ip_file)
      exit(1)



  def scan(self):
    pids = []
    for obj in self.BU_scan_objs:
      pid = os.fork()
      if pid != 0:
        pids.append(pid)
      else:
        send_log(obj.command)
        os.system(obj.command)
        send_log("Im done executing")
        exit(0)

    for i in pids:
      os.waitpid(i, 0)


  def parse_output(self):
    master_out = []

    for obj in self.BU_scan_objs:
      with open(obj.outfile, 'r') as f:
        READING = False
        scan_id = ""
        for line in f:
          if line[:16] == "Nmap scan report":
            scan_id = line[21:].strip(' \n')
            continue
          if line[0].isnumeric():
            if "open|filtered" in line:
                self.stats["open|filtered"] = self.stats["open|filtered"] + 1
            elif "closed|filtered" in line:
                self.stats["closed|filtered"] = self.stats["closed|filtered"] + 1
            elif "filtered" in line:
                self.stats["filtered"] = self.stats["filtered"] + 1
            elif "closed" in line:
                self.stats["closed"] = self.stats["closed"] + 1
            elif "open" in line:
                self.stats["open"] = self.stats["open"] + 1

            explode = line.split(' ')
            final = scan_id + ","
            for i in explode:
              if len(i) > 0:
                final = final + i.strip(' \n\t\r') + ","
            master_out.append(final.strip(' \t\r\n'))
    return master_out


  def collect(self):
    out = self.parse_output()
    self.outfile = self.nmap_dir + "output-" + self.business_unit + ".csv";
    with open(self.outfile, 'w') as f:
      for line in out:
        f.write(line + "\n")
    os.system("zip " + self.outfile + ".zip " + self.outfile) 




    



