#  Original Author : Rob McNeal
#  limited_port_scan.php
#
#  Description:
#  This script takes in configuration files that provide IP addresses and ports to
#  be scanned. v1.0 uses nmap xml format, and generates web/email accessible reports
#  for immediate notifcation. v1.1 was a fork that will eventually expand on the
#  reporting system, There was a need at NBCUniversal of a cron-job-esque php
#  script that could generate csv reports that reported on the status of select
#  ports on provided IP addresses. v1.1 also uses nmap, and added concurrency because
#  of the massive amount of scannable ports.
# 
#     ___________________________________________________________________________________________________________________________________________
#    |    Version   |       Author         |  Release Date  |       Contact            |                       Comment                           |
#    |______________|______________________|________________|__________________________|_________________________________________________________|
#    |      1.0     |   Rob McNeal         |        ?       | sporac@hotmail.com       |  Original Release. Checkout orig branch.                |
#    |______________|______________________|________________|__________________________|_________________________________________________________|
#    |      1.1     |   Daniel Thurau      |     7-11-17    | Daniel.thurau@nbcuni.com |  First Deployable version inside of NBCU. CLI tool.     |
#    |______________|______________________|________________|__________________________|_________________________________________________________|
#    |      1.2     |   Daniel Thurau      |     7-18-17    | Daniel.thurau@nbcuni.com |  Email notification module.                             |
#    |______________|______________________|________________|__________________________|_________________________________________________________|
#    |      1.3     |   Daniel Thurau      |     7-19-17    | Daniel.thurau@nbcuni.com |  Email module extended heavily. Intro of flags          |
#    |______________|______________________|________________|__________________________|_________________________________________________________|
#    |      1.4     |   Daniel Thurau      |     8-14-17    | Daniel.thurau@nbcuni.com |  Converted to python                                    |
#    |______________|______________________|________________|__________________________|_________________________________________________________|
#
#
#   Usage:
#       A makefile exists in the devel version and any developer should follow that guideline
#       because of specific development env variables.
#
#           {python3} limited_port_scan.php [buisness_unit]
#
#        Refer to the manual for expanded explanation and for config folder setup.
# 

from BusinessUnit import *
from Emailing import *
from Upload import *

from Log import *
import os
import sys

FULL_PATH = os.path.dirname(os.path.realpath(__file__)) + "/"
LOG_FILE = FULL_PATH + ".log"
UNIX_LIKE = True

if(len(sys.argv) != 2):
  print("\nUsage: python3 limited_port_scan.py \{business_unit\}")
  exit(0)

logging.basicConfig(filename=LOG_FILE, level=logging.INFO)

business_unit = BusinessUnit(sys.argv[1], FULL_PATH)

# At this point the object is substantiated and all dependencies have been resolved. 

business_unit.read_file_ports()
business_unit.read_file_base()
business_unit.scan()


business_unit.collect()

links = uploadToDropbox([business_unit.outfile], business_unit.nmap_dir)
print(links)


if len(business_unit.emails) > 0:
    sendMail(business_unit.emails,'Scanner@KaliBox.com',business_unit.stats, business_unit.outfile + ".zip", business_unit.machineCount)

if len(business_unit.mobile) > 0:
  sendMail(business_unit.mobile, 'Scanner@KaliBox.com', business_unit.stats, business_unit.outfile, business_unit.machineCount, True)

# Flush stdout if fork has failed
sys.stdout = sys.__stdout__
sys.stdout.flush()

