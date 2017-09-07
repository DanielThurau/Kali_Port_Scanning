from portscan import BusinessUnit

from portscan import Emailing

from portscan import Log

import argparse
import os
import sys

FULL_PATH = os.path.dirname(os.path.realpath(__file__)) + "/"
UNIX_LIKE = True
BUSINESS_PATH="/scanning/Kali_Port_Scanning/external.csv"

parser = argparse.ArgumentParser(description='Lets scan some ports')

parser.add_argument("business_unit", help="The business unit the scan will be performed on")
parser.add_argument("-b", "--businessName", help="Additional information for more verbose emails")
parser.add_argument("-o", "--org", help="Additional information on the organization for this scan")


args = parser.parse_args()

bs = org = ""
if args.businessName:
    bs = args.businessName
if args.org:
    org = args.org

business_unit = BusinessUnit.BusinessUnit(args.business_unit, FULL_PATH, bs, org)

# At this point the object is substantiated and all dependencies have been resolved. 

business_unit.ReadPorts()
business_unit.ReadBase()

business_unit.Scan()


business_unit.Collect(BUSINESS_PATH)


if len(business_unit.emails) > 0 or len(business_unit.mobile) > 0:
    Emailing.SendMail(business_unit)

# Flush stdout if fork has failed
sys.stdout = sys.__stdout__
sys.stdout.flush()
