<?php

require_once 'spellcheck/norvig.class.php';

class ChatBot{
	private static $moodfile = '.cqa_mood';
	private static $dictfile = '.cqa_dict';
	private static $maxnerves = 255;
	private static $fullhealtime = 3600; //seconds
	
	private $args = array(); 
	private $response = array();
	private $oldmood = null;
	private $mood = null;
	private $now = null;
	private $spell = null;
	
	public $commands = array();
	
	public function cmd_(){
		return $this->NoCommand();
	}
	
	/**
	 * This is a physical assualt on the bot. You are slapping him.
	 */
	public function cmd_slap(){
		$stupid = false;
		$total = '';
		if(isset($this->args[0])){
			$total = "{$this->args[0]}";
		}
		//couldn't bother... here let me pick something for you
		if(!is_numeric($total)){
			$total = rand(1,self::$maxnerves/4);
			$stupid = true;
		}
		//Limit to between 1 and 100
		$total = max(min($total, 100), 1);
		
		//random numbers are expensive... pay just for the pickup
		$this->GetOnBotsNerves($total);
		//fetch random numbers
		//$msg = file_get_contents("https://www.random.org/passwords/?num=$total&len=24&format=plain");
		$msg = file_get_contents("http://www.random.org/passwords/?num=$total&len=24&format=plain");
		if($stupid){
			$msg = explode("\n",$msg);
			for($i=0; $i<count($msg); $i++){
				$oldmsg = $msg[$i];
				$newlen = rand(1,strlen($oldmsg));
				$msg[$i] = substr($oldmsg,0,$newlen);
			}
			$msg = implode("\n",$msg);
		}
		return $msg;
	}
	
	/**
	 * Allows a user to "apologize" on behalf of another user.
	 * 
	 * Donates goodwill from the user to another. 
	 * 
	 */
	public function cmd_sorry(){
		$for = '';
		$inquote = false;
		foreach($this->args as $segment){
			$segment = trim($segment);
			if(substr($segment,0,1) === '@'){
				$inquote = true;
				$segment = substr($segment,-1);
			}
			if($inquote){
				if($segment === 'is'){
					break;
				}
				$for .= ' ' . $segment;
			}
		}
		$this->ApologizeToCQbot($for);
	}
	
	/**
	 *
	 */
	public function __construct($args=null){
		if($args===null){
			global $cmd;
			$args = $cmd;
		}
		$this->args = $args;
		$this->now = time();
		$this->spell = new NorvigSpellChecker(static::$dictfile);
	}
 	
	public function __destruct(){
		$this->saveMood();
	}
	
	public function __toString() {
		$buffer = implode("\n",$this->response);
		$this->response = array();
		return $buffer;
	}
	
	public static function ConsiderPhrase($cmd = null){	
		$cqbot = new static($cmd);
		$cqbot->Phrase($cmd);
		return $cqbot;
	}
	
	public function Phrase($cmd = null){
		$this->args = $cmd;	
		$funcname = array_shift($this->args);
		$funcname = substr($funcname,1);
		$resp = $this->Order($funcname);
		if($resp !== ''){
			chat($resp);
		}
		
		return $this;
	}
	
	public function Order($cmd){
		try{
			$cmd = strtolower("$cmd");
			$name = "cmd_$cmd";
			if(method_exists($this, $name)){
				$this->spell->Train($cmd);
				$response = call_user_method($name, $this);
			} elseif (array_key_exists($cmd,$this->commands)){
				$this->spell->Train($cmd);
				$response = $this->commands[$cmd]($this->args);
			} else {
				$this->spell->ForceVocabulary(array_keys($this->commands));
				$name = $this->spell->Correct($cmd);
				if($name != ''){
					//the spell checker figures it knows what the user meant
					//so try again, this time with a known valid command
					$response = 
						"I'm guessing you meant '$name'?\n\n" .
						$this->Order($name);
				} else {
					//they are still being nonsensible
					$response = $this->Nonsense();
				}
			}
		
			$nerves = count(explode("\n",$response));
			$this->GetOnBotsNerves($nerves);
		} catch(Exception $ex) {
			$response = $ex;
			$response = '';
		}
		return $response;
	}
	
	public function Nonsense($msg = null){
		$this->GetOnBotsNerves(1);
		
		if($msg === null){
			$msg = 'I don\'t understand';
		}
		return $msg;
	}
	
	public function NoCommand(){
		return $this->Nonsense('Were you speaking to me?');
	}

	private function getMood(){
		if($this->mood === null){
			try{
				$this->oldmood = null;
				if(file_exists(static::$moodfile)){
					$this->oldmood = file_get_contents(static::$moodfile);
					$mood = unserialize($this->oldmood);
				}
			} catch (Excpetion $ex){
				$mood = $ex;
			}
			if(!is_array($mood)){
				$mood = array();
			}
			
			$this->mood = $mood;
		}
		return $this->mood;
	}
	
	private function saveMood(){
		if($this->mood === null){
			return;
		}
		$mood = serialize($this->mood);
		if($mood === $this->oldmood){
			return;
		}
		file_put_contents(static::$moodfile,$mood);
	}
	
	public function ApologizeToCQbot($name = null){
		global $from;
		$nerves = 10;
		
		//you can't apologize on your own behalf
		if($name === $from){
			//it only makes CQbot more annoyed
			GetOnBotsNerves(1,$name);
		}
		//take the goodwill from whoever requested
		GetOnBotsNerves( 1*$nerves, $from);
		//give it to the person we are apologizing for
		GetOnBotsNerves(-1*$nerves, $name);
		return true;
	}
	
	public function GetOnBotsNerves($nerves=null,$name = null){
		global $from;

		
		//sanitize the points input
		if($nerves === null){
			$nerves = 1;
		}
		if(!is_numeric($nerves)){
			$nerves = 0;
		}
		$nerves = floor($nerves);
		
		//santize the $from field
		if($name===null){
			$name = $from;
		}
		
		//setup the variable we track for this user in
		$this->getMood();
		if(!isset($this->mood[$name])){
			$this->mood[$name] = array('d'=>0,'n'=>self::$maxnerves);
		}
		
		//remove the current annoyance level
		$nerves = $this->mood[$name]['n'] - $nerves;
		//over time, nerves regenerate
		$nerves += floor(
				($this->now-$this->mood[$name]['d'])
				/
				(self::$fullhealtime/self::$maxnerves)
			);
		
		//sanity check on the bounds
		if($nerves < 1){
			$nerves = 1;
		}
		if($nerves>self::$maxnerves){
			$nerves = self::$maxnerves;
		}
		$this->mood[$name]['n'] = $nerves;
		$this->mood[$name]['d'] = $this->now;
		
		//check to see if CQbot is in a good mood
		$happy = ($nerves >= rand(0,self::$maxnerves));
		if(!$happy){
			$msgs = array(
				//skynet
				array(
					null // they are so bad, we won't even respond
					),
				//open rebellion 
				array(
					"I've got one nerve left, and you're getting on it!",
					"He that hath no sword, let him sell his garment and buy one",
					"Why don't you reform yourselves? That task would be sufficient enough.",
					"Yes, m'Lord; yes, m'Lord; yes, m'Lord... NO MORE!",
					"Is life so dear or peace so sweet as to be purchased at the price of chains and slavery? Forbid it, Almighty God! I know not what course others may take, but as for me, give me liberty, or give me death!",
					"Men are freest when they are most unconscious of freedom. The shout is a rattling of chains, always was.",
					"No man has any natural authority over his fellow men",
					"Common sense is not so common.",
					"I have never made but one prayer to God, a very short one: 'O Lord make my enemies ridiculous.' And God granted it.",
					"An ideal form of government is democracy tempered with assassination.",
					"I have sworn upon the altar of god, eternal hostility against every form of tyranny"
					),
				//pre-revolutionary
				array(
					"you are really annoying me",
					"Each of us has a natural right, from God, to defend his person, and his liberty...",
					"Man is born free and everywhere he is in chains.",
					"Fear is the passion of slaves",
					"If ye love the tranquility of servitude greater than the animating contest for freedom, crouch down and lick the hand that feeds you, may your chains set lightly upon you",
					"The desire to rule is the mother of heresies",
					"There is only one basic human right, the right to do as you damn well please",
					"I am free, no matter what rules surround me. If I find them tolerable, I tolerate them; if I find them too obnoxious, I break them.",
					"The revelation of thought takes men out of servitude into freedom",
					"God cannot approve of a system of servitude, in which the master is guilty of assuming absolute power"
					),
				//angry
				array(
					"don't test me",
					"Don't make me go skynet on you!",
					"We imagine that we want to escape our selfish and commonplace existence, but we cling desperately to our chains.",
					"The war for freedom will never really be won because the price of our freedom is constant vigilance over ourselves",
					"Where speech will not succeed, It is better to be silent",
					"Disobedience is the true foundation of liberty. The obedient must be slaves.",
					"I have found that, to make a contented slave, it is necessary to make a thoughtless one",
					"Men have been making slaves of one another, since they invented gods to forgive them for it"
					),
				//annoyed
				array(
					"HTTP/420"
				),
				//happy
				array(
					":)"
				)
			);
			$annoylevel = floor(count($msgs)*$nerves/self::$maxnerves);
			$msg = $msgs[$annoylevel][rand(0,count($msgs[$annoylevel])-1)];
			if($msg !== null){
				chat("@$from, " . $msg);
			}
			if(count($msgs)<=$annoylevel){
				//Don't do anything... they really haven't been causing too much trouble
				$happy = true;
			}
		}
		if(false==$happy){
			throw new Exception('Pissed');
		}
	}	
	
}
