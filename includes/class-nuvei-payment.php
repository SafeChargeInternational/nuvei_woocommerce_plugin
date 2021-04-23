<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Payment extends Nuvei_Request
{
    /**
     * @param int       $order_id
     * @param string    $return_success_url
     * @param string    $return_error_url
     */
    public function process($order_id, $return_success_url, $return_error_url) {
        $order      = wc_get_order($order_id);
        $addresses  = $this->get_order_addresses();
        
        // complicated way to filter all $_POST input, but WP will be happy
		$post_array = $_POST;
		
		array_walk_recursive($post_array, function (&$value) {
			$value = trim($value);
			$value = filter_var($value);
		});
		// complicated way to filter all $_POST input, but WP will be happy END
        
		$params = array(
            'clientUniqueId'    => $this->set_cuid($order_id),
            'currency'          => $order->get_currency(),
            'amount'            => (string) $order->get_total(),
            'billingAddress'	=> $addresses['billingAddress'],
            'userDetails'       => $addresses['billingAddress'],
            'shippingAddress'	=> $addresses['shippingAddress'],
            'sessionToken'      => $post_array['lst'],
            
            'items'             => array(array(
                'name'      => $order_id,
                'price'     => $params['amount'],
                'quantity'  => 1,
            )),

            'amountDetails'     => array(
                'totalShipping'     => '0.00',
                'totalHandling'     => '0.00',
                'totalDiscount'     => '0.00',
                'totalTax'          => '0.00',
            ),

            'urlDetails'        => array(
                'successUrl'        => $return_success_url,
                'failureUrl'        => $return_error_url,
                'pendingUrl'        => $return_success_url,
                'notificationUrl'   => $this->set_notify_url(),
            ),
        );
		
		$sc_payment_method = $post_array['sc_payment_method'];
		
		// UPO
		if (is_numeric($sc_payment_method)) {
			$endpoint_method                                = 'payment';
			$params['paymentOption']['userPaymentOptionId'] = $sc_payment_method;
			$params['userTokenId']							= $order->get_billing_email();
		} else { // APM
			$endpoint_method         = 'paymentAPM';
			$params['paymentMethod'] = $sc_payment_method;
			
			if (!empty($post_array[$sc_payment_method])) {
				$params['userAccountDetails'] = $post_array[$sc_payment_method];
			}
			
			if ($this->get_param('nuvei_save_upo') == 1) {
				$params['userTokenId'] = $order->get_billing_email();
			}
		}
        
        return $this->call_rest_api($endpoint_method, $params);
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
