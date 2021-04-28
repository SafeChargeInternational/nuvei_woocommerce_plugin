<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Update_Order extends Nuvei_Request
{
    public function __construct(array $plugin_settings) {
        parent::__construct($plugin_settings);
    }
    
    /**
     * @global Woocommerce $woocommerce
     * @return array
     */
    public function process() {
        if (!session_id()) {
			session_start();
		}
		
        Nuvei_Logger::write(
            isset($_SESSION['nuvei_last_open_order_details']) ? $_SESSION['nuvei_last_open_order_details'] : '',
			'update_order() - session[nuvei_last_open_order_details]'
        );
        
		if (empty($_SESSION['nuvei_last_open_order_details'])
			|| empty($_SESSION['nuvei_last_open_order_details']['sessionToken'])
			|| empty($_SESSION['nuvei_last_open_order_details']['orderId'])
			|| empty($_SESSION['nuvei_last_open_order_details']['clientRequestId'])
		) {
			Nuvei_Logger::write('update_order() - Missing last Order session data.');
			
			return array('status' => 'ERROR');
		}
		
		global $woocommerce;
		
		$cart           = $woocommerce->cart;
		$cart_amount    = (string) number_format((float) $cart->total, 2, '.', '');
		$addresses      = $this->get_order_addresses();
        
		// create Order upgrade
		$params = array(
			'sessionToken'		=> $_SESSION['nuvei_last_open_order_details']['sessionToken'],
			'orderId'			=> $_SESSION['nuvei_last_open_order_details']['orderId'],
			'clientRequestId'	=> $_SESSION['nuvei_last_open_order_details']['clientRequestId'],
			'currency'			=> get_woocommerce_currency(),
			'amount'			=> $cart_amount,
            'billingAddress'	=> $addresses['billingAddress'],
            'userDetails'       => $addresses['billingAddress'],
            'shippingAddress'	=> $addresses['shippingAddress'],
			'items'				=> array(
				array(
					'name'		=> 'wc_order',
					'price'		=> $cart_amount,
					'quantity'	=> 1
				)
			),
		);
		
		$resp = $this->call_rest_api('updateOrder', $params);
		
		# Success
		if (!empty($resp['status']) && 'SUCCESS' == $resp['status']) {
			$_SESSION['nuvei_last_open_order_details']['amount']					= $cart_amount;
			$_SESSION['nuvei_last_open_order_details']['merchantDetails']			= $resp['request_base_params']['merchantDetails'];
			$_SESSION['nuvei_last_open_order_details']['billingAddress']['country']	= $params['billingAddress']['country'];
			
			return array_merge($resp, $params);
		}
		
		Nuvei_Logger::write('Nuvei_Update_Order - Order update was not successful.');

		return array('status' => 'ERROR');
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
