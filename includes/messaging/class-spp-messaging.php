<?php
/**
 * Real-Time Messaging System Class
 *
 * Handles conversations, messages and in-app notifications, plus an optional
 * WebSocket layer for real-time delivery. When a WebSocket server is configured
 * (Settings -> Sports Player Platform), new messages and notifications are pushed
 * to it via a signed, non-blocking HTTP webhook. The browser connects directly to
 * the WebSocket server using a short-lived HMAC-SHA256 auth token and otherwise
 * falls back to the existing 5-second AJAX polling endpoint.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Messaging')) {

    final class SPP_Messaging {

        private static $instance = null;

        const STATUS_SENT = 'sent';
        const STATUS_READ  = 'read';

        // Seconds a WebSocket auth token remains valid.
        const WS_TOKEN_TTL  = 60;
        const WS_TOKEN_ALGO = 'sha256';

        const NONCE_MESSAGING = 'spp_messaging_nonce';
        const NONCE_WS_TOKEN  = 'spp_ws_token_nonce';

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
            add_shortcode('spp_dashboard_messages', [$this, 'render_messages_page']);
            add_shortcode('spp_message_form', [$this, 'render_message_form']);

            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

            // Authenticated AJAX endpoints.
            add_action('wp_ajax_spp_fetch_messages', [$this, 'ajax_fetch_messages']);
            add_action('wp_ajax_spp_send_reply', [$this, 'ajax_send_reply']);
            add_action('wp_ajax_spp_ws_token', [$this, 'ajax_ws_token']);
        }

        /**
         * Make sure the messaging tables exist (delegates to the DB layer).
         */
        public function create_messaging_tables(): void {
            if (class_exists('SPP_DB')) {
                SPP_DB::get_instance()->create_tables();
            }
        }

        /* ---------------------------------------------------------------------
         * Settings access
         * --------------------------------------------------------------------- */

        /**
         * Read and normalise the WebSocket settings.
         *
         * @return array{enabled:bool, url:string, secret:string}
         */
        public function get_ws_settings(): array {
            $settings = get_option('spp_settings', []);

            $enabled = !empty($settings['spp_ws_enabled'])
                && !empty($settings['spp_ws_url'])
                && !empty($settings['spp_ws_secret']);

            return [
                'enabled' => (bool) $enabled,
                'url'     => isset($settings['spp_ws_url']) ? esc_url_raw($settings['spp_ws_url']) : '',
                'secret'  => isset($settings['spp_ws_secret']) ? (string) $settings['spp_ws_secret'] : '',
            ];
        }

        /* ---------------------------------------------------------------------
         * Access control
         * --------------------------------------------------------------------- */

        /**
         * Whether the CURRENT logged-in user may use real-time messaging.
         * Requires an authenticated, paid account with messaging permission.
         */
        public function user_can_use_realtime(): bool {
            if (!is_user_logged_in()) {
                return false;
            }

            if (!current_user_can('send_messages') && !current_user_can('manage_options')) {
                return false;
            }

            if (function_exists('spp_user_has_membership') && !spp_user_has_membership(get_current_user_id(), 'paid')) {
                return false;
            }

            return true;
        }

        /* ---------------------------------------------------------------------
         * HMAC auth token (short-lived)
         * --------------------------------------------------------------------- */

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
         * Verify a token and return the user id, or null on failure/expiry.
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

        /* ---------------------------------------------------------------------
         * Realtime event publishing (non-blocking webhook to the WS server)
         * --------------------------------------------------------------------- */

        /**
         * Derive the HTTP publish endpoint from the WebSocket URL.
         */
        private function get_publish_url(string $ws_url): string {
            if ('' === $ws_url) {
                return '';
            }
            $http_url = preg_replace('#^wss://#i', 'https://', $ws_url);
            $http_url = preg_replace('#^ws://#i', 'http://', $http_url);
            return rtrim($http_url, '/') . '/publish';
        }

        /**
         * Push an event to the WebSocket server. Non-blocking so it never delays
         * the request that triggered it. Returns false when WS is disabled or the
         * publish endpoint is unreachable/unconfigured.
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

        /* ---------------------------------------------------------------------
         * Messaging data layer
         * --------------------------------------------------------------------- */

        private function insert_message(int $conversation_id, int $sender_id, int $recipient_id, string $message): int {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'spp_messages',
                [
                    'conversation_id' => $conversation_id,
                    'sender_id'       => $sender_id,
                    'recipient_id'    => $recipient_id,
                    'message'         => wp_kses_post($message),
                ]
            );

            return (int) $wpdb->insert_id;
        }

        /**
         * Start a new conversation and notify each recipient in real time.
         */
        public function send_message(int $sender_id, array $recipient_ids, string $subject, string $message): int {
            global $wpdb;

            $recipient_ids   = array_filter(array_map('intval', $recipient_ids));
            $conversations_t = $wpdb->prefix . 'spp_conversations';

            $wpdb->insert(
                $conversations_t,
                [
                    'subject'        => $subject,
                    'sender_id'      => $sender_id,
                    'participant_ids'=> implode(',', $recipient_ids),
                ]
            );
            $conversation_id = (int) $wpdb->insert_id;

            $inserted_ids = [];
            foreach ($recipient_ids as $recipient_id) {
                $inserted_ids[] = $this->insert_message($conversation_id, $sender_id, $recipient_id, $message);
            }

            $this->notify_recipients($conversation_id, $sender_id, $recipient_ids, $subject, $message, $inserted_ids);

            return $conversation_id;
        }

        /**
         * Reply within an existing conversation. Only participants may reply.
         */
        public function reply_to_conversation(int $conversation_id, int $sender_id, string $message): int {
            global $wpdb;

            if (!$this->can_user_access_conversation($sender_id, $conversation_id)) {
                return 0;
            }

            $conversations_t = $wpdb->prefix . 'spp_conversations';
            $row             = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $conversations_t WHERE id = %d",
                $conversation_id
            ));
            if (!$row) {
                return 0;
            }

            $participants = array_filter(array_map('intval', explode(',', (string) $row->participant_ids)));
            $recipients   = array_values(array_diff($participants, [$sender_id]));

            $inserted_ids = [];
            foreach ($recipients as $recipient_id) {
                $inserted_ids[] = $this->insert_message($conversation_id, $sender_id, (int) $recipient_id, $message);
            }

            $wpdb->update(
                $conversations_t,
                ['updated_at' => current_time('mysql')],
                ['id' => $conversation_id]
            );

            $this->notify_recipients($conversation_id, $sender_id, $recipients, $row->subject ?? '', $message, $inserted_ids);

            return !empty($inserted_ids) ? (int) end($inserted_ids) : 0;
        }

        /**
         * Insert notifications and push realtime message + notification events.
         */
        private function notify_recipients(int $conversation_id, int $sender_id, array $recipient_ids, string $subject, string $message, array $message_ids): void {
            $recipient_ids = array_filter(array_map('intval', $recipient_ids));
            if (empty($recipient_ids)) {
                return;
            }

            $sender_name = $this->get_display_name($sender_id);
            $snippet     = wp_strip_all_tags($message);
            $snippet     = mb_substr($snippet, 0, 120);
            $link        = home_url('/dashboard/messages/?conversation=' . $conversation_id);

            foreach ($recipient_ids as $recipient_id) {
                $this->create_notification(
                    $recipient_id,
                    'message',
                    sprintf(__('New message from %s', 'sports-player-platform'), $sender_name),
                    $snippet,
                    $link
                );
            }

            $user_ids = array_unique(array_map('intval', array_merge([$sender_id], $recipient_ids)));

            // Message event (wakes the client; the client re-fetches via AJAX).
            $this->push_realtime_event([
                'event'    => 'message',
                'user_ids' => $user_ids,
                'payload'  => [
                    'conversation_id' => $conversation_id,
                    'sender_id'       => $sender_id,
                    'sender_name'     => $sender_name,
                    'subject'         => $subject,
                    'message_ids'     => $message_ids,
                    'sent_at'         => current_time('mysql'),
                ],
            ]);

            // Notification event (drives the unread badge / toast).
            $this->push_realtime_event([
                'event'    => 'notification',
                'user_ids' => array_values($recipient_ids),
                'payload'  => [
                    'conversation_id' => $conversation_id,
                    'title'           => sprintf(__('New message from %s', 'sports-player-platform'), $sender_name),
                    'message'         => $snippet,
                    'link'            => $link,
                ],
            ]);
        }

        public function create_notification(int $user_id, string $type, string $title, string $message, string $link = ''): int {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'spp_notifications',
                [
                    'user_id' => $user_id,
                    'type'    => $type,
                    'title'   => $title,
                    'message' => $message,
                    'link'    => $link,
                ]
            );

            return (int) $wpdb->insert_id;
        }

        private function get_display_name(int $user_id): string {
            $user = get_userdata($user_id);
            return $user ? (string) $user->display_name : __('Someone', 'sports-player-platform');
        }

        public function get_user_conversations(int $user_id, int $limit = 50): array {
            global $wpdb;

            $conversations_table = $wpdb->prefix . 'spp_conversations';

            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $conversations_table
                WHERE FIND_IN_SET(%d, participant_ids)
                AND is_archived = 0
                ORDER BY updated_at DESC
                LIMIT %d",
                $user_id,
                $limit
            ), ARRAY_A) ?: [];
        }

        public function get_conversation_messages(int $conversation_id, int $user_id): array {
            global $wpdb;

            $messages_table = $wpdb->prefix . 'spp_messages';

            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT m.*, u.display_name as sender_name
                FROM $messages_table m
                LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
                WHERE m.conversation_id = %d
                AND m.is_deleted = 0
                ORDER BY m.sent_at ASC",
                $conversation_id
            ), ARRAY_A) ?: [];

            // Mark messages as read for this recipient.
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

        /**
         * Fetch messages newer than $since for a conversation and mark them read.
         * Used by the AJAX poll fallback and as a refresh after a WS event.
         */
        public function get_new_messages(int $conversation_id, int $user_id, string $since): array {
            global $wpdb;

            if (!$this->can_user_access_conversation($user_id, $conversation_id)) {
                return [];
            }

            $messages_table = $wpdb->prefix . 'spp_messages';

            // Mark everything up to now read for this recipient.
            $wpdb->update(
                $messages_table,
                ['is_read' => 1],
                [
                    'conversation_id' => $conversation_id,
                    'recipient_id'    => $user_id,
                    'is_read'         => 0,
                ]
            );

            $args = [$conversation_id];
            $since_sql = '';
            if ('' !== $since && strtotime($since) !== false) {
                $since_sql = 'AND m.sent_at > %s';
                $args[]    = $since;
            }

            return $wpdb->get_results($wpdb->prepare(
                "SELECT m.*, u.display_name as sender_name
                FROM $messages_table m
                LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
                WHERE m.conversation_id = %d $since_sql
                AND m.is_deleted = 0
                ORDER BY m.sent_at ASC",
                ...$args
            ), ARRAY_A) ?: [];
        }

        public function can_user_access_conversation(int $user_id, int $conversation_id): bool {
            global $wpdb;

            $conversations_table = $wpdb->prefix . 'spp_conversations';

            $participant_ids = $wpdb->get_var($wpdb->prepare(
                "SELECT participant_ids FROM $conversations_table WHERE id = %d",
                $conversation_id
            ));

            if (!$participant_ids) {
                return false;
            }

            $participants = explode(',', $participant_ids);
            return in_array($user_id, $participants, true)
                || $wpdb->get_var($wpdb->prepare(
                    "SELECT sender_id FROM $conversations_table WHERE id = %d",
                    $conversation_id
                )) === $user_id;
        }

        /**
         * Unread message count for a user (drives the nav badge).
         */
        public function get_unread_count(int $user_id): int {
            global $wpdb;

            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}spp_messages
                WHERE recipient_id = %d AND is_read = 0 AND is_deleted = 0",
                $user_id
            ));
        }

        /**
         * Unread message count for a single conversation.
         */
        public function get_conversation_unread_count(int $user_id, int $conversation_id): int {
            global $wpdb;

            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}spp_messages
                WHERE conversation_id = %d AND recipient_id = %d AND is_read = 0 AND is_deleted = 0",
                $conversation_id,
                $user_id
            ));
        }

        /* ---------------------------------------------------------------------
         * AJAX endpoints
         * --------------------------------------------------------------------- */

        /**
         * Poll for new messages since $since and return the global unread count.
         */
        public function ajax_fetch_messages(): void {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 403);
            }
            check_ajax_referer(self::NONCE_MESSAGING, 'nonce');

            $user_id         = get_current_user_id();
            $conversation_id = isset($_POST['conversation_id']) ? absint($_POST['conversation_id']) : 0;
            $since           = isset($_POST['since']) ? sanitize_text_field(wp_unslash($_POST['since'])) : '';

            $messages = [];
            if ($conversation_id > 0) {
                $messages = $this->get_new_messages($conversation_id, $user_id, $since);
            }

            wp_send_json_success([
                'messages' => $messages,
                'unread'   => $this->get_unread_count($user_id),
            ]);
        }

        /**
         * Reply to the current conversation via AJAX and trigger realtime push.
         */
        public function ajax_send_reply(): void {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 403);
            }
            check_ajax_referer(self::NONCE_MESSAGING, 'nonce');

            if (!current_user_can('send_messages') && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('You do not have permission to send messages.', 'sports-player-platform')], 403);
            }

            $conversation_id = isset($_POST['conversation_id']) ? absint($_POST['conversation_id']) : 0;
            $message         = isset($_POST['message']) ? wp_kses_post(wp_unslash($_POST['message'])) : '';

            if ($conversation_id <= 0 || '' === $message) {
                wp_send_json_error(['message' => __('Invalid request.', 'sports-player-platform')], 400);
            }

            $message_id = $this->reply_to_conversation($conversation_id, get_current_user_id(), $message);
            if (!$message_id) {
                wp_send_json_error(['message' => __('Unable to send message.', 'sports-player-platform')], 400);
            }

            wp_send_json_success([
                'message_id'      => $message_id,
                'conversation_id' => $conversation_id,
                'sender_id'       => get_current_user_id(),
                'sender_name'     => wp_get_current_user()->display_name,
                'message'         => $message,
                'sent_at'         => current_time('mysql'),
            ]);
        }

        /**
         * Issue a fresh short-lived WebSocket auth token.
         */
        public function ajax_ws_token(): void {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 403);
            }
            check_ajax_referer(self::NONCE_WS_TOKEN, 'nonce');

            if (!$this->user_can_use_realtime()) {
                wp_send_json_error(['message' => __('Real-time messaging is not available for your account.', 'sports-player-platform')], 403);
            }

            wp_send_json_success([
                'token'      => $this->generate_ws_token(get_current_user_id()),
                'expires_in' => self::WS_TOKEN_TTL,
            ]);
        }

        /* ---------------------------------------------------------------------
         * Front-end assets & rendering
         * --------------------------------------------------------------------- */

        private function is_dashboard_request(): bool {
            if (is_admin()) {
                return false;
            }
            $request_path = trim((string) parse_url(add_query_arg([]), PHP_URL_PATH), '/');
            return ($request_path === 'dashboard' || strpos($request_path, 'dashboard/') === 0);
        }

        /**
         * Localization object consumed by assets/js/messaging.js.
         */
        public function get_realtime_config(): array {
            $user_id = get_current_user_id();
            $ws      = $this->get_ws_settings();

            $config = [
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce(self::NONCE_MESSAGING),
                'wsTokenNonce' => wp_create_nonce(self::NONCE_WS_TOKEN),
                'userId'       => $user_id,
                'wsEnabled'    => false,
                'wsUrl'        => '',
                'wsToken'      => '',
                'pollInterval' => 5,
                'i18n'         => [
                    'connecting' => __('Connecting real-time messaging…', 'sports-player-platform'),
                    'offline'    => __('Real-time disconnected. Using periodic refresh.', 'sports-player-platform'),
                    'sendError'  => __('Could not send message. Please try again.', 'sports-player-platform'),
                ],
            ];

            if ($ws['enabled'] && $this->user_can_use_realtime()) {
                $config['wsEnabled'] = true;
                $config['wsUrl']     = $ws['url'];
                $config['wsToken']   = $this->generate_ws_token($user_id);
            }

            return $config;
        }

        public function enqueue_assets(): void {
            if (!is_user_logged_in()) {
                return;
            }

            $is_dashboard = $this->is_dashboard_request();
            $post         = get_post();
            $has_shortcode = $post instanceof WP_Post
                && (has_shortcode($post->post_content, 'spp_messages')
                    || has_shortcode($post->post_content, 'spp_dashboard_messages'));

            if (!$is_dashboard && !$has_shortcode) {
                return;
            }

            wp_enqueue_style(
                'spp-messaging',
                SPP_PLUGIN_URL . 'assets/css/messaging.css',
                [],
                SPP_VERSION
            );

            wp_enqueue_script(
                'spp-messaging',
                SPP_PLUGIN_URL . 'assets/js/messaging.js',
                [],
                SPP_VERSION,
                true
            );

            wp_localize_script('spp-messaging', 'sppMessaging', $this->get_realtime_config());
        }

        public function render_messages_page(): string {
            if (!is_user_logged_in()) {
                return '<p>' . __('You must be logged in to view messages.', 'sports-player-platform') . '</p>';
            }

            $user_id         = get_current_user_id();
            $action          = isset($_GET['spp_message_action']) ? sanitize_text_field(wp_unslash($_GET['spp_message_action'])) : '';
            $conversation_id = isset($_GET['conversation']) ? absint($_GET['conversation']) : 0;

            if ($action === 'archive' && current_user_can('manage_options')) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'spp_conversations',
                    ['is_archived' => 1],
                    ['id' => $conversation_id]
                );
                wp_safe_redirect(remove_query_arg(['spp_message_action', 'conversation']));
                exit;
            }

            $conversations = $this->get_user_conversations($user_id);
            foreach ($conversations as &$conv) {
                $conv['unread'] = $this->get_conversation_unread_count($user_id, (int) $conv['id']);
            }
            unset($conv);

            $messages     = [];
            $conversation = null;
            if ($conversation_id && $this->can_user_access_conversation($user_id, $conversation_id)) {
                $messages = $this->get_conversation_messages($conversation_id, $user_id);
                global $wpdb;
                $conversation = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}spp_conversations WHERE id = %d",
                    $conversation_id
                ));
            }

            $vars = [
                'active_tab'       => 'messages',
                'user_id'          => $user_id,
                'conversation_id'  => $conversation_id,
                'conversations'    => $conversations,
                'messages'         => $messages,
                'conversation'     => $conversation,
                'can_archive'      => current_user_can('manage_options'),
                'unread'           => $this->get_unread_count($user_id),
                'messaging_nonce'  => wp_create_nonce(self::NONCE_MESSAGING),
            ];

            ob_start();
            $this->load_template('dashboard/messages.php', $vars);
            return (string) ob_get_clean();
        }

        private function load_template(string $relative_path, array $vars = []): void {
            $template_path = SPP_PLUGIN_DIR . 'templates/' . ltrim($relative_path, '/');
            if (!file_exists($template_path)) {
                return;
            }

            extract($vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            include $template_path;
        }

        public function render_message_form(int $conversation_id = 0): string {
            if (!is_user_logged_in()) {
                return '';
            }

            ob_start();
            ?>
            <form method="post" action="">
                <?php wp_nonce_field('spp_message_nonce', 'spp_nonce'); ?>
                <input type="hidden" name="spp_message_action" value="send">
                <?php if ($conversation_id): ?>
                    <input type="hidden" name="conversation_id" value="<?php echo esc_attr($conversation_id); ?>">
                <?php endif; ?>

                <textarea name="message" id="message" rows="4" class="large-text" required></textarea>
                <p><button type="submit" class="button button-primary"><?php esc_html_e('Send Message', 'sports-player-platform'); ?></button></p>
            </form>
            <?php
            return ob_get_clean();
        }
    }
}
