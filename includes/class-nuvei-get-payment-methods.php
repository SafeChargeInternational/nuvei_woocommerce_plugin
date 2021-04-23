<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Get_Payment_Methods extends Nuvei_Request
{
    public function process() {
        
    }
    
    protected function get_checksum_params() {
        return array('merchantId', 'merchantSiteId', 'clientRequestId', 'timeStamp');
    }
}
