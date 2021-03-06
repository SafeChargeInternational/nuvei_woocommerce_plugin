<?php 
/**
 * Plugin Name: Nuvei Payments
 * Plugin URI: https://github.com/SafeChargeInternational/safecharge_woocommerce_plugin
 * Description: Nuvei Gateway for WooCommerce
 * Version: 4.1.5
 * Author: Nuvei
 * Author URI: https://nuvei.com
 * Text Domain: nuvei_woocommerce
 * Domain Path: /languages
 * Require at least: 4.7
 * Tested up to: 5.7.2
 * WC requires at least: 3.0
 * WC tested up to: 5.3.0
*/

defined('ABSPATH') || die('die');

if ( ! defined( 'NUVEI_PLUGIN_FILE' ) ) {
	define( 'NUVEI_PLUGIN_FILE', __FILE__ );
}

require_once 'config.php';
require_once 'includes' . DIRECTORY_SEPARATOR . 'class-nuvei-autoloader.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$wc_nuvei = null;

add_action('admin_init', 'nuvei_admin_init');
add_filter('woocommerce_payment_gateways', 'nuvei_add_gateway');
add_action('plugins_loaded', 'nuvei_init', 0);

function nuvei_admin_init() {
	// check if there is the version with "nuvei" in the name of directory, in this case deactivate the current plugin
	$path_to_nuvei_plugin = dirname(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR
		. 'nuvei_woocommerce_plugin' . DIRECTORY_SEPARATOR . 'index.php';
	
	if (strpos(basename(dirname(__FILE__)), 'safecharge') !== false && file_exists($path_to_nuvei_plugin)) {
		deactivate_plugins(plugin_basename( __FILE__ ));
	}
	// check if there is the version with "nuvei" in the name of directory, in this case deactivate the current plugin END
	
	// check in GIT for new version
	$file         = dirname(__FILE__) . '/tmp/nuvei-latest-version.json';
	$plugin_data  = get_plugin_data(__FILE__);
	$curr_version = (int) str_replace('.', '', $plugin_data['Version']);
	$git_version  = 0;
	$date_check   = 0;
	
	if (!file_exists($file)) {
		$data = nuvei_get_file_form_git($file);
		
		if (!empty($data['git_v'])) {
			$git_version = $data['git_v'];
		}
		if (!empty($data['date'])) {
			$date_check = $data['date'];
		}
	}
	
	// read file if need to
	if (0 == $date_check || 0 == $git_version) {
		$version_file_data = json_decode(file_get_contents($file), true);
		
		if (!empty($version_file_data['date'])) {
			$date_check = $version_file_data['date'];
		}
		if (!empty($version_file_data['git_v'])) {
			$git_version = $version_file_data['git_v'];
		}
	}
	
	// check file date and get new file if current one is more than a week old
	if (strtotime('-1 Week') > strtotime($date_check)) {
		$data = nuvei_get_file_form_git($file);
		
		if (!empty($data['git_v'])) {
			$git_version = $data['git_v'];
		}
		if (!empty($data['date'])) {
			$date_check = $data['date'];
		}
	}
	
	// compare versions and show message if need to
	if ($git_version > $curr_version) {
		add_action('admin_notices', function() {
			$class     = 'notice notice-info is-dismissible';
			$url       = 'https://github.com/SafeChargeInternational/nuvei_woocommerce_plugin/blob/main/changelog.txt';
			$message_1 = __('There is a new version of Nuvei Plugin available.', 'nuvei_woocommerce' );
			$message_2 = __('View version details.', 'nuvei_woocommerce' );
			
			printf(
				'<div class="%1$s"><p>%2$s <a href="%3$s" target="_blank">%4$s</a></p></div>',
				esc_attr($class),
				esc_html($message_1),
				esc_url($url),
				esc_html($message_2)
			);
		});
	}
	// check in GIT for new version END
}

function nuvei_init() {
	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}
	
	global $wc_nuvei;
	$wc_nuvei = new Nuvei_Gateway();
	
	load_plugin_textdomain(
		'nuvei_woocommerce',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages/'
	);
	
	add_action('init', 'nuvei_enqueue');
	
	// load WC styles
	add_filter('woocommerce_enqueue_styles', 'nuvei_load_styles_scripts');
	
	// add admin style
	add_filter('admin_enqueue_scripts', 'nuvei_load_admin_styles_scripts');
	
	// add void and/or settle buttons to completed orders, we check in the method is this order made via SC paygate
	add_action('woocommerce_order_item_add_action_buttons', 'nuvei_add_buttons');
	
	// handle custom Ajax calls
	add_action('wp_ajax_sc-ajax-action', 'nuvei_ajax_action');
	add_action('wp_ajax_nopriv_sc-ajax-action', 'nuvei_ajax_action');
	
	// add the APMs step with the custom merchant style, if any
	add_action( 'woocommerce_checkout_after_order_review', array($wc_nuvei, 'add_apms_step'), 10, 1 );
	
	// if validation success get order details
	add_action('woocommerce_after_checkout_validation', function( $data, $errors) {
		global $wc_nuvei;
		
		//        Nuvei_Logger::write($errors->errors, 'woocommerce_after_checkout_validation errors');
		//        Nuvei_Logger::write($_POST, 'woocommerce_after_checkout_validation post params');
		
		if (empty( $errors->errors ) 
			&& NUVEI_GATEWAY_NAME == $data['payment_method'] 
			&& empty(Nuvei_Http::get_param('sc_payment_method'))
		) {
			$wc_nuvei->get_payment_methods();
		}
	}, 9999, 2);
	
	// use this to change button text, because of the cache the jQuery not always works
	add_filter('woocommerce_order_button_text', 'nuvei_edit_order_buttons');
	
	// those actions are valid only when the plugin is enabled
	$plugin_enabled = isset($wc_nuvei->settings['enabled']) ? $wc_nuvei->settings['enabled'] : 'no';
	
	if ('yes' == $plugin_enabled) {
		// for WPML plugin
		if (
			is_plugin_active('sitepress-multilingual-cms' . DIRECTORY_SEPARATOR . 'sitepress.php')
			&& 'yes' == $wc_nuvei->settings['use_wpml_thanks_page']
		) {
			add_filter('woocommerce_get_checkout_order_received_url', 'nuvei_wpml_thank_you_page', 10, 2);
		}

		// if the merchant needs to rewrite the DMN URL
		if (isset($wc_nuvei->settings['rewrite_dmn']) && 'yes' == $wc_nuvei->settings['rewrite_dmn']) {
			add_action('template_redirect', 'nuvei_rewrite_return_url'); // need WC_SC
		}
		
		// For the custom column in the Order list
		add_action( 'manage_shop_order_posts_custom_column', 'nuvei_fill_custom_column' );
		// for the Store > My Account > Orders list
		add_action( 'woocommerce_my_account_my_orders_column_order-number', 'nuvei_edit_my_account_orders_col' );
	}
	
	// change Thank-you page title and text
	if ('error' === strtolower($wc_nuvei->get_request_status())) {
		add_filter('the_title', function ( $title, $id) {
			if (
				function_exists('is_order_received_page')
				&& is_order_received_page()
				&& get_the_ID() === $id
			) {
				$title = esc_html__('Order error', 'nuvei_woocommerce');
			}

			return $title;
		}, 10, 2);
		
		add_filter(
			'woocommerce_thankyou_order_received_text',
		
			function ( $str, $order) {
				return esc_html__(' There is an error with your order. Please check your Order status for more information.', 'nuvei_woocommerce');
			}, 10, 2);
	} elseif ('canceled' === strtolower($wc_nuvei->get_request_status())) {
		add_filter('the_title', function ( $title, $id) {
			if (
				function_exists('is_order_received_page')
				&& is_order_received_page()
				&& get_the_ID() === $id
			) {
				$title = esc_html__('Order canceled', 'nuvei_woocommerce');
			}

			return $title;
		}, 10, 2);
		
		add_filter('woocommerce_thankyou_order_received_text', function ( $str, $order) {
			return esc_html__('Please, check the order for details!', 'nuvei_woocommerce');
		}, 10, 2);
	}
	
	add_filter('woocommerce_pay_order_after_submit', 'nuvei_user_orders', 10, 2);
	
	if (!empty($_GET['sc_msg'])) {
		add_filter('woocommerce_before_cart', 'nuvei_show_message_on_cart', 10, 2);
	}
	
	# Payment Plans taxonomies
	// extend Term form to add meta data
	add_action('pa_' . Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME) . '_add_form_fields', 'nuvei_add_term_fields_form', 10, 2);
	// update Terms' meta data form
	add_action('pa_' . Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME) . '_edit_form_fields', 'nuvei_edit_term_meta_form', 10, 2);
	// hook to catch our meta data and save it
	add_action( 'created_pa_' . Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME), 'nuvei_save_term_meta', 10, 2 );
	// eit Term meta data
	add_action( 'edited_pa_' . Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME), 'nuvei_edit_term_meta', 10, 2 );
	
	// before add a product to the cart
	add_filter( 'woocommerce_add_to_cart_validation', array($wc_nuvei, 'add_to_cart_validation'), 10, 3 );
}

/**
 * Function nuvei_ajax_action
 * Main function for the Ajax requests.
 */
function nuvei_ajax_action() {
	if (!check_ajax_referer('sc-security-nonce', 'security')) {
		wp_send_json_error(__('Invalid security token sent.'));
		wp_die('Invalid security token sent');
	}
	
	global $wc_nuvei;
	
	if (empty($wc_nuvei->test)) {
		wp_send_json_error(__('Invalid site mode.'));
		wp_die('Invalid site mode.');
	}
	
	$order_id = Nuvei_Http::get_param('orderId', 'int');
	
	$payment_method_sc = '';
	if (!empty(Nuvei_Http::get_param('payment_method_sc'))) {
		$payment_method_sc = sanitize_text_field(Nuvei_Http::get_param('payment_method_sc'));
	}

	# recognize the action:
	// Void (Cancel)
	if (Nuvei_Http::get_param('cancelOrder', 'int') == 1 && $order_id > 0) {
		$wc_nuvei->create_settle_void(sanitize_text_field($order_id), 'void');
	}

	// Settle
	if (Nuvei_Http::get_param('settleOrder', 'int') == 1 && $order_id > 0) {
		$wc_nuvei->create_settle_void(sanitize_text_field($order_id), 'settle');
	}
	
	// Refund
	if ( isset($_POST['refAmount']) ) {
		$wc_nuvei->create_refund_request(Nuvei_Http::get_param('postId', 'int'), Nuvei_Http::get_param('refAmount', 'float'));
	}
	
	// when we use the REST - Open order and get APMs
	if (in_array(Nuvei_Http::get_param('sc_request'), array('OpenOrder', 'updateOrder'))) {
		$wc_nuvei->open_order(true);
	}
	
	// delete UPO
	if (Nuvei_Http::get_param('scUpoId', 'int') > 0) {
		$wc_nuvei->delete_user_upo();
	}
	
	// when Reorder
	if (Nuvei_Http::get_param('sc_request') == 'scReorder') {
		$wc_nuvei->reorder();
	}
	
	// download Subscriptions Plans
	if (Nuvei_Http::get_param('downloadPlans', 'int') == 1) {
		$wc_nuvei->download_subscr_pans();
	}
	
	wp_send_json_error(__('Not recognized Ajax call.', 'nuvei_woocommerce'));
	wp_die();
}

/**
* Add the Gateway to WooCommerce
**/
function nuvei_add_gateway( $methods) {
	$methods[] = 'Nuvei_Gateway'; // get the name of the Gateway Class
	return $methods;
}

# Load Styles and Scripts
/**
 * Loads public styles and scripts
 * 
 * @global Nuvei_Gateway $wc_nuvei
 * @global type $wpdb
 * 
 * @param type $styles
 */
function nuvei_load_styles_scripts( $styles) {
	global $wc_nuvei;
	global $wpdb;
	
	$plugin_url = plugin_dir_url(__FILE__);
	
	if (
		( isset($_SERVER['HTTPS']) && 'on' == $_SERVER['HTTPS'] )
		&& ( isset($_SERVER['REQUEST_SCHEME']) && 'https' == $_SERVER['REQUEST_SCHEME'] )
	) {
		if (strpos($plugin_url, 'https') === false) {
			$plugin_url = str_replace('http:', 'https:', $plugin_url);
		}
	}
		
	// novo style
	wp_register_style(
		'nuvei_style',
		$plugin_url . 'assets/css/nuvei_style.css',
		'',
		'2.2',
		'all'
	);
	wp_enqueue_style('nuvei_style');
	
	// WebSDK URL for integration and production
	wp_register_script(
		'sc_websdk',
		'https://cdn.safecharge.com/safecharge_resources/v1/websdk/safecharge.js',
		array('jquery'),
		'1'
	);
	wp_enqueue_script('sc_websdk');

	// main JS
	wp_register_script(
		'nuvei_js_public',
		$plugin_url . 'assets/js/nuvei_public.js',
		array('jquery'),
		'1.5'
	);
	
	// reorder js
	wp_register_script(
		'nuvei_js_reorder',
		$plugin_url . 'assets/js/nuvei_reorder.js',
		array('jquery'),
		'1'
	);
	wp_enqueue_script('nuvei_js_reorder');
	
	// get selected WC price separators
	$wcThSep  = '';
	$wcDecSep = '';
	
	$res = $wpdb->get_results(
		'SELECT option_name, option_value '
			. "FROM {$wpdb->prefix}options "
			. "WHERE option_name LIKE 'woocommerce%_sep' ;",
		ARRAY_N
	);
			
	if (!empty($res)) {
		foreach ($res as $row) {
			if (false != strpos($row[0], 'thousand_sep') && !empty($row[1])) {
				$wcThSep = $row[1];
			}

			if (false != strpos($row[0], 'decimal_sep') && !empty($row[1])) {
				$wcDecSep = $row[1];
			}
		}
	}
	
	// put translations here into the array
	wp_localize_script(
		'nuvei_js_public',
		'scTrans',
		array(
			'ajaxurl'               => admin_url('admin-ajax.php'),
			'security'              => wp_create_nonce('sc-security-nonce'),
			'webMasterId'           => 'WooCommerce ' . WOOCOMMERCE_VERSION,
			'sourceApplication'     => NUVEI_SOURCE_APPLICATION,
			'plugin_dir_url'        => plugin_dir_url(__FILE__),
			'wcThSep'               => $wcThSep,
			'wcDecSep'              => $wcDecSep,
			'useUpos'               => $wc_nuvei->can_use_upos(),
			'showApmsNames'         => $wc_nuvei->show_apms_names(),
			'isUserLogged'          => is_user_logged_in() ? 1 : 0,
			'paymentGatewayName'    => NUVEI_GATEWAY_NAME,
			
			// translations
			'paymentDeclined'	=> __('Your Payment was DECLINED.', 'nuvei_woocommerce'),
			'paymentError'      => __('Error with your Payment.', 'nuvei_woocommerce'),
			'unexpectedError'	=> __('Unexpected error.', 'nuvei_woocommerce'),
			'choosePM'          => __('Please, choose payment method, and fill all fields!', 'nuvei_woocommerce'),
			'fillFields'        => __('Please fill all fields marked with * !', 'nuvei_woocommerce'),
			'errorWithPMs'      => __('Error when try to get the Payment Methods. Please try again later or use different Payment Option!', 'nuvei_woocommerce'),
			'errorWithSToken'	=> __('Error when try to get the Session Token.', 'nuvei_woocommerce'),
			'goBack'            => __('Go back', 'nuvei_woocommerce'),
			'CCNameIsEmpty'     => __('Card Holder Name is empty.', 'nuvei_woocommerce'),
			'CCNumError'        => __('Card Number is empty or wrong.', 'nuvei_woocommerce'),
			'CCExpDateError'    => __('Card Expiry Date is not correct.', 'nuvei_woocommerce'),
			'CCCvcError'        => __('Card CVC is not correct.', 'nuvei_woocommerce'),
			'AskDeleteUpo'      => __('Do you want to delete this UPO?', 'nuvei_woocommerce'),
			'ConfirmSaveUpo'	=> __('Would you like Nuvei to keep the selected payment method as Preferred?', 'nuvei_woocommerce'),
			'RequestFail'       => __('Request fail.', 'nuvei_woocommerce'),
			'ApplePayError'     => __('Unexpected session error.', 'nuvei_woocommerce'),
			'TryAgainLater'     => __('Please try again later!', 'nuvei_woocommerce'),
			'TryAnotherPM'      => __('Please try another payment method!', 'nuvei_woocommerce'),
		)
	);

	// connect the translations with some of the JS files
	wp_enqueue_script('nuvei_js_public');
	
	return $styles;
}

/**
 * Loads admin styles and scripts
 * 
 * @param string $hook
 */
function nuvei_load_admin_styles_scripts( $hook) {
	$plugin_url = plugin_dir_url(__FILE__);
	
	if ( 'post.php' == $hook ) {
		wp_register_style(
			'nuvei_admin_style',
			$plugin_url . 'assets/css/nuvei_admin_style.css',
			'',
			1.1,
			'all'
		);
		wp_enqueue_style('nuvei_admin_style');
	}

	// main JS
	wp_register_script(
		'nuvei_js_admin',
		$plugin_url . 'assets/js/nuvei_admin.js',
		array('jquery'),
		'1'
	);

	$nuvei_plans_path       = plugin_dir_path(__FILE__) . '/tmp/sc_plans.json';
	$sc_plans_last_mod_time = '';
	$plans_list             = array();

	if (is_readable($nuvei_plans_path)) { 
		$sc_plans_last_mod_time = gmdate('Y-m-d H:i:s', filemtime($nuvei_plans_path));
		$plans_list             = stripslashes(file_get_contents($nuvei_plans_path));
	}

	// put translations here into the array
	wp_localize_script(
		'nuvei_js_admin',
		'scTrans',
		array(
			'ajaxurl'				=> admin_url('admin-ajax.php'),
			'security'				=> wp_create_nonce('sc-security-nonce'),
			'scPlansLastModTime'	=> $sc_plans_last_mod_time,
			'nuveiPaymentPlans'     => $plans_list,

			// translations
			'refundQuestion'		=> __('Are you sure about this Refund?', 'nuvei_woocommerce'),
			'LastDownload'			=> __('Last Download', 'nuvei_woocommerce'),
		)
	);

	wp_enqueue_script('nuvei_js_admin');
}

// first method we come in
function nuvei_enqueue( $hook) {
	global $wc_nuvei;
		
	# DMNs catch
	if (isset($_REQUEST['wc-api']) 
		&& in_array($_REQUEST['wc-api'], array('sc_listener', 'nuvei_listener')) // sc_listener is legacy value
	) {
		add_action('wp_loaded', array($wc_nuvei, 'process_dmns'));
	}
	
	// second checkout step process order
	if (
		isset($_REQUEST['wc-api'])
		&& 'process-order' == $_REQUEST['wc-api']
		&& !empty($_REQUEST['order_id'])
	) {
		$wc_nuvei->process_payment(Nuvei_Http::get_param('order_id', 'int', 0));
	}
}
# Load Styles and Scripts END

function nuvei_add_buttons() {
	global $wc_nuvei;
	
	$order_id = false;
	
	if (!empty($_GET['post'])) {
		$order_id = sanitize_text_field($_GET['post']);
	}
	
	try {
		$order = $wc_nuvei->is_order_valid($order_id, true);
		
		if (!$order) {
			Nuvei_Logger::write('nuvei_add_buttons() - hook activated for not valid Order. Probably an Order created form the Admin.');
			
			return;
		}
		
		$order_status         = strtolower($order->get_status());
		$order_payment_method = $order->get_meta('_paymentMethod');
		$order_refunds        = json_decode($order->get_meta(NUVEI_REFUNDS), true);
		$refunds_exists       = false;
		
		if (!empty($order_refunds) && is_array($order_refunds)) {
			foreach ($order_refunds as $data) {
				if ('approved' == $data['status']) {
					$refunds_exists = true;
					break;
				}
			}
		}
	} catch (Exception $ex) {
		echo '<script type="text/javascript">console.error("'
			. esc_js($ex->getMessage()) . '")</script>';
		exit;
	}
	
	// hide Refund Button
	if (
		!in_array($order_payment_method, array('cc_card', 'dc_card', 'apmgw_expresscheckout'))
		|| 'processing' == $order_status
	) {
		echo '<script type="text/javascript">jQuery(\'.refund-items\').prop("disabled", true);</script>';
	}
	
	// to show SC buttons we must be sure the order is paid via SC Paygate
	if (!$order->get_meta(NUVEI_AUTH_CODE_KEY) || !$order->get_meta(NUVEI_TRANS_ID)) {
		return;
	}
	
	if (in_array($order_status, array('completed', 'pending', 'failed'))) {
		global $wc_nuvei;

		// we do not set this meta anymore, keep it only because of the orders made before v3.5 of the plugin
		$order_has_refund = $order->get_meta(NUVEI_ORDER_HAS_REFUND);
		
		// Show VOID button
		if (
			'cc_card' == $order_payment_method
			/**
			 * Before v3.5 we put a flag on refund - $order_has_refund
			 * since v3.5 we save some of the refund parameters as json in "_sc_refunds" meta data
			 * and do not save $order_has_refund flag anymore
			 */
			&& ( '1' != $order_has_refund && !$refunds_exists )
		) {
			$question = sprintf(
				/* translators: %d is replaced with "decimal" */
				__('Are you sure, you want to Cancel Order #%d?', 'nuvei_woocommerce'),
				$order_id
			);
			
			echo
				'<button id="sc_void_btn" type="button" onclick="settleAndCancelOrder(\''
					. esc_html($question) . '\', \'void\', ' . esc_html($order_id)
					. ')" class="button generate-items">'
					. esc_html__('Void', 'nuvei_woocommerce') . '</button>';
		}
		
		// show SETTLE button ONLY if P3D resonse transaction_type IS Auth
		if ('pending' == $order_status && 'Auth' == $order->get_meta(NUVEI_RESP_TRANS_TYPE)) {
			$question = sprintf(
				/* translators: %d is replaced with "decimal" */
				__('Are you sure, you want to Settle Order #%d?', 'nuvei_woocommerce'),
				$order_id
			);
			
			echo
				'<button id="sc_settle_btn" type="button" onclick="settleAndCancelOrder(\''
					. esc_html($question)
					. '\', \'settle\', \'' . esc_html($order_id) . '\')" class="button generate-items">'
					. esc_html__('Settle', 'nuvei_woocommerce') . '</button>';
		}
		
		// add loading screen
		echo '<div id="custom_loader" class="blockUI blockOverlay" style="height: 100%; position: absolute; top: 0px; width: 100%; z-index: 10; background-color: rgba(255,255,255,0.5); display: none;"></div>';
	}
}

/**
 * Function nuvei_rewrite_return_url
 * When user have problem with white spaces in the URL, it have option to
 * rewrite the return URL and redirect to new one.
 *
 * @global WC_SC $wc_nuvei
 */
function nuvei_rewrite_return_url() {
	if (
		isset($_REQUEST['ppp_status']) && '' != $_REQUEST['ppp_status']
		&& ( !isset($_REQUEST['wc_sc_redirected']) || 0 ==  $_REQUEST['wc_sc_redirected'] )
	) {
		$query_string = '';
		if (isset($_SERVER['QUERY_STRING'])) {
			$query_string = sanitize_text_field($_SERVER['QUERY_STRING']);
		}
		
		$server_protocol = '';
		if (isset($_SERVER['SERVER_PROTOCOL'])) {
			$server_protocol = sanitize_text_field($_SERVER['SERVER_PROTOCOL']);
		}
		
		$http_host = '';
		if (isset($_SERVER['HTTP_HOST'])) {
			$http_host = sanitize_text_field($_SERVER['HTTP_HOST']);
		}
		
		$request_uri = '';
		if (isset($_SERVER['REQUEST_URI'])) {
			$request_uri = sanitize_text_field($_SERVER['REQUEST_URI']);
		}
		
		$new_url = '';
		$host    = ( strpos($server_protocol, 'HTTP/') !== false ? 'http' : 'https' )
			. '://' . $http_host . current(explode('?', $request_uri));
		
		if ('' != $query_string) {
			$new_url = preg_replace('/\+|\s|\%20/', '_', $query_string);
			// put flag the URL was rewrited
			$new_url .= '&wc_sc_redirected=1';
			
			wp_redirect($host . '?' . $new_url);
			exit;
		}
	}
}

/**
 * Fix for WPML plugin "Thank you" page
 *
 * @param string $order_received_url
 * @param WC_Order $order
 * 
 * @return string $order_received_url
 */
function nuvei_wpml_thank_you_page( $order_received_url, $order) {
	$lang_code          = get_post_meta($order->id, 'wpml_language', true);
	$order_received_url = apply_filters('wpml_permalink', $order_received_url, $lang_code);
	
	Nuvei_Logger::write($order_received_url, 'nuvei_wpml_thank_you_page: ');
 
	return $order_received_url;
}

function nuvei_edit_order_buttons() {
	$default_text          = __('Place order', 'woocommerce');
	$sc_continue_text      = __('Continue', 'woocommerce');
	$chosen_payment_method = WC()->session->get('chosen_payment_method');
	
	// save default text into button attribute ?><script>
		(function($){
			$('#place_order')
				.attr('data-default-text', '<?php echo esc_attr($default_text); ?>')
				.attr('data-sc-text', '<?php echo esc_attr($sc_continue_text); ?>');
		})(jQuery);
	</script>
	<?php

	// check for 'sc' also, because of the older Orders
	if (in_array($chosen_payment_method, array(NUVEI_GATEWAY_NAME, 'sc'))) {
		return $sc_continue_text;
	}

	return $default_text;
}

function nuvei_change_title_order_received( $title, $id) {
	if (
		function_exists('is_order_received_page')
		&& is_order_received_page()
		&& get_the_ID() === $id
	) {
		$title = esc_html__('Order error', 'nuvei_woocommerce');
	}
	
	return $title;
}

/**
 * Call this on Store when the logged user is in My Account section
 * 
 * @global type $wp
 */
function nuvei_user_orders() {
	global $wp;
	
	$order     = wc_get_order($wp->query_vars['order-pay']);
	$order_key = $order->get_order_key();
	
	// check for 'sc' also, because of the older Orders
	if (!in_array($order->get_payment_method(), array(NUVEI_GATEWAY_NAME, 'sc'))) {
		return;
	}
	
	if (Nuvei_Http::get_param('key') != $order_key) {
		return;
	}
	
	$prods_ids = array();
	
	foreach ($order->get_items() as $data) {
		$prods_ids[] = $data->get_product_id();
	}
	
	echo '<script>'
		. 'var scProductsIdsToReorder = ' . wp_kses_post(json_encode($prods_ids)) . ';'
		. 'scOnPayOrderPage();'
	. '</script>';
}

// on reorder, show warning message to the cart if need to
function nuvei_show_message_on_cart( $data) {
	echo '<script>jQuery("#content .woocommerce:first").append("<div class=\'woocommerce-warning\'>'
		. wp_kses_post(Nuvei_Http::get_param('sc_msg')) . '</div>");</script>';
}

// Attributes, Terms and Meta functions
function nuvei_add_term_fields_form( $taxonomy) {
	$nuvei_plans_path = plugin_dir_path(__FILE__) . '/tmp/sc_plans.json';
	
	ob_start();
	
	$plans_list = array();
	if (is_readable($nuvei_plans_path)) {
		$plans_list = json_decode(file_get_contents($nuvei_plans_path), true);
	}
	
	require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'templates/admin/add_terms_form.php';
	
	ob_end_flush();
}

function nuvei_edit_term_meta_form( $term, $taxonomy) {
	$nuvei_plans_path = plugin_dir_path(__FILE__) . '/tmp/sc_plans.json';

	ob_start();
	$term_meta  = get_term_meta($term->term_id);
	$plans_list = array();
	$plans_json = '';
	
	if (is_readable($nuvei_plans_path)) {
		$plans_json = file_get_contents($nuvei_plans_path);
		$plans_list = json_decode($plans_json, true);
	}
	
	// clean unused elements
	foreach ($term_meta as $key => $data) {
		if (strpos($key, '_') !== false) {
			unset($term_meta[$key]);
			break;
		}
	}
	
	require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'templates/admin/edit_term_form.php';
	ob_end_flush();
}

function nuvei_save_term_meta( $term_id, $tt_id) {
	$taxonomy      = 'pa_' . Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME);
	$post_taxonomy = Nuvei_Http::get_param('taxonomy', 'string');
	
	if ($post_taxonomy != $taxonomy) {
		return;
	}
	
	add_term_meta( $term_id, 'planId', Nuvei_Http::get_param('planId', 'int') );
	add_term_meta( $term_id, 'recurringAmount', Nuvei_Http::get_param('recurringAmount', 'float') );

	add_term_meta( $term_id, 'startAfterUnit', Nuvei_Http::get_param('startAfterUnit', 'string') );
	add_term_meta( $term_id, 'startAfterPeriod', Nuvei_Http::get_param('startAfterPeriod', 'int') );

	add_term_meta( $term_id, 'recurringPeriodUnit', Nuvei_Http::get_param('recurringPeriodUnit', 'string') );
	add_term_meta( $term_id, 'recurringPeriodPeriod', Nuvei_Http::get_param('recurringPeriodPeriod', 'int') );

	add_term_meta( $term_id, 'endAfterUnit', Nuvei_Http::get_param('endAfterUnit', 'string') );
	add_term_meta( $term_id, 'endAfterPeriod', Nuvei_Http::get_param('endAfterPeriod', 'int') );
}

function nuvei_edit_term_meta( $term_id, $tt_id) {
	$taxonomy      = 'pa_' . Nuvei_String::get_slug(NUVEI_GLOB_ATTR_NAME);
	$post_taxonomy = Nuvei_Http::get_param('taxonomy', 'string');
	
	if ($post_taxonomy != $taxonomy) {
		return;
	}
	
	update_term_meta( $term_id, 'planId', Nuvei_Http::get_param('planId', 'int') );
	update_term_meta( $term_id, 'recurringAmount', Nuvei_Http::get_param('recurringAmount', 'float') );

	update_term_meta( $term_id, 'startAfterUnit', Nuvei_Http::get_param('startAfterUnit', 'string') );
	update_term_meta( $term_id, 'startAfterPeriod', Nuvei_Http::get_param('startAfterPeriod', 'int') );

	update_term_meta( $term_id, 'recurringPeriodUnit', Nuvei_Http::get_param('recurringPeriodUnit', 'string') );
	update_term_meta( $term_id, 'recurringPeriodPeriod', Nuvei_Http::get_param('recurringPeriodPeriod', 'int') );

	update_term_meta( $term_id, 'endAfterUnit', Nuvei_Http::get_param('endAfterUnit', 'string') );
	update_term_meta( $term_id, 'endAfterPeriod', Nuvei_Http::get_param('endAfterPeriod', 'int') );
}
// Attributes, Terms and Meta functions END

# For the custom column in the Order list
function nuvei_fill_custom_column( $column) {
	global $post;
	
	$order = wc_get_order($post->ID);
	
	if ('order_number' === $column && !empty($order->get_meta(NUVEI_ORDER_SUBSCR_IDS))) { 
		?>
		<mark class="order-status status-processing tips" style="float: right;">
			<span><?php echo esc_html__('Nuvei Subscription', 'nuvei_woocommerce'); ?></span>
		</mark>
		<?php 
	}
}
# For the custom column in the Order list END

/**
 * In Store > My Account > Orders table, Order column
 * add Rebilling icon for the Orders with Nuvei Payment Plan.
 * 
 * @param WC_Order $order
 */
function nuvei_edit_my_account_orders_col( $order) {
	echo '<a href="' . esc_url( $order->get_view_order_url() ) . '"';
	
	if (!empty($order->get_meta(NUVEI_ORDER_SUBSCR_IDS))) {
		echo ' class="nuvei_plan_order" title="' . esc_attr__('Nuvei Payment Plan Order', 'nuvei_woocommerce') . '"';
	}
	
	echo '>#' . esc_html($order->get_order_number()) . '</a>';
}

/**
 * Repeating code from Version Checker logic.
 * 
 * @param string $file the path to the file we will save
 * @return array
 */
function nuvei_get_file_form_git( $file) {
	$matches = array();
	$ch      = curl_init();

	curl_setopt(
		$ch,
		CURLOPT_URL,
		'https://raw.githubusercontent.com/SafeChargeInternational/nuvei_woocommerce_plugin/main/index.php'
	);

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	$file_text = curl_exec($ch);
	curl_close($ch);

	preg_match('/(\s?\*\s?Version\s?:\s?)(.*\s?)(\n)/', $file_text, $matches);

	$array = array(
		'date'  => gmdate('Y-m-d H:i:s', time()),
		'git_v' => (int) str_replace('.', '', trim($matches[2])),
	);

	file_put_contents($file, json_encode($array));
	
	return $array;
}
