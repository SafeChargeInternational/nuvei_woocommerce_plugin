<?php

/**
 * WC_SC Class
 *
 * Main class for the Nuvei Plugin
 */

class WC_SC extends WC_Payment_Gateway {
	# payments URL
	private $webMasterId            = 'WooCommerce ';
	private $stop_dmn               = 0; // when 1 - stops the DMN for testing
	private $cuid_postfix           = '_sandbox_apm'; // postfix for Sandbox APM payments
    private $nuvei_glob_attr_name   = 'Nuvei Payment Plan';
    private $subscr_units           = array('year', 'month', 'day');
    private $plugin_data            = array();
	private $sc_order;
        
	// the fields for the subscription
	private $subscr_fields = array('_sc_subscr_enabled', '_sc_subscr_plan_id', '_sc_subscr_initial_amount', '_sc_subscr_recurr_amount', '_sc_subscr_recurr_units', '_sc_subscr_recurr_period', '_sc_subscr_trial_units', '_sc_subscr_trial_period', '_sc_subscr_end_after_units', '_sc_subscr_end_after_period');
    
    //'_sc_subscr_trial_units', '_sc_subscr_trial_period', '_sc_subscr_end_after_units', '_sc_subscr_end_after_period');
	
	public function __construct() {
		# settings to get/save options
		$this->id                 = 'sc';
		$this->method_title       = SC_GATEWAY_TITLE;
		$this->method_description = 'Pay with ' . SC_GATEWAY_TITLE . '.';
		$this->icon               = plugin_dir_url(__FILE__) . 'icons/nuvei.png';
		$this->has_fields         = false;

		$this->init_settings();
		
		// required for the Store
		$this->title		= $this->sc_get_setting('title', '');
		$this->description	= $this->sc_get_setting('description', '');
		$this->test			= $this->sc_get_setting('test', '');
		$this->rewrite_dmn	= $this->sc_get_setting('rewrite_dmn', 'no');
		$this->webMasterId .= WOOCOMMERCE_VERSION;
		$this->plugin_data	= get_plugin_data(plugin_dir_path(__FILE__) . 'index.php'); // get plugin data from the index.
		
		//      $_SESSION['SC_Variables']['sc_create_logs'] = $this->sc_get_setting('save_logs');
		
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
		add_action('woocommerce_order_after_calculate_totals', array($this, 'sc_return_sc_settle_btn'));
		add_action('woocommerce_order_status_refunded', array($this, 'sc_restock_on_refunded_status'));
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
				'label' => __('Enable ' . SC_GATEWAY_TITLE . ' Payment Module.', 'nuvei_woocommerce'),
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
				'description' => __('Merchant ID is provided by ' . SC_GATEWAY_TITLE . '.')
			),
			'merchantSiteId' => array(
				'title' => __('Merchant Site ID', 'nuvei_woocommerce') . ' *',
				'type' => 'text',
				'required' => true,
				'description' => __('Merchant Site ID is provided by ' . SC_GATEWAY_TITLE . '.')
			),
			'secret' => array(
				'title' => __('Secret key', 'nuvei_woocommerce') . ' *',
				'type' => 'text',
				'required' => true,
				'description' =>  __('Secret key is provided by ' . SC_GATEWAY_TITLE, 'nuvei_woocommerce'),
			),
			'hash_type' => array(
				'title' => __('Hash type', 'nuvei_woocommerce') . ' *',
				'type' => 'select',
				'required' => true,
				'description' => __('Choose Hash type provided by ' . SC_GATEWAY_TITLE, 'nuvei_woocommerce'),
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
				'description' => $this->set_notify_url(),
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
	 * Function generate_button_html
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
		$field    = $this->plugin_id . $this->id . '_' . $key;
		$defaults = array(
			'class'             => 'button-secondary',
			'css'               => '',
			'custom_attributes' => array(),
			'desc_tip'          => false,
			'description'       => '',
			'title'             => '',
		);

		$data = wp_parse_args($data, $defaults);

		ob_start(); ?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($data['title']); ?></label>
				<?php echo esc_html($this->get_tooltip_html($data)); ?>
			</th>
			
			<td class="forminp" style="position: relative;">
				<fieldset>
					<legend class="screen-reader-text">
						<span><?php echo wp_kses_post($data['title']); ?></span>
					</legend>
					
					<button class="<?php echo esc_attr($data['class']); ?>" 
							type="button" 
							name="<?php echo esc_attr($field); ?>" 
							id="<?php echo esc_attr($field); ?>" 
							style="<?php echo esc_attr($data['css']); ?>" 
							<?php echo esc_attr($this->get_custom_attribute_html($data)); ?>
					>
						<?php echo esc_html($data['title']); ?>
					</button>
					
						<?php echo esc_html($this->get_description_html($data)); ?>
				</fieldset>
				
				<div id="custom_loader" class="blockUI blockOverlay" style="margin-left: -3.5em;"></div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	// Generate the HTML For the settings form.
	public function admin_options() {
		echo
			'<h3>' . esc_html(SC_GATEWAY_TITLE . ' ', 'nuvei_woocommerce') . '</h3>'
				. '<p>' . esc_html__('SC payment option') . '</p>'
				. '<table class="form-table">';
		
		$this->generate_settings_html();
		
		echo
				'</table>';
	}

	/**
	 * Function payment_fields
	 *
	 *  Add fields on the payment page. Because we get APMs with Ajax
	 * here we add only AMPs fields modal.
	 **/
	public function payment_fields() {
		if ($this->description) {
			echo wp_kses_post(wpautop(wptexturize($this->description)));
		}
		
		// echo here some html if needed
	}

	/**
	  * Function process_payment
	  * Process the payment and return the result. This is the place where site
	  * POST the form and then redirect. Here we will get our custom fields.
	  *
	  * @param int $order_id
	 **/
	public function process_payment( $order_id) {
		$this->create_log('Process payment(), Order #' . $order_id);
		
		//      $_SESSION['nuvei_last_open_order_details'] = array();
		session_destroy();
		
		$sc_nonce = $this->get_param('sc_nonce');
		
		if (
			!empty($sc_nonce)
			&& !wp_verify_nonce($sc_nonce, 'sc_checkout')
		) {
			$this->create_log('process_payment() Error - can not verify WP Nonce.');
			
			return array(
				'result'    => 'success',
				'redirect'  => array(
					'Status'    => 'error',
				),
				wc_get_checkout_url() . 'order-received/' . $order_id . '/'
			);
		}
		
		$order = wc_get_order($order_id);
		
		if (!$order) {
			$this->create_log('Order is false for order id ' . $order_id);
			
			return array(
				'result'    => 'success',
				'redirect'  => array(
					'Status'    => 'error',
				),
				wc_get_checkout_url() . 'order-received/' . $order_id . '/'
			);
		}
		
		$return_success_url = add_query_arg(
			array('key' => $order->get_order_key()),
			$this->get_return_url($order)
		);
		
		$return_error_url = add_query_arg(
			array(
				'Status'    => 'error',
				'key'        => $order->get_order_key()
			),
			$this->get_return_url($order)
		);
		
		if ('sc' !== $order->get_payment_method()) {
			$this->create_log('Process payment Error - Order payment method is not "sc".');
			
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
		$sc_transaction_id = $this->get_param('sc_transaction_id', 'int');
		
		# in case of webSDK payment (cc_card)
		if (!empty($sc_transaction_id)) {
			$this->create_log('Process webSDK Order, transaction ID #' . $sc_transaction_id);
			
			$order->update_meta_data(SC_TRANS_ID, $sc_transaction_id);
			$order->save();
			
			return array(
				'result'    => 'success',
				'redirect'  => $return_success_url
			);
		}
		# in case of webSDK payment (cc_card) END
		
		$this->create_log('Process Rest APM Order.');
		
		// if we use APM
		$time = gmdate('Ymdhis');
		
		// complicated way to filter all $_POST input, but WP will be happy
		$post_array = $_POST;
		
		array_walk_recursive($post_array, function ( &$value) {
			$value = trim($value);
			$value = filter_var($value);
		});
		// complicated way to filter all $_POST input, but WP will be happy END
		
		$params = array(
			'merchantId'        => $this->sc_get_setting('merchantId'),
			'merchantSiteId'    => $this->sc_get_setting('merchantSiteId'),
			'clientUniqueId'    => $this->set_cuid($order_id),
			'clientRequestId'   => $time . '_' . uniqid(),
			'currency'          => $order->get_currency(),
			'amount'            => (string) $order->get_total(),
			
			'amountDetails'     => array(
				'totalShipping'     => '0.00',
				'totalHandling'     => '0.00',
				'totalDiscount'     => '0.00',
				'totalTax'          => '0.00',
			),
			
			'billingAddress'    => array(
				'firstName'    => $order->get_billing_first_name(),
				'lastName'    => $order->get_billing_last_name(),
				'address'   => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
				'phone'     => $order->get_billing_phone(),
				'zip'       => $order->get_billing_postcode(),
				'city'      => $order->get_billing_city(),
				'country'    => $order->get_billing_country(),
				'email'        => $order->get_billing_email(),
			),
			
			'shippingAddress'    => array(
				'firstName'            => $order->get_shipping_first_name(),
				'lastName'            => $order->get_shipping_last_name(),
				'address'            => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
				'zip'                => $order->get_shipping_postcode(),
				'city'                => $order->get_shipping_city(),
				'country'            => $order->get_shipping_country(),
				'cell'                => '',
				'phone'                => '',
				'state'                => '',
				'shippingCounty'    => '',
			),
			
			'urlDetails'        => array(
				'successUrl'        => $return_success_url,
				'failureUrl'        => $return_error_url,
				'pendingUrl'        => $return_success_url,
				'notificationUrl'   => $this->set_notify_url(),
			),
			
			'timeStamp'         => $time,
			'webMasterId'       => $this->webMasterId,
			'sourceApplication' => SC_SOURCE_APPLICATION,
			'deviceDetails'     => SC_CLASS::get_device_details(),
			'sessionToken'      => $post_array['lst'],
		);
		
		$params['userDetails'] = $params['billingAddress'];
		
		$params['items'][0] = array(
			'name'      => $order_id,
			'price'     => $params['amount'],
			'quantity'  => 1,
		);
		
		$params['checksum'] = hash(
			$this->sc_get_setting('hash_type'),
			$params['merchantId'] . $params['merchantSiteId'] . $params['clientRequestId']
				. $params['amount'] . $params['currency'] . $time . $this->sc_get_setting('secret')
		);
		
		$sc_payment_method = $post_array['sc_payment_method'];
		
		// UPO
		if (is_numeric($sc_payment_method)) {
			$endpoint_method                                = 'payment';
			$params['paymentOption']['userPaymentOptionId'] = $sc_payment_method;
			$params['userTokenId']							= $order->get_billing_email();
		} else {
			// APM
			$endpoint_method         = 'paymentAPM';
			$params['paymentMethod'] = $sc_payment_method;
			
			if (!empty($post_array[$sc_payment_method])) {
				$params['userAccountDetails'] = $post_array[$sc_payment_method];
			}
			
			if ($this->get_param('nuvei_save_upo') == 1) {
				$params['userTokenId'] = $order->get_billing_email();
			}
		}
		
		$resp = $this->callRestApi($endpoint_method, $params);
		
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
		
		// If we get Transaction ID save it as meta-data
		if (isset($resp['transactionId']) && $resp['transactionId']) {
			$order->update_meta_data(SC_TRANS_ID, $resp['transactionId'], 0);
		}

		if (
			'DECLINED' === $this->get_request_status($resp)
			|| 'DECLINED' === @$resp['transactionStatus']
		) {
			$order->add_order_note(__('Order Declined.', 'nuvei_woocommerce'));
			$order->set_status('cancelled');
			$order->save();
			
			return array(
				'result'    => 'success',
				'redirect'  => $return_error_url
			);
		}
		
		if (
			'ERROR' === $this->get_request_status($resp)
			|| 'ERROR' === @$resp['transactionStatus']
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
		if (
			( isset($resp['gwErrorCode']) && -1 === $resp['gwErrorCode'] )
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
		
		// Success status
		if (!empty($resp['redirectURL']) || !empty($resp['paymentOption']['redirectUrl'])) {
			return array(
				'result'    => 'success',
				'redirect'    => add_query_arg(
					array(),
					!empty($resp['redirectURL']) ? $resp['redirectURL'] : $resp['paymentOption']['redirectUrl']
				)
			);
		}

		if (isset($resp['transactionId']) && '' !== $resp['transactionId']) {
			$order->add_order_note(__('Payment succsess for Transaction Id ', 'nuvei_woocommerce') . $resp['transactionId']);
		} else {
			$order->add_order_note(__('Payment succsess.', 'nuvei_woocommerce'));
		}

		// save the response transactionType value
		if (isset($resp['transactionType']) && '' !== $resp['transactionType']) {
			$order->update_meta_data(SC_RESP_TRANS_TYPE, $resp['transactionType']);
		}

		$order->save();
		
		return array(
			'result'    => 'success',
			'redirect'  => $return_success_url
		);
	}
	
	public function add_apms_step( $data) {
		ob_start();
		require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'templates/sc_second_step_form.php';
		ob_end_flush();
	}
	
	//  public function get_payment_methods( $content) {
	public function get_payment_methods() {
		global $woocommerce;
		
		$cart      = $woocommerce->cart;
		$resp_data = array(); // use it in the template
		# OpenOrder
		$oo_data = $this->open_order();
		
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
		$time     = gmdate('YmdHis', time());
		$currency = !empty($oo_data['currency']) ? $oo_data['currency'] : get_woocommerce_currency();
		
		if (!empty($oo_data['billingAddress']['country'])) {
			$countryCode = $oo_data['billingAddress']['country'];
		} elseif (!empty($_SESSION['nuvei_last_open_order_details']['billingAddress']['country'])) {
			$countryCode = $_SESSION['nuvei_last_open_order_details']['billingAddress']['country'];
		} else {
			$countryCode = '';
		}
		
		$apms_params = array(
			'merchantId'        => $this->sc_get_setting('merchantId'),
			'merchantSiteId'    => $this->sc_get_setting('merchantSiteId'),
			'clientRequestId'   => $time . '_' . uniqid(),
			'timeStamp'         => $time,
			'sessionToken'      => $oo_data['sessionToken'],
			'currencyCode'      => $currency,
			'countryCode'       => $countryCode,
			'languageCode'      => $this->formatLocation(get_locale()),
		);
		
		$apms_params['checksum'] = hash(
			$this->sc_get_setting('hash_type'),
			$this->sc_get_setting('merchantId') . $this->sc_get_setting('merchantSiteId') . $apms_params['clientRequestId']
				. $time . $this->sc_get_setting('secret')
		);

		$apms_data = $this->callRestApi('getMerchantPaymentMethods', $apms_params);
		
		if (!is_array($apms_data) || empty($apms_data['paymentMethods'])) {
			wp_send_json(array(
				'result'	=> 'failure',
				'refresh'	=> false,
				'reload'	=> false,
				'messages'	=> '<ul id="sc_fake_error" class="woocommerce-error" role="alert"><li>' . __('Can not obtain Payment Methods, please try again later!', 'nuvei_woocommerce') . '</li></ul>'
			));

			exit;
		}
		
		$resp_data['apms'] = $apms_data['paymentMethods'];
		# get APMs END
		
		# get UPOs
		$icons     = array();
		$upos      = array();
		$user_mail = '';

		// get them only for registred users when there are APMs
		if (
			1 == $this->sc_get_setting('use_upos')
			&& is_user_logged_in()
			&& !empty($apms_data['paymentMethods'])
		) {
			$user_mail = $this->get_param('billing_email', 'mail');
		
			if (empty($user_mail)) {
				$addresses = $this->get_order_addresses();
				
				if (!empty($addresses['billingAddress']['email'])) {
					$user_mail = $addresses['billingAddress']['email'];
				}
			}
			
			$upo_params = array(
				'merchantId'        => $apms_params['merchantId'],
				'merchantSiteId'    => $apms_params['merchantSiteId'],
				'userTokenId'       => $user_mail,
				'clientRequestId'   => $apms_params['clientRequestId'],
				'timeStamp'         => $time,
			);

			$upo_params['checksum'] = hash(
				$this->sc_get_setting('hash_type'),
				implode('', $upo_params) . $this->sc_get_setting('secret')
			);

			$upo_res = $this->callRestApi('getUserUPOs', $upo_params);
			
			if (!empty($upo_res['paymentMethods']) && is_array($upo_res['paymentMethods'])) {
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
							$data['logoURL'] = @$pm_data['logoURL'];
							$data['name']    = @$pm_data['paymentMethodDisplayName'][0]['message'];

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
		$resp_data['userTokenId'] = $user_mail;
		$resp_data['pluginUrl']   = plugin_dir_url(__FILE__);
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
		$this->create_log($_REQUEST, 'DMN params');
		
		// stop DMNs only on test mode
		if ($this->get_param('stopDMN', 'int') == 1 && $this->sc_get_setting('test') == 'yes') {
			$params            = $_REQUEST;
			$params['stopDMN'] = 0;
			
			$this->create_log(
				get_site_url() . '/?' . http_build_query($params),
				'DMN was stopped, please run it manually from the URL bleow:'
			);
			
			echo wp_json_encode('DMN was stopped, please run it manually!');
			exit;
		}
		
		$req_status = $this->get_request_status();
		
		if (empty($req_status)) {
			$this->create_log($_REQUEST, 'DMN Error - the Status is empty!');
			
			echo wp_json_encode('DMN Error - the Status is empty!');
			exit;
		}
		
		// santitized get variables
		$invoice_id           = $this->get_param('invoice_id');
		$clientUniqueId       = $this->get_cuid();
		$transactionType      = $this->get_param('transactionType');
		$Reason               = $this->get_param('Reason');
		$action               = $this->get_param('action');
		$order_id             = $this->get_param('order_id', 'int');
		$gwErrorReason        = $this->get_param('gwErrorReason');
		$AuthCode             = $this->get_param('AuthCode', 'int');
		$TransactionID        = $this->get_param('TransactionID', 'int');
		$relatedTransactionId = $this->get_param('relatedTransactionId', 'int');
		
		if (empty($TransactionID)) {
			$this->create_log($_REQUEST, 'DMN error - The TransactionID is empty!');
			
			echo wp_json_encode('DMN error - The TransactionID is empty!');
			exit;
		}
		
		if (!$this->checkAdvancedCheckSum()) {
			$this->create_log($_REQUEST, 'DMN Error - AdvancedCheckSum problem!');
			
			echo wp_json_encode('DMN Error - AdvancedCheckSum problem!');
			exit;
		}
		
		# Sale and Auth
		if (in_array($transactionType, array('Sale', 'Auth'), true)) {
			// WebSDK
			if (
				!is_numeric($clientUniqueId)
				&& $this->get_param('TransactionID', 'int') != 0
			) {
				$order_id = $this->get_order_by_trans_id($TransactionID, $transactionType);
				
			} else {
				// REST
				$this->create_log($order_id, '$order_id');
				
				
				if (empty($order_id) && is_numeric($clientUniqueId)) {
					$order_id = $clientUniqueId;
				}
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
			
			echo esc_html('DMN process end for Order #' . $order_id);
			exit;
		}
		
		// try to get the Order ID
		$ord_data = $this->sc_get_order_data($relatedTransactionId);

		if (!empty($ord_data[0]->post_id)) {
			$order_id = $ord_data[0]->post_id;
		}
			
		# Void, Settle
		if (
			'' != $clientUniqueId
			&& ( in_array($transactionType, array('Void', 'Settle'), true) )
		) {
			$this->is_order_valid($order_id);
			
			/* translators: %d is replaced with "decimal" */
			$msg = sprintf(__('DMN for Order #%d, was received.', 'nuvei_woocommerce'), $order_id);
			
			if ('Settle' == $transactionType) {
				$this->save_update_order_numbers();
			}

			$this->change_order_status($order_id, $req_status, $transactionType);
				
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
					'totalAmount'   => $this->get_param('totalAmount', 'float')
				)
			);

			echo wp_json_encode(array('DMN process end for Order #' . $order_id));
			exit;
		}
		
		$this->create_log(
			array(
				'TransactionID' => $TransactionID,
				'relatedTransactionId' => $relatedTransactionId,
			),
			'DMN was not recognized.'
		);
		
		echo wp_json_encode('DMN was not recognized.');
		exit;
	}
	
	//  public function sc_checkout_process() {
	//      $_SESSION['sc_subpayment'] = '';
	//      $sc_payment_method         = $this->get_param('sc_payment_method');
	//      
	//      if ('' != $sc_payment_method) {
	//          $_SESSION['sc_subpayment'] = $sc_payment_method;
	//      }
	//      
	//      return true;
	//  }

	public function showMessage( $content) {
		return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
	}

	/**
	 * Function checkAdvancedCheckSum
	 * Checks the Advanced Checsum
	 *
	 * @return boolean
	 */
	public function checkAdvancedCheckSum() {
		$str = hash(
			$this->sc_get_setting('hash_type'),
			$this->sc_get_setting('secret') . $this->get_param('totalAmount')
				. $this->get_param('currency') . $this->get_param('responseTimeStamp')
				. $this->get_param('PPP_TransactionID') . $this->get_request_status()
				. $this->get_param('productId')
		);
		
		//      var_dump($this->sc_get_setting('secret') . $this->get_param('totalAmount')
		//          . $this->get_param('currency') . $this->get_param('responseTimeStamp')
		//          . $this->get_param('PPP_TransactionID') . $this->get_request_status()
		//          . $this->get_param('productId'));
		
		//      var_dump($this->get_param('totalAmount', 'float'));
		
		if (strval($str) == $this->get_param('advanceResponseChecksum')) {
			return true;
		}
		
		return false;
	}
	
	public function set_notify_url() {
		$url_part = get_site_url();
			
		$url = $url_part . ( strpos($url_part, '?') !== false ? '&' : '?' )
			. 'wc-api=sc_listener&stopDMN=' . $this->stop_dmn;
		
		// some servers needs / before ?
		if (strpos($url, '?') !== false && strpos($url, '/?') === false) {
			$url = str_replace('?', '/?', $url);
		}
		
		// force Notification URL protocol to http
		if ('yes' === $this->sc_get_setting('use_http') && false !== strpos($url, 'https://')) {
			$url = str_replace('https://', '', $url);
			$url = 'http://' . $url;
		}
		
		return $url;
	}
	
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
		if ('true' == $this->get_param('api_refund')) {
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
			$this->create_log($order_id, 'create_refund_request() Error - Post parameter is less than 1.');
			
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
		
		$tr_id = $this->sc_order->get_meta(SC_TRANS_ID);
		
		if (empty($tr_id)) {
			wp_send_json(array(
				'status' => 0,
				'msg' => __('The Order missing Transaction ID.', 'nuvei_woocommerce')));
			exit;
		}
		
		$notify_url	= $this->set_notify_url();
		$time		= gmdate('YmdHis', time());
		
		$ref_parameters = array(
			'merchantId'            => $this->settings['merchantId'],
			'merchantSiteId'        => $this->settings['merchantSiteId'],
			'clientRequestId'       => $order_id . '_' . $time . '_' . uniqid(),
			'clientUniqueId'        => $time . '_' . uniqid(),
			'amount'                => number_format($ref_amount, 2, '.', ''),
			'currency'              => get_woocommerce_currency(),
			'relatedTransactionId'  => $tr_id, // GW Transaction ID
			'url'                   => $notify_url,
			'timeStamp'             => $time,
		);
		
		$checksum_str = implode('', $ref_parameters);
		
		$checksum = hash(
			$this->settings['hash_type'],
			$checksum_str . $this->settings['secret']
		);
		
		$ref_parameters['checksum']          = $checksum;
		$ref_parameters['urlDetails']        = array('notificationUrl' => $notify_url);
		$ref_parameters['webMasterId']       = $this->webMasterId;
		$ref_parameters['sourceApplication'] = SC_SOURCE_APPLICATION;
		
		$resp = $this->callRestApi('refundTransaction', $ref_parameters);
		$msg  = '';

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
			
			$this->create_log($msg);

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
			
			$this->create_log($msg);
			
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
			
			$this->create_log($msg);
			
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
			
			$this->create_log($msg);
			
			wp_send_json(array(
				'status' => 0,
				'msg' => $msg
			));
			exit;
		}

		$msg = __('The status of Refund request is UNKONOWN.', 'nuvei_woocommerce');

		$this->sc_order->add_order_note($msg);
		$this->sc_order->save();
		
		$this->create_log($msg);
		
		wp_send_json(array(
			'status' => 0,
			'msg' => $msg
		));
		exit;
	}
	
	public function sc_return_sc_settle_btn( $args) {
		// revert buttons on Recalculate
		if (!$this->get_param('refund_amount', 'float', false) && !empty($this->get_param('items'))) {
			echo esc_js('<script type="text/javascript">returnSCBtns();</script>');
		}
	}

	/**
	 * Function sc_restock_on_refunded_status
	 * Restock on refund.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public function sc_restock_on_refunded_status( $order_id) {
		$order            = wc_get_order($order_id);
		$items            = $order->get_items();
		$is_order_restock = $order->get_meta('_scIsRestock');
		
		// do restock only once
		if (1 !== $is_order_restock) {
			wc_restock_refunded_items($order, $items);
			$order->update_meta_data('_scIsRestock', 1);
			$order->save();
			
			$this->create_log('Items were restocked.');
		}
		
		return;
	}
	
	/**
	 * Function create_void
	 *
	 * @param int $order_id
	 * @param string $action
	 */
	public function create_settle_void( $order_id, $action) {
		$ord_status = 1;
		$time       = gmdate('YmdHis');
		
		if ('settle' == $action) {
			//          $method = 'settleTransaction.do';
			$method = 'settleTransaction';
		} else {
			//          $method = 'voidTransaction.do';
			$method = 'voidTransaction';
		}
		
		try {
			$order = wc_get_order($order_id);
			
			$order_meta_data = array(
				'order_tr_id'   => $order->get_meta(SC_TRANS_ID),
				'auth_code'     => $order->get_meta(SC_AUTH_CODE_KEY),
			);
			
			$params = array(
				'merchantId'            => $this->sc_get_setting('merchantId'),
				'merchantSiteId'        => $this->sc_get_setting('merchantSiteId'),
				'clientRequestId'        => $time . '_' . uniqid(),
				'clientUniqueId'        => $order_id,
				'amount'                => (string) $order->get_total(),
				'currency'                => get_woocommerce_currency(),
				'relatedTransactionId'    => $order_meta_data['order_tr_id'],
				'authCode'                => $order_meta_data['auth_code'],
				'urlDetails'            => array(
					'notificationUrl'   => $this->set_notify_url(),
				),
				'timeStamp'                => $time,
				'checksum'                => '',
				'webMasterId'            => $this->webMasterId,
				'sourceApplication'        => SC_SOURCE_APPLICATION,
			);

			$params['checksum'] = hash(
				$this->sc_get_setting('hash_type'),
				$params['merchantId'] . $params['merchantSiteId'] . $params['clientRequestId']
					. $params['clientUniqueId'] . $params['amount'] . $params['currency']
					. $params['relatedTransactionId'] . $params['authCode']
					. $params['urlDetails']['notificationUrl'] . $params['timeStamp']
					. $this->sc_get_setting('secret')
			);

			$resp = $this->callRestApi($method, $params);
		} catch (Exception $ex) {
			$this->create_log($ex->getMessage(), 'Create void exception:');
			
			wp_send_json(array(
				'status' => 0,
				'msg' => 'Unexpexted error during the ' . $action
			));
			exit;
		}
		
		if (
			!$resp || !is_array($resp)
			|| 'ERROR' == @$resp['status']
			|| 'ERROR' == @$resp['transactionStatus']
			|| 'DECLINED' == @$resp['transactionStatus']
		) {
			$ord_status = 0;
		} else {
			$order->update_status('processing');
		}
		
		wp_send_json(array('status' => $ord_status, 'data' => $resp));
		exit;
	}
	
	public function delete_user_upo() {
		$upo_id = $this->get_param('scUpoId', 'int', false);
		
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
		
		if (empty($curr_user->user_email) || !filter_var($curr_user->user_email, FILTER_VALIDATE_EMAIL)) {
			wp_send_json(array(
				'status' => 'error',
				'msg' => 'The user email is not valid.'
			));

			exit;
		}
		
		$timeStamp = gmdate('YmdHis', time());
			
		$params = array(
			'merchantId'            => $this->sc_get_setting('merchantId'),
			'merchantSiteId'        => $this->sc_get_setting('merchantSiteId'),
			'userTokenId'            => $curr_user->user_email,
			'clientRequestId'        => $timeStamp . '_' . uniqid(),
			'userPaymentOptionId'    => $upo_id,
			'timeStamp'                => $timeStamp,
		);
		
		$params['checksum'] = hash(
			$this->sc_get_setting('hash_type'),
			implode('', $params) . $this->sc_get_setting('secret')
		);
		
		$resp = $this->callRestApi('deleteUPO', $params);
		
		if (empty($resp['status']) || 'SUCCESS' != $resp['status']) {
			$msg = !empty($resp['reason']) ? $resp['reason'] : '';
			
			wp_send_json(
			
				array(
				'status' => 'error',
				'msg' => $msg
				)
			);

			exit;
		}
		
		wp_send_json(array('status' => 'success'));
		exit;
	}
	
	/**
	 * Function get_request_status
	 * We need this stupid function because as response request variable
	 * we get 'Status' or 'status'...
	 *
	 * @return string
	 */
	public function get_request_status( $params = array()) {
		$Status = $this->get_param('Status');
		$status = $this->get_param('status');
		
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
	
	/**
	 * Function get_param
	 * Get request parameter by key
	 *
	 * @param string $key - request key
	 * @param string $type - possible vaues: string, float, int, array, mail, other
	 * @param mixed $default - return value if fail
	 * @param array $parent - optional list with parameters
	 *
	 * @return mixed
	 */
	public function get_param( $key, $type = 'string', $default = '', $parent = array()) {
		$arr = $_REQUEST;
		
		if (!empty($parent) && is_array($parent)) {
			$arr = $parent;
		}
		
		switch ($type) {
			case 'mail':
				return !empty($arr[$key])
					? filter_var($arr[$key], FILTER_VALIDATE_EMAIL) : $default;
				
			case 'float':
				if (empty($default)) {
					$default = (float) 0;
				}
				
				return ( !empty($arr[$key]) && is_numeric($arr[$key]) )
					? floatval(filter_var($arr[$key], FILTER_DEFAULT)) : $default;
				
			case 'int':
				if (empty($default)) {
					$default = (float) 0;
				}
				
				return ( !empty($arr[$key]) && is_numeric($arr[$key]) )
					? intval(filter_var($arr[$key], FILTER_DEFAULT)) : $default;
				
			case 'array':
				if (empty($default)) {
					$default = array();
				}
				
				return !empty($arr[$key])
					? filter_var($arr[$key], FILTER_REQUIRE_ARRAY) : $default;
				
			case 'string':
				return !empty($arr[$key])
			//                  ? urlencode(preg_replace('/[[:punct:]]/', '', filter_var($arr[$key], FILTER_SANITIZE_STRING))) : $default;
			//                  ? urlencode(filter_var($arr[$key], FILTER_SANITIZE_STRING)) : $default;
					? filter_var($arr[$key], FILTER_SANITIZE_STRING) : $default;
				
			default:
				return !empty($arr[$key])
					? filter_var($arr[$key], FILTER_DEFAULT) : $default;
		}
	}
	
	public function sc_reorder() {
		global $woocommerce;
		
		$products_ids = json_decode($this->get_param('product_ids'), true);
		
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
	 * Function download_subscr_pans
	 * 
     * Download the Active Payment pPlans and save them to a json file.
     * If there are no Active Plans, create default one with name, based
     * on MerchatSiteId parameter, and get it.
     * 
     * @param int $recursions
	 */
	public function download_subscr_pans($recursions = 0) {
        $this->create_nuvei_global_attribute();
        wp_send_json(array(
            'status' => 1,
            'time' => gmdate('Y-m-d H:i:s')
        ));
        exit;
            
            
            
        
        if($recursions > 1) {
            wp_send_json(array('status' => 0));
            exit;
        }
        
		$time     = gmdate('YmdHis');
		$uniq_str = $time . '_' . uniqid();
		
		$params = array(
			'merchantId'        => $this->sc_get_setting('merchantId'),
			'merchantSiteId'    => $this->sc_get_setting('merchantSiteId'),
			'clientRequestId'	=> $uniq_str,
			'timeStamp'         => $time,
			'webMasterId'       => $this->webMasterId,
			'encoding'          => 'UTF-8',
			'planStatus'		=> 'ACTIVE',
			'currency'			=> '',
		);
		
		$params['checksum'] = hash(
			$this->sc_get_setting('hash_type'),
			$this->sc_get_setting('merchantId') . $this->sc_get_setting('merchantSiteId') . $params['currency']
				. $params['planStatus'] . $time . $this->sc_get_setting('secret')
		);
		
		$resp = $this->callRestApi('getPlansList', $params);
		
		if (empty($resp) || !is_array($resp) || 'SUCCESS' != $resp['status']) {
			$this->create_log('Get Plans response error.');
			
			wp_send_json(array('status' => 0));
			exit;
		}
        
        // in case there are  no active plans - create default one
        if(isset($resp['total']) && 0 == $resp['total']) {
            $time = gmdate('YmdHis');
            
            $create_params = array(
                'merchantId'        => $params['merchantId'],
                'merchantSiteId'    => $params['merchantSiteId'],
                'name'              => 'Default_plan_for_site_' . $params['merchantSiteId'],
                'initialAmount'     => 0,
                'recurringAmount'   => 1,
                'currency'          => get_woocommerce_currency(),
                'timeStamp'         => $time,
                
                'clientRequestId'   => $time . '_' . uniqid(),
                'webMasterId'       => $this->webMasterId,
                'sourceApplication' => SC_SOURCE_APPLICATION,
                'encoding'          => 'UTF-8',
                
                'startAfter'        => array(
                    'day'   => 0,
                    'month' => 1,
                    'year'  => 0,
                ),
                'recurringPeriod'   => array(
                    'day'   => 0,
                    'month' => 1,
                    'year'  => 0,
                ),
                'endAfter'          => array(
                    'day'   => 0,
                    'month' => 0,
                    'year'  => 1,
                ),
                'planStatus'        => 'ACTIVE'
            );
            
            $create_params['checksum'] = hash(
                $this->sc_get_setting('hash_type'),
                implode('', array_slice($create_params, 0, 7)) . $this->sc_get_setting('secret')
            );
                
            $create_resp = $this->callRestApi('createPlan', $create_params);
            
            if (!empty($create_resp['planId'])) {
                $recursions += 1;
                $this->download_subscr_pans($recursions);
                return;
            }
        }
        // in case there are  no active plans - create default one END
		
		if (file_put_contents(plugin_dir_path(__FILE__) . '/tmp/sc_plans.json', json_encode($resp['plans']))) {
            $this->create_nuvei_global_attribute();
            
			wp_send_json(array(
				'status' => 1,
				'time' => gmdate('Y-m-d H:i:s')
			));
			exit;
		}
		
		$this->create_log(
			plugin_dir_path(__FILE__) . '/tmp/sc_plans.json',
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
	 * Function create_log
	 * Save a record into log file.
	 * 
	 * @param mixed $data
	 * @param string $title
	 */
	public function create_log( $data, $title = '') {
		$logs_path = plugin_dir_path(__FILE__) . 'logs' . DIRECTORY_SEPARATOR;
		
		// path is different fore each plugin
		if (!is_dir($logs_path) || $this->sc_get_setting('save_logs') != 'yes') {
			return;
		}
		
		$d		= $data;
		$string	= '';

		if (is_array($data)) {
			// do not log accounts if on prod
			if ($this->sc_get_setting('test') == 'no') {
				if (isset($data['userAccountDetails']) && is_array($data['userAccountDetails'])) {
					$data['userAccountDetails'] = 'account details';
				}
				if (isset($data['userPaymentOption']) && is_array($data['userPaymentOption'])) {
					$data['userPaymentOption'] = 'user payment options details';
				}
				if (isset($data['paymentOption']) && is_array($data['paymentOption'])) {
					$data['paymentOption'] = 'payment options details';
				}
			}
			// do not log accounts if on prod
			
			if (!empty($data['paymentMethods']) && is_array($data['paymentMethods'])) {
				$data['paymentMethods'] = json_encode($data['paymentMethods']);
			}
			if (!empty($data['plans']) && is_array($data['plans'])) {
				$data['plans'] = json_encode($data['plans']);
			}
			
			$d = $this->sc_get_setting('test') == 'yes' ? print_r($data, true) : json_encode($data);
		} elseif (is_object($data)) {
			$d = $this->sc_get_setting('test') == 'yes' ? print_r($data, true) : json_encode($data);
		} elseif (is_bool($data)) {
			$d = $data ? 'true' : 'false';
		}
		
		if (!empty($this->plugin_data['Version'])) {
			$string .= '[v.' . $this->plugin_data['Version'] . '] | ';
		}

		if (!empty($title)) {
			if (is_string($title)) {
				$string .= $title;
			} else {
				$string .= "\r\n" . ( $this->sc_get_setting('test') == 'yes'
					? json_encode($title, JSON_PRETTY_PRINT) : json_encode($title) );
			}
			
			$string .= "\r\n";
		}

		$string .= $d . "\r\n\r\n";
		
		file_put_contents(
			$logs_path . gmdate('Y-m-d', time()) . '.txt',
			gmdate('H:i:s', time()) . ': ' . $string,
			FILE_APPEND
		);
	}
	
	/**
	 * Function is_order_valid
	 * Checks if the Order belongs to WC_Order and if the order was made
	 * with Nuvei payment module.
	 * 
	 * @param int|string $order_id
	 * @param bool $return - return the order
	 * 
	 * @return bool|WC_Order
	 */
	public function is_order_valid( $order_id, $return = false) {
		$this->create_log('is_order_vali() check.');
		
		$this->sc_order = wc_get_order( $order_id );
		
		if ( ! is_a( $this->sc_order, 'WC_Order') ) {
			$this->create_log('is_order_valid() Error - Provided Order ID is not a WC Order');
			
			if ($return) {
				return false;
			}
			
			echo wp_json_encode('is_order_valid() Error - Provided Order ID is not a WC Order');
			exit;
		}
		
		if ('sc' !== $this->sc_order->get_payment_method()) {
			$this->create_log($this->sc_order->get_payment_method(), 'DMN Error - the order does not belongs to Nuvei.');
			
			if ($return) {
				return false;
			}
			
			echo wp_json_encode('DMN Error - the order does not belongs to Nuvei.');
			exit;
		}
		
		// can we override Order status (state)
		$ord_status = strtolower($this->sc_order->get_status());
		
		if ( in_array($ord_status, array('cancelled', 'refunded')) ) {
			$this->create_log($this->sc_order->get_payment_method(), 'DMN Error - can not override status of Voided/Refunded Order.');
			
			if ($return) {
				return false;
			}
			
			echo wp_json_encode('DMN Error - can not override status of Voided/Refunded Order.');
			exit;
		}
		
		if (
			'completed' == $ord_status
			&& 'auth' == strtolower($this->get_param('transactionType'))
		) {
			$this->create_log($this->sc_order->get_payment_method(), 'DMN Error - can not override status Completed with Auth.');
			
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
	 * Function open_order
	 * 
	 * First try to Update the Order. If fail request new OpenOrder.
	 * 
	 * @global Woocommerce $woocommerce
	 * @param boolean $is_ajax
	 * 
	 * @return mixed
	 */
	public function open_order( $is_ajax = false ) {
		global $woocommerce;
		
		$cart          = $woocommerce->cart;
		$time          = gmdate('YmdHis');
		$uniq_str      = $time . '_' . uniqid();
		$ajax_params   = array();
		$subscr_data   = array();
		$products_data = array();
		
		# try to update Order
		$resp = $this->update_order();
		
		if (!empty($resp['status']) && 'SUCCESS' == $resp['status']) {
			if ($is_ajax) {
				wp_send_json(array(
					'status'        => 1,
					'sessionToken'	=> $resp['sessionToken']
				));
				exit;
			}

			return $resp;
		}
		# try to update Order END
		
		if (!empty($this->get_param('scFormData'))) {
			parse_str($this->get_param('scFormData'), $ajax_params); 
		}
		
		// check for product with subscription
		foreach ($cart->get_cart() as $item_id => $item) {
			// check for enabled subscription
			if (current(get_post_meta($item['product_id'], '_sc_subscr_enabled')) == '1') {
				// mandatory data
				$subscr_data[$item['product_id']] = array(
					'planId' => current(get_post_meta($item['product_id'], '_sc_subscr_plan_id')),
					'initialAmount' => number_format(current(get_post_meta(
						$item['product_id'], '_sc_subscr_initial_amount')), 2, '.', ''),
					'recurringAmount' => number_format(current(get_post_meta(
						$item['product_id'], '_sc_subscr_recurr_amount')), 2, '.', ''),
				);
				
				# optional data
				$recurr_unit   = current(get_post_meta($item['product_id'], '_sc_subscr_recurr_units'));
				$recurr_period = current(get_post_meta($item['product_id'], '_sc_subscr_recurr_period'));
				$subscr_data[$item['product_id']]['recurringPeriod'][$recurr_unit] = $recurr_period;

				$trial_unit   = current(get_post_meta($item['product_id'], '_sc_subscr_trial_units'));
				$trial_period = current(get_post_meta($item['product_id'], '_sc_subscr_trial_period'));
				$subscr_data[$item['product_id']]['startAfter'][$trial_unit] = $trial_period;

				$end_after_unit   = current(get_post_meta($item['product_id'], '_sc_subscr_end_after_units'));
				$end_after_period = current(get_post_meta($item['product_id'], '_sc_subscr_end_after_period'));
				$subscr_data[$item['product_id']]['endAfter'][$end_after_unit] = $end_after_period;
				# optional data END
			}
			
			$products_data[$item['product_id']] = array(
				'quantity'	=> $item['quantity'],
				'price'		=> get_post_meta($item['product_id'] , '_price', true),
			);
		}
		// check for product with subscription END
		
		$addresses = $this->get_order_addresses();
		
		$oo_params = array(
			'merchantId'        => $this->sc_get_setting('merchantId'),
			'merchantSiteId'    => $this->sc_get_setting('merchantSiteId'),
			'clientRequestId'	=> $uniq_str,
			'clientUniqueId'    => $uniq_str . '_wc_cart',
			'amount'            => (string) number_format((float) $cart->total, 2, '.', ''),
			'currency'          => get_woocommerce_currency(),
			'timeStamp'         => $time,
			
			'urlDetails'        => array(
				'notificationUrl'   => $this->set_notify_url(),
			),
			
			'deviceDetails'     => SC_CLASS::get_device_details(),
			'billingAddress'	=> isset($addresses['billingAddress'])
				? $addresses['billingAddress'] : array(),
			'shippingAddress'	=> isset($addresses['shippingAddress'])
				? $addresses['shippingAddress'] : array(),
			'webMasterId'       => $this->webMasterId,
			'paymentOption'     => array('card' => array('threeD' => array('isDynamic3D' => 1))),
			'transactionType'   => $this->sc_get_setting('payment_action'),
			
			'merchantDetails'	=> array(
				'customField1' => json_encode($subscr_data), // put subscr data here
				'customField2' => json_encode($products_data), // item details
				'customField3' => time(), // open order create time
			),
		);
		
		$oo_params['userDetails'] = $oo_params['billingAddress'];
		
		$oo_params['checksum'] = hash(
			$this->sc_get_setting('hash_type'),
			$this->sc_get_setting('merchantId')
				. $this->sc_get_setting('merchantSiteId')
				. $oo_params['clientRequestId']
				. $oo_params['amount'] . $oo_params['currency'] . $time
				. $this->sc_get_setting('secret')
		);
		
		$resp = $this->callRestApi('openOrder', $oo_params);
		
		if (
			empty($resp['status'])
			|| empty($resp['sessionToken'])
			|| 'SUCCESS' != $resp['status']
		) {
			if ($is_ajax) {
				wp_send_json(array(
					'status'	=> 0,
					'msg'		=> $resp
				));
				exit;
			} else {
				return false;
			}
		}
		
		// set them to session for the check before submit the data to the webSDK
		$_SESSION['nuvei_last_open_order_details'] = array(
			'amount'			=> $oo_params['amount'],
			'merchantDetails'	=> $oo_params['merchantDetails'],
			'sessionToken'		=> $resp['sessionToken'],
			'clientRequestId'	=> $oo_params['clientRequestId'],
			'orderId'			=> $resp['orderId'],
			'billingAddress'	=> $oo_params['billingAddress'],
		);
		
		$this->create_log($cart->nuvei_last_open_order_details, 'nuvei_last_open_order_details');
		
		if ($is_ajax) {
			wp_send_json(array(
				'status'        => 1,
				'sessionToken'    => $resp['sessionToken']
			));
			exit;
		}
		
		return array_merge($resp, $oo_params);
	}
	
	public function can_use_upos() {
		return $this->settings['use_upos'];
	}
    
    public function get_slug($text = '') {
        return str_replace(' ', '-', strtolower($text));
    }
    
    public function create_nuvei_global_attribute() {
        $this->create_log('create_nuvei_global_attribute()');
        
        $nuvei_plans_path           = plugin_dir_path(__FILE__) . '/tmp/sc_plans.json';
        $nuvei_glob_attr_name_slug  = $this->get_slug($this->nuvei_glob_attr_name);
        $taxonomy_name              = wc_attribute_taxonomy_name($nuvei_glob_attr_name_slug);
        
        // a check
        if (!is_readable($nuvei_plans_path)) {
            $this->create_log('Plans json is not readable.');
            
            wp_send_json(array(
                'status'    => 0,
                'msg'       => __('Plans json is not readable.')
            ));
            exit;
        }
        
        $plans = json_decode(file_get_contents($nuvei_plans_path), true);

        // a check
        if(empty($plans) || !is_array($plans)) {
            $this->create_log($plans, 'Unexpected problem with the Plans list.');

            wp_send_json(array(
                'status'    => 0,
                'msg'       => __('Unexpected problem with the Plans list.')
            ));
            exit;
        }
        
        // check if Taxonomy exists
        if (taxonomy_exists($taxonomy_name)) {
            $this->create_log('$taxonomy_name exists');
            
            /** TODO - update data */
        }
        else { // create the Global Attribute
            $args = array(
                'name'         => $this->nuvei_glob_attr_name,
                'slug'         => $nuvei_glob_attr_name_slug,
                'order_by'     => 'menu_order',
                'has_archives' => true,
            );

            // create the attribute and check for errors
            $attribute_id = wc_create_attribute($args);

            if(is_wp_error($attribute_id)) {
                $this->create_log(
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
            
            register_taxonomy(
                $taxonomy_name, 
                array('product'), 
                array(
                    'public' => false,
                )
            );
        }
        
        $this->create_log('create_nuvei_global_attribute() 2');
        
        // try to create the Plans Terms
        foreach($plans as $plan) {
            $term_slug  = $this->get_slug($plan['name']);

            $term       = wp_insert_term(
                $plan['name'], 
                $taxonomy_name,
                array('slug' => $term_slug)
            );

            $this->create_log($term, 'resp term data after insert');

            if(is_wp_error($term)) {
                $this->create_log(
                    array(
                        '$taxonomy_name'    => $taxonomy_name,
                        'term name'         => $plan['name'],
                        'message'           => $term->get_error_message(),
                    ),
                    'Error when try to add a term to the Nuvei Global Attribute'
                );

                break;
            }

            // fill the plan data as Meta for the Term
            add_term_meta( $term['term_id'], 'planId', $plan['planId'] );
            add_term_meta( $term['term_id'], 'recurringAmount', $plan['recurringAmount'] );
                
            if($plan['startAfter']['year'] > 0) {
                add_term_meta( $term['term_id'], 'startAfterUnit', 'year' );
                add_term_meta( $term['term_id'], 'startAfterPeriod', $plan['startAfter']['year'] );
            }
            elseif($plan['startAfter']['month'] > 0) {
                add_term_meta( $term['term_id'], 'startAfterUnit', 'month' );
                add_term_meta( $term['term_id'], 'startAfterPeriod', $plan['startAfter']['month'] );
            }
            else {
                add_term_meta( $term['term_id'], 'startAfterUnit', 'day' );
                add_term_meta( $term['term_id'], 'startAfterPeriod', $plan['startAfter']['day'] );
            }
                
            if($plan['recurringPeriod']['year'] > 0) {
                add_term_meta( $term['term_id'], 'recurringPeriodUnit', 'year' );
                add_term_meta( $term['term_id'], 'recurringPeriodPeriod', $plan['recurringPeriod']['year'] );
            }
            elseif($plan['recurringPeriod']['month'] > 0) {
                add_term_meta( $term['term_id'], 'recurringPeriodUnit', 'month' );
                add_term_meta( $term['term_id'], 'recurringPeriodPeriod', $plan['recurringPeriod']['month'] );
            }
            else {
                add_term_meta( $term['term_id'], 'recurringPeriodUnit', 'day' );
                add_term_meta( $term['term_id'], 'recurringPeriodPeriod', $plan['recurringPeriod']['day'] );
            }

            if($plan['endAfter']['year'] > 0) {
                add_term_meta( $term['term_id'], 'endAfterUnit', 'year' );
                add_term_meta( $term['term_id'], 'endAfterPeriod', $plan['endAfter']['year'] );
            }
            elseif($plan['endAfter']['month'] > 0) {
                add_term_meta( $term['term_id'], 'endAfterUnit', 'month' );
                add_term_meta( $term['term_id'], 'endAfterPeriod', $plan['endAfter']['month'] );
            }
            else {
                add_term_meta( $term['term_id'], 'endAfterUnit', 'day' );
                add_term_meta( $term['term_id'], 'endAfterPeriod', $plan['endAfter']['day'] );
            }
        }
    }
    
    /**
     * Function get_nuvei_glob_attr_name
     * A get function.
     * 
     * @return string
     */
    public function get_nuvei_glob_attr_name() {
        return $this->nuvei_glob_attr_name;
    }
    
	/**
	 * Function update_order
	 * 
	 * @return array
	 */
	private function update_order() {
		if (!session_id()) {
			session_start();
		}
		
		$this->create_log(
			isset($_SESSION['nuvei_last_open_order_details']) ? $_SESSION['nuvei_last_open_order_details'] : '',
			'update_order() - session[nuvei_last_open_order_details]'
		);
		
		if (
			empty($_SESSION['nuvei_last_open_order_details'])
			|| empty($_SESSION['nuvei_last_open_order_details']['sessionToken'])
			|| empty($_SESSION['nuvei_last_open_order_details']['orderId'])
			|| empty($_SESSION['nuvei_last_open_order_details']['clientRequestId'])
		) {
			$this->create_log('update_order() - Missing last Order session data.');
			
			return array('status' => 'ERROR');
		}
		
		global $woocommerce;
		
		$cart        = $woocommerce->cart;
		$time        = gmdate('YmdHis');
		$cart_amount = (string) number_format((float) $cart->total, 2, '.', '');
		$cart_items  = array();
		$addresses   = $this->get_order_addresses();

		// get items
		foreach ($cart->get_cart() as $item_id => $item) {
			$cart_items[$item['product_id']] = array(
				'quantity'  => $item['quantity'],
				'price'     => get_post_meta($item['product_id'] , '_price', true),
			);
		}

		// create Order upgrade
		$params = array(
			'sessionToken'		=> $_SESSION['nuvei_last_open_order_details']['sessionToken'],
			'orderId'			=> $_SESSION['nuvei_last_open_order_details']['orderId'],
			'merchantId'		=> $this->sc_get_setting('merchantId'),
			'merchantSiteId'	=> $this->sc_get_setting('merchantSiteId'),
			'clientRequestId'	=> $cart->nuvei_last_open_order_details['clientRequestId'],
			'currency'			=> get_woocommerce_currency(),
			'amount'			=> $cart_amount,
			'billingAddress'	=> isset($addresses['billingAddress'])
				? $addresses['billingAddress'] : array(),
			'shippingAddress'	=> isset($addresses['shippingAddress'])
				? $addresses['shippingAddress'] : array(),
			'timeStamp'			=> $time,
			
			'items'				=> array(
				array(
					'name'		=> 'wc_order',
					'price'		=> $cart_amount,
					'quantity'	=> 1
				)
			),
			
			'merchantDetails'   => array(
				'customField2' => json_encode($cart_items), // items
				'customField3' => time(), // update time
			),
		);
		
		$params['userDetails'] = $params['billingAddress'];
		
		$params['checksum'] = hash(
			$this->sc_get_setting('hash_type'),
			$this->sc_get_setting('merchantId') . $this->sc_get_setting('merchantSiteId') . $params['clientRequestId']
				. $params['amount'] . $params['currency'] . $time . $this->sc_get_setting('secret')
		);
		
		$resp = $this->callRestApi('updateOrder', $params);
		
		# Success
		if (!empty($resp['status']) && 'SUCCESS' == $resp['status']) {
			$_SESSION['nuvei_last_open_order_details']['amount']					= $cart_amount;
			$_SESSION['nuvei_last_open_order_details']['merchantDetails']			= $params['merchantDetails'];
			$_SESSION['nuvei_last_open_order_details']['billingAddress']['country']	= $params['billingAddress']['country'];
			
			return array_merge($resp, $params);
		}
		
		$this->create_log('update_order() - Order update was not successful.');

		return array('status' => 'ERROR');
	}
	
	/**
	 * Function get_order_addresses
	 * 
	 * Help function to generate Billing and Shipping details.
	 * 
	 * @global Woocommerce $woocommerce
	 * @return array
	 */
	private function get_order_addresses() {
		global $woocommerce;
		
		$form_params = array();
		$addresses   = array();
		$cart        = $woocommerce->cart;
		
		if (!empty($this->get_param('scFormData'))) {
			parse_str($this->get_param('scFormData'), $form_params); 
		}
		
		# set params
		// billing
		$bfn = $this->get_param('billing_first_name', 'string', '', $form_params);
		if (empty($bfn)) {
			$bfn = $cart->get_customer()->get_billing_first_name();
		}
		
		$bln = $this->get_param('billing_last_name', 'string', '', $form_params);
		if (empty($bln)) {
			$bln = $cart->get_customer()->get_billing_last_name();
		}
		
		$ba = $this->get_param('billing_address_1', 'string', '', $form_params)
			. ' ' . $this->get_param('billing_address_1', 'string', '', $form_params);
		if (empty($ba)) {
			$ba = $cart->get_customer()->get_billing_address() . ' '
				. $cart->get_customer()->get_billing_address_2();
		}
		
		$bp = $this->get_param('billing_phone', 'string', '', $form_params);
		if (empty($bp)) {
			$bp = $cart->get_customer()->get_billing_phone();
		}
		
		$bz = $this->get_param('billing_postcode', 'int', 0, $form_params);
		if (empty($bz)) {
			$bz = $cart->get_customer()->get_billing_postcode();
		}
		
		$bc = $this->get_param('billing_city', 'string', '', $form_params);
		if (empty($bc)) {
			$bc = $cart->get_customer()->get_billing_city();
		}
		
		$bcn = $this->get_param('billing_country', 'string', '', $form_params);
		if (empty($bcn)) {
			$bcn = $cart->get_customer()->get_billing_country();
		}
		
		$be = $this->get_param('billing_email', 'mail', '', $form_params);
		if (empty($be)) {
			$be = $cart->get_customer()->get_billing_email();
		}
		
		// shipping
		$sfn = $this->get_param('shipping_first_name', 'string', '', $form_params);
		if (empty($sfn)) {
			$sfn = $cart->get_customer()->get_shipping_first_name();
		}
		
		$sln = $this->get_param('shipping_last_name', 'string', '', $form_params);
		if (empty($sln)) {
			$sln = $cart->get_customer()->get_shipping_last_name();
		}
		
		$sa = $this->get_param('shipping_address_1', 'string', '', $form_params)
			. ' ' . $this->get_param('shipping_address_2', 'string', '', $form_params);
		if (empty($sa)) {
			$sa = $cart->get_customer()->get_shipping_address() . ' '
				. $cart->get_customer()->get_shipping_address_2();
		}
		
		$sz = $this->get_param('shipping_postcode', 'string', '', $form_params);
		if (empty($sz)) {
			$sz = $cart->get_customer()->get_shipping_postcode();
		}
		
		$sc = $this->get_param('shipping_city', 'string', '', $form_params);
		if (empty($sc)) {
			$sc = $cart->get_customer()->get_shipping_city();
		}
		
		$scn = $this->get_param('shipping_country', 'string', '', $form_params);
		if (empty($scn)) {
			$scn = $cart->get_customer()->get_shipping_country();
		}
		# set params END
		
		return array(
			'billingAddress'	=> array(
				'firstName'	=> $bfn,
				'lastName'	=> $bln,
				'address'	=> $ba,
				'phone'		=> $bp,
				'zip'		=> $bz,
				'city'		=> $bc,
				'country'	=> $bcn,
				'email'		=> $be,
			),
			
			'shippingAddress'	=> array(
				'firstName'	=> $sfn,
				'lastName'  => $sln,
				'address'   => $sa,
				'zip'       => $sz,
				'city'      => $sc,
				'country'   => $scn,
			),
		);
	}
	
	/**
	 * Function callRestApi
	 * Call Rest Api from here for easy log of details
	 * 
	 * @param string $method - method_name.do
	 * @param array $params
	 * 
	 * @return mixed $resp
	 */
	private function callRestApi( $method, $params) {
		$resp = '';
		$url  = $this->getEndPointBase() . $method . '.do';
		
		if (empty($method)) {
			$this->create_log($url, 'callRestApi() Error - the passed method can not be empty.');
			return false;
		}
		
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			$this->create_log($url, 'call_rest_api() Error - the passed url is not valid.');
			return false;
		}
		
		if (!is_array($params)) {
			$this->create_log($params, 'call_rest_api() Error - the passed params parameter is not array ot object.');
			return false;
		}
		
		$this->create_log(
			array(
				'REST API URL'	=> $url,
				'params'		=> $params
			),
			'REST API call (before validation)'
		);

		$resp		= SC_CLASS::call_rest_api($url, $params);
		$resp_array	= json_decode($resp, true);

		$this->create_log(!empty($resp_array) ? $resp_array : $resp, 'Rest API response');
		
		return $resp_array;
	}
	
	private function sc_get_order_data( $TransactionID) {
		global $wpdb;
		
		$res = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = %s AND meta_value = %s ;",
				SC_TRANS_ID,
				$TransactionID
			)
		);
				
		return $res;
	}
	
	/**
	 * Function change_order_status
	 * Change the status of the order.
	 *
	 * @param int $order_id
	 * @param string $status
	 * @param string $transactionType
	 * @param array $res_args - we must use $res_args instead $_REQUEST, if not empty
	 */
	private function change_order_status( $order_id, $req_status, $transactionType, $res_args = array()) {
		$this->create_log(
			'Order ' . $order_id . ' was ' . $req_status,
			'WC_SC change_order_status()'
		);
		
		$gw_data = '<br/>' . __('Status: ', 'nuvei_woocommerce') . $req_status
			. '<br/>' . __('PPP Transaction ID: ', 'nuvei_woocommerce') . $this->get_param('PPP_TransactionID', 'int')
			. ',<br/>' . __('Transaction Type: ', 'nuvei_woocommerce') . $transactionType
			. ',<br/>' . __('Transaction ID: ', 'nuvei_woocommerce') . $this->get_param('TransactionID', 'int')
			. ',<br/>' . __('Payment Method: ', 'nuvei_woocommerce') . $this->get_param('payment_method');
		
		$message = '';
		$status  = $this->sc_order->get_status();
		
		switch ($req_status) {
			case 'CANCELED':
				$message            = __('Your action was Canceld.', 'nuvei_woocommerce') . $gw_data;
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
					$refunds         = json_decode($this->sc_order->get_meta('_sc_refunds'), true);
					$currency_code   = $this->sc_order->get_currency();
					$currency_symbol = get_woocommerce_currency_symbol( $currency_code );
					
					if (isset($refunds[$this->get_param('TransactionID', 'int')]['refund_amount'])) {
						$message .= '<br/>' . __('Refund Amount') . ': ' . $currency_symbol
							. number_format($refunds[$this->get_param('TransactionID', 'int')]['refund_amount'], 2, '.', '')
							. '<br/>' . __('Refund') . ' #' . $refunds[$this->get_param('TransactionID', 'int')]['wc_id'];
					}
					
					if (round($this->sc_order->get_total(), 2) <= $this->sc_sum_order_refunds()) {
						$status = 'refunded';
					}
				} elseif ('Auth' === $transactionType) {
					$message = __('The amount has been authorized and wait for Settle.', 'nuvei_woocommerce') . $gw_data;
					$status  = 'pending';
				} elseif (in_array($transactionType, array('Settle', 'Sale'), true)) {
					$message = __('The amount has been authorized and captured by ', 'nuvei_woocommerce') . SC_GATEWAY_TITLE . '.' . $gw_data;
					$status  = 'completed';
					
					$this->sc_order->payment_complete($order_id);
				}
				
				// check for correct amount
				if (in_array($transactionType, array('Auth', 'Sale'), true)) {
					$order_amount = round(floatval($this->sc_order->get_total()), 2);
					$dmn_amount   = round($this->get_param('totalAmount', 'float'), 2);
					
					if ($order_amount !== $dmn_amount) {
						$message .= '<hr/><b>' . __('Payment ERROR!', 'nuvei_woocommerce') . '</b> ' 
							. $dmn_amount . ' ' . $this->get_param('currency')
							. ' ' . __('paid instead of', 'nuvei_woocommerce') . ' ' . $order_amount
							. ' ' . $this->sc_order->get_currency() . '!';
						
						$status = 'failed';
						
						$this->create_log(
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
				$reason = ',<br/>' . __('Reason: ', 'nuvei_woocommerce');
				if ('' != $this->get_param('reason')) {
					$reason .= $this->get_param('reason');
				} elseif ('' != $this->get_param('Reason')) {
					$reason .= $this->get_param('Reason');
				}
				
				$message = __('Transaction failed.', 'nuvei_woocommerce')
					. '<br/>' . __('Error code: ', 'nuvei_woocommerce') . $this->get_param('ErrCode')
					. '<br/>' . __('Message: ', 'nuvei_woocommerce') . $this->get_param('message') . $reason . $gw_data;
				
				// do not change status
				if ('Void' === $transactionType) {
					$message = 'DMN message: Your Void request fail.';
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
		
		$this->create_log($status, 'Status of Order #' . $order_id . ' was set to');
	}
	
	private function formatLocation( $locale) {
		switch ($locale) {
			case 'de_DE':
				return 'de';
				
			case 'zh_CN':
				return 'zh';
				
			case 'en_GB':
			default:
				return 'en';
		}
	}
	
	/**
	 * Function save_update_order_numbers
	 * Save or update order AuthCode and TransactionID on status change.
	 */
	private function save_update_order_numbers() {
		// save or update AuthCode and GW Transaction ID
		$auth_code = $this->get_param('AuthCode', 'int');
		if (!empty($auth_code)) {
			$this->sc_order->update_meta_data(SC_AUTH_CODE_KEY, $auth_code);
		}

		$transaction_id = $this->get_param('TransactionID', 'int');
		if (!empty($transaction_id)) {
			$this->sc_order->update_meta_data(SC_TRANS_ID, $transaction_id);
		}
		
		$pm = $this->get_param('payment_method');
		if (!empty($pm)) {
			$this->sc_order->update_meta_data(SC_PAYMENT_METHOD, $pm);
		}

		$tr_type = $this->get_param('transactionType');
		if (!empty($tr_type)) {
			$this->sc_order->update_meta_data(SC_RESP_TRANS_TYPE, $tr_type);
		}
		
		//      $this->create_log(
		//          array(
		//              'order id' => $this->sc_order->get_id(),
		//              'AuthCode' => $auth_code,
		//              'TransactionID' => $transaction_id,
		//              'payment_method' => $pm,
		//              'transactionType' => $tr_type,
		//          ),
		//          'Order to update fields'
		//      );
		
		$this->sc_order->save();
	}
	
	private function getEndPointBase() {
		if ('yes' == $this->sc_get_setting('test')) {
			return 'https://ppp-test.safecharge.com/ppp/api/v1/';
		}
		
		return 'https://secure.safecharge.com/ppp/api/v1/';
	}
	
	/**
	 * Function sc_get_setting
	 *
	 * @param string $key - the key we are search for
	 * @param mixed $default - the default value if no setting found
	 */
	private function sc_get_setting( $key, $default = 0) {
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
		$order_request_time	= $this->get_param('customField3', 'int'); // time of create/update order
		
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

			$res = $this->sc_get_order_data($trans_id);

			if (empty($res[0]->post_id)) {
				sleep(3);
			}
		} while ($tries <= $max_tries && empty($res[0]->post_id));

		if (empty($res[0]->post_id)) {
			$this->create_log(
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
		$ref_tr_id	= $this->get_param('TransactionID', 'int');
		
		$this->is_order_valid($order_id);
		
		if ( !in_array($this->sc_order->get_status(), array('completed', 'processing')) ) {
			$this->create_log(
				$this->sc_order->get_status(),
				'DMN Refund Error - the Order status does not allow refunds, the status is:'
			);

			echo wp_json_encode(array('DMN Refund Error - the Order status does not allow refunds.'));
			exit;
		}
		
		// there is chance of slow saving of meta data (in create_refund_record()), so let's wait
		do {
			$refunds = json_decode($this->sc_order->get_meta('_sc_refunds'), true);
			$this->create_log('create_refund_record() Wait for Refund meta data.');
			
			sleep(3);
			$tries++;
		} while (empty($refunds[$ref_tr_id]) && $tries < 5);
		
		$this->create_log($refunds, 'create_refund_record() Saved refunds for Order #' . $order_id);
		
		// check for DMN trans ID in the refunds
		if (
			!empty($refunds[$ref_tr_id])
			&& 'pending' == $refunds[$ref_tr_id]['status']
			&& !empty($refunds[$ref_tr_id]['refund_amount'])
		) {
			$ref_amount = $refunds[$ref_tr_id]['refund_amount'];
		} elseif (0 == $ref_amount && strpos($this->get_param('clientRequestId'), 'gwp_') !== false) {
			// in case of CPanel refund - add Refund meta data here
			$ref_amount = $this->get_param('totalAmount', 'float');
		}
		
		if (0 == $ref_amount) {
			$this->create_log('create_refund_record() Refund Amount is 0, do not create Refund in WooCommerce.');
			
			return;
		}
		
		$refund = wc_create_refund(array(
			'amount'	=> round(floatval($ref_amount), 2),
			'order_id'	=> $order_id,
		));
		
		if (is_a($refund, 'WP_Error')) {
			$this->create_log($refund, 'create_refund_record() - the Refund process in WC returns error: ');
			
			echo wp_json_encode(array('create_refund_record() - the Refund process in WC returns error.'));
			exit;
		}
		
		$this->save_refund_meta_data(
			$this->get_param('TransactionID'),
			$ref_amount,
			'approved',
			$refund->get_id()
		);

		//      $refunds[$ref_tr_id]['status'] = 'approved';
		//      $refunds[$ref_tr_id]['wc_id']  = $refund->get_id();
		//      
		//      $this->sc_order->update_meta_data('_sc_refunds', json_encode($refunds));
		//      $this->sc_order->save();

		return true;
	}
	
	private function sc_sum_order_refunds() {
		$refunds = json_decode($this->sc_order->get_meta('_sc_refunds'), true);
		$sum     = 0;
		
		if (!empty($refunds[$this->get_param('TransactionID', 'int')])) {
			$this->create_log($refunds, 'Order Refunds');
			
			foreach ($refunds as $data) {
				if ('approved' == $data['status']) {
					$sum += $data['refund_amount'];
				}
			}
		}
		
		$this->create_log($sum, 'Sum of refunds for an Order.');
		return round($sum, 2);
	}
	
	private function save_refund_meta_data( $trans_id, $ref_amount, $status = '', $wc_id = 0) {
		$refunds = json_decode($this->sc_order->get_meta('_sc_refunds'), true);
		
		if (empty($refunds)) {
			$refunds = array();
		}
		
		//      $this->create_log($refunds, 'save_refund_meta_data(): Saved Refunds before the current one.');
		
		// add the new refund
		$refunds[$trans_id] = array(
			'refund_amount'	=> round((float) $ref_amount, 2),
			'status'		=> empty($status) ? 'pending' : $status
		);
		
		if (0 < $wc_id) {
			$refunds[$trans_id]['wc_id'] = $wc_id;
		}

		$this->sc_order->update_meta_data('_sc_refunds', json_encode($refunds));
		$order_id = $this->sc_order->save();
		
		$this->create_log('save_refund_meta_data() Saved Refund with Tr ID ' . $trans_id);
		
		return $order_id;
	}
	
	/**
	 * Function set_cuid
	 * 
	 * Set client unique id.
	 * We change it only for Sandbox (test) mode.
	 * 
	 * @param int $order_id
	 * @return int|string
	 */
	private function set_cuid( $order_id) {
		if ('yes' != $this->test) {
			return (int) $order_id;
		}
		
		return $order_id . '_' . time() . $this->cuid_postfix;
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
		$clientUniqueId = $this->get_param('clientUniqueId');
		
		if ('yes' != $this->test) {
			return $clientUniqueId;
		}
		
		if (strpos($clientUniqueId, $this->cuid_postfix) !== false) {
			return current(explode('_', $clientUniqueId));
		}
		
		return $clientUniqueId;
	}
	
}
