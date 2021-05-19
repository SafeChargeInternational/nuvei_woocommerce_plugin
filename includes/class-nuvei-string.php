<?php

defined( 'ABSPATH' ) || exit;

/**
 * A class to help with some strings.
 */
class Nuvei_String {

	/**
	 * Generate base of the Notify URL.
	 * 
	 * @return string
	 */
	public static function get_notify_url($plugin_settings) {
		$url_part = get_site_url();
			
//		$url = $url_part . ( strpos($url_part, '?') !== false ? '&' : '?' )
//			. 'wc-api=sc_listener&stop_dmn=' . NUVEI_STOP_DMN;
        
        $url = $url_part . ( strpos($url_part, '?') !== false ? '&' : '?' )
			. 'wc-api=nuvei_listener'
            . '&save_logs=' . $plugin_settings['save_logs']
            . '&test_mode=' . $plugin_settings['test']
            . '&stop_dmn=' . NUVEI_STOP_DMN;
		
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
	 * Convert string to a URL frendly slug.
	 * 
	 * @param string $text
	 * @return string
	 */
	public static function get_slug( $text = '') {
		return str_replace(' ', '-', strtolower($text));
	}
	
	/**
	 * Convert 5 letter locale to 2 letter locale.
	 * 
	 * @param string $locale
	 * @return string
	 */
	public static function format_location( $locale) {
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
