<?php
/**
 * Stripe Payment Gateway (WordPress HTTP API)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Stripe')) {

    class SPP_Stripe implements SPP_Payment_Gateway {

        private const API_BASE = 'https://api.stripe.com/v1';

        private function get_secret_key(): string {
            $settings = get_option('spp_settings', []);
            return trim((string) ($settings['spp_stripe_secret_key'] ?? ''));
        }

        private function get_webhook_secret(): string {
            $settings = get_option('spp_settings', []);
            return trim((string) ($settings['spp_stripe_webhook_secret'] ?? ''));
        }

        /**
         * Create a Stripe Checkout Session for a subscription plan.
         *
         * @return string|WP_Error
         */
        public function create_session(array $plan, int $user_id) {
            $secret_key = $this->get_secret_key();
            if ($secret_key === '') {
                return new WP_Error('stripe_not_configured', __('Stripe is not configured. Please add your Stripe API keys in Settings.', 'sports-player-platform'));
            }

            $user = get_userdata($user_id);
            if (!$user) {
                return new WP_Error('invalid_user', __('Invalid user account.', 'sports-player-platform'));
            }

            $unit_amount = (int) round(((float) $plan['price']) * 100);
            $interval    = $plan['interval'] === 'year' ? 'year' : 'month';

            $body = [
                'mode'                       => 'subscription',
                'success_url'                => add_query_arg(['spp_payment' => 'success'], home_url('/dashboard/membership/')),
                'cancel_url'                 => add_query_arg(['spp_payment' => 'cancelled'], home_url('/pricing/')),
                'customer_email'             => $user->user_email,
                'client_reference_id'        => (string) $user_id,
                'metadata[user_id]'          => (string) $user_id,
                'metadata[plan_id]'          => (string) $plan['id'],
                'line_items[0][quantity]'    => 1,
                'line_items[0][price_data][currency]'                  => strtolower($plan['currency'] ?? 'usd'),
                'line_items[0][price_data][unit_amount]'               => $unit_amount,
                'line_items[0][price_data][product_data][name]'        => 'Sports Player Platform - ' . $plan['name'],
                'line_items[0][price_data][recurring][interval]'       => $interval,
            ];

            $response = wp_remote_post(self::API_BASE . '/checkout/sessions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret_key,
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body'    => $body,
                'timeout' => 20,
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            $code = wp_remote_retrieve_response_code($response);

            if ($code >= 400 || empty($data['url'])) {
                $err_msg = $data['error']['message'] ?? __('Unable to create Stripe checkout session.', 'sports-player-platform');
                return new WP_Error('stripe_session_error', $err_msg);
            }

            return (string) $data['url'];
        }

        /**
         * Handle incoming Stripe webhook events with signature verification.
         */
        public function handle_webhook(WP_REST_Request $request): WP_REST_Response {
            $payload    = $request->get_body();
            $sig_header = (string) $request->get_header('stripe-signature');
            $secret     = $this->get_webhook_secret();

            if ($secret !== '' && !$this->verify_signature($payload, $sig_header, $secret)) {
                return new WP_REST_Response(['error' => 'Invalid signature'], 400);
            }

            $event = json_decode($payload, true);
            if (!is_array($event) || empty($event['type'])) {
                return new WP_REST_Response(['error' => 'Invalid payload'], 400);
            }

            $type   = $event['type'];
            $object = $event['data']['object'] ?? [];

            switch ($type) {
                case 'checkout.session.completed':
                    $user_id = absint($object['metadata']['user_id'] ?? ($object['client_reference_id'] ?? 0));
                    $plan_id = sanitize_key($object['metadata']['plan_id'] ?? 'monthly');
                    $sub_id  = sanitize_text_field($object['subscription'] ?? ($object['id'] ?? ''));
                    $amount  = isset($object['amount_total']) ? ((float) $object['amount_total'] / 100) : 0.00;
                    $curr    = strtoupper((string) ($object['currency'] ?? 'USD'));

                    if ($user_id > 0 && class_exists('SPP_Membership')) {
                        $expires = $plan_id === 'annual'
                            ? gmdate('Y-m-d H:i:s', strtotime('+1 year'))
                            : gmdate('Y-m-d H:i:s', strtotime('+1 month'));

                        SPP_Membership::get_instance()->upgrade_membership($user_id, $plan_id, $expires, 'stripe');
                        update_user_meta($user_id, 'spp_stripe_subscription_id', $sub_id);

                        SPP_Payment::log_payment($user_id, 'stripe', $sub_id, $amount, $curr, 'completed', null, $event);
                    }
                    break;

                case 'invoice.payment_failed':
                    $sub_id  = sanitize_text_field($object['subscription'] ?? '');
                    $user_id = $this->find_user_by_subscription($sub_id);
                    if ($user_id > 0) {
                        SPP_Payment::log_payment($user_id, 'stripe', $sub_id, 0.00, 'USD', 'failed', null, $event);
                    }
                    break;

                case 'customer.subscription.deleted':
                    $sub_id  = sanitize_text_field($object['id'] ?? '');
                    $user_id = $this->find_user_by_subscription($sub_id);
                    if ($user_id > 0 && class_exists('SPP_Membership')) {
                        SPP_Membership::get_instance()->cancel_membership($user_id);
                        SPP_Payment::log_payment($user_id, 'stripe', $sub_id, 0.00, 'USD', 'cancelled', null, $event);
                    }
                    break;
            }

            return new WP_REST_Response(['received' => true], 200);
        }

        /**
         * Cancel a Stripe subscription.
         */
        public function cancel_subscription(string $sub_id): bool {
            $secret_key = $this->get_secret_key();
            if ($secret_key === '' || $sub_id === '') {
                return false;
            }

            $response = wp_remote_request(self::API_BASE . '/subscriptions/' . rawurlencode($sub_id), [
                'method'  => 'DELETE',
                'headers' => ['Authorization' => 'Bearer ' . $secret_key],
                'timeout' => 15,
            ]);

            return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
        }

        /**
         * Verify Stripe-Signature HMAC SHA-256 header.
         */
        private function verify_signature(string $payload, string $sig_header, string $secret): bool {
            if ($sig_header === '') {
                return false;
            }

            $parts = [];
            foreach (explode(',', $sig_header) as $item) {
                $kv = explode('=', trim($item), 2);
                if (count($kv) === 2) {
                    $parts[$kv[0]] = $kv[1];
                }
            }

            if (empty($parts['t']) || empty($parts['v1'])) {
                return false;
            }

            $signed_payload = $parts['t'] . '.' . $payload;
            $expected_sig   = hash_hmac('sha256', $signed_payload, $secret);

            return hash_equals($expected_sig, $parts['v1']);
        }

        private function find_user_by_subscription(string $sub_id): int {
            if ($sub_id === '') {
                return 0;
            }
            $users = get_users([
                'meta_key'   => 'spp_stripe_subscription_id',
                'meta_value' => $sub_id,
                'number'     => 1,
                'fields'     => 'ID',
            ]);
            return !empty($users) ? (int) $users[0] : 0;
        }
    }
}
