# Kali_Port_Scanning

This script takes in configuration files that provide IP addresses and ports to be scanned. v1.0 uses nmap xml 
format, and generates web/email accessible reports for immediate notifcation. v1.1 was a fork that will eventually 
expand on the reporting system, There was a need at NBCUniversal of a cron-job-esque php script that could 
generate csv reports that reported on the status of select ports on provided IP addresses. v1.1 also uses nmap, 
and added concurrency because of the massive amount of scannable ports. v1.2 introduced immediate email notification
for infoSec security analysts to respond quickly.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

### Prerequisites

It is recommended to have PHP 7.x.x or greater, but PHP 5.1 or greater will work.

This script acts as a complex wrapper around nmap and as such nmap is a required program to have on your system.
        Nmap is availible on Win systems, but may be more complex to use. This script relies on spawing background processes
        and configuring that is much more complex (look for solution in v1.2)

For super users who will be scanning hundereds/thousands of ports and IP addresses may want to enable concurrency. This
entails enabling the php pcntl package which is not always enabled. Consult your verison of PHP as well as OS for more
info. A useful guide can be found at http://php.net/manual/en/pcntl.setup.php. How to Enable concurrency will be
addressed later in this manual.

A feature of this script is sending live updates to specified email handles. PHP uses its default email client/protocol/port specified
inside of its php.ini file. Be sure to consult your php build, as of php7.1.8 none of the default smtp settings are enabled. It is
recommended to also secure your machine to not allow it to be a forwarding node, so be sure to research how to disable this feature
on your specific OS.

```
sudo apt-get install nmap
sudo apt-get install php7.1-cli
```

### Installing

A step by step series of examples that tell you have to get a development env running

Say what the step will be

```
git clone https://github.com/DanielThurau/Kali_Port_Scanning.git
cd Kali_Ports_Scanning
```

Basic running of example config

```
cp config/ports_bad_template.template config/ports_bad_template
cp config/ports_baseline_template.conf.template config/ports_baseline_template.conf
php limited_port_scan.php template
```

This will run several mnap commands on local host IP's. To view live updates of the script running run command

```
tail -f .log 
```

The tool will output 

```
Scan Complete
``` 

when the report has been generated. To view, use you favorite editor to view.

```
[editor] nmap-template/output-template.csv
```

## Configuration

Two configuration files are required to have a successful run of limited_port_scan and several configurable variables 
within the script are availible for an advanced user to tweak.

config/

In the config folder, a ports_bad_{business_unit} file must exist. A ports_bad_template.template file exists in the 
directory and can be a good starting off point.
                
This file contains universal ports that the user wants to be scanned on every IP provided in ports_baseline_{buisness_unit}.conf. These ports must be comma seperated and on a single line. 
Do not add port numbers to this file if you DO NOT want them scanned on every IP.

The ports_baseline_{business_unit}.conf file contains the specific IP addresses, ports, and Networks that the user 
wants to be scanned and reported on. There are some basic ways to include these in the file:

                        single ip address                          [1.2.3.4]
                                A single IP address will have its ports that are specified in the 
				ports_bad file scanned ONLY.

                        ip address w/ netmask as cidr              [1.2.3.4/28]
                                The entire newtork specified by the IP and cidr will be scanned using 
				the ports in the pad_ports file ONLY.

                        ip address w/ port                         [1.2.3.4:22]
                                A single IP address with a port will have its ports that are specified in the 
				ports_bad file scanned AS WELL as the specified port.

                        line comments                              [# Hello World]
                                This line will be ignored.

                        inline comments.                           [1.2.3.4 # This is an IP addr]
                                Anything after the # will be ignored.

                        formatted excluded ip                      [-1.2.3.4]
                                This IP will not be scanned.
Super-User Variables:

        Default log location is the current directory in a hidden file named .log. This can be changed by specifying
	a path and name in variable $LOG_FILE

        To enable concurrency, default is sequential, find the $UNIX_LIKE variable and change its boolean value to true.

        If timezone is not set by default in your php.ini file, set it in the beggining of the script using
	date_default_timezone_set("{Timezone}")
	
	
## Authors

* **Sporac TheGnome** - *Initial work*
* **Daniel Thurau** - *v1.1-1.2* - [DanielThurau](https://github.com/DanielThurau)

See also the list of [contributors](https://github.com/your/project/contributors) who participated in this project.



## Acknowledgments

* Hat tip to Sporac TheGnome who did much of the grunt work on a previous project and selflessly gave code 
* to NBCU to replace a previous service.





/* 
 * README
 * Description: This is the manual for v1.2 of limited_port_scan
 * Author: Daniel Thurau
 * Contact: Daniel.Thurau@nbcuni.com
 * Date: 7-25-17
 */


 limited_port_scan.php is a command line php script that takes in two config files and will generate csv reports on the openess or closedness of specfic ports on specified IP addresses.


 1. Requirements
 2. Usage
 3. Configuration
 4. Extensibility
 5. Reports





--------- Requirements ---------

It is recommended to have PHP 7.x.x or greater, but PHP 5.1 or greater will work.

This script acts as a complex wrapper around nmap and as such nmap is a required program to have on your system. 
	Nmap is availible on Win systems, but may be more complex to use. This script relies on spawing background processes 
	and configuring that is much more complex (look for solution in v1.2)

For super users who will be scanning hundereds/thousands of ports and IP addresses may want to enable concurrency. This 
entails enabling the php pcntl package which is not always enabled. Consult your verison of PHP as well as OS for more 
info. A useful guide can be found at http://php.net/manual/en/pcntl.setup.php. How to Enable concurrency will be 
addressed later in this manual.

A feature of this script is sending live updates to specified email handles. PHP uses its default email client/protocol/port specified
inside of its php.ini file. Be sure to consult your php build, as of php7.1.8 none of the default smtp settings are enabled. It is 
recommended to also secure your machine to not allow it to be a forwarding node, so be sure to research how to disable this feature 
on your specific OS.

--------- Usage ---------

php limited_port_scan.php business_unit

Buisness_unit will be the universal name you add to the end of ports_baseline_{business_unit}.conf and will be used to create the 
nmap output directory named nmap-{business_unit} and the final csv report file named output-{business_unit}.csv, so choose wise
meaningful names. In your config directory, if there is no file: ports_bad or ports_baseline_{business_unit}.conf the script will
abort and send logs to your .log file.

--------- Configuration ---------

Two configuration files are required to have a successful run of limited_port_scan and several configurable variables within the 
script that an advanced user can tweak.

config/
	
		In the config folder, a ports_bad file must exist. A ports_bad.template file exists in the directory and can be a good starting off point.
		This file contains universal ports that the user wants to be scanned on every IP provided in ports_baseline_{buisness_unit}.conf. These ports
		must be comma seperated and on a single line. Do not add port numbers to this file if you DO NOT want them scanned on every IP.

		The ports_baseline_{business_unit}.conf file contains the specific IP addresses, ports, and Networks that the user wants to be scanned and
		reported on. There are some basic ways to include these in the file:

			single ip address                          [1.2.3.4]
				A single IP address will have its ports that are specified in the ports_bad file scanned ONLY.

			ip address w/ netmask as cidr              [1.2.3.4/28]
				A network will have every IP starting at the IP and covered by the mask will have their ports 
				that are specified in the ports_bad file scanned ONLY.

			ip address w/ port                         [1.2.3.4:22]
				A single IP address with a port will have its ports that are specified in the ports_bad file 
				scanned AS WELL as the specified port.

			line comments                              [# Hello World]
				This line will be ignored.

			inline comments.                           [1.2.3.4 # This is an IP addr]
				Anything after the # will be ignored.

			formatted excluded ip                      [-1.2.3.4]
				This IP will not be scanned.


Super-User Variables:

	Default log location is the current directory in a hidden file names .log. This can be changed by specifying a path and name in variable $LOG_FILE

	To enable concurrency, default is sequential, find the $UNIX_LIKE variable and change its boolean value to true.

	If timezone is not set by default in your php.ini file, set it in the beggining of the script using date_default_timezone_set("{Timezone}")



--------- Extensibility ---------

This is a command line tool, and as such many personal scripts can be used to extend its abilities. It is recommended to have a makefile for developers 
because there are several cli arguments on the development branch of this tool.

One very useful extension is to configure a cron job on linux systems that on a schedule will re-scan each business unit and create output reports.

--------- Reports ---------

The reports are created in the nmap-{business_unit} directory and will have format of [ IP ADDRESS | PORT | STATUS | TYPE ]


