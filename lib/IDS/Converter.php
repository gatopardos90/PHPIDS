<?php

/**
 * PHPIDS
 * Requirements: PHP5, SimpleXML
 *
 * Copyright (c) 2007 PHPIDS group (http://php-ids.org)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the license.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @package	PHPIDS
 */

/**
 * PHPIDS specific utility class to convert charsets manually
 *
 * Note that if you make use of IDS_Converter::runAll(), existing class
 * methods will be executed in the same order as they are implemented in the
 * class tree!
 *
 * @author		christ1an <ch0012@gmail.com>
 * @author		.mario <mario.heiderich@gmail.com>
 *
 * @package		PHPIDS
 * @copyright   2007 The PHPIDS Group
 * @version		SVN: $Id:Converter.php 517 2007-09-15 15:04:13Z mario $
 * @link        http://php-ids.org/
 */
class IDS_Converter {
    
    /**
     * Runs all converter functions
     *
     * Note that if you make use of IDS_Converter::runAll(), existing class
     * methods will be executed in the same order as they are implemented in the
     * class tree!
     *
     * @param   string  $value
     * @static
     * @return  string
     */
    public static function runAll($value) {
        $methods = get_class_methods(__CLASS__);
        
        $key = array_search('runAll', $methods);
        unset($methods[$key]);
                
        foreach ($methods as $key => $func) {
            $value = self::$func($value);
        }
        
        return $value;
    }
    
    /**
     * Strip newlines
     * 
     * @param   string  $value
     * @static
     * @return  string
     */
    public static function convertFromNewLines ($value) {
        
        return preg_replace('/(?:\n|\r)/m', ' ', $value);  
    } 
    
    /**
     * Check for comments and erases them if available
     * 
     * @param   string  $value
     * @static
     * @return  string
     */ 
    public static function convertFromCommented($value) {

        // check for existing comments
        if (preg_match('/(?:\<!-|-->|\/\*|\*\/|\/\/\W*\w+\s*$)|(?:--[^-]*-)/ms', $value)) {            

            $pattern = array(
                '/(?:(?:<!)(?:(?:--(?:[^-]*(?:-[^-]+)*)--\s*)*)(?:>))/ms', 
                '/(?:(?:\/\*\/*[^\/\*]*)+\*\/)/ms', 
                '/(?:--[^-]*-)/ms'
            );
            
            $converted = preg_replace($pattern, NULL, $value);
            
            $value .= "\n" . $converted;
        } 
          
        return $value;
    }    
    
    /**
     * Converts relevant UTF-7 tags to UTF-8
     *
     * @param   string  $data
     * @static
     * @return  string
     */
    public static function convertFromUTF7($data) {

        $schemes = array(
            '+AFwAIg'  => '"',
            '+ADw-'     => '<',
            '+AD4-'     => '>',
            '+AFs'     => '[',
            '+AF0'     => ']',
            '+AHs'     => '{',
            '+AH0'     => '}',
            '+AFw'     => '\\',
            '+ADs'     => ';',
            '+ACM'     => '#',
            '+ACY'     => '&',
            '+ACU'     => '%',
            '+ACQ'     => '$',
            '+AD0'     => '=',
            '+AGA'     => '`',
            '+ALQ'     => '"',
            '+IBg'     => '"',
            '+IBk'     => '"',     
            '+AHw'     => '|',
            '+ACo'     => '*',
            '+AF4'     => '^'
        );
        
        $data = str_ireplace(array_keys($schemes), array_values($schemes), $data);  

        return $data;
    }

    /**
     * Checks for common charcode pattern and decodes them
     * 
     * @param   string  $value
     * @static
     * @return  string
     */ 
    public static function convertFromJSCharcode($value) {   

        $matches = array();
        
        // check if value matches typical charCode pattern
        if (preg_match_all('/(?:[\d+-=\/\* ]+(?:\s?,\s?[\d+-=\/\* ]+)+){4,}/ms', $value, $matches)) {
            
            $converted  = '';
            $string = implode(',', $matches[0]);
            $string = preg_replace('/\s/', '', $string);
            $string = preg_replace('/\w+=/', '', $string);
            $charcode = explode(',', $string);       
            
            foreach ($charcode as $char) {
                $char = preg_replace('/[\W]0/s', '', $char);
               
                if (preg_match_all('/\d*[+-\/\* ]\d+/', $char, $matches)) {                  
                    $match = preg_split(
                        '/([\W]?\d+)/',
                        (implode('', $matches[0])),
                        NULL,
                        PREG_SPLIT_DELIM_CAPTURE
                    ); 

                    if(array_sum($match) >= 20 && array_sum($match) <= 127){
                        $converted .= chr(array_sum($match));
                    }
    
                } elseif (!empty($char) && $char >= 20 && $char <= 127) {
                    $converted .= chr($char);                               
                }                              
            }
            
            $value .= "\n" . $converted;
        }

        // check for octal charcode pattern
        if (preg_match_all('/(?:(?:[\\\]+\d+\s*){8,})/ims', $value, $matches)) {

            $converted  = '';
            $charcode   = explode('\\', preg_replace('/\s/', '', implode(',', $matches[0])));

            foreach ($charcode as $char) {
                if (!empty($char)) {
                	if(octdec($char) >= 20 && octdec($char) <= 127) {
                        $converted .= chr(octdec($char));
                	}                               
                }
            }       
            
            $value .= "\n" . $converted;
        }

        // check for hexadecimal charcode pattern
        if (preg_match_all('/(?:(?:[\\\]+\w+\s*){8,})/ims', $value, $matches)) {

            $converted  = '';
            $charcode   = explode('\\', preg_replace('/[ux]/', '', implode(',', $matches[0])));

            foreach ($charcode as $char) {
                if (!empty($char)) {
                    if(hexdec($char) >= 20 && hexdec($char) <= 127) {
                	   $converted .= chr(hexdec($char));  
                    }                             
                }
            }
            
            $value .= "\n" . $converted;
        }

        return $value;
     }

    /**
     * Normalize quotes
     * 
     * @param   string  $value
     * @static
     * @return  string
     */ 
    public static function convertQuotes($value) {

        // normalize different quotes to "
        $pattern = array('\'', '`', '´', '’', '‘');
        
        $value = str_replace($pattern, '"', $value);
          
        return $value;
    }     

    /**
     * Converts basic concatenations
     * 
     * @param   string  $value
     * @static
     * @return  string
     */ 
    public static function convertFromSQLKeywords($value) {

        $pattern = array('/NULL|TRUE|FALSE|LOCALTIME|BINARY|CURRENT_USER/ims'); 
        $converted = preg_replace($pattern, 0, $value);

        $pattern = array('/(?:NOT\s+BETWEEN)|(?:IS\s+NOT)|(?:NOT\s+IN)|XOR|<>|RLIKE/ims'); 
        $converted = preg_replace($pattern, '=', $converted);        
        
        if ($value != $converted) {    
            $value .= "\n" . $converted;
        }
        
        return $value;    
    }

    /**
     * Converts basic concatenations
     * 
     * @param   string  $value
     * @static
     * @return  string
     */ 
    public static function convertConcatenations($value) {

        $compare = stripslashes($value);  

        $pattern = array('/(?:"?"\+\w+\+")/ms',
            '/(?:"\s*;[^"]+")|(?:";[^"]+:\s*")/ms',
            '/(?:"\s*(?:;|\+).{8,18}:\s*")/ms',
            '/(";\w+=)|(!""&&")|(?:~)/ms', 
            '/(?:"?"\+""?\+?"?)|(?:;\w+=")|(?:"[|&]{2,})/ms',
            '/("\s*[\W]+\s*\n*")/ms',
            '/(";\w\s*+=\s*\w?\s*\n*")/ms',
            '/("[|&;]+\s*[^|&\n]*[|&]+\s*\n*"?)/ms',
            '/(";\s*\w+\W+\w*\s*[|&]*")/ms'); 

        // strip out concatenations
        $converted = preg_replace($pattern, NULL, $compare);
            
        if ($compare != $converted) {    
            $value .= "\n" . $converted;
        }

        return $value;    
    }    
    
    /**
     * Converts from hex/dec entities
     * 
     * @param   string  $value
     * @static
     * @return  string
     */     
    public static function convertEntities($value) {
    
        $converted = NULL;
        $matches = array();
        if (preg_match('/&#x?[\w]+/ms', $value)) {
            $converted = preg_replace('/(&#x?[\w]+);?/ms', '$1;', $value);
            $converted = html_entity_decode($converted);   
            $value .= "\n" . $converted;     
        }
        
        return $value;  
    }    
    
    /**
     * Detects nullbytes and controls chars via ord()
     * 
     * @param   string  $value
     * @static
     * @return  string
     */
    public static function convertFromControlChars($value) {

        // critical ctrl values
        $crlf = array(0,1,2,3,4,5,6,7,8,11,12,14,15,16,17,18,19);

        $values = str_split($value);
        foreach ($values as $item) {
            if (in_array(ord($item), $crlf, true)) {
                $value .= "\n%00";
                return $value;
            }
        }

        return $value;
    }

    /**
     * Detects nullbytes and controls chars via ord()
     * 
     * @param   string  $value
     * @static
     * @return  string
     */
    public static function convertFromOutOfRangeChars($value) {

        $values = str_split($value);
        foreach ($values as $item) {
            if(ord($item) >= 128) {
            	$value = str_replace($item, 'U', $value);
            }
        }

        return $value;
    }    
    
    /**
     * Basic approach to fight attacks using common parser bugs
     * 
     * @param   string  $value
     * @static
     * @return  string
     */
    public static function convertParserBugs($value) {

        $search = array('\a', '\l');
        $replace = array('a', 'l');
        
        $value = str_replace($search, $replace, $value);
        
        return $value;
    }  

    /**
     * Strip XML patterns
     * 
     * @param   string  $value
     * @static
     * @return  string
     */
    public static function convertFromXML ($value) {
        
    	$converted = strip_tags($value);
    	
    	if($converted != $value) {
            return $value . "\n" . $converted;     		
    	}
    	return $value;
    }  

    /**
     * This method is the centrifuge prototype
     * 
     * @param   string  $value
     * @static
     * @return  string
     * //TODO: Test and optimize
     */
    public static function convertFromCentrifuge ($value) {

    	if(strlen($value) > 80) {
    		//replace all non-special chars
	        $converted =  preg_replace('/[\w\s\p{L}]/', NULL, $value);
	
	        //split string into an array, unify and sort
	        $array = str_split($converted);
	        $array = array_unique($array);
	        asort($array);
	            
	        //normalize certain tokens
	        $schemes = array(
	            '~' => '+', 
	            '^' => '+', 
	            '|' => '+',
	            '=' => '+');
	            
	        $converted = implode($array);
	        $converted = str_replace(array_keys($schemes), array_values($schemes), $converted);  
	        $converted = preg_replace('/[()[\]{}]/', '(', $converted);
	        $converted = preg_replace('/[!?,.:;]/', ':', $converted);
	        $converted = preg_replace('/[^:(+]/', NULL, stripslashes($converted));
	            
	        //sort again and implode
	        $array = str_split($converted);
	        asort($array); 
	        $converted = implode($array);          
	        
	        if(preg_match('/(?:\({2,}\+{2,}:{2,})|(?:\({2,}\+{2,}:+)|(?:\({3,}\++:{2,})/', $converted)) {
	        	return $value . "\n" . $converted;          
	        }
    	}
        
        return $value;
    }     
    
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 */