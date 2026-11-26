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
        const ROLE_COACH = 'spp_coach';

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
            add_action('template_redirect', [$this, 'handle_password_reset']);

            add_shortcode('spp_login_form', [$this, 'render_login_form']);
            add_shortcode('spp_register_form', [$this, 'render_registration_form']);
            add_shortcode('spp_password_reset_form', [$this, 'render_password_reset_form']);
        }

        public function register_user_roles(): void {
            if (get_role(self::ROLE_PLAYER)) {
                return;
            }

            add_role(self::ROLE_PLAYER, 'Player/Athlete', [
                'read' => true,
                'list_players' => true,
            ]);

            add_role(self::ROLE_PARENT, 'Parent', [
                'read' => true,
                'list_players' => true,
                'view_saved_players' => true,
            ]);

            add_role(self::ROLE_COACH, 'Coach', [
                'read' => true,
                'list_players' => true,
                'view_saved_players' => true,
                'track_recruiting' => true,
            ]);
        }

        public function handle_login(): void {
            if (!isset($_POST['spp_login_action']) || $_POST['spp_login_action'] !== 'login') {
                return;
            }

            if (!isset($_POST['spp_nonce']) || !wp_verify_nonce($_POST['spp_nonce'], 'spp_login_nonce')) {
                wp_die(__('Security check failed', 'sports-player-platform'));
            }

            $creds = [
                'user_login' => sanitize_user($_POST['username'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'remember' => isset($_POST['remember']),
            ];

            $user = wp_signon($creds, is_ssl());

            if (is_wp_error($user)) {
                wp_safe_redirect(add_query_arg([
                    'spp_login_error' => urlencode($user->get_error_message()),
                ], wp_get_referer() ?: home_url()));
                exit;
            }

            wp_safe_redirect(home_url());
            exit;
        }

        public function handle_logout(): void {
            if (!isset($_GET['spp_logout']) || $_GET['spp_logout'] !== '1') {
                return;
            }

            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'spp_logout_nonce')) {
                wp_die(__('Security check failed', 'sports-player-platform'));
            }

            wp_logout();
            wp_safe_redirect(home_url());
            exit;
        }

        public function handle_registration_form(): void {
            if (!isset($_POST['spp_register_action']) || $_POST['spp_register_action'] !== 'register') {
                return;
            }

            if (!isset($_POST['spp_nonce']) || !wp_verify_nonce($_POST['spp_nonce'], 'spp_register_nonce')) {
                wp_die(__('Security check failed', 'sports-player-platform'));
            }

            $username = sanitize_user($_POST['username'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = sanitize_text_field($_POST['role'] ?? '');

            if (!username_exists($username) && !email_exists($email) && wp_validate_password_strength($password) >= 2) {
                $user_id = wp_create_user($username, $password, $email);

                if (!is_wp_error($user_id)) {
                    $valid_roles = [self::ROLE_PLAYER, self::ROLE_PARENT, self::ROLE_COACH];
                    if (in_array($role, $valid_roles)) {
                        $user = new WP_User($user_id);
                        $user->set_role($role);
                    }

                    $this->send_welcome_email($user_id, $email);

                    wp_safe_redirect(add_query_arg([
                        'spp_register_success' => '1',
                    ], wp_get_referer() ?: home_url()));
                    exit;
                }
            }

            wp_safe_redirect(add_query_arg([
                'spp_register_error' => urlencode(__('Registration failed. Please try again.', 'sports-player-platform')),
            ], wp_get_referer() ?: home_url()));
            exit;
        }

        public function handle_password_reset(): void {
            if (isset($_POST['spp_reset_action']) && $_POST['spp_reset_action'] === 'reset_request') {
                if (!isset($_POST['spp_nonce']) || !wp_verify_nonce($_POST['spp_nonce'], 'spp_reset_nonce')) {
                    return;
                }

                $email = sanitize_email($_POST['email'] ?? '');
                if ($email && email_exists($email)) {
                    retrieve_password($email);
                    wp_safe_redirect(add_query_arg([
                        'spp_reset_sent' => '1',
                    ], wp_get_referer() ?: home_url()));
                    exit;
                }
            }

            if (isset($_GET['action']) && $_GET['action'] === 'rp') {
                $key = $_GET['key'] ?? '';
                if (isset($_POST['spp_reset_action']) && $_POST['spp_reset_action'] === 'reset_confirm') {
                    if (!isset($_POST['spp_nonce']) || !wp_verify_nonce($_POST['spp_nonce'], 'spp_reset_confirm_nonce')) {
                        return;
                    }

                    $passwd = $_POST['pwd'] ?? '';
                    $userdata = check_password_reset_key($key, $passwd);

                    if (!is_wp_error($userdata) && $_POST['pwd'] === $_POST['pwd_confirm']) {
                        wp_reset_password($key, $passwd);
                        wp_safe_redirect(home_url());
                        exit;
                    }
                }
            }
        }

        public function send_welcome_email(int $user_id, string $email): void {
            $user = get_userdata($user_id);
            $subject = __('Welcome to Sports Player Platform', 'sports-player-platform');
            $message = sprintf(
                __("Hello %s,\n\nWelcome to our platform! Your account has been created successfully.\n\nBest regards,\nThe Sports Player Platform Team", 'sports-player-platform'),
                $user->display_name
            );

            wp_mail($email, $subject, $message);
        }

        public function render_login_form(): string {
            if (is_user_logged_in()) {
                return '<p>' . __('You are already logged in.', 'sports-player-platform') . '</p>';
            }

            $error = isset($_GET['spp_login_error']) ? sanitize_text_field($_GET['spp_login_error']) : '';
            $success = isset($_GET['spp_register_success']) && $_GET['spp_register_success'] === '1';

            ob_start();
            ?>
            <div class="spp-auth-container">
                <?php if ($success): ?>
                    <div class="notice notice-success">
                        <p><?php esc_html_e('Registration successful! You can now log in.', 'sports-player-platform'); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="notice notice-error">
                        <p><?php echo esc_html($error); ?></p>
                    </div>
                <?php endif; ?>

                <h3><?php esc_html_e('Login', 'sports-player-platform'); ?></h3>
                <form method="post" action="">
                    <?php wp_nonce_field('spp_login_nonce', 'spp_nonce'); ?>
                    <input type="hidden" name="spp_login_action" value="login">
                    <p>
                        <label for="username"><?php esc_html_e('Username', 'sports-player-platform'); ?></label>
                        <input type="text" name="username" id="username" required>
                    </p>
                    <p>
                        <label for="password"><?php esc_html_e('Password', 'sports-player-platform'); ?></label>
                        <input type="password" name="password" id="password" required>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="remember"> <?php esc_html_e('Remember me', 'sports-player-platform'); ?>
                        </label>
                    </p>
                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Log In', 'sports-player-platform'); ?></button>
                    </p>
                </form>

                <p><a href="<?php echo esc_url(add_query_arg(['action' => 'rp'], home_url('password-reset'))); ?>"><?php esc_html_e('Forgot your password?', 'sports-player-platform'); ?></a></p>
            </div>
            <?php
            return ob_get_clean();
        }

        public function render_registration_form(): string {
            if (is_user_logged_in()) {
                return '<p>' . __('You are already logged in.', 'sports-player-platform') . '</p>';
            }

            $error = isset($_GET['spp_register_error']) ? sanitize_text_field($_GET['spp_register_error']) : '';

            ob_start();
            ?>
            <div class="spp-auth-container">
                <?php if (!empty($error)): ?>
                    <div class="notice notice-error">
                        <p><?php echo esc_html($error); ?></p>
                    </div>
                <?php endif; ?>

                <h3><?php esc_html_e('Register', 'sports-player-platform'); ?></h3>
                <form method="post" action="">
                    <?php wp_nonce_field('spp_register_nonce', 'spp_nonce'); ?>
                    <input type="hidden" name="spp_register_action" value="register">
                    <p>
                        <label for="username"><?php esc_html_e('Username', 'sports-player-platform'); ?></label>
                        <input type="text" name="username" id="username" required maxlength="49">
                    </p>
                    <p>
                        <label for="email"><?php esc_html_e('Email', 'sports-player-platform'); ?></label>
                        <input type="email" name="email" id="email" required>
                    </p>
                    <p>
                        <label for="password"><?php esc_html_e('Password', 'sports-player-platform'); ?></label>
                        <input type="password" name="password" id="password" required minlength="8">
                    </p>
                    <p>
                        <label for="role"><?php esc_html_e('Role', 'sports-player-platform'); ?></label>
                        <select name="role" id="role" required>
                            <option value=""><?php esc_html_e('Select Role', 'sports-player-platform'); ?></option>
                            <option value="spp_player"><?php esc_html_e('Player/Athlete', 'sports-player-platform'); ?></option>
                            <option value="spp_parent"><?php esc_html_e('Parent', 'sports-player-platform'); ?></option>
                            <option value="spp_coach"><?php esc_html_e('Coach', 'sports-player-platform'); ?></option>
                        </select>
                    </p>
                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Register', 'sports-player-platform'); ?></button>
                    </p>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }

        public function render_password_reset_form(): string {
            $key = $_GET['key'] ?? '';
            $error = '';

            if (isset($_GET['action']) && $_GET['action'] === 'rp') {
                $userdata = check_password_reset_key($key);

                if (is_wp_error($userdata)) {
                    return '<p>' . esc_html($userdata->get_error_message()) . '</p>';
                }

                if (isset($_POST['spp_nonce']) && wp_verify_nonce($_POST['spp_nonce'], 'spp_reset_confirm_nonce')) {
                    if (isset($_POST['pwd']) && $_POST['pwd'] === $_POST['pwd_confirm'] && $_POST['pwd'] === $key) {
                        wp_reset_password($key, $_POST['pwd']);
                        return '<p>' . __('Password reset successful!', 'sports-player-platform') . '</p>';
                    }
                }

                ob_start();
                ?>
                <form method="post">
                    <?php wp_nonce_field('spp_reset_confirm_nonce', 'spp_nonce'); ?>
                    <input type="hidden" name="spp_reset_action" value="reset_confirm">
                    <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
                    <p>
                        <label for="pwd"><?php esc_html_e('New Password', 'sports-player-platform'); ?></label>
                        <input type="password" name="pwd" id="pwd" required minlength="8">
                    </p>
                    <p>
                        <label for="pwd_confirm"><?php esc_html_e('Confirm New Password', 'sports-player-platform'); ?></label>
                        <input type="password" name="pwd_confirm" id="pwd_confirm" required minlength="8">
                    </p>
                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Reset Password', 'sports-player-platform'); ?></button>
                    </p>
                </form>
                <?php
                return ob_get_clean();
            }

            ob_start();
            ?>
            <form method="post">
                <?php wp_nonce_field('spp_reset_nonce', 'spp_nonce'); ?>
                <input type="hidden" name="spp_reset_action" value="reset_request">
                <p>
                    <label for="email"><?php esc_html_e('Enter your email', 'sports-player-platform'); ?></label>
                    <input type="email" name="email" id="email" required>
                </p>
                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Reset Password', 'sports-player-platform'); ?></button>
                </p>
            </form>
            <?php
            return ob_get_clean();
        }
    }
}