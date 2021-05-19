<?php

defined( 'ABSPATH' ) || exit;

/**
 * A class for Refund requests.
 */
class Nuvei_Refund extends Nuvei_Request {

	/**
	 * The main method.
	 * 
	 * @param array $data
	 * @return array|false
	 */
	public function process() {
		$data = current(func_get_args());
		
		if (empty($data['order_id']) 
			|| empty($data['ref_amount'])
			|| empty($data['tr_id'])
		) {
			Nuvei_Logger::write($data, 'Nuvei_Refund error missing mandatoriy parameters.');
			return false;
		}
		
		$notify_url	= Nuvei_String::get_notify_url($this->plugin_settings);
		$time		= gmdate('YmdHis', time());
		
		$ref_parameters = array(
			'clientRequestId'       => $data['order_id'] . '_' . $time . '_' . uniqid(),
			'clientUniqueId'        => $time . '_' . uniqid(),
			'amount'                => number_format($data['ref_amount'], 2, '.', ''),
			'currency'              => get_woocommerce_currency(),
			'relatedTransactionId'  => $data['tr_id'], // GW Transaction ID
		);
		
		return $this->call_rest_api('refundTransaction', $ref_parameters);
	}

	protected function get_checksum_params() {
		return  array('merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'url', 'timeStamp');
	}
}
