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
         * Create a recurring PayPal subscription and return its approval URL.
         *
         * @return string|WP_Error
         */
        public function create_session(array $plan, int $user_id) {
            $token = $this->get_access_token();
            if (!$token) {
                return new WP_Error('paypal_not_configured', __('PayPal is not configured. Please add your PayPal credentials in Settings.', 'sports-player-platform'));
            }

            $settings = get_option('spp_settings', []);
            $provider_plan = $settings['spp_paypal_' . $plan['id'] . '_plan_id'] ?? '';
            if (!$this->plan_matches($token, $provider_plan, $plan)) {
                return new WP_Error('paypal_plan_error', __('The PayPal billing plan does not match the selected membership.', 'sports-player-platform'));
            }

            $body = [
                'plan_id'             => $provider_plan,
                'custom_id'           => $user_id . ':' . $plan['id'],
                'application_context' => [
                    'return_url' => add_query_arg(['spp_payment' => 'success', 'gateway' => 'paypal'], spp_page_url('dashboard/membership')),
                    'cancel_url' => add_query_arg(['spp_payment' => 'cancelled'], spp_page_url('pricing')),
                ],
            ];

            $response = wp_remote_post($this->get_base_url() . '/v1/billing/subscriptions', [
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
            if (wp_remote_retrieve_response_code($response) === 201 && !empty($data['links']) && is_array($data['links'])) {
                foreach ($data['links'] as $link) {
                    if (($link['rel'] ?? '') === 'approve' && !empty($link['href'])) {
                        return (string) $link['href'];
                    }
                }
            }

            return new WP_Error('paypal_subscription_error', __('Unable to create PayPal checkout session.', 'sports-player-platform'));
        }

        private function plan_matches(string $token, string $provider_plan, array $plan): bool {
            $remote = $this->get_billing_resource($token, 'plans', $provider_plan);
            $cycles = $remote['billing_cycles'] ?? [];
            $cycle = $cycles[0] ?? [];
            $price = $cycle['pricing_scheme']['fixed_price'] ?? [];

            return ($remote['status'] ?? '') === 'ACTIVE'
                && count($cycles) === 1
                && ($cycle['tenure_type'] ?? '') === 'REGULAR'
                && (int) ($cycle['total_cycles'] ?? -1) === 0
                && ($cycle['frequency']['interval_unit'] ?? '') === strtoupper($plan['interval'])
                && (int) ($cycle['frequency']['interval_count'] ?? 0) === 1
                && ($price['currency_code'] ?? '') === $plan['currency']
                && (float) ($price['value'] ?? 0) === $plan['price']
                && (float) ($remote['payment_preferences']['setup_fee']['value'] ?? 0) === 0.0;
        }

        private function get_billing_resource(string $token, string $resource, string $id): ?array {
            $response = wp_remote_get($this->get_base_url() . '/v1/billing/' . $resource . '/' . rawurlencode($id), [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'timeout' => 15,
            ]);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return null;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            return is_array($data) ? $data : null;
        }

        /**
         * Handle PayPal webhook events.
         */
        public function handle_webhook(WP_REST_Request $request): WP_REST_Response {
            $event = json_decode($request->get_body(), true);
            if (!is_array($event) || empty($event['event_type']) || empty($event['id']) || empty($event['create_time'])) {
                return new WP_REST_Response(['error' => 'Invalid payload'], 400);
            }

            $settings = get_option('spp_settings', []);
            $webhook_id = $settings['spp_paypal_webhook_id'] ?? '';
            $headers = ['auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time'];
            $verification = ['webhook_id' => $webhook_id, 'webhook_event' => $event];
            foreach ($headers as $header) {
                $verification[$header] = $request->get_header('paypal-' . str_replace('_', '-', $header));
                if ($verification[$header] === null || $verification[$header] === '') {
                    return new WP_REST_Response(['error' => 'Missing PayPal signature headers'], 400);
                }
            }

            $token = $this->get_access_token();
            if ($webhook_id === '' || !$token) {
                return SPP_Payment::webhook_response(false);
            }

            $response = wp_remote_post($this->get_base_url() . '/v1/notifications/verify-webhook-signature', [
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'body' => wp_json_encode($verification),
                'timeout' => 15,
            ]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return SPP_Payment::webhook_response(false);
            }
            $result = json_decode(wp_remote_retrieve_body($response), true);
            if (($result['verification_status'] ?? '') !== 'SUCCESS') {
                return new WP_REST_Response(['error' => 'Invalid PayPal signature'], 400);
            }

            $type = $event['event_type'];
            $statuses = [
                'PAYMENT.SALE.COMPLETED' => [SPP_Membership::STATUS_ACTIVE, 'completed'],
                'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => [SPP_Membership::STATUS_PAUSED, 'failed'],
                'BILLING.SUBSCRIPTION.SUSPENDED' => [SPP_Membership::STATUS_PAUSED, 'paused'],
                'BILLING.SUBSCRIPTION.CANCELLED' => [SPP_Membership::STATUS_CANCELLED, 'cancelled'],
                'BILLING.SUBSCRIPTION.EXPIRED' => [SPP_Membership::STATUS_CANCELLED, 'expired'],
            ];
            if (!isset($statuses[$type])) {
                return SPP_Payment::webhook_response();
            }

            $resource = $event['resource'] ?? [];
            $is_payment = $type === 'PAYMENT.SALE.COMPLETED';
            $sub_id = $is_payment ? ($resource['billing_agreement_id'] ?? '') : ($resource['id'] ?? '');
            if (!is_string($sub_id) || !preg_match('/^I-[A-Za-z0-9]{1,130}$/D', $sub_id)) {
                return SPP_Payment::webhook_response();
            }

            $memberships = SPP_Membership::get_instance();
            $existing = $memberships->get_subscription('paypal', $sub_id);
            $subscription = $resource;
            if ($is_payment) {
                $subscription = $this->get_billing_resource($token, 'subscriptions', $sub_id);
                if (!$subscription) {
                    return SPP_Payment::webhook_response(false);
                }
            }

            $identity = explode(':', (string) ($subscription['custom_id'] ?? ''), 2);
            $user_id = (int) ($existing['user_id'] ?? $identity[0] ?? 0);
            $plan_id = $existing['type'] ?? $identity[1] ?? '';
            if (!$user_id || !get_userdata($user_id) || !in_array($plan_id, ['monthly', 'annual'], true)) {
                return SPP_Payment::webhook_response();
            }
            $configured_plan = $settings['spp_paypal_' . $plan_id . '_plan_id'] ?? '';
            if (!$existing && ($configured_plan === '' || $configured_plan !== ($subscription['plan_id'] ?? ''))) {
                return SPP_Payment::webhook_response();
            }

            $amount = 0.0;
            $period_end = 0;
            $currency = 'USD';
            if ($is_payment) {
                $amount = (float) ($resource['amount']['total'] ?? 0);
                $currency = $resource['amount']['currency'] ?? '';
                $period_end = strtotime($subscription['billing_info']['next_billing_time'] ?? '');
                if (!SPP_Payment::matches_payment_plan($plan_id, $amount, $currency)) {
                    return SPP_Payment::webhook_response();
                }
                if (!$period_end) {
                    return SPP_Payment::webhook_response(false);
                }
            }

            $event_time = strtotime($event['create_time']);
            if ($event_time === false) {
                return new WP_REST_Response(['error' => 'Invalid event time'], 400);
            }

            [$status, $log_status] = $statuses[$type];
            $saved = $memberships->record_subscription($user_id, $plan_id, 'paypal', $sub_id, $event_time, $status, $period_end)
                && SPP_Payment::log_payment($user_id, 'paypal', $resource['id'] ?? $sub_id, $amount, $currency, $log_status, $event['id']);

            return SPP_Payment::webhook_response($saved);
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

            return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 204;
        }
    }
}
