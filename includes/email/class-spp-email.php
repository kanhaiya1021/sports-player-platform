<?php
/**
 * Email System Class
 *
 * Handles HTML email rendering, delivery, and database logging.
 *
 * @package Sports_Player_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Email')) {

    final class SPP_Email {

        /**
         * Singleton instance.
         *
         * @var SPP_Email|null
         */
        private static ?self $instance = null;

        /**
         * Last wp_mail error message captured via wp_mail_failed.
         *
         * @var string
         */
        private static string $last_error = '';

        /**
         * Get singleton instance.
         */
        public static function get_instance(): self {
            return self::$instance ??= new self();
        }

        /**
         * Register lifecycle email hooks.
         */
        private function __construct() {
            add_action('spp_profile_approved', [$this, 'on_profile_approved']);
            add_action('spp_profile_rejected', [$this, 'on_profile_rejected']);
            add_action('spp_payment_logged', [$this, 'on_payment_logged'], 10, 6);
        }

        /**
         * Send an HTML email using a plugin template and log the attempt.
         *
         * @param string $to       Recipient email address.
         * @param string $template Template slug under templates/email/.
         * @param array  $data     Template variables and optional 'subject'.
         * @return bool Whether wp_mail accepted the email.
         */
        public static function send(string $to, string $template, array $data = []): bool {
            if (!is_email($to)) {
                return false;
            }

            $template = sanitize_file_name($template);
            $template_path = SPP_PLUGIN_DIR . 'templates/email/' . $template . '.php';
            if (!file_exists($template_path)) {
                self::log_email($to, (string) ($data['subject'] ?? ''), $template, 'failed', 'Template not found: ' . $template);
                return false;
            }

            $subject = !empty($data['subject'])
                ? sanitize_text_field((string) $data['subject'])
                : self::default_subject($template);

            $body = self::render_template($template_path, $subject, $data);

            self::$last_error = '';
            $capture_error = static function ($wp_error): void {
                if (is_wp_error($wp_error)) {
                    self::$last_error = $wp_error->get_error_message();
                }
            };
            add_action('wp_mail_failed', $capture_error);

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail($to, $subject, $body, $headers);

            remove_action('wp_mail_failed', $capture_error);

            self::log_email(
                $to,
                $subject,
                $template,
                $sent ? 'sent' : 'failed',
                $sent ? '' : self::$last_error
            );

            return $sent;
        }

        /**
         * Render an email template wrapped with header and footer.
         */
        private static function render_template(string $template_path, string $subject, array $data): string {
            $site_name = get_bloginfo('name') ?: __('Sports Player Platform', 'sports-player-platform');

            ob_start();
            include SPP_PLUGIN_DIR . 'templates/email/header.php';
            include $template_path;
            include SPP_PLUGIN_DIR . 'templates/email/footer.php';
            return (string) ob_get_clean();
        }

        /**
         * Default subject lines per template.
         */
        private static function default_subject(string $template): string {
            $site_name = get_bloginfo('name') ?: __('Sports Player Platform', 'sports-player-platform');
            $subjects = [
                'welcome' => sprintf(__('Welcome to %s', 'sports-player-platform'), $site_name),
                'verify-email' => sprintf(__('Verify your email - %s', 'sports-player-platform'), $site_name),
                'password-reset' => sprintf(__('Reset your password - %s', 'sports-player-platform'), $site_name),
                'membership-confirm' => sprintf(__('Membership Confirmed - %s', 'sports-player-platform'), $site_name),
                'payment-receipt' => sprintf(__('Payment Receipt - %s', 'sports-player-platform'), $site_name),
                'payment-failed' => sprintf(__('Payment Failed - %s', 'sports-player-platform'), $site_name),
                'new-message' => sprintf(__('You have a new message - %s', 'sports-player-platform'), $site_name),
                'profile-approved' => sprintf(__('Your Player Profile is Approved! - %s', 'sports-player-platform'), $site_name),
                'profile-rejected' => sprintf(__('Player Profile Update Required - %s', 'sports-player-platform'), $site_name),
            ];

            return $subjects[$template] ?? $site_name;
        }

        /**
         * Record email delivery status in spp_email_logs.
         */
        private static function log_email(
            string $recipient,
            string $subject,
            string $template,
            string $status,
            string $error_message = ''
        ): void {
            global $wpdb;

            $table = $wpdb->prefix . 'spp_email_logs';
            $wpdb->insert(
                $table,
                [
                    'recipient' => sanitize_email($recipient),
                    'subject' => mb_substr($subject, 0, 255),
                    'template' => sanitize_key($template),
                    'status' => $status === 'sent' ? 'sent' : 'failed',
                    'error_message' => $error_message,
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }

        /**
         * Send email when a player profile is approved.
         */
        public function on_profile_approved(int $post_id): void {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'player') {
                return;
            }
            $user = get_userdata((int) $post->post_author);
            if (!$user || !is_email($user->user_email)) {
                return;
            }

            self::send($user->user_email, 'profile-approved', [
                'name' => $user->display_name,
                'player_name' => $post->post_title,
                'profile_url' => get_permalink($post_id),
            ]);
        }

        /**
         * Send email when a player profile is rejected.
         */
        public function on_profile_rejected(int $post_id): void {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'player') {
                return;
            }
            $user = get_userdata((int) $post->post_author);
            if (!$user || !is_email($user->user_email)) {
                return;
            }

            self::send($user->user_email, 'profile-rejected', [
                'name' => $user->display_name,
                'player_name' => $post->post_title,
                'edit_url' => spp_page_url('dashboard'),
            ]);
        }

        /**
         * Send receipt/confirmation or failure notice when a payment is logged.
         */
        public function on_payment_logged(
            int $user_id,
            string $gateway,
            string $transaction_id,
            float $amount,
            string $currency,
            string $status
        ): void {
            $user = get_userdata($user_id);
            if (!$user || !is_email($user->user_email)) {
                return;
            }

            if ($status === 'completed') {
                $membership = class_exists('SPP_Membership') ? SPP_Membership::get_instance()->get_user_membership($user_id) : null;
                $plan_name = $membership ? ucfirst($membership['type']) : __('Pro', 'sports-player-platform');

                self::send($user->user_email, 'payment-receipt', [
                    'name' => $user->display_name,
                    'plan_name' => $plan_name,
                    'amount' => $amount,
                    'currency' => $currency,
                    'gateway' => ucfirst($gateway),
                    'transaction_id' => $transaction_id,
                    'dashboard_url' => spp_page_url('dashboard'),
                ]);

                self::send($user->user_email, 'membership-confirm', [
                    'name' => $user->display_name,
                    'plan_name' => $plan_name,
                    'expires_at' => $membership['expires_at'] ?? '',
                    'dashboard_url' => spp_page_url('dashboard'),
                ]);
            } elseif ($status === 'failed') {
                self::send($user->user_email, 'payment-failed', [
                    'name' => $user->display_name,
                    'amount' => $amount,
                    'currency' => $currency,
                    'gateway' => ucfirst($gateway),
                    'pricing_url' => spp_page_url('pricing'),
                ]);
            }
        }
    }
}
