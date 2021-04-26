<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Settle_Void extends Nuvei_Request
{
    /**
     * 
     * @param array $data
     */
    public function process(array $data) {
        if(empty($data['order_id']) 
            || empty($data['action'])
            || empty($data['method'])
        ) {
            Nuvei_Logger::write($data, 'Nuvei_Settle_Void error missing mandatoriy parameters.');
            return false;
        }
        
        $order      = wc_get_order($data['order_id']);
        $notify_url = Nuvei_String::get_notify_url($this->plugin_settings);
        
        $params = array_merge(
            $this->get_request_base_params(),
            array(
                'clientUniqueId'        => $data['order_id'],
                'amount'                => (string) $order->get_total(),
                'currency'              => get_woocommerce_currency(),
                'relatedTransactionId'  => $order->get_meta(NUVEI_TRANS_ID),
                'authCode'              => $order->get_meta(NUVEI_AUTH_CODE_KEY),
                'urlDetails'            => array(
                    'notificationUrl' => $notify_url,
                ),
                'url'                   => $notify_url, // a custom parameter
            )
        );

        return $this->call_rest_api($data['method'], $params);
    }

    protected function get_checksum_params() {
        return array('merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'authCode', 'url', 'timeStamp');
    }
}
