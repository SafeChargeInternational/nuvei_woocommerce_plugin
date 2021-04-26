<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Open_Order extends Nuvei_Request
{
    private $is_ajax;
    
    /**
     * 
     * @param array $plugin_settings
     * @param bool  $is_ajax
     */
    public function __construct(array $plugin_settings, $is_ajax = false) {
        parent::__construct($plugin_settings);
        
        $this->is_ajax = $is_ajax;
    }

    /**
     * @global object $woocommerce
     * 
     * @param array $args - default empty parameter
     * @return array|boolean
     */
    public function process($args = array()) {
        global $woocommerce;
		
		$cart           = $woocommerce->cart;
		$uniq_str       = gmdate('YmdHis') . '_' . uniqid();
		$ajax_params    = array();
		
		# try to update Order
		$uo_obj = new Nuvei_Update_Order($this->plugin_settings);
        $resp   = $uo_obj->process();
		
		if (!empty($resp['status']) && 'SUCCESS' == $resp['status']) {
			if ($this->is_ajax) {
				wp_send_json(array(
					'status'        => 1,
					'sessionToken'	=> $resp['sessionToken']
				));
				exit;
			}

			return $resp;
		}
		# try to update Order END
		
        $form_data = Nuvei_Http::get_param('scFormData');
        
		if (!empty($form_data)) {
			parse_str($form_data, $ajax_params); 
		}
		
		// check for a Product with Payment Plan
		$addresses  = $this->get_order_addresses();
		$oo_params  = array(
            'clientUniqueId'    => $uniq_str . '_wc_cart',
            'amount'            => (string) number_format((float) $cart->total, 2, '.', ''),
            'currency'          => get_woocommerce_currency(),
            'billingAddress'	=> $addresses['billingAddress'],
            'userDetails'       => $addresses['billingAddress'],
            'shippingAddress'	=> $addresses['shippingAddress'],
            'paymentOption'     => array('card' => array('threeD' => array('isDynamic3D' => 1))),
            'transactionType'   => $this->plugin_settings['payment_action'],
            'urlDetails'        => array(
                'notificationUrl'   => Nuvei_String::get_notify_url($this->plugin_settings),
            ),
        );
		
		$resp = $this->call_rest_api('openOrder', $oo_params);
		
		if (empty($resp['status'])
			|| empty($resp['sessionToken'])
			|| 'SUCCESS' != $resp['status']
		) {
			if ($this->is_ajax) {
				wp_send_json(array(
					'status'	=> 0,
					'msg'		=> $resp
				));
				exit;
			}
			
            return false;
		}
		
		// set them to session for the check before submit the data to the webSDK
		$_SESSION['nuvei_last_open_order_details'] = array(
			'amount'			=> $oo_params['amount'],
			'merchantDetails'	=> $resp['request_base_params']['merchantDetails'],
			'sessionToken'		=> $resp['sessionToken'],
			'clientRequestId'	=> $resp['request_base_params']['clientRequestId'],
			'orderId'			=> $resp['orderId'],
			'billingAddress'	=> $oo_params['billingAddress'],
		);
		
		Nuvei_Logger::write($cart->nuvei_last_open_order_details, 'nuvei_last_open_order_details');
		
		if ($this->is_ajax) {
			wp_send_json(array(
				'status'        => 1,
				'sessionToken'    => $resp['sessionToken']
			));
			exit;
		}
		
		return array_merge($resp, $oo_params);
    }
    
    /**
     * Return keys required to calculate checksum. Keys order is relevant.
     *
     * @return array
     */
    protected function get_checksum_params() {
        return array('merchantId', 'merchantSiteId', 'clientRequestId', 'amount', 'currency', 'timeStamp');
    }
}
