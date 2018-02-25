<?php

require_once('DamerauLevenshtein.class.php');

/**
 * Norvig came up with a simple spell checker that catches the most common typos.
 * 
 * The basic idea is to insert and swap characters, and then check that against 
 * a list of known good words. If matches are found, then we can suggest the 
 * most likely one based on the frequency with which that word is used.
 * 
 * Norvig suggested keeping a training file on hand (something like Moby Dick) 
 * which could be pulled through the trainer to initialize the spell checker. 
 * This implementation maintains a frequency dictionary on file. The frequency 
 * dictionary can be updated with the training routine.
 */
class NorvigSpellChecker {
	private static $filter = '/([a-z]+)/'; // only accept values that are in our alphabet
	private static $alphabet = null;
	private static $MAXFREQ = PHP_INT_MAX;
	private static $MAXDIST = PHP_INT_MAX;
	
	public $Dictionary = array();//Frequency Dictionary
	private $olddict = null;
	private $dictfile = null;
	
	public function __construct($dictpath = null){
		if(self::$alphabet === null){
			self::$alphabet = str_split('abcdefghijklmnopqrstuvwxyz');
			self::$MAXFREQ = PHP_INT_MAX-2;
		}
		if($dictpath != null){
			$this->dictfile = $dictpath;
			$this->LoadDictionary($dictpath);
		}
	}
	
	public function __destruct(){
		$this->SaveDictionary();
	}
	
	/**
	 *
	 */
	public function LoadDictionary($dictpath){
		if(!file_exists($dictpath)){
			return false;
		}
		try{		
			$this->dictfile = $dictpath;
			$this->SetDictionary(file_get_contents($dictpath));
		} catch (Exception $ex){
			$this->Dictionary = $ex;
		}
		if(!is_array($this->Dictionary)){
			$this->Dictionary = array();
		}
		return true;
	}
	
	/**
	 *
	 */
	public function SetDictionary($dictionary){
		$this->Dictionary = $dictionary;
		if(!is_array($dictionary)){
			//try to unserialze the text
			$this->olddict = $dictionary;
			$this->Dictionary = unserialize($this->olddict);
		}
		//if it wasn't a proper frequency dictionary(string=>int)
		if(!is_array($this->Dictionary)){
			//use it as training text
			$this->Train($dictionary);
		}
	}
	
	/**
	 *
	 */
	public function SaveDictionary($dictfile = null){
		if("$dictfile" === ''){
			$dictfile = $this->dictfile;
		}
		if("$dictfile" === ''){
			return;
		}
		$newdict = serialize($this->Dictionary);
		if($this->olddict == $newdict){
			return;
		}
		file_put_contents($dictfile,$newdict);
		$this->dictfile = $dictfile;
		$this->olddict = $newdict;
	}
	
	/**
	 * If you have a known vocabulary list, force the dictionary to only 
	 * contain elements in the vocabulary list. It does not affect existing 
	 * frequency statistics.
	 */
	public function ForceVocabulary($vocab){
		$vocab = array_flip($vocab);
		
		//we want to preserve the old values, so we need to check the list
		foreach(array_keys($vocab) as $word){
			//if it's new initialize it as one
			$vocab[$word] = 1;
			//check if its new
			if(isset($this->Dictionary[$word])){
				//copy the old frequency value from the old dictionary
				$vocab[$word] = $this->Dictionary[$word];
			}
		}
		$this->Dictionary = $vocab;
		
		return $this;
	}
	
	/**
	 * Train creates a dictionary from a sample of text. It does this by 
	 * counting the number of times a word exists in the text.
	 */
	public function Train($trainingtext) {
		if(!is_array($this->Dictionary)){
			$this->Dictionary = array();
		}
		$wordlist = $this->SantizedWords($trainingtext);
		foreach($wordlist as $word){
			if(!isset($this->Dictionary[$word])){
				$this->Dictionary[$word] = 0;
			}
			$this->Dictionary[$word]++;
			//if we hit the top of our frequency size we are going to have trouble 
			if($this->Dictionary[$word] > self::$MAXFREQ){
				//we need to adjust all the items, but maintain their relative position
				foreach(array_keys($this->Dictionary) as $item){
					//just divide them all by 2, this will maintain their 
					//relative position, but also move everything down so we 
					//don't hit the top of the memory. We should still maintain 
					//it as an integer. This has an interesting mathematic 
					//effect of weighting more recent data; which in the context 
					//of a command spell checker makes sense since usage 
					//patterns will change over time.
					$this->Dictionary[$item] = (int)($this->Dictionary[$item]*0.75);
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Splits text into a sanitized list of words
	 */
	private function SantizedWords($text) {
		//if its text split it into an array
		if(!is_array($text)){
			//case insensitive string
			$text = strtolower("$text");
			//split on whitespace
			$text = preg_split('/\s/',$text,null,PREG_SPLIT_NO_EMPTY);
		}
		// filter the list
		$rtn = preg_grep(self::$filter, $text);
		
		return $rtn;
	}
	
	/**
	 *
	 * 
	 */
	private function MakeEdits($words) {
		if(!is_array($words)){
			$words = array($words);
		}
		
		$rtn = array();
		foreach($words as $word) {
			$word = trim($word);
			if($word !== ''){
				for($i=strlen($word); $i>=0;$i--){
					//splits (a & b)
					$prefix = substr($word,0,$i);
					$postfix = substr($word,$i);
					foreach(self::$alphabet as $letter) {
						//inserts
						$rtn[] = $prefix . $letter . $postfix;
					}
					if ($postfix !== '') {
						$removed = substr($postfix,1);
						$removed2 = substr($postfix,2);
						//deletes
						$rtn[] = $prefix . $removed;
						//transposes
						if (strlen($postfix) > 1) {
							$transpose = str_split($postfix);
							$rtn[] = ($prefix . $transpose[1] . $transpose[0] . $removed2);
						}
						//replaces & inserts
						foreach (self::$alphabet as $letter) {
							//replaces
							$rtn[] = $prefix . $letter . $removed;
						}
					}
				}
			}
		}
		
		return $rtn;
	}	
	
	/**
	 * Lists all of the matches we have found
	 * 
	 * @param array $words
	 * @return array valid words
	 */
	private function Known($words) {
		if(!is_array($words)){
			$words = array($words);
		}
		$rtn = $words;
		$rtn = array_flip($rtn);
		foreach($words as $word){
			if(!array_key_exists($word,$this->Dictionary)){
				unset($rtn[$word]);
			}
		}
		foreach($rtn as $key => $word){
			$rtn[$key] = $key;
		}
		return $rtn;
	}
	
	/**
	 * Returns a list of suggested replacement words. Ordered by most most likely.
	 * 
	 * 
	 * @param string $word
	 * @return array Suggested replacements
	 */
	public function Suggestions($word) {
		$orig = $word;
		$tries = 2;
		$foundwords = $this->Known($word);
		for($i = $tries; $i>0 && 0==count($foundwords); $i--){
			$word = $this->MakeEdits($word);
			$foundwords = $this->Known($word);
		}
		foreach($foundwords as $word=>$freq){
			$foundwords[$word] = array(
				'orig' => $orig,
				'sugg' => $word,
				'freq' => $freq,
				'dist' => DamerauLevenshtein::compare($orig, $word)
			);
			if($foundwords[$word]['dist'] > self::$MAXDIST){
				unset($foundwords[$word]);
			}
		}

		return $foundwords; 
	}
	
	/**
	 * Returns the most likely replacement value
	 * 
	 * @param string $word
	 * @return string
	 */
	public function Correct($word){
		$word = $this->Suggestions($word);
		//sort the list by word usage frequency
		uasort($word, function($a,$b){
			$c=$a['dist']-$b['dist']; 
			$c=($c==0)?$a['freq']-$b['freq']:$c; 
			return $c;
			});
		var_dump($word);
		$word = array_shift($word);
		$word = $word['sugg'];
		return $word;
	}
	
}
