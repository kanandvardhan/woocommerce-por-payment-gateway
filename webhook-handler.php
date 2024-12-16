<?php
// Webhook handler for POR Payment Gateway.
add_action('woocommerce_api_por_payment_webhook', 'handle_por_payment_webhook');

function handle_por_payment_webhook() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['response']['TransactionNumber'])) {
        $order_id = wc_get_order_id_by_order_key($data['response']['TransactionNumber']);
        $order = wc_get_order($order_id);

        if ($order && $data['response']['Message'] === 'Transaction was successful') {
            $order->payment_complete();
            $order->add_order_note('Payment confirmed via webhook.');
            $order->update_status('processing', __('Payment completed.', 'woocommerce'));
            status_header(200);
        } else {
            status_header(400);
        }
    }

    exit;
}
