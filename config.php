<?php

/**
 * Put all Constants here.
 *
 * @author Nuvei
 */

define('NUVEI_GATEWAY_TITLE', 'Nuvei');

// some keys for order metadata, we make them hiden when starts with underscore
define('NUVEI_AUTH_CODE_KEY',       '_authCode');
define('NUVEI_TRANS_ID',            '_transactionId');
define('NUVEI_RESP_TRANS_TYPE',     '_transactionType');
define('NUVEI_PAYMENT_METHOD',      '_paymentMethod');
define('NUVEI_ORDER_HAS_REFUND',    '_scHasRefund');
define('NUVEI_REFUNDS',             '_sc_refunds');
define('NUVEI_SOURCE_APPLICATION',  'WOOCOMMERCE_PLUGIN');
define('NUVEI_GLOB_ATTR_NAME',      'Nuvei Payment Plan'); // the name of the Nuvei Global Product Attribute name
define('NUVEI_STOP_DMN',            0); // manually stop DMN process
define('NUVEI_CUID_POSTFIX',        '_sandbox_apm'); // postfix for Sandbox APM payments
