<?php
/**
 * PayPal Payment Gateway (REST API via WordPress HTTP API)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_PayPal')) {

    class SPP_PayPal implements SPP_Payment_Gateway {

        private function get_base_url(): string {
            $settings = get_option('spp_settings', []);
            $sandbox  = !empty($settings['spp_paypal_sandbox']);
            return $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        }

        private function get_access_token(): ?string {
            $settings  = get_option('spp_settings', []);
            $client_id = trim((string) ($settings['spp_paypal_client_id'] ?? ''));
            $secret    = trim((string) ($settings['spp_paypal_secret'] ?? ''));

            if ($client_id === '' || $secret === '') {
                return null;
            }

            $response = wp_remote_post($this->get_base_url() . '/v1/oauth2/token', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $secret),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body'    => ['grant_type' => 'client_credentials'],
                'timeout' => 15,
            ]);

            if (is_wp_error($response)) {
                return null;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            return $data['access_token'] ?? null;
        }

        /**
         * Create a PayPal Checkout Order and return the approval URL.
         *
         * @return string|WP_Error
         */
        public function create_session(array $plan, int $user_id) {
            $token = $this->get_access_token();
            if (!$token) {
                return new WP_Error('paypal_not_configured', __('PayPal is not configured. Please add your PayPal credentials in Settings.', 'sports-player-platform'));
            }

            $body = [
                'intent'              => 'CAPTURE',
                'purchase_units'      => [[
                    'custom_id'   => $user_id . ':' . $plan['id'],
                    'description' => 'Sports Player Platform - ' . $plan['name'],
                    'amount'      => [
                        'currency_code' => strtoupper($plan['currency'] ?? 'USD'),
                        'value'         => number_format((float) $plan['price'], 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'return_url' => add_query_arg(['spp_payment' => 'success', 'gateway' => 'paypal'], home_url('/dashboard/membership/')),
                    'cancel_url' => add_query_arg(['spp_payment' => 'cancelled'], home_url('/pricing/')),
                ],
            ];

            $response = wp_remote_post($this->get_base_url() . '/v2/checkout/orders', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($body),
                'timeout' => 20,
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($data['links']) && is_array($data['links'])) {
                foreach ($data['links'] as $link) {
                    if (($link['rel'] ?? '') === 'approve' && !empty($link['href'])) {
                        return (string) $link['href'];
                    }
                }
            }

            return new WP_Error('paypal_order_error', __('Unable to create PayPal checkout session.', 'sports-player-platform'));
        }

        /**
         * Handle PayPal webhook events.
         */
        public function handle_webhook(WP_REST_Request $request): WP_REST_Response {
            $event = json_decode($request->get_body(), true);
            if (!is_array($event) || empty($event['event_type'])) {
                return new WP_REST_Response(['error' => 'Invalid payload'], 400);
            }

            $type     = $event['event_type'];
            $resource = $event['resource'] ?? [];

            if ($type === 'PAYMENT.CAPTURE.COMPLETED' || $type === 'CHECKOUT.ORDER.APPROVED') {
                $custom_id = (string) ($resource['custom_id'] ?? ($resource['purchase_units'][0]['custom_id'] ?? ''));
                $parts     = explode(':', $custom_id);
                $user_id   = absint($parts[0] ?? 0);
                $plan_id   = sanitize_key($parts[1] ?? 'monthly');
                $txn_id    = sanitize_text_field($resource['id'] ?? '');
                $amount    = (float) ($resource['amount']['value'] ?? 0.00);
                $currency  = strtoupper((string) ($resource['amount']['currency_code'] ?? 'USD'));

                if ($user_id > 0 && class_exists('SPP_Membership')) {
                    $expires = $plan_id === 'annual'
                        ? gmdate('Y-m-d H:i:s', strtotime('+1 year'))
                        : gmdate('Y-m-d H:i:s', strtotime('+1 month'));

                    SPP_Membership::get_instance()->upgrade_membership($user_id, $plan_id, $expires, 'paypal');
                    SPP_Payment::log_payment($user_id, 'paypal', $txn_id, $amount, $currency, 'completed', null, $event);
                }
            }

            return new WP_REST_Response(['received' => true], 200);
        }

        /**
         * Cancel a PayPal subscription by ID.
         */
        public function cancel_subscription(string $sub_id): bool {
            $token = $this->get_access_token();
            if (!$token || $sub_id === '') {
                return false;
            }

            $response = wp_remote_post($this->get_base_url() . '/v1/billing/subscriptions/' . rawurlencode($sub_id) . '/cancel', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode(['reason' => 'Cancelled by user']),
                'timeout' => 15,
            ]);

            return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
        }
    }
}
