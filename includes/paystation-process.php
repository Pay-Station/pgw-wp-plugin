<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Handle response from PayStation gateway
add_action('woocommerce_api_wc_gateway_paystation', 'paystation_payment_response_handler');

function paystation_payment_response_handler() {
    $status         = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $invoice_number = isset($_GET['invoice_number']) ? sanitize_text_field($_GET['invoice_number']) : '';
    $trx_id         = isset($_GET['trx_id']) ? sanitize_text_field($_GET['trx_id']) : '';

    if (empty($status) || empty($invoice_number)) {
        wp_die('Invalid request');
    }

    // Extract WooCommerce Order ID from invoice_number: e.g., WP941-1741945382-33410
    $invoice_parts = explode('-', $invoice_number);
    $order_id      = end($invoice_parts);
    $order         = wc_get_order($order_id);

    if (!$order) {
        error_log("PayStation Gateway: Order not found for invoice: {$invoice_number}");
        wp_die('Order not found.');
    }

    $status = strtolower($status);


    // Check Transaction Status
    $body = array(
        'invoice_number' => $invoice_number
    );

    $header = array(
        'merchantId' => get_option('merchant_id')
    );

    $response = wp_remote_post('https://api.paystation.com.bd/transaction-status', array(
        'method' => 'POST',
        'body' => $body,
        'headers' => $header,
        'timeout' => 60,
    ));

    // if (is_wp_error($response)) {
    //     wc_add_notice(__('Payment error:', 'woocommerce') . $response->get_error_message(), 'error');
    //     return;
    // }

    $result = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($result['status_code']) && $result['status_code'] === '200') {
        if (isset($result['data']) && $result['data']['trx_status'] === 'successful') {
            $order->payment_complete($trx_id);
            $order->update_status('completed');
            $order->add_order_note("Payment completed via PayStation. Transaction ID: {$trx_id}");

            // Redirect to thank you page with status query
            $redirect_url = $order->get_checkout_order_received_url() . '&status=' . $result['data']['trx_status'];
        } else {

            if($status === 'canceled'){
                $order->update_status('cancelled', 'PayStation payment failed or cancelled.');
                WC()->cart->empty_cart();

                // Retrieve fail_url from WordPress options
                $redirect_url = get_option('paystation_fail_url', '');
                
                if (empty($redirect_url)) {
                    echo "Fail URL is not set.";
                }
            }else {
                $order->update_status('failed', 'PayStation payment failed or cancelled.');
                WC()->cart->empty_cart();

                // Retrieve fail_url from WordPress options
                $redirect_url = get_option('paystation_fail_url', '');

                if (empty($redirect_url)) {
                    echo "Fail URL is not set.";
                }
            }

        }
    } else {
    
        if($status === 'canceled'){
            $order->update_status('cancelled', 'PayStation payment failed or cancelled.');
            WC()->cart->empty_cart();

            // Retrieve fail_url from WordPress options
            $redirect_url = get_option('paystation_fail_url', '');
            
            if (empty($redirect_url)) {
                echo "Fail URL is not set.";
            }
        }else {
            $order->update_status('failed', 'PayStation payment failed or cancelled.');
            WC()->cart->empty_cart();

            // Retrieve fail_url from WordPress options
            $redirect_url = get_option('paystation_fail_url', '');

            if (empty($redirect_url)) {
                echo "Fail URL is not set.";
            }
        }

    }
    
    wp_redirect($redirect_url);
    exit;
}