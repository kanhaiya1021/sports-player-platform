<?php
/**
 * Payment Gateway Interface & Manager Class
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!interface_exists('SPP_Payment_Gateway')) {
    interface SPP_Payment_Gateway {
        /**
         * Create a hosted checkout session and return the redirect URL.
         *
         * @return string|WP_Error
         */
        public function create_session(array $plan, int $user_id);

        /**
         * Handle incoming gateway webhook request.
         */
        public function handle_webhook(WP_REST_Request $request): WP_REST_Response;

        /**
         * Cancel an active recurring subscription at the gateway.
         */
        public function cancel_subscription(string $sub_id): bool;
    }
}

if (!class_exists('SPP_Payment')) {

    final class SPP_Payment {

        private static $instance = null;

        public static function get_instance(): self {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->load_gateways();
            $this->init_hooks();
        }

        private function load_gateways(): void {
            require_once SPP_PLUGIN_DIR . 'includes/payment/class-spp-stripe.php';
            require_once SPP_PLUGIN_DIR . 'includes/payment/class-spp-paypal.php';
        }

        private function init_hooks(): void {
            add_action('rest_api_init', [$this, 'register_webhook_routes']);
            add_action('wp_ajax_spp_create_checkout', [$this, 'ajax_create_checkout']);
        }

        /**
         * Register Stripe and PayPal webhook REST endpoints.
         */
        public function register_webhook_routes(): void {
            register_rest_route('spp/v1', '/stripe-webhook', [
                'methods'             => 'POST',
                'callback'            => [new SPP_Stripe(), 'handle_webhook'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('spp/v1', '/paypal-webhook', [
                'methods'             => 'POST',
                'callback'            => [new SPP_PayPal(), 'handle_webhook'],
                'permission_callback' => '__return_true',
            ]);
        }

        /**
         * Get available membership plans.
         */
        public static function get_plans(): array {
            return [
                'free' => [
                    'id'          => 'free',
                    'name'        => __('Free', 'sports-player-platform'),
                    'price'       => 0.00,
                    'currency'    => 'USD',
                    'interval'    => 'forever',
                    'interval_label' => __('Forever', 'sports-player-platform'),
                    'features'    => [
                        __('Browse player directory', 'sports-player-platform'),
                        __('View public rankings & teams', 'sports-player-platform'),
                        __('Basic profile listing', 'sports-player-platform'),
                    ],
                ],
                'monthly' => [
                    'id'          => 'monthly',
                    'name'        => __('Monthly Pro', 'sports-player-platform'),
                    'price'       => 29.00,
                    'currency'    => 'USD',
                    'interval'    => 'month',
                    'interval_label' => __('/ month', 'sports-player-platform'),
                    'features'    => [
                        __('Full player contact information', 'sports-player-platform'),
                        __('Up to 50 direct messages / month', 'sports-player-platform'),
                        __('Up to 5 recruiting lists', 'sports-player-platform'),
                        __('Shortlist & player notes', 'sports-player-platform'),
                    ],
                ],
                'annual' => [
                    'id'          => 'annual',
                    'name'        => __('Annual All-Access', 'sports-player-platform'),
                    'price'       => 249.00,
                    'currency'    => 'USD',
                    'interval'    => 'year',
                    'interval_label' => __('/ year', 'sports-player-platform'),
                    'popular'     => true,
                    'features'    => [
                        __('Everything in Monthly Pro', 'sports-player-platform'),
                        __('Unlimited direct messages', 'sports-player-platform'),
                        __('Unlimited recruiting lists & CSV export', 'sports-player-platform'),
                        __('Priority recruiting support', 'sports-player-platform'),
                    ],
                ],
            ];
        }

        /**
         * Get active gateway instance based on plugin settings.
         */
        public function get_gateway(?string $preferred = null): SPP_Payment_Gateway {
            $settings = get_option('spp_settings', []);
            $gateway  = $preferred ?: ($settings['spp_active_gateway'] ?? 'stripe');

            if ($gateway === 'paypal') {
                return new SPP_PayPal();
            }

            return new SPP_Stripe();
        }

        /**
         * AJAX: Create checkout session for selected plan.
         */
        public function ajax_create_checkout(): void {
            check_ajax_referer('spp_pricing_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error([
                    'message'      => __('Please log in to upgrade your plan.', 'sports-player-platform'),
                    'redirect_url' => home_url('/login/'),
                ], 401);
            }

            $plan_id = sanitize_key($_POST['plan'] ?? '');
            $plans   = self::get_plans();

            if (!isset($plans[$plan_id]) || $plan_id === 'free') {
                wp_send_json_error(['message' => __('Invalid plan selected.', 'sports-player-platform')], 400);
            }

            $gateway_name = sanitize_key($_POST['gateway'] ?? '');
            $gateway      = $this->get_gateway($gateway_name ?: null);
            $session_url  = $gateway->create_session($plans[$plan_id], get_current_user_id());

            if (is_wp_error($session_url)) {
                wp_send_json_error(['message' => $session_url->get_error_message()], 400);
            }

            wp_send_json_success(['checkout_url' => $session_url]);
        }

        /**
         * Log a payment transaction into spp_payment_logs.
         */
        public static function log_payment(
            int $user_id,
            string $gateway,
            string $transaction_id,
            float $amount,
            string $currency,
            string $status,
            ?int $membership_id = null,
            array $payload = []
        ): void {
            global $wpdb;

            $table = $wpdb->prefix . 'spp_payment_logs';
            $wpdb->insert($table, [
                'user_id'        => $user_id,
                'membership_id'  => $membership_id,
                'gateway'        => sanitize_text_field($gateway),
                'transaction_id' => sanitize_text_field($transaction_id),
                'amount'         => $amount,
                'currency'       => strtoupper(sanitize_text_field($currency)),
                'status'         => sanitize_text_field($status),
                'payload'        => !empty($payload) ? wp_json_encode($payload) : '',
            ]);
        }
    }
}
