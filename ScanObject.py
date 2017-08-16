from os import system as sys
import subprocess
from collections import deque


class ScanObject:
  def __init__(self):
    self.start_ip = self.subnet = self.range = self.ports = ""
    self.command = ""
    self.outfile = ""

  def getMachineCount(self):
    if self.subnet is None and self.range is None:
      return 1
    elif self.range is None:
      return pow(2, 32 - int(self.subnet))
    else:
      end = self.start_ip.split('.')[3]
      return int(self.range) - int(end) + 1

  # populates the fields and creates the command to be returned 
  def populate(self, line):
    # parse any ports
    if ':' in line:
      line = line.split(':')
      self.ports = line[1];
      # trim any trailing commas
      while self.ports[-1] == ',':
        self.ports = self.ports[:-1]
      line = line[0]
    else:
      self.ports = None

    # 1.2.3.0/24 - > 1.2.3.0 & 24
    if '/' in line:
      line = line.split('/')
      self.subnet = line[1]
      self.start_ip = line[0]
      self.range = None
    elif '-' in line:
      line = line.split('-')
      self.range = line[1]
      self.start_ip = line[0]
      self.subnet = None
    else:
      self.start_ip = line
      self.range = None
      self.subnet = None
    return True

  def createCommand(self, exclusion_string, global_ports, out_dir):
    # THIS IS ALLOWED. CHECKS HAVE BEEN MADE, bad_ports 
    # will always have a single comma after. self.ports 
    # will never
    if self.ports is not None:
      total_ports = global_ports + self.ports
    else:
      total_ports = global_ports[:-1]

    self.outfile = out_dir + "nmap-T-" + self.start_ip + ".out"

    if len(exclusion_string) > 0 :
      exclude = "--exclude " + exclusion_string[:-1]
    else:
      exclude = ""

    if self.subnet is None and self.range is None:
      key = self.start_ip
    elif self.subnet is None:
      key = self.start_ip + "-" + self.range
    else:
      key = self.start_ip + "/" + self.subnet

    self.command = "nmap -P0 -sT -p " + total_ports + " -oN " + self.outfile \
      + " " + exclude +" " + key + " > /dev/null 2>&1"




