<?php

namespace Ortegacmanuel\PubsubhubbubLaravel;

class PubsubhubbubUtils
{

	static function atomHubLink() 
	{
		$link = '<link href="' . route('push_actions.get') . '" rel="hub"/>';
		
		return $link;
   }

	/**
	 * returns $bytes bytes of random data as a hexadecimal string
	 */
	static function commonRandomHexstr($bytes)
	{
	    $str = str_random($bytes);

	    $hexstr = '';
	    for ($i = 0; $i < $bytes; $i++) {
	        $hexstr .= sprintf("%02x", ord($str[$i]));
	    }
	    return $hexstr;
	}   
}