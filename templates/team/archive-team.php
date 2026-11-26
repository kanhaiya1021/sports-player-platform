<?php
/**
 * Team Archive Template (/teams/)
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$selected_sport = sanitize_text_field(wp_unslash($_GET['sport'] ?? ''));
$args           = [
    'post_type'      => 'team',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => max(1, get_query_var('paged')),
];

if ($selected_sport !== '') {
    $args['meta_query'] = [[
        'key'   => 'sport',
        'value' => $selected_sport,
    ]];
}

$teams_query = new WP_Query($args);
?>
<main class="spp-public-wrap spp-archive-wrap">
    <header class="spp-archive-header">
        <h1><?php esc_html_e('Teams', 'sports-player-platform'); ?></h1>
        <form method="get" class="spp-team-filter">
            <input type="text" name="sport" value="<?php echo esc_attr($selected_sport); ?>" placeholder="<?php esc_attr_e('Filter by sport...', 'sports-player-platform'); ?>">
            <button type="submit" class="spp-btn-primary"><?php esc_html_e('Filter', 'sports-player-platform'); ?></button>
        </form>
    </header>

    <div class="spp-teams-list">
        <?php if ($teams_query->have_posts()): ?>
            <?php while ($teams_query->have_posts()): $teams_query->the_post(); ?>
                <?php
                $team_id  = get_the_ID();
                $sport    = (string) get_post_meta($team_id, 'sport', true);
                $location = (string) get_post_meta($team_id, 'location', true);
                ?>
                <article class="spp-team-card">
                    <?php if (has_post_thumbnail()): ?>
                        <?php the_post_thumbnail('medium', ['loading' => 'lazy']); ?>
                    <?php endif; ?>
                    <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                    <?php if ($sport !== ''): ?><p><?php echo esc_html($sport); ?></p><?php endif; ?>
                    <?php if ($location !== ''): ?><p class="spp-muted"><?php echo esc_html($location); ?></p><?php endif; ?>
                </article>
            <?php endwhile; wp_reset_postdata(); ?>
        <?php else: ?>
            <p><?php esc_html_e('No teams found.', 'sports-player-platform'); ?></p>
        <?php endif; ?>
    </div>
</main>
<?php
get_footer();
