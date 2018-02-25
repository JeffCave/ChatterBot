<?php

/**
 *
 *
 *
 */
class DamerauLevenshtein{


	/**
	 *
	 */
	public static function compare ($source, $target){
		if ('' === "$source"){
			if ('' === "$target"){
				return 0;
			}
			else{
				return strlen($target);
			}
		}
		elseif ('' === "$target"){
			return strlen($source);
		} 

		$score = array();
		for($i = strlen($source)+2; $i>=0; $i--){
			$score[] = array_fill(0 , strlen($target)+2, 0);
		}

		$INF = strlen($source) + strlen($target);
		$score[0][0] = $INF;
		for ($i = 0; $i <= strlen($source); $i++) {$score[$i+1][ 1] = $i; $score[$i+1][ 0] = $INF;}
		for ($j = 0; $j <= strlen($target); $j++) {$score[1][$j+1] = $j; $score[0][$j+1] = $INF; }
		
		$sd = array();
		foreach (str_split($source.$target) as $letter){
			$sd[$letter] = 0;
		}
		for($i=1; $i<=strlen($source); $i++){
			$DB = 0;
			for ($j=1; $j<=strlen($target); $j++){
				$i1 = $sd[$target[$j - 1]];
				$j1 = $DB;

				if ($source[$i - 1] === $target[$j - 1]){
					$score[$i+1][$j+1] = $score[$i][$j];
					$DB = $j;
				}
				else{
					$score[$i + 1][ $j + 1] = min($score[$i][j], min($score[$i + 1][ $j], $score[$i][$j+1])) + 1;
				}

				$score[$i + 1][ $j + 1] = min($score[$i + 1][$j + 1], $score[$i1][$j1] + ($i - $i1 - 1) + 1 + ($j - $j1 - 1));
			}

			$sd[$source[$i - 1]] = $i;
		}

		return $score[strlen($source)+1][strlen($target)+1];
		
	}
	
	public static function DLTest(){
		$tests = array();
		// format: [string 1, string 2, expected result, comment]
		$tests[] = array("",		"",			0,	'Empty strings');
		$tests[] = array("abc",		"abc",		0,	'Identicle strings');
		$tests[] = array("abc",		"abcd",		1,	'Insert 1 character at end of string');
		$tests[] = array("abc",		"",			3,	'Adding 3 characters to empty string');
		$tests[] = array("acbd",	"abcd",		1,	'Swap 2 characters in middle of string');
		$tests[] = array("abcd",	"abdc",		1,	'Swap 2 characters at end of string');
		$tests[] = array("abcde",	"acde",		1,	'Insert 1 character in middle of string');
		$tests[] = array("abcdef",	"acebdf",	3,  'Adjacent transpositions');
		
		foreach($tests as $test){
			if($test[2] === static::compare($test[0], $test[1])){
				echo 'PASS';
			} else{
				echo 'FAIL';
			}
			echo ":{$test[3]}\n";
		}
	}

}