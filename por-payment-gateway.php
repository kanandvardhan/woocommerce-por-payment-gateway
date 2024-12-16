<?php
/*
 * Plugin Name: POR Payment Gateway
 * Plugin URI: https://payonramp.io
 * Description: A custom payment gateway for WooCommerce by PayOnRamp.
 * Author: PayOnRamp
 * Author URI: https://payonramp.io
 * Version: 1.0.0
 * text-domain: por-payment-gateway
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit('You must not access this file directly.');
}

echo 'Am working';

// Hook into WooCommerce to load the gateway class after plugins are loaded.
add_action('plugins_loaded', 'por_init_gateway_class');

if (file_exists(plugin_dir_path(__FILE__) . 'webhook-handler.php')) {
    include_once plugin_dir_path(__FILE__) . 'webhook-handler.php';
}

function por_init_gateway_class() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Include the gateway class.
    include_once 'class-por-payment-gateway.php';

    // Register the gateway with WooCommerce.
    add_filter('woocommerce_payment_gateways', 'por_add_gateway_class');
}

function por_add_gateway_class($gateways) {
    $gateways[] = 'WC_POR_Gateway'; // Your class name.
    return $gateways;
}

