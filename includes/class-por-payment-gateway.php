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
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_reference_number_in_admin']);

        // Webhhook for updating order status
        // add_action('rest_api_init', [$this, 'update_order_status_webhook_endpoint']);

        // add_action('wp_ajax_por_resend_payment_link', [$this, 'resend_payment_link']);
        // add_action('wp_ajax_nopriv_por_resend_payment_link', [$this, 'resend_payment_link']);

        // add_action('wp_ajax_handle_order_status_update', [$this, 'handle_order_status_update']);
        // add_action('wp_ajax_nopriv_handle_order_status_update', [$this, 'handle_order_status_update']);
        // add_action('woocommerce_thankyou', [$this, 'display_payment_instructions'], 10, 1);
        
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
            'default_order_status' => array(
                'title' => __( 'Order Status After The Checkout', 'por-payment-gateway' ),
                'type' => 'select',
                'options' => wc_get_order_statuses(),
                'default' => 'wc-processing',
                'description' 	=> __( 'The default order status if this gateway used in payment.', 'por-payment-gateway' ),
            ),
            'api_domain' => [
                'title'       => __('API Domain', 'por-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter the API domain for PayOnRamp integration.', 'por-payment-gateway'),
                'default'     => 'https://dev-api.payonramp.io',
                'desc_tip'    => true,
            ],
            'email' => [
                'title'       => __('Application Email', 'por-payment-gateway'),
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
            'app_secret' => [
                'title'       => __('Application Secret', 'por-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter the secret for the API.', 'por-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'webhook_secret' => [
                'title'       => __('Webhook Secret', 'por-payment-gateway'),
                'type'        => 'text',
                'description' => __('Enter the secret for the webhook used for payment status updates.', 'por-payment-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ],
        ];
    }

    public function process_admin_options() {
        // 1. Get the posted values FIRST
        $posted_values = [];
        foreach ($this->form_fields as $key => $field) {
            $posted_values[$key] = isset($_POST['woocommerce_por_gateway_' . $key]) ? wc_clean($_POST['woocommerce_por_gateway_' . $key]) : '';
        }
    
        // 2. Perform validation using the POSTED values
        $email = $posted_values['email'];
        $app_id = $posted_values['app_id'];
        $app_secret = $posted_values['app_secret'];
        $webhook_secret = $posted_values['webhook_secret'];
    
        if (empty($email) || empty($app_id) || empty($app_secret) || empty($webhook_secret)) {
            por_display_admin_notice(__('Please fill in all required API credentials (Email, Application ID, Application Secret, and Webhook Secret).'), 'error');
            return false; // Prevent saving settings
        }
    
        try {
            // Instantiate the gateway class to use get_access_token
            $payment_gateway = new WC_POR_Payment_Gateway();
    
            // TEMPORARILY set the options within the INSTANCE for get_access_token()
            $payment_gateway->settings['email'] = $email;
            $payment_gateway->settings['app_id'] = $app_id;
            $payment_gateway->settings['app_secret'] = $app_secret;
    
            $payment_gateway->get_access_token(); // Attempt to get the token
    
            // 3. Only if validation and token retrieval are successful, save the options
            foreach ($posted_values as $key => $value) {
                $this->update_option($key, $value); // Use $this to save the options
            }
    
            por_display_admin_notice(__('Settings saved and API credentials validated successfully.', 'success'));
            return true;
    
        } catch (Exception $e) {
            por_display_admin_notice(__('API credentials are invalid. Please check your settings: ' . $e->getMessage()), 'error');
            return false; // Prevent saving settings
        }
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
            echo '<p><input type="checkbox" id="por_phone" name="por_phone" checked> ' . __('Phone Number', 'por-payment-gateway') . '</p>';
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
            $order->update_meta_data('_payment_email_success', $response_body['email'] ?? false);
            $order->update_meta_data('_payment_phone_success', $response_body['phone'] || !$response_body['phone']['error']);
            $order->save();
            error_log('response_body-email: ' . $response_body['email'] ?? false);
            error_log('response_body-phone: ' . $response_body['phone'] || !$response_body['phone']['error']);

            // Set order status.
            $order->update_status('pending', __('Payment initiated. Awaiting user confirmation.', 'por-payment-gateway'));

            // Add order note.
            $order->add_order_note(__('Payment initiated by the user using PayOnRamp Payment Gateway', 'por-payment-gateway'));
            
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
     * Display payment instructions on the "Thank You" page.
     */
    public function display_payment_instructions($order_id) {
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
        $reference_number = $order->get_meta('_reference_number');
        if ($reference_number) {
            $order_id = $order->get_id();

            echo '<li class="woocommerce-notice woocommerce-notice--info">Click ';
            echo '<a href="#" id="por-resend-payment-link" style="color: #0073aa;"
                data-order-id="' . esc_attr($order_id) . '" >' . __('here', 'por-payment-gateway') . '</a>';
            echo ' to resend the payment link, if you did not receive any.';
            echo ' <span id="resend-timer" style="font-size: 0.9em; color: #555;"></span>';
            echo '</li>';
            
        }
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

        error_log('AJAX URL: ' . admin_url('admin-ajax.php'));

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
                        action: 'handle_order_status_update',
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

    public function resend_payment_link() {
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
            $access_token = $this->get_access_token();
            $api_domain = $this->get_option('api_domain');
            $email_option_check = $order->get_meta('_payment_email_success') ? true : false;
            $phone_option_check = $order->get_meta('_payment_phone_success') ? true : false;

            // Build the API request body
            $api_body = [
                'referenceNumber'    => $reference_number,
                'phoneNumber'        => $order->get_billing_phone(), 
                'emailOptionCheck'   => $email_option_check,
                'phoneNumberCheck'   => $phone_option_check,
            ];

            $response = wp_remote_post($api_domain . '/interac/initiate-resend', [
                'method'  => 'POST',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => json_encode($api_body),
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                throw new Exception(__('Failed to resend the payment link. Please try again.', 'por-payment-gateway'));
            }

            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($response_body['error'])) {
                throw new Exception($response_body['message'] ?? __('Failed to resend the payment link.', 'por-payment-gateway'));
            }

            wc_add_notice(__('The payment link has been resent successfully.', 'por-payment-gateway'), 'success');
            wp_send_json_success(['message' => __('Payment link resent successfully!', 'por-payment-gateway')]);

        } catch (Exception $e) {
            wc_add_notice(__('Failed to resend the payment link. Please try again later.', 'por-payment-gateway'), 'error');
            wp_send_json_error(['message' => $e->getMessage()]);
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

        error_log('email: ' . $email);
        error_log('app_id: ' . $app_id);
        error_log('app_secret: ' . $app_secret);
        error_log('api_domain: ' . $api_domain);

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
        add_action('admin_notices', function () use ($message, $type) {
            $class = ($type === 'success') ? 'notice-success' : 'notice-error';
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($message));
        });
    }
    
}
