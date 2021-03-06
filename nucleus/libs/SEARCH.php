<?php

/*
 * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/)
 * Copyright (C) 2003-2007 The Nucleus Group
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * (see nucleus/documentation/index.html#license for more info)
 */
/**
 * SEARCH(querystring) offers different functionality to create an
 * SQL query to find certain items. (and comments)
 *
 * based on code by David Altherr:
 * http://www.evolt.org/article/Boolean_Fulltext_Searching_with_PHP_and_MySQL/18/15665/
 * http://davidaltherr.net/web/php_functions/boolean/funcs.mysql.boolean.txt
 *
 * @license http://nucleuscms.org/license.txt GNU General Public License
 * @copyright Copyright (C) 2002-2007 The Nucleus Group
 * @version $Id: SEARCH.php 1116 2007-02-03 08:24:29Z kimitake $
 */



class SEARCH {

	var $querystring;
	var $marked;
	var $inclusive;
	var $blogs;


	function SEARCH($text) {
		global $blogid;
		$text = preg_replace ("/[<,>,=,?,!,#,^,(,),[,\],:,;,\\\,%]/","",$text);
		$this->querystring	= $text;
		$this->marked		= $this->boolean_mark_atoms($text);
		$this->inclusive	= $this->boolean_inclusive_atoms($text);
		$this->blogs		= array();

		// get all public searchable blogs, no matter what, include the current blog allways.
		$res = sql_query('SELECT bnumber FROM '.sql_table('blog').' WHERE bincludesearch=1 ');
		while ($obj = mysql_fetch_object($res))
			$this->blogs[] = intval($obj->bnumber);
	}

	function  boolean_sql_select($match){
		if (strlen($this->inclusive) > 0) {
		   /* build sql for determining score for each record */
		   $result=explode(" ",$this->inclusive);
		   for($cth=0;$cth<count($result);$cth++){
			   if(strlen($result[$cth])>=4){
				   $stringsum_long .=  " $result[$cth] ";
			   }else{
				   $stringsum_a[] = ' '.$this->boolean_sql_select_short($result[$cth],$match).' ';
			   }
		   }

		   if(strlen($stringsum_long)>0){
				$stringsum_long = addslashes($stringsum_long);
				$stringsum_a[] = " match ($match) against ('$stringsum_long') ";
		   }

		   $stringsum .= implode("+",$stringsum_a);
		   return $stringsum;
		}
	}

	function boolean_inclusive_atoms($string){
		$result=trim($string);
		$result=preg_replace("/([[:space:]]{2,})/",' ',$result);

		/* convert normal boolean operators to shortened syntax */
		$result=eregi_replace(' not ',' -',$result);
		$result=eregi_replace(' and ',' ',$result);
		$result=eregi_replace(' or ',',',$result);

		/* drop unnecessary spaces */
		$result=str_replace(' ,',',',$result);
		$result=str_replace(', ',',',$result);
		$result=str_replace('- ','-',$result);
		$result=str_replace('+','',$result);

		/* strip exlusive atoms */
		$result=preg_replace(
			"(\-\([A-Za-z0-9]{1,}[A-Za-z0-9\-\.\_\,]{0,}\))",
			'',
			$result);

		$result=str_replace('(',' ',$result);
		$result=str_replace(')',' ',$result);
		$result=str_replace(',',' ',$result);

		return $result;
	}

	function boolean_sql_where($match){
		$result = $this->marked;
		$result = preg_replace(
			"/foo\[\(\'([^\)]{4,})\'\)\]bar/e",
			" 'match ('.\$match.') against (\''.\$this->copyvalue(\"$1\").'\') > 0 ' ",
			$result);

		$result = preg_replace(
			"/foo\[\(\'([^\)]{1,3})\'\)\]bar/e",
			" '('.\$this->boolean_sql_where_short(\"$1\",\"$match\").')' ",
			$result);
		return $result;
	}

	// there must be a simple way to simply copy a value with backslashes in it through
	// the preg_replace, but I cannot currently find it (karma 2003-12-30)
	function copyvalue($foo) {
		return $foo;
	}


	function boolean_mark_atoms($string){
		$result=trim($string);
		$result=preg_replace("/([[:space:]]{2,})/",' ',$result);

		/* convert normal boolean operators to shortened syntax */
		$result=eregi_replace(' not ',' -',$result);
		$result=eregi_replace(' and ',' ',$result);
		$result=eregi_replace(' or ',',',$result);


		/* strip excessive whitespace */
		$result=str_replace('( ','(',$result);
		$result=str_replace(' )',')',$result);
		$result=str_replace(', ',',',$result);
		$result=str_replace(' ,',',',$result);
		$result=str_replace('- ','-',$result);
		$result=str_replace('+','',$result);

		// remove double spaces (we might have introduced some new ones above)
		$result=trim($result);
		$result=preg_replace("/([[:space:]]{2,})/",' ',$result);

		/* apply arbitrary function to all 'word' atoms */

		$result_a = explode(" ",$result);
		for($word=0;$word<count($result_a);$word++){
			$result_a[$word] = "foo[('".$result_a[$word]."')]bar";
		}
		$result = implode(" ",$result_a);

		/* dispatch ' ' to ' AND ' */
		$result=str_replace(' ',' AND ',$result);

		/* dispatch ',' to ' OR ' */
		$result=str_replace(',',' OR ',$result);

		/* dispatch '-' to ' NOT ' */
		$result=str_replace(' -',' NOT ',$result);
		return $result;
	}

	function boolean_sql_where_short($string,$match){
		$match_a = explode(',',$match);
		for($ith=0;$ith<count($match_a);$ith++){
			$like_a[$ith] = " $match_a[$ith] LIKE '% $string %' ";
		}
		$like = implode(" OR ",$like_a);

		return $like;
	}
	function boolean_sql_select_short($string,$match){
		$match_a = explode(',',$match);
		$score_unit_weight = .2;
		for($ith=0;$ith<count($match_a);$ith++){
			$score_a[$ith] =
						   " $score_unit_weight*(
						   LENGTH(" . addslashes($match_a[$ith]) . ") -
						   LENGTH(REPLACE(LOWER(" . addslashes($match_a[$ith]) . "),LOWER('" . addslashes($string) . "'),'')))
						   /LENGTH('" . addslashes($string) . "') ";
		}
		$score = implode(" + ",$score_a);

		return $score;
	}
}
?>
