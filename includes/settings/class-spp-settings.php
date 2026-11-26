<?php
/**
 * Plugin Settings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Settings')) {

    final class SPP_Settings {

        private static $instance = null;

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
            add_action('admin_menu', [$this, 'add_settings_page']);
            add_action('admin_init', [$this, 'register_settings']);
        }

        public function add_settings_page(): void {
            add_submenu_page(
                'spp-players',
                __('Settings', 'sports-player-platform'),
                __('Settings', 'sports-player-platform'),
                'manage_options',
                'spp-settings',
                [$this, 'render_settings_page']
            );
        }

        public function register_settings(): void {
            register_setting('spp_settings_group', 'spp_settings', [$this, 'sanitize_options']);

            add_settings_section(
                'spp_general_section',
                __('General Settings', 'sports-player-platform'),
                null,
                'spp-settings'
            );

            add_settings_field(
                'spp_events_provider_url',
                __('Events Provider URL', 'sports-player-platform'),
                [$this, 'render_text_field'],
                'spp-settings',
                'spp_general_section',
                ['option' => 'spp_events_provider_url', 'label' => __('Enter the events URL or embed code endpoint:', 'sports-player-platform')]
            );

            add_settings_field(
                'spp_payments_enabled',
                __('Enable Payments', 'sports-player-platform'),
                [$this, 'render_checkbox_field'],
                'spp-settings',
                'spp_general_section',
                ['option' => 'spp_payments_enabled', 'label' => __('Enable payment functionality:', 'sports-player-platform')]
            );

            add_settings_field(
                'spp_membership_plans',
                __('Membership Plans', 'sports-player-platform'),
                [$this, 'render_textarea_field'],
                'spp-settings',
                'spp_general_section',
                ['option' => 'spp_membership_plans', 'label' => __('Define membership plans (one per line, format: name|price|duration):', 'sports-player-platform')]
            );

            add_settings_section(
                'spp_payment_section',
                __('Payment Gateways', 'sports-player-platform'),
                null,
                'spp-settings'
            );

            add_settings_field(
                'spp_active_gateway',
                __('Active Payment Gateway', 'sports-player-platform'),
                [$this, 'render_select_field'],
                'spp-settings',
                'spp_payment_section',
                ['option' => 'spp_active_gateway', 'options' => ['stripe' => 'Stripe', 'paypal' => 'PayPal']]
            );

            add_settings_field(
                'spp_stripe_publishable_key',
                __('Stripe Publishable Key', 'sports-player-platform'),
                [$this, 'render_text_field'],
                'spp-settings',
                'spp_payment_section',
                ['option' => 'spp_stripe_publishable_key', 'type' => 'text', 'label' => __('pk_test_... or pk_live_...', 'sports-player-platform')]
            );

            add_settings_field(
                'spp_stripe_secret_key',
                __('Stripe Secret Key', 'sports-player-platform'),
                [$this, 'render_text_field'],
                'spp-settings',
                'spp_payment_section',
                ['option' => 'spp_stripe_secret_key', 'type' => 'password', 'label' => __('sk_test_... or sk_live_...', 'sports-player-platform')]
            );

            add_settings_field(
                'spp_stripe_webhook_secret',
                __('Stripe Webhook Secret', 'sports-player-platform'),
                [$this, 'render_text_field'],
                'spp-settings',
                'spp_payment_section',
                ['option' => 'spp_stripe_webhook_secret', 'type' => 'password', 'label' => __('whsec_... (Endpoint: /wp-json/spp/v1/stripe-webhook)', 'sports-player-platform')]
            );

            add_settings_field(
                'spp_paypal_sandbox',
                __('PayPal Sandbox Mode', 'sports-player-platform'),
                [$this, 'render_checkbox_field'],
                'spp-settings',
                'spp_payment_section',
                ['option' => 'spp_paypal_sandbox', 'label' => __('Use PayPal Sandbox for testing', 'sports-player-platform')]
            );

            add_settings_field(
                'spp_paypal_client_id',
                __('PayPal Client ID', 'sports-player-platform'),
                [$this, 'render_text_field'],
                'spp-settings',
                'spp_payment_section',
                ['option' => 'spp_paypal_client_id', 'type' => 'text', 'label' => __('PayPal REST App Client ID', 'sports-player-platform')]
            );

            add_settings_field(
                'spp_paypal_secret',
                __('PayPal Secret', 'sports-player-platform'),
                [$this, 'render_text_field'],
                'spp-settings',
                'spp_payment_section',
                ['option' => 'spp_paypal_secret', 'type' => 'password', 'label' => __('PayPal REST App Secret', 'sports-player-platform')]
            );
        }

        public function sanitize_options(array $options): array {
            $sanitized = [];

            if (isset($options['spp_events_provider_url'])) {
                $sanitized['spp_events_provider_url'] = esc_url_raw($options['spp_events_provider_url']);
            }
            if (isset($options['spp_payments_enabled'])) {
                $sanitized['spp_payments_enabled'] = 1;
            }
            if (isset($options['spp_membership_plans'])) {
                $sanitized['spp_membership_plans'] = sanitize_textarea_field($options['spp_membership_plans']);
            }
            if (isset($options['spp_active_gateway'])) {
                $sanitized['spp_active_gateway'] = in_array($options['spp_active_gateway'], ['stripe', 'paypal'], true)
                    ? $options['spp_active_gateway']
                    : 'stripe';
            }
            if (isset($options['spp_paypal_sandbox'])) {
                $sanitized['spp_paypal_sandbox'] = 1;
            }

            foreach (['spp_stripe_publishable_key', 'spp_stripe_secret_key', 'spp_stripe_webhook_secret', 'spp_paypal_client_id', 'spp_paypal_secret'] as $key) {
                if (isset($options[$key])) {
                    $sanitized[$key] = sanitize_text_field($options[$key]);
                }
            }

            return $sanitized;
        }

        public function render_settings_page(): void {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Sports Player Platform Settings', 'sports-player-platform'); ?></h1>

                <form method="post" action="options.php">
                    <?php
                    settings_fields('spp_settings_group');
                    do_settings_sections('spp-settings');
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }

        public function render_text_field(array $args): void {
            $option   = $args['option'] ?? '';
            $label    = $args['label'] ?? '';
            $type     = $args['type'] ?? 'url';
            $settings = get_option('spp_settings', []);
            $value    = $settings[$option] ?? '';
            ?>
            <input type="<?php echo esc_attr($type); ?>" name="spp_settings[<?php echo esc_attr($option); ?>]" id="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" />
            <?php if ($label): ?>
                <p class="description"><?php echo esc_html($label); ?></p>
            <?php endif; ?>
            <?php
        }

        public function render_select_field(array $args): void {
            $option   = $args['option'] ?? '';
            $options  = $args['options'] ?? [];
            $settings = get_option('spp_settings', []);
            $value    = $settings[$option] ?? '';
            ?>
            <select name="spp_settings[<?php echo esc_attr($option); ?>]" id="<?php echo esc_attr($option); ?>">
                <?php foreach ($options as $val => $text): ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($value, $val); ?>>
                        <?php echo esc_html($text); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php
        }

        public function render_checkbox_field(array $args): void {
            $option   = $args['option'] ?? '';
            $label    = $args['label'] ?? '';
            $settings = get_option('spp_settings', []);
            $value    = !empty($settings[$option]);
            ?>
            <label for="<?php echo esc_attr($option); ?>">
                <input type="checkbox" name="spp_settings[<?php echo esc_attr($option); ?>]" id="<?php echo esc_attr($option); ?>" value="1" <?php checked($value, true, true); ?> />
                <?php echo esc_html($label); ?>
            </label>
            <?php
        }

        public function render_textarea_field(array $args): void {
            $option   = $args['option'] ?? '';
            $label    = $args['label'] ?? '';
            $settings = get_option('spp_settings', []);
            $value    = $settings[$option] ?? '';
            ?>
            <textarea name="spp_settings[<?php echo esc_attr($option); ?>]" id="<?php echo esc_attr($option); ?>" rows="5" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <p class="description"><?php echo esc_html($label); ?> Example: Monthly|$29.00|30 days</p>
            <?php
        }
    }}
