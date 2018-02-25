<?php

require_once './lib/cqbot.class.php';

/******* Works  *******/


/******* Testing  *******/
$chatterbot->commands['dltest'] = function(){
	ob_start();
	DamerauLevenshtein::DLTest();
	$contents = ob_get_contents();
	ob_end_clean();
	return $contents;		
};

/******* TODO  *******/

/******* Can't Test *******/


$chatterbot->commands['uptime'] = function() {
	$t = `grep -i 'connecting' nohup.out | tail -n1 | awk '{print $1}'`;
	return "I've been running since ".date("F j, Y, g:i a!", $t);
} ;


$chatterbot->commands['tkt'] = function($args){
	$args = $args[0];
	if ($args=='404'){ 
		return "Ticket not found.";
	}
	
	$str = get_ticket($args);
	if ("$str" == ''){ 
		return("Could not find a ticket matching ".$args." :(");
	}
	
	return $str;
} ;

$chatterbot->commands['tweet'] = function($args) {
	$msg = implode(" ", $args);
	twitter_post('statuses/update', array('status' => $msg));
} ;

$chatterbot->commands['follow'] = function($args) {
	twitter_post('friendships/create',array('screen_name' => $args[0]));
} ;

$chatterbot->commands['unfollow'] = function($args) {
	twitter_post('friendships/destroy', array('screen_name' => $args[0]));
} ;

$chatterbot->commands['retweets'] = function() {
	$con = twitter_get('statuses/retweeted_to_me', array());
	$rtn = '';
	foreach ($con as $t) $rtn .= "{$t->text}\n";
	return $rtn;
} ;

