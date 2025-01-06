<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class WC_POR_Payment_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        
        $this->id = 'por_gateway'; // Payment gateway ID.
        $this->icon = POR_PAYMENT_GATEWAY_PLUGIN_URL . 'assets/images/interac_logo_legacy.png';
        $this->method_title = __('PayOnRamp Payment Gateway', 'por-payment-gateway');
        $this->method_description = __('Enable seamless and secure payments with the PayOnRamp Gateway, providing flexible payment options leveraging Interac® for an optimized checkout experience.', 'por-payment-gateway');
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
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_reference_number_in_admin']);
        
    }

    /**
     * Initialize form fields for the admin panel.
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'por-payment-gateway'),
                'label'   => __('Enable PayOnRamp Payment Gateway', 'por-payment-gateway'),
                'type'    => 'checkbox',
                'default' => 'no',
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
            // 'enable_qr_code' => [
            // 'title'   => __('Enable QR Code', 'por-payment-gateway'),
            // 'type'    => 'checkbox',
            // 'label'   => __('Allow payment via QR Code', 'por-payment-gateway'),
            // 'default' => 'yes',
            // 'required' => true,
            // 'custom_attributes' => [
            //     'disabled' => 'disabled', 
            //     ],
            // ],
            'enable_email' => [
                'title'   => __('Enable/Disable Email', 'por-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Allow sending payment link via Email', 'por-payment-gateway'),
                'default' => 'yes',
            ],
            'enable_phone' => [
                'title'   => __('Enable/Disable Phone Number', 'por-payment-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Allow sending payment link via Phone Number', 'por-payment-gateway'),
                'default' => 'yes',
            ],
            'default_order_status' => array(
                'title' => __( 'Order Status After Successful Payment', 'por-payment-gateway' ),
                'type' => 'select',
                'options' => wc_get_order_statuses(),
                'default' => 'wc-processing',
                'description' 	=> __( 'The default order status if this gateway used in payment.', 'por-payment-gateway' ),
                // 'required' => true
            ),
            'api_domain' => [
                'title'       => __('API Domain', 'por-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter the API domain for PayOnRamp payment integration. (Required)', 'por-payment-gateway'),
                'default'     => 'https://dev-api.payonramp.io',
                'desc_tip'    => true,
                // 'required' => true
            ],
            'email' => [
                'title'       => __('Application Email', 'por-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter the email to authenticate the API. (Required)', 'por-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
                // 'required' => true
            ],
            'app_id' => [
                'title'       => __('Application ID', 'por-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter the Application ID for the API. (Required)', 'por-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
                // 'required' => true
            ],
            'app_secret' => [
                'title'       => __('Application Secret', 'por-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter the secret for the API. (Required)', 'por-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
                // 'required' => true
            ],
            'webhook_secret' => [
                'title'       => __('Webhook Secret', 'por-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter the secret for the webhook used for payment status updates. (Required)', 'por-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
                // 'required' => true
            ],
        ];
    }

    public function process_admin_options() {
        // Get posted values first
        $posted_values = [];
        foreach ($this->form_fields as $key => $field) {
            if (isset($_POST['woocommerce_por_gateway_' . $key])) {
                switch ($field['type']) {
                    case 'checkbox':
                        $posted_values[$key] = isset($_POST['woocommerce_por_gateway_' . $key]) ? 'yes' : 'no';
                        break;
                    case 'textarea':
                        $posted_values[$key] = sanitize_textarea_field($_POST['woocommerce_por_gateway_' . $key]);
                        break;
                    case 'text':
                    default:
                        $posted_values[$key] = sanitize_text_field($_POST['woocommerce_por_gateway_' . $key]);
                        break;
                }
            } else {
                $posted_values[$key] = '';
            }
        }
    
        // Validate required fields
        $api_domain = $posted_values['api_domain'];
        $email = $posted_values['email'];
        $app_id = $posted_values['app_id'];
        $app_secret = $posted_values['app_secret'];
        $webhook_secret = $posted_values['webhook_secret'];
    
        // Ensure at least one payment method is enabled
        // $enable_qr_code = !empty($posted_values['enable_qr_code']);
        $enable_email = !empty($posted_values['enable_email']);
        $enable_phone = !empty($posted_values['enable_phone']);

        if (!$enable_email && !$enable_phone) {
            $this->update_option('enabled', 'no');
            por_display_admin_notice('Atleast one payment option Email or Phone must be enabled.', 'error');
            return false;
        }

        if (empty($api_domain) || empty($email) || empty($app_id) || empty($app_secret) || empty($webhook_secret)) {
            $this->update_option('enabled', 'no');
            por_display_admin_notice('Please fill in all required API credentials (API Domain, Email, Application ID, Application Secret, and Webhook Secret).', 'error');
            return false;
        }
    
        try {
            // Temporarily set instance variables for token validation
            $this->settings['api_domain'] = $api_domain;
            $this->settings['email'] = $email;
            $this->settings['app_id'] = $app_id;
            $this->settings['app_secret'] = $app_secret;
    
            // Attempt to get access token
            $this->get_access_token();
    
            // Save settings using WooCommerce's method
            if (parent::process_admin_options()) {
                por_display_admin_notice('Settings saved and API credentials validated successfully.', 'success');
                return true;
            }
        } catch (Exception $e) {
             // Disable the payment gateway
            $this->update_option('enabled', 'no');

            // Display error notice if API validation fails
            por_display_admin_notice('API credentials are invalid. Please check your settings: ' . $e->getMessage(), 'error');
            return true; 
        }
    }
    

    /**
     * Display payment fields on the checkout page.
     */
    public function payment_fields() {
        echo '<fieldset>';
        echo '<p><strong>' . __('Proceed to payment using:', 'por-payment-gateway') . '</strong></p>';

        echo '<p><input type="checkbox" id="por_qr_code" name="por_qr_code" checked disabled> ' . __('QR Code', 'por-payment-gateway') . '</p>';

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
            wc_add_notice(__('Please select atleast one additional payment option (Email or Phone).', 'por-payment-gateway'), 'error');
            return false;
        }

        return true;
    }

    /**
     * Display the reference number in the admin order details section.
     *
     * @param WC_Order $order The current order object.
     */
    public function display_reference_number_in_admin($order) {
        $reference_number = $order->get_meta('_reference_number');

        if ($reference_number) {
            ?>
            <div class="order_data_column" style="width: 100%;">
                <h4><?php esc_html_e('PayOnRamp Payment Details', 'por-payment-gateway'); ?></h4>
                <p>
                    <strong><?php esc_html_e('Reference Number:', 'por-payment-gateway'); ?></strong>
                    <span><?php echo esc_html($reference_number); ?></span>
                </p>
            </div>
            <?php
        }
    }


    /**
     * Process the payment.
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $api_domain = $this->get_option('api_domain');

        // Optionally, check if a payment is already in progress
        $payment_initiated = $order->get_meta('_reference_number');
        if ($payment_initiated) {
            $payment_url = $this->get_return_url($order) . '&payment_status=awaiting_payment';

            // Add a notice with a link to continue the previous payment
            wc_add_notice(
                sprintf(
                    __('Payment for this order has already been initiated. %s', 'por-payment-gateway'),
                    '<a href="' . esc_url($payment_url) . '" class="button-link">' . __('Continue Payment', 'por-payment-gateway') . '</a>'
                ),
                'notice'
            );

            return ['result' => 'failure'];
        }

        try {

            // Ensure phone number includes country code
            $phone = $order->get_billing_phone();
            if (!preg_match('/^\+/', $phone)) { // Check if phone number starts with '+'
                $phone = '+1' . ltrim($phone, '0'); // Append +1 and remove leading zero if exists
            }

            // Generate access token and make API call.
            $access_token = $this->get_access_token();
            $data = [
                'email'            => $order->get_billing_email(),
                'amount'           => (string) $order->get_total(),
                'name'             => $order->get_billing_first_name() . '-' . $order->get_id(),
                'phoneNumber'      => $phone,
                'phoneNumberCheck' => isset($_POST['por_phone']),
                'emailOptionCheck' => isset($_POST['por_email']),
            ];

            $response = wp_remote_post($api_domain . '/interac/initiate-deposit', [
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
            $order->update_meta_data('_reference_number', $response_body['response']['ReferenceNumber'] ?? '');
            $order->update_meta_data('_qr_code', $response_body['image'] ?? '');
            $order->update_meta_data('_payment_link', $response_body['link'] ?? '');
            $order->update_meta_data('_payment_option_email', isset($_POST['por_email']) ? true : false);
            $order->update_meta_data('_payment_option_phone', isset($_POST['por_phone']) ? true : false);
            $order->save();

            // Add order note.
            $order->add_order_note(__('Payment initiated by the user using PayOnRamp Payment Gateway. ~Processed by PayOnRamp.', 'por-payment-gateway'));

            // Set order status.
            $order->update_status('on-hold', __('Payment initiated. Awaiting user confirmation. ~Processed by PayOnRamp.', 'por-payment-gateway'));
            
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
        $app_secret = $this->get_option('app_secret');
        $api_domain = $this->get_option('api_domain');

        if (!$email || !$app_id || !$app_secret) {
            throw new Exception(__('Missing API credentials.', 'por-payment-gateway'));
        }

        $timestamp = round(microtime(true) * 1000);
        $data_to_encrypt = $app_id . ':' . $timestamp;
        $key = hash('sha256', $app_secret, true);
        $iv = random_bytes(16);

        $encrypted_data = openssl_encrypt($data_to_encrypt, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $hash = bin2hex($iv) . ':' . bin2hex($encrypted_data);

        $response = wp_remote_post($api_domain .'/merchantlogin/interac/login', [
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

   /**
     * Display an admin notice.
     *
     * @param string $message The message to display.
     * @param string $type    The notice type ('success' or 'error').
     */
    function por_display_admin_notice($message, $type = 'error') {
        $type = trim(strtolower($type)) ?: 'error'; // Normalize and default to 'error'
        add_action('admin_notices', function () use ($message, $type) {
            $class = ($type === 'success') ? 'notice-success' : 'notice-error';
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($message));
        });
    }

    
}
