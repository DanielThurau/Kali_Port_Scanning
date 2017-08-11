<?php

// include("limited_tcp_port_scan_alert.php");

function nmap_child($command_block){
	foreach ($command_block as $child=>$value) {
		if (($pid = pcntl_fork()) == -1){
	        send_log("Bad Fork on command: $value. Killing processes...");
	        exit(1);
		}
		if(!$pid){
			send_log(" Nmap started: $value");
			exec($value);
	        while(check_status($child) == "running"){sleep(2);}
	        exit();
		}
	}

	 while (pcntl_waitpid(0, $status) != -1) { 
        $status = pcntl_wexitstatus($status); 
        send_log("Child $status completed");
    }

}


?>
