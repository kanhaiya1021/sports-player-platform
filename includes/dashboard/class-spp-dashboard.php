<?php
/**
 * User Dashboard Class
 *
 * Handles [spp_dashboard], [spp_edit_profile], [spp_my_membership],
 * [spp_shortlist], and their AJAX actions.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Dashboard')) {

    final class SPP_Dashboard {

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
            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

            add_shortcode('spp_dashboard', [$this, 'render_dashboard']);
            add_shortcode('spp_edit_profile', [$this, 'render_edit_profile']);
            add_shortcode('spp_my_membership', [$this, 'render_membership']);
            add_shortcode('spp_shortlist', [$this, 'render_shortlist']);

            add_action('wp_ajax_spp_save_player_profile', [$this, 'ajax_save_player_profile']);
            add_action('wp_ajax_spp_cancel_membership', [$this, 'ajax_cancel_membership']);
            add_action('wp_ajax_spp_remove_saved_player', [$this, 'ajax_remove_saved_player']);
        }

        /**
         * Enqueue dashboard CSS and JS on dashboard pages.
         */
        public function enqueue_assets(): void {
            if (!is_user_logged_in()) {
                return;
            }

            $request_path = trim((string) parse_url(add_query_arg([]), PHP_URL_PATH), '/');
            $is_dashboard = ($request_path === 'dashboard' || strpos($request_path, 'dashboard/') === 0);

            if (!$is_dashboard && !$this->post_has_dashboard_shortcode()) {
                return;
            }

            wp_enqueue_style(
                'spp-dashboard',
                SPP_PLUGIN_URL . 'assets/css/dashboard.css',
                [],
                SPP_VERSION
            );

            wp_enqueue_script(
                'spp-dashboard',
                SPP_PLUGIN_URL . 'assets/js/dashboard.js',
                [],
                SPP_VERSION,
                true
            );

            wp_localize_script('spp-dashboard', 'sppDashboard', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('spp_dashboard_nonce'),
                'i18n'    => [
                    'saving'        => __('Saving...', 'sports-player-platform'),
                    'saved'         => __('Profile saved and submitted for review!', 'sports-player-platform'),
                    'error'         => __('Something went wrong. Please try again.', 'sports-player-platform'),
                    'confirmCancel' => __('Are you sure you want to cancel your membership?', 'sports-player-platform'),
                    'confirmRemove' => __('Remove this player from your shortlist?', 'sports-player-platform'),
                ],
            ]);
        }

        /**
         * Render [spp_dashboard] shortcode.
         */
        public function render_dashboard(): string {
            if (!is_user_logged_in()) {
                return $this->login_required_notice();
            }

            $user_id    = get_current_user_id();
            $user       = wp_get_current_user();
            $role       = function_exists('spp_get_current_user_role') ? spp_get_current_user_role() : '';
            $membership = function_exists('spp_get_user_membership') ? spp_get_user_membership($user_id) : null;
            $stats      = $this->get_user_stats($user_id, (string) $role);

            return $this->load_template('dashboard/dashboard.php', [
                'user'       => $user,
                'role'       => $role,
                'membership' => $membership,
                'stats'      => $stats,
                'active_tab' => 'overview',
            ]);
        }

        /**
         * Render [spp_edit_profile] shortcode (Player only).
         */
        public function render_edit_profile(): string {
            if (!is_user_logged_in()) {
                return $this->login_required_notice();
            }

            $user_id = get_current_user_id();
            if (!current_user_can('edit_own_profile') && !current_user_can('manage_options')) {
                return '<div class="spp-notice spp-notice-error"><p>' . esc_html__('Only player accounts can edit a player profile.', 'sports-player-platform') . '</p></div>';
            }

            $player_post = $this->get_user_player_post($user_id);
            $profile     = $this->get_player_profile_data($user_id, $player_post);
            $completion  = $this->calculate_profile_completion($profile);

            return $this->load_template('dashboard/edit-profile.php', [
                'user_id'     => $user_id,
                'player_post' => $player_post,
                'profile'     => $profile,
                'completion'  => $completion,
                'active_tab'  => 'edit-profile',
            ]);
        }

        /**
         * Render [spp_my_membership] shortcode.
         */
        public function render_membership(): string {
            if (!is_user_logged_in()) {
                return $this->login_required_notice();
            }

            global $wpdb;
            $user_id    = get_current_user_id();
            $membership = function_exists('spp_get_user_membership') ? spp_get_user_membership($user_id) : null;

            $logs_table   = $wpdb->prefix . 'spp_payment_logs';
            $payment_logs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$logs_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 20",
                $user_id
            ), ARRAY_A) ?: [];

            return $this->load_template('dashboard/membership.php', [
                'membership'   => $membership,
                'payment_logs' => $payment_logs,
                'active_tab'   => 'membership',
            ]);
        }

        /**
         * Render [spp_shortlist] shortcode (Coach / Parent).
         */
        public function render_shortlist(): string {
            if (!is_user_logged_in()) {
                return $this->login_required_notice();
            }

            if (!current_user_can('view_saved_players') && !current_user_can('manage_options')) {
                return '<div class="spp-notice spp-notice-error"><p>' . esc_html__('Shortlist is available for Coach and Parent accounts.', 'sports-player-platform') . '</p></div>';
            }

            $user_id       = get_current_user_id();
            $saved_players = class_exists('SPP_Recruiting')
                ? SPP_Recruiting::get_instance()->get_saved_players($user_id)
                : [];

            return $this->load_template('dashboard/shortlist.php', [
                'saved_players' => $saved_players,
                'active_tab'    => 'shortlist',
            ]);
        }

        /**
         * AJAX: Save player profile and set post status to spp_pending for admin approval.
         */
        public function ajax_save_player_profile(): void {
            check_ajax_referer('spp_dashboard_nonce', 'nonce');

            if (!is_user_logged_in() || (!current_user_can('edit_own_profile') && !current_user_can('manage_options'))) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 403);
            }

            $user_id     = get_current_user_id();
            $player_name = sanitize_text_field(wp_unslash($_POST['player_name'] ?? ''));
            $bio         = wp_kses_post(wp_unslash($_POST['bio'] ?? ''));

            if ($player_name === '') {
                wp_send_json_error(['message' => __('Player name is required.', 'sports-player-platform')], 400);
            }

            $existing_post = $this->get_user_player_post($user_id);
            $post_data     = [
                'post_title'   => $player_name,
                'post_content' => $bio,
                'post_type'    => 'player',
                'post_author'  => $user_id,
                'post_status'  => 'spp_pending',
            ];

            if ($existing_post) {
                $post_data['ID'] = $existing_post->ID;
                $post_id         = wp_update_post($post_data, true);
            } else {
                $post_id = wp_insert_post($post_data, true);
            }

            if (is_wp_error($post_id)) {
                wp_send_json_error(['message' => $post_id->get_error_message()], 500);
            }

            $meta_fields = [
                'date_of_birth'     => sanitize_text_field(wp_unslash($_POST['date_of_birth'] ?? '')),
                'location'          => sanitize_text_field(wp_unslash($_POST['location'] ?? '')),
                'avatar_url'        => esc_url_raw(wp_unslash($_POST['avatar_url'] ?? '')),
                'sport'             => sanitize_text_field(wp_unslash($_POST['sport'] ?? '')),
                'playing_position'  => sanitize_text_field(wp_unslash($_POST['playing_position'] ?? '')),
                'height'            => sanitize_text_field(wp_unslash($_POST['height'] ?? '')),
                'weight'            => sanitize_text_field(wp_unslash($_POST['weight'] ?? '')),
                'graduation_year'   => absint($_POST['graduation_year'] ?? 0) ?: '',
                'school'            => sanitize_text_field(wp_unslash($_POST['school'] ?? '')),
                'image_gallery'     => sanitize_textarea_field(wp_unslash($_POST['image_gallery'] ?? '')),
                'videos_highlights' => esc_url_raw(wp_unslash($_POST['videos_highlights'] ?? '')),
                'gpa'               => sanitize_text_field(wp_unslash($_POST['gpa'] ?? '')),
                'sat_act_scores'    => sanitize_text_field(wp_unslash($_POST['sat_act_scores'] ?? '')),
                'awards'            => sanitize_textarea_field(wp_unslash($_POST['awards'] ?? '')),
            ];

            foreach ($meta_fields as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }

            if (!empty($meta_fields['avatar_url'])) {
                update_user_meta($user_id, 'spp_avatar_url', $meta_fields['avatar_url']);
            }

            $profile    = $this->get_player_profile_data($user_id, get_post($post_id));
            $completion = $this->calculate_profile_completion($profile);

            wp_send_json_success([
                'message'    => __('Profile saved and submitted for review!', 'sports-player-platform'),
                'post_id'    => $post_id,
                'completion' => $completion,
                'status'     => 'spp_pending',
            ]);
        }

        /**
         * AJAX: Cancel active membership.
         */
        public function ajax_cancel_membership(): void {
            check_ajax_referer('spp_dashboard_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 403);
            }

            $user_id = get_current_user_id();
            $result  = class_exists('SPP_Membership')
                ? SPP_Membership::get_instance()->cancel_membership($user_id)
                : false;

            if (!$result) {
                wp_send_json_error(['message' => __('No active membership found to cancel.', 'sports-player-platform')], 400);
            }

            wp_send_json_success(['message' => __('Your membership has been cancelled.', 'sports-player-platform')]);
        }

        /**
         * AJAX: Remove a player from shortlist.
         */
        public function ajax_remove_saved_player(): void {
            check_ajax_referer('spp_dashboard_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 403);
            }

            $player_id = absint($_POST['player_id'] ?? 0);
            if (!$player_id) {
                wp_send_json_error(['message' => __('Invalid player ID.', 'sports-player-platform')], 400);
            }

            $removed = class_exists('SPP_Recruiting')
                ? SPP_Recruiting::get_instance()->remove_saved_player(get_current_user_id(), $player_id)
                : false;

            if (!$removed) {
                wp_send_json_error(['message' => __('Could not remove player.', 'sports-player-platform')], 400);
            }

            wp_send_json_success(['message' => __('Player removed from shortlist.', 'sports-player-platform')]);
        }

        /**
         * Compute role-specific quick stats for the dashboard home widget.
         */
        private function get_user_stats(int $user_id, string $role): array {
            global $wpdb;

            $messages_table = $wpdb->prefix . 'spp_messages';
            $views_table    = $wpdb->prefix . 'spp_player_views';
            $saved_table    = $wpdb->prefix . 'spp_saved_players';
            $lists_table    = $wpdb->prefix . 'spp_recruiting_lists';

            if ($role === 'spp_coach') {
                $shortlisted = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$saved_table} WHERE user_id = %d", $user_id));
                $msgs_sent   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$messages_table} WHERE sender_id = %d", $user_id));
                $lists_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$lists_table} WHERE coach_id = %d AND is_active = 1", $user_id));

                return [
                    ['label' => __('Players Shortlisted', 'sports-player-platform'), 'value' => $shortlisted],
                    ['label' => __('Messages Sent', 'sports-player-platform'), 'value' => $msgs_sent],
                    ['label' => __('Recruiting Lists', 'sports-player-platform'), 'value' => $lists_count],
                ];
            }

            if ($role === 'spp_parent') {
                $shortlisted = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$saved_table} WHERE user_id = %d", $user_id));
                return [
                    ['label' => __('Saved Players', 'sports-player-platform'), 'value' => $shortlisted],
                ];
            }

            // Default: Player stats
            $player_post   = $this->get_user_player_post($user_id);
            $views_month   = 0;
            $completion    = 0;

            if ($player_post) {
                $views_month = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$views_table} WHERE player_id = %d AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                    $player_post->ID
                ));
                $completion = $this->calculate_profile_completion($this->get_player_profile_data($user_id, $player_post));
            }

            $msgs_received = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$messages_table} WHERE recipient_id = %d AND is_deleted = 0",
                $user_id
            ));

            return [
                ['label' => __('Profile Views (30d)', 'sports-player-platform'), 'value' => $views_month],
                ['label' => __('Messages Received', 'sports-player-platform'), 'value' => $msgs_received],
                ['label' => __('Profile Completion', 'sports-player-platform'), 'value' => $completion . '%'],
            ];
        }

        /**
         * Get the player CPT post authored by the given user.
         */
        private function get_user_player_post(int $user_id): ?WP_Post {
            $posts = get_posts([
                'post_type'      => 'player',
                'author'         => $user_id,
                'post_status'    => ['publish', 'spp_pending', 'draft', 'pending'],
                'posts_per_page' => 1,
            ]);

            return !empty($posts) ? $posts[0] : null;
        }

        /**
         * Collect player profile fields from post and meta.
         */
        private function get_player_profile_data(int $user_id, ?WP_Post $player_post): array {
            $user    = get_userdata($user_id);
            $post_id = $player_post ? $player_post->ID : 0;

            $keys = [
                'date_of_birth', 'location', 'avatar_url', 'sport', 'playing_position',
                'height', 'weight', 'graduation_year', 'school', 'image_gallery',
                'videos_highlights', 'gpa', 'sat_act_scores', 'awards',
            ];

            $data = [
                'player_name' => $player_post ? $player_post->post_title : ($user ? $user->display_name : ''),
                'bio'         => $player_post ? $player_post->post_content : '',
                'status'      => $player_post ? $player_post->post_status : 'new',
            ];

            foreach ($keys as $key) {
                $data[$key] = $post_id ? (string) get_post_meta($post_id, $key, true) : '';
            }

            if ($data['sport'] === '') {
                $data['sport'] = (string) get_user_meta($user_id, 'spp_sport', true);
            }

            return $data;
        }

        /**
         * Calculate profile completion percentage (0-100).
         */
        private function calculate_profile_completion(array $profile): int {
            $tracked = [
                'player_name', 'bio', 'date_of_birth', 'location', 'sport',
                'playing_position', 'height', 'weight', 'graduation_year', 'school',
            ];

            $filled = 0;
            foreach ($tracked as $field) {
                if (!empty($profile[$field])) {
                    $filled++;
                }
            }

            return (int) round(($filled / count($tracked)) * 100);
        }

        private function post_has_dashboard_shortcode(): bool {
            global $post;
            if (!$post instanceof WP_Post) {
                return false;
            }
            foreach (['spp_dashboard', 'spp_edit_profile', 'spp_my_membership', 'spp_shortlist'] as $tag) {
                if (has_shortcode($post->post_content, $tag)) {
                    return true;
                }
            }
            return false;
        }

        private function login_required_notice(): string {
            return '<div class="spp-notice spp-notice-error"><p>' .
                esc_html__('Please log in to access your dashboard.', 'sports-player-platform') .
                ' <a href="' . esc_url(home_url('/login/')) . '">' . esc_html__('Log In', 'sports-player-platform') . '</a></p></div>';
        }

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
