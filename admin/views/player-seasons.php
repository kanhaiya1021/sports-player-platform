<?php
/**
 * Repeatable team history and season stats in the player editor.
 */

if (!defined('ABSPATH')) {
    exit;
}

$rows = array_values(array_filter($rows, 'is_array'));
$is_team = $type === 'teams';
$render_row = static function (array $row, string $index) use ($is_team, $name, $teams, $sports, $default_sport): void {
    $prefix = $name . '[rows][' . $index . ']';
    ?>
    <div class="spp-player-season-row" data-spp-season-row>
        <div class="spp-player-season-controls">
            <?php if ($is_team): ?>
                <?php $team_id = (int) ($row['team_id'] ?? 0); ?>
                <label class="spp-player-season-control">
                    <?php esc_html_e('Team', 'sports-player-platform'); ?>
                    <select name="<?php echo esc_attr($prefix . '[team_id]'); ?>">
                        <option value=""><?php esc_html_e('Select a team', 'sports-player-platform'); ?></option>
                        <?php if ($team_id && !isset($teams[$team_id])): ?>
                            <option value="<?php echo esc_attr($team_id); ?>" selected><?php esc_html_e('Previously linked team unavailable', 'sports-player-platform'); ?></option>
                        <?php endif; ?>
                        <?php foreach ($teams as $id => $title): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($team_id, $id); ?>><?php echo esc_html($title ?: __('Untitled team', 'sports-player-platform')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php else: ?>
                <?php $sport = $row['sport'] ?? $default_sport; ?>
                <label class="spp-player-season-control">
                    <?php esc_html_e('Sport', 'sports-player-platform'); ?>
                    <select name="<?php echo esc_attr($prefix . '[sport]'); ?>" data-spp-stat-sport>
                        <?php foreach ($sports as $option => $definition): ?>
                            <option value="<?php echo esc_attr($option); ?>" <?php selected($sport, $option); ?>><?php echo esc_html($definition['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label class="spp-player-season-control">
                <?php esc_html_e('Season', 'sports-player-platform'); ?>
                <input type="text" name="<?php echo esc_attr($prefix . '[season]'); ?>" value="<?php echo esc_attr($row['season'] ?? ''); ?>"
                    maxlength="40" placeholder="<?php esc_attr_e('e.g. 2025/26', 'sports-player-platform'); ?>">
            </label>
            <?php if ($is_team): ?>
                <label class="spp-player-season-current">
                    <input type="checkbox" name="<?php echo esc_attr($prefix . '[current]'); ?>" value="1" <?php checked(!empty($row['current'])); ?> data-spp-current-team>
                    <?php esc_html_e('Current team', 'sports-player-platform'); ?>
                </label>
            <?php else: ?>
                <label class="spp-player-season-control">
                    <?php esc_html_e('Competition (optional)', 'sports-player-platform'); ?>
                    <input type="text" name="<?php echo esc_attr($prefix . '[competition]'); ?>" value="<?php echo esc_attr($row['competition'] ?? ''); ?>" maxlength="120">
                </label>
            <?php endif; ?>
        </div>
        <?php if (!$is_team): ?>
            <?php foreach ($sports as $option => $definition): ?>
                <div class="spp-player-season-controls" data-spp-stat-metrics="<?php echo esc_attr($option); ?>" <?php if ($sport !== $option): ?>hidden<?php endif; ?>>
                    <?php foreach ($definition['metrics'] as $metric => $metric_label): ?>
                        <label class="spp-player-season-control">
                            <?php echo esc_html($metric_label); ?>
                            <input type="number" min="0" max="9999999" step="1" name="<?php echo esc_attr($prefix . '[metrics][' . $metric . ']'); ?>"
                                value="<?php echo esc_attr($sport === $option ? ($row['metrics'][$metric] ?? '') : ''); ?>" <?php disabled($sport !== $option); ?>>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <button type="button" class="button-link-delete" data-spp-remove-season><?php esc_html_e('Remove row', 'sports-player-platform'); ?></button>
    </div>
    <?php
};
?>
<fieldset class="spp-player-field spp-player-field--wide spp-player-group" data-spp-season-group data-next-index="<?php echo esc_attr(count($rows)); ?>"
    data-max-rows="<?php echo esc_attr(SPP_Player_Profile::MAX_SEASON_ROWS); ?>" data-can-add="<?php echo esc_attr(!$is_team || $teams ? '1' : '0'); ?>">
    <legend><?php echo esc_html($label); ?></legend>
    <input type="hidden" name="<?php echo esc_attr($name . '[present]'); ?>" value="1">
    <?php if ($is_team): ?>
        <p class="description">
            <?php esc_html_e('Link a WordPress Team to each season and mark one current team.', 'sports-player-platform'); ?>
            <?php if (current_user_can(get_post_type_object('team')->cap->create_posts)): ?>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=team')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Add a Team', 'sports-player-platform'); ?></a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <div class="spp-player-season-rows" data-spp-season-rows>
        <?php foreach ($rows as $index => $row): ?>
            <?php $render_row($row, (string) $index); ?>
        <?php endforeach; ?>
    </div>
    <template data-spp-season-template><?php $render_row([], '__row__'); ?></template>
    <button type="button" class="button" data-spp-add-season <?php disabled($is_team && !$teams); ?>>
        <?php echo esc_html($is_team ? __('Add Team Season', 'sports-player-platform') : __('Add Season Stats', 'sports-player-platform')); ?>
    </button>
</fieldset>
