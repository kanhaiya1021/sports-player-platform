<?php
/**
 * Player Archive / Directory Template (/players/)
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$positions = ['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center', 'Guard', 'Forward'];
$sports    = ['Basketball'];

global $wpdb;
$ranking_categories = get_transient('spp_ranking_categories_active');
if (false === $ranking_categories) {
    $ranking_categories = $wpdb->get_results(
        "SELECT id, name FROM {$wpdb->prefix}spp_ranking_categories WHERE is_active = 1 ORDER BY name ASC"
    ) ?: [];
    set_transient('spp_ranking_categories_active', $ranking_categories, 15 * MINUTE_IN_SECONDS);
}

$initial_query = new WP_Query([
    'post_type'      => 'player',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => max(1, get_query_var('paged')),
    'orderby'        => 'date',
    'order'          => 'DESC',
]);
?>
<main class="spp-public-wrap spp-archive-wrap">
    <header class="spp-archive-header">
        <h1><?php esc_html_e('Player Directory', 'sports-player-platform'); ?></h1>
        <p><?php esc_html_e('Discover verified athletes by sport, position, graduation year, and location.', 'sports-player-platform'); ?></p>
    </header>

    <div class="spp-directory-layout">
        <!-- Filter Sidebar -->
        <aside class="spp-filter-sidebar">
            <form id="spp-directory-filters" class="spp-filter-form">
                <div class="spp-filter-group">
                    <label for="spp_dir_search"><?php esc_html_e('Search Name', 'sports-player-platform'); ?></label>
                    <input type="text" id="spp_dir_search" name="search" placeholder="<?php esc_attr_e('Player name...', 'sports-player-platform'); ?>">
                </div>

                <div class="spp-filter-group">
                    <label for="spp_dir_sport"><?php esc_html_e('Sport', 'sports-player-platform'); ?></label>
                    <select id="spp_dir_sport" name="sport" class="spp-filter-sport">
                        <option value=""><?php esc_html_e('All Sports', 'sports-player-platform'); ?></option>
                        <?php foreach ($sports as $sport): ?>
                            <option value="<?php echo esc_attr($sport); ?>"><?php echo esc_html($sport); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="spp-filter-group">
                    <label for="spp_dir_position"><?php esc_html_e('Position', 'sports-player-platform'); ?></label>
                    <select id="spp_dir_position" name="position" class="spp-filter-position">
                        <option value=""><?php esc_html_e('All Positions', 'sports-player-platform'); ?></option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?php echo esc_attr($pos); ?>"><?php echo esc_html($pos); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="spp-filter-group">
                    <label for="spp_dir_grad_year"><?php esc_html_e('Graduation Year', 'sports-player-platform'); ?></label>
                    <select id="spp_dir_grad_year" name="grad_year" class="spp-filter-grad-year">
                        <option value=""><?php esc_html_e('All Years', 'sports-player-platform'); ?></option>
                        <?php for ($year = 2024; $year <= 2027; $year++): ?>
                            <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="spp-filter-group">
                    <label for="spp_dir_ranking"><?php esc_html_e('Ranking', 'sports-player-platform'); ?></label>
                    <select id="spp_dir_ranking" name="ranking" class="spp-filter-ranking">
                        <option value=""><?php esc_html_e('All Rankings', 'sports-player-platform'); ?></option>
                        <?php foreach ($ranking_categories as $cat): ?>
                            <option value="<?php echo esc_attr((string) $cat->id); ?>"><?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="button" id="spp-dir-reset" class="spp-btn-secondary spp-reset-filters" style="width:100%;">
                    <?php esc_html_e('Reset Filters', 'sports-player-platform'); ?>
                </button>
            </form>
        </aside>

        <!-- Results Area -->
        <section class="spp-directory-results">
            <div class="spp-results-meta">
                <span id="spp-results-count">
                    <?php printf(esc_html__('Showing %d players', 'sports-player-platform'), (int) $initial_query->found_posts); ?>
                </span>
            </div>

            <div id="spp-directory-grid" class="spp-players-grid spp-columns-3">
                <?php
                if ($initial_query->have_posts()):
                    while ($initial_query->have_posts()):
                        $initial_query->the_post();
                        $player_id = get_the_ID();
                        include SPP_PLUGIN_DIR . 'templates/player/player-card.php';
                    endwhile;
                    wp_reset_postdata();
                else:
                    echo '<p>' . esc_html__('No players found.', 'sports-player-platform') . '</p>';
                endif;
                ?>
            </div>

            <div id="spp-directory-pagination" class="spp-pagination">
                <?php
                echo wp_kses_post(paginate_links([
                    'total'   => (int) $initial_query->max_num_pages,
                    'current' => max(1, get_query_var('paged')),
                ]) ?: '');
                ?>
            </div>
        </section>
    </div>
</main>
<?php
get_footer();
