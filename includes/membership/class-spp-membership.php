<?php
/**
 * Membership and Payment System Class
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Membership')) {

    final class SPP_Membership {

        private static $instance = null;

        const TYPE_FREE = 'free';
        const TYPE_MONTHLY = 'monthly';
        const TYPE_ANNUAL = 'annual';

        const STATUS_ACTIVE = 'active';
        const STATUS_EXPIRED = 'expired';
        const STATUS_CANCELLED = 'cancelled';
        const STATUS_PAUSED = 'paused';

        public static function get_instance(): self {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->init_hooks();
        }

        private function init_hooks(): void {
            add_action('user_register', [$this, 'create_default_membership']);

            add_shortcode('spp_membership_status', [$this, 'render_membership_status']);
            add_shortcode('spp_pricing_table', [$this, 'render_pricing_table']);
        }

        public function create_membership_tables(): void {
            if (class_exists('SPP_DB')) {
                SPP_DB::get_instance()->create_tables();
            }
        }

        public function create_default_membership(int $user_id): void {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_memberships';

            $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'type' => self::TYPE_FREE,
                    'status' => self::STATUS_ACTIVE,
                    'started_at' => current_time('mysql'),
                ]
            );
        }

        public function get_user_membership(int $user_id): ?array {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_memberships';

            $membership = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                $user_id
            ), ARRAY_A);

            if ($membership && $membership['status'] === self::STATUS_ACTIVE && !empty($membership['expires_at'])
                && $membership['expires_at'] <= current_time('mysql', true)) {
                $membership['status'] = self::STATUS_EXPIRED;
            }

            return $membership;
        }

        public function upgrade_membership(int $user_id, string $type, ?string $payment_method = null): bool {
            global $wpdb;

            if (!in_array($type, [self::TYPE_FREE, self::TYPE_MONTHLY, self::TYPE_ANNUAL], true)) {
                return false;
            }

            $expires_at = null;
            if ($type === self::TYPE_MONTHLY) {
                $expires_at = gmdate('Y-m-d H:i:s', strtotime('+1 month'));
            } elseif ($type === self::TYPE_ANNUAL) {
                $expires_at = gmdate('Y-m-d H:i:s', strtotime('+1 year'));
            }

            return (bool) $wpdb->insert(
                $wpdb->prefix . 'spp_memberships',
                [
                    'user_id' => $user_id,
                    'type' => $type,
                    'status' => self::STATUS_ACTIVE,
                    'started_at' => current_time('mysql'),
                    'expires_at' => $expires_at,
                    'payment_method' => $payment_method,
                ]
            );
        }

        public function cancel_membership(int $user_id): bool {
            $membership = $this->get_user_membership($user_id);
            if (!$membership || $membership['type'] === self::TYPE_FREE || $membership['status'] === self::STATUS_CANCELLED) {
                return false;
            }

            $gateway = $membership['payment_method'];
            $subscription_id = $membership['transaction_id'] ?? '';
            if ($subscription_id === '') {
                // Older Stripe checkouts stored the subscription ID in user meta.
                $subscription_id = (string) get_user_meta($user_id, 'spp_stripe_subscription_id', true);
                $gateway = $subscription_id !== '' ? 'stripe' : $gateway;
            }

            if (!in_array($gateway, ['stripe', 'paypal'], true) || $subscription_id === ''
                || !SPP_Payment::get_instance()->get_gateway($gateway)->cancel_subscription($subscription_id)) {
                return false;
            }

            return $this->record_subscription($user_id, $membership['type'], $gateway, $subscription_id, time(), self::STATUS_CANCELLED);
        }

        public function get_subscription(string $gateway, string $subscription_id): ?array {
            global $wpdb;

            $table = $wpdb->prefix . 'spp_memberships';
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE payment_method = %s AND transaction_id = %s",
                $gateway,
                $subscription_id
            ), ARRAY_A);
        }

        /**
         * Keep a single row per provider subscription. Retries and older events cannot reopen a cancelled subscription.
         */
        public function record_subscription(
            int $user_id,
            string $plan,
            string $gateway,
            string $subscription_id,
            int $event_time,
            string $status,
            int $period_end = 0
        ): bool {
            global $wpdb;

            $table = $wpdb->prefix . 'spp_memberships';
            $expires_at = gmdate('Y-m-d H:i:s', $period_end);

            return $wpdb->query($wpdb->prepare(
                "INSERT INTO $table (user_id, type, payment_method, transaction_id, started_at, expires_at, billing_event_at, status)
                 VALUES (%d, %s, %s, %s, %s, NULLIF(%s, ''), %d, %s)
                 ON DUPLICATE KEY UPDATE
                    expires_at = IF(status <> 'cancelled'
                        AND (VALUES(billing_event_at) > billing_event_at
                            OR (VALUES(billing_event_at) = billing_event_at AND status <> 'active' AND VALUES(status) = 'active'))
                        AND VALUES(expires_at) IS NOT NULL, VALUES(expires_at), expires_at),
                    status = IF(status = 'cancelled' OR VALUES(status) = 'cancelled', 'cancelled',
                        IF(VALUES(billing_event_at) > billing_event_at
                            OR (VALUES(billing_event_at) = billing_event_at AND VALUES(status) = 'active'), VALUES(status), status)),
                    billing_event_at = GREATEST(billing_event_at, VALUES(billing_event_at))",
                $user_id,
                $plan,
                $gateway,
                $subscription_id,
                current_time('mysql'),
                $period_end > 0 ? $expires_at : '',
                $event_time,
                $status
            )) !== false;
        }

        public function is_member(int $user_id, ?string $required_type = null): bool {
            $membership = $this->get_user_membership($user_id);

            if (!$membership) {
                return false;
            }

            if ($membership['status'] !== self::STATUS_ACTIVE) {
                return false;
            }

            if ($required_type === 'paid') {
                return in_array($membership['type'], [self::TYPE_MONTHLY, self::TYPE_ANNUAL], true);
            }

            return $required_type === null || $membership['type'] === $required_type;
        }

        public function render_membership_status(): string {
            if (!is_user_logged_in()) {
                return '<p>' . __('You must be logged in to view membership status.', 'sports-player-platform') . '</p>';
            }

            $membership = $this->get_user_membership(get_current_user_id());

            ob_start();
            ?>
            <div class="spp-membership-status">
                <?php if ($membership): ?>
                    <h3><?php esc_html_e('Membership Status', 'sports-player-platform'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <th><?php esc_html_e('Type', 'sports-player-platform'); ?></th>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $membership['type']))); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Status', 'sports-player-platform'); ?></th>
                            <td><?php echo esc_html(ucwords($membership['status'])); ?></td>
                        </tr>
                        <?php if ($membership['started_at']): ?>
                            <tr>
                                <th><?php esc_html_e('Started', 'sports-player-platform'); ?></th>
                                <td><?php echo esc_html(date('Y-m-d', strtotime($membership['started_at']))); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($membership['expires_at']): ?>
                            <tr>
                                <th><?php esc_html_e('Expires', 'sports-player-platform'); ?></th>
                                <td><?php echo esc_html(date('Y-m-d', strtotime($membership['expires_at']))); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>

                    <?php if ($membership['type'] === self::TYPE_FREE): ?>
                        <p><a href="<?php echo esc_url(spp_page_url('pricing')); ?>"><?php esc_html_e('View membership plans', 'sports-player-platform'); ?></a></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><?php esc_html_e('No active membership found.', 'sports-player-platform'); ?></p>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Render [spp_pricing_table] shortcode.
         */
        public function render_pricing_table(): string {
            $plans        = SPP_Payment::get_plans();
            $is_logged_in = is_user_logged_in();
            $current_plan = 'free';

            if ($is_logged_in) {
                $membership = $this->get_user_membership(get_current_user_id());
                if ($membership && ($membership['status'] ?? '') === self::STATUS_ACTIVE) {
                    $current_plan = $membership['type'] ?? 'free';
                }
            }

            $template_path = SPP_PLUGIN_DIR . 'templates/membership/pricing-table.php';
            if (!file_exists($template_path)) {
                return '';
            }

            $ajax_url = admin_url('admin-ajax.php');
            $nonce    = wp_create_nonce('spp_pricing_nonce');
            $payments_enabled = SPP_Payment::checkout_enabled();

            ob_start();
            include $template_path;
            return (string) ob_get_clean();
        }
    }
}
