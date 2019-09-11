<?php 
/**
 * Plugin Name: WooCommerce ReddotPayment Gateway API3
 * Plugin URI: https://reddotpayment.com/
 * Description: Payment Plugin by reddotpayment
 * Version: 1.3.1
 * Author: Red Dot Payment
 * Author URI: https://reddotpayment.com/
 * Developer: thamjieying
 *
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 * Requires PHP: 5.6
 *
 * Copyright: © 2009-2015 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

 use Firebase\JWT\JWT;


if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
  return;
}

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + reddotpay gateway
 */
function wc_reddotpayment_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_RedDotPay';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_reddotpayment_add_to_gateways' );


/**
 * RedDot Payment Gateway
 *
 * Provides an RedDot Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_RedDotPay
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		RedDotPayment
 */
add_action( 'plugins_loaded', 'wc_reddotpayment_gateway_init', 11 );

function wc_reddotpayment_gateway_init() {
  class WC_Gateway_RedDotPay extends WC_Payment_Gateway {
    
    public function __construct(){
      $this->id                 = 'reddotpayment_gateway';
      $this->icon               = apply_filters('woocommerce_reddotpayment_icon', '');
      $this->has_fields         = false; //output a ‘payment_box’ containing your direct payment form that you define next.
      $this->method_title       = 'Red Dot Payment';
      $this->method_description = 'Red Dot Payment integration for woocommerce';
      
      // Methods with all option fields
      $this->init_form_fields();
      
      // Set Variables
      $this->init_settings();
      $this->title = $this->get_option( 'title' );
      $this->description = $this->get_option( 'description' );
      $this->enabled = $this->get_option( 'enabled' );
      $this->test_mode = $this->get_option( 'test' );
      $this->client_key = $this->get_option( 'client_key' );
	    $this->client_secret = $this->get_option( 'client_secret' );
      $this->merchant_id = $this->get_option( 'merchant_id' );
      // $this->return_url = $this->get_option('return_url');

      $this->log = new WC_Logger();

      // Save Settings
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

      // Webhook to handle callback
      add_action( 'woocommerce_api_wc_rdp', array( $this, 'complete_payment' ) );      

      // Thank you page
      add_filter( 'woocommerce_thankyou_'.$this->id, array($this, 'thankyou_page'), 2, 1);
      add_filter( 'woocommerce_thankyou_order_received_text', array($this, 'rdp_order_received'), 1, 2);
    }

    // options show in admin
    public function init_form_fields() {
      $this->form_fields = array(
        'enabled' => array(
          'title' => 'Enable/Disable',
          'type' => 'checkbox',
          'label' => 'Enable Red Dot Payment',
          'default' => 'yes'
        ),
        'title' => array(
          'title' => 'Title',
          'type' => 'text',
					'description' => 'This controls the title for the payment method the customer sees during checkout.',
					'default'     => 'Red Dot Payment',
					'desc_tip'    => true,
        ),
        'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'Payment method description that the customer will see on your checkout.',
					'default'     => 'We are accepting various types of payment methods',
					'desc_tip'    => true,
        ),	
        'test' => array(
          'title' => 'Test Mode',
          'type' => 'checkbox',
          'description' => 'Un-check test mode to use it on Production',
          'label' => 'Enable Test Mode',
          'default' => 'yes', 
          'desc_tip' => true,
        ),
        'client_key' => array(
            'title' => 'Client Key',
            'type' => 'password',
            'description' => 'client key issued by connect2',
            'desc_tip'      => true,
        ),
        'client_secret' => array(
            'title' => 'Client Secret',
            'type' => 'password',
            'description' => 'client secret issued by connect2 associated to the client key',
            'desc_tip'      => true,
        ),
        'merchant_id' => array(
            'title' => 'Merchant Id',
            'type' => 'password',
            'description' => 'merchant ID acquired from connect2',
            'desc_tip' => true,
        ),
        'webhook' => array(
          'title' => 'Webhook Endpoints', 
          'type' => 'title',
          'description' => $this->display_webhook_url(),
        )
      );
    }

    // Submit payment and handle response
    public function process_payment( $order_id ){
      global $woocommerce;
      $domain = $this->test_mode ? 'https://connect2.api.reddotpay.sg' : 'https://connect2.api.reddotpay.com';

      // increase timeout timing to 15 seconds
      add_filter( 'http_request_args', 'debug_url_request_args', 10, 2 );
      function debug_url_request_args($r, $url){
        if( preg_match("/connect2\.api\.reddotpay/", $url) ){
                $r["timeout"] = 15;
        }
        return $r;
      }
      
      // Get Access Token
      $access_token = $this->get_access_token($domain);

      // Get Payment Url 
      $payment_page_url = $this->get_payment_url($domain, $access_token, $order_id);

      // redirect to payment url
      return array(
        'result' => 'success',
        'redirect' => $payment_page_url
      );
    }

    //
    public function thankyou_page( $order_id, $params2 = ""){

    }

    private function get_access_token($domain){
      $req_body = array('clientKey' => $this->client_key, 'clientSecret'=> $this->client_secret);
      $args = array(
        'method' => 'POST',
        'headers' => array(
          'Content-Type' => 'application/json; charset=utf-8',
        ),
        'body' => json_encode($req_body), 
        'data_format' => 'body',
      );

      $response_token = wp_remote_post( $domain.'/v1/authenticate', $args);

      if( is_wp_error( $response_token ) ) {
        $error_msg = $response_token->get_error_message();
        // var_dump($error_msg);
        wc_add_notice(  'Connection Error. Please Try again!' , 'error' );
		    return;
      }

      $res_body = json_decode( wp_remote_retrieve_body( $response_token ), true );
      $access_token = $res_body['accessToken'];
      // var_dump('access token'.$access_token);
      if( !$access_token ){
        wc_add_notice( 'Cannot Authenticate, Please Try Again.', 'error' );
			  return;
      }

      return $access_token;
    }

    private function get_payment_url($domain, $access_token, $order_id){
      $order = wc_get_order( $order_id ); // get order details
      $currency = get_woocommerce_currency(); // get shop currency
      $total = $order->get_total(); // get total amount
      $customer_email = $order->get_billing_email(); // get order email
      $transaction_id = $this->generate_transaction_id($order_id); // transaction_id
      $return_url = $order->get_checkout_order_received_url();

      // save transaction id
      $add_success = add_post_meta( $order->id, '_transaction_id', $transaction_id, false );

      // check if return_url exist and start with https
      $secure_returnUrl = $return_url && preg_match("/^https/", $return_url)===1;

      $req_body = array(
        'orderId' => $transaction_id,
        'amount' => $total,
        'currency' => $currency,
        'email' => $customer_email,
        'returnUrl' => $secure_returnUrl ? $return_url : ''
      );

      $arg = array(
        'method' => 'POST',
        'headers' => array(
          'Content-Type' => 'application/json; charset=utf-8',
          'Authorization' => $access_token
        ),
        'body' => json_encode($req_body),
      );

      $response = wp_remote_post( $domain.'/v1/payments/token/'.$this->merchant_id, $arg);
      if( is_wp_error( $response ) ){
        wc_add_notice('Error getting payment page url. Please try again', 'error');
        return;
      }
      $res_body = json_decode(wp_remote_retrieve_body($response), true);
      if($res_body['error']){
        wc_add_notice($res_body['error']['message'], 'error');
        return;
      }
      
      $payment_page_url = $res_body['pageURI'];
      // var_dump('url '.$payment_page_url);
      if(!$payment_page_url){
        wc_add_notice('error getting pageURI. Please try again!', 'error');
        return;
      }

      return $payment_page_url;
    }

    private function generate_transaction_id($order_id){
      global $wpdb;

      // $wpdb->dba_insert
      $transaction_id = '#'.$order_id.time().'rdp';

      return $transaction_id;
    }

    private function get_order_id_from_transaction_id($transaction_id){
      global $wpdb; 

      $order = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = '_transaction_id' AND meta_value = '$transaction_id' " );
      $order_id = $order->post_id;
      
      return $order_id;
    }

    private function display_webhook_url(){
      $hook_url = add_query_arg( 'wc-api', 'wc_rdp', trailingslashit( get_home_url() ) );

		  return sprintf('Please add the following webhook endpoint <strong style="background-color:#ddd;">&nbsp;%s&nbsp;</strong> to your <a href="https://connect2.reddotpay.com" target="_blank">Hosted Page settings</a>. This will allow order status to be updated on completion of transaction.', $hook_url );
    }

    // webhook function that runs after payment is received (testing new plugin)
     public function complete_payment() {
        include plugin_dir_path(__FILE__).'php-jwt/src/JWT.php';
  
        $json = file_get_contents('php://input');
        $resBody = json_decode($json, TRUE); //convert JSON into array
        $token = $resBody['messageToken'];
        
        if(!$token){
          error_log('no token');
          status_header( 400 );
          exit;
        }

        // // decode and verify jwt token
        JWT::$leeway = 60;
        $payload = JWT::decode($token, $this->client_secret, array('HS256'));
        $payload_array = (array) $payload;

        $transaction_id = $payload_array['orderId'];
        $order_id = $this->get_order_id_from_transaction_id($transaction_id);
        $order_status = $payload_array['status'];
        $order = wc_get_order( $order_id );
        
        //Update payment status
        if($order_status == 'success'){
          // payment success
          $order->payment_complete();
          wc_reduce_stock_levels( $order->get_id() );
        }else{
          // payment failure
          $order->add_order_note(__('Payment Failed', 'woocommerce'));

          // $order->update_status('failed', __('Payment has been cancelled.'));
          // $woocommerce->cart->empty_cart();
        }
        error_log('update');
        update_option('webhook_debug', $_GET );
      }

    public function rdp_order_received( $thank_you_title, $order){
      
    }

  } 
}
?>