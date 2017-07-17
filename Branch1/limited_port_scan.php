#!/usr/bin/php -q
<?php
# ---------------------------------------
# TO DO
# - Alert sending for bad port
# - Logging
# ---------------------------------------


/* 
 * Original Author : Sporac TheGnome 
 * limited_port_scan.php
 * 
 * Description: 
 * This script takes in configuration files that provide IP addresses and ports to
 * be scanned. v1.0 uses nmap xml format, and generates web/email accessible reports 
 * for immediate notifcation. v1.1 was a fork that will eventually expand on the 
 * reporting system, There was a need at NBCUniversal of a cron-job-esque php
 * script that could generate csv reports that reported on the status of select
 * ports on provided IP addresses. v1.1 also uses nmap, and added concurrency because 
 * of the massive amount of scannable ports.
 * 
 *    _________________________________________________________________________________
 *   |    Version   |       Author         |  Release Date  |       Contact            |
 *   |______________|______________________|________________|__________________________|
 *   |      1.0     |   Sporac TheGnome    |        ?       | sporac@hotmail.com       |
 *   |______________|______________________|________________|__________________________|
 *   |      1.1     |   Daniel Thurau      |     7-11-17    | Daniel.thurau@nbcuni.com |
 *   |______________|______________________|________________|__________________________|
 *   |      1.2     |   Daniel Thurau      |     7-17-17    | Daniel.thurau@nbcuni.com |
 *   |______________|______________________|________________|__________________________|
 * 
 *  Usage:
 *      A makefile exists in the devel version and any developer should follow that guideline 
 *      because of specific development env variables.
 *    
 *          php limited_port_scan.php [buisness_unit]
 *
 *       Refer to the manual for expanded explanation and for config folder setup.
 */

// Forking module for running nmaps
// Only availible on unix-like systems because of MacOS and SIP
include("./nmap_child.php");
// Sequential moudle for running nmaps on macOS and Windows
include("./nmap_sequential.php");

// Debug statement, triggers all print statements
// very rudementary debug only on devel branch
$DEBUG = $argv[2];
$LOG_FILE = ".log";
$VERISON = "1.2";
$UNIX_LIKE = true;
date_default_timezone_set("America/Los_Angeles");


//***************
// Change back to 2 (no need for false anymore)
//***************
//# Arguments and Usage
if ( count($argv) != "3" ) {
    print "\nusage: limited_port_scan.php business_unit debug_var\n\n";
    exit;
}

$networks = array();
$ipaddress_exclude_list_array = array();
$businessunit = strtoupper($argv[1]);
send_log(" Scan started on business unit: " . $businessunit);


### Directories / Files ###
// Check existence of config dir
$config_dir = "config";
if (! file_exists($config_dir)) { 
    echo "Configuration directory does NOT exist\n"; 
    send_log(" Scan on $businessunit failed. No config directory.");
    exit(1); 
}

// Check existence/readability of ports_bad file
$ports_bad_file = "$config_dir/ports_bad";
if (! file_exists($ports_bad_file)) { 
    echo "ports_bad_file does NOT exist\n"; 
    send_log(" Scan on $businessunit failed. No ports_bad_file file.");
    exit(1); 
}
if (! is_readable($ports_bad_file)) { 
    echo "ports_bad_file is NOT readable\n"; 
    send_log(" Scan on $businessunit failed. Ports_bad_file unreadable.");
    exit(1); 
}

// check if ports_baseline_$businessunit file exists
$ports_auth_file = "$config_dir/ports_baseline_$businessunit" . ".conf";
if (! file_exists($ports_auth_file)) { 
    echo "ports_auth_file does NOT exist\n"; 
    send_log(" Scan on $businessunit failed. No ports_auth_file.");
    exit(1); 
}

if (! is_readable($ports_auth_file)) { 
    echo "ports_auth_file is NOT readable\n"; 
    send_log(" Scan on $businessunit failed. Ports_auth_file unreadable.");
    exit(1); 
}

// checks if nmap-dir exists (if not create)
$nmap_dir = "nmap-$businessunit";
if (! file_exists($nmap_dir)) { 
    echo "nmap directory ($nmap_dir) does NOT exist\n"; 
    echo ".creating directory...\n";
    system("mkdir $nmap_dir");
    send_log("nmap directory ($nmap_dir) does NOT exist, creating directory....");
}

$nmapOut = $nmap_dir."/output.csv";
if (file_exists($nmapOut)){
    send_log(" Backing up previous NMAP output for $businessunit.");
    $toBeExec = "cp $nmapOut " . $nmap_dir . "/output.backup";
    system($toBeExec);
    xdiff_file_diff($nmapOut, $nmap_dir . "/output.backup", $nmap_dir."/output.diff");
}


# Read Bad Ports
$bad_port_list = read_bad_ports($ports_bad_file);

if($DEBUG == "true"){
    print("------------------------------------\n");
    print "bad_port_list:\n";
    print_r($bad_port_list);
    print "\n";
}

//# Read Auth Ports
$auth_port_list = read_auth_ports($ports_auth_file);
if($DEBUG == "true"){
    print("------------------------------------\n");
    print "auth_port_list:\n";
    print_r($auth_port_list);
    print "\n";
}

# FYI debug
# These are global variables (why i have no idea)
# that should be set 
if($DEBUG == "true"){
    print("-------------------------------------\n");
    print "Networks:\n";
    print_r($networks);
    print("-------------------------------------\n");
    print "IP Address Exclude List:\n";
    print_r($ipaddress_exclude_list_array);
    print "\n";
}

/*
 * Execute an nmap status check and prepare a nmap command 
 */

// Holds all commands that will be run on the cli
$command_block = array();

/* 
 * Iterate through the networks from the config files and 
 * create commands to be exec
 */
foreach ($networks as $value) {
    if($DEBUG == "true"){print "-Checking status $value ...\n";}

    //determine scan status running
    $status = check_status($value);
    if($DEBUG == "true"){print " status: $status\n";}


    # Combine and unique ports
    $port_list = trim($auth_port_list) . "," . trim($bad_port_list);
    $tmp_port_list = explode(",",$port_list);
    $tmp_port_list = array_unique($tmp_port_list);
    if ( $tmp_port_list[0] < "1" ) { array_shift($tmp_port_list); } # remove empty ports

    $port_list = implode(",",$tmp_port_list);
    # double check empty ports
    if ( substr($port_list, 0, 1) == "," ) { $port_list = substr($port_list, 1); } 

    list($net, $sub) = explode("/", $value);
    if($DEBUG == "true"){
        print "$value = net: $net, sub: $sub\n";
    }

    # Exclude IP List
    if (count($ipaddress_exclude_list_array) > 0 ) {
        $ipaddress_exclude_list = implode(",", $ipaddress_exclude_list_array);
        $ipaddress_exclude_list = "--exclude $ipaddress_exclude_list";
    }

    $nmap_output_file = "$nmap_dir/nmap-T-$net.out";

    if ( isset($ipaddress_exclude_list) ) {
        $command_block[$value] = "nmap -P0 -sT -p $port_list -oN $nmap_output_file $ipaddress_exclude_list $value &";
    } else {
        $command_block[$value] = "nmap -P0 -sT -p $port_list -oN $nmap_output_file $value &";
    }
}

/*
 * Iterate through the singular networks from the config files 
 * and create commands to be exec
 */
foreach ($auth_port_list_array as $key=>$value) {
    if($DEBUG == "true"){print "-Checking status $value ...\n";}

    //determine scan status running
    // $status = check_status($value);
    if($DEBUG == "true"){print " status: $status\n";}


    # Combine and unique ports
    $port_list = trim($auth_port_list) . "," . trim($bad_port_list);
    $tmp_port_list = explode(",",$port_list);
    $tmp_port_list = array_unique($tmp_port_list);
    if ( $tmp_port_list[0] < "1" ) { array_shift($tmp_port_list); } # remove empty ports

    $port_list = implode(",",$tmp_port_list);
    # double check empty ports
    if ( substr($port_list, 0, 1) == "," ) { $port_list = substr($port_list, 1); } 

    if(strlen($value) > 0) {$port_list = $value . "," . $port_list;}
    // list($net, $ports) = explode(":", $value);
    // // if($DEBUG == "true"){
    // print("$value = net: $net, port: $ports\n");
    // }

    # Exclude IP List
    if (count($ipaddress_exclude_list_array) > 0 ) {
        $ipaddress_exclude_list = implode(",", $ipaddress_exclude_list_array);
        $ipaddress_exclude_list = "--exclude $ipaddress_exclude_list";
    }

    $nmap_output_file = "$nmap_dir/nmap-T-$key.out";

    

    if ( isset($ipaddress_exclude_list) ) {
        $command_block[$key] = "nmap -P0 -sT -p $port_list -oN $nmap_output_file $ipaddress_exclude_list $key &";
    } else {
        $command_block[$key] = "nmap -P0 -sT -p $port_list -oN $nmap_output_file $key &";
    }
}

/* 
 * Call External PHP modules to perform sequential or concurrent
 * nmap port scans
 */
// if($UNIX_LIKE){
//     nmap_child($command_block);
// }else{
//     nmap_sequential($command_block);
// }




parse_nmap_output($command_block);

send_email();

// ************************************************************************************
// ************************************************************************************
// Above This Line is Documented, Debugged, and Approved Production Code
// Approval by : Daniel Thurau
// ************************************************************************************
// ************************************************************************************




/*

//## Clean and Count
clean_and_count();

if ( is_array($nmap_data) ) {
    ksort($nmap_data);
} else {
    print "####\nNo processed data; exiting\n";
    exit;
}

//print_r($networks);
//print "\n";
//exit;


//# Ensure all scans are complete
//# network only in nmap_data is scan complete (and nmap data parsed)
//# Therefore, complete and parsed if network contained in nmap_data

print "\n#####\ndouble check completed scans before reporting\n";
foreach ($networks as $key=>$network) {
//    print "key: $key\n";
    print "network: $network\n";

    if ( is_array($nmap_data[$network]) > "0" ) {
        print "..network ($network) contained within nmap_data: ok\n";
    } else {
        print "..network ($network) missing from nmap_data: running\n";
        print "####\nA scan is still running; retry later\n";
        exit;
    }
}


//# Generate Report
$report=report();

//print $report;


send_email($report);


//remove files
foreach ($networks as $key=>$network) {

print "#### removing file for $network ...\n";
//    if (is_array($value)) {
        list($net, $sub) = split("/", $network);
        $nmap_output_file = "$nmap_dir/nmap-T-$net.out";

        if (is_file($nmap_output_file)) {
#            print "File: $nmap_output_file exists\n";
#print "rm -f $nmap_output_file\n";
#            exec ("rm -f $nmap_output_file");
        } else {
            print "File: $nmap_output_file does NOT exist\n";
        }
//    }
}

exit;

*/



 
// ************************************************************************************
// ************************************************************************************
// Below This Line is Documented, Debugged, and Approved Production Code
// Approval by : Daniel Thurau
// ************************************************************************************
// ************************************************************************************

/*
 * Read Bad Ports returns a list of ports from config/ports_bad
 * See function read_file to see data definition of ports_bad file
 * or refer to manual for configuration of these files
 */
function read_bad_ports($ports_bad_file) {
    global $DEBUG;

    if (! file_exists($ports_bad_file)) {
        print "\nFile ($ports_bad_file) not found\n\n";
        // exit(1);
    } else {
        # Parse Bad Ports
        if($DEBUG == true){print "#read: $ports_bad_file\n";}

        $output = read_file($ports_bad_file);
        
        $bad_port_list = $output["port_list"];
        
        if($DEBUG == "true"){print "bad_port_list:\n$bad_port_list\n";}

        $bad_port_array = explode(",", $bad_port_list);

        if ( strlen($bad_port_list) < "1" && $DEBUG) {
            print "\nBad port list is empty; Nothing to alert on. Exiting...\n\n";
            // exit(1);
        }
    }

    return($bad_port_list);

}

/*
 * Reads config/ports_baseline_$business_unit.conf
 * See function read_file for data definitions.
 * As of now (section is commented out and I'm still tracing)
 * this function creates two data structures, one is a string  
 * of all ports that can scanned : this data structure is returned
 * the other is key,value array that has ip as key and string of
 * authorized ports as the value
 */


function read_auth_ports($auth_port_file) {
    global $DEBUG;
    global $auth_port_list_array;

    if (! file_exists($auth_port_file)) {
        print "\nFile ($auth_port_file) not found\n\n";
        exit(1);
    } else {
        $output = read_file($auth_port_file);

        if ( isset($output["email_address"]) ) { $email_address = $output["email_address"]; }

        $ip_list = $output["ip_list"];
        # List of auth ports; This is for nmap argument
        # This is returned 
        $auth_port_list = $output["port_list"];     
        # list of auth IP and port information (for nmap parcer)
        # This is set to global
        $auth_port_list_array = $output["auth_port_list_array"];  


        if($DEBUG == "true"){
            print "ip_list:\n$ip_list";
            print "auth_port_list:\n$auth_port_list\n";
            print_r($auth_port_list_array);
        }

        if ( strlen($ip_list) < "1" ) {
            print "\nTarget IP list ($auth_port_file)is empty; Nothing to scan. Exiting...\n\n";
            exit(1);
        } else {
            // Dont know why this is commented out. add ip list to the file
            // but $namsp_target_file doesnt exist
            # Create Target File
//            $fh = fopen($nmap_target_file, 'w') or die("can't open file");
//            fwrite($fh, $ip_list);
//            fclose($fh);
        }
    }

    return($auth_port_list);
}

/*
 * Takes in several formats of files:
 *      comma seperated single line of ports       [21,23,135,445] 
 *      single ip address                          [1.2.3.4]
 *      ip address w/ netmask as cidr              [1.2.3.4/28]
 *      ip address w/ port                         [1.2.3.4:22]
 *      email address                              [john.doe@mail.com]
 *      line comments                              [# Hello World]
 *      inline comments.                           [1.2.3.4 # This is an IP addr]
 *      formatted excluded ip                      [-1.2.3.4]
 * Processes them and joins them into this data definition:
 *
 *      $output[]
 *           $output["ip_list"]
 *           $output["port_list"]
 *           $output["email_address"]
 * 
 * Certain functions overlap the used of ip_list and port list, but since they are 
 * file specific there is no conflict
 */

function read_file($file) {
    # read ports (and IPs)
    global $email_address;
    global $ipaddress_exclude_list_array;
    global $ip_list_array;
    global $networks;
    global $DEBUG;

    $email_address = "";
    $ip_list = "";
    $ip_list_array = array();
    $ips = "";
    $port_list = "";
    $ports = "";
    $auth_port_list_array = array();

    ## Create target list
    if (file_exists($file)) {

        $handle = @fopen($file, "r");
        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {

                # Remove the carrage return
                $buffer = substr($buffer, 0, -1);


                # Remove comments from target list 
                # Does not remove comment lines, 
                # just in line comments - Daniel Thurau
                if ( strpos($buffer, '#') > "1" ) {
                   if($DEBUG == "true"){echo "contains comment: $buffer\n";}
                   $xxx = explode('#', $buffer);
                   if($DEBUG == "true"){print_r($xxx);}
                   $buffer = $xxx[0];
                   $buffer = trim($buffer);
                   unset($xxx);
                   if($DEBUG == "true"){echo "*$buffer*\n";}
                }


                # Ignore comment lines
                if ( substr($buffer, 0,1) == "#" || trim($buffer) == "") {
                    if($DEBUG == "true"){print("line comment: $buffer\n");}
                    # ignore this line
                    // pass;
                } elseif ( substr($buffer, 0,1) == "-" ) {
                    # add to exclude list
                    if($DEBUG == "true"){print("excluded IP: $buffer\n");}
                    array_push($ipaddress_exclude_list_array, substr($buffer, 1));
                } elseif ( strpos($buffer, "/") > "0" ) {
                    //# Network
                    if($DEBUG == "true"){print "Network: $buffer\n";}
                    array_push($networks, $buffer);

                } else {
                    # Process line
                    if($DEBUG == "true"){print "#line:$buffer";}

                    if ( strpos($buffer,"@") > "0" ) {
                        # line contains @ , so contains email address
                        $email_address = $buffer;
                        if($DEBUG == "true"){print "email: $email_address\n";}
                    } elseif ( strpos($buffer,":") > "0" ) {
                        # line contains : , so has ip and ports
                        list($ip, $ports) = explode(":", $buffer);
                        if($DEBUG == "true"){print "ip: $ip\nports: $ports\n";}
                        $ip_list .= $ip . "\n";
                        array_push($ip_list_array, $ip);


                        # Convert names to IPs 
                        # converts ip's but fails on hostname
                        $long = ip2long($ip);
                        if($DEBUG == "true"){print "#ip: $ip long: $long\n";}
                        if ($long == -1 || $long === FALSE) {
                            # Get he IP address of the hostname if located on inet
                            $ip = gethostbyname($ip);
                        }

                        if ( strlen($ports) > 0 ) {
                            $auth_port_list_array[$ip] = $ports;
                        } else {
                            # Possible issue in port detect, 
                            # if no auth ports, so include port 0
                            $auth_port_list_array[$ip] = 0;
                        }




                    } else {
                        # line contains IP/hostname only
                        $ports = $buffer;
                    }

                    // Add a comma a the end of the line if not empty string ?
                    // What the fuck - Daniel Thurau (i see why + 30 lines from here)
                    // will always remove that comma
                    if ( strlen($ports) > 0 ) {
                        $port_list .= $ports . ",";
                    }
                }
            }
            // Error Handling
            if (!feof($handle)) {
                echo "Error: unexpected fgets() fail\n";
            }
            fclose($handle);
        // Error Handling
        }else{exit("Failed to open file\n");}
    // Error Handling
    } else {
        print "file ($file) not found\n";
        exit(1);
    }
    # Cleanup

    # Remove trailing comma
    $port_list = substr($port_list, 0, -1);

    # Unique ports
    // sepeartes on comma, sorts, glues back together on comma unt sinlge string
    $pieces = explode(",", $port_list);
    $pieces_unique = array_unique($pieces);
    sort($pieces_unique);
    $port_list = implode(",", $pieces_unique);
    unset($pieces); unset($pieces_unique);

    if($DEBUG == "true"){
        print "ip_list:\n$ip_list";
        print "port_list:\n$port_list\n";
        print_r($ip_list_array);
    }

    if ( strlen($email_address) > 0) { $output["email_address"]=$email_address; }
    $output["ip_list"]=$ip_list;
    $output["port_list"]=$port_list;
    $output["auth_port_list_array"]=$auth_port_list_array;

    if($DEBUG == "true"){
        print "output:\n";
        print_r($output);
    }

    return($output);

}

/*
 * This functuin is called on a string of the form [1.2.3.4/56]
 * And returns the status of the nmap running on this network.
 * It returns a string ["no_file" | "complete" | "running"]
 */
function check_status($value) {
    global $nmap_dir;
    global $DEBUG;

    // Split into network ip address and mask
    if(strpos($buffer_id, "/") > "0"){
        list($net, $sub) = explode("/", $value);
    }else{
        $net = $value;
    }
    if($DEBUG == "true"){ print "$value = net: $net, sub: $sub\n";}

    $nmap_output_file = "$nmap_dir/nmap-T-$net.out";

    if (file_exists($nmap_output_file)) {
        # nmap_outfile exists (nmap running or finished)
        // Get head of nmap file
        $head = system("head -n 1 $nmap_output_file");
        $head_check = substr($head, 0, 13);
        // Get end of nmap file
        $end = system("tail -n 1 $nmap_output_file");
        $end_check = substr($end, 0, 10);
        
        // Header check
        if ( $head_check != 'Starting Nmap' ) {
            // if($DEBUG == "true"){print "*$head1*\n";}
            if($DEBUG == "true"){print "Incorrect scan format, exiting. \n\n";}
        } else {
            // Footer Check
            if ( ($end_check == 'Nmap done:') ) {
                if($DEBUG == "true"){print "## scan Finished ##\n\n";}
                return("complete");
            } else {
                if($DEBUG == "true"){print "## scan running ... ##\n\n";}
                return("running");
            }
        }
    } else {
        if($DEBUG == "true"){ print "## no file; launch scan\n";}
        return("no_file");
    }
}













function parse_nmap_output($commands) {
    var_dump($value);
    global $DEBUG;
    global $nmap_dir;
    global $auth_port_list_array;
    global $bad_port_list;
    global $ipaddress_exclude_list_array;
    global $master_nmap_out;
    global $businessunit;
    $master_nmap_out = array();

    if($DEBUG == "true"){print "+Parse nmap output $value\n";}
    foreach ($commands as $key => $value) {
        if(strpos($key, "/") > "0"){
            list($net, $sub) = explode("/", $key);
        }else{
            $net = $key;
        }
        $file = "$nmap_dir/nmap-T-$net.out";
        // print($file . "\n");
        if(!file_exists($file)){exit(1);}
        $handle = @fopen($file, "r");
        while (($buffer = fgets($handle, 4096)) !== false) {
            // print(substr($buffer,0,16)."\n");
            if(substr($buffer,0,16) == "Nmap scan report"){
                $id = trim(substr($buffer, 21));
                // print($id);
                while (($buffer_id = fgets($handle, 4096)) != "\n"){
                    if(strpos($buffer_id, "/") > "0"){
                        $buffer_id = explode(" ", $buffer_id);
                        $buffer_id_final = "";
                        foreach($buffer_id as $item){
                              $item = trim($item);
                              $item.= ",";
                              if($item != ","){$buffer_id_final.=$item;}
                        }
                        array_push($master_nmap_out, $id . "," . $buffer_id_final . "\n");
                    }
                }

            }
        }
        
    }


    $file = "$nmap_dir/output-$businessunit.csv";
    $handle = @fopen($file, "w+");
    if($handle){
        fwrite($handle, "IP,Port,Status,Type\n");
        foreach ($master_nmap_out as $value) {
            fwrite($handle, $value);
        }        
    }

}


function send_log($message){
    global $LOG_FILE;
    error_log(date('l jS \of F Y h:i:s A') . $message . "\n", 3, $LOG_FILE);
}

// ************************************************************************************
// ************************************************************************************
// Above This Line is Documented, Debugged, and Approved Production Code
// Approval by : Daniel Thurau
// ************************************************************************************
// ************************************************************************************

/*

##############
## Port Status
function port_status($IP, $port, $state) {

    global $auth_port_list_array;
    global $bad_port_list;

#print "auth_port_list_array:\n";
#print_r($auth_port_list_array);
#print "\n";

    $status = "";
    $bad_ports = explode(",", $bad_port_list);
#echo "bad_ports:\n";
#print_r($bad_ports);

    if ( $state == "open" ) {

        //# For known IPs
        if (array_key_exists($IP, $auth_port_list_array)) {
            $auth_ports = $auth_port_list_array[$IP]; 
            $pieces = explode(",", $auth_ports);

            if (in_array($port, $pieces)) {
                //print "auth\n";
                $status = "auth";
            } elseif ( in_array($port, $bad_ports) ) {
                //print "bad\n";
                $status = "bad";
            } else {
                //print "unknown\n";
                $status = "unknown-port";
            }
        } else {
            //# For Unknown IPs
            if ( in_array($port, $bad_ports) ) {
                #print "bad\n";
                $status = "unknown_host-bad_port";
            } else {
                $status = "unknown_host-unknown_port";
            }
        }

    }

#print "status: $status\n";
    return($status);
}
## Port Status - end
*/



/*

#### Clean and Count - End
function clean_and_count() {
    global $nmap_data;
    global $DEBUG;

    if($DEBUG){
        echo "nmap_data:\n";
        print_r($nmap_data);
    }


    if ( is_array($nmap_data) ) {
        foreach ($nmap_data as $network=>$value) {
            print "cleaning $network ...\n";

            $host_cnt = 0;
            $host_auth_cnt = 0;
            $host_unknown_cnt = 0;

            //# Host
            if ( count($value) > "6" ) {
                foreach ($value as $IP=>$value2) {
                    if ( isset($value2["ports"]) && (strlen($value2["ports"]) ) < "1" ) {
//print "### ports empty; remove host $IP ...\n";
                        unset($nmap_data[$network][$IP]);
                    } elseif ( isset($value2["ports"]) && (strlen($value2["ports"]) ) > "1" ) {
                        $host_cnt++;
                        $nmap_data[$network]["host_count"] = $host_cnt;
//print "### host with ports +1; $cnt ...\n";
                    }

                //## Unknown Host count
                    if ( isset($value2["host_status"]) && ($value2["host_status"] == "unknown" ) ) {
                        $host_unknown_cnt++;
                    } elseif ( isset($value2["host_status"]) && ($value2["host_status"] == "authorized" ) ) {
                        $host_auth_cnt++;
                    }
                }
            }
            $nmap_data[$network]["host_unknown_count"] = $host_unknown_cnt;

            $nmap_data["total_status_port_auth"] = $nmap_data["total_status_port_auth"] + $nmap_data[$network]["status_port_auth"];
            $nmap_data["total_status_port_bad"] = $nmap_data["total_status_port_bad"] + $nmap_data[$network]["status_port_bad"];
            $nmap_data["total_status_port_unknown"] = $nmap_data["total_status_port_unknown"] + $nmap_data[$network]["status_port_unknown"];

            $nmap_data["total_host_count"] =+ $nmap_data["total_host_count"] + $nmap_data[$network]["host_count"];
            $nmap_data["total_host_unknown_count"] =+ $nmap_data["total_host_unknown_count"] + $host_unknown_cnt;
            $nmap_data["total_host_auth_count"] =+ $nmap_data["total_host_auth_count"] + $host_auth_cnt;
        }
    }
}

/*


##############
## Report
function report() {

    global $auth_port_list_array;
    global $auth_port_list;
    global $bad_port_list;
    global $businessunit;
    global $count_bad_ports;
    global $nmap_data;
    global $ipaddress_exclude_list_array;


    $report = "";

    //# HTML Header
    $report .= "<html>";
    $report .= "<head><title>TCP Port Report</title>";
    $report .= "<style> 
table {
    border-collapse: collapse;
    width: 50%;
}

th, td {
    text-align: left;
}

tr:nth-child(even){background-color: #f2f2f2}
</style></head>";
    $report .= "<body>";


    //# Report
    $report .= "\r\nTCP Host/Port Discovery Report<br>\n\n";

    $report .= "Business Unit: <b>$businessunit</b> <br>&nbsp; <p>\n\n";
    
    //# Excluded IPs
    if ( count($ipaddress_exclude_list_array) > 0 ) {
        print "Excluded IP list coming soon(ish)\n\n";
    }

    $numBad = intval($nmap_data["total_status_port_bad"]); 
    (int) $numBad;

    $report .= "<table>";
    $report .= "<tr><th><b><p>Port Summary</b></th></tr>";
    $report .= "<tr><td>Dangerous/Bad</td><td align=center>";

    if ($numBad > 0) { $report .= "<font color=red>";} 

    $report .= $numBad . "</td></tr>\n";
    $report .= "<tr><td>Not classified</td><td align=center> ". $nmap_data["total_status_port_unknown"] ."</td></tr>\n";
    $report .= "<tr><td>Authorized</td><td align=center> ". $nmap_data["total_status_port_auth"] ."</td></tr>\n";
    $total_ports = $nmap_data["total_status_port_bad"] + $nmap_data["total_status_port_unknown"] + $nmap_data["total_status_port_auth"];
    $report .= "<tr bgcolor=\"#F2F2F2\"><td align=center><b>TOTAL</b></td><td align=center><b> $total_ports</b></td></tr>";
    $report .= "</table>";

    $report .= "&nbsp; <br> ";

    $report .= "<table>";
    $report .= "<tr><th><b>Host Summary</b></th></tr>";
    $report .= "<tr><td>Not Classified</td><td align=center> ". $nmap_data["total_host_unknown_count"] ."</td></tr>\n";
    $report .= "<tr><td>Authorized</td><td align=center> ". $nmap_data["total_host_auth_count"] ."</td></tr>\n";
    $report .= "<tr bgcolor=\"#F2F2F2\"><td align=center><b>TOTAL</b></td><td align=center><b>". $nmap_data["total_host_count"] ."</b></td></tr>";
    $report .= "</table>";

    $report .= "<p>\n\n";


//    $report .= "Unknown Hosts: ". $nmap_data["total_host_unknown_count"] ."<br>\n";
//    $report .= "Autorized Hosts: ". $nmap_data["total_host_auth_count"] ."<br>\n";
//    $report .= "<b>TOTAL HOSTS: ". $nmap_data["total_host_count"] ."</b><p>\n\n";


    $report .= "A port scan was conducted against the following TCP ports:<br>\n";

    //# Combine port lists
    $port_list = explode(",", $auth_port_list);
    $count_auth_ports = count($port_list);
    $port_list = implode(", ", $port_list);
    $report .= "Authorized Ports $count_auth_ports (tcp $port_list)<br>\n";

    $bad_port_array = explode(",", $bad_port_list);
    $count_bad_ports = count($bad_port_array);
    $bad_ports = implode(", ", $bad_port_array);
    $report .= "Dangerous Ports $count_bad_ports (tcp $bad_ports)<p>\n";



    $report .= "\n\nThe following is a status of each network/individual system for the business unit:<br>\n";

    $report .= "<table>";
    $report .= "<tr>";
    $report .= "<td><b>Network</b></td><td width=25> </td>\t\t<td align=center><b>Bad<br>Ports</b></td>\t<td align=center><b>Unknown<br>Ports</b></td>\t<td align=center><b>Auth<br>Ports</b></td><td width=25> </td>\t\t<td align=center><b>Unknown<br>Host</b></td>\t<td align=center><b>Auth<br>Host</b></td>\n";

    $report .= "</b></tr>";
    $report .= "<tr>";

    if ( is_array($nmap_data) ) {
        foreach ($nmap_data as $network=>$value) {

            if ( isset($value["duration"]) ) {
                $host_auth = 0;
                $host_auth = $value["host_count"] - $value["host_unknown_count"];

//                if ( strlen($network) < "16" ) {
    $report .= "<tr>";
                    $report .= "<td>$network</td><td> </td>\t\t<td align=center>". $value["status_port_bad"] ."</td>\t\t<td align=center>". $value["status_port_unknown"] ."</td>\t\t<td align=center>". $value["status_port_auth"] ."</td><td> </td>\t\t\t<td align=center>". $value["host_unknown_count"] ."</td>\t\t<td align=center>$host_auth</td>\n";
    $report .= "</tr>";
//                } else {
//                    $report .= "$network\t". $value["status_port_bad"] ."\t\t". $value["status_port_unknown"] ."\t\t". $value["status_port_auth"] ."\t\t\t". $value["host_unknown_count"] ."\t\t$host_auth\n";
//                }
            }
        }
    }

    $total_host_auth = 0;
    $total_host_auth = $nmap_data["total_host_count"] - $nmap_data["total_host_unknown_count"];

    $report .= "<tr bgcolor=\"#F2F2F2\">";
    $report .= "\n<td align=center><b>TOTAL</b></td><td> </td>\t\t\t\t<td align=center><b>". $nmap_data["total_status_port_bad"] ."</b></td>\t\t<td align=center><b>". $nmap_data["total_status_port_unknown"] ."</b></td>\t\t<td align=center><b>". $nmap_data["total_status_port_auth"] ."</b></td><td> </td>\t\t\t<td align=center><b>". $nmap_data["total_host_unknown_count"] ."</b></td>\t\t<td align=center><b>$total_host_auth</b></td>\n\n";
    $report .= "</tr>";

    $report .= "</table>";
    $report .= "<p>";


    //## Unknown Hosts
    $report .= "Unknown Hosts Detected - Hosts containing only Unauthorized ports<br>\n---------------<br>\n";
    $host_exception_cnt = 0;
    if ( is_array($nmap_data) ) {
        foreach ($nmap_data as $network=>$value) {
#echo"network: $network:\n";
#print_r($value);
            if ( count($value) > "6" ) {
                foreach ($value as $IP=>$value2) {
                    if ( $value2["host_status"] == "unknown" ) {
                        $report .= "$IP:". $value2["ports"] ."<br>\n";
                        $host_exception_cnt++;
                    }
                }
            }
        }
    }
    if ( $host_exception_cnt == "0" ) { $report .= "No exceptions<br>\n"; }

    print "<p>\n\n";

    //## Port Exceptions
    $report .= "<br>Unauthorized Ports Detected - Dangerous or Unauthorized ports<br>\n-------------<br>\n";
    $port_exception_cnt = 0;
    if ( is_array($nmap_data) ) {
        foreach ($nmap_data as $network=>$value) {
            if ( count($value) > "6" ) {
                foreach ($value as $IP=>$value2) {
                    $ports = $value2['ports']; 
                    if ( (strpos($ports,'*')) || (strpos($ports,'?')) ) {
                        $report .= "<font color=red>$IP:". $value2["ports"] ."</font><br>\n";
                        $port_exception_cnt++;
                    }
                }
            }
        }
    }
    if ( $port_exception_cnt == "0" ) { $report .= "No exceptions<br>\n"; }

$report .= "<p>\n";
$report .= "Notes:<br>To change the authorized ports, send an email to rmcneal@teksecurelabs.com<br>\n";

//    $report .= "</body><html>";

//print "\n". $report ."\n";


    return($report);
}
## Report - end


*/
##############
## Send Email
function send_email() {
    global $businessunit;
    global $email_address;
    global $nmap_data;


    $date = date("j M Y");
    echo $date;
//     $nmap_status_port_bad = $nmap_data["total_status_port_bad"];
//     $nmap_status_port_unknown = $nmap_data["total_status_port_unknown"];
//     $nmap_host_unknown_count = $nmap_data["total_host_unknown_count"];

//     if ( strpos($email_address,"@") > "0" ) {
//         $email_address .= ",rmcneal@teksecurelabs.com";
//     } else {
//         $email_address = "rmcneal@teksecurelabs.com";
//     }

//     $headers  = 'MIME-Version: 1.0' . "\r\n";
//     $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
//     $headers .= 'MIME-Version: 1.0' . "\r\n";
// //    $headers .= "Importance: Normal\n";

// //    $headers = "From: Bob McNeal <rmcneal@teksecurelabs.com>;\n";
//     $headers .= "From: TCP Detect <scanning@socops.net>;\n";
// //    $headers .= "MIME-Version: 1.0\n";
// //    $headers .= "X-Priority: 2\n";
// //    $headers .= "X-MSMail-Priority: Normal\n";
// //        $headers .= "Content-Type: text/html; charset=\"iso-8859-1\"\r\n";
// //    $headers .= "Content-Type: text/html;   charset=\"us-ascii\"\n";
// //    $headers .= "Content-Transfer-Encoding: 7bit\n";
// //    $headers .= "Importance: Normal\n";

//     // # Format: mail ( string $to , string $subject , string $message [, string $additional_headers);
//     mail ( $email_address, "($nmap_status_port_bad Bad ports; $nmap_status_port_unknown Unclassified ports; $nmap_host_unknown_count Unknown hosts) TCP Host/Port Report - $businessunit - $date", $report, $headers);

}
## Send Email - End



?>
