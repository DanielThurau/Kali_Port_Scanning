BusinessUnit
=============

Purpose of libnmap.process
--------------------------

The purpose of this module is to enable the lib users to launch and control nmap scans. This module will consequently fire the nmap command following the specified parameters provided in the constructor.

It is to note that this module will not perform a full inline parsing of the data. Only specific events are parsed and exploitable via either a callback function defined by the user and provided in the constructor; either by running the process in the background and accessing the NmapProcess attributes will the scan is running.

To run an nmap scan, you need to:

- instanciate NmapProcess
- call the run*() methods

Raw results of the scans will be available in the following properties:

- NmapProcess.stdout: string, XML output
- NmapProcess.stderr: string, text error message from nmap process

To instanciate a NmapProcess instance, call the constructor with appropriate parameters
