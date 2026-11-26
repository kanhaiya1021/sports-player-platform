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
            add_filter('user_meta', [$this, 'add_membership_meta']);

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

        public function add_membership_meta(array $meta): array {
            // Add membership status to user meta for quick access
            return $meta;
        }

        public function get_user_membership(int $user_id): ?array {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_memberships';

            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
                $user_id
            ), ARRAY_A);
        }

        public function upgrade_membership(int $user_id, string $type, string $payment_method = null): bool {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_memberships';

            // Deactivate current membership
            $wpdb->update(
                $table_name,
                ['status' => self::STATUS_EXPIRED],
                ['user_id' => $user_id, 'status' => self::STATUS_ACTIVE]
            );

            // Create new membership
            $expires_at = null;
            if ($type === self::TYPE_MONTHLY) {
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 month'));
            } elseif ($type === self::TYPE_ANNUAL) {
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));
            }

            return (bool) $wpdb->insert(
                $table_name,
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
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_memberships';

            return (bool) $wpdb->update(
                $table_name,
                ['status' => self::STATUS_CANCELLED],
                ['user_id' => $user_id, 'status' => self::STATUS_ACTIVE]
            );
        }

        public function is_member(int $user_id, string $required_type = null): bool {
            $membership = $this->get_user_membership($user_id);

            if (!$membership) {
                return false;
            }

            if ($membership['status'] !== self::STATUS_ACTIVE) {
                return false;
            }

            // Free members have access to basic features
            if ($membership['type'] === self::TYPE_FREE) {
                return true;
            }

            // Check required type
            if ($required_type) {
                return $membership['type'] === $required_type;
            }

            return true;
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
                        <h4><?php esc_html_e('Upgrade Options', 'sports-player-platform'); ?></h4>
                        <ul>
                            <li><?php esc_html_e('Monthly - $9.99/month', 'sports-player-platform'); ?></li>
                            <li><?php esc_html_e('Annual - $99.99/year (save 17%)', 'sports-player-platform'); ?></li>
                        </ul>
                        <p class="spp-upgrade-note"><?php esc_html_e('Contact administrator for payment setup.', 'sports-player-platform'); ?></p>
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
            $plans        = class_exists('SPP_Payment') ? SPP_Payment::get_plans() : [];
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

            ob_start();
            include $template_path;
            return (string) ob_get_clean();
        }
    }
}