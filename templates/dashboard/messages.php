<?php
/**
 * Dashboard Messages Template
 *
 * Renders the conversation list and the active thread. Real-time updates are
 * handled by assets/js/messaging.js (WebSocket with AJAX polling fallback).
 *
 * @var string $active_tab
 * @var int    $user_id
 * @var int    $conversation_id
 * @var array  $conversations
 * @var array  $messages
 * @var object $conversation
 * @var bool   $can_archive
 * @var int    $unread
 * @var string $messaging_nonce
 */

if (!defined('ABSPATH')) {
    exit;
}

$conversations = $conversations ?? [];
$messages      = $messages ?? [];
$conversation  = $conversation ?? null;
$can_archive   = $can_archive ?? false;
$unread        = (int) ($unread ?? 0);
?>
<div class="spp-dashboard-wrap spp-messages-wrap">
    <?php include SPP_PLUGIN_DIR . 'templates/dashboard/nav.php'; ?>

    <div class="spp-messages-layout">
        <aside class="spp-conversation-list" aria-label="<?php esc_attr_e('Conversations', 'sports-player-platform'); ?>">
            <h2><?php esc_html_e('Conversations', 'sports-player-platform'); ?></h2>
            <?php if ($conversations): ?>
                <ul>
                    <?php foreach ($conversations as $conv): ?>
                        <?php
                        $is_active  = ((int) ($conv['id'] ?? 0) === (int) $conversation_id);
                        $conv_unread = (int) ($conv['unread'] ?? 0);
                        ?>
                        <li>
                            <a href="<?php echo esc_url(add_query_arg('conversation', $conv['id'])); ?>"
                               class="spp-conversation-item <?php echo $is_active ? 'is-active' : ''; ?>">
                                <span class="spp-conversation-subject"><?php echo esc_html($conv['subject'] ?? ''); ?></span>
                                <span class="spp-conversation-meta"><?php echo esc_html(spp_format_date($conv['updated_at'] ?? '')); ?></span>
                                <?php if ($conv_unread > 0): ?>
                                    <span class="spp-conv-badge"><?php echo esc_html($conv_unread); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php esc_html_e('No conversations found.', 'sports-player-platform'); ?></p>
            <?php endif; ?>
        </aside>

        <section class="spp-conversation-thread">
            <?php if ($conversation): ?>
                <header class="spp-thread-header">
                    <h2><?php echo esc_html($conversation->subject ?? ''); ?></h2>
                    <?php if ($can_archive): ?>
                        <a href="<?php echo esc_url(add_query_arg(['spp_message_action' => 'archive', 'conversation' => $conversation_id])); ?>"
                           class="button button-secondary spp-archive-btn">
                            <?php esc_html_e('Archive', 'sports-player-platform'); ?>
                        </a>
                    <?php endif; ?>
                </header>

                <div id="spp-thread-messages" class="spp-thread-messages"
                     data-conversation-id="<?php echo esc_attr((string) $conversation_id); ?>"
                     data-user-id="<?php echo esc_attr((string) $user_id); ?>"
                     data-last-ts="<?php echo esc_attr($messages ? (string) end($messages)['sent_at'] : ''); ?>">
                    <?php if ($messages): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="spp-message <?php echo ((int) $msg['sender_id'] === (int) $user_id) ? 'spp-message-sent' : 'spp-message-received'; ?>"
                                 data-message-id="<?php echo esc_attr((string) $msg['id']); ?>">
                                <strong class="spp-message-sender"><?php echo esc_html($msg['sender_name'] ?? ''); ?>:</strong>
                                <p class="spp-message-body"><?php echo wp_kses_post($msg['message']); ?></p>
                                <small class="spp-message-time"><?php echo esc_html(spp_format_date($msg['sent_at'] ?? '', 'Y-m-d H:i')); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="spp-no-messages"><?php esc_html_e('No messages in this conversation yet.', 'sports-player-platform'); ?></p>
                    <?php endif; ?>
                </div>

                <form id="spp-reply-form" class="spp-reply-form" method="post" autocomplete="off">
                    <?php wp_nonce_field(SPP_Messaging::NONCE_MESSAGING, 'spp_nonce'); ?>
                    <input type="hidden" name="conversation_id" value="<?php echo esc_attr((string) $conversation_id); ?>">
                    <textarea name="message" id="spp-reply-message" rows="3" class="large-text" required
                              placeholder="<?php esc_attr_e('Type your reply…', 'sports-player-platform'); ?>"></textarea>
                    <p class="spp-reply-actions">
                        <button type="submit" class="button button-primary" id="spp-send-reply-btn">
                            <?php esc_html_e('Send', 'sports-player-platform'); ?>
                        </button>
                        <span class="spp-realtime-status" id="spp-realtime-status" aria-live="polite"></span>
                    </p>
                </form>
            <?php else: ?>
                <div class="spp-thread-empty">
                    <p><?php esc_html_e('Select a conversation to view messages.', 'sports-player-platform'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(add_query_arg(['action' => 'compose'], home_url())); ?>">
                            <?php esc_html_e('Compose New Message', 'sports-player-platform'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
