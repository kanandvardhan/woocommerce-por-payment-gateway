<?php
/*
 * Plugin Name: POR Payment Gateway
 * Plugin URI: https://payonramp.io
 * Description: A custom payment gateway for WooCommerce by PayOnRamp.
 * Author: PayOnRamp
 * Author URI: https://payonramp.io
 * Version: 1.0.1
 * Text Domain: por-payment-gateway
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants
define('POR_PAYMENT_GATEWAY_VERSION', '1.0.1');
define('POR_PAYMENT_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('POR_PAYMENT_GATEWAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Load plugin text domain for translations.
 */
add_action('plugins_loaded', function () {
    load_plugin_textdomain('por-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

/**
 * Check if WooCommerce is active.
 */
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>' . esc_html__('POR Payment Gateway requires WooCommerce to be installed and active.', 'por-payment-gateway') . '</p>';
        echo '</div>';
    });
    return;
}

// WooCommerce is active, proceed with plugin initialization
add_action('plugins_loaded', 'por_payment_gateway_init');
add_filter('woocommerce_payment_gateways', 'add_por_gateway_class');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'por_payment_gateway_settings_link');
add_action('wp_ajax_por_update_order_status', 'por_update_order_status');
add_action('wp_ajax_nopriv_por_update_order_status', 'por_update_order_status');
add_action('woocommerce_thankyou', 'display_payment_instructions', 10, 1);
add_action('rest_api_init', 'update_order_status_webhook_endpoint');
// add_action('wp_ajax_por_resend_payment_link', 'resend_payment_link');
// add_action('wp_ajax_nopriv_por_resend_payment_link', 'resend_payment_link');

/**
 * Initialize the payment gateway.
 */
function por_payment_gateway_init() {
    if (!class_exists('WC_POR_Payment_Gateway')) {
        include_once POR_PAYMENT_GATEWAY_PLUGIN_PATH . '/includes/class-por-payment-gateway.php';
    }
}

/**
 * Add the POR Gateway to WooCommerce payment methods.
 */
function add_por_gateway_class($gateways) {
    $gateways[] = 'WC_POR_Payment_Gateway';
    return $gateways;
}

/**
 * Add settings link to the plugin action links.
 */
function por_payment_gateway_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=por_gateway">' . __('Settings', 'por-payment-gateway') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function por_display_admin_notice($message, $type = 'error') {
    add_action('admin_notices', function () use ($message, $type) {
        $class = ($type === 'success') ? 'notice-success' : 'notice-error';
        printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($message));
    });
}

/**
 * Display payment instructions on the "Thank You" page.
 */
function display_payment_instructions($order_id) {
    $order = wc_get_order($order_id);

    if (!$order) {
        return; // Order doesn't exist.
    }

    // Check if the payment status is awaiting payment.
    if (!isset($_GET['payment_status']) || $_GET['payment_status'] !== 'awaiting_payment') {
        return;
    }

    // Start WooCommerce wrapper
    echo '<div class="woocommerce-order" style="max-width: 600px; margin: 3rem 0;">';
    echo '<h2 class="woocommerce-order-details__title">' . __('Complete Your Payment', 'por-payment-gateway') . '</h2>';

    // Retrieve and display QR code
    $qr_code = $order->get_meta('_qr_code');
    if ($qr_code) {
        if (strpos($qr_code, 'data:image') === 0) {
            echo '<p>' . __('Scan the QR code below to complete your payment:', 'por-payment-gateway') . '</p>';
            echo '<img src="' . esc_attr($qr_code) . '" alt="QR Code" style="max-width:200px; margin:10px auto; display:block;">';
        } else {
            echo '<p>' . __('Scan the QR code below to complete your payment:', 'por-payment-gateway') . '</p>';
            echo '<img src="' . esc_url($qr_code) . '" alt="QR Code" style="max-width:200px; margin:10px auto; display:block;">';
        }
    } else {
        echo '<p>' . __('QR Code could not be retrieved. Please contact support.', 'por-payment-gateway') . '</p>';
    }

    echo '<p style="text-align: center;">' . __('OR', 'por-payment-gateway') . '</p>';

    // Display payment link
    $payment_link = $order->get_meta('_payment_link');
    if ($payment_link) {
        echo '<div class="woocommerce-notice woocommerce-notice--info">';
        echo '<p style="text-align: center;"><a href="' . esc_url($payment_link) . '" target="_blank" class="button">' . __('Click here to complete your payment', 'por-payment-gateway') . '</a></p>';
        echo '</div>';
    }

    // Dynamic messages
    $email_success = $order->get_meta('_payment_email_success');
    $phone_success = $order->get_meta('_payment_phone_success');

    if ($email_success && $phone_success) {
        echo '<ul><li class="woocommerce-notice woocommerce-notice--info">' . __('A payment link has been sent to your email and phone number. Please complete the payment to finish your order.', 'por-payment-gateway') . '</li>';
    } elseif ($email_success) {
        echo '<li class="woocommerce-notice woocommerce-notice--info">' . __('A payment link has been sent to your email. Please complete the payment to finish your order.', 'por-payment-gateway') . '</li>';
    } elseif ($phone_success) {
        echo '<li class="woocommerce-notice woocommerce-notice--info">' . __('A payment link has been sent to your phone number. Please complete the payment to finish your order.', 'por-payment-gateway') . '</li>';
    }
    

    // Resend Payment Link option
    // $reference_number = $order->get_meta('_reference_number');
    // if ($reference_number) {
    //     $order_id = $order->get_id();

    //     echo '<li class="woocommerce-notice woocommerce-notice--info">Click ';
    //     echo '<a href="#" id="por-resend-payment-link" style="color: #0073aa;"
    //         data-order-id="' . esc_attr($order_id) . '" >' . __('here', 'por-payment-gateway') . '</a>';
    //     echo ' to resend the payment link, if you did not receive any.';
    //     echo ' <span id="resend-timer" style="font-size: 0.9em; color: #555;"></span>';
    //     echo '</li>';
        
    // }
    ?>
    <script>
    jQuery(function ($) {
        let cooldownTime = 60;
        let timer;

        const startTimer = () => {
            let remainingTime = cooldownTime;
            const timerElement = $('#resend-timer');
            const resendLink = $('#por-resend-payment-link');

            resendLink.addClass('disabled').css('pointer-events', 'none');
            timerElement.text(`(Available in ${remainingTime}s)`);

            timer = setInterval(function () {
                remainingTime--;
                timerElement.text(`(Available in ${remainingTime}s)`);

                if (remainingTime <= 0) {
                    clearInterval(timer);
                    timerElement.text('');
                    resendLink.removeClass('disabled').css('pointer-events', 'auto');
                }
            }, 1000);
        };

        // Start the timer immediately on page load
        // startTimer();

        $('#por-resend-payment-link').on('click', function (e) {
            e.preventDefault();
            var link = $(this);
            var order_id = link.data('order-id'); // Fetch the order ID from the link

            // Prevent click if already disabled
            if (link.hasClass('disabled')) {
                return;
            }

            link.text('<?php echo esc_js(__(' sending... ' , 'por-payment-gateway')); ?>');
            
            $.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                method: 'POST',
                data: {
                    action: 'por_resend_payment_link',
                    order_id: '<?php echo esc_js($order_id); ?>',
                },
                success: function (response) {
                    console.log('AJAX Success:', response); 
                    if (response.success) {
                        alert(response.data.message);
                        // startTimer(); // Restart the cooldown timer
                    } else {
                        console.log('AJAX Failed:', response); 
                        alert(response.data.message || '<?php echo esc_js(__('Failed to resend the payment link.', 'por-payment-gateway')); ?>');
                    }
                    link.text('<?php echo esc_js(__('here', 'por-payment-gateway')); ?>');
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.log('AJAX Error:',  textStatus, errorThrown);
                    alert('<?php echo esc_js(__('An error occurred. Please try again.', 'por-payment-gateway')); ?>');
                    link.text('<?php echo esc_js(__('here', 'por-payment-gateway')); ?>');
                }
            });
        });
    });
    </script>
    <?php

    echo '<li>' . __('Once the payment is complete, click the button below to confirm.', 'por-payment-gateway') . '</li></ul>';

    // Confirmation button
    echo '<div class="form-row form-row-wide" style="text-align: center; margin-top: 20px;">';
    echo '<button id="por-payment-confirm-btn" class="button alt">' . __('I have completed the payment', 'por-payment-gateway') . '</button>';
    echo '</div>';

    // JavaScript for AJAX functionality
    ?>
    <script>
    jQuery(function ($) {
        $('#por-payment-confirm-btn').on('click', function () {
            var button = $(this);
            button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'por-payment-gateway')); ?>');

            $.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                method: 'POST',
                data: {
                    action: 'por_update_order_status',
                    order_id: '<?php echo esc_js($order_id); ?>',
                },
                success: function (response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__('Your payment will be confirmed by our team. Thank you!', 'por-payment-gateway')); ?>');
                        button.prop('disabled', true).text('<?php echo esc_js(__('Please ignore if already paid.', 'por-payment-gateway')); ?>');
                    } else {
                        alert('<?php echo esc_js(__('Failed to confirm payment. Please try again.', 'por-payment-gateway')); ?>');
                        button.prop('disabled', false).text('<?php echo esc_js(__('I have completed the payment', 'por-payment-gateway')); ?>');
                    }
                },
                error: function () {
                    alert('<?php echo esc_js(__('An error occurred. Please try again.', 'por-payment-gateway')); ?>');
                    button.prop('disabled', false).text('<?php echo esc_js(__('I have completed the payment', 'por-payment-gateway')); ?>');
                }
            });
        });
    });
    </script>
    <?php

    echo '</div>'; // End WooCommerce wrapper
}

    /**
     * Handle AJAX request to update the order status.
     */
    function por_update_order_status() {
        if (!isset($_POST['order_id'])) {
            wp_send_json_error(['message' => __('Invalid order ID.', 'por-payment-gateway')]);
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'por-payment-gateway')]);
        }

        // Update order status and add note
        $order->update_status('on-hold', __('Payment confirmed by the user.', 'por-payment-gateway'));
        $order->add_order_note(__('Payment manually confirmed by the user via "I have completed the payment" button.', 'por-payment-gateway'));

        wp_send_json_success();
    }

    function resend_payment_link() {
        if (!isset($_POST['order_id']) || empty($_POST['order_id'])) {
            wp_send_json_error(['message' => __('Invalid order ID.', 'por-payment-gateway')]);
        }
    
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
    
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'por-payment-gateway')]);
        }
    
        $reference_number = $order->get_meta('_reference_number');
        if (!$reference_number) {
            wp_send_json_error(['message' => __('Reference number missing. Contact support.', 'por-payment-gateway')]);
        }
    
        try {
            $payment_gateway = new WC_POR_Payment_Gateway(); // Instantiate the gateway class
            $access_token = $payment_gateway->get_access_token();
            $api_domain = $payment_gateway->get_option('api_domain');
            $email_option_check = $order->get_meta('_payment_email_success') ? true : false;
            $phone_option_check = $order->get_meta('_payment_phone_success') ? true : false;
    
            // ... (rest of the API call code remains the same)
    
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]); // Send specific error message
        }
    }

    /**
     * Register the webhook endpoint with WordPress REST API.
     */
    function update_order_status_webhook_endpoint() {
        register_rest_route('por-payment/v1', '/webhook', [
            'methods'  => 'POST',
            'callback' => 'handle_webhook_request',
            'permission_callback' => '__return_true', // Allow public access (validate manually)
        ]);
    }

    /**
     * Handle incoming webhook requests.
     *
     * @param WP_REST_Request $request The request object containing webhook payload.
     * @return WP_REST_Response
     */
    function handle_webhook_request(WP_REST_Request $request) {
        $gateway_settings = get_option('woocommerce_por_gateway_settings');
        $webhook_secret = isset($gateway_settings['webhook_secret']) ? $gateway_settings['webhook_secret'] : '';
        $default_order_status = isset($gateway_settings['default_order_status']) ? $gateway_settings['default_order_status'] : '';

        $data = $request->get_json_params();
        $eventType = sanitize_text_field($data['eventType'] ?? '');
        $payload = $data['payload'] ?? [];
        $reference_number = sanitize_text_field($data['payload']['referenceNumber'] ?? '');
        $status = sanitize_text_field($data['payload']['status'] ?? '');
        $signature  = $request->get_header('X-Signature');

        error_log('default_order_status: ' . $default_order_status);
        error_log('eventType: ' . $eventType);
        error_log('reference_number: ' . $reference_number);
        error_log('status: ' . $status);
        error_log('signature: ' . $signature);

        // Get the raw body of the request
        $raw_body = $request->get_body();
        error_log('raw_body: ' . $raw_body);

        // Validate the signature based on the raw body
        $expected_signature = hash_hmac('sha256', $raw_body, $webhook_secret);
        error_log('expected_signature: ' . $expected_signature);

        if ($signature !== $expected_signature) {
            return new WP_REST_Response([
                'error' => true,
                'message' => 'Invalid signature.'
            ], 403);
        }

        // Log the request for debugging
        error_log('Webhook Received: ' . print_r($data, true));

        // Validate required data
        if (empty($reference_number) || empty($status) || empty($eventType)) {
            return new WP_REST_Response([
                'error' => true,
                'message' => 'Invalid payload. Missing reference number, status, or event type.',
            ], 400);
        }

        // Check eventType before proceeding with order update
        $valid_event_types = ['payment_success', 'payment_failure', 'payment_pending'];
        if (!in_array($eventType, $valid_event_types)) {
            return new WP_REST_Response([
                'error' => true,
                'message' => 'Invalid event type: ' . $eventType,
            ], 400);
        }

        // Find the WooCommerce order by reference number
        $orders = wc_get_orders([
            'meta_key'   => '_reference_number',
            'meta_value' => $reference_number,
            'limit'      => 1,
        ]);

        if (empty($orders)) {
            return new WP_REST_Response([
                'error' => true,
                'message' => 'Order not found.',
            ], 404);
        }

        $order = current($orders);

        try {
            if ($eventType === 'payment_success') {
                if (strcasecmp($status, 'APPROVED') === 0) {
                    $order->payment_complete();
                    $order->update_status($default_order_status, __('Payment completed via webhook.', 'por-payment-gateway'));
                } else {
                    $order->add_order_note(__('Webhook received an unrecognized status for payment_success event: ' . $status, 'por-payment-gateway'));
                    error_log('Unrecognized webhook status for payment_success event: ' . $status);
                    return new WP_REST_Response([
                        'error' => true,
                        'message' => 'Invalid status for payment_success event.',
                    ], 500);
                }
            } elseif ($eventType === 'payment_pending') {
                if (strcasecmp($status, 'PENDING') === 0) {
                    $order->update_status('pending', __('Payment is pending via webhook.', 'por-payment-gateway'));
                } else {
                    $order->add_order_note(__('Webhook received an unrecognized status for payment_pending event: ' . $status, 'por-payment-gateway'));
                    error_log('Unrecognized webhook status for payment_pending event: ' . $status);
                    return new WP_REST_Response([
                        'error' => true,
                        'message' => 'Invalid status for payment_pending event.',
                    ], 500);
                }
            } elseif ($eventType === 'payment_failure') {
                if (strcasecmp($status, 'REJECTED') === 0) {
                    $order->update_status('failed', __('Payment failed via webhook.', 'por-payment-gateway'));
                } else {
                    $order->add_order_note(__('Webhook received an unrecognized status for payment_failure event: ' . $status, 'por-payment-gateway'));
                    error_log('Unrecognized webhook status for payment_failure event: ' . $status);
                    return new WP_REST_Response([
                        'error' => true,
                        'message' => 'Invalid status for payment_failure event.',
                    ], 500);
                }
            }
            $order->save(); // Save the order only ONCE after status updates.

            $response = [
                'error' => false,
                'message' => 'Order updated successfully.',
            ];
            error_log('Response Data: ' . print_r($response, true));
            return new WP_REST_Response($response, 200);

        } catch (Exception $e) {
            $errorResponse = [
                'error' => true,
                'message' => 'Error: ' . $e->getMessage(),
            ];
            error_log('Error Response: ' . print_r($errorResponse, true));
            return new WP_REST_Response($errorResponse, 500);
        }
    }