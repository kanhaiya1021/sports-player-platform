<?php
/**
 * Single Team Template (/teams/{slug}/)
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()):
    the_post();
    $team_id  = get_the_ID();
    $sport    = (string) get_post_meta($team_id, 'sport', true);
    $location = (string) get_post_meta($team_id, 'location', true);
    $founded  = (string) get_post_meta($team_id, 'founded', true);

    $roster = new WP_Query([
        'post_type'      => 'player',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'meta_query'     => [[
            'key'     => 'school',
            'value'   => get_the_title(),
            'compare' => 'LIKE',
        ]],
    ]);
    ?>
    <main class="spp-public-wrap spp-single-team">
        <section class="spp-player-hero">
            <?php if (has_post_thumbnail()): ?>
                <div class="spp-hero-avatar"><?php the_post_thumbnail('medium', ['class' => 'spp-hero-img']); ?></div>
            <?php endif; ?>
            <div class="spp-hero-info">
                <h1><?php the_title(); ?></h1>
                <p class="spp-hero-sub"><?php echo esc_html(implode(' • ', array_filter([$sport, $location, $founded ? 'Est. ' . $founded : '']))); ?></p>
            </div>
        </section>

        <div class="spp-box" style="margin-bottom:24px;">
            <h2><?php esc_html_e('About Team', 'sports-player-platform'); ?></h2>
            <?php the_content(); ?>
        </div>

        <section>
            <h2><?php esc_html_e('Team Roster', 'sports-player-platform'); ?></h2>
            <?php if ($roster->have_posts()): ?>
                <div class="spp-players-grid spp-columns-3">
                    <?php while ($roster->have_posts()): $roster->the_post(); ?>
                        <?php $player_id = get_the_ID(); include SPP_PLUGIN_DIR . 'templates/player/player-card.php'; ?>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            <?php else: ?>
                <p><?php esc_html_e('No roster players linked yet.', 'sports-player-platform'); ?></p>
            <?php endif; ?>
        </section>
    </main>
    <?php
endwhile;

get_footer();
