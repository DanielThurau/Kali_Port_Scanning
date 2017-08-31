# User Defined Modules
import HTMLGenerator
import Log
import ScanObject
import Upload

# Standard Library Modules
import os

# Business Uni 
class BusinessUnit:
  def __init__(self, p_name, p_path, p_verbose = "", p_org = ""):
    """ BusinessUnit Class Constructor """

    isinstance(p_name, str)
    isinstance(p_path, str)
    isinstance(p_verbose, str)
    isinstance(p_org, str)

    Log.send_log("Scan started on " + p_name)

    # Provided input population
    self.business_unit = p_name
    self.path = p_path
    self.verbose = p_verbose
    self.org = p_org

    # Object populates this when Reading configs
    self.machine_count = 0;
    self.exclude_string = ""
    self.emails = self.mobile = self.links = []
    self.scan_objs = []
    self.stats = {"open":0, "open|filtered":0, "filtered":0, "closed|filtered":0, "closed":0}

    # immediatley populated by checkDeps()
    self.config_dir = self.ports_file = self.ip_file = self.nmap_dir = self.ports = self.outfile = ""
    self.CheckDeps()

  # Check that all neccessary configuration dependancies exist  
  def CheckDeps(self):
    """ Private Method that depends on self.path existing in the object """
    if self.path == "":
      Log.send_log("CheckDeps called on " + self.business_unit + " object but does not contain a self.path defined variable. ")
      exit(0)

    self.config_dir = self.path + "config/"
    self.CheckExist(self.config_dir)

    self.ports_file = self.config_dir + "ports_bad_" + self.business_unit
    self.CheckExist(self.ports_file)

    self.ip_file = self.config_dir + "ports_baseline_" + self.business_unit + ".conf"
    self.CheckExist(self.ip_file)

    # output directory
    self.nmap_dir = self.path + "nmap-" + self.business_unit + "/"
    if not os.path.exists(self.nmap_dir):
      Log.send_log(self.nmap_dir + " does not exist... creating now")
      os.system("mkdir " + self.nmap_dir)

  def CheckExist(self, file):
    isinstance(file, str)
    """ Helper private method for CheckDeps """
    if not os.path.exists(file):
      print(file + " does not exist. Exiting...")
      Log.send_log(file+ " does not exist.")
      exit(0)

  def ReadPorts(self):
    """ Parse and store ports to be general ports in ports_bad_{business_unit}"""
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
          # IMPORTANT. DO NOT REMOVE; SANITIZES USER INPUT
          while self.ports[-1] == ',':
            self.ports = self.ports[:-1]
          self.ports = self.ports + ','

    except IOError:
      Log.send_log("Unable to open " + self.ports_file)
      exit(1)
    Log.send_log("Finished reading ports")


  def ReadBase(self):
    """ Parse and store networks, subnets, ranges, and individual IP's for scanning from ports_baseline_{business_unit}.conf"""
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
            if "-m" in line:
              self.mobile.append(line.split(' ')[0].strip(' \t\n\r'))
            else:
              self.emails.append(line.strip(' \t\n\r'))
            continue

          # Business unit scan object
          if line[0] == "-":
            self.exclude_string = self.exclude_string + line[1:].strip(' \t\n\r') + ","
            continue
          else:
            # create scan object
            BU_SO = ScanObject.ScanObject()
            # populate fields based on line input
            if(BU_SO.Populate(line.strip(' \t\n\r'))):
              # from populated fields, create the command using this data
              BU_SO.CreateCommand(self.exclude_string, self.ports, self.nmap_dir)
              # if not fails. Append to the scan_obj list
              self.scan_objs.append(BU_SO)
              self.machine_count = self.machine_count + BU_SO.GetMachineCount()
    except IOError:
      Log.send_log("Unable to open " + self.ip_file)
      exit(1)
    Log.send_log("Finished reading Commands")

  def Scan(self):
    """Execute scanning commands"""
    pids = []
    for obj in self.scan_objs:
      pid = os.fork()
      if pid != 0:
        pids.append(pid)
      else:
        Log.send_log(obj.command)
        os.system(obj.command)
        exit(0)
    for i in pids:
      os.waitpid(i, 0)


  def ParseOutput(self):
    """Parse and assemble human readable csv report of all nmap results"""
    master_out = []

    for obj in self.scan_objs:
      try:
        with open(obj.outfile, 'r') as f:
          scan_id = ""
          for line in f:
            # Grab title
            if line[:16] == "Nmap scan report":
              scan_id = line[21:].strip(' \n')
              continue

            if line[0].isnumeric():
              # Collect stats
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

              # Parse individual line
              explode = line.split(' ')
              final = scan_id + ","
              for i in explode:
                if len(i) > 0:
                  final = final + i.strip(' \n\t\r') + ","
              master_out.append(final.strip(' \t\r\n'))
      except IOError:
        Log.send_log("Unable to open " + obj.outfile)
        exit(1)
      Log.send_log("File " + obj.outfile + " parsed.")
    return master_out


  def Collect(self):
    out = self.ParseOutput()
    self.outfile = self.nmap_dir + "output-" + self.business_unit + ".csv";
    with open(self.outfile, 'w') as f:
      for line in out:
        f.write(line + "\n")
    Log.send_log("Generated CSV report.")
    # upload Report to DropBox
    self.links = Upload.UploadToDropbox([self.outfile], '/' + os.path.basename(os.path.normpath(self.nmap_dir)) + '/')
    # Generate HMTL
    HTMLGenerator.GenerateHTML(self)


    



