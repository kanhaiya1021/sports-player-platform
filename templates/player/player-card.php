<?php
/**
 * Reusable Player Card Partial
 *
 * @var int $player_id
 */

if (!defined('ABSPATH')) {
    exit;
}

$player_id = $player_id ?? get_the_ID();
$sport     = (string) get_post_meta($player_id, 'sport', true);
$position  = class_exists('SPP_Player_Profile') ? (string) SPP_Player_Profile::get($player_id, 'playing_position') : (string) get_post_meta($player_id, 'playing_position', true);
$school    = (string) get_post_meta($player_id, 'school', true);
$grad_year = (string) get_post_meta($player_id, 'graduation_year', true);
$location  = (string) get_post_meta($player_id, 'location', true);
$avatar    = class_exists('SPP_Player_Profile') ? (string) SPP_Player_Profile::get($player_id, 'avatar_url') : (string) get_post_meta($player_id, 'avatar_url', true);
?>
<article class="spp-player-card">
    <a href="<?php echo esc_url(get_permalink($player_id)); ?>" class="spp-player-card-media">
        <?php if (has_post_thumbnail($player_id)): ?>
            <?php echo get_the_post_thumbnail($player_id, 'medium', ['class' => 'spp-player-thumb', 'loading' => 'lazy']); ?>
        <?php elseif ($avatar !== ''): ?>
            <img class="spp-player-thumb" src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr(get_the_title($player_id)); ?>" loading="lazy" referrerpolicy="no-referrer">
        <?php else: ?>
            <div class="spp-player-placeholder"><?php echo esc_html(strtoupper(substr(get_the_title($player_id), 0, 1))); ?></div>
        <?php endif; ?>
    </a>

    <div class="spp-player-card-body">
        <h3><a href="<?php echo esc_url(get_permalink($player_id)); ?>"><?php echo esc_html(get_the_title($player_id)); ?></a></h3>
        <?php if ($sport !== '' || $position !== ''): ?>
            <p class="spp-player-meta-line">
                <?php echo esc_html(trim($sport . ($sport && $position ? ' • ' : '') . $position)); ?>
            </p>
        <?php endif; ?>
        <?php if ($school !== ''): ?>
            <p class="spp-player-school"><?php echo esc_html($school); ?></p>
        <?php endif; ?>
        <?php if ($grad_year !== '' || $location !== ''): ?>
            <p class="spp-player-sub">
                <?php
                $parts = array_filter([
                    $grad_year !== '' ? sprintf(__('Class of %s', 'sports-player-platform'), $grad_year) : '',
                    $location,
                ]);
                echo esc_html(implode(' • ', $parts));
                ?>
            </p>
        <?php endif; ?>
    </div>
</article>
