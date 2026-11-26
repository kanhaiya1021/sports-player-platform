<?php
/**
 * Player Archive / Directory Template (/players/)
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$positions = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward', 'Guard', 'Center', 'Pitcher', 'Quarterback'];
$sports    = ['Soccer', 'Basketball', 'Baseball', 'Football', 'Tennis', 'Cricket', 'Volleyball', 'Track & Field'];

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
                    <select id="spp_dir_sport" name="sport">
                        <option value=""><?php esc_html_e('All Sports', 'sports-player-platform'); ?></option>
                        <?php foreach ($sports as $sport): ?>
                            <option value="<?php echo esc_attr($sport); ?>"><?php echo esc_html($sport); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="spp-filter-group">
                    <label for="spp_dir_position"><?php esc_html_e('Position', 'sports-player-platform'); ?></label>
                    <select id="spp_dir_position" name="position">
                        <option value=""><?php esc_html_e('All Positions', 'sports-player-platform'); ?></option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?php echo esc_attr($pos); ?>"><?php echo esc_html($pos); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="spp-filter-group">
                    <label for="spp_dir_grad_min"><?php esc_html_e('Graduation Year Range', 'sports-player-platform'); ?></label>
                    <div class="spp-range-inputs">
                        <input type="number" id="spp_dir_grad_min" name="grad_min" placeholder="2024" min="2000" max="2035">
                        <span>–</span>
                        <input type="number" id="spp_dir_grad_max" name="grad_max" placeholder="2030" min="2000" max="2035">
                    </div>
                </div>

                <div class="spp-filter-group">
                    <label for="spp_dir_location"><?php esc_html_e('Location', 'sports-player-platform'); ?></label>
                    <input type="text" id="spp_dir_location" name="location" placeholder="<?php esc_attr_e('City or State', 'sports-player-platform'); ?>">
                </div>

                <div class="spp-filter-group">
                    <label for="spp_dir_height_min"><?php esc_html_e('Height Range (cm)', 'sports-player-platform'); ?></label>
                    <div class="spp-range-inputs">
                        <input type="number" id="spp_dir_height_min" name="height_min" placeholder="150" min="100" max="240">
                        <span>–</span>
                        <input type="number" id="spp_dir_height_max" name="height_max" placeholder="210" min="100" max="240">
                    </div>
                </div>

                <div class="spp-filter-group">
                    <label for="spp_dir_sort"><?php esc_html_e('Sort By', 'sports-player-platform'); ?></label>
                    <select id="spp_dir_sort" name="orderby">
                        <option value="recent"><?php esc_html_e('Recently Added', 'sports-player-platform'); ?></option>
                        <option value="name"><?php esc_html_e('Name (A–Z)', 'sports-player-platform'); ?></option>
                        <option value="grad_year"><?php esc_html_e('Graduation Year', 'sports-player-platform'); ?></option>
                    </select>
                </div>

                <button type="button" id="spp-dir-reset" class="spp-btn-secondary" style="width:100%;">
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
