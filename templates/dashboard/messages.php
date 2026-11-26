<?php
/**
 * Dashboard Messages Split-Pane Template ([spp_messages])
 *
 * @var int    $user_id
 * @var array  $conversations
 * @var int    $active_conv_id
 * @var array  $active_messages
 * @var array  $recipients
 * @var string $active_tab
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="spp-dashboard-wrap">
    <?php include SPP_PLUGIN_DIR . 'templates/dashboard/nav.php'; ?>

    <div class="spp-messages-header">
        <h2><?php esc_html_e('Messages', 'sports-player-platform'); ?></h2>
        <?php if (!empty($recipients)): ?>
            <button type="button" id="spp-new-conv-toggle" class="spp-btn spp-btn-primary">
                <?php esc_html_e('+ New Message', 'sports-player-platform'); ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($recipients)): ?>
        <form id="spp-new-conv-form" class="spp-card spp-form" style="display:none; margin-bottom:20px;">
            <h4><?php esc_html_e('Start New Conversation', 'sports-player-platform'); ?></h4>
            <div class="spp-form-row">
                <p class="spp-form-field">
                    <label for="spp_new_recipient"><?php esc_html_e('Player', 'sports-player-platform'); ?></label>
                    <select id="spp_new_recipient" name="recipient_id" required>
                        <option value=""><?php esc_html_e('Select Player', 'sports-player-platform'); ?></option>
                        <?php foreach ($recipients as $rec): ?>
                            <option value="<?php echo esc_attr((string) $rec->ID); ?>"><?php echo esc_html($rec->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="spp-form-field">
                    <label for="spp_new_subject"><?php esc_html_e('Subject', 'sports-player-platform'); ?></label>
                    <input type="text" id="spp_new_subject" name="subject" required>
                </p>
            </div>
            <p class="spp-form-field">
                <label for="spp_new_body"><?php esc_html_e('Message', 'sports-player-platform'); ?></label>
                <textarea id="spp_new_body" name="message" rows="3" required></textarea>
            </p>
            <button type="submit" class="spp-btn spp-btn-primary"><?php esc_html_e('Send Message', 'sports-player-platform'); ?></button>
        </form>
    <?php endif; ?>

    <div id="spp-msg-feedback" class="spp-notice" style="display:none;"></div>

    <div class="spp-messaging-shell" data-active-conversation="<?php echo esc_attr((string) $active_conv_id); ?>">
        <!-- Left Panel: Conversation List -->
        <aside class="spp-conv-list">
            <?php if (empty($conversations)): ?>
                <p class="spp-muted"><?php esc_html_e('No conversations yet.', 'sports-player-platform'); ?></p>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <?php $is_active = ((int) $conv['id'] === $active_conv_id); ?>
                    <a href="<?php echo esc_url(add_query_arg('conversation', $conv['id'])); ?>"
                       class="spp-conv-item <?php echo $is_active ? 'is-active' : ''; ?>">
                        <div class="spp-conv-title">
                            <span><?php echo esc_html($conv['subject']); ?></span>
                            <?php if (!empty($conv['unread_count'])): ?>
                                <span class="spp-unread-pill"><?php echo esc_html((string) $conv['unread_count']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="spp-conv-preview"><?php echo esc_html($conv['last_message']); ?></div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </aside>

        <!-- Right Panel: Message Thread -->
        <section class="spp-thread-panel">
            <?php if ($active_conv_id): ?>
                <div class="spp-thread-messages" id="spp-thread-messages">
                    <?php foreach ($active_messages as $msg): ?>
                        <?php $is_mine = ((int) $msg['sender_id'] === $user_id); ?>
                        <div class="spp-bubble <?php echo $is_mine ? 'is-mine' : 'is-theirs'; ?>">
                            <div class="spp-bubble-meta">
                                <strong><?php echo esc_html($msg['sender_name'] ?? ''); ?></strong>
                                <span><?php echo esc_html(spp_format_date($msg['sent_at'], 'M j, g:i a')); ?></span>
                            </div>
                            <div class="spp-bubble-body"><?php echo wp_kses_post($msg['message']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form id="spp-reply-form" class="spp-reply-form">
                    <input type="hidden" name="conversation_id" value="<?php echo esc_attr((string) $active_conv_id); ?>">
                    <textarea name="message" id="spp-reply-input" rows="2" placeholder="<?php esc_attr_e('Write a reply...', 'sports-player-platform'); ?>" required></textarea>
                    <button type="submit" id="spp-reply-btn" class="spp-btn spp-btn-primary">
                        <?php esc_html_e('Send', 'sports-player-platform'); ?>
                    </button>
                </form>
            <?php else: ?>
                <div class="spp-thread-empty">
                    <p><?php esc_html_e('Select a conversation from the left to view messages.', 'sports-player-platform'); ?></p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
