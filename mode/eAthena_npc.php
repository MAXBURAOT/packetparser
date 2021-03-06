<?php

// packet_parser functions
function PP_MODE_INIT($parser) {
	$parser->mode["mode_name"] = "eAthena_npc";	//
	$parser->mode["extra_bytes"] = false;		// warning about extra packet data
	$parser->mode["save_npc"] = true;
	
	$parser->data['talking_to_npc'] = false;
	$parser->data['money'] = false;
	$parser->data['indent'] = 0;
	
	if($parser->mode["save_npc"]) {
		$npc_filename = "output/npc_capture/".date("Ymd-Gis").".txt";
		$parser->npc_file = fopen($npc_filename, "w+");
	}
}

function echo_save($parser, $text){
	if($parser->data['indent'] < 0)
		$parser->data['indent'] = 0;
	$text = str_repeat("\t",$parser->data['indent']) . $text . "\n";
	echo $text;
	fwrite($parser->npc_file, $text);
}

function PACKET_HC_NOTIFY_ZONESVR($parser) {
	$parser->data['map'] = substr($parser->string(16, 6),0,-4);
}

function PACKET_ZC_SAY_DIALOG($parser){
    $gid = $parser->long(4);
	if($parser->data['talking_to_npc'] == false){
        $parser->data['talking_to_npc'] = $gid;
		if(!isset($parser->npc_list[$gid])){
			echo_save($parser, "\n\n-	script	UNKNOWN_NPC_NAME	-1,{");
			echo_save($parser,"OnPCLoginEvent:");
		} else {
			echo_save($parser,"\n\n".$parser->npc_list[$gid]['map'].",".$parser->npc_list[$gid]['x'].",".$parser->npc_list[$gid]['y'].","."4"."\tscript\t".$parser->npc_list[$gid]['name']."\t".$parser->npc_list[$gid]['job'].",{");
			echo_save($parser,"OnClick:");
		}
		$parser->data['indent'] = $parser->data['indent'] + 1;
    }
	$text = str_replace($parser->data["char_name"], "\"+strcharinfo(0)+\"" , $parser->string($parser->word(2)-8,8));
    echo_save($parser,"mes \"$text\";");
}

function PACKET_ZC_WAIT_DIALOG($parser){
    echo_save($parser,"next;");
}

function PACKET_ZC_CLOSE_DIALOG($parser){
    echo_save($parser,"close2;");
	$parser->data['indent'] = $parser->data['indent'] - 1;
    echo_save($parser,"end;");
	$parser->data['indent'] = $parser->data['indent'] - 1;
	echo_save($parser,"}\n\n");
    $parser->data['talking_to_npc'] = false;
}

function PACKET_ZC_MENU_LIST($parser){
	$select = $parser->string($parser->word(2)-10,8);
	$parser->menu_list = explode(":",":".$select); // begin with : to create a blank entry at begining
	$parser->menu_list[255] = "cancel clicked";
	echo_save($parser,"switch(select(\"$select\")) {");
}

function PACKET_CZ_CHOOSE_MENU($parser){
	$chose = $parser->byte(6);
	$option = $parser->menu_list[$chose];
	echo_save($parser,"case $chose: // $option");
	$parser->data['indent'] = $parser->data['indent'] + 1;
}

function PACKET_ZC_ACK_REQNAME($parser) {
	$gid = $parser->long();
	$name = $parser->string(24);
	$parser->npc_list[$gid]['name'] = $name;
	echo "### gid name resolved $gid => $name\r\n";
}

function PACKET_ZC_NOTIFY_STANDENTRY7($parser){
    $gid = $parser->long(5);
    $parser->npc_list[$gid]['GID'] = $parser->long(5);
    $parser->npc_list[$gid]['job'] = $parser->word(19);
    #$parser->npc_list[$gid]['name'] = $parser->string(24,65);
    $parser->npc_list[$gid]['map'] = $parser->data['map'];
    list($x,$y) = explode(",",$parser->xy(55));
    $parser->npc_list[$gid]['x'] = $x;
    $parser->npc_list[$gid]['y'] = $y;
    echo "### Seen NPC # GID $gid \n";
	#print_r($parser->npc_list[$gid]);
}

function PACKET_ZC_ADD_QUEST($parser) {
	echo_save($parser, "setquest ".$parser->long().";");
}

function PACKET_ZC_DEL_QUEST($parser) {
	echo_save($parser,"erasequest ".$parser->long().";");
}

function PACKET_ZC_COMPASS($parser) {
	$naid = $parser->long();
	$action = $parser->long();
	$x = $parser->long();
	$y = $parser->long();
	$id = $parser->byte();
	$color = $parser->long();
	echo_save($parser,"viewpoint $action,$x,$y,$id,$color;");
}

function PACKET_ZC_SHOW_IMAGE2($parser) {
	$imageName = $parser->string(64);
	$type = $parser->byte();
	echo_save($parser,"cutin \"$imageName\",$type;");
}

function PACKET_ZC_NOTIFY_EXP($parser) {
	$AID=$parser->long();
	$amount=$parser->long();
	$varID=$parser->word();
	$expType=$parser->word();
	if($parser->data['talking_to_npc'] == false){
		return;
	}
	if($expType == 1){
		echo_save($parser, "getexp $amount,0;");
	}elseif($expType == 2){
		echo_save($parser, "getexp 0,$amount;");
	}
}

function PACKET_ZC_ITEM_PICKUP_ACK3($parser) {
	$Index=$parser->word();
	$count=$parser->word();
	$ITID=$parser->word();
	if($parser->data['talking_to_npc'] == false){
		return;
	}
	echo_save($parser,"getitem $ITID,$count;");
}

function PACKET_ZC_OPEN_EDITDLGSTR($parser) {
	echo_save($parser,"input .@input1$;");
}

function PACKET_ZC_OPEN_EDITDLG($parser) {
	echo_save($parser,"input .@amount;");
}

function PACKET_ZC_EMOTION($parser) {
	$GID=$parser->long();
	$type=$parser->byte();
	if($parser->data['talking_to_npc'] == $GID){
		echo_save($parser,"emotion $type,0;"); //emotion from npc
	}elseif($parser->aid == $GID){
		echo_save($parser,"emotion $type,1;"); //emotion from player
	}
}

function PACKET_ZC_LONGPAR_CHANGE($parser) {
	$varID=$parser->word();
	$amount=$parser->long();
	if($varID == 20){ // money
		if($parser->data['money'] !== false){
			if($parser->data['talking_to_npc'] == true){
				$diff = $amount - $parser->data['money'];
				if($diff < 0){
					$diff = abs($diff);
					echo_save($parser,"set zeny,zeny-$diff;");
				} else {
					echo_save($parser,"set zeny,zeny+$diff;");
				}
			}
		}
		$parser->data['money'] = $amount;
	}
}

function PACKET_ZC_NOTIFY_EFFECT($parser) {
	$AID=$parser->long();
	$effectID=$parser->long();
	if($parser->data['talking_to_npc'] == $AID || $parser->aid == $AID){
		echo_save($parser,"misceffect $effectID;");
	}
}

// packet 0x6b
function PACKET_HC_ACCEPT_ENTER_NEO_UNION($parser) {
	$charInfo = ($parser->packet_length - 27) / 144;
	#echo $charInfo;
	for ($i = 0; $i < $charInfo; $i++) {
		$parser->data["char_name_$i"] = $parser->string(24,$i*144+105);
	}
	#print_r($parser->data);
}

// packet 0x66
function PACKET_CH_SELECT_CHAR($parser) {
	$num = $parser->byte();
	$parser->data["char_name"] = $parser->data["char_name_$num"];
}

// packet 0xc4
function PACKET_ZC_SELECT_DEALTYPE($parser) {
	$NAID=$parser->long();
	$map = $parser->data['map'];
	$x = $parser->npc_list[$NAID]['x'];
	$y = $parser->npc_list[$NAID]['y'];
	$name = $parser->npc_list[$NAID]['name'];
	$job = $parser->npc_list[$NAID]['job'];
	$parser->data["shop"] = "$map,$x,$y,4\tshop\t$name\t$job";
}

// packet 0xc6
function PACKET_ZC_PC_PURCHASE_ITEMLIST($parser) {
	$map = $parser->data['map'];
	$shop_filename = "trader_capture/$map.txt";
	$shop_file = fopen($shop_filename, "w");
	$items = "";
	echo $parser->data["shop"] . "\n";
	$PacketLength=$parser->word();
	$itemList = ($parser->packet_length - $parser->packet_pointer) / 11;
	for ($i = 0; $i < $itemList; $i++) {
		$price=$parser->long();
		$discountprice=$parser->long();
		$type=$parser->byte();
		$ITID=$parser->word();
		$items .= ",$ITID:-1";
		echo "  item $ITID\n";
	}
	echo "end of trader;\n";
	$items .= "\n";
	fwrite($shop_file, $parser->data["shop"] . $items);
	fclose($shop_file);
}

?>