Welcome to Kali_Port_Scanning's developer documentation!
========================================================

About Kali_Port_Scanning
-------------

Kali_Port_Scanning is a complex wrapper around the nmap command line utility. 

Current modules

- BusinessUnit: Creates and structures data per business unit.
- ScanObject: Holds the scanning information for each IP segment.
- Emailing: Configures push notifications via emails.
- HTMLGenerator: Programatically generates HTML output.
- Log: Allows logging of key features.
- Upload: Allows uploading of reports to DropBox


libnmap's modules
-----------------

The full `source code <https://github.com/msadekuni/Kali_Port_Scanning>`_ is available on GitHub.
The different modules are documented below:

.. toctree::
   :maxdepth: 2
   :glob:

   BusinessUnit
   ScanObject
   Emailing
   HTMLGenerator
   Log
   Upload

Indices and tables
==================

* :ref:`genindex`
* :ref:`modindex`
* :ref:`search`