<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Refund extends Nuvei_Request
{
    /**
     * @param array $data
     * @return array|false
     */
    public function process(array $data) {
        if(empty($data['order_id']) 
            || empty($data['ref_amount'])
            || empty($data['tr_id'])
        ) {
            Nuvei_Logger::write($data, 'Nuvei_Refund error missing mandatoriy parameters.');
            return false;
        }
        
        $notify_url	= Nuvei_String::get_notify_url($this->plugin_settings);
		$time		= gmdate('YmdHis', time());
		
		$ref_parameters = array_merge(
            $this->get_request_base_params(),
            array(
                'clientRequestId'       => $data['order_id'] . '_' . $time . '_' . uniqid(),
                'clientUniqueId'        => $time . '_' . uniqid(),
                'amount'                => number_format($data['ref_amount'], 2, '.', ''),
                'currency'              => get_woocommerce_currency(),
                'relatedTransactionId'  => $data['tr_id'], // GW Transaction ID
                'urlDetails'            => array('notificationUrl' => $notify_url),
                'url'                   => $notify_url, // custom parameter
            )
        );
		
		return $this->call_rest_api('refundTransaction', $ref_parameters);
    }

    protected function get_checksum_params() {
        return  array('merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'url', 'timeStamp');
    }
}
