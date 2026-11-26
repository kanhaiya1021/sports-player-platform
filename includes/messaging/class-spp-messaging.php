<?php
/**
 * Messaging System Class
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Messaging')) {

    final class SPP_Messaging {

        private static $instance = null;

        const STATUS_SENT = 'sent';
        const STATUS_READ = 'read';

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
            add_action('init', [$this, 'create_messaging_tables']);

            add_shortcode('spp_messages', [$this, 'render_messages_page']);
            add_shortcode('spp_message_form', [$this, 'render_message_form']);
        }

        public function create_messaging_tables(): void {
            global $wpdb;

            $charset_collate = $wpdb->get_charset_collate();

            // Conversations table
            $table_name = $wpdb->prefix . 'spp_conversations';
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                subject VARCHAR(255) NOT NULL,
                sender_id BIGINT(20) UNSIGNED NOT NULL,
                participant_ids TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_archived TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY sender_id_idx (sender_id),
                KEY updated_at_idx (updated_at)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);

            // Messages table
            $messages_table = $wpdb->prefix . 'spp_messages';
            $sql = "CREATE TABLE IF NOT EXISTS $messages_table (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id BIGINT(20) UNSIGNED NOT NULL,
                sender_id BIGINT(20) UNSIGNED NOT NULL,
                recipient_id BIGINT(20) UNSIGNED NOT NULL,
                message LONGTEXT NOT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                is_deleted TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY conversation_id_idx (conversation_id),
                KEY recipient_id_idx (recipient_id),
                KEY is_read_idx (is_read)
            ) $charset_collate;";

            dbDelta($sql);
        }

        public function send_message(int $sender_id, array $recipient_ids, string $subject, string $message): int {
            global $wpdb;

            $conversations_table = $wpdb->prefix . 'spp_conversations';
            $messages_table = $wpdb->prefix . 'spp_messages';

            // Create conversation
            $participant_str = implode(',', $recipient_ids);
            $wpdb->insert(
                $conversations_table,
                [
                    'subject' => $subject,
                    'sender_id' => $sender_id,
                    'participant_ids' => $participant_str,
                ]
            );

            $conversation_id = $wpdb->insert_id;

            // Insert message for each recipient
            foreach ($recipient_ids as $recipient_id) {
                $wpdb->insert(
                    $messages_table,
                    [
                        'conversation_id' => $conversation_id,
                        'sender_id' => $sender_id,
                        'recipient_id' => $recipient_id,
                        'message' => wp_kses_post($message),
                    ]
                );
            }

            return $conversation_id;
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
            ), ARRAY_A);
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
            ), ARRAY_A);

            // Mark messages as read
            $wpdb->update(
                $messages_table,
                ['is_read' => 1],
                [
                    'conversation_id' => $conversation_id,
                    'recipient_id' => $user_id,
                    'is_read' => 0,
                ]
            );

            return $messages;
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
            return in_array($user_id, $participants) || $wpdb->get_var($wpdb->prepare(
                "SELECT sender_id FROM $conversations_table WHERE id = %d",
                $conversation_id
            )) === $user_id;
        }

        public function render_messages_page(): string {
            if (!is_user_logged_in()) {
                return '<p>' . __('You must be logged in to view messages.', 'sports-player-platform') . '</p>';
            }

            $user_id = get_current_user_id();
            $action = $_GET['spp_message_action'] ?? '';
            $conversation_id = absint($_GET['conversation'] ?? 0);

            if ($action === 'archive' && current_user_can('edit_posts')) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'spp_conversations',
                    ['is_archived' => 1],
                    ['id' => $conversation_id]
                );
                wp_safe_redirect(remove_query_arg(['spp_message_action', 'conversation']));
                exit;
            }

            ob_start();
            ?>
            <div class="spp-messages-page">
                <h1><?php esc_html_e('Messages', 'sports-player-platform'); ?></h1>

                <?php
                if ($conversation_id && $this->can_user_access_conversation($user_id, $conversation_id)) {
                    $messages = $this->get_conversation_messages($conversation_id, $user_id);
                    $conversation = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}spp_conversations WHERE id = %d",
                            $conversation_id
                        )
                    );
                    ?>
                    <div class="spp-conversation-view">
                        <h2><?php echo esc_html($conversation->subject ?? ''); ?></h2>
                        <a href="<?php echo esc_url(remove_query_arg('conversation')); ?>" class="button button-secondary"><?php esc_html_e('Back to Conversations', 'sports-player-platform'); ?></a>

                        <div class="spp-messages-list">
                            <?php if ($messages): ?>
                                <?php foreach ($messages as $msg): ?>
                                    <div class="spp-message <?php echo $msg['sender_id'] == $user_id ? 'spp-message-sent' : 'spp-message-received'; ?>">
                                        <strong><?php echo esc_html($msg['sender_name']); ?>:</strong>
                                        <p><?php echo wp_kses_post($msg['message']); ?></p>
                                        <small><?php echo esc_html(date('Y-m-d H:i:s', strtotime($msg['sent_at']))); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p><?php esc_html_e('No messages in this conversation.', 'sports-player-platform'); ?></p>
                            <?php endif; ?>
                        </div>

                        <?php echo $this->render_message_form($conversation_id); ?>
                    </div>
                <?php } else { ?>
                    <p><a href="<?php echo esc_url(add_query_arg(['action' => 'compose'], home_url())); ?>">
                        <?php esc_html_e('Compose New Message', 'sports-player-platform'); ?>
                    </a></p>

                    <?php
                    $conversations = $this->get_user_conversations($user_id);
                    ?>

                    <?php if ($conversations): ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Subject', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Last Updated', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Actions', 'sports-player-platform'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conversations as $conv): ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url(add_query_arg('conversation', $conv['id'])); ?>"><?php echo esc_html($conv['subject']); ?></a></td>
                                        <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($conv['updated_at']))); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url(add_query_arg('conversation', $conv['id'])); ?>" class="button button-small"><?php esc_html_e('View', 'sports-player-platform'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php esc_html_e('No conversations found.', 'sports-player-platform'); ?></p>
                    <?php endif; ?>
                <?php } ?>
            </div>
            <?php
                return ob_get_clean();
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
