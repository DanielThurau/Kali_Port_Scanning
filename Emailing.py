import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.base import MIMEBase
from email.mime.text import MIMEText
from email.utils import COMMASPACE, formatdate
from email import encoders
import os

def sendMail(to, fro, stats,file, mc, mobile=False, server="localhost"):
    """Send formatted email using information from a BuisnessUnit Object"""
    assert type(to)==list
    print(file)
    subject = "Scan results from Kali on " + formatdate(localtime=True) + ". There are " + str(stats["open"] + stats["open|filtered"]) + " actionable events, and " + str(mc) + " peripherals scanned."
 

    if stats["open"] > 0 or stats["open|filtered"] > 0:
        subject = "ACTION REQUIRED: " + subject 

    text = ""
    for item in stats:
        text = text + item + ":" + str(stats[item]) + "\n"

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

# Example:
#sendMail(['Daniel <daniel.thurau@nbcuni.com>'],'Scanner <Scanner@KaliBox.com>','Hello Python!','Heya buddy! Say hello to Python! :)',['output-perimeter.csv',])
