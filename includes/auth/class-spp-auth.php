<?php
/**
 * Authentication and User Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Auth')) {

    final class SPP_Auth {

        private static $instance = null;

        const ROLE_PLAYER = 'spp_player';
        const ROLE_PARENT = 'spp_parent';
        const ROLE_COACH  = 'spp_coach';
        const ROLES_VERSION = '1.1.0';
        const MAX_LOGIN_ATTEMPTS = 5;
        const LOCKOUT_SECONDS = 900; // 15 minutes

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
            add_action('init', [$this, 'register_user_roles']);
            add_action('wp_loaded', [$this, 'handle_login']);
            add_action('wp_loaded', [$this, 'handle_logout']);
            add_action('template_redirect', [$this, 'handle_registration_form']);
            add_action('template_redirect', [$this, 'handle_email_verification']);
            add_action('template_redirect', [$this, 'handle_resend_verification']);
            add_action('template_redirect', [$this, 'handle_password_reset']);
            add_action('template_redirect', [$this, 'guard_protected_pages'], 20);

            add_shortcode('spp_login_form', [$this, 'render_login_form']);
            add_shortcode('spp_register_form', [$this, 'render_registration_form']);
            add_shortcode('spp_password_reset_form', [$this, 'render_password_reset_form']);
        }

        /**
         * Register or update custom user roles and capabilities.
         */
        public function register_user_roles(): void {
            if (get_option('spp_roles_version') === self::ROLES_VERSION) {
                return;
            }

            $roles_config = [
                self::ROLE_PLAYER => [
                    'label' => __('Player/Athlete', 'sports-player-platform'),
                    'caps'  => [
                        'read'             => true,
                        'list_players'     => true,
                        'browse_players'   => true,
                        'edit_own_profile' => true,
                        'send_messages'    => true,
                    ],
                ],
                self::ROLE_PARENT => [
                    'label' => __('Parent', 'sports-player-platform'),
                    'caps'  => [
                        'read'               => true,
                        'list_players'       => true,
                        'browse_players'     => true,
                        'view_saved_players' => true,
                    ],
                ],
                self::ROLE_COACH => [
                    'label' => __('Coach', 'sports-player-platform'),
                    'caps'  => [
                        'read'                   => true,
                        'list_players'           => true,
                        'browse_players'         => true,
                        'view_saved_players'     => true,
                        'track_recruiting'       => true,
                        'create_recruiting_list' => true,
                        'send_messages'          => true,
                    ],
                ],
            ];

            foreach ($roles_config as $role_slug => $config) {
                $existing_role = get_role($role_slug);
                if (!$existing_role) {
                    add_role($role_slug, $config['label'], $config['caps']);
                } else {
                    foreach ($config['caps'] as $cap => $grant) {
                        $existing_role->add_cap($cap, $grant);
                    }
                }
            }

            update_option('spp_roles_version', self::ROLES_VERSION);
        }

        /**
         * Handle user login with rate limiting, email verification check, and role-based redirect.
         */
        public function handle_login(): void {
            if (!isset($_POST['spp_login_action']) || $_POST['spp_login_action'] !== 'login') {
                return;
            }

            if (!isset($_POST['spp_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spp_nonce'])), 'spp_login_nonce')) {
                wp_die(esc_html__('Security check failed', 'sports-player-platform'));
            }

            $login_url = wp_get_referer() ?: home_url('/login/');
            $ip_key    = 'spp_login_attempts_' . md5($this->get_client_ip());
            $attempts  = (int) get_transient($ip_key);

            if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
                wp_safe_redirect(add_query_arg([
                    'spp_login_error' => rawurlencode(__('Too many failed login attempts. Please try again in 15 minutes.', 'sports-player-platform')),
                ], $login_url));
                exit;
            }

            $login_input = sanitize_text_field(wp_unslash($_POST['username'] ?? ''));
            $password    = $_POST['password'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

            // Check email verification before signing in
            $user_obj = is_email($login_input)
                ? get_user_by('email', $login_input)
                : get_user_by('login', $login_input);

            if ($user_obj && get_user_meta($user_obj->ID, 'spp_email_verified', true) === '0') {
                wp_safe_redirect(add_query_arg([
                    'spp_login_error' => rawurlencode(__('Please verify your email address before logging in.', 'sports-player-platform')),
                    'spp_unverified'  => '1',
                    'spp_user'        => rawurlencode($user_obj->user_login),
                ], $login_url));
                exit;
            }

            $creds = [
                'user_login'    => $login_input,
                'user_password' => $password,
                'remember'      => !empty($_POST['remember']),
            ];

            $user = wp_signon($creds, is_ssl());

            if (is_wp_error($user)) {
                set_transient($ip_key, $attempts + 1, self::LOCKOUT_SECONDS);
                wp_safe_redirect(add_query_arg([
                    'spp_login_error' => rawurlencode(wp_strip_all_tags($user->get_error_message())),
                ], $login_url));
                exit;
            }

            delete_transient($ip_key);
            wp_safe_redirect($this->get_role_redirect_url($user));
            exit;
        }

        /**
         * Handle user logout.
         */
        public function handle_logout(): void {
            if (!isset($_GET['spp_logout']) || $_GET['spp_logout'] !== '1') {
                return;
            }

            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'spp_logout_nonce')) {
                wp_die(esc_html__('Security check failed', 'sports-player-platform'));
            }

            wp_logout();
            wp_safe_redirect(home_url('/login/'));
            exit;
        }

        /**
         * Handle user registration form submission.
         */
        public function handle_registration_form(): void {
            if (!isset($_POST['spp_register_action']) || $_POST['spp_register_action'] !== 'register') {
                return;
            }

            if (!isset($_POST['spp_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spp_nonce'])), 'spp_register_nonce')) {
                wp_die(esc_html__('Security check failed', 'sports-player-platform'));
            }

            $register_url = wp_get_referer() ?: home_url('/register/');
            $full_name    = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
            $username     = sanitize_user(wp_unslash($_POST['username'] ?? ''), true);
            $email        = sanitize_email(wp_unslash($_POST['email'] ?? ''));
            $password     = $_POST['password'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $role         = sanitize_text_field(wp_unslash($_POST['role'] ?? ''));
            $sport        = sanitize_text_field(wp_unslash($_POST['sport'] ?? ''));

            $error = $this->validate_registration($full_name, $username, $email, $password, $role);
            if ($error !== '') {
                wp_safe_redirect(add_query_arg(['spp_register_error' => rawurlencode($error)], $register_url));
                exit;
            }

            $user_id = wp_create_user($username, $password, $email);
            if (is_wp_error($user_id)) {
                wp_safe_redirect(add_query_arg([
                    'spp_register_error' => rawurlencode($user_id->get_error_message()),
                ], $register_url));
                exit;
            }

            $user = new WP_User($user_id);
            $user->set_role($role);

            wp_update_user([
                'ID'           => $user_id,
                'display_name' => $full_name,
                'first_name'   => $full_name,
            ]);

            if ($sport !== '') {
                update_user_meta($user_id, 'spp_sport', $sport);
            }

            // Generate email verification token
            $token = wp_generate_password(32, false);
            update_user_meta($user_id, 'spp_email_verified', '0');
            update_user_meta($user_id, 'spp_email_verify_token', $token);

            $this->send_verification_email($user_id, $email, $token);

            wp_safe_redirect(add_query_arg(['spp_register_success' => '1'], home_url('/login/')));
            exit;
        }

        /**
         * Handle email verification link clicks.
         */
        public function handle_email_verification(): void {
            if (!is_page('verify-email') && !isset($_GET['spp_verify_token'])) {
                return;
            }

            $token    = sanitize_text_field(wp_unslash($_GET['token'] ?? ($_GET['spp_verify_token'] ?? '')));
            $username = sanitize_user(wp_unslash($_GET['user'] ?? ''), true);

            if ($token === '' || $username === '') {
                wp_safe_redirect(add_query_arg([
                    'spp_login_error' => rawurlencode(__('Invalid verification link.', 'sports-player-platform')),
                ], home_url('/login/')));
                exit;
            }

            $user = get_user_by('login', $username);
            if (!$user) {
                wp_safe_redirect(add_query_arg([
                    'spp_login_error' => rawurlencode(__('User account not found.', 'sports-player-platform')),
                ], home_url('/login/')));
                exit;
            }

            $stored_token = (string) get_user_meta($user->ID, 'spp_email_verify_token', true);
            if ($stored_token === '' || !hash_equals($stored_token, $token)) {
                wp_safe_redirect(add_query_arg([
                    'spp_login_error' => rawurlencode(__('Verification token is invalid or expired.', 'sports-player-platform')),
                ], home_url('/login/')));
                exit;
            }

            update_user_meta($user->ID, 'spp_email_verified', '1');
            delete_user_meta($user->ID, 'spp_email_verify_token');
            $this->send_welcome_email($user->ID, $user->user_email);

            wp_safe_redirect(add_query_arg(['spp_verified' => '1'], home_url('/login/')));
            exit;
        }

        /**
         * Handle resending the verification email.
         */
        public function handle_resend_verification(): void {
            if (!isset($_GET['spp_resend_verify']) || $_GET['spp_resend_verify'] !== '1') {
                return;
            }

            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'spp_resend_verify_nonce')) {
                wp_die(esc_html__('Security check failed', 'sports-player-platform'));
            }

            $username = sanitize_user(wp_unslash($_GET['user'] ?? ''), true);
            $user     = get_user_by('login', $username);

            if ($user && get_user_meta($user->ID, 'spp_email_verified', true) === '0') {
                $token = wp_generate_password(32, false);
                update_user_meta($user->ID, 'spp_email_verify_token', $token);
                $this->send_verification_email($user->ID, $user->user_email, $token);
            }

            wp_safe_redirect(add_query_arg(['spp_resent' => '1'], home_url('/login/')));
            exit;
        }

        /**
         * Handle password reset request and confirmation.
         */
        public function handle_password_reset(): void {
            if (isset($_POST['spp_reset_action']) && $_POST['spp_reset_action'] === 'reset_request') {
                if (!isset($_POST['spp_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spp_nonce'])), 'spp_reset_nonce')) {
                    wp_die(esc_html__('Security check failed', 'sports-player-platform'));
                }

                $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
                $user  = $email ? get_user_by('email', $email) : false;

                if ($user) {
                    $key = get_password_reset_key($user);
                    if (!is_wp_error($key)) {
                        $reset_url = add_query_arg([
                            'action' => 'rp',
                            'key'    => rawurlencode($key),
                            'login'  => rawurlencode($user->user_login),
                        ], home_url('/reset-password/'));

                        $subject = __('Password Reset Request - Sports Player Platform', 'sports-player-platform');
                        $message = sprintf(
                            __("Hello %s,\n\nSomeone requested a password reset for your account. Click the link below to set a new password:\n\n%s\n\nIf you did not request this, you can safely ignore this email.", 'sports-player-platform'),
                            $user->display_name,
                            $reset_url
                        );
                        wp_mail($user->user_email, $subject, $message);
                    }
                }

                wp_safe_redirect(add_query_arg(['spp_reset_sent' => '1'], home_url('/reset-password/')));
                exit;
            }

            if (isset($_POST['spp_reset_action']) && $_POST['spp_reset_action'] === 'reset_confirm') {
                if (!isset($_POST['spp_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spp_nonce'])), 'spp_reset_confirm_nonce')) {
                    wp_die(esc_html__('Security check failed', 'sports-player-platform'));
                }

                $key         = sanitize_text_field(wp_unslash($_POST['key'] ?? ''));
                $login       = sanitize_user(wp_unslash($_POST['login'] ?? ''), true);
                $pwd         = $_POST['pwd'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $pwd_confirm = $_POST['pwd_confirm'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

                $userdata = check_password_reset_key($key, $login);
                if (is_wp_error($userdata)) {
                    wp_safe_redirect(add_query_arg([
                        'spp_reset_error' => rawurlencode($userdata->get_error_message()),
                    ], home_url('/reset-password/')));
                    exit;
                }

                if (strlen($pwd) < 8 || $pwd !== $pwd_confirm) {
                    wp_safe_redirect(add_query_arg([
                        'action'          => 'rp',
                        'key'             => rawurlencode($key),
                        'login'           => rawurlencode($login),
                        'spp_reset_error' => rawurlencode(__('Passwords must match and be at least 8 characters long.', 'sports-player-platform')),
                    ], home_url('/reset-password/')));
                    exit;
                }

                reset_password($userdata, $pwd);
                wp_safe_redirect(add_query_arg(['spp_reset_success' => '1'], home_url('/login/')));
                exit;
            }
        }

        /**
         * Guard protected dashboard and membership-gated pages.
         */
        public function guard_protected_pages(): void {
            if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
                return;
            }

            $request_path = trim((string) parse_url(add_query_arg([]), PHP_URL_PATH), '/');
            $is_dashboard = ($request_path === 'dashboard' || strpos($request_path, 'dashboard/') === 0);

            if (!$is_dashboard) {
                return;
            }

            if (!is_user_logged_in()) {
                wp_safe_redirect(add_query_arg([
                    'redirect_to' => rawurlencode(home_url('/' . $request_path . '/')),
                ], home_url('/login/')));
                exit;
            }

            $user_id = get_current_user_id();

            // Paid membership required for messaging
            if ($request_path === 'dashboard/messages' && !current_user_can('manage_options')) {
                if (function_exists('spp_user_has_membership') && !spp_user_has_membership($user_id, 'paid')) {
                    wp_safe_redirect(home_url('/pricing/'));
                    exit;
                }
            }
        }

        /**
         * Send verification email to newly registered user.
         */
        public function send_verification_email(int $user_id, string $email, string $token): void {
            $user = get_userdata($user_id);
            if (!$user) {
                return;
            }

            $verify_url = add_query_arg([
                'token' => rawurlencode($token),
                'user'  => rawurlencode($user->user_login),
            ], home_url('/verify-email/'));

            $subject = __('Verify Your Email - Sports Player Platform', 'sports-player-platform');
            $message = sprintf(
                __("Hello %s,\n\nPlease verify your email address by clicking the link below:\n\n%s\n\nThank you,\nThe Sports Player Platform Team", 'sports-player-platform'),
                $user->display_name,
                $verify_url
            );

            wp_mail($email, $subject, $message);
        }

        /**
         * Send welcome email after email verification.
         */
        public function send_welcome_email(int $user_id, string $email): void {
            $user = get_userdata($user_id);
            if (!$user) {
                return;
            }

            $subject = __('Welcome to Sports Player Platform', 'sports-player-platform');
            $message = sprintf(
                __("Hello %s,\n\nYour email is verified and your account is now active!\n\nLog in here: %s\n\nBest regards,\nThe Sports Player Platform Team", 'sports-player-platform'),
                $user->display_name,
                home_url('/login/')
            );

            wp_mail($email, $subject, $message);
        }

        /**
         * Render [spp_login_form] shortcode.
         */
        public function render_login_form(): string {
            if (is_user_logged_in()) {
                return '<p>' . esc_html__('You are already logged in.', 'sports-player-platform') . ' <a href="' . esc_url(home_url('/dashboard/')) . '">' . esc_html__('Go to Dashboard', 'sports-player-platform') . '</a></p>';
            }

            return $this->load_template('auth/login.php', [
                'error'           => isset($_GET['spp_login_error']) ? sanitize_text_field(wp_unslash($_GET['spp_login_error'])) : '',
                'unverified'      => isset($_GET['spp_unverified']) && $_GET['spp_unverified'] === '1',
                'unverified_user' => isset($_GET['spp_user']) ? sanitize_user(wp_unslash($_GET['spp_user']), true) : '',
                'registered'      => isset($_GET['spp_register_success']) && $_GET['spp_register_success'] === '1',
                'verified'        => isset($_GET['spp_verified']) && $_GET['spp_verified'] === '1',
                'resent'          => isset($_GET['spp_resent']) && $_GET['spp_resent'] === '1',
                'reset_success'   => isset($_GET['spp_reset_success']) && $_GET['spp_reset_success'] === '1',
            ]);
        }

        /**
         * Render [spp_register_form] shortcode.
         */
        public function render_registration_form(): string {
            if (is_user_logged_in()) {
                return '<p>' . esc_html__('You are already logged in.', 'sports-player-platform') . '</p>';
            }

            return $this->load_template('auth/register.php', [
                'error' => isset($_GET['spp_register_error']) ? sanitize_text_field(wp_unslash($_GET['spp_register_error'])) : '',
            ]);
        }

        /**
         * Render [spp_password_reset_form] shortcode.
         */
        public function render_password_reset_form(): string {
            $mode  = 'request';
            $key   = sanitize_text_field(wp_unslash($_GET['key'] ?? ''));
            $login = sanitize_user(wp_unslash($_GET['login'] ?? ''), true);
            $error = isset($_GET['spp_reset_error']) ? sanitize_text_field(wp_unslash($_GET['spp_reset_error'])) : '';
            $sent  = isset($_GET['spp_reset_sent']) && $_GET['spp_reset_sent'] === '1';

            if (isset($_GET['action']) && $_GET['action'] === 'rp') {
                $userdata = check_password_reset_key($key, $login);
                if (is_wp_error($userdata)) {
                    $mode  = 'invalid';
                    $error = $userdata->get_error_message();
                } else {
                    $mode = 'confirm';
                }
            }

            return $this->load_template('auth/reset-password.php', [
                'mode'  => $mode,
                'error' => $error,
                'sent'  => $sent,
                'key'   => $key,
                'login' => $login,
            ]);
        }

        /**
         * Validate registration inputs and return an error message or empty string.
         */
        private function validate_registration(string $full_name, string $username, string $email, string $password, string $role): string {
            if ($full_name === '' || $username === '' || !is_email($email)) {
                return __('Please fill in all required fields with a valid email address.', 'sports-player-platform');
            }
            if (username_exists($username)) {
                return __('That username is already taken.', 'sports-player-platform');
            }
            if (email_exists($email)) {
                return __('An account with that email address already exists.', 'sports-player-platform');
            }
            if (strlen($password) < 8) {
                return __('Password must be at least 8 characters long.', 'sports-player-platform');
            }
            if (!in_array($role, [self::ROLE_PLAYER, self::ROLE_PARENT, self::ROLE_COACH], true)) {
                return __('Please select a valid account role.', 'sports-player-platform');
            }
            return '';
        }

        /**
         * Determine redirect URL based on user role after login.
         */
        private function get_role_redirect_url(WP_User $user): string {
            if (!empty($_REQUEST['redirect_to'])) {
                return wp_validate_redirect(esc_url_raw(wp_unslash($_REQUEST['redirect_to'])), home_url('/dashboard/'));
            }
            if (in_array('administrator', $user->roles, true)) {
                return admin_url();
            }
            if (in_array(self::ROLE_COACH, $user->roles, true)) {
                return home_url('/dashboard/recruiting/');
            }
            if (in_array(self::ROLE_PLAYER, $user->roles, true) || in_array(self::ROLE_PARENT, $user->roles, true)) {
                return home_url('/dashboard/');
            }
            return home_url();
        }

        /**
         * Get client IP address for rate limiting.
         */
        private function get_client_ip(): string {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        }

        /**
         * Load a template file from templates/ and return rendered HTML.
         */
        private function load_template(string $relative_path, array $vars = []): string {
            $template_path = SPP_PLUGIN_DIR . 'templates/' . ltrim($relative_path, '/');
            if (!file_exists($template_path)) {
                return '';
            }

            extract($vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            ob_start();
            include $template_path;
            return (string) ob_get_clean();
        }
    }
}
