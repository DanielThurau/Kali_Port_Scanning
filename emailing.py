import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.base import MIMEBase
from email.mime.text import MIMEText
from email.utils import COMMASPACE, formatdate
from email import encoders
import os

def sendMail(to, fro, subject, text, files=[],server="localhost"):
    print(to)
    print(fro)
    print(subject)
    print(text)
    print(files)
    assert type(to)==list
    assert type(files)==list


    msg = MIMEMultipart()
    msg['From'] = fro
    msg['To'] = COMMASPACE.join(to)
    msg['Date'] = formatdate(localtime=True)
    msg['Subject'] = subject

    msg.attach( MIMEText(text) )

    for file in files:
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
