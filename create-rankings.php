<?php
/**
 * Create Basketball Rankings and Categories
 *
 * Run via: php create-rankings.php or wp eval-file create-rankings.php
 */

// Load WordPress
if (file_exists(__DIR__ . '/../../../wp-load.php')) {
    require_once __DIR__ . '/../../../wp-load.php';
} elseif (defined('ABSPATH')) {
    // Already loaded
} else {
    die("Cannot find wp-load.php\n");
}

global $wpdb;

$table_prefix = $wpdb->prefix;

echo "Setting up Basketball Rankings...\n\n";

// Create Basketball ranking category
$basketball_category = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$table_prefix}spp_ranking_categories WHERE name = %s",
        'Basketball'
    )
);

if (!$basketball_category) {
    $result = $wpdb->insert(
        $table_prefix . 'spp_ranking_categories',
        [
            'name' => 'Basketball',
            'description' => 'Professional and collegiate basketball player rankings',
            'is_active' => 1,
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%d', '%s']
    );

    if ($result) {
        $basketball_category_id = $wpdb->insert_id;
        echo "Created Basketball ranking category (ID: $basketball_category_id)\n";
    } else {
        echo "Error creating category: " . $wpdb->last_error . "\n";
        die();
    }
} else {
    $basketball_category_id = $basketball_category->id;
    echo "Basketball category already exists (ID: $basketball_category_id)\n";
}

// Create Regional categories for Basketball
$regions = [
    'East Coast' => 'Northeast and Atlantic seaboard basketball prospects',
    'Midwest' => 'Midwest regional basketball rankings',
    'West Coast' => 'California, Pacific Northwest basketball prospects',
    'Southern' => 'Southeast and Southwest basketball rankings',
    'National' => 'National top basketball prospects',
];

$regional_category_ids = [];
foreach ($regions as $name => $description) {
    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_prefix}spp_ranking_categories WHERE name = %s",
            $name
        )
    );

    if (!$existing) {
        $result = $wpdb->insert(
            $table_prefix . 'spp_ranking_categories',
            [
                'name' => $name,
                'description' => $description,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s']
        );

        if ($result) {
            $regional_category_ids[$name] = $wpdb->insert_id;
            echo "Created $name category (ID: $wpdb->insert_id)\n";
        }
    } else {
        $regional_category_ids[$name] = $existing->id;
        echo "$name category exists (ID: $existing->id)\n";
    }
}

// Get ONLY basketball players (created from our script)
$basketball_players = get_posts([
    'post_type' => 'player',
    'post_status' => 'publish',
    'posts_per_page' => 100,
    'meta_query' => [
        [
            'key' => 'sport',
            'value' => 'Basketball',
            'compare' => '=',
        ],
    ],
]);

echo "\nFound " . count($basketball_players) . " basketball players\n";

if (empty($basketball_players)) {
    echo "No basketball players found. Run create-players.php first.\n";
    die();
}

// Clear existing basketball rankings to avoid duplicates
$category_ids_list = implode(',', array_values($regional_category_ids));
$wpdb->query("DELETE FROM {$table_prefix}spp_rankings WHERE ranking_category_id IN ($category_ids_list)");

echo "\nAssigning rankings...\n";

// Regions for round-robin assignment
$regions_list = array_keys($regional_category_ids);

foreach ($basketball_players as $index => $player) {
    $player_id = $player->ID;

    // Get player attributes
    $height = (int) get_post_meta($player_id, 'height', true);
    $position = get_post_meta($player_id, 'playing_position', true);
    $grad_year = get_post_meta($player_id, 'graduation_year', true);
    $jersey = get_post_meta($player_id, 'player_jersey', true);

    // Determine regional ranking (round-robin style assignment)
    $selected_region = $regions_list[$index % count($regions_list)];
    $category_id = $regional_category_ids[$selected_region];

    // Calculate rank based on height/position (higher rank for taller players, better positions)
    $base_rank = ($index + 1) * 2; // Start at rank 2, 4, 6, etc.
    if ($height > 205) $base_rank -= 1; // Add bonus for very tall players
    if (in_array($position, ['Point Guard', 'Shooting Guard'])) $base_rank += 1; // Bonus for ball handlers

    // Make sure rank is positive
    $rank = max(1, $base_rank);

    // Insert ranking
    $result = $wpdb->insert(
        $table_prefix . 'spp_rankings',
        [
            'player_id' => $player_id,
            'ranking_category_id' => $category_id,
            'ranking_position' => $rank,
            'effective_date' => current_time('mysql'),
            'notes' => $position . ($grad_year ? ' - Class of ' . $grad_year : ''),
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%d', '%d', '%s', '%s', '%s']
    );

    if ($result) {
        echo "Ranked {$player->post_title} as #$rank in $selected_region\n";
    }
}

echo "\n--- Summary ---\n";

// Count rankings per category
foreach ($regional_category_ids as $name => $cat_id) {
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_prefix}spp_rankings WHERE ranking_category_id = %d",
        $cat_id
    ));
    echo "$name: $count players ranked\n";
}

echo "\nDone! You can now view rankings at: http://faast.local/rankings/\n";
echo "Or use shortcode: [spp_rankings]\n";