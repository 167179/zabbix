<?php

function get_script_by_scriptid($scriptid){
	$sql = 'SELECT * FROM scripts WHERE scriptid='.$scriptid;

	$rows = false;
	if($res = DBSelect($sql)){
		$rows = DBfetch($res);
	}
return $rows;
}

function add_script($name,$command,$usrgrpid,$groupid,$access){
	$scriptid = get_dbid('scripts','scriptid');
	$sql = 'INSERT INTO scripts (scriptid,name,command,usrgrpid,groupid,host_access) '.
				" VALUES ($scriptid,".zbx_dbstr($name).','.zbx_dbstr($command).",$usrgrpid,$groupid,$access)";
	$result = DBexecute($sql);
	if($result){
		$result = $scriptid;
	}
return $result;
}

function delete_script($scriptids){
	zbx_value2array($scriptids);

	$sql = 'DELETE FROM scripts WHERE '.DBcondition('scriptid',$scriptids);
	$result = DBexecute($sql);

return $result;
}

function update_script($scriptid,$name,$command,$usrgrpid,$groupid,$access){

	$sql = 'UPDATE scripts SET '.
				' name='.zbx_dbstr($name).
				' ,command='.zbx_dbstr($command).
				' ,usrgrpid='.$usrgrpid.
				' ,groupid='.$groupid.
				' ,host_access='.$access.
			' WHERE scriptid='.$scriptid;

	$result = DBexecute($sql);
return $result;
}

function script_make_command($scriptid,$hostid){
	$host_db = DBfetch(DBselect('SELECT dns,useip,ip FROM hosts WHERE hostid='.$hostid));
	$script_db = DBfetch(DBselect('SELECT command FROM scripts WHERE scriptid='.$scriptid));

	if($host_db && $script_db){
		$command = $script_db['command'];
		$command = str_replace("{HOST.DNS}", $host_db['dns'],$command);
		$command = str_replace("{IPADDRESS}", $host_db['ip'],$command);
		$command = ($host_db['useip']==0)?
				str_replace("{HOST.CONN}", $host_db['dns'],$command):
				str_replace("{HOST.CONN}", $host_db['ip'],$command);
	}
	else{
		$command = FALSE;
	}
	return $command;
}

function execute_script($scriptid,$hostid){
	global $ZBX_SERVER, $ZBX_SERVER_PORT;

	$message = array();

	if(!$socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)){
		return false;
	}

	$res = socket_connect($socket, $ZBX_SERVER, $ZBX_SERVER_PORT);

	if($res){
		$json = new CJSON();

		$array = array();
		$array['request'] = 'command'; 
		$array['nodeid'] = id2nodeid($hostid); 
		$array['scriptid'] = $scriptid; 
		$array['hostid'] = $hostid; 

		$send = $json->encode($array, false);

		socket_write($socket, $send);

		$res = socket_read($socket, 65535);
	}

	if($res){
		$json = new CJSON();

		$rcv = $json->decode($res, true);
	}
	else{
		$rcv['response']='failed';
		$rcv['value'] = S_CONNECT_TO_SERVER_ERROR.' ['.$ZBX_SERVER.':'.$ZBX_SERVER_PORT.']'.
				' ['.socket_strerror(socket_last_error()).']';
	}

	socket_close($socket);
return $rcv;
}

?>
