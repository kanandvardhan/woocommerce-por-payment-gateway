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
// add_action('wp_ajax_por_update_order_status', 'por_update_order_status');
// add_action('wp_ajax_nopriv_por_update_order_status', 'por_update_order_status');
// add_action('woocommerce_thankyou', 'por_payment_instructions', 10, 1);

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

/**
 * Display payment instructions on the "Thank You" page.
 */
// function por_payment_instructions($order_id) {
//     $order = wc_get_order($order_id);

//     if (!$order) {
//         return; // Order doesn't exist.
//     }

//     // Check if the payment status is awaiting payment.
//     if (!isset($_GET['payment_status']) || $_GET['payment_status'] !== 'awaiting_payment') {
//         return;
//     }

//     // Start WooCommerce wrapper
//     echo '<div class="woocommerce-order" style="max-width: 600px; margin: 3rem 0;">';
//     echo '<h2 class="woocommerce-order-details__title">' . __('Complete Your Payment', 'por-payment-gateway') . '</h2>';

//     $qr_code = $order->get_meta('_qr_code');
//     error_log('QR Code URL: ' . $qr_code); // Log the QR code URL.

//     $qr_code = $order->get_meta('_qr_code');
//     if ($qr_code) {
//         // Check if it's a base64 encoded string
//         if (strpos($qr_code, 'data:image') === 0) {
//             echo '<p>' . __('Scan the QR code below to complete your payment:', 'por-payment-gateway') . '</p>';
//             echo '<img src="' . esc_attr($qr_code) . '" alt="QR Code" style="max-width:200px; margin:10px auto; display:block;">';
//         } else {
//             // Treat as a URL
//             echo '<p>' . __('Scan the QR code below to complete your payment:', 'por-payment-gateway') . '</p>';
//             echo '<img src="' . esc_url($qr_code) . '" alt="QR Code" style="max-width:200px; margin:10px auto; display:block;">';
//         }
//     } else {
//         echo '<p>' . __('QR Code could not be retrieved. Please contact support.', 'por-payment-gateway') . '</p>';
//     }

//     echo '<p style="text-align: center;">' . __('OR', 'por-payment-gateway') . '</p>';

//     // Display payment link
//     $payment_link = $order->get_meta('_payment_link');
//     if ($payment_link) {
//         echo '<div class="woocommerce-notice woocommerce-notice--info">';
//         echo '<p style="text-align: center;"><a href="' . esc_url($payment_link) . '" target="_blank" class="button">' . __('Click here to complete your payment', 'por-payment-gateway') . '</a></p>';
//         echo '</div>';
//     }

//     // Display dynamic messages
//     $email_success = $order->get_meta('_payment_email_success');
//     $phone_success = $order->get_meta('_payment_phone_success');

//     if ($email_success && $phone_success) {
//         echo '<li class="woocommerce-notice woocommerce-notice--info">' . __('A payment link has been sent to your email and phone number. Please complete the payment to finish your order.', 'por-payment-gateway') . '</li>';
//     } elseif ($email_success) {
//         echo '<li class="woocommerce-notice woocommerce-notice--info">' . __('A payment link has been sent to your email. Please complete the payment to finish your order.', 'por-payment-gateway') . '</li>';
//     } elseif ($phone_success) {
//         echo '<li class="woocommerce-notice woocommerce-notice--info">' . __('A payment link has been sent to your phone number. Please complete the payment to finish your order.', 'por-payment-gateway') . '</li>';
//     }

//     echo '<p>' . __('Once the payment is complete, click the button below to confirm.', 'por-payment-gateway') . '</p>';
    

//     // Display confirmation button
//     echo '<div class="form-row form-row-wide" margin-top:20px;">';
//     echo '<button id="por-payment-confirm-btn" class="button alt">' . __('I have completed the payment', 'por-payment-gateway') . '</button>';
//     echo '</div>';

//     // End WooCommerce wrapper
//     echo '</div>';

//     // Include JavaScript for button functionality
//     ?>
//     <script>
//     jQuery(function ($) {
//         $('#por-payment-confirm-btn').on('click', function () {
//             var button = $(this);
//             button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'por-payment-gateway')); ?>');

//             $.ajax({
//                 url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
//                 method: 'POST',
//                 data: {
//                     action: 'por_update_order_status',
//                     order_id: '<?php echo esc_js($order_id); ?>',
//                 },
//                 success: function (response) {
//                     if (response.success) {
//                         alert('<?php echo esc_js(__('Your payment will be confirmed by our team. Thank you!', 'por-payment-gateway')); ?>');
//                         button.prop('disabled', true).text('<?php echo esc_js(__('Please ignore if already payed.', 'por-payment-gateway')); ?>');
//                         // location.reload();
//                     } else {
//                         alert('<?php echo esc_js(__('Failed to confirm payment. Please try again.', 'por-payment-gateway')); ?>');
//                         button.prop('disabled', false).text('<?php echo esc_js(__('I have completed the payment', 'por-payment-gateway')); ?>');
//                     }
//                 },
//                 error: function () {
//                     alert('<?php echo esc_js(__('An error occurred. Please try again.', 'por-payment-gateway')); ?>');
//                     button.prop('disabled', false).text('<?php echo esc_js(__('I have completed the payment', 'por-payment-gateway')); ?>');
//                 }
//             });
//         });
//     });
//     </script>
//     <?php
// }


/**
 * Handle AJAX request to update the order status.
 */
// function por_update_order_status() {
//     if (!isset($_POST['order_id'])) {
//         wp_send_json_error(['message' => __('Invalid order ID.', 'por-payment-gateway')]);
//     }

//     $order_id = intval($_POST['order_id']);
//     $order = wc_get_order($order_id);

//     if (!$order) {
//         wp_send_json_error(['message' => __('Order not found.', 'por-payment-gateway')]);
//     }

//     // Update order status and add note
//     $order->update_status('on-hold', __('Payment confirmed by the user.', 'por-payment-gateway'));
//     $order->add_order_note(__('Payment manually confirmed by the user via "I have completed the payment" button.', 'por-payment-gateway'));

//     wp_send_json_success();
// }
