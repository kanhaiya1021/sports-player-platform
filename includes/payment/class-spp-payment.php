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
                        __('Message inbox access for eligible accounts', 'sports-player-platform'),
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
                        __('Message inbox access for eligible accounts', 'sports-player-platform'),
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

        public static function matches_payment_plan(string $plan_id, float $amount, string $currency): bool {
            $plan = self::get_plans()[$plan_id] ?? null;
            return $plan && strtoupper($currency) === $plan['currency'] && $amount >= $plan['price'];
        }

        public static function checkout_enabled(): bool {
            $settings = get_option('spp_settings', []);
            if (empty($settings['spp_payments_enabled'])) {
                return false;
            }

            $required = ($settings['spp_active_gateway'] ?? 'stripe') === 'paypal'
                ? ['spp_paypal_client_id', 'spp_paypal_secret', 'spp_paypal_webhook_id', 'spp_paypal_monthly_plan_id', 'spp_paypal_annual_plan_id']
                : ['spp_stripe_secret_key', 'spp_stripe_webhook_secret'];

            foreach ($required as $key) {
                if (empty($settings[$key])) {
                    return false;
                }
            }

            return !SPP_DB::get_instance()->needs_update();
        }

        /**
         * AJAX: Create checkout session for selected plan.
         */
        public function ajax_create_checkout(): void {
            check_ajax_referer('spp_pricing_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error([
                    'message'      => __('Please log in to upgrade your plan.', 'sports-player-platform'),
                    'redirect_url' => spp_page_url('login'),
                ], 401);
            }

            if (!self::checkout_enabled()) {
                wp_send_json_error(['message' => __('Paid checkout is currently unavailable.', 'sports-player-platform')], 503);
            }

            $plan_id = sanitize_key(wp_unslash($_POST['plan'] ?? ''));
            $plans   = self::get_plans();

            if (!isset($plans[$plan_id]) || $plan_id === 'free') {
                wp_send_json_error(['message' => __('Invalid plan selected.', 'sports-player-platform')], 400);
            }

            $user_id = get_current_user_id();
            $membership = SPP_Membership::get_instance()->get_user_membership($user_id);
            if ($membership && $membership['type'] !== 'free' && $membership['status'] !== 'cancelled'
                && (!empty($membership['transaction_id']) || get_user_meta($user_id, 'spp_stripe_subscription_id', true))) {
                wp_send_json_error(['message' => __('Cancel your existing subscription before starting a new one.', 'sports-player-platform')], 409);
            }

            $session_url = $this->get_gateway()->create_session($plans[$plan_id], $user_id);

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
            string $event_id
        ): bool {
            global $wpdb;

            $table = $wpdb->prefix . 'spp_payment_logs';
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO $table (user_id, gateway, transaction_id, amount, currency, status, event_id)
                 VALUES (%d, %s, %s, %f, %s, %s, %s) ON DUPLICATE KEY UPDATE id = id",
                $user_id,
                $gateway,
                $transaction_id,
                $amount,
                strtoupper($currency),
                $status,
                $event_id
            ));

            if ($result !== false && (int) $wpdb->rows_affected === 1) {
                do_action('spp_payment_logged', $user_id, $gateway, $transaction_id, $amount, strtoupper($currency), $status);
            }

            return $result !== false;
        }

        public static function webhook_response(bool $saved = true): WP_REST_Response {
            return $saved
                ? new WP_REST_Response(['received' => true], 200)
                : new WP_REST_Response(['error' => 'Payment event could not be processed'], 503);
        }
    }
}
