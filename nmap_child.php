<?php

// include("limited_tcp_port_scan_alert.php");

function nmap_child($command_block){
	for( $i = 0; $i < sizeof($command_block); ++$i){
		$pid = pcntl_fork();
		if(!$pid){
			send_log(" Nmap started: $value");
			exec($value);
	        while(check_status($child) == "running"){sleep(2);
	        }
	        exit();
		}
	}

	 while (pcntl_waitpid(0, $status) != -1) { 
        $status = pcntl_wexitstatus($status); 
        send_log("Child $status completed");
        // echo "Child $status completed\n"; 
    }

	// foreach ($command_block as $child=>$value) {
	//     if (($pid=pcntl_fork()) == -1){
	//         if($DEBUG){
	//             print("Bad Fork: $child \n");
	//         }
	//         send_log("Bad Fork on command: $value. Killing processes...");
	//         exit(1);
	//     }else if ($pid){
	//         //protect against zombie children, one wait vs one child 
	//         pcntl_wait($status); 
	//     }else if ($pid===0) {
	//    	send_log(" Nmap started: $value");
	//         //prevent output to main process 
	//         ob_start();
	//         // to kill self before exit();, 
	//         // or else the resource shared with parent will be closed 
	//         register_shutdown_function(create_function('$pars', 'ob_end_clean();posix_kill(getmypid(), SIGKILL);'), array());
	//         exec($value);
	//         // while(check_status($child) == "running"){sleep(2);}


	//         exit();//avoid foreach loop in child process 
	//     } 
	// } 
}


?>
