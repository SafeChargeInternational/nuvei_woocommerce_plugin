<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_String
{
    /**
     * @return string
     */
	public static function get_notify_url($plugin_settings) {
		$url_part = get_site_url();
			
		$url = $url_part . ( strpos($url_part, '?') !== false ? '&' : '?' )
			. 'wc-api=sc_listener&stopDMN=' . NUVEI_STOP_DMN;
		
		// some servers needs / before ?
		if (strpos($url, '?') !== false && strpos($url, '/?') === false) {
			$url = str_replace('?', '/?', $url);
		}
		
		// force Notification URL protocol to http
		if ('yes' === $plugin_settings['use_http'] && false !== strpos($url, 'https://')) {
			$url = str_replace('https://', '', $url);
			$url = 'http://' . $url;
		}
		
		return $url;
	}
    
    /**
     * @param string $text
     * 
     * @return string
     */
    public static function get_slug($text = '') {
        return str_replace(' ', '-', strtolower($text));
    }
    
    /**
     * @param string $locale
     * 
     * @return string
     */
    public static function format_location($locale) {
		switch ($locale) {
			case 'de_DE':
				return 'de';
				
			case 'zh_CN':
				return 'zh';
				
			case 'en_GB':
			default:
				return 'en';
		}
	}
}
