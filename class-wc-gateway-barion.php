<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once 'barion-library/library/BarionClient.php';
require_once 'includes/class-wc-gateway-barion-ipn-handler.php';

class WC_Gateway_Barion extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'barion';
        $this->method_title       = __('Barion', 'woocommerce');
        $this->method_description = sprintf( __( 'Barion payment gateway sends customers to Barion to enter their payment information. Barion callback requires cURL support to update order statuses after payment. Check the %ssystem status%s page for more details.', 'woocommerce' ), '<a href="' . admin_url( 'admin.php?page=wc-status' ) . '">', '</a>' );
        $this->has_fields         = false;
        $this->order_button_text  = __( 'Proceed to Barion', 'woocommerce' );
        $this->icon               = $this->plugin_url() . '/assets/barion-card-payment-banner-2016-300x35px.png';
        $this->supports           = array(
            'products'
        );
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->barion_environment = BarionEnvironment::Prod;
        
        if ( $this->settings['environment'] == 'test' ) {
            $this->title .= ' [TEST MODE]';
            $this->description .= '<br/><br/><u>Test Mode is <strong>ACTIVE</strong>, use following Credit Card details:-</u><br/>'."\n"
                                 .'Test Card Name: <strong><em>any name</em></strong><br/>'."\n"
                                 .'Test Card Number: <strong>4908 3660 9990 0425</strong><br/>'."\n"
                                 .'Test Card CVV: <strong>823</strong><br/>'."\n"
                                 .'Test Card Expiry: <strong>Future date</strong>';    

            $this->barion_environment = BarionEnvironment::Test;                                   
        }

        $this->poskey = $this->settings['poskey'];
        $this->payee = $this->settings['payee'];
        $this->redirect_page = $this->settings['redirect_page'];
        
        $this->barion_client = new BarionClient($this->poskey, 2, $this->barion_environment, true);
        
        $callback_handler = new WC_Gateway_Barion_IPN_Handler($this->barion_client);
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }
    
    public function plugin_url() {
		return untrailingslashit(plugins_url('/', __FILE__));
	}
    
    /** @var boolean */
    static $debug_mode = false;
    
	/** @var WC_Logger Logger instance */
	static $log = null;
    
    public static function log($message, $level = 'error') {
		if ($level != 'error' && !self::$debug_mode) {
            return;
        }
        
		if (empty(self::$log)) {
			self::$log = new WC_Logger();
		}
        
		self::$log->add('barion', $message);
	}
    
    function init_form_fields() {
        $this->form_fields = include('includes/settings-barion.php');
    }

    function process_payment($order_id) {
        $order = new WC_Order($order_id);
        
        require_once('includes/class-wc-gateway-barion-request.php');
        
        $request = new WC_Gateway_Barion_Request($this->barion_client, $this);
        
        $request->prepare_payment($order);
        
        if(!$request->is_prepared) {
            return array(
                'result' => 'failure'
            );
        }
        
        $redirectUrl = $request->get_redirect_url();
        
        $order->add_order_note(__('User redirected to the Barion payment page.', 'woocommerce') . ' redirectUrl: "' . $redirectUrl . '"');
        
        return array(
            'result' => 'success', 
            'redirect' => $redirectUrl
        );
    }
}
