<?php
/**
 * Messaging System Class
 *
 * Handles coach-player conversations, monthly plan limits,
 * REST API endpoints, AJAX polling, and in-app/email notifications.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Messaging')) {

    final class SPP_Messaging {

        private static $instance = null;

        const STATUS_SENT = 'sent';
        const STATUS_READ = 'read';
        const MONTHLY_PLAN_LIMIT = 50;

        // WebSocket token TTL (seconds) and HMAC algorithm.
        const WS_TOKEN_TTL  = 60;
        const WS_TOKEN_ALGO = 'sha256';

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
            add_shortcode('spp_messages', [$this, 'render_messages_page']);
            add_shortcode('spp_message_form', [$this, 'render_message_form']);

            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('rest_api_init', [$this, 'register_rest_routes']);

            add_action('wp_ajax_spp_send_message', [$this, 'ajax_send_message']);
            add_action('wp_ajax_spp_fetch_messages', [$this, 'ajax_fetch_messages']);
            add_action('wp_ajax_spp_mark_notifications_read', [$this, 'ajax_mark_notifications_read']);
            add_action('wp_ajax_spp_ws_token', [$this, 'ajax_ws_token']);
        }

        public function create_messaging_tables(): void {
            if (class_exists('SPP_DB')) {
                SPP_DB::get_instance()->create_tables();
            }
        }

        /**
         * Enqueue messaging CSS/JS on messages page.
         */
        public function enqueue_assets(): void {
            if (!is_user_logged_in()) {
                return;
            }

            $request_path = trim((string) parse_url(add_query_arg([]), PHP_URL_PATH), '/');
            global $post;
            $has_shortcode = ($post instanceof WP_Post) && (
                has_shortcode($post->post_content, 'spp_messages') ||
                has_shortcode($post->post_content, 'spp_message_form')
            );

            if ($request_path !== 'dashboard/messages' && !$has_shortcode && strpos($request_path, 'dashboard') !== 0) {
                return;
            }

            wp_enqueue_style('spp-dashboard', SPP_PLUGIN_URL . 'assets/css/dashboard.css', [], SPP_VERSION);
            wp_enqueue_style('spp-messaging', SPP_PLUGIN_URL . 'assets/css/messaging.css', ['spp-dashboard'], SPP_VERSION);
            wp_enqueue_script('spp-messaging', SPP_PLUGIN_URL . 'assets/js/messaging.js', [], SPP_VERSION, true);

            $ws      = $this->get_ws_settings();
            $user_id = get_current_user_id();
            $ws_data = [
                'ajaxUrl'       => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('spp_messaging_nonce'),
                'wsTokenNonce'  => wp_create_nonce('spp_ws_token_nonce'),
                'currentUserId' => $user_id,
                'pollInterval'  => 5000,
                'wsEnabled'     => false,
                'wsUrl'         => '',
                'wsToken'       => '',
                'i18n'          => [
                    'sending'    => __('Sending...', 'sports-player-platform'),
                    'send'       => __('Send Message', 'sports-player-platform'),
                    'error'      => __('Unable to send message.', 'sports-player-platform'),
                    'connecting' => __('Connecting real-time messaging…', 'sports-player-platform'),
                    'offline'    => __('Real-time disconnected. Using periodic refresh.', 'sports-player-platform'),
                    'sendError'  => __('Could not send message. Please try again.', 'sports-player-platform'),
                ],
            ];

            if ($ws['enabled']) {
                $ws_data['wsEnabled'] = true;
                $ws_data['wsUrl']     = $ws['url'];
                $ws_data['wsToken']   = $this->generate_ws_token($user_id);
            }

            wp_localize_script('spp-messaging', 'sppMessaging', $ws_data);
        }

        /**
         * Register REST API routes for messaging & notifications.
         */
        public function register_rest_routes(): void {
            register_rest_route('spp/v1', '/messages', [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'rest_get_conversations'],
                    'permission_callback' => [$this, 'rest_permission_check'],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'rest_send_message'],
                    'permission_callback' => [$this, 'rest_permission_check'],
                ],
            ]);

            register_rest_route('spp/v1', '/messages/(?P<id>\d+)', [
                'methods'             => 'GET',
                'callback'            => [$this, 'rest_get_thread'],
                'permission_callback' => [$this, 'rest_permission_check'],
            ]);

            register_rest_route('spp/v1', '/notifications', [
                'methods'             => 'GET',
                'callback'            => [$this, 'rest_get_notifications'],
                'permission_callback' => static fn(): bool => is_user_logged_in(),
            ]);

            register_rest_route('spp/v1', '/notifications/read', [
                'methods'             => 'POST',
                'callback'            => [$this, 'rest_mark_notifications_read'],
                'permission_callback' => static fn(): bool => is_user_logged_in(),
            ]);
        }

        public function rest_permission_check(): bool {
            if (!is_user_logged_in()) {
                return false;
            }
            $user_id = get_current_user_id();
            return current_user_can('manage_options') || (
                current_user_can('send_messages') &&
                function_exists('spp_user_has_membership') &&
                spp_user_has_membership($user_id, 'paid')
            );
        }

        /**
         * Check whether a user is allowed to send a message right now (role, paid plan, monthly quota).
         *
         * @return true|WP_Error
         */
        public function can_send_message(int $user_id, bool $is_new_conversation = false) {
            if (user_can($user_id, 'manage_options')) {
                return true;
            }

            $user = get_userdata($user_id);
            if (!$user) {
                return new WP_Error('invalid_user', __('Invalid user account.', 'sports-player-platform'));
            }

            if (in_array('spp_parent', $user->roles, true)) {
                return new WP_Error('role_restricted', __('Parent accounts cannot send direct messages. Upgrade a Coach or Player account to message.', 'sports-player-platform'));
            }

            if (!user_can($user_id, 'send_messages')) {
                return new WP_Error('no_permission', __('You do not have permission to send messages.', 'sports-player-platform'));
            }

            if (!function_exists('spp_user_has_membership') || !spp_user_has_membership($user_id, 'paid')) {
                return new WP_Error('paid_required', __('An active Monthly or Annual membership is required to send messages.', 'sports-player-platform'));
            }

            if ($is_new_conversation && in_array('spp_player', $user->roles, true)) {
                return new WP_Error('player_reply_only', __('Players can reply to conversations initiated by coaches.', 'sports-player-platform'));
            }

            // Enforce monthly plan 50 messages/month limit
            $membership = function_exists('spp_get_user_membership') ? spp_get_user_membership($user_id) : null;
            if ($membership && ($membership['type'] ?? '') === 'monthly') {
                $sent_this_month = $this->get_monthly_sent_count($user_id);
                if ($sent_this_month >= self::MONTHLY_PLAN_LIMIT) {
                    return new WP_Error('quota_exceeded', __('You have reached your 50 messages/month limit. Upgrade to Annual for unlimited messaging.', 'sports-player-platform'));
                }
            }

            return true;
        }

        /**
         * Count messages sent by user in the current calendar month.
         */
        public function get_monthly_sent_count(int $user_id): int {
            global $wpdb;
            $table = $wpdb->prefix . 'spp_messages';

            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE sender_id = %d AND MONTH(sent_at) = MONTH(CURRENT_DATE()) AND YEAR(sent_at) = YEAR(CURRENT_DATE())",
                $user_id
            ));
        }

        /**
         * Start a new conversation (or send to recipients).
         */
        public function send_message(int $sender_id, array $recipient_ids, string $subject, string $message): int {
            global $wpdb;

            $conversations_table = $wpdb->prefix . 'spp_conversations';
            $messages_table      = $wpdb->prefix . 'spp_messages';
            $recipient_ids       = array_values(array_unique(array_filter(array_map('absint', $recipient_ids))));

            if (empty($recipient_ids)) {
                return 0;
            }

            $participant_str = implode(',', $recipient_ids);
            $wpdb->insert($conversations_table, [
                'subject'         => sanitize_text_field($subject),
                'sender_id'       => $sender_id,
                'participant_ids' => $participant_str,
            ]);

            $conversation_id = (int) $wpdb->insert_id;
            if (!$conversation_id) {
                return 0;
            }

            foreach ($recipient_ids as $recipient_id) {
                $wpdb->insert($messages_table, [
                    'conversation_id' => $conversation_id,
                    'sender_id'       => $sender_id,
                    'recipient_id'    => $recipient_id,
                    'message'         => wp_kses_post($message),
                ]);

                $this->notify_recipient($sender_id, $recipient_id, $conversation_id, $subject);
            }

            // Push message event to WS relay (all participants).
            $user_ids = array_unique(array_merge([$sender_id], $recipient_ids));
            $this->push_realtime_event([
                'event'    => 'message',
                'user_ids' => $user_ids,
                'payload'  => [
                    'conversation_id' => $conversation_id,
                    'sender_id'       => $sender_id,
                ],
            ]);

            return $conversation_id;
        }

        /**
         * Reply to an existing conversation.
         *
         * @return int|WP_Error Message ID or WP_Error.
         */
        public function reply_to_conversation(int $conversation_id, int $sender_id, string $message) {
            global $wpdb;

            if (!$this->can_user_access_conversation($sender_id, $conversation_id)) {
                return new WP_Error('forbidden', __('You do not have access to this conversation.', 'sports-player-platform'));
            }

            $can_send = $this->can_send_message($sender_id, false);
            if (is_wp_error($can_send)) {
                return $can_send;
            }

            $conversations_table = $wpdb->prefix . 'spp_conversations';
            $messages_table      = $wpdb->prefix . 'spp_messages';

            $conv = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$conversations_table} WHERE id = %d",
                $conversation_id
            ), ARRAY_A);

            if (!$conv) {
                return new WP_Error('not_found', __('Conversation not found.', 'sports-player-platform'));
            }

            $participants = array_map('absint', explode(',', (string) $conv['participant_ids']));
            $participants[] = (int) $conv['sender_id'];
            $recipients = array_values(array_unique(array_filter($participants, static fn(int $id): bool => $id > 0 && $id !== $sender_id)));

            if (empty($recipients)) {
                $recipients = [(int) $conv['sender_id']];
            }

            $inserted_id = 0;
            foreach ($recipients as $recipient_id) {
                $wpdb->insert($messages_table, [
                    'conversation_id' => $conversation_id,
                    'sender_id'       => $sender_id,
                    'recipient_id'    => $recipient_id,
                    'message'         => wp_kses_post($message),
                ]);
                $inserted_id = (int) $wpdb->insert_id;
                $this->notify_recipient($sender_id, $recipient_id, $conversation_id, (string) $conv['subject']);
            }

            $wpdb->update(
                $conversations_table,
                ['updated_at' => current_time('mysql')],
                ['id' => $conversation_id]
            );

            // Push message event to WS relay (all participants).
            $user_ids = array_unique(array_merge([$sender_id], $recipients));
            $this->push_realtime_event([
                'event'    => 'message',
                'user_ids' => $user_ids,
                'payload'  => [
                    'conversation_id' => $conversation_id,
                    'sender_id'       => $sender_id,
                ],
            ]);

            return $inserted_id;
        }

        /**
         * Get user's conversations with unread count and last message preview.
         */
        public function get_user_conversations(int $user_id, int $limit = 50): array {
            global $wpdb;

            $conversations_table = $wpdb->prefix . 'spp_conversations';
            $messages_table      = $wpdb->prefix . 'spp_messages';

            $conversations = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$conversations_table}
                WHERE (sender_id = %d OR FIND_IN_SET(%d, participant_ids))
                AND is_archived = 0
                ORDER BY updated_at DESC
                LIMIT %d",
                $user_id,
                $user_id,
                $limit
            ), ARRAY_A) ?: [];

            foreach ($conversations as &$conv) {
                $conv['unread_count'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$messages_table} WHERE conversation_id = %d AND recipient_id = %d AND is_read = 0 AND is_deleted = 0",
                    (int) $conv['id'],
                    $user_id
                ));
                $last = $wpdb->get_row($wpdb->prepare(
                    "SELECT message, sent_at, sender_id FROM {$messages_table} WHERE conversation_id = %d AND is_deleted = 0 ORDER BY sent_at DESC LIMIT 1",
                    (int) $conv['id']
                ), ARRAY_A);
                $conv['last_message'] = $last ? wp_trim_words(wp_strip_all_tags($last['message']), 10) : '';
            }
            unset($conv);

            return $conversations;
        }

        /**
         * Fetch messages in a conversation and mark incoming messages as read.
         */
        public function get_conversation_messages(int $conversation_id, int $user_id): array {
            global $wpdb;

            if (!$this->can_user_access_conversation($user_id, $conversation_id)) {
                return [];
            }

            $messages_table = $wpdb->prefix . 'spp_messages';

            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT m.*, u.display_name as sender_name
                FROM {$messages_table} m
                LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
                WHERE m.conversation_id = %d
                AND (m.sender_id = %d OR m.recipient_id = %d)
                AND m.is_deleted = 0
                ORDER BY m.sent_at ASC",
                $conversation_id,
                $user_id,
                $user_id
            ), ARRAY_A) ?: [];

            $wpdb->update(
                $messages_table,
                ['is_read' => 1],
                [
                    'conversation_id' => $conversation_id,
                    'recipient_id'    => $user_id,
                    'is_read'         => 0,
                ]
            );

            return $messages;
        }

        public function can_user_access_conversation(int $user_id, int $conversation_id): bool {
            global $wpdb;

            if (!$user_id || !$conversation_id) {
                return false;
            }

            $conversations_table = $wpdb->prefix . 'spp_conversations';

            return (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$conversations_table} WHERE id = %d AND (sender_id = %d OR FIND_IN_SET(%d, participant_ids))",
                $conversation_id,
                $user_id,
                $user_id
            ));
        }

        /**
         * Get total unread messages count for a user.
         */
        public function get_unread_count(int $user_id): int {
            global $wpdb;
            $table = $wpdb->prefix . 'spp_messages';

            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE recipient_id = %d AND is_read = 0 AND is_deleted = 0",
                $user_id
            ));
        }

        /**
         * Get unread in-app notifications for a user.
         */
        public function get_unread_notifications(int $user_id, int $limit = 10): array {
            global $wpdb;
            $table = $wpdb->prefix . 'spp_notifications';

            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND is_read = 0 ORDER BY created_at DESC LIMIT %d",
                $user_id,
                $limit
            ), ARRAY_A) ?: [];
        }

        /**
         * Mark all notifications as read for a user.
         */
        public function mark_notifications_read(int $user_id): void {
            global $wpdb;
            $table = $wpdb->prefix . 'spp_notifications';
            $wpdb->update($table, ['is_read' => 1], ['user_id' => $user_id, 'is_read' => 0]);
        }

        /**
         * AJAX: Send new message or reply.
         */
        public function ajax_send_message(): void {
            check_ajax_referer('spp_messaging_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 401);
            }

            $sender_id       = get_current_user_id();
            $conversation_id = absint($_POST['conversation_id'] ?? 0);
            $recipient_id    = absint($_POST['recipient_id'] ?? 0);
            $subject         = sanitize_text_field(wp_unslash($_POST['subject'] ?? __('New Message', 'sports-player-platform')));
            $message         = trim((string) wp_unslash($_POST['message'] ?? ''));

            if ($message === '') {
                wp_send_json_error(['message' => __('Message cannot be empty.', 'sports-player-platform')], 400);
            }

            if ($conversation_id > 0) {
                $res = $this->reply_to_conversation($conversation_id, $sender_id, $message);
                if (is_wp_error($res)) {
                    wp_send_json_error(['message' => $res->get_error_message()], 403);
                }
                wp_send_json_success([
                    'conversation_id' => $conversation_id,
                    'messages'        => $this->get_conversation_messages($conversation_id, $sender_id),
                ]);
            }

            $can_send = $this->can_send_message($sender_id, true);
            if (is_wp_error($can_send)) {
                wp_send_json_error(['message' => $can_send->get_error_message()], 403);
            }

            if (!$recipient_id || !get_userdata($recipient_id)) {
                wp_send_json_error(['message' => __('Please select a valid recipient.', 'sports-player-platform')], 400);
            }

            $new_conv_id = $this->send_message($sender_id, [$recipient_id], $subject, $message);
            if (!$new_conv_id) {
                wp_send_json_error(['message' => __('Failed to create conversation.', 'sports-player-platform')], 500);
            }

            wp_send_json_success([
                'conversation_id' => $new_conv_id,
                'messages'        => $this->get_conversation_messages($new_conv_id, $sender_id),
            ]);
        }

        /**
         * AJAX: Poll latest messages for active thread and unread counts.
         */
        public function ajax_fetch_messages(): void {
            check_ajax_referer('spp_messaging_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 401);
            }

            $user_id         = get_current_user_id();
            $conversation_id = absint($_GET['conversation_id'] ?? 0);
            $messages        = [];

            if ($conversation_id > 0 && $this->can_user_access_conversation($user_id, $conversation_id)) {
                $messages = $this->get_conversation_messages($conversation_id, $user_id);
            }

            wp_send_json_success([
                'messages'     => $messages,
                'unread_total' => $this->get_unread_count($user_id),
            ]);
        }

        /**
         * AJAX: Mark notifications read.
         */
        public function ajax_mark_notifications_read(): void {
            check_ajax_referer('spp_messaging_nonce', 'nonce');
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 401);
            }
            $this->mark_notifications_read(get_current_user_id());
            wp_send_json_success();
        }

        public function rest_get_conversations(): WP_REST_Response {
            return new WP_REST_Response($this->get_user_conversations(get_current_user_id()), 200);
        }

        public function rest_get_thread(WP_REST_Request $request): WP_REST_Response {
            $conv_id = absint($request['id']);
            $user_id = get_current_user_id();
            if (!$this->can_user_access_conversation($user_id, $conv_id)) {
                return new WP_REST_Response(['error' => 'Forbidden'], 403);
            }
            return new WP_REST_Response($this->get_conversation_messages($conv_id, $user_id), 200);
        }

        public function rest_send_message(WP_REST_Request $request): WP_REST_Response {
            $sender_id       = get_current_user_id();
            $conversation_id = absint($request->get_param('conversation_id') ?? 0);
            $recipient_id    = absint($request->get_param('recipient_id') ?? 0);
            $subject         = sanitize_text_field((string) ($request->get_param('subject') ?? __('New Message', 'sports-player-platform')));
            $message         = trim((string) ($request->get_param('message') ?? ''));

            if ($message === '') {
                return new WP_REST_Response(['error' => 'Message is required'], 400);
            }

            if ($conversation_id > 0) {
                $res = $this->reply_to_conversation($conversation_id, $sender_id, $message);
                if (is_wp_error($res)) {
                    return new WP_REST_Response(['error' => $res->get_error_message()], 403);
                }
                return new WP_REST_Response(['conversation_id' => $conversation_id, 'message_id' => $res], 201);
            }

            $can_send = $this->can_send_message($sender_id, true);
            if (is_wp_error($can_send)) {
                return new WP_REST_Response(['error' => $can_send->get_error_message()], 403);
            }

            if (!$recipient_id) {
                return new WP_REST_Response(['error' => 'Recipient is required'], 400);
            }

            $new_id = $this->send_message($sender_id, [$recipient_id], $subject, $message);
            return new WP_REST_Response(['conversation_id' => $new_id], 201);
        }

        public function rest_get_notifications(): WP_REST_Response {
            return new WP_REST_Response($this->get_unread_notifications(get_current_user_id()), 200);
        }

        public function rest_mark_notifications_read(): WP_REST_Response {
            $this->mark_notifications_read(get_current_user_id());
            return new WP_REST_Response(['success' => true], 200);
        }

        /**
         * Render [spp_messages] split-pane UI.
         */
        public function render_messages_page(): string {
            if (!is_user_logged_in()) {
                return '<div class="spp-notice spp-notice-error"><p>' . esc_html__('Please log in to view messages.', 'sports-player-platform') . '</p></div>';
            }

            $user_id = get_current_user_id();
            $user    = wp_get_current_user();

            if (!current_user_can('manage_options') && in_array('spp_parent', $user->roles, true)) {
                return '<div class="spp-dashboard-wrap"><div class="spp-notice spp-notice-info"><p>' .
                    esc_html__('Direct messaging is not available for Parent accounts. Please register a Coach or Player account.', 'sports-player-platform') .
                    '</p></div></div>';
            }

            if (!current_user_can('manage_options') && (!function_exists('spp_user_has_membership') || !spp_user_has_membership($user_id, 'paid'))) {
                return '<div class="spp-dashboard-wrap"><div class="spp-notice spp-notice-info"><p>' .
                    esc_html__('Upgrade to a Monthly or Annual plan to unlock direct messaging.', 'sports-player-platform') .
                    ' <a href="' . esc_url(home_url('/pricing/')) . '" class="spp-btn spp-btn-primary">' . esc_html__('View Plans', 'sports-player-platform') . '</a></p></div></div>';
            }

            $conversations   = $this->get_user_conversations($user_id);
            $active_conv_id  = absint($_GET['conversation'] ?? ($conversations[0]['id'] ?? 0));
            $active_messages = $active_conv_id ? $this->get_conversation_messages($active_conv_id, $user_id) : [];

            $recipients = [];
            if (current_user_can('create_recruiting_list') || current_user_can('manage_options')) {
                $recipients = get_users(['role' => 'spp_player', 'number' => 100, 'fields' => ['ID', 'display_name']]);
            }

            $template_path = SPP_PLUGIN_DIR . 'templates/dashboard/messages.php';
            if (!file_exists($template_path)) {
                return '';
            }

            $active_tab = 'messages';
            ob_start();
            include $template_path;
            return (string) ob_get_clean();
        }

        /**
         * Render [spp_message_form recipient_id="..."] shortcode.
         */
        public function render_message_form(array $atts = []): string {
            $atts = shortcode_atts(['recipient_id' => 0], $atts);
            $recipient_id = absint($atts['recipient_id']);

            if (!is_user_logged_in() || !$recipient_id) {
                return '';
            }

            $can_send = $this->can_send_message(get_current_user_id(), true);
            if (is_wp_error($can_send)) {
                return '<p class="spp-notice spp-notice-info">' . esc_html($can_send->get_error_message()) . '</p>';
            }

            ob_start();
            ?>
            <form class="spp-inline-message-form spp-form" data-recipient-id="<?php echo esc_attr((string) $recipient_id); ?>">
                <p class="spp-form-field">
                    <label><?php esc_html_e('Subject', 'sports-player-platform'); ?></label>
                    <input type="text" name="subject" required placeholder="<?php esc_attr_e('Recruiting Inquiry', 'sports-player-platform'); ?>">
                </p>
                <p class="spp-form-field">
                    <label><?php esc_html_e('Message', 'sports-player-platform'); ?></label>
                    <textarea name="message" rows="3" required></textarea>
                </p>
                <button type="submit" class="spp-btn spp-btn-primary"><?php esc_html_e('Send Message', 'sports-player-platform'); ?></button>
                <div class="spp-inline-msg-feedback"></div>
            </form>
            <?php
            return (string) ob_get_clean();
        }

        /**
         * Create in-app notification and optionally send email notification.
         */
        private function notify_recipient(int $sender_id, int $recipient_id, int $conversation_id, string $subject): void {
            global $wpdb;

            $sender      = get_userdata($sender_id);
            $sender_name = $sender ? $sender->display_name : __('Someone', 'sports-player-platform');

            $wpdb->insert($wpdb->prefix . 'spp_notifications', [
                'user_id' => $recipient_id,
                'type'    => 'message',
                'title'   => sprintf(__('New message from %s', 'sports-player-platform'), $sender_name),
                'message' => $subject,
                'link'    => add_query_arg('conversation', $conversation_id, home_url('/dashboard/messages/')),
            ]);

            $opt_in = get_user_meta($recipient_id, 'spp_email_message_notifications', true);
            if ($opt_in === '1') {
                $recipient = get_userdata($recipient_id);
                if ($recipient && is_email($recipient->user_email)) {
                    $message_url = add_query_arg('conversation', $conversation_id, home_url('/dashboard/messages/'));
                    $email_subject = sprintf(__('New message from %s - Sports Player Platform', 'sports-player-platform'), $sender_name);
                    if (class_exists('SPP_Email')) {
                        SPP_Email::send($recipient->user_email, 'new-message', [
                            'subject' => $email_subject,
                            'name' => $recipient->display_name,
                            'sender_name' => $sender_name,
                            'message_subject' => $subject,
                            'url' => $message_url,
                        ]);
                    } else {
                        wp_mail(
                            $recipient->user_email,
                            $email_subject,
                            sprintf(
                                __("Hello %s,\n\nYou received a new message regarding \"%s\".\n\nView it here: %s", 'sports-player-platform'),
                                $recipient->display_name,
                                $subject,
                                $message_url
                            )
                        );
                    }
                }
            }

            // Push real-time WebSocket event (non-blocking; falls back silently if WS disabled).
            $this->push_realtime_event([
                'event'    => 'notification',
                'user_ids' => [$recipient_id],
                'payload'  => [
                    'conversation_id' => $conversation_id,
                    'sender_name'     => $sender_name,
                    'subject'         => $subject,
                ],
            ]);
        }

        /* -----------------------------------------------------------------------
         * WebSocket Support — HMAC token generation + realtime event push
         * ----------------------------------------------------------------------- */

        /**
         * Read and normalise the WebSocket settings from spp_settings option.
         *
         * @return array{enabled:bool, url:string, secret:string}
         */
        public function get_ws_settings(): array {
            $settings = get_option('spp_settings', []);
            $enabled  = !empty($settings['spp_ws_enabled'])
                && !empty($settings['spp_ws_url'])
                && !empty($settings['spp_ws_secret']);

            return [
                'enabled' => (bool) $enabled,
                'url'     => isset($settings['spp_ws_url']) ? esc_url_raw($settings['spp_ws_url']) : '',
                'secret'  => isset($settings['spp_ws_secret']) ? (string) $settings['spp_ws_secret'] : '',
            ];
        }

        private function base64url_encode(string $data): string {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }

        private function base64url_decode(string $data): string {
            $pad = strlen($data) % 4;
            if ($pad) {
                $data .= str_repeat('=', 4 - $pad);
            }
            return (string) base64_decode(strtr($data, '-_', '+/'), true);
        }

        /**
         * Build a signed, short-lived token for the given user.
         * Format: base64url(json({uid,exp})) . '.' . base64url(hmac(payload))
         */
        public function generate_ws_token(int $user_id): string {
            $settings = $this->get_ws_settings();
            if (empty($settings['secret']) || $user_id <= 0) {
                return '';
            }

            $payload = $this->base64url_encode(wp_json_encode([
                'uid' => (int) $user_id,
                'exp' => time() + self::WS_TOKEN_TTL,
            ]));

            $sig = $this->base64url_encode(
                hash_hmac(self::WS_TOKEN_ALGO, $payload, $settings['secret'], true)
            );

            return $payload . '.' . $sig;
        }

        /**
         * Verify a WS token and return the user id, or null on failure/expiry.
         */
        public function verify_ws_token(string $token): ?int {
            $settings = $this->get_ws_settings();
            if (empty($settings['secret']) || !is_string($token) || strpos($token, '.') === false) {
                return null;
            }

            $parts = explode('.', $token, 2);
            if (count($parts) !== 2) {
                return null;
            }
            [$payload, $sig] = $parts;

            $expected = $this->base64url_encode(
                hash_hmac(self::WS_TOKEN_ALGO, $payload, $settings['secret'], true)
            );
            if (!hash_equals($expected, $sig)) {
                return null;
            }

            $data = json_decode($this->base64url_decode($payload), true);
            if (!is_array($data) || empty($data['uid']) || empty($data['exp'])) {
                return null;
            }
            if (time() > (int) $data['exp']) {
                return null;
            }

            return (int) $data['uid'];
        }

        /**
         * Derive the HTTP publish endpoint URL from the WebSocket URL.
         */
        private function get_publish_url(string $ws_url): string {
            if ('' === $ws_url) {
                return '';
            }
            $http_url = preg_replace('#^wss://#i', 'https://', $ws_url);
            $http_url = preg_replace('#^ws://#i', 'http://', $http_url);
            return rtrim((string) $http_url, '/') . '/publish';
        }

        /**
         * Push a realtime event to the WebSocket relay server.
         * Non-blocking (blocking=false) so it never delays the current request.
         *
         * @param array{event:string, user_ids:int[], payload:array} $event
         */
        public function push_realtime_event(array $event): bool {
            $settings = $this->get_ws_settings();
            if (!$settings['enabled']) {
                return false;
            }

            $publish_url = $this->get_publish_url($settings['url']);
            if ('' === $publish_url) {
                return false;
            }

            $body = wp_json_encode($event);
            if (false === $body) {
                return false;
            }

            $signature = $this->base64url_encode(
                hash_hmac(self::WS_TOKEN_ALGO, $body, $settings['secret'], true)
            );

            $response = wp_remote_post($publish_url, [
                'method'   => 'POST',
                'timeout'  => 5,
                'blocking' => false,
                'headers'  => [
                    'Content-Type'    => 'application/json',
                    'X-SPP-Signature' => $signature,
                ],
                'body'     => $body,
            ]);

            return !is_wp_error($response);
        }

        /**
         * AJAX: Issue a fresh short-lived WS auth token for the current user.
         */
        public function ajax_ws_token(): void {
            check_ajax_referer('spp_ws_token_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 401);
            }

            $token = $this->generate_ws_token(get_current_user_id());
            if ('' === $token) {
                wp_send_json_error(['message' => __('WebSocket not configured.', 'sports-player-platform')], 503);
            }

            wp_send_json_success(['token' => $token]);
        }
    }
}
