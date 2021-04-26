<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Get_Upos extends Nuvei_Request
{
    /**
     * @param array $args - the Open Order data
     * @return array|bool
     */
    public function process(array $args = array()) {
        if(empty($args['billingAddress']['email'])) {
            Nuvei_Logger::write($args, 'Nuvei_Get_Upos error, missing Billing Address Email.');
            return false;
        }
        
        $upo_params = array(
            'userTokenId' => $args['billingAddress']['email'],
        );
        
        return $this->call_rest_api('getUserUPOs', $upo_params);
    }

//    public function process(array $pms) {
//		$upos      = array();
//        $user_mail = Nuvei_Http::get_param('billing_email', 'mail');
//		
//        if (empty($user_mail)) {
//            $addresses = $this->get_order_addresses();
//
//            if (!empty($addresses['billingAddress']['email'])) {
//                $user_mail = $addresses['billingAddress']['email'];
//            }
//        }
//
//        $upo_params = array(
//            'userTokenId' => $user_mail,
//        );
//        
//        $upo_res = $this->call_rest_api('getUserUPOs', $upo_params);
//        
//        if (is_array($upo_res['paymentMethods'])) {
//            foreach ($upo_res['paymentMethods'] as $data) {
//                // chech if it is not expired
//                if (!empty($data['expiryDate']) && gmdate('Ymd') > $data['expiryDate']) {
//                    continue;
//                }
//
//                if (empty($data['upoStatus']) || 'enabled' !== $data['upoStatus']) {
//                    continue;
//                }
//
//                // search for same method in APMs, use this UPO only if it is available there
//                foreach ($pms as $pm_data) {
//                    // found it
//                    if ($pm_data['paymentMethod'] === $data['paymentMethodName']) {
//                        $data['logoURL'] = @$pm_data['logoURL'];
//                        $data['name']    = @$pm_data['paymentMethodDisplayName'][0]['message'];
//
//                        $upos[] = $data;
//                        break;
//                    }
//                }
//            }
//        }
//        
//        return array(
//            'upos'      => $upos,
//            'user_mail' => $user_mail,
//        );
//    }

    protected function get_checksum_params() {
        return array('merchantId', 'merchantSiteId', 'userTokenId', 'clientRequestId', 'timeStamp');
    }
}
