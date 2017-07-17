<?php

// include("limited_tcp_port_scan_alert.php");



function nmap_sequential($command_block){
	foreach ($command_block as $key => $value) {
		exec($value);
	}
}


?>