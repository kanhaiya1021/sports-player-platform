<?php
/**
 * Coach Recruiting Dashboard Template ([spp_recruiting])
 *
 * @var array      $lists
 * @var int        $active_list_id
 * @var array|null $active_list
 * @var array      $list_players
 * @var string     $active_tab
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="spp-dashboard-wrap">
    <?php include SPP_PLUGIN_DIR . 'templates/dashboard/nav.php'; ?>

    <div class="spp-messages-header">
        <h2><?php esc_html_e('Recruiting Lists', 'sports-player-platform'); ?></h2>
        <button type="button" id="spp-toggle-create-list" class="spp-btn spp-btn-primary">
            <?php esc_html_e('+ Create List', 'sports-player-platform'); ?>
        </button>
    </div>

    <div id="spp-recruiting-feedback" class="spp-notice" style="display:none;"></div>

    <form id="spp-create-list-form" class="spp-card spp-form" style="display:none; margin-bottom:20px;">
        <h4><?php esc_html_e('New Recruiting List', 'sports-player-platform'); ?></h4>
        <div class="spp-form-row">
            <p class="spp-form-field">
                <label for="spp_list_name"><?php esc_html_e('List Name *', 'sports-player-platform'); ?></label>
                <input type="text" id="spp_list_name" name="name" required placeholder="<?php esc_attr_e('e.g., 2027 Midfield Targets', 'sports-player-platform'); ?>">
            </p>
            <p class="spp-form-field">
                <label for="spp_list_sport"><?php esc_html_e('Sport', 'sports-player-platform'); ?></label>
                <input type="text" id="spp_list_sport" name="sport" placeholder="<?php esc_attr_e('e.g., Basketball', 'sports-player-platform'); ?>">
            </p>
        </div>
        <p class="spp-form-field">
            <label for="spp_list_desc"><?php esc_html_e('Description', 'sports-player-platform'); ?></label>
            <textarea id="spp_list_desc" name="description" rows="2"></textarea>
        </p>
        <p class="spp-form-field">
            <label>
                <input type="checkbox" name="is_private" value="1" checked>
                <?php esc_html_e('Private list (visible only to you)', 'sports-player-platform'); ?>
            </label>
        </p>
        <button type="submit" class="spp-btn spp-btn-primary"><?php esc_html_e('Create List', 'sports-player-platform'); ?></button>
    </form>

    <?php if (empty($lists)): ?>
        <div class="spp-notice spp-notice-info">
            <p><?php esc_html_e('You have not created any recruiting lists yet.', 'sports-player-platform'); ?></p>
        </div>
    <?php else: ?>
        <div class="spp-stats-grid">
            <?php foreach ($lists as $list): ?>
                <?php $is_selected = ((int) $list['id'] === $active_list_id); ?>
                <a href="<?php echo esc_url(add_query_arg('list_id', $list['id'])); ?>" class="spp-stat-card" style="text-decoration:none; color:inherit; border-width: <?php echo $is_selected ? '2px' : '1px'; ?>; border-color: <?php echo $is_selected ? 'var(--spp-primary)' : 'var(--spp-border)'; ?>;">
                    <div class="spp-stat-value"><?php echo esc_html((string) ($list['player_count'] ?? 0)); ?></div>
                    <div class="spp-stat-label"><strong><?php echo esc_html($list['name']); ?></strong></div>
                    <?php if (!empty($list['sport'])): ?>
                        <small style="color:#6b7280;"><?php echo esc_html($list['sport']); ?></small>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($active_list): ?>
            <div class="spp-card">
                <div class="spp-messages-header">
                    <div>
                        <h3 style="margin:0;"><?php echo esc_html($active_list['name']); ?></h3>
                        <?php if (!empty($active_list['description'])): ?>
                            <p style="margin:4px 0 0; color:#6b7280;"><?php echo esc_html($active_list['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=spp_export_recruiting_list&list_id=' . (int) $active_list['id']), 'spp_export_list_nonce')); ?>" class="spp-btn spp-btn-secondary">
                        <?php esc_html_e('Export CSV', 'sports-player-platform'); ?>
                    </a>
                </div>

                <?php if (empty($list_players)): ?>
                    <p><?php esc_html_e('No players in this list yet. Visit any player profile to add them.', 'sports-player-platform'); ?></p>
                <?php else: ?>
                    <table class="spp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Player', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Notes', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Actions', 'sports-player-platform'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($list_players as $lp): ?>
                                <tr data-list-id="<?php echo esc_attr((string) $active_list['id']); ?>" data-player-id="<?php echo esc_attr((string) $lp['player_id']); ?>">
                                    <td>
                                        <a href="<?php echo esc_url(get_permalink((int) $lp['player_id'])); ?>">
                                            <?php echo esc_html($lp['player_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <input type="text" class="spp-list-notes-input" value="<?php echo esc_attr($lp['notes'] ?? ''); ?>" style="width:100%; padding:6px;">
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <button type="button" class="spp-btn spp-btn-primary spp-save-list-notes" data-list-id="<?php echo esc_attr((string) $active_list['id']); ?>" data-player-id="<?php echo esc_attr((string) $lp['player_id']); ?>">
                                            <?php esc_html_e('Save', 'sports-player-platform'); ?>
                                        </button>
                                        <button type="button" class="spp-btn spp-btn-danger spp-remove-from-list" data-list-id="<?php echo esc_attr((string) $active_list['id']); ?>" data-player-id="<?php echo esc_attr((string) $lp['player_id']); ?>">
                                            <?php esc_html_e('Remove', 'sports-player-platform'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
