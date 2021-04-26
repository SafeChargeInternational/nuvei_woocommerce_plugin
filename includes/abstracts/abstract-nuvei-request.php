<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
abstract class Nuvei_Request
{
    protected $plugin_settings;
    
    private $request_base_params;
    
    // array details to validate request parameters
	private $params_validation = array(
		// deviceDetails
		'deviceType' => array(
			'length' => 10,
			'flag'    => FILTER_SANITIZE_STRING
		),
		'deviceName' => array(
			'length' => 255,
			'flag'    => FILTER_DEFAULT
		),
		'deviceOS' => array(
			'length' => 255,
			'flag'    => FILTER_DEFAULT
		),
		'browser' => array(
			'length' => 255,
			'flag'    => FILTER_DEFAULT
		),
		// deviceDetails END
		
		// userDetails, shippingAddress, billingAddress
		'firstName' => array(
			'length' => 30,
			'flag'    => FILTER_DEFAULT
		),
		'lastName' => array(
			'length' => 40,
			'flag'    => FILTER_DEFAULT
		),
		'address' => array(
			'length' => 60,
			'flag'    => FILTER_DEFAULT
		),
		'cell' => array(
			'length' => 18,
			'flag'    => FILTER_DEFAULT
		),
		'phone' => array(
			'length' => 18,
			'flag'    => FILTER_DEFAULT
		),
		'zip' => array(
			'length' => 10,
			'flag'    => FILTER_DEFAULT
		),
		'city' => array(
			'length' => 30,
			'flag'    => FILTER_DEFAULT
		),
		'country' => array(
			'length' => 20,
			'flag'    => FILTER_SANITIZE_STRING
		),
		'state' => array(
			'length' => 2,
			'flag'    => FILTER_SANITIZE_STRING
		),
		'county' => array(
			'length' => 255,
			'flag'    => FILTER_DEFAULT
		),
		// userDetails, shippingAddress, billingAddress END
		
		// specific for shippingAddress
		'shippingCounty' => array(
			'length' => 255,
			'flag'    => FILTER_DEFAULT
		),
		'addressLine2' => array(
			'length' => 50,
			'flag'    => FILTER_DEFAULT
		),
		'addressLine3' => array(
			'length' => 50,
			'flag'    => FILTER_DEFAULT
		),
		// specific for shippingAddress END
		
		// urlDetails
		'successUrl' => array(
			'length' => 1000,
			'flag'    => FILTER_VALIDATE_URL
		),
		'failureUrl' => array(
			'length' => 1000,
			'flag'    => FILTER_VALIDATE_URL
		),
		'pendingUrl' => array(
			'length' => 1000,
			'flag'    => FILTER_VALIDATE_URL
		),
		'notificationUrl' => array(
			'length' => 1000,
			'flag'    => FILTER_VALIDATE_URL
		),
		// urlDetails END
	);
	
	private $params_validation_email = array(
		'length'    => 79,
		'flag'        => FILTER_VALIDATE_EMAIL
	);
    
    private $devices_os     = array('iphone', 'ipad', 'android', 'silk', 'blackberry', 'touch', 'linux', 'windows', 'mac');
    private $browsers       = array('ucbrowser', 'firefox', 'chrome', 'opera', 'msie', 'edge', 'safari', 'blackberry', 'trident');
    private $device_types   = array('macintosh', 'tablet', 'mobile', 'tv', 'windows', 'linux', 'tv', 'smarttv', 'googletv', 'appletv', 'hbbtv', 'pov_tv', 'netcast.tv', 'bluray');
    
    public abstract function process(array $args = array());
    protected abstract function get_checksum_params();

    public function __construct(array $plugin_settings) {
        $time                       = gmdate('Ymdhis');
        $all_prod_data              = $this->get_products_data();
        $this->plugin_settings      = $plugin_settings;
        
        $this->request_base_params  = array(
            'merchantId'        => $plugin_settings['merchantId'],
            'merchantSiteId'    => $plugin_settings['merchantSiteId'],
            'clientRequestId'   => $time . '_' . uniqid(),
            'timeStamp'         => $time,
            'webMasterId'       => 'WooCommerce ' . WOOCOMMERCE_VERSION,
            'sourceApplication' => NUVEI_SOURCE_APPLICATION,
            'encoding'          => 'UTF-8',
            'deviceDetails'     => $this->get_device_details(),
            'merchantDetails'	=> array(
                'customField1'      => json_encode($all_prod_data['subscr_data']),   // put subscr data here
                'customField2'      => json_encode($all_prod_data['products_data']), // item details
                'customField3'      => time(),                          // create time
            ),
        );
    }
    
    /**
	 * Help function to generate Billing and Shipping details.
	 * 
	 * @global Woocommerce $woocommerce
	 * @return array
	 */
	protected function get_order_addresses() {
		global $woocommerce;
		
		$form_params = array();
		$addresses   = array();
		$cart        = $woocommerce->cart;
		
		if (!empty(Nuvei_Http::get_param('scFormData'))) {
			parse_str(Nuvei_Http::get_param('scFormData'), $form_params); 
		}
		
		# set params
		// billing
		$bfn = Nuvei_Http::get_param('billing_first_name', 'string', '', $form_params);
		if (empty($bfn)) {
			$bfn = $cart->get_customer()->get_billing_first_name();
		}
		
		$bln = Nuvei_Http::get_param('billing_last_name', 'string', '', $form_params);
		if (empty($bln)) {
			$bln = $cart->get_customer()->get_billing_last_name();
		}
		
		$ba = Nuvei_Http::get_param('billing_address_1', 'string', '', $form_params)
			. ' ' . Nuvei_Http::get_param('billing_address_1', 'string', '', $form_params);
		if (empty($ba)) {
			$ba = $cart->get_customer()->get_billing_address() . ' '
				. $cart->get_customer()->get_billing_address_2();
		}
		
		$bp = Nuvei_Http::get_param('billing_phone', 'string', '', $form_params);
		if (empty($bp)) {
			$bp = $cart->get_customer()->get_billing_phone();
		}
		
		$bz = Nuvei_Http::get_param('billing_postcode', 'int', 0, $form_params);
		if (empty($bz)) {
			$bz = $cart->get_customer()->get_billing_postcode();
		}
		
		$bc = Nuvei_Http::get_param('billing_city', 'string', '', $form_params);
		if (empty($bc)) {
			$bc = $cart->get_customer()->get_billing_city();
		}
		
		$bcn = Nuvei_Http::get_param('billing_country', 'string', '', $form_params);
		if (empty($bcn)) {
			$bcn = $cart->get_customer()->get_billing_country();
		}
		
		$be = Nuvei_Http::get_param('billing_email', 'mail', '', $form_params);
		if (empty($be)) {
			$be = $cart->get_customer()->get_billing_email();
		}
		
		// shipping
		$sfn = Nuvei_Http::get_param('shipping_first_name', 'string', '', $form_params);
		if (empty($sfn)) {
			$sfn = $cart->get_customer()->get_shipping_first_name();
		}
		
		$sln = Nuvei_Http::get_param('shipping_last_name', 'string', '', $form_params);
		if (empty($sln)) {
			$sln = $cart->get_customer()->get_shipping_last_name();
		}
		
		$sa = Nuvei_Http::get_param('shipping_address_1', 'string', '', $form_params)
			. ' ' . Nuvei_Http::get_param('shipping_address_2', 'string', '', $form_params);
		if (empty($sa)) {
			$sa = $cart->get_customer()->get_shipping_address() . ' '
				. $cart->get_customer()->get_shipping_address_2();
		}
		
		$sz = Nuvei_Http::get_param('shipping_postcode', 'string', '', $form_params);
		if (empty($sz)) {
			$sz = $cart->get_customer()->get_shipping_postcode();
		}
		
		$sc = Nuvei_Http::get_param('shipping_city', 'string', '', $form_params);
		if (empty($sc)) {
			$sc = $cart->get_customer()->get_shipping_city();
		}
		
		$scn = Nuvei_Http::get_param('shipping_country', 'string', '', $form_params);
		if (empty($scn)) {
			$scn = $cart->get_customer()->get_shipping_country();
		}
		# set params END
		
		return array(
			'billingAddress'	=> array(
				'firstName'	=> $bfn,
				'lastName'	=> $bln,
				'address'	=> $ba,
				'phone'		=> $bp,
				'zip'		=> $bz,
				'city'		=> $bc,
				'country'	=> $bcn,
				'email'		=> $be,
			),
			
			'shippingAddress'	=> array(
				'firstName'	=> $sfn,
				'lastName'  => $sln,
				'address'   => $sa,
				'zip'       => $sz,
				'city'      => $sc,
				'country'   => $scn,
			),
		);
	}

    /**
	 * Call REST API with cURL post and get response.
	 * The URL depends from the case.
	 *
	 * @param type $method - API method
	 * @param array $params - parameters
	 *
	 * @return mixed
	 */
	protected function call_rest_api($method, $params) {
		$concat = '';
        $resp   = false;
        $url    = $this->get_endpoint_base() . $method . '.do';
        $params = $this->validate_parameters($params); // validate parameters
        
        if(isset($params['status']) && 'ERROR' == $params['status']) {
            return $params;
        }
        
        $all_params = array_merge($this->request_base_params, $params);
        
        // add the checksum
        $checksum_keys = $this->get_checksum_params();
        
        if(is_array($checksum_keys)) {
            foreach($checksum_keys as $idx => $key) {
                if(isset($all_params[$key])) {
                    $concat .= $all_params[$key];
                }
            }
        }
        
        $all_params['checksum'] = hash(
			$this->plugin_settings['hash_type'],
			$concat . $this->plugin_settings['secret']
		);
        // add the checksum END
        
        Nuvei_Logger::write(
            array(
                'URL'       => $url,
                'params'    => $all_params
            ),
            'Nuvei Request all params:'
        );
        
		$json_post = json_encode($all_params);
		
		try {
			$header =  array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($json_post),
			);
			
			// create cURL post
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json_post);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

			$resp = curl_exec($ch);
			curl_close($ch);
            
			if (false === $resp) {
				return array(
					'status' => 'ERROR',
					'message' => 'REST API ERROR: response is false'
				);
			}
            
            $resp_array	= json_decode($resp, true);
            // return base params to the sender
            $resp_array['request_base_params'] = $this->request_base_params;
            
            Nuvei_Logger::write($resp_array, 'Nuvei Request response:');

			return $resp_array;
		} catch (Exception $e) {
			return array(
				'status' => 'ERROR',
				'message' => 'Exception ERROR when call REST API: ' . $e->getMessage()
			);
		}
	}
    
    /**
	 * Function get_device_details
	 *
	 * Get browser and device based on HTTP_USER_AGENT.
	 * The method is based on D3D payment needs.
	 *
	 * @return array $device_details
	 */
	protected function get_device_details() {
		$device_details = array(
			'deviceType'    => 'UNKNOWN', // DESKTOP, SMARTPHONE, TABLET, TV, and UNKNOWN
			'deviceName'    => 'UNKNOWN',
			'deviceOS'      => 'UNKNOWN',
			'browser'       => 'UNKNOWN',
			'ipAddress'     => '0.0.0.0',
		);
		
		if (empty($_SERVER['HTTP_USER_AGENT'])) {
			$device_details['Warning'] = 'User Agent is empty.';
			
			return $device_details;
		}
		
		$user_agent = strtolower(filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_STRING));
		
		if (empty($user_agent)) {
			$device_details['Warning'] = 'Probably the merchant Server has problems with PHP filter_var function!';
			
			return $device_details;
		}
		
		$device_details['deviceName'] = $user_agent;

        foreach ($this->device_types as $d) {
            if (strstr($user_agent, $d) !== false) {
                if (in_array($d, array('linux', 'windows', 'macintosh'), true)) {
                    $device_details['deviceType'] = 'DESKTOP';
                } elseif ('mobile' === $d) {
                    $device_details['deviceType'] = 'SMARTPHONE';
                } elseif ('tablet' === $d) {
                    $device_details['deviceType'] = 'TABLET';
                } else {
                    $device_details['deviceType'] = 'TV';
                }

                break;
            }
        }

        foreach ($this->devices_os as $d) {
            if (strstr($user_agent, $d) !== false) {
                $device_details['deviceOS'] = $d;
                break;
            }
        }

        foreach ($this->browsers as $b) {
            if (strstr($user_agent, $b) !== false) {
                $device_details['browser'] = $b;
                break;
            }
        }

		// get ip
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip_address = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
		}
		if (empty($ip_address) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip_address = filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP);
		}
		if (empty($ip_address) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip_address = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP);
		}
		if (!empty($ip_address)) {
			$device_details['ipAddress'] = (string) $ip_address;
		} else {
			$device_details['Warning'] = array(
				'REMOTE_ADDR'			=> empty($_SERVER['REMOTE_ADDR'])
					? '' : filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP),
				'HTTP_X_FORWARDED_FOR'	=> empty($_SERVER['HTTP_X_FORWARDED_FOR'])
					? '' : filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP),
				'HTTP_CLIENT_IP'		=> empty($_SERVER['HTTP_CLIENT_IP'])
					? '' : filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP),
			);
		}
		
		return $device_details;
	}
    
    /**
     * function get_products_data
     * 
     * A help function to get Products data from the Cart and pass it to the
     * OpenOrder or UpdateOrder.
     * 
     * @return  array $data
     */
    protected function get_products_data() {
        global $woocommerce;
        
        $items  = $woocommerce->cart->get_cart();
        $data   = array(
            'subscr_data'	=> array(),
            'products_data'	=> array(),
        );
        
        foreach ($items as $item_id => $item) {
            $cart_product   = wc_get_product( $item['product_id'] );
            $cart_prod_attr = $cart_product->get_attributes();

            // get short items data
            $data['products_data'][$item['product_id']] = array(
                'quantity'	=> $item['quantity'],
                'price'		=> get_post_meta($item['product_id'] , '_price', true),
            );

            // check for variations
            if(empty($cart_prod_attr['pa_' . Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME)])) {
                continue;
            }

            Nuvei_Logger::write($item['variation_id'], 'item variation id');
            Nuvei_Logger::write($item['variation'], 'item variation');
            
            $taxonomy_name  = wc_attribute_taxonomy_name(Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME));
            $term           = get_term_by('slug', current($item['variation']), $taxonomy_name);

            if(is_wp_error($term) || empty($term->term_id)) {
                Nuvei_Logger::write($item['variation'], 'Error when try to get Term by Slug:');
                continue;
            }
            
            $term_meta = get_term_meta($term->term_id);
            
            $data['subscr_data'][$item['product_id']] = array(
                'planId'			=> $term_meta['planId'][0],
                'recurringAmount'	=> number_format($term_meta['recurringAmount'][0], 2, '.', ''),
            );

            $data['subscr_data'][$item['product_id']]['recurringPeriod']
                [$term_meta['recurringPeriodUnit'][0]] = $term_meta['recurringPeriodPeriod'][0];
            
            $data['subscr_data'][$item['product_id']]['startAfter']
                [$term_meta['startAfterUnit'][0]] = $term_meta['startAfterPeriod'][0];
            
            $data['subscr_data'][$item['product_id']]['endAfter']
                [$term_meta['endAfterUnit'][0]] = $term_meta['endAfterPeriod'][0];
            # optional data END
        }

        return $data;
    }
    
    /**
     * 
     * @return string
     */
    private function get_endpoint_base() {
		if ('yes' == $this->plugin_settings['test']) {
			return 'https://ppp-test.safecharge.com/ppp/api/v1/';
		}
		
		return 'https://secure.safecharge.com/ppp/api/v1/';
	}
    
    /**
     * 
     * @param array $params
     * @return array
     */
    private function validate_parameters($params) {
        // directly check the mails
		if (isset($params['billingAddress']['email'])) {
			if (!filter_var($params['billingAddress']['email'], $this->params_validation_email['flag'])) {
				
				return array(
					'status' => 'ERROR',
					'message' => 'REST API ERROR: The parameter Billing Address Email is not valid.'
				);
			}
			
			if (strlen($params['billingAddress']['email']) > $this->params_validation_email['length']) {
				return array(
					'status' => 'ERROR',
					'message' => 'REST API ERROR: The parameter Billing Address Email must be maximum '
						. $this->params_validation_email['length'] . ' symbols.'
				);
			}
		}
		
		if (isset($params['shippingAddress']['email'])) {
			if (!filter_var($params['shippingAddress']['email'], $this->params_validation_email['flag'])) {
				return array(
					'status' => 'ERROR',
					'message' => 'REST API ERROR: The parameter Shipping Address Email is not valid.'
				);
			}
			
			if (strlen($params['shippingAddress']['email']) > $this->params_validation_email['length']) {
				return array(
					'status' => 'ERROR',
					'message' => 'REST API ERROR: The parameter Shipping Address Email must be maximum '
						. $this->params_validation_email['length'] . ' symbols.'
				);
			}
		}
		// directly check the mails END
		
		foreach ($params as $key1 => $val1) {
			if (!is_array($val1) && !empty($val1) && array_key_exists($key1, $this->params_validation)) {
				$new_val = $val1;
				
				if (mb_strlen($val1) > $this->params_validation[$key1]['length']) {
					$new_val = mb_substr($val1, 0, $this->params_validation[$key1]['length']);
				}
				
				$params[$key1] = filter_var($new_val, $this->params_validation[$key1]['flag']);
			} elseif (is_array($val1) && !empty($val1)) {
				foreach ($val1 as $key2 => $val2) {
					if (!is_array($val2) && !empty($val2) && array_key_exists($key2, $this->params_validation)) {
						$new_val = $val2;

						if (mb_strlen($val2) > $this->params_validation[$key2]['length']) {
							$new_val = mb_substr($val2, 0, $this->params_validation[$key2]['length']);
						}

						$params[$key1][$key2] = filter_var($new_val, $this->params_validation[$key2]['flag']);
					}
				}
			}
		}
        
        return $params;
    }
    
    /**
	 * Set client unique id.
	 * We change it only for Sandbox (test) mode.
	 * 
	 * @param int $order_id
     * 
	 * @return int|string
	 */
	private function set_cuid( $order_id) {
		if ('yes' != $this->plugin_settings['test']) {
			return (int) $order_id;
		}
		
		return $order_id . '_' . time() . NUVEI_CUID_POSTFIX;
	}
}
