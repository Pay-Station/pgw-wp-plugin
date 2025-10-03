<?php
/*
Plugin Name: PayStation Payment Gateway
Description: Accept payments through PayStation in WooCommerce.
Version: 1.0.0
Author: PayStation
Author URI: https://www.paystation.com.bd
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PAYSTATION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PAYSTATION_PLUGIN_URL', plugin_dir_url(__FILE__));

defined('ABSPATH') || exit;

// Include payment processor
require_once PAYSTATION_PLUGIN_DIR . 'includes/paystation-process.php';

// Ensure is_plugin_active is available
if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// Admin notice if WooCommerce is not active (backend)
add_action('admin_notices', function() {
    if (
        current_user_can('activate_plugins') &&
        !class_exists('WooCommerce')
    ) {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('PayStation Payment Gateway cannot be activated because WooCommerce is missing or inactive.', 'paystation_payment_gateway');
        echo '</p></div>';
    }
});

// Show a custom notice on the checkout page if WooCommerce is not active (frontend)
add_action('woocommerce_before_checkout_form', function() {
    if (!class_exists('WC_Payment_Gateway')) {
        wc_print_notice(__('PayStation Payment Gateway requires WooCommerce to be active. Please contact the site administrator.', 'paystation_payment_gateway'), 'error');
    }
});

// Show a custom notice if [woocommerce_checkout] is used but WooCommerce is not active
add_shortcode('woocommerce_checkout', function($atts = [], $content = null) {
    if (!class_exists('WooCommerce')) {
        return '<div style="padding:20px;background:#ffeaea;border:1px solid #e00;color:#a00;font-weight:bold;">'
            . esc_html__('PayStation Payment Gateway requires WooCommerce to be active. Please contact the site administrator.', 'paystation_payment_gateway')
            . '</div>';
    }
    // If WooCommerce is active, run the original shortcode handler
    return function_exists('woocommerce_checkout') ? woocommerce_checkout($atts) : '';
});

// Register the gateway with WooCommerce
add_filter('woocommerce_payment_gateways', 'add_paystation_gateway_class');
function add_paystation_gateway_class($gateways)
{
    $gateways[] = 'WC_Gateway_Paystation';
    return $gateways;
}

// Initialize the gateway class after WooCommerce is loaded
add_action('plugins_loaded', 'init_paystation_gateway_class', 11);
function init_paystation_gateway_class()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Paystation extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'paystation_payment_gateway';
            $this->method_title = __('Paystation Payment Gateway', 'paystation_payment_gateway');
            // $this->method_description = __('Pay via PayStation.', 'woocommerce');
            $this->icon = trailingslashit(WP_PLUGIN_URL) . plugin_basename(dirname(__FILE__)) . '/images/icon.png';
            $this->has_fields = true;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->merchant_id = $this->get_option('merchant_id');
            $this->password = $this->get_option('password');
            $this->charge_for_customer = $this->get_option('charge_for_customer');
            $this->emi = $this->get_option('emi');
            $this->fail_url = $this->get_option('fail_url');

            $this->description 	= $this->get_option('ps_gateway_description');
            $this->supports = array(
                'products'
            );

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Handle callback
            add_action('init', array($this, 'check_paystation_response'));

            // Store fail_url in WordPress options
            update_option('paystation_fail_url', $this->fail_url);

            // Store merchantId in WordPress options
            update_option('merchant_id', $this->merchant_id);
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'title' => array(
                    'title' => __('Title', 'paystation_payment_gateway'),
                    'type' => 'text',
                    'description' => __('This controls the title the user sees during checkout.'),
                    'default' => __('PayStation', 'paystation_payment_gateway'),
                    'desc_tip' => true
                ),
                'ps_gateway_description' => array(
                    'title'       => __('Description', 'paystation_payment_gateway'),
                    'type'        => 'textarea',
                    'description' => __( 'This will be shown as the payment method description on the checkout page.', 'paystation_payment_gateway'),
                    'default'     => __('Pay securely by Credit/Debit card, Internet banking or Mobile banking through PayStation.', 'paystation_payment_gateway')
                ),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'paystation_payment_gateway'),
                    'type' => 'text',
                    'description' => __('Your PayStation Merchant ID'),
                    'default' => ''
                ),
                'password' => array(
                    'title' => __('Password', 'paystation_payment_gateway'),
                    'type' => 'password',
                    'description' => __('Your PayStation API Password'),
                    'default' => ''
                ),
                'charge_for_customer' => array(
                    'title'     => __('Charge', 'paystation_payment_gateway'),
                    'type'      => 'select',
                    'desc_tip'  => __('Select payment option.', 'charge_for_customer'),
                    'options'   => array(
                        '1' => __('Pay With Charge', 'paystation_payment_gateway'),
                        '0' => __('Pay Without Charge', 'paystation_payment_gateway'),
                    ),
                    'default'   => '1',
                ),
                'emi' => array(
                    'title'     => __('EMI', 'paystation_payment_gateway'),
                    'type'      => 'select',
                    'desc_tip'  => __('Select option.', 'emi'),
                    'options'   => array(
                        '1' => __('Yes', 'paystation_payment_gateway'),
                        '0' => __('No', 'paystation_payment_gateway'),
                    ),
                    'default'   => '0',
                ),
                'fail_url' => array(
                    'title' => __('Fail/Cancel URL', 'paystation_payment_gateway'),
                    'type' => 'text',
                    'description' => __('Fail/Cancel URL'),
                    'default' => ''
                ),
            );
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            $callback_url = home_url('/?wc-api=wc_gateway_paystation');

            $current_datetime = date('YmdHis');
            $invoice_number = 'WP' . $current_datetime . '-' . $order_id;

            $amount = $order->get_total();
            $billing = $order->get_address('billing');

            if ($this->emi == 1 && $amount < 5000) {
                wc_add_notice(__('Minimum amount should be 5000 Tk for EMI.', 'woocommerce') . " Please choose a higher amount for EMI.", 'error');
                return;
            }

            $body = array(
                'invoice_number' => $invoice_number,
                'currency' => 'BDT',
                'payment_amount' => $amount,
                'cust_name' => $billing['first_name'],
                'cust_phone' => $billing['phone'],
                'cust_email' => $billing['email'],
                'cust_address' => $billing['address_1'],
                'reference' => 'WP-WooCommerce',
                'callback_url' => $callback_url,
                'checkout_items' => 'items',
                'pay_with_charge' => $this->charge_for_customer,
                'emi' => $this->emi,
                'merchantId' => $this->merchant_id,
                'password' => $this->password
            );

            $response = wp_remote_post('https://api.paystation.com.bd/initiate-payment', array(
                'method' => 'POST',
                'body' => $body,
                'timeout' => 60,
            ));

            if (is_wp_error($response)) {
                wc_add_notice(__('Payment error:', 'woocommerce') . $response->get_error_message(), 'error');
                return;
            }

            $result = json_decode(wp_remote_retrieve_body($response), true);
            if ($result['status'] === 'success') {
                return array(
                    'result' => 'success',
                    'redirect' => $result['payment_url']
                );
            } else {
                wc_add_notice(__('Payment failed:', 'woocommerce') . $result['message'], 'error');
                return;
            }
        }

        public function check_paystation_response()
        {
            if (isset($_GET['wc-api']) && $_GET['wc-api'] === 'wc_gateway_paystation') {
                require_once PAYSTATION_PLUGIN_DIR . 'includes/paystation-process.php';
                exit;
            }
        }
    }
}