<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Get_Apms extends Nuvei_Request
{
    /**
     * @param array $args
     * @return array|false
     */
    public function process() {
        $args = current(func_get_args());
        
		$currency = !empty($args['currency']) ? $args['currency'] : get_woocommerce_currency();
		
		if (!empty($args['billingAddress']['country'])) {
			$countryCode = $args['billingAddress']['country'];
		} elseif (!empty($_SESSION['nuvei_last_open_order_details']['billingAddress']['country'])) {
			$countryCode = $_SESSION['nuvei_last_open_order_details']['billingAddress']['country'];
		} else {
            $addresses      = $this->get_order_addresses();
            
            if(!empty($addresses['billingAddress']['country'])) {
                $countryCode = $addresses['billingAddress']['country'];
            } else {
                $countryCode = '';
            }
		}
		
		$apms_params = array(
            'sessionToken'      => $args['sessionToken'],
            'currencyCode'      => $currency,
            'countryCode'       => $countryCode,
            'languageCode'      => Nuvei_String::format_location(get_locale()),
        );
        
        return $this->call_rest_api('getMerchantPaymentMethods', $apms_params);
    }
    
    protected function get_checksum_params() {
        return array('merchantId', 'merchantSiteId', 'clientRequestId', 'timeStamp');
    }
}
