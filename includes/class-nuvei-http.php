<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Http
{
    /**
	 * Get request parameter by key
	 *
	 * @param string    $key - request key
	 * @param string    $type - possible vaues: string, float, int, array, mail/email, other
	 * @param mixed     $default - return value if fail
	 * @param array     $parent - optional list with parameters
	 *
	 * @return mixed
	 */
    public static function get_param($key, $type = 'string', $default = '', $parent = array()) {
		$arr            = array();
        $glob_request   = filter_var($_REQUEST, FILTER_REQUIRE_ARRAY);
        
		if (!empty($glob_request) && is_array($glob_request)) {
			$arr = $glob_request;
		}
		if (!empty($parent) && is_array($parent)) {
			$arr = $parent;
		}
		
		switch ($type) {
			case 'mail':
			case 'email':
				return !empty($arr[$key]) ? filter_var($arr[$key], FILTER_VALIDATE_EMAIL) : $default;
				
			case 'float':
				if (empty($default)) {
					$default = 0;
				}
				
				return ( !empty($arr[$key]) && is_numeric($arr[$key]) ) ? (float) $arr[$key] : $default;
				
			case 'int':
				if (empty($default)) {
					$default = 0;
				}
				
				return ( !empty($arr[$key]) && is_numeric($arr[$key]) ) ? (int) $arr[$key] : $default;
				
			case 'array':
				if (empty($default) || !is_array($default)) {
					$default = array();
				}
				
				return !empty($arr[$key]) ? filter_var($arr[$key], FILTER_REQUIRE_ARRAY) : $default;
				
			case 'string':
				return !empty($arr[$key]) ? filter_var($arr[$key], FILTER_SANITIZE_STRING) : $default;
				
			default:
				return !empty($arr[$key]) ? filter_var($arr[$key], FILTER_DEFAULT) : $default;
		}
	}
}
