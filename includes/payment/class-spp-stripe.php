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
                'success_url'                => add_query_arg(['spp_payment' => 'success'], spp_page_url('dashboard/membership')),
                'cancel_url'                 => add_query_arg(['spp_payment' => 'cancelled'], spp_page_url('pricing')),
                'customer_email'             => $user->user_email,
                'client_reference_id'        => (string) $user_id,
                'metadata[user_id]'          => (string) $user_id,
                'metadata[plan_id]'          => (string) $plan['id'],
                'subscription_data[metadata][spp_user_id]' => (string) $user_id,
                'subscription_data[metadata][spp_plan_id]' => (string) $plan['id'],
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

            if ($secret === '' || !$this->verify_signature($payload, $sig_header, $secret)) {
                return new WP_REST_Response(['error' => 'Invalid signature'], 400);
            }

            $event = json_decode($payload, true);
            if (!is_array($event) || empty($event['type']) || empty($event['id']) || empty($event['created'])) {
                return new WP_REST_Response(['error' => 'Invalid payload'], 400);
            }

            $type = $event['type'];
            if (!in_array($type, ['invoice.paid', 'invoice.payment_failed', 'customer.subscription.deleted'], true)) {
                return SPP_Payment::webhook_response();
            }

            $object = $event['data']['object'] ?? [];
            $is_deleted = $type === 'customer.subscription.deleted';
            $details = $object['parent']['subscription_details'] ?? $object['subscription_details'] ?? [];
            $sub_id = $is_deleted ? ($object['id'] ?? '') : ($details['subscription'] ?? $object['subscription'] ?? '');
            if (!is_string($sub_id) || !preg_match('/^sub_[A-Za-z0-9]{1,130}$/D', $sub_id)) {
                return SPP_Payment::webhook_response();
            }

            $memberships = SPP_Membership::get_instance();
            $existing = $memberships->get_subscription('stripe', $sub_id);
            $metadata = $is_deleted ? ($object['metadata'] ?? []) : ($details['metadata'] ?? []);
            $user_id = (int) ($existing['user_id'] ?? $metadata['spp_user_id'] ?? $this->find_user_by_subscription($sub_id));
            $plan_id = $existing['type'] ?? $metadata['spp_plan_id'] ?? '';
            if ($user_id && $plan_id === '') {
                $legacy = $memberships->get_user_membership($user_id);
                if (empty($legacy['transaction_id'])) {
                    $plan_id = $legacy['type'] ?? '';
                }
            }

            if (!$user_id || !get_userdata($user_id) || !in_array($plan_id, ['monthly', 'annual'], true)) {
                return SPP_Payment::webhook_response();
            }

            $period_end = 0;
            $amount = 0.0;
            $status = SPP_Membership::STATUS_CANCELLED;
            $log_status = 'cancelled';
            $transaction_id = $object['id'] ?? $sub_id;

            if (!$is_deleted) {
                if (!in_array($object['billing_reason'] ?? '', ['subscription_create', 'subscription_cycle'], true)) {
                    return SPP_Payment::webhook_response();
                }
                $amount = (float) ($object['amount_paid'] ?? 0) / 100;
                $status = SPP_Membership::STATUS_PAUSED;
                $log_status = 'failed';
                if ($type === 'invoice.paid') {
                    if (($object['status'] ?? '') !== 'paid'
                        || !SPP_Payment::matches_payment_plan($plan_id, $amount, $object['currency'] ?? '')) {
                        return SPP_Payment::webhook_response();
                    }
                    foreach ($object['lines']['data'] ?? [] as $line) {
                        $line_details = $line['parent']['subscription_item_details'] ?? [];
                        $is_subscription_line = $line_details || ($line['type'] ?? '') === 'subscription';
                        if ($is_subscription_line && empty($line_details['proration'] ?? $line['proration'] ?? false)) {
                            $period_end = max($period_end, (int) ($line['period']['end'] ?? 0));
                        }
                    }
                    if (!$period_end) {
                        return SPP_Payment::webhook_response(false);
                    }
                    $status = SPP_Membership::STATUS_ACTIVE;
                    $log_status = 'completed';
                }
            }

            $saved = $memberships->record_subscription($user_id, $plan_id, 'stripe', $sub_id, (int) $event['created'], $status, $period_end)
                && SPP_Payment::log_payment($user_id, 'stripe', $transaction_id, $amount, $object['currency'] ?? 'USD', $log_status, $event['id']);

            return SPP_Payment::webhook_response($saved);
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

            return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
        }

        /**
         * Verify Stripe-Signature HMAC SHA-256 header.
         */
        private function verify_signature(string $payload, string $sig_header, string $secret): bool {
            if ($sig_header === '') {
                return false;
            }

            $timestamp = '';
            $signatures = [];
            foreach (explode(',', $sig_header) as $item) {
                [$key, $value] = array_pad(explode('=', trim($item), 2), 2, '');
                if ($key === 't') {
                    $timestamp = $value;
                } elseif ($key === 'v1') {
                    $signatures[] = $value;
                }
            }

            if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
                return false;
            }

            $expected_sig = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
            foreach ($signatures as $signature) {
                if (hash_equals($expected_sig, $signature)) {
                    return true;
                }
            }

            return false;
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
