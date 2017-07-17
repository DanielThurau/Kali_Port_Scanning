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
 *
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
$LOG_FILE = ".log";
$VERISON = "1.2";
$UNIX_LIKE = true;
date_default_timezone_set("America/Los_Angeles");


//***************
// Change back to 2 (no need for false anymore)
//***************
//# Arguments and Usage
if ( count($argv) != "2" ) {
    print "\nusage: limited_port_scan.php business_unit \n\n";
    exit;
}

$networks = array();
$ipaddress_exclude_list_array = array();
$businessunit = $argv[1];
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
    send_log(" Nmap directory ($nmap_dir) does NOT exist, creating directory....");
}

$nmapOut = $nmap_dir."/output-$businessunit.csv";
print($nmapOut);
if (file_exists($nmapOut)){
    send_log(" Backing up previous NMAP output for $businessunit.");
    $toBeExec = "cp $nmapOut " . $nmap_dir . "/output.backup";
    system($toBeExec);
}


# Read Bad Ports
$bad_port_list = read_bad_ports($ports_bad_file);

//# Read Auth Ports
$auth_port_list = read_auth_ports($ports_auth_file);

// Holds all commands that will be run on the cli
$command_block = array();

/*
 * Iterate through the networks from the config files and
 * create commands to be exec
 */
foreach ($networks as $value) {
    //determine scan status running
    $status = check_status($value);

    # Combine and unique ports
    $port_list = trim($auth_port_list) . "," . trim($bad_port_list);
    $tmp_port_list = explode(",",$port_list);
    $tmp_port_list = array_unique($tmp_port_list);
    if ( $tmp_port_list[0] < "1" ) { array_shift($tmp_port_list); } # remove empty ports

    $port_list = implode(",",$tmp_port_list);
    # double check empty ports
    if ( substr($port_list, 0, 1) == "," ) { $port_list = substr($port_list, 1); }

    list($net, $sub) = explode("/", $value);

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
    # Combine and unique ports
    $port_list = trim($auth_port_list) . "," . trim($bad_port_list);
    $tmp_port_list = explode(",",$port_list);
    $tmp_port_list = array_unique($tmp_port_list);
    if ( $tmp_port_list[0] < "1" ) { array_shift($tmp_port_list); } # remove empty ports

    $port_list = implode(",",$tmp_port_list);
    # double check empty ports
    if ( substr($port_list, 0, 1) == "," ) { $port_list = substr($port_list, 1); }

    if(strlen($value) > 0) {$port_list = $value . "," . $port_list;}

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
if($UNIX_LIKE){
     nmap_child($command_block);
}else{
     nmap_sequential($command_block);
}


parse_nmap_output($command_block);
send_email();
/*
 * Read Bad Ports returns a list of ports from config/ports_bad
 * See function read_file to see data definition of ports_bad file
 * or refer to manual for configuration of these files
 */
function read_bad_ports($ports_bad_file) {
    if (! file_exists($ports_bad_file)) {
        print "\nFile ($ports_bad_file) not found\n\n";
        // exit(1);
    } else {
        # Parse Bad Ports
        $output = read_file($ports_bad_file, True);

        $bad_port_list = $output["port_list"];

        $bad_port_array = explode(",", $bad_port_list);
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
    global $auth_port_list_array;
    global $networks;

    if (! file_exists($auth_port_file)) {
        print "\nFile ($auth_port_file) not found\n\n";
        exit(1);
    } else {
        $output = read_file($auth_port_file, False);

        if ( isset($output["email_address"]) ) { $email_address = $output["email_address"]; }
        $ip_list = $output["ip_list"];
        # List of auth ports; This is for nmap argument
        # This is returned
        $auth_port_list = $output["port_list"];
        # list of auth IP and port information (for nmap parcer)
        # This is set to global
        $auth_port_list_array = $output["auth_port_list_array"];

        if ( sizeof($auth_port_list_array) < 1 && sizeof($networks) < 1) {
            print "\nTarget IP list ($auth_port_file) is empty; Nothing to scan. Exiting...\n\n";
            exit(1);
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
 *           $output["auth_port_list_array"]
 *           $output["email_address"]
 *
 * Certain functions overlap the used of ip_list and port list, but since they are
 * file specific there is no conflict
 */

function read_file($file, $ports_flag) {
    # read ports (and IPs)
    global $email_address;
    global $ipaddress_exclude_list_array;
    global $ip_list_array;
    global $networks;

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
                   $xxx = explode('#', $buffer);
                   $buffer = $xxx[0];
                   $buffer = trim($buffer);
                   unset($xxx);
                }

                if($ports_flag == True){
                    $ports = $buffer;
                    // Add a comma a the end of the line if not empty string ?
                    // What the fuck - Daniel Thurau (i see why + 30 lines from here)
                    // will always remove that comma
                    if ( strlen($ports) > 0 ) {
                        $port_list .= $ports . ",";
                    }
                }else{
                    // print("non port\n");
                    # Ignore comment lines
                    if ( substr($buffer, 0,1) == "#" || trim($buffer) == "") {
                        # ignore this line
                        // pass;
                    } elseif ( substr($buffer, 0,1) == "-" ) {
                        # add to exclude list
                        array_push($ipaddress_exclude_list_array, substr($buffer, 1));
                    } elseif ( strpos($buffer, "/") > "0" ) {
                        //# Network
                        array_push($networks, $buffer);

                    } else {
                        # Process line

                        if ( strpos($buffer,"@") > "0" ) {
                            # line contains @ , so contains email address
                            $email_address = $buffer;
                        } elseif ( strpos($buffer,":") > "0" ) {
                            # line contains : , so has ip and ports
                            list($ip, $ports) = explode(":", $buffer);
                            $ip_list .= $ip . "\n";
                            array_push($ip_list_array, $ip);


                            # Convert names to IPs
                            # converts ip's but fails on hostname
                            $long = ip2long($ip);
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
                            array_push($ip_list_array,$buffer);
                            $long = ip2long($buffer);
                            if($long == -1 || $long == FALSE){
                                $buffer = gethostbyname($buffer);
                            }
                            $auth_port_list_array[$buffer] = "";
                        }

                        
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


    if ( strlen($email_address) > 0) { $output["email_address"]=$email_address; }
    $output["ip_list"]=$ip_list;
    $output["port_list"]=$port_list;
    $output["auth_port_list_array"]=$auth_port_list_array;

    return($output);

}

/*
 * This functuin is called on a string of the form [1.2.3.4/56]
 * And returns the status of the nmap running on this network.
 * It returns a string ["no_file" | "complete" | "running"]
 */
function check_status($value) {
    global $nmap_dir;

    // Split into network ip address and mask
    if(strpos($value, "/") > "0"){
        list($net, $sub) = explode("/", $value);
    }else{
        $net = $value;
    }

    $nmap_output_file = "$nmap_dir/nmap-T-$net.out";

    if (file_exists($nmap_output_file)) {
        # nmap_outfile exists (nmap running or finished)
        // Get head of nmap file
        $head = system("head -n 1 $nmap_output_file");
        $head_check = substr($head, 0, 6);
        // Get end of nmap file
        $end = system("tail -n 1 $nmap_output_file");
        $end_check = substr($end, 0, 11);

        // Header check
        if ( $head_check != '# Nmap' ) {
            send_log("Incorrect Nmap format: Exiting...");
            exit(1);
        } else {
            // Footer Check
            if ( ($end_check == '# Nmap done') ) {
                return("complete");
            } else {
                return("running");
            }
        }
    } else {
        return("no_file");
    }
}

function parse_nmap_output($commands) {
    global $nmap_dir;
    global $auth_port_list_array;
    global $bad_port_list;
    global $ipaddress_exclude_list_array;
    global $master_nmap_out;
    global $businessunit;
    $master_nmap_out = array();

    foreach ($commands as $key => $value) {
        if(strpos($key, "/") > "0"){
            list($net, $sub) = explode("/", $key);
        }else{
            $net = $key;
        }
        $file = "$nmap_dir/nmap-T-$net.out";
        if(!file_exists($file)){exit(1);}
        $handle = @fopen($file, "r");
        while (($buffer = fgets($handle, 4096)) !== false) {
            if(substr($buffer,0,16) == "Nmap scan report"){
                $id = trim(substr($buffer, 21));
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

##############
## Send Email
function send_email() {
    global $businessunit;
    global $email_address;
    global $nmap_data;


    $date = date("j M Y");
    print $date;

    $mail = new PHPMailer;

    $mail->From = 'Scanner@KaliBox.com';
    $mail->FromName = 'Scanner';
    $mail->Subject = 'Result from Scan on: ' . $date;
	        // $mail->Body = 'THis is the bdy';
	    //     $mail->AddAddress('dthurau@ucsc.edu');
	    //
	    //
	    //         // $mail->AddAttachment('/home/dthruau/myfile.txt');
	    //
    return $mail->Send();


}
## Send Email - End


?>
