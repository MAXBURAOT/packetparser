<?php
date_default_timezone_set('Asia/Seoul');
error_reporting(E_ALL);
ini_set("display_errors", 1);

require("parser_cls.php");
require("mode/full_info.php");

echo "       _____         _       _      _____                      \n";
echo "      |  _  |___ ___| |_ ___| |_   |  _  |___ ___ ___ ___ ___  \n";
echo "      |   __| .'|  _| '_| -_|  _|  |   __| .'|  _|_ -| -_|  _| \n";
echo "      |__|  |__,|___|_,_|___|_|    |__|  |__,|_| |___|___|_|   \n";
echo "         Yommys Amazing Ragnarok Packet Analyzer Framework\n\n";

$parser = new parser();

// ########  Socket System  #######
$connected = false;		// socket connected
$listening = true;		// socket listening
$sock = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
if(!@socket_bind($sock,'127.0.0.1',1234)){
	die("\n## Socket already in use ##\n");
}
socket_listen($sock);
//socket_set_nonblock($sock);

while(true){
	$parser->packet_num = 0;
	echo "\nwaiting for connection from client...";
	while($connected == false){
		if(($socket = socket_accept($sock)) !== false){
			echo "\nClient connected <3\n";
			$connected = true;
		}
	}

	echo "T-----T-----T------T----------------------------------------------------T----------------------------------------------T\n";
	echo "| Num | Way |  ID  | Packet description                                 | Extra information                            \n";
	echo "I-----I-----I------I----------------------------------------------------I----------------------------------------------I\n";

	while($listening){
		if (($parser->stream = @socket_read($socket, 2048, PHP_BINARY_READ)) === false) {
			echo "\nClient disconnected!\n";
			socket_close($socket);
			$connected = false;
			break;
		}
		if($parser->stream) {
			if(substr($parser->stream,0,2) == "RR") {
				$parser->packet_dir = "R";
				$parser->stream = substr($parser->stream,2);
			} elseif(substr($parser->stream,0,2) == "SS") {
				$parser->packet_dir = "S";
				$parser->stream = substr($parser->stream,2);
			}
			if(strlen($parser->stream)) {
				$parser->parse_stream();
			}
		}
	}
}

?>