<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_POR_Payment_Gateway extends WC_Payment_Gateway {

    private $order_status;

    public function __construct() {
        $this->id = 'por_gateway'; // Payment gateway ID
        $this->icon = 'https://etransfer-notification.interac.ca/images/new/interac_logo.png'; 
        $this->method_title = 'PayOnRamp Payment Gateway';
        $this->method_description = 'A custom payment gateway by PayOnRamp.';
        $this->has_fields = true; // Enable custom fields.

        // Supported features.
        $this->supports = array(
            'products',
        );

        // Initialize settings.
        $this->init_form_fields();
        $this->init_settings();

        // Retrieve settings values.
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->order_status = $this->get_option('order_status');

        // Save admin options.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_ajax_por_update_order_status', 'por_update_order_status');
        add_action('wp_ajax_nopriv_por_update_order_status', 'por_update_order_status');
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'label' => 'Enable POR Payment Gateway',
                'type' => 'checkbox',
                'default' => 'yes',
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'default' => 'PayOnRamp Payment',
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'default' => 'Pay securely via PayOnRamp.',
            ),
            'enable_qr_code' => array(
            'title' => 'Enable QR Code',
            'type' => 'checkbox',
            'label' => 'Allow payment via QR Code',
            'default' => 'yes',
            ),
            'enable_email' => array(
                'title' => 'Enable Email',
                'type' => 'checkbox',
                'label' => 'Allow payment via Email',
                'default' => 'yes',
            ),
            'enable_phone' => array(
                'title' => 'Enable Phone Number',
                'type' => 'checkbox',
                'label' => 'Allow payment via Phone Number',
                'default' => 'yes',
            ),
            'order_status' => array(
						'title' => __( 'Order Status After The Checkout', 'por-payment-gateway' ),
						'type' => 'select',
						'options' => wc_get_order_statuses(),
						'default' => 'wc-processing',
						'description' 	=> __( 'The default order status if this gateway used in payment.', 'por-payment-gateway' ),
					),
            'email' => array(
                'title' => 'Email',
                'type' => 'text',
                'description' => 'Enter the email to authenticate the API.',
                'default' => '',
                'desc_tip' => true,
            ),
            'app_id' => array(
                'title' => 'Application ID',
                'type' => 'text',
                'description' => 'Enter the Application ID for the API.',
                'default' => '',
                'desc_tip' => true,
            ),
            'secret' => array(
                'title' => 'Secret',
                'type' => 'text',
                'description' => 'Enter the secret for the API.',
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }

    public function payment_fields() {
        echo '<fieldset>';
        echo '<p><strong>Proceed to payment using:</strong></p>';
    
        // Display QR Code option if enabled.
        if ($this->get_option('enable_qr_code') === 'yes') {
            echo '<p><input type="checkbox" id="por_qr_code" name="por_qr_code" checked> QR Code</p>';
        }
    
        // Display Email option if enabled.
        if ($this->get_option('enable_email') === 'yes') {
            echo '<p><input type="checkbox" id="por_email" name="por_email" checked> Email</p>';
        }
    
        // Display Phone Number option if enabled.
        if ($this->get_option('enable_phone') === 'yes') {
            echo '<p><input type="checkbox" id="por_phone_number_check" name="por_phone_number_check"> Phone Number</p>';
        }
    
        echo '</fieldset>';
    
        // JavaScript to validate checkbox selection.
        ?>
        <script>
        jQuery(function($) {
            $('form.woocommerce-checkout').on('submit', function(e) {
                if (!$('#por_qr_code').is(':checked') && !$('#por_email').is(':checked') && !$('#por_phone_number_check').is(':checked')) {
                    e.preventDefault();
                    alert('Please select at least one payment method.');
                }
            });
        });
        </script>
        <?php
    }
    
    public function validate_fields() {
        $qr_code_enabled = $this->get_option('enable_qr_code') === 'yes';
        $email_enabled = $this->get_option('enable_email') === 'yes';
        $phone_enabled = $this->get_option('enable_phone') === 'yes';
    
        if ((!isset($_POST['por_qr_code']) || !$qr_code_enabled) &&
            (!isset($_POST['por_email']) || !$email_enabled) &&
            (!isset($_POST['por_phone_number_check']) || !$phone_enabled)) {
            wc_add_notice('Please select at least one payment method.', 'error');
            return false;
        }
        return true;
    }

    function por_update_order_status() {
        if (!isset($_POST['order_id'])) {
            wp_send_json_error(array('message' => 'Invalid order ID.'));
        }
    
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
    
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found.'));
        }
    
        $order->update_status('on-hold', __('Payment confirmed by user.', 'por-payment-gateway'));
        wp_send_json_success(array('redirect_url' => $order->get_checkout_order_received_url()));
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
    
        try {
            // Initiate the payment and generate access token
            $access_token = $this->get_access_token();
    
            $data = [
                'email' => $order->get_billing_email(),
                'amount' => (string) $order->get_total(),
                'name' => $order->get_billing_first_name() . '-' . $order->get_id(),
                'phoneNumber' => $order->get_billing_phone(),
                'phoneNumberCheck' => isset($_POST['por_phone_number_check']) && $_POST['por_phone_number_check'] === 'on',
                'emailOptionCheck' => isset($_POST['por_email']) && $_POST['por_email'] === 'on',
            ];
    
            $response = wp_remote_post('https://dev-api.payonramp.io/interac/initiate-deposit', [
                'method' => 'POST',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($data),
                'timeout' => 30,
            ]);
    
            if (is_wp_error($response)) {
                throw new Exception('Error initiating payment: ' . $response->get_error_message());
            }
    
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
            if ($response_body['error'] ?? true) {
                throw new Exception($response_body['message'] ?? 'Unknown error occurred.');
            }
    
            // Add order note and set status to pending
            $order->update_status('pending', __('Payment initiated. Awaiting user confirmation.', 'por-payment-gateway'));
            $order->add_order_note(__('Payment link sent or QR code displayed. Awaiting user confirmation.', 'por-payment-gateway'));
    
            // Return WooCommerce success response
            return [
                'result' => 'success',
                'modal' => true, // Trigger modal
                'data' => [
                    'qr_code' => $response_body['image'] ?? '',
                    'payment_link' => $response_body['link'] ?? '',
                    'email' => isset($data['email']),
                    'phone' => isset($data['phoneNumber']),
                ],
            ];
        } catch (Exception $e) {
            wc_add_notice(__('Payment failed: ', 'por-payment-gateway') . $e->getMessage(), 'error');
    
            return ['result' => 'failure']; // Failure response
        }
    }
    

    // public function process_payment($order_id) {
    //     $order = wc_get_order($order_id);
    
    //     // Retrieve the access token
    //     try {
    //         $access_token = $this->get_access_token();
    //     } catch (Exception $e) {
    //         wc_add_notice('Payment failed: ' . $e->getMessage(), 'error');
    //         return;
    //     }
    
    //     // Prepare data for the API call
    //     $data = array(
    //         'email' => $order->get_billing_email(),
    //         'amount' => (string) $order->get_total(),
    //         'name' => $order->get_billing_first_name() . '-' . $order->get_id(),
    //         'phoneNumber' => $order->get_billing_phone(),
    //         'phoneNumberCheck' => isset($_POST['por_phone_number_check']) && $_POST['por_phone_number_check'] === 'on',
    //         'emailOptionCheck' => isset($_POST['por_email']) && $_POST['por_email'] === 'on',
    //     );
    
    //     // Make the API call
    //     $response = wp_remote_post('https://dev-api.payonramp.io/interac/initiate-deposit', array(
    //         'method' => 'POST',
    //         'headers' => array(
    //             'Authorization' => 'Bearer ' . $access_token,
    //             'Content-Type' => 'application/json',
    //         ),
    //         'body' => json_encode($data),
    //         'timeout' => 30,
    //     ));
    
    //     if (is_wp_error($response)) {
    //         wc_add_notice('Payment failed: ' . $response->get_error_message(), 'error');
    //         return;
    //     }
    
    //     $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
    //     if (!$response_body['error'] && isset($response_body['response']['Message'])) {
    //         // Update the order status
    //         $order->update_status('on-hold', __('Awaiting payment confirmation.', 'woocommerce'));
    
    //         // Return custom result to trigger modal
    //         return array(
    //             'result' => 'success',
    //             'modal' => true,
    //             'data' => array(
    //                 'qr_code' => isset($response_body['image']) ? $response_body['image'] : '',
    //                 'payment_link' => isset($response_body['link']) ? $response_body['link'] : '',
    //                 'email' => isset($_POST['por_email']) && $_POST['por_email'] === 'on',
    //                 'phone' => isset($_POST['por_phone_number_check']) && $_POST['por_phone_number_check'] === 'on',
    //             ),
    //         );
    //     } else {
    //         wc_add_notice('Payment failed: ' . $response_body['message'], 'error');
    //         return;
    //     }
    // }
    

    private function get_access_token() {
        $email = $this->get_option('email');
        $app_id = $this->get_option('app_id');
        $secret = $this->get_option('secret');
    
        if (empty($email) || empty($app_id) || empty($secret)) {
            throw new Exception('API credentials are not set in the plugin settings.');
        }
    
        // Generate the hash using the provided script logic.
        $timestamp = round(microtime(true) * 1000);
        $data_to_encrypt = $app_id . ':' . $timestamp;
        $key = hash('sha256', $secret, true);
        $iv = random_bytes(16);
    
        $encrypted_data = openssl_encrypt($data_to_encrypt, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $encrypted_id = bin2hex($iv) . ':' . bin2hex($encrypted_data);
    
        // Make the API call to get the access token.
        $response = wp_remote_post('https://dev-api.payonramp.io/merchantlogin/interac/login', array(
            'method' => 'POST',
            'headers' => array(
                'email' => $email,
                'hash' => $encrypted_id,
            ),
        ));
        error_log('API Response: ' . print_r($response, true));
    
        if (is_wp_error($response)) {
            throw new Exception('Failed to get access token: ' . $response->get_error_message());
        }
    
        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Check for valid response structure.
        if (isset($body['data']['accessToken'])) {
            return $body['data']['accessToken'];
        }
    
        if (isset($body['message'])) {
            throw new Exception('Failed to get access token: ' . $body['message']);
        }
    
        throw new Exception('Failed to retrieve access token: Invalid response format.');
    }

}
