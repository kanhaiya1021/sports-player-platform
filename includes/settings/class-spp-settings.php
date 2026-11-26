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
            $option = $args['option'] ?? '';
            $label = $args['label'] ?? '';
            $value = get_option('spp_events_provider_url', '');
            ?>
            <p>
                <label for="spp_events_provider_url"><?php echo esc_html($label); ?></label><br>
                <input type="url" name="spp_events_provider_url" id="spp_events_provider_url" value="<?php echo esc_attr($value); ?>" class="regular-text" />
            </p>
            <?php
        }

        public function render_checkbox_field(array $args): void {
            $option = $args['option'] ?? '';
            $label = $args['label'] ?? '';
            $value = (bool) get_option('spp_payments_enabled', false);
            ?>
            <p>
                <label for="spp_payments_enabled">
                    <input type="checkbox" name="spp_payments_enabled" id="spp_payments_enabled" value="1" <?php checked($value, true, true); ?> />
                    <?php echo esc_html($label); ?>
                </label>
            </p>
            <?php
        }

        public function render_textarea_field(array $args): void {
            $option = $args['option'] ?? '';
            $label = $args['label'] ?? '';
            $value = get_option('spp_membership_plans', '');
            ?>
            <p>
                <label for="spp_membership_plans"><?php echo esc_html($label); ?></label><br>
                <textarea name="spp_membership_plans" id="spp_membership_plans" rows="5" class="large-text"><?php echo esc_textarea($value); ?></textarea>
                <p class="description">Example: Monthly|$9.99|30 days&#10;Annual|$99.99|365 days</p>
            </p>
            <?php
        }
    }}
