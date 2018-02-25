<?php
require_once 'lib/cqbot.class.php';


function chat($msg){echo "$msg\n";}

$cmd = $argv;
array_shift($cmd);
$from = 'Jeff Cave';

$chatterbot = new ChatBot($cmd);
require_once 'chatbot.php';

if(count($cmd) < 1){
	exit(0);
}

if(preg_match('/^\!.*$/', $cmd[0])){
	$chatterbot->Phrase($cmd);
}
