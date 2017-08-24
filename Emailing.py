import arrow
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.base import MIMEBase
from email.mime.text import MIMEText
from email.utils import COMMASPACE, formatdate
from email import encoders
import os


utc = arrow.utcnow()
local = utc.to('US/Pacific')



def sendMail(BU, dropboxLinks=[], server="localhost"):
    """Send formatted email using information from a BuisnessUnit Object"""
    
#    to = BU.emails/BU.mobile
#    fro = "Scanner@KaliBox.com
#    stats = BU.stats
#    file = BU.outfile
#    mc = BU.machineCount
#    dropboxlinks = 'passed by ref'
#    mobile = if len(BU.mobiles > 0)
#    server = "localhost" 
    assert type(dropboxLinks)==list

    # Subject Creation
    subject = "Scan-" + local.format('YYYY-MM-DD HH:mm:ss')

    if BU.verbose != "":
        subject = BU.verbose + " " + subject

    if BU.stats["open"] > 0 or BU.stats["open|filtered"] > 0:
        subject = "ACTION REQUIRED-" + subject

    if BU.org != "":
        subject = BU.org + "-" + subject


    text = ""

    if BU.org != "":
        text = BU.org + "\n"

    if BU.verbose != "":
        text = BU.verbose + "-Scan on " + text

    text = text + "     Completed scan on " + local.format('YYY-MM-DD HH:mm:ss') + " with " + str(BU.machineCount) + " machines scanned.\n\n"
    
    if len(dropboxLinks) > 0: 
        text = text + "DropBox download link:\n"
        for link in dropboxLinks:
            text = text + "     " + link + "\n\n"
    
    for item in BU.stats:
        text = text + item + ":" + str(BU.stats[item]) + "\n"

    print(text)
"""
    if mobile == True:
        text = text + "\n\n"
        with open(file, 'r') as f:
            for line in f:
                if 'open' in line:
                    text = text + line

    msg = MIMEMultipart()
    msg['From'] = fro
    msg['To'] = COMMASPACE.join(to)
    msg['Date'] = formatdate(localtime=True)
    msg['Subject'] = subject
        
    msg.attach( MIMEText(text) )

    if mobile == False: 
        part = MIMEBase('application', "octet-stream")
        part.set_payload( open(file,"rb").read() )
        encoders.encode_base64(part)
        part.add_header('Content-Disposition', 'attachment; filename="%s"'
                    % os.path.basename(file))
        msg.attach(part)
    
    try:
        smtp = smtplib.SMTP(server)
        smtp.sendmail(fro, to, msg.as_string() )
        smtp.close()
        print("Successfully sent mail")
    except smtplib.SMTPException as e:
        print("Error: unable to send email")
        print(e)

"""

# Example:
#sendMail(['Daniel <daniel.thurau@nbcuni.com>'],'Scanner <Scanner@KaliBox.com>','Hello Python!','Heya buddy! Say hello to Python! :)',['output-perimeter.csv',])
