<?php

defined( 'ABSPATH' ) || exit;

/**
 * @author Nuvei
 */
class Nuvei_Download_Plans extends Nuvei_Request
{
    /**
     * @return array|false
     */
    public function process() {
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
