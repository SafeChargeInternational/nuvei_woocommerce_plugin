<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Delete_Upo extends Nuvei_Request
{
    /**
     * @param array $args
     * @return array|false
     */
    public function process(array $args) {
        if(empty($args['email']) || empty($args['upo_id'])) {
            Nuvei_Logger::write($args, 'Nuvei_Delete_Upo error, missiing email and/or upo_id parameter:');
            return false;
        }
        
        $params = array(
            'userTokenId'            => $args['email'],
            'userPaymentOptionId'    => $args['upo_id'],
        );
		
		return $this->call_rest_api('deleteUPO', $params);
    }

    protected function get_checksum_params() {
        return array('merchantId', 'merchantSiteId', 'userTokenId', 'clientRequestId', 'userPaymentOptionId', 'timeStamp');
    }
}
