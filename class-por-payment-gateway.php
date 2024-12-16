<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_POR_Gateway extends WC_Payment_Gateway {

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
						'title' => __( 'Order Status After The Checkout', 'woocommerce-other-payment-gateway' ),
						'type' => 'select',
						'options' => wc_get_order_statuses(),
						'default' => 'wc-processing',
						'description' 	=> __( 'The default order status if this gateway used in payment.', 'woocommerce-other-payment-gateway' ),
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
    

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
    
        // Retrieve the access token.
        try {
            $access_token = $this->get_access_token();
        } catch (Exception $e) {
            wc_add_notice('Payment failed: ' . $e->getMessage(), 'error');
            return;
        }
    
        // Prepare data for the final API call.
        $data = array(
            'email' => $order->get_billing_email(),
            'amount' => (string) $order->get_total(), // Ensure amount is a string
            'name' => $order->get_billing_first_name() . '-' . $order->get_id(),
            'phoneNumber' => $order->get_billing_phone(), // Use phone from billing details
            'phoneNumberCheck' => isset($_POST['por_phone_number_check']) && $_POST['por_phone_number_check'] === 'on',
            'emailOptionCheck' => isset($_POST['por_email']) && $_POST['por_email'] === 'on',
        );
    
        // Make the final payment API call.
        $response = wp_remote_post('https://dev-api.payonramp.io/interac/initiate-deposit', array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                
            ),
            'body' => json_encode($data),
            'timeout' => 30,
        ));
    
        if (is_wp_error($response)) {
            error_log('API Error: ' . $response->get_error_message());
            error_log('Request Data: ' . print_r($data, true));
            wc_add_notice('Payment failed: ' . $response->get_error_message(), 'error');
            return;
        }

         error_log('API Response: ' . print_r($response, true));

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
        if (!$response_body['error'] && isset($response_body['response']['Message'])) {
            // Render QR code if selected.
            if (isset($_POST['por_qr_code']) && $_POST['por_qr_code'] === 'on') {
                wc_add_notice('Scan the QR code below to complete your payment.', 'notice');
                wc_add_notice('<img src="' . esc_url($response_body['image']) . '" alt="QR Code" style="max-width:200px; margin-top:10px;">', 'notice');
            }
    
            // Show messages for email or phone link.
            if (isset($_POST['por_email']) && $_POST['por_email'] === 'on') {
                wc_add_notice('A payment link has been sent to your email. Please complete it to finish your order.', 'notice');
            }
    
            if (isset($_POST['por_phone_number_check']) && $_POST['por_phone_number_check'] === 'on') {
                if (!$response_body['phone']['error']) {
                    wc_add_notice('A payment link has been sent to your phone number. Please complete it to finish your order.', 'notice');
                } else {
                    wc_add_notice('Unable to send payment link to your phone: ' . $response_body['phone']['message'], 'error');
                }
            }
    
            // Update the order note for admin reference.
            $order->add_order_note('Transaction initiated. Reference Number: ' . $response_body['response']['ReferenceNumber']);
    
            // Temporarily set the order status to "on-hold".
            $order->update_status('on-hold', __('Awaiting payment confirmation.', 'woocommerce'));
    
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        } else {
            wc_add_notice('Payment failed: ' . $response_body['message'], 'error');
            return;
        }
    }
    

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
