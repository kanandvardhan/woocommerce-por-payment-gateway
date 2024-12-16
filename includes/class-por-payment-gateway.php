<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class WC_POR_Payment_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'por_gateway'; // Payment gateway ID.
        $this->icon = 'https://etransfer-notification.interac.ca/images/new/interac_logo.png'; 
        $this->method_title = __('PayOnRamp Payment Gateway', 'por-payment-gateway');
        $this->method_description = __('A custom payment gateway by PayOnRamp.', 'por-payment-gateway');
        $this->has_fields = true; // Enable custom fields on the checkout page.

        // Supported WooCommerce features.
        $this->supports = ['products'];

        // Initialize settings.
        $this->init_form_fields();
        $this->init_settings();

        // Load settings.
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Hook for saving settings in the admin panel.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

    }

    /**
     * Initialize form fields for the admin panel.
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'por-payment-gateway'),
                'label'   => __('Enable POR Payment Gateway', 'por-payment-gateway'),
                'type'    => 'checkbox',
                'default' => 'yes',
            ],
            'title' => [
                'title'   => __('Title', 'por-payment-gateway'),
                'type'    => 'text',
                'default' => __('PayOnRamp Payment', 'por-payment-gateway'),
            ],
            'description' => [
                'title'   => __('Description', 'por-payment-gateway'),
                'type'    => 'textarea',
                'default' => __('Pay securely via PayOnRamp.', 'por-payment-gateway'),
            ],
            'enable_qr_code' => [
            'title'   => __('Enable QR Code', 'por-payment-gateway'),
            'type'    => 'checkbox',
            'label'   => __('Allow payment via QR Code', 'por-payment-gateway'),
            'default' => 'yes',
            'custom_attributes' => [
                'disabled' => 'disabled', 
                ],
            ],
            'enable_email' => [
                'title'   => __('Enable Email', 'por-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Allow payment via Email', 'por-payment-gateway'),
                'default' => 'yes',
            ],
            'enable_phone' => [
                'title'   => __('Enable Phone Number', 'por-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Allow payment via Phone Number', 'por-payment-gateway'),
                'default' => 'yes',
            ],
            'email' => [
                'title'       => __('Email', 'por-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter the email to authenticate the API.', 'por-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'app_id' => [
                'title'       => __('Application ID', 'por-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter the Application ID for the API.', 'por-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'secret' => [
                'title'       => __('Secret', 'por-payment-gateway'),
                'type'        => 'password',
                'description' => __('Enter the secret for the API.', 'por-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ],
        ];
    }


    /**
     * Display payment fields on the checkout page.
     */
    public function payment_fields() {
        echo '<fieldset>';
        echo '<p><strong>' . __('Proceed to payment using:', 'por-payment-gateway') . '</strong></p>';

        if ($this->get_option('enable_qr_code') === 'yes') {
            echo '<p><input type="checkbox" id="por_qr_code" name="por_qr_code" checked disabled> ' . __('QR Code', 'por-payment-gateway') . '</p>';
        }

        if ($this->get_option('enable_email') === 'yes') {
            echo '<p><input type="checkbox" id="por_email" name="por_email" checked> ' . __('Email', 'por-payment-gateway') . '</p>';
        }

        if ($this->get_option('enable_phone') === 'yes') {
            echo '<p><input type="checkbox" id="por_phone" name="por_phone"> ' . __('Phone Number', 'por-payment-gateway') . '</p>';
        }

        echo '</fieldset>';
    }

    /**
     * Validate the payment fields.
     */
    public function validate_fields() {
        // Check if at least one option is selected
        $email_checked = isset($_POST['por_email']);
        $phone_checked = isset($_POST['por_phone']);

        if (!$email_checked && !$phone_checked) {
            wc_add_notice(__('Please select at least one payment method (Email or Phone).', 'por-payment-gateway'), 'error');
            return false;
        }

        return true;
    }


    /**
     * Process the payment.
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        try {
            // Generate access token and make API call.
            $access_token = $this->get_access_token();
            $data = [
                'email'            => $order->get_billing_email(),
                'amount'           => (string) $order->get_total(),
                'name'             => $order->get_billing_first_name() . '-' . $order->get_id(),
                'phoneNumber'      => $order->get_billing_phone(),
                'phoneNumberCheck' => isset($_POST['por_phone']),
                'emailOptionCheck' => isset($_POST['por_email']),
            ];

            $response = wp_remote_post('https://dev-api.payonramp.io/interac/initiate-deposit', [
                'method'  => 'POST',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => json_encode($data),
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            if (!empty($response_body['error'])) {
                throw new Exception($response_body['message'] ?? __('Unknown error occurred.', 'por-payment-gateway'));
            }

            // Save payment data to order meta.
            $order->update_meta_data('_qr_code', $response_body['image'] ?? '');
            $order->update_meta_data('_payment_link', $response_body['link'] ?? '');
            $order->update_meta_data('_payment_email_success', $response_body['email'] ?? false);
            $order->update_meta_data('_payment_phone_success', !$response_body['phone']['error']);
            $order->save();

            // Set order status.
            $order->update_status('pending', __('Payment initiated. Awaiting user confirmation.', 'por-payment-gateway'));

            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order) . '&payment_status=awaiting_payment',
            ];

        } catch (Exception $e) {
            wc_add_notice(__('Payment failed: ', 'por-payment-gateway') . $e->getMessage(), 'error');
            return ['result' => 'failure'];
        }
    }
    

    /**
     * Retrieve access token for API.
     */
    private function get_access_token() {
        $email = $this->get_option('email');
        $app_id = $this->get_option('app_id');
        $secret = $this->get_option('secret');

        if (!$email || !$app_id || !$secret) {
            throw new Exception(__('Missing API credentials.', 'por-payment-gateway'));
        }

        $timestamp = round(microtime(true) * 1000);
        $data_to_encrypt = $app_id . ':' . $timestamp;
        $key = hash('sha256', $secret, true);
        $iv = random_bytes(16);

        $encrypted_data = openssl_encrypt($data_to_encrypt, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $hash = bin2hex($iv) . ':' . bin2hex($encrypted_data);

        $response = wp_remote_post('https://dev-api.payonramp.io/merchantlogin/interac/login', [
            'method'  => 'POST',
            'headers' => [
                'email' => $email,
                'hash'  => $hash,
            ],
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['data']['accessToken'])) {
            throw new Exception($body['message'] ?? __('Failed to retrieve access token.', 'por-payment-gateway'));
        }

        return $body['data']['accessToken'];
    }
}
