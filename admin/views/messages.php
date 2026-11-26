<?php
/**
 * Admin View: Message Moderation
 *
 * @package Sports_Player_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Messages Moderation', 'sports-player-platform'); ?></h1>

    <?php if (!empty($notice)): ?>
        <div class="notice notice-<?php echo esc_attr($notice_type ?? 'success'); ?> is-dismissible">
            <p><?php echo esc_html($notice); ?></p>
        </div>
    <?php endif; ?>

    <form method="get" style="margin: 15px 0; display: flex; gap: 8px; align-items: center;">
        <input type="hidden" name="page" value="spp-messages">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search subject or message...', 'sports-player-platform'); ?>">
        <select name="archived">
            <option value=""><?php esc_html_e('All Conversations', 'sports-player-platform'); ?></option>
            <option value="0" <?php selected($archived_filter, '0'); ?>><?php esc_html_e('Active Only', 'sports-player-platform'); ?></option>
            <option value="1" <?php selected($archived_filter, '1'); ?>><?php esc_html_e('Flagged / Archived', 'sports-player-platform'); ?></option>
        </select>
        <button type="submit" class="button"><?php esc_html_e('Filter', 'sports-player-platform'); ?></button>
    </form>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('ID', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Subject', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Participants', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Messages', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Last Activity', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Status', 'sports-player-platform'); ?></th>
                <th><?php esc_html_e('Actions', 'sports-player-platform'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($conversations)): ?>
                <?php foreach ($conversations as $conv): ?>
                    <tr>
                        <td>#<?php echo absint($conv['id']); ?></td>
                        <td>
                            <strong><?php echo esc_html($conv['subject']); ?></strong>
                            <?php if (!empty($conv['last_message'])): ?>
                                <p style="margin: 4px 0 0; color: #646970;">
                                    <?php echo esc_html(wp_trim_words($conv['last_message'], 16)); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($conv['participants_label']); ?></td>
                        <td><?php echo absint($conv['message_count']); ?></td>
                        <td><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($conv['updated_at']))); ?></td>
                        <td>
                            <?php if ((int) $conv['is_archived'] === 1): ?>
                                <span style="color: #b32d2e; font-weight: 600;"><?php esc_html_e('Flagged / Archived', 'sports-player-platform'); ?></span>
                            <?php else: ?>
                                <span style="color: #00a32a; font-weight: 600;"><?php esc_html_e('Active', 'sports-player-platform'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-flex; gap:6px;">
                                <?php wp_nonce_field('spp_admin_message_action'); ?>
                                <input type="hidden" name="action" value="spp_admin_moderate_conversation">
                                <input type="hidden" name="conversation_id" value="<?php echo absint($conv['id']); ?>">

                                <?php if ((int) $conv['is_archived'] === 1): ?>
                                    <button type="submit" name="sub_action" value="unflag" class="button button-small">
                                        <?php esc_html_e('Unflag', 'sports-player-platform'); ?>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="sub_action" value="flag" class="button button-small">
                                        <?php esc_html_e('Flag / Archive', 'sports-player-platform'); ?>
                                    </button>
                                <?php endif; ?>

                                <button type="submit" name="sub_action" value="delete" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Permanently delete this conversation and all its messages?', 'sports-player-platform')); ?>');">
                                    <?php esc_html_e('Delete', 'sports-player-platform'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7"><?php esc_html_e('No conversations found.', 'sports-player-platform'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
