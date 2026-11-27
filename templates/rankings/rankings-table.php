<?php
/**
 * Rankings Table Template
 * Displays player rankings from database with player info from posts
 *
 * @package Sports Player Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get active ranking categories
$categories = get_transient('spp_ranking_categories_active');
if ($categories === false) {
    $categories = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}spp_ranking_categories WHERE is_active = 1 ORDER BY name ASC"
    );
    set_transient('spp_ranking_categories_active', $categories, 15 * MINUTE_IN_SECONDS);
}

if (empty($categories)) {
    echo '<div class="spp-rankings-display">';
    echo '<p class="spp-no-rankings">' . esc_html__('No ranking categories available. Please contact the site administrator.', 'sports-player-platform') . '</p>';
    echo '</div>';
    return;
}

// Get selected category from URL or filter
$category_id = isset($_GET['spp_category']) ? absint($_GET['spp_category']) : 0;

echo '<div class="spp-rankings-display">';

// Category selector dropdown
echo '<form class="spp-rankings-form" method="get">';
echo '<label for="spp_category">' . esc_html__('Filter by Sport', 'sports-player-platform') . '</label>';
echo '<select name="spp_category" id="spp_category" class="spp-category-select">';

// Get unique sports from categories for better grouping
$sports = [];
foreach ($categories as $cat) {
    if (!isset($sports[$cat->name])) {
        $sports[$cat->name] = $cat->id;
    }
}

echo '<option value="">' . esc_html__('All Sports', 'sports-player-platform') . '</option>';
foreach ($sports as $sport_name => $cat_id) {
    $selected = selected($category_id, $cat_id, false);
    echo '<option value="' . esc_attr($cat_id) . '"' . $selected . '>' .
         esc_html(ucfirst($sport_name)) . '</option>';
}
echo '</select>';
echo '</form>';

// If category selected, show rankings for that category
if ($category_id > 0) {
    $category = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}spp_ranking_categories WHERE id = %d",
        $category_id
    ));

    if ($category) {
        echo '<h2 class="spp-rankings-title">' . esc_html(__('Rankings for', 'sports-player-platform')) . ' ' . esc_html($category->name) . '</h2>';

        // Get rankings with player info
        $ranked_players = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, p.post_title as player_name, p.guid as player_guid
            FROM {$wpdb->prefix}spp_rankings r
            JOIN {$wpdb->posts} p ON r.player_id = p.ID
            WHERE r.ranking_category_id = %d
              AND p.post_status = 'publish'
            ORDER BY r.ranking_position ASC
            LIMIT 50
        ", $category_id));

        if (!empty($ranked_players)) {
            echo '<table class="spp-rankings-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th class="spp-rank-position">' . esc_html__('Rank', 'sports-player-platform') . '</th>';
            echo '<th class="spp-rank-player">' . esc_html__('Player', 'sports-player-platform') . '</th>';
            echo '<th class="spp-rank-position-info">' . esc_html__('Position', 'sports-player-platform') . '</th>';
            echo '<th class="spp-rank-grade">' . esc_html__('Graduation Year', 'sports-player-platform') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($ranked_players as $ranking) {
                // Get player position and graduation year from post meta
                $position = get_post_meta($ranking->player_id, 'playing_position', true);
                $grad_year = get_post_meta($ranking->player_id, 'graduation_year', true);
                $school = get_post_meta($ranking->player_id, 'school', true);

                echo '<tr class="spp-ranking-row">';
                echo '<td class="spp-rank-position-cell">';
                echo '<span class="spp-rank-badge">' . esc_html($ranking->ranking_position) . '</span>';
                echo '</td>';

                echo '<td class="spp-rank-player-cell">';
                if (get_permalink($ranking->player_id)) {
                    echo '<a href="' . esc_url(get_permalink($ranking->player_id)) . '" class="spp-player-link">';
                    echo esc_html($ranking->player_name);
                    echo '</a>';
                } else {
                    echo esc_html($ranking->player_name);
                }
                if (!empty($school)) {
                    echo '<span class="spp-player-school">' . esc_html($school) . '</span>';
                }
                echo '</td>';

                if (!empty($position)) {
                    echo '<td class="spp-rank-position-info-cell">' . esc_html($position) . '</td>';
                } else {
                    echo '<td class="spp-rank-position-info-cell">-</td>';
                }

                if (!empty($grad_year)) {
                    echo '<td class="spp-rank-grade-cell">' . esc_html($grad_year) . '</td>';
                } else {
                    echo '<td class="spp-rank-grade-cell">-</td>';
                }
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p class="spp-no-rankings">' . esc_html__('No players are currently ranked in this category.', 'sports-player-platform') . '</p>';
        }
    }
} else {
    // Show all sports with available rankings count
    echo '<div class="spp-rankings-categories">';
    foreach ($categories as $cat) {
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spp_rankings WHERE ranking_category_id = %d",
            $cat->id
        ));
        $url = add_query_arg('spp_category', $cat->id, get_permalink());
        echo '<div class="spp-ranking-category-card">';
        echo '<h3><a href="' . esc_url($url) . '" class="spp-category-link">' . esc_html($cat->name) . '</a></h3>';
        echo '<p class="spp-category-count">' . sprintf(esc_html__('%d players ranked', 'sports-player-platform'), $count) . '</p>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div>';