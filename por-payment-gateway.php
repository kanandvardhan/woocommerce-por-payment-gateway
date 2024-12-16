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

if ( ! defined( 'ABSPATH' ) ) exit;

// Define constants.
define( 'POR_PAYMENT_GATEWAY_VERSION', '1.0.1' );
define( 'POR_PAYMENT_GATEWAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'POR_PAYMENT_GATEWAY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Load the plugin's text domain.
 */
add_action( 'plugins_loaded', 'por_payment_gateway_load_textdomain' );

function por_payment_gateway_load_textdomain() {
    load_plugin_textdomain( 'por-payment-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * WooCommerce dependency check.
 */
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'admin_notices', 'por_payment_gateway_woocommerce_notice' );
} else {
    add_action( 'plugins_loaded', 'por_payment_gateway_init' );
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'por_payment_gateway_settings_link' );
    add_filter( 'woocommerce_payment_gateways', 'add_por_gateway_class' );
    add_action('wp_enqueue_scripts', 'por_enqueue_custom_scripts');
    add_action('wp_footer', 'por_add_payment_modal');

}

/**
 * Initialize the gateway.
 */
function por_payment_gateway_init() {
    if ( ! class_exists( 'WC_POR_Payment_Gateway' ) ) {
        if ( file_exists( POR_PAYMENT_GATEWAY_PLUGIN_PATH . '/includes/class-por-payment-gateway.php' ) ) {
            include_once POR_PAYMENT_GATEWAY_PLUGIN_PATH . '/includes/class-por-payment-gateway.php';
        } else {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'POR Payment Gateway: Missing required files.', 'por-payment-gateway' ) . '</p></div>';
            } );
        }
    }
}

/**
 * Add POR Gateway to WooCommerce.
 */
function add_por_gateway_class( $gateways ) {
    $gateways[] = 'WC_POR_Payment_Gateway';
    return $gateways;
}

/**
 * Add settings link to plugin actions.
 */
function por_payment_gateway_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=por_gateway">' . __( 'Settings', 'por-payment-gateway' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

/**
 * Admin notice if WooCommerce is inactive.
 */
function por_payment_gateway_woocommerce_notice() {
    echo '<div class="notice notice-error is-dismissible">';
    echo '<p>' . esc_html__( 'POR Payment Gateway requires WooCommerce to be installed and active.', 'por-payment-gateway' ) . '</p>';
    echo '</div>';
}

/**
 * Log messages for debugging.
 */
function por_log( $message ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $logger = wc_get_logger();
        $logger->debug( $message, array( 'source' => 'por-payment-gateway' ) );
    }
}


function por_enqueue_custom_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('por-custom-js', POR_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/js/por-custom.js', array('jquery'), POR_PAYMENT_GATEWAY_VERSION, true);

    // Pass PHP variables to JavaScript
    wp_localize_script('por-custom-js', 'por_gateway_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'order_id' => get_query_var('order-received'), // Use actual order ID if available
    ));

    wp_enqueue_style('por-custom-css', POR_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/css/por-custom.css', array(), POR_PAYMENT_GATEWAY_VERSION);
}

function por_add_payment_modal() {
    // Render the modal only on the WooCommerce checkout page
    if (is_checkout() && !is_order_received_page()) {
        ?>
        <div id="por-payment-modal">
            <div class="por-modal-content">
                <div id="por-modal-body">
                    <p id="payment-instructions"></p>
                    <img id="qr-code-image" src="" alt="QR Code" style="display:none; max-width:200px; margin: 20px auto;">
                    <button id="por-payment-confirm-btn" class="button">I have completed the payment</button>
                </div>
            </div>
        </div>
        <?php
    }
}
