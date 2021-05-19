<?php

defined( 'ABSPATH' ) || exit;

/**
 * Main class for the Nuvei Plugin
 */

class Nuvei_Gateway extends WC_Payment_Gateway {

	private $sc_order;
	private $plugin_data  = array();
	private $subscr_units = array('year', 'month', 'day');
	
	public function __construct() {
		# settings to get/save options
		$this->id                 = NUVEI_GATEWAY_NAME;
		$this->method_title       = NUVEI_GATEWAY_TITLE;
		$this->method_description = 'Pay with ' . NUVEI_GATEWAY_TITLE . '.';
		$this->icon               = plugin_dir_url(NUVEI_PLUGIN_FILE) . 'assets/icons/nuvei.png';
		$this->has_fields         = false;

		$this->init_settings();
		
		// required for the Store
		$this->title       = $this->get_setting('title', $this->method_title);
		$this->description = $this->get_setting('description', $this->method_description);
		$this->test        = $this->get_setting('test', '');
		$this->rewrite_dmn = $this->get_setting('rewrite_dmn', 'no');
		$this->plugin_data = get_plugin_data(plugin_dir_path(NUVEI_PLUGIN_FILE) . DIRECTORY_SEPARATOR . 'index.php');
		
        $nuvei_vars = array(
			'save_logs' => $this->get_setting('save_logs'),
			'test_mode' => $this->get_setting('test'),
		);
        
        if(!is_admin() && !empty(WC()->session)) {
            WC()->session->set('nuvei_vars', $nuvei_vars);
        }
		
		$this->use_wpml_thanks_page = !empty($this->settings['use_wpml_thanks_page']) 
			? $this->settings['use_wpml_thanks_page'] : 'no';
		
		$this->supports[] = 'refunds'; // to enable auto refund support
		
		$this->init_form_fields();
		
		$this->msg['message'] = '';
		$this->msg['class']   = '';
        
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( &$this, 'process_admin_options' )
		);
		
		// This crash Refund action
		add_action('woocommerce_order_after_calculate_totals', array($this, 'return_settle_btn'));
		add_action('woocommerce_order_status_refunded', array($this, 'restock_on_refunded_status'));
	}
    
    /**
	 * Function init_form_fields
	 * Set all fields for admin settings page.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'nuvei_woocommerce'),
				'type' => 'checkbox',
				'label' => __('Enable ' . NUVEI_GATEWAY_TITLE . ' Payment Module.', 'nuvei_woocommerce'),
				'default' => 'no'
			),
		   'title' => array(
				'title' => __('Default title:', 'nuvei_woocommerce'),
				'type'=> 'text',
				'description' => __('This is the payment method which the user sees during checkout.', 'nuvei_woocommerce'),
				'default' => __('Secure Payment with Nuvei', 'nuvei_woocommerce')
			),
			'description' => array(
				'title' => __('Description:', 'nuvei_woocommerce'),
				'type' => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'nuvei_woocommerce'),
				'default' => 'Place order to get to our secured payment page to select your payment option'
			),
			'test' => array(
				'title' => __('Site Mode', 'nuvei_woocommerce') . ' *',
				'type' => 'select',
				'required' => 'required',
				'options' => array(
					'' => __('Select an option...'),
					'yes' => 'Sandbox',
					'no' => 'Production',
				),
			),
			'merchantId' => array(
				'title' => __('Merchant ID', 'nuvei_woocommerce') . ' *',
				'type' => 'text',
				'required' => true,
				'description' => __('Merchant ID is provided by ' . NUVEI_GATEWAY_TITLE . '.')
			),
			'merchantSiteId' => array(
				'title' => __('Merchant Site ID', 'nuvei_woocommerce') . ' *',
				'type' => 'text',
				'required' => true,
				'description' => __('Merchant Site ID is provided by ' . NUVEI_GATEWAY_TITLE . '.')
			),
			'secret' => array(
				'title' => __('Secret key', 'nuvei_woocommerce') . ' *',
				'type' => 'text',
				'required' => true,
				'description' =>  __('Secret key is provided by ' . NUVEI_GATEWAY_TITLE, 'nuvei_woocommerce'),
			),
			'hash_type' => array(
				'title' => __('Hash type', 'nuvei_woocommerce') . ' *',
				'type' => 'select',
				'required' => true,
				'description' => __('Choose Hash type provided by ' . NUVEI_GATEWAY_TITLE, 'nuvei_woocommerce'),
				'options' => array(
					'' => __('Select an option...'),
					'sha256' => 'sha256',
					'md5' => 'md5',
				)
			),
			'payment_action' => array(
				'title' => __('Payment action', 'nuvei_woocommerce') . ' *',
				'type' => 'select',
				'required' => true,
				'options' => array(
					'' => __('Select an option...'),
					'Sale' => 'Authorize and Capture',
					'Auth' => 'Authorize',
				)
			),
			'use_upos' => array(
				'title' => __('Allow client to use UPOs', 'nuvei_woocommerce'),
				'type' => 'select',
				'options' => array(
					0 => 'No',
					1 => 'Yes',
				)
			),
			'merchant_style' => array(
				'title' => __('Custom style', 'nuvei_woocommerce'),
				'type' => 'textarea',
				'default' => '',
				'description' => __('Override the build-in style for the Nuvei elements.', 'nuvei_woocommerce')
			),
			'notify_url' => array(
				'title' => __('Notify URL', 'nuvei_woocommerce'),
				'type' => 'text',
				'default' => '',
				'description' => Nuvei_String::get_notify_url($this->settings),
				'type' => 'hidden'
			),
			'use_http' => array(
				'title' => __('Use HTTP', 'nuvei_woocommerce'),
				'type' => 'checkbox',
				'label' => __('Force protocol where receive DMNs to be HTTP. You must have valid certificate for HTTPS! In case the checkbox is not set the default Protocol will be used.', 'nuvei_woocommerce'),
				'default' => 'no'
			),
			// actually this is not for the DMN, but for return URL after Cashier page
			'rewrite_dmn' => array(
				'title' => __('Rewrite DMN', 'nuvei_woocommerce'),
				'type' => 'checkbox',
				'label' => __('Check this option ONLY when URL symbols like "+", " " and "%20" in the DMN cause error 404 - Page not found.', 'nuvei_woocommerce'),
				'default' => 'no'
			),
			'use_wpml_thanks_page' => array(
				'title' => __('Use WPML "Thank you" page.', 'nuvei_woocommerce'),
				'type' => 'checkbox',
				'label' => __('Works only if you have installed and configured WPML plugin. Please, use it careful, this option can brake your "Thank you" page and DMN recieve page!', 'nuvei_woocommerce'),
				'default' => 'no'
			),
			'save_logs' => array(
				'title' => __('Save logs', 'nuvei_woocommerce'),
				'type' => 'checkbox',
				'label' => __('Create and save daily log files. This can help for debugging and catching bugs.', 'nuvei_woocommerce'),
				'default' => 'yes'
			),
			'get_plans_btn' => array(
				'title' => __('Download Payment Plans', 'nuvei_woocommerce'),
				'type' => 'button',
			),
		);
	}
	
	/**
	 * Generate Button HTML.
	 * Custom function to generate beautiful button in admin settings.
	 * Thanks to https://gist.github.com/BFTrick/31de2d2235b924e853b0
	 *
	 * @param mixed $key
	 * @param mixed $data
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function generate_button_html( $key, $data) {
		Nuvei_Logger::write($key);
		Nuvei_Logger::write($data);
		
		$defaults = array(
			'class'             => 'button-secondary',
			'css'               => '',
			'custom_attributes' => array(),
			'desc_tip'          => false,
			'description'       => '',
			'title'             => '',
		);

		ob_start();
		
		$field = $this->plugin_id . $this->id . '_' . $key;
		$data  = wp_parse_args($data, $defaults);
		
		require_once dirname(NUVEI_PLUGIN_FILE) . '/templates/admin/download_payments_plans_btn.php';
		
		return ob_get_clean();
	}

	// Generate the HTML For the settings form.
	public function admin_options() {
		echo '<h2>' . esc_html(NUVEI_GATEWAY_TITLE, 'nuvei_woocommerce');
		wc_back_link(__( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ));
		echo '</h2>';
		
		echo '<table class="form-table">';
				$this->generate_settings_html()
			. '</table>';
	}

	/**
	 *  Add fields on the payment page. Because we get APMs with Ajax
	 * here we add only AMPs fields modal.
	 */
	public function payment_fields() {
		if ($this->description) {
			echo wp_kses_post(wpautop(wptexturize($this->description)));
		}
		
		// echo here some html if needed
	}

	/**
	  * Process the payment and return the result. This is the place where site
	  * submit the form and then redirect. Here we will get our custom fields.
	  *
	  * @param int $order_id
	  * @return array
	 */
	public function process_payment( $order_id) {
		Nuvei_Logger::write('Process payment(), Order #' . $order_id);
		
		$sc_nonce = Nuvei_Http::get_param('sc_nonce');
		
		if (!empty($sc_nonce)
			&& !wp_verify_nonce($sc_nonce, 'sc_checkout')
		) {
			Nuvei_Logger::write('process_payment() Error - can not verify WP Nonce.');
			
			return array(
				'result'    => 'success',
				'redirect'  => array(
					'Status'    => 'error',
				),
				wc_get_checkout_url() . 'order-received/' . $order_id . '/'
			);
		}
		
		$order = wc_get_order($order_id);
		$key   = $order->get_order_key();
		
		if (!$order) {
			Nuvei_Logger::write('Order is false for order id ' . $order_id);
			
			return array(
				'result'    => 'success',
				'redirect'  => array(
					'Status'    => 'error',
				),
				wc_get_checkout_url() . 'order-received/' . $order_id . '/'
			);
		}
		
		$return_success_url = add_query_arg(
			array('key' => $key),
			$this->get_return_url($order)
		);
		
		$return_error_url = add_query_arg(
			array(
				'Status'    => 'error',
				'key'        => $key
			),
			$this->get_return_url($order)
		);
		
		if ($order->get_payment_method() != NUVEI_GATEWAY_NAME) {
			Nuvei_Logger::write('Process payment Error - Order payment gateway is not ' . NUVEI_GATEWAY_NAME);
			
			return array(
				'result'    => 'success',
				'redirect'  => array(
					'Status'    => 'error',
					'key'        => $key
				),
				wc_get_checkout_url() . 'order-received/' . $order_id . '/'
			);
		}
		
		// when we have Approved from the SDK we complete the order here
		$sc_transaction_id = Nuvei_Http::get_param('sc_transaction_id', 'int');
		
		# in case of webSDK payment (cc_card)
		if (!empty($sc_transaction_id)) {
			Nuvei_Logger::write('Process webSDK Order, transaction ID #' . $sc_transaction_id);
			
			$order->update_meta_data(NUVEI_TRANS_ID, $sc_transaction_id);
			$order->save();
			
			return array(
				'result'    => 'success',
				'redirect'  => $return_success_url
			);
		}
		# in case of webSDK payment (cc_card) END
		
		Nuvei_Logger::write('Process Rest APM Order.');
		
		$np_obj = new Nuvei_Payment($this->settings);
		$resp   = $np_obj->process(array(
			'order_id'             => $order_id, 
			'return_success_url'   => $return_success_url, 
			'return_error_url'     => $return_error_url
		));
		
		if (!$resp) {
			$msg = __('There is no response for the Order.', 'nuvei_woocommerce');
			
			$order->add_order_note($msg);
			$order->save();
			
			return array(
				'result'    => 'success',
				'redirect'  => $return_error_url
			);
		}

		if (empty($this->get_request_status($resp))) {
			$msg = __('There is no Status for the Order.', 'nuvei_woocommerce');
			
			$order->add_order_note($msg);
			$order->save();
			
			return array(
				'result'    => 'success',
				'redirect'  => $return_error_url
			);
		}
		
		# Redirect
		if (!empty($resp['redirectURL']) || !empty($resp['paymentOption']['redirectUrl'])) {
			return array(
				'result'    => 'success',
				'redirect'    => add_query_arg(
					array(),
					!empty($resp['redirectURL']) ? $resp['redirectURL'] : $resp['paymentOption']['redirectUrl']
				)
			);
		}
		
		if (empty($resp['transactionStatus'])) {
			$msg = __('There is no Transaction Status for the Order.', 'nuvei_woocommerce');
			
			$order->add_order_note($msg);
			$order->save();
			
			return array(
				'result'    => 'success',
				'redirect'  => $return_error_url
			);
		}
		
		if ('DECLINED' === $this->get_request_status($resp)
			|| 'DECLINED' === $resp['transactionStatus']
		) {
			$order->add_order_note(__('Order Declined.', 'nuvei_woocommerce'));
			$order->set_status('cancelled');
			$order->save();
			
			return array(
				'result'    => 'success',
				'redirect'  => $return_error_url
			);
		}
		
		if ('ERROR' === $this->get_request_status($resp)
			|| 'ERROR' === $resp['transactionStatus']
		) {
			$order->set_status('failed');

			$error_txt = __('Payment error', 'nuvei_woocommerce');

			if (!empty($resp['reason'])) {
				$error_txt .= ': ' . $resp['errCode'] . ' - ' . $resp['reason'] . '.';
			} elseif (!empty($resp['threeDReason'])) {
				$error_txt .= ': ' . $resp['threeDReason'] . '.';
			} elseif (!empty($resp['message'])) {
				$error_txt .= ': ' . $resp['message'] . '.';
			}
			
			$order->add_order_note($error_txt);
			$order->save();
			
			return array(
				'result'    => 'success',
				'redirect'  => $return_error_url
			);
		}
		
		// catch Error code or reason
		if ( ( isset($resp['gwErrorCode']) && -1 === $resp['gwErrorCode'] )
			|| isset($resp['gwErrorReason'])
		) {
			$msg = __('Error with the Payment: ', 'nuvei_woocommerce') . $resp['gwErrorReason'] . '.';

			$order->add_order_note($msg);
			$order->save();

			return array(
				'result'    => 'success',
				'redirect'  => $return_error_url
			);
		}
		
		# SUCCESS
		// If we get Transaction ID save it as meta-data
		if (isset($resp['transactionId']) && $resp['transactionId']) {
			$order->update_meta_data(NUVEI_TRANS_ID, $resp['transactionId'], 0);
		}
		
		// save the response transactionType value
		if (isset($resp['transactionType']) && '' !== $resp['transactionType']) {
			$order->update_meta_data(NUVEI_RESP_TRANS_TYPE, $resp['transactionType']);
		}

		if (isset($resp['transactionId']) && '' !== $resp['transactionId']) {
			$order->add_order_note(__('Payment succsess for Transaction Id ', 'nuvei_woocommerce') . $resp['transactionId']);
		} else {
			$order->add_order_note(__('Payment succsess.', 'nuvei_woocommerce'));
		}

		$order->save();
		
		return array(
			'result'    => 'success',
			'redirect'  => $return_success_url
		);
	}
	
	public function add_apms_step() {
		global $woocommerce;
		
		$items      = $woocommerce->cart->get_cart();
		$force_flag = false;
		
		foreach ($items as $item) {
			$cart_product   = wc_get_product( $item['product_id'] );
			$cart_prod_attr = $cart_product->get_attributes();
			
			// check for variations
			if (!empty($cart_prod_attr['pa_' . Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME)])) {
				$force_flag = true;
				break;
			}
		}
		
		ob_start();
		
		$plugin_url          = plugin_dir_url(NUVEI_PLUGIN_FILE);
		$force_user_token_id = $force_flag;
		
		require_once dirname(NUVEI_PLUGIN_FILE) . '/templates/sc_second_step_form.php';
		
		ob_end_flush();
	}
	
	public function get_payment_methods() {
		$resp_data = array(); // use it in the template
		
		# OpenOrder
		$oo_obj  = new Nuvei_Open_Order($this->settings);
		$oo_data = $oo_obj->process();
		
		if (!$oo_data) {
			wp_send_json(array(
				'result'	=> 'failure',
				'refresh'	=> false,
				'reload'	=> false,
				'messages'	=> '<ul id="sc_fake_error" class="woocommerce-error" role="alert"><li>' . __('Unexpected error, please try again later!', 'nuvei_woocommerce') . '</li></ul>'
			));

			exit;
		}

		$resp_data['sessonToken'] = $oo_data['sessionToken'];
		# OpenOrder END
		
		# get APMs
		$gapms_obj = new Nuvei_Get_Apms($this->settings);
		$apms_data = $gapms_obj->process($oo_data);
		
		if (!is_array($apms_data) || empty($apms_data['paymentMethods'])) {
			wp_send_json(array(
				'result'	=> 'failure',
				'refresh'	=> false,
				'reload'	=> false,
				'messages'	=> '<ul id="sc_fake_error" class="woocommerce-error" role="alert"><li>'
					. __('Can not obtain Payment Methods, please try again later!', 'nuvei_woocommerce') . '</li></ul>'
			));

			exit;
		}
		
		$resp_data['apms'] = $apms_data['paymentMethods'];
		# get APMs END
		
		# get UPOs
		$upos = array();
		
		// get them only for registred users when there are APMs
		if (
			1 == $this->get_setting('use_upos')
			&& is_user_logged_in()
			&& !empty($apms_data['paymentMethods'])
		) {
			$gupos_obj = new Nuvei_Get_Upos($this->settings);
			$upo_res   = $gupos_obj->process($oo_data);
			
			if (is_array($upo_res['paymentMethods'])) {
				foreach ($upo_res['paymentMethods'] as $data) {
					// chech if it is not expired
					if (!empty($data['expiryDate']) && gmdate('Ymd') > $data['expiryDate']) {
						continue;
					}

					if (empty($data['upoStatus']) || 'enabled' !== $data['upoStatus']) {
						continue;
					}

					// search for same method in APMs, use this UPO only if it is available there
					foreach ($apms_data['paymentMethods'] as $pm_data) {
						// found it
						if ($pm_data['paymentMethod'] === $data['paymentMethodName']) {
							if (!empty($pm_data['logoURL'])) {
								$data['logoURL'] = $pm_data['logoURL'];
							}
							
							if (!empty($pm_data['paymentMethodDisplayName'][0]['message'])) {
								$data['name'] = $pm_data['paymentMethodDisplayName'][0]['message'];
							}
							
							$upos[] = $data;
							break;
						}
					}
				}
			}
		}
		
		$resp_data['upos'] = $upos;
		# get UPOs END
		
		$resp_data['orderAmount'] = WC()->cart->total;
		$resp_data['userTokenId'] = $oo_data['billingAddress']['email'];
		$resp_data['pluginUrl']   = plugin_dir_url(NUVEI_PLUGIN_FILE);
		$resp_data['siteUrl']     = get_site_url();
			
		wp_send_json(array(
			'result'	=> 'failure', // this is just to stop WC send the form, and show APMs
			'refresh'	=> false,
			'reload'	=> false,
			'messages'	=> '<script>scPrintApms(' . json_encode($resp_data) . ');</script>'
		));

		exit;
	}

	/**
	 * Function process_dmns
	 * 
	 * Process information from the DMNs.
	 * We call this method form index.php
	 */
	public function process_dmns( $params = array()) {
		Nuvei_Logger::write($_REQUEST, 'DMN params');
		
		// stop DMNs only on test mode
		if (Nuvei_Http::get_param('stop_dmn', 'int') == 1 && $this->get_setting('test') == 'yes') {
			$params            = $_REQUEST;
			$params['stop_dmn'] = 0;
			
			Nuvei_Logger::write(
				get_site_url() . '/?' . http_build_query($params),
				'DMN was stopped, please run it manually from the URL bleow:'
			);
			
			echo wp_json_encode('DMN was stopped, please run it manually!');
			exit;
		}
		
		// santitized get variables
		$clientUniqueId       = $this->get_cuid();
		$transactionType      = Nuvei_Http::get_param('transactionType');
		$order_id             = Nuvei_Http::get_param('order_id', 'int');
		$TransactionID        = Nuvei_Http::get_param('TransactionID', 'int');
		$relatedTransactionId = Nuvei_Http::get_param('relatedTransactionId', 'int');
		$dmnType              = Nuvei_Http::get_param('dmnType');
		$client_request_id    = Nuvei_Http::get_param('clientRequestId');
		
		$req_status = $this->get_request_status();
		
		if (empty($req_status) && empty($dmnType)) {
			Nuvei_Logger::write('DMN Error - the Status is empty!');
			echo wp_json_encode('DMN Error - the Status is empty!');
			exit;
		}
		
		# Subscription State DMN
		if ('subscription' == $dmnType) {
			$subscriptionState = Nuvei_Http::get_param('subscriptionState');
			$cri_parts         = explode('_', $client_request_id);
			
			if (empty($cri_parts) || empty($cri_parts[0]) || !is_numeric($cri_parts[0])) {
				Nuvei_Logger::write($cri_parts, 'DMN Subscription Error with Client Request Id parts:');
				echo wp_json_encode('DMN Subscription Error with Client Request Id parts.');
				exit;
			}
			
			$this->is_order_valid((int) $cri_parts[0]);
			
			if (!empty($subscriptionState)) {
				if ('active' == strtolower($subscriptionState)) {
					$msg = __('<b>Subscription is Active</b>.', 'nuvei_woocommerce') . '<br/>'
						. __('<b>Subscription ID:</b> ', 'nuvei_woocommerce') . Nuvei_Http::get_param('subscriptionId', 'int') . '<br/>'
						. __('<b>Plan ID:</b> ', 'nuvei_woocommerce') . Nuvei_Http::get_param('planId', 'int');
					
					$this->sc_order->update_meta_data(NUVEI_ORDER_HAS_SUBSCR, 1);
					$this->sc_order->add_order_note($msg);
					$this->sc_order->save();
				} elseif ('inactive' == strtolower($subscriptionState)) {
					$msg            = __('<b>Subscription is Inactive</b>.', 'nuvei_woocommerce');
					$subscriptionId = Nuvei_Http::get_param('subscriptionId', 'int');
					$planId         = Nuvei_Http::get_param('planId', 'int');
					
					if (0 < $subscriptionId) {
						$msg .= '<br/>' . __('<b>Subscription ID:</b> ', 'nuvei_woocommerce') . $subscriptionId;
					}

					if (0 < $planId) {
						$msg .= '<br/>' . __('<b>Plan ID:</b> ', 'nuvei_woocommerce') . $planId;
					}

					//                  $this->sc_order->update_meta_data(NUVEI_ORDER_HAS_SUBSCR, 0);
					$this->sc_order->add_order_note($msg);
					$this->sc_order->save();
				}
			}

			echo wp_json_encode('DMN received.');
			exit;
		}
		# Subscription State DMN END
		
		if (empty($TransactionID)) {
			Nuvei_Logger::write('DMN error - The TransactionID is empty!');
			echo wp_json_encode('DMN error - The TransactionID is empty!');
			exit;
		}
		
		if (!$this->check_advanced_checksum()) {
			Nuvei_Logger::write('DMN Error - AdvancedCheckSum problem!');
			echo wp_json_encode('DMN Error - Checksum validation problem!');
			exit;
		}
		
		# Subscription Payment DMN
		if ('subscriptionPayment' == $dmnType && 0 != $TransactionID) {
			$cri_parts = explode('_', $client_request_id);
			
			if (empty($cri_parts) || empty($cri_parts[0]) || !is_numeric($cri_parts[0])) {
				Nuvei_Logger::write($cri_parts, 'DMN Subscription Payment Error with Client Request Id parts:');
				echo wp_json_encode('DMN Subscription Payment Error with Client Request Id parts.');
				exit;
			}
			
			$this->is_order_valid((int) $cri_parts[0]);
			
			$msg = sprintf(
				/* translators: %s: the status of the Payment */
				__('<b>Subscription Payment</b> with Status %s was made.', 'nuvei_woocommerce'),
				$req_status
			)
				. '<br/>' . __('<b>Plan ID:</b> ', 'nuvei_woocommerce') . Nuvei_Http::get_param('planId', 'int') . '.'
				. '<br/>' . __('<b>Subscription ID:</b> ', 'nuvei_woocommerce') . Nuvei_Http::get_param('subscriptionId', 'int') . '.'
				. '<br/>' . __('<b>Amount:</b> ', 'nuvei_woocommerce') . $this->sc_order->get_currency() . ' '
				. Nuvei_Http::get_param('totalAmount', 'float') . '.'
				. '<br/>' . __('<b>TransactionId:</b> ', 'nuvei_woocommerce') . $TransactionID;

			Nuvei_Logger::write($msg, 'Subscription DMN Payment');
			
			$this->sc_order->add_order_note($msg);
			
			echo wp_json_encode('DMN received.');
			exit;
		}
		# Subscription Payment DMN END
		
		# Sale and Auth
		if (in_array($transactionType, array('Sale', 'Auth'), true)) {
			// WebSDK
			if (
				!is_numeric($clientUniqueId)
				&& Nuvei_Http::get_param('TransactionID', 'int') != 0
			) {
				$order_id = $this->get_order_by_trans_id($TransactionID, $transactionType);
				
			} elseif (empty($order_id) && is_numeric($clientUniqueId)) { // REST
				Nuvei_Logger::write($order_id, '$order_id');

				$order_id = $clientUniqueId;
			}
			
			$this->is_order_valid($order_id);
			$this->save_update_order_numbers();
			
			$order_status = strtolower($this->sc_order->get_status());
			
			if ('completed' !== $order_status) {
				$this->change_order_status(
					$order_id,
					$req_status,
					$transactionType
				);
			}
			
			$this->subscription_start($transactionType, $order_id);
			
			echo esc_html('DMN process end for Order #' . $order_id);
			exit;
		}
		
		// try to get the Order ID
		$ord_data = $this->get_order_data($relatedTransactionId);

		if (!empty($ord_data[0]->post_id)) {
			$order_id = $ord_data[0]->post_id;
		}
			
		# Void, Settle
		if (
			'' != $clientUniqueId
			&& ( in_array($transactionType, array('Void', 'Settle'), true) )
		) {
			$this->is_order_valid($order_id);
			
			if ('Settle' == $transactionType) {
				$this->save_update_order_numbers();
			}

			$this->change_order_status($order_id, $req_status, $transactionType);
			$this->subscription_start($transactionType, $order_id);
				
			echo wp_json_encode('DMN received.');
			exit;
		}
		
		# Refund
		if (in_array($transactionType, array('Credit', 'Refund'), true)) {
			if (0 == $order_id) {
				$order_id = $this->get_order_by_trans_id($relatedTransactionId, $transactionType);
			}
			
			$this->create_refund_record($order_id);
			
			$this->change_order_status(
				$order_id,
				$req_status,
				$transactionType,
				array(
					'resp_id'       => $clientUniqueId,
					'totalAmount'   => Nuvei_Http::get_param('totalAmount', 'float')
				)
			);

			echo wp_json_encode(array('DMN process end for Order #' . $order_id));
			exit;
		}
		
		Nuvei_Logger::write(
			array(
				'TransactionID' => $TransactionID,
				'relatedTransactionId' => $relatedTransactionId,
			),
			'DMN was not recognized.'
		);
		
		echo wp_json_encode('DMN was not recognized.');
		exit;
	}
	
	/** TODO - do we use this function */
	//  public function showMessage( $content) {
	//      return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
	//  }

	/**
	 * Function process_refund
	 * A overwrite original function to enable auto refund in WC.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Refund amount.
	 * @param  string $reason Refund reason.
	 *
	 * @return boolean
	 */
	public function process_refund( $order_id, $amount = null, $reason = '') {
		if ('true' == Nuvei_Http::get_param('api_refund')) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Function create_refund
	 * 
	 * Create Refund in SC by Refund from WC, after the merchant
	 * click refund button or set Status to Refunded
	 */
	public function create_refund_request( $order_id, $ref_amount) {
		if ($order_id < 1) {
			Nuvei_Logger::write($order_id, 'create_refund_request() Error - Post parameter is less than 1.');
			
			wp_send_json(array(
				'status' => 0,
				'msg' => __('Post parameter is less than 1.', 'nuvei_woocommerce'),
				'data' => array($order_id)
			));
			exit;
		}
		
		$ref_amount = round($ref_amount, 2);
		
		if ($ref_amount < 0) {
			wp_send_json(array(
				'status' => 0,
				'msg' => __('Invalid Refund amount.', 'nuvei_woocommerce')));
			exit;
		}
		
		$this->is_order_valid($order_id);
		
		$tr_id = $this->sc_order->get_meta(NUVEI_TRANS_ID);
		
		if (empty($tr_id)) {
			wp_send_json(array(
				'status' => 0,
				'msg' => __('The Order missing Transaction ID.', 'nuvei_woocommerce')));
			exit;
		}
		
		$nr_obj = new Nuvei_Refund($this->settings);
		$resp   = $nr_obj->process(array(
			'order_id'     => $order_id, 
			'ref_amount'   => $ref_amount, 
			'tr_id'        => $tr_id
		));
		$msg    = '';

		if (false === $resp) {
			$msg = __('The REST API retun false.', 'nuvei_woocommerce');

			$this->sc_order->add_order_note($msg);
			$this->sc_order->save();
			
			wp_send_json(array(
				'status' => 0,
				'msg' => $msg
			));
			exit;
		}

		$json_arr = $resp;
		if (!is_array($resp)) {
			parse_str($resp, $json_arr);
		}

		if (!is_array($json_arr)) {
			$msg = __('Invalid API response.', 'nuvei_woocommerce');

			$this->sc_order->add_order_note($msg);
			$this->sc_order->save();
			
			wp_send_json(array(
				'status' => 0,
				'msg' => $msg
			));
			exit;
		}

		// APPROVED
		if (!empty($json_arr['transactionStatus']) && 'APPROVED' == $json_arr['transactionStatus']) {
			$this->sc_order->update_status('processing');
			
			$this->save_refund_meta_data($json_arr['transactionId'], $ref_amount);
			
			wp_send_json(array('status' => 1));
			exit;
		}
		
		// in case we have message but without status
		if (!isset($json_arr['status']) && isset($json_arr['msg'])) {
			$msg = __('Refund request problem: ', 'nuvei_woocommerce') . $json_arr['msg'];

			$this->sc_order->add_order_note($msg);
			$this->sc_order->save();
			
			Nuvei_Logger::write($msg);

			wp_send_json(array(
				'status' => 0,
				'msg' => $msg
			));
			exit;
		}
		
		// the status of the request is ERROR
		if (isset($json_arr['status']) && 'ERROR' === $json_arr['status']) {
			$msg = __('Request ERROR: ', 'nuvei_woocommerce') . $json_arr['reason'];

			$this->sc_order->add_order_note($msg);
			$this->sc_order->save();
			
			Nuvei_Logger::write($msg);
			
			wp_send_json(array(
				'status' => 0,
				'msg' => $msg
			));
			exit;
		}

		// the status of the request is SUCCESS, check the transaction status
		if (isset($json_arr['transactionStatus']) && 'ERROR' === $json_arr['transactionStatus']) {
			if (isset($json_arr['gwErrorReason']) && !empty($json_arr['gwErrorReason'])) {
				$msg = $json_arr['gwErrorReason'];
			} elseif (isset($json_arr['paymentMethodErrorReason']) && !empty($json_arr['paymentMethodErrorReason'])) {
				$msg = $json_arr['paymentMethodErrorReason'];
			} else {
				$msg = __('Transaction error.', 'nuvei_woocommerce');
			}

			$this->sc_order->add_order_note($msg);
			$this->sc_order->save();
			
			Nuvei_Logger::write($msg);
			
			wp_send_json(array(
				'status' => 0,
				'msg' => $msg
			));
			exit;
		}

		if (isset($json_arr['transactionStatus']) && 'DECLINED' === $json_arr['transactionStatus']) {
			$msg = __('The refund was declined.', 'nuvei_woocommerce');

			$this->sc_order->add_order_note($msg);
			$this->sc_order->save();
			
			Nuvei_Logger::write($msg);
			
			wp_send_json(array(
				'status' => 0,
				'msg' => $msg
			));
			exit;
		}

		$msg = __('The status of Refund request is UNKONOWN.', 'nuvei_woocommerce');

		$this->sc_order->add_order_note($msg);
		$this->sc_order->save();
		
		Nuvei_Logger::write($msg);
		
		wp_send_json(array(
			'status' => 0,
			'msg' => $msg
		));
		exit;
	}
	
	public function return_settle_btn() {
		// revert buttons on Recalculate
		if (!Nuvei_Http::get_param('refund_amount', 'float', false) && !empty(Nuvei_Http::get_param('items'))) {
			echo esc_js('<script type="text/javascript">returnSCBtns();</script>');
		}
	}

	/**
	 * Restock on refund.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public function restock_on_refunded_status( $order_id) {
		$order            = wc_get_order($order_id);
		$items            = $order->get_items();
		$is_order_restock = $order->get_meta('_scIsRestock');
		
		// do restock only once
		if (1 !== $is_order_restock) {
			wc_restock_refunded_items($order, $items);
			$order->update_meta_data('_scIsRestock', 1);
			$order->save();
			
			Nuvei_Logger::write('Items were restocked.');
		}
		
		return;
	}
	
	/**
	 * Create Settle and Void
	 * 
	 * @param int $order_id
	 * @param string $action
	 */
	public function create_settle_void( $order_id, $action) {
		$order   = wc_get_order($order_id);
		$method  = 'settle' == $action ? 'settleTransaction' : 'voidTransaction';
		$nsv_obj = new Nuvei_Settle_Void($this->settings);
		$resp    = $nsv_obj->process(array(
			'order_id' => $order_id, 
			'action'   => $action, 
			'method'   => $method
		));
		
		if (!empty($resp['status']) && 'SUCCESS' == $resp['status']) {
			$ord_status = 1;
			$order->update_status('processing');
		} else {
			$ord_status = 0;
		}
		
		wp_send_json(array('status' => $ord_status, 'data' => $resp));
		exit;
	}
	
	public function delete_user_upo() {
		$upo_id = Nuvei_Http::get_param('scUpoId', 'int', false);
		
		if (!$upo_id) {
			wp_send_json(
				array(
				'status' => 'error',
				'msg' => __('Invalid UPO ID parameter.', 'nuvei_woocommerce')
				)
			);

			exit;
		}
		
		if (!is_user_logged_in()) {
			wp_send_json(
				array(
				'status' => 'error',
				'msg' => 'The user in not logged in.'
				)
			);

			exit;
		}
		
		$curr_user = wp_get_current_user();
		
		if (empty($curr_user->user_email)) {
			wp_send_json(array(
				'status' => 'error',
				'msg' => 'The user email is not valid.'
			));

			exit;
		}
		
		$ndu_obj = new Nuvei_Delete_Upo($this->settings);
		$resp    = $ndu_obj->process(array(
			'email'     => $curr_user->user_email,
			'upo_id'    => $upo_id
		));
		
		if (empty($resp['status']) || 'SUCCESS' != $resp['status']) {
			$msg = !empty($resp['reason']) ? $resp['reason'] : '';
			
			wp_send_json(array(
				'status' => 'error',
				'msg' => $msg
			));

			exit;
		}
		
		wp_send_json(array('status' => 'success'));
		exit;
	}
	
	/**
	 * We need this stupid function because as response request variable
	 * we get 'Status' or 'status'...
	 *
	 * @param array $params
	 * @return string
	 */
	public function get_request_status( $params = array()) {
		$Status = Nuvei_Http::get_param('Status');
		$status = Nuvei_Http::get_param('status');
		
		if (empty($params)) {
			if ('' != $Status) {
				return $Status;
			}
			
			if ('' != $status) {
				return $status;
			}
		} else {
			if (isset($params['Status'])) {
				return $params['Status'];
			}

			if (isset($params['status'])) {
				return $params['status'];
			}
		}
		
		return '';
	}
	
	public function reorder() {
		global $woocommerce;
		
		$products_ids = json_decode(Nuvei_Http::get_param('product_ids'), true);
		
		if (empty($products_ids) || !is_array($products_ids)) {
			wp_send_json(array(
				'status' => 0,
				'msg' => __('Problem with the Products IDs.', 'nuvei_woocommerce')
			));
			exit;
		}
		
		$prod_factory  = new WC_Product_Factory();
		$msg           = '';
		$is_prod_added = false;
		
		foreach ($products_ids as $id) {
			$product = $prod_factory->get_product($id);
		
			if ('in-stock' != $product->get_availability()['class'] ) {
				$msg = __('Some of the Products are not availavle, and are not added in the new Order.', 'nuvei_woocommerce');
				continue;
			}

			$is_prod_added = true;
			$woocommerce->cart->add_to_cart($id);
		}
		
		if (!$is_prod_added) {
			wp_send_json(array(
				'status' => 0,
				'msg' => 'There are no added Products to the Cart.',
			));
			exit;
		}
		
		$cart_url = wc_get_cart_url();
		
		if (!empty($msg)) {
			$cart_url .= strpos($cart_url, '?') !== false ? '&sc_msg=' : '?sc_msg=';
			$cart_url .= urlencode($msg);
		}
		
		wp_send_json(array(
			'status'		=> 1,
			'msg'			=> $msg,
			'redirect_url'	=> wc_get_cart_url(),
		));
		exit;
	}
	
	/**
	 * Download the Active Payment pPlans and save them to a json file.
	 * If there are no Active Plans, create default one with name, based
	 * on MerchatSiteId parameter, and get it.
	 * 
	 * @param int $recursions
	 */
	public function download_subscr_pans( $recursions = 0) {
		if ($recursions > 1) {
			wp_send_json(array('status' => 0));
			exit;
		}
		
		$ndp_obj = new Nuvei_Download_Plans($this->settings);
		$resp    = $ndp_obj->process();
		
		if (empty($resp) || !is_array($resp) || 'SUCCESS' != $resp['status']) {
			Nuvei_Logger::write('Get Plans response error.');
			
			wp_send_json(array('status' => 0));
			exit;
		}
		
		// in case there are  no active plans - create default one
		if (isset($resp['total']) && 0 == $resp['total']) {
			$ncp_obj     = new Nuvei_Create_Plan($this->settings);
			$create_resp = $ncp_obj->process();
			
			if (!empty($create_resp['planId'])) {
				$recursions++;
				$this->download_subscr_pans($recursions);
				return;
			}
		}
		// in case there are  no active plans - create default one END
		
		if (file_put_contents(plugin_dir_path(NUVEI_PLUGIN_FILE) . '/tmp/sc_plans.json', json_encode($resp['plans']))) {
			$this->create_nuvei_global_attribute();
			
			wp_send_json(array(
				'status' => 1,
				'time' => gmdate('Y-m-d H:i:s')
			));
			exit;
		}
		
		Nuvei_Logger::write(
			plugin_dir_path(NUVEI_PLUGIN_FILE) . '/tmp/sc_plans.json',
			'Plans list was not saved.'
		);
		
		wp_send_json(array('status' => 0));
		exit;
	}
	
	public function get_subscr_fields() {
		return $this->subscr_fields;
	}
	
	public function get_subscr_units() {
		return $this->subscr_units;
	}
	
	/**
	 * Checks if the Order belongs to WC_Order and if the order was made
	 * with Nuvei payment module.
	 * 
	 * @param int|string $order_id
	 * @param bool $return - return the order
	 * 
	 * @return bool|WC_Order
	 */
	public function is_order_valid( $order_id, $return = false) {
		Nuvei_Logger::write('is_order_vali() check.');
		
		$this->sc_order = wc_get_order( $order_id );
		
		if ( ! is_a( $this->sc_order, 'WC_Order') ) {
			Nuvei_Logger::write('is_order_valid() Error - Provided Order ID is not a WC Order');
			
			if ($return) {
				return false;
			}
			
			echo wp_json_encode('is_order_valid() Error - Provided Order ID is not a WC Order');
			exit;
		}
		
		// check for 'sc' also because of the older Orders
		if (!in_array($this->sc_order->get_payment_method(), array(NUVEI_GATEWAY_NAME, 'sc'))) {
			Nuvei_Logger::write(
				$this->sc_order->get_payment_method(), 
				'DMN Error - the order does not belongs to Nuvei.'
			);
			
			if ($return) {
				return false;
			}
			
			echo wp_json_encode('DMN Error - the order does not belongs to Nuvei.');
			exit;
		}
		
		// can we override Order status (state)
		$ord_status = strtolower($this->sc_order->get_status());
		
		if ( in_array($ord_status, array('cancelled', 'refunded')) ) {
			Nuvei_Logger::write($this->sc_order->get_payment_method(), 'DMN Error - can not override status of Voided/Refunded Order.');
			
			if ($return) {
				return false;
			}
			
			echo wp_json_encode('DMN Error - can not override status of Voided/Refunded Order.');
			exit;
		}
		
		if (
			'completed' == $ord_status
			&& 'auth' == strtolower(Nuvei_Http::get_param('transactionType'))
		) {
			Nuvei_Logger::write($this->sc_order->get_payment_method(), 'DMN Error - can not override status Completed with Auth.');
			
			if ($return) {
				return false;
			}
			
			echo wp_json_encode('DMN Error - can not override status Completed with Auth.');
			exit;
		}
		// can we override Order status (state) END
		
		return $this->sc_order;
	}
	
	/**
	 * Call Nuvei_Open_Order after Ajax request.
	 * 
	 * @param boolean $is_ajax
	 */
	public function open_order( $is_ajax = false ) {
		// new
		$oo_obj = new Nuvei_Open_Order($this->settings, $is_ajax);
		$oo_obj->process();
		exit;
	}
	
	public function can_use_upos() {
		return $this->settings['use_upos'];
	}
	
	public function create_nuvei_global_attribute() {
		Nuvei_Logger::write('create_nuvei_global_attribute()');
		
		$nuvei_plans_path          = plugin_dir_path(NUVEI_PLUGIN_FILE) . '/tmp/sc_plans.json';
		$nuvei_glob_attr_name_slug = Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME);
		$taxonomy_name             = wc_attribute_taxonomy_name($nuvei_glob_attr_name_slug);
		
		// a check
		if (!is_readable($nuvei_plans_path)) {
			Nuvei_Logger::write('Plans json is not readable.');
			
			wp_send_json(array(
				'status'    => 0,
				'msg'       => __('Plans json is not readable.')
			));
			exit;
		}
		
		$plans = json_decode(file_get_contents($nuvei_plans_path), true);

		// a check
		if (empty($plans) || !is_array($plans)) {
			Nuvei_Logger::write($plans, 'Unexpected problem with the Plans list.');

			wp_send_json(array(
				'status'    => 0,
				'msg'       => __('Unexpected problem with the Plans list.')
			));
			exit;
		}
		
		// check if Taxonomy exists
		if (taxonomy_exists($taxonomy_name)) {
			Nuvei_Logger::write('$taxonomy_name exists');
			return;
		}
		
		// create the Global Attribute
		$args = array(
			'name'         => NUVEI_GLOB_ATTR_NAME,
			'slug'         => $nuvei_glob_attr_name_slug,
			'order_by'     => 'menu_order',
			'has_archives' => true,
		);

		// create the attribute and check for errors
		$attribute_id = wc_create_attribute($args);

		if (is_wp_error($attribute_id)) {
			Nuvei_Logger::write(
				array(
					'$data'     => $data,
					'$args'     => $args,
					'message'   => $attribute_id->get_error_message(), 
				),
				'Error when try to add Global Attribute with arguments'
			);

			wp_send_json(array(
				'status'    => 0,
				'msg'       => $attribute_id->get_error_message()
			));
			exit;
		}

		// craete WP taxonomy based on the WC attribute
		register_taxonomy(
			$taxonomy_name, 
			array('product'), 
			array(
				'public' => false,
			)
		);
	}
	
	/**
	 * Decide to add or not a product to the card.
	 * 
	 * @param bool $true
	 * @param int $product_id
	 * @param int $quantity
	 * 
	 * @return bool
	 */
	public function add_to_cart_validation( $true, $product_id, $quantity) {
		global $woocommerce;
		
		$cart       = $woocommerce->cart;
		$product    = wc_get_product( $product_id );
		$attributes = $product->get_attributes();
		$cart_items = $cart->get_cart();
		
		// 1 - incoming Product with plan
		if (!empty($attributes['pa_' . Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME)])) {
			// 1.1 if there are Products in the cart, stop the process
			if (count($cart_items) > 0) {
				wc_print_notice(__('You can not add a Product with Payment Plan to another Product.', 'nuvei_woocommerce'), 'error');
				return false;
			}
			
			return true;
		}
		
		// 2 - incoming Product without plan
		// 2.1 - the cart is not empty
		if (count($cart_items) > 0) {
			foreach ($cart_items as $item_id => $item) {
				$cart_product   = wc_get_product( $item['product_id'] );
				$cart_prod_attr = $cart_product->get_attributes();

				// 2.1.1 in case there is Product with plan in the Cart
				if (!empty($cart_prod_attr['pa_' . Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME)])) {
					wc_print_notice(__('You can not add Product to a Product with Payment Plan.', 'nuvei_woocommerce'), 'error');
					return false;
				}
			}
		}
		
		return true;
	}
	
	/**
	 * The start of create subscriptions logic.
	 * We call this method when we've got Settle or Sale DMNs.
	 * 
	 * @param string    $transactionType
	 * @param int       $order_id
	 */
	private function subscription_start( $transactionType, $order_id) {
		if (!in_array($transactionType, array('Settle', 'Sale'))
			|| empty($_REQUEST['customField1'])
		) {
			return;
		}
		
		$subscr_data = json_decode(Nuvei_Http::get_param('customField1', 'json'), true);
		
		if (empty($subscr_data) || !is_array($subscr_data)) {
			Nuvei_Logger::write($subscr_data, 'DMN Payment Plan data is empty or wrong format. We will not start a Payment plan.');
			return;
		}
		
		$prod_plan = current($subscr_data);
		
		if (empty($prod_plan) || !is_array($prod_plan)) {
			Nuvei_Logger::write($prod_plan, 'There is a problem with the DMN Product Payment Plan data:');
			return;
		}
		
		$prod_plan['clientRequestId'] = $order_id . '_' . uniqid();
		
		$ns_obj = new Nuvei_Subscription($this->settings);
		$resp   = $ns_obj->process($prod_plan);
		
		// On Error
		if (!$resp || !is_array($resp) || 'SUCCESS' != $resp['status']) {
			$msg = __('<b>Error</b> when try to start a Subscription by the Order.', 'nuvei_woocommerce');
			
			if (!empty($resp['reason'])) {
				$msg .= '<br/>' . __('<b>Reason:</b> ', 'nuvei_woocommerce') . $resp['reason'];
			}
		} else { // On Success
			$msg = __('<b>Subscription</b> was created. ') . '<br/>'
				. __('<b>Subscription ID:</b> ', 'nuvei_woocommerce') . $resp['subscriptionId'] . '.<br/>' 
				. __('<b>Recurring amount:</b> ', 'nuvei_woocommerce') . $this->sc_order->get_currency() . ' '
				. $prod_plan['recurringAmount'];
		}
		
		$this->sc_order->add_order_note($msg);
		$this->sc_order->save();
			
		return;
	}
	
	/**
	 * Validate advanceResponseChecksum and/or responsechecksum parameters
	 *
	 * @return boolean
	 */
	private function check_advanced_checksum() {
		$advanceResponseChecksum = Nuvei_Http::get_param('advanceResponseChecksum');
		$responsechecksum        = Nuvei_Http::get_param('responsechecksum');
		
		if (empty($advanceResponseChecksum) && empty($responsechecksum)) {
			return false;
		}
		
		// advanceResponseChecksum case
		if (!empty($advanceResponseChecksum)) {
			$concat = $this->get_setting('secret') 
				. Nuvei_Http::get_param('totalAmount')
				. Nuvei_Http::get_param('currency') 
				. Nuvei_Http::get_param('responseTimeStamp')
				. Nuvei_Http::get_param('PPP_TransactionID') 
				. $this->get_request_status()
				. Nuvei_Http::get_param('productId');
			
			$str = hash($this->get_setting('hash_type'), $concat);

			if (strval($str) == $advanceResponseChecksum) {
				return true;
			}

			return false;
		}
		
		# subscription DMN with responsechecksum case
		$concat = '';
		
		// complicated way to filter all $_REQUEST input, but WP will be happy
		$request_arr = $_REQUEST;
		array_walk_recursive($request_arr, function ( &$value) {
			$value = trim($value);
			$value = filter_var($value);
		});
		// complicated way to filter all $_REQUEST input, but WP will be happy END
		
		foreach ($request_arr as $name => $value) {
			if ('responsechecksum' == $name) {
				continue;
			}
			
			$concat .= $value;
		}
		
		$concat_final = $concat . $this->get_setting('secret');
		$checksum     = hash($this->get_setting('hash_type'), utf8_encode($concat_final));
		
		if ($responsechecksum !== $checksum) {
			return false;
		}
		
		return true;
	}
	
	private function get_order_data( $TransactionID) {
		global $wpdb;
		
		$res = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = %s AND meta_value = %s ;",
				NUVEI_TRANS_ID,
				$TransactionID
			)
		);
				
		return $res;
	}
	
	/**
	 * Change the status of the order.
	 *
	 * @param int $order_id
	 * @param string $req_status
	 * @param string $transactionType
	 * @param array $res_args - we must use $res_args instead $_REQUEST, if not empty
	 */
	private function change_order_status( $order_id, $req_status, $transactionType, $res_args = array()) {
		Nuvei_Logger::write(
			'Order ' . $order_id . ' was ' . $req_status,
			'Nuvei change_order_status()'
		);
		
		$gw_data = '<br/>' 
			. __('<b>Status:</b> ', 'nuvei_woocommerce') . $req_status . '<br/>' 
			. __('<b>PPP Transaction ID:</b> ', 'nuvei_woocommerce') . Nuvei_Http::get_param('PPP_TransactionID', 'int') . '<br/>' 
			. __('<b>Transaction Type:</b> ', 'nuvei_woocommerce') . $transactionType . '<br/>' 
			. __('<b>Transaction ID:</b> ', 'nuvei_woocommerce') . Nuvei_Http::get_param('TransactionID', 'int') . ',<br/>' 
			. __('<b>Payment Method:</b> ', 'nuvei_woocommerce') . Nuvei_Http::get_param('payment_method');
		
		$message = '';
		$status  = $this->sc_order->get_status();
		
		switch ($req_status) {
			case 'CANCELED':
				$message            = __('Your action was <b>Canceld</b>.', 'nuvei_woocommerce') . $gw_data;
				$this->msg['class'] = 'woocommerce_message';
				
				if (in_array($transactionType, array('Auth', 'Settle', 'Sale'))) {
					$status = 'failed';
				}
				break;

			case 'APPROVED':
				if ('Void' === $transactionType) {
					$message = __('DMN Void message', 'nuvei_woocommerce')
						. $gw_data . '<br/>' . __('Plsese check your stock!', 'nuvei_woocommerce');
					
					$status = 'cancelled';
				} elseif (in_array($transactionType, array('Credit', 'Refund'), true)) {
					$message = __('DMN Refund message', 'nuvei_woocommerce') . $gw_data;
					$status  = 'completed';
					
					// get current refund amount
					$refunds         = json_decode($this->sc_order->get_meta(NUVEI_REFUNDS), true);
					$currency_code   = $this->sc_order->get_currency();
					$currency_symbol = get_woocommerce_currency_symbol( $currency_code );
					
					if (isset($refunds[Nuvei_Http::get_param('TransactionID', 'int')]['refund_amount'])) {
						$message .= '<br/>' . __('<b>Refund Amount:</b>') . ': ' . $currency_symbol
							. number_format($refunds[Nuvei_Http::get_param('TransactionID', 'int')]['refund_amount'], 2, '.', '')
							. '<br/>' . __('<b>Refund:</b>') . ' #' 
							. $refunds[Nuvei_Http::get_param('TransactionID', 'int')]['wc_id'];
					}
					
					if (round($this->sc_order->get_total(), 2) <= $this->sum_order_refunds()) {
						$status = 'refunded';
					}
				} elseif ('Auth' === $transactionType) {
					$message = __('The amount has been authorized and wait for Settle.', 'nuvei_woocommerce') . $gw_data;
					$status  = 'pending';
				} elseif (in_array($transactionType, array('Settle', 'Sale'), true)) {
					$message = __('The amount has been authorized and captured by ', 'nuvei_woocommerce') . NUVEI_GATEWAY_TITLE . '.' . $gw_data;
					$status  = 'completed';
					
					$this->sc_order->payment_complete($order_id);
				}
				
				// check for correct amount
				if (in_array($transactionType, array('Auth', 'Sale'), true)) {
					$order_amount = round(floatval($this->sc_order->get_total()), 2);
					$dmn_amount   = round(Nuvei_Http::get_param('totalAmount', 'float'), 2);
					
					if ($order_amount !== $dmn_amount) {
						$message .= '<hr/><b>' . __('Payment ERROR!', 'nuvei_woocommerce') . '</b> ' 
							. $dmn_amount . ' ' . Nuvei_Http::get_param('currency')
							. ' ' . __('paid instead of', 'nuvei_woocommerce') . ' ' . $order_amount
							. ' ' . $this->sc_order->get_currency() . '!';
						
						$status = 'failed';
						
						Nuvei_Logger::write(
							array(
								'order_amount'	=> $order_amount,
								'dmn_amount'	=> $dmn_amount,
							),
							'DMN amount and Order amount do not much.'
						);
					}
				}
				
				$this->msg['class'] = 'woocommerce_message';
				break;

			case 'ERROR':
			case 'DECLINED':
			case 'FAIL':
				$reason = ',<br/>' . __('<b>Reason:</b> ', 'nuvei_woocommerce');
				if ('' != Nuvei_Http::get_param('reason')) {
					$reason .= Nuvei_Http::get_param('reason');
				} elseif ('' != Nuvei_Http::get_param('Reason')) {
					$reason .= Nuvei_Http::get_param('Reason');
				}
				
				$message = __('<b>Transaction failed.</b>', 'nuvei_woocommerce') . '<br/>' 
					. __('<b>Error code:</b> ', 'nuvei_woocommerce') . Nuvei_Http::get_param('ErrCode') . '<br/>' 
					. __('<b>Message:</b> ', 'nuvei_woocommerce') . Nuvei_Http::get_param('message') . $reason . $gw_data;
				
				// do not change status
				if ('Void' === $transactionType) {
					$message = 'Your Void request <b>fail</b>.';
				}
				if (in_array($transactionType, array('Auth', 'Settle', 'Sale'))) {
					$status = 'failed';
				}
				
				$this->msg['class'] = 'woocommerce_message';
				break;

			case 'PENDING':
				if ('processing' === $status || 'completed' === $status) {
					break;
				}

				$message            = __('Payment is still pending.', 'nuvei_woocommerce') . $gw_data;
				$this->msg['class'] = 'woocommerce_message woocommerce_message_info';
				$status             = 'on-hold';
				break;
		}
		
		if (!empty($message)) {
			$this->msg['message'] = $message;
			$this->sc_order->add_order_note($this->msg['message']);
		}

		$this->sc_order->update_status($status);
		$this->sc_order->save();
		
		Nuvei_Logger::write($status, 'Status of Order #' . $order_id . ' was set to');
	}
	
	/**
	 * Function save_update_order_numbers
	 * Save or update order AuthCode and TransactionID on status change.
	 */
	private function save_update_order_numbers() {
		// save or update AuthCode and GW Transaction ID
		$auth_code = Nuvei_Http::get_param('AuthCode', 'int');
		if (!empty($auth_code)) {
			$this->sc_order->update_meta_data(NUVEI_AUTH_CODE_KEY, $auth_code);
		}

		$transaction_id = Nuvei_Http::get_param('TransactionID', 'int');
		if (!empty($transaction_id)) {
			$this->sc_order->update_meta_data(NUVEI_TRANS_ID, $transaction_id);
		}
		
		$pm = Nuvei_Http::get_param('payment_method');
		if (!empty($pm)) {
			$this->sc_order->update_meta_data(NUVEI_PAYMENT_METHOD, $pm);
		}

		$tr_type = Nuvei_Http::get_param('transactionType');
		if (!empty($tr_type)) {
			$this->sc_order->update_meta_data(NUVEI_RESP_TRANS_TYPE, $tr_type);
		}
		
		$this->sc_order->save();
	}
	
	/**
	 * Get a plugin setting by its key.
	 * 
	 * @param string    $key - the key we are search for
	 * @param mixed     $default - the default value if no setting found
	 */
	private function get_setting( $key, $default = 0) {
		if (!empty($this->settings[$key])) {
			return $this->settings[$key];
		}
		
		return $default;
	}
	
	/**
	 * Function get_order_by_trans_id
	 * 
	 * @param int $trans_id
	 * @param string $transactionType
	 * 
	 * @return int
	 */
	private function get_order_by_trans_id( $trans_id, $transactionType = '') {
		// try to get Order ID by its meta key
		$tries				= 0;
		$max_tries			= 10;
		$order_request_time	= Nuvei_Http::get_param('customField3', 'int'); // time of create/update order
		
		// do not search more than once if the DMN response time is more than 1 houre before now
		if (
			$order_request_time > 0
			&& in_array($transactionType, array('Auth', 'Sale', 'Credit', 'Refund'), true)
			&& ( time() - $order_request_time > 3600 )
		) {
			$max_tries = 0;
		}

		do {
			$tries++;

			$res = $this->get_order_data($trans_id);

			if (empty($res[0]->post_id)) {
				sleep(3);
			}
		} while ($tries <= $max_tries && empty($res[0]->post_id));

		if (empty($res[0]->post_id)) {
			Nuvei_Logger::write(
				array(
					'trans_id' => $trans_id,
					'tries' => $tries
				),
				'The DMN didn\'t wait for the Order creation. Exit.'
			);
			
			echo wp_json_encode('The DMN didn\'t wait for the Order creation. Exit.');
			exit;
		}

		return $res[0]->post_id;
	}
	
	/**
	 * Function create_refund_record
	 * 
	 * @param int $order_id
	 * @return int the order id
	 */
	private function create_refund_record( $order_id) {
		$refunds	= array();
		$ref_amount = 0;
		$tries		= 0;
		$ref_tr_id	= Nuvei_Http::get_param('TransactionID', 'int');
		
		$this->is_order_valid($order_id);
		
		if ( !in_array($this->sc_order->get_status(), array('completed', 'processing')) ) {
			Nuvei_Logger::write(
				$this->sc_order->get_status(),
				'DMN Refund Error - the Order status does not allow refunds, the status is:'
			);

			echo wp_json_encode(array('DMN Refund Error - the Order status does not allow refunds.'));
			exit;
		}
		
		// there is chance of slow saving of meta data (in create_refund_record()), so let's wait
		do {
			$refunds = json_decode($this->sc_order->get_meta(NUVEI_REFUNDS), true);
			Nuvei_Logger::write('create_refund_record() Wait for Refund meta data.');
			
			sleep(3);
			$tries++;
		} while (empty($refunds[$ref_tr_id]) && $tries < 5);
		
		Nuvei_Logger::write($refunds, 'create_refund_record() Saved refunds for Order #' . $order_id);
		
		// check for DMN trans ID in the refunds
		if (
			!empty($refunds[$ref_tr_id])
			&& 'pending' == $refunds[$ref_tr_id]['status']
			&& !empty($refunds[$ref_tr_id]['refund_amount'])
		) {
			$ref_amount = $refunds[$ref_tr_id]['refund_amount'];
		} elseif (0 == $ref_amount && strpos(Nuvei_Http::get_param('clientRequestId'), 'gwp_') !== false) {
			// in case of CPanel refund - add Refund meta data here
			$ref_amount = Nuvei_Http::get_param('totalAmount', 'float');
		}
		
		if (0 == $ref_amount) {
			Nuvei_Logger::write('create_refund_record() Refund Amount is 0, do not create Refund in WooCommerce.');
			
			return;
		}
		
		$refund = wc_create_refund(array(
			'amount'	=> round(floatval($ref_amount), 2),
			'order_id'	=> $order_id,
		));
		
		if (is_a($refund, 'WP_Error')) {
			Nuvei_Logger::write($refund, 'create_refund_record() - the Refund process in WC returns error: ');
			
			echo wp_json_encode(array('create_refund_record() - the Refund process in WC returns error.'));
			exit;
		}
		
		$this->save_refund_meta_data(
			Nuvei_Http::get_param('TransactionID'),
			$ref_amount,
			'approved',
			$refund->get_id()
		);

		return true;
	}
	
	private function sum_order_refunds() {
		$refunds = json_decode($this->sc_order->get_meta(NUVEI_REFUNDS), true);
		$sum     = 0;
		
		if (!empty($refunds[Nuvei_Http::get_param('TransactionID', 'int')])) {
			Nuvei_Logger::write($refunds, 'Order Refunds');
			
			foreach ($refunds as $data) {
				if ('approved' == $data['status']) {
					$sum += $data['refund_amount'];
				}
			}
		}
		
		Nuvei_Logger::write($sum, 'Sum of refunds for an Order.');
		return round($sum, 2);
	}
	
	private function save_refund_meta_data( $trans_id, $ref_amount, $status = '', $wc_id = 0) {
		$refunds = json_decode($this->sc_order->get_meta(NUVEI_REFUNDS), true);
		
		if (empty($refunds)) {
			$refunds = array();
		}
		
		//      Nuvei_Logger::write($refunds, 'save_refund_meta_data(): Saved Refunds before the current one.');
		
		// add the new refund
		$refunds[$trans_id] = array(
			'refund_amount'	=> round((float) $ref_amount, 2),
			'status'		=> empty($status) ? 'pending' : $status
		);
		
		if (0 < $wc_id) {
			$refunds[$trans_id]['wc_id'] = $wc_id;
		}

		$this->sc_order->update_meta_data(NUVEI_REFUNDS, json_encode($refunds));
		$order_id = $this->sc_order->save();
		
		Nuvei_Logger::write('save_refund_meta_data() Saved Refund with Tr ID ' . $trans_id);
		
		return $order_id;
	}
	
	/**
	 * Function get_cuid
	 * 
	 * Get client unique id.
	 * We change it only for Sandbox (test) mode.
	 * 
	 * @return int|string
	 */
	private function get_cuid() {
		$clientUniqueId = Nuvei_Http::get_param('clientUniqueId');
		
		if ('yes' != $this->test) {
			return $clientUniqueId;
		}
		
		if (strpos($clientUniqueId, NUVEI_CUID_POSTFIX) !== false) {
			return current(explode('_', $clientUniqueId));
		}
		
		return $clientUniqueId;
	}
	
}
