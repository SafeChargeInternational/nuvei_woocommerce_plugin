<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Download_Plans extends Nuvei_Request
{
    /**
     * 
     * @param array $args - default empty parameter
     * @return array|false
     */
    public function process($args = array()) {
        $params = array(
            'planStatus'		=> 'ACTIVE',
            'currency'			=> '',
        );
		
		return $this->call_rest_api('getPlansList', $params);
    }

    protected function get_checksum_params() {
        return array('merchantId', 'merchantSiteId', 'currency', 'planStatus', 'timeStamp');
    }
}
