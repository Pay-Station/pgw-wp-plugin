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

    if ($status === 'successful') {
        $order->payment_complete($trx_id);
        $order->update_status('completed');
        $order->add_order_note("Payment completed via PayStation. Transaction ID: {$trx_id}");
    } else if($status === 'Canceled'){
        $order->update_status('cancelled', 'PayStation payment failed or cancelled.');
        WC()->cart->empty_cart();
    }else {
        $order->update_status('failed', 'PayStation payment failed or cancelled.');
        WC()->cart->empty_cart();
    }

    // Redirect to thank you page with status query
    $redirect_url = $order->get_checkout_order_received_url() . '&status=' . $status;
    wp_redirect($redirect_url);
    exit;
}