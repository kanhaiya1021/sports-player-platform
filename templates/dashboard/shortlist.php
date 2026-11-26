<?php
/**
 * Dashboard Shortlist Template (Coach / Parent)
 *
 * @var array  $saved_players
 * @var string $active_tab
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="spp-dashboard-wrap">
    <?php include SPP_PLUGIN_DIR . 'templates/dashboard/nav.php'; ?>

    <h2><?php esc_html_e('Saved Players Shortlist', 'sports-player-platform'); ?></h2>

    <?php if (empty($saved_players)): ?>
        <div class="spp-notice spp-notice-info">
            <p>
                <?php esc_html_e('You have not saved any players yet.', 'sports-player-platform'); ?>
                <a href="<?php echo esc_url(home_url('/players/')); ?>"><?php esc_html_e('Browse Players', 'sports-player-platform'); ?></a>
            </p>
        </div>
    <?php else: ?>
        <table class="spp-table" id="spp-shortlist-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Player', 'sports-player-platform'); ?></th>
                    <th><?php esc_html_e('Saved On', 'sports-player-platform'); ?></th>
                    <th><?php esc_html_e('Notes', 'sports-player-platform'); ?></th>
                    <th><?php esc_html_e('Actions', 'sports-player-platform'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($saved_players as $item): ?>
                    <tr data-player-id="<?php echo esc_attr((string) $item['player_id']); ?>">
                        <td>
                            <a href="<?php echo esc_url(get_permalink((int) $item['player_id'])); ?>">
                                <?php echo esc_html($item['player_name']); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html(spp_format_date($item['saved_at'])); ?></td>
                        <td><?php echo esc_html($item['notes'] ?? ''); ?></td>
                        <td>
                            <button type="button" class="spp-btn spp-btn-danger spp-remove-shortlist" data-player-id="<?php echo esc_attr((string) $item['player_id']); ?>">
                                <?php esc_html_e('Remove', 'sports-player-platform'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
