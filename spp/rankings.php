<?php
/**
 * Rankings Page Template
 * Displays player/team rankings
 *
 * @package Sports Player Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="spp-rankings-page">
    <div class="spp-container">
        <h1 class="spp-page-title">Player Rankings</h1>
        <p class="spp-page-description">Discover the top athletes in our database, ranked by performance metrics and recruiting potential. Browse by sport and region.</p>

        <div class="spp-rankings-content">
            <?php echo do_shortcode('[spp_rankings]'); ?>

            <?php if (get_transient('spp_ranking_categories_active') === false) {
                // Check if there are any ranking categories
                global $wpdb;
                $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}spp_ranking_categories WHERE is_active = 1");
                if (empty($categories)): ?>
                    <div class="spp-no-rankings">
                        <div class="spp-no-rankings-icon">📊</div>
                        <h3>No Rankings Available Yet</h3>
                        <p>Rankings will be displayed here once the ranking system is populated with data.</p>
                        <p>Check back soon for player and team rankings across all categories.</p>
                    </div>
                <?php endif;
            } else {
                $categories = get_transient('spp_ranking_categories_active');
                if (empty($categories)): ?>
                    <div class="spp-no-rankings">
                        <div class="spp-no-rankings-icon">📊</div>
                        <h3>No Rankings Available Yet</h3>
                        <p>Rankings are being curated. Check back soon!</p>
                    </div>
                <?php endif;
            } ?>
        </div>
    </div>
</main>

<style>
.spp-rankings-page {
    padding: 30px 15px;
}

.spp-page-title {
    font-size: 36px;
    margin: 0 0 12px;
    color: #2c3e50;
}

.spp-page-description {
    font-size: 16px;
    color: #6b7280;
    margin: 0 0 30px;
}

.spp-rankings-content {
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.spp-rankings-display {
    margin-top: 20px;
}

.spp-rankings-display .spp-rankings-table {
    width: 100%;
    border-collapse: collapse;
}

.spp-rankings-display .spp-rankings-table th,
.spp-rankings-display .spp-rankings-table td {
    text-align: left;
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
}

.spp-rankings-display .spp-rankings-table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
    text-transform: uppercase;
}

.spp-rankings-display .spp-rankings-table td {
    font-size: 14px;
}

.spp-rankings-display .spp-rankings-table tr:hover {
    background: #f3f4f6;
}

.spp-rankings-display .spp-rankings-table .spp-rank-position {
    width: 60px;
    text-align: center;
    font-weight: 600;
}

.spp-rankings-display .spp-rank-player-name a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
}

.spp-rankings-display .spp-rank-player-name a:hover {
    text-decoration: underline;
}

.spp-rankings-display .spp-rank-club {
    color: #6b7280;
    font-size: 13px;
}

.spp-rankings-display .spp-no-rankings {
    text-align: center;
    padding: 60px 30px;
    background: #f9fafb;
    border-radius: 12px;
    margin-top: 30px;
}

.spp-rankings-display .spp-no-rankings-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.spp-rankings-display .spp-no-rankings h3 {
    font-size: 24px;
    margin: 0 0 12px;
    color: #1f2937;
}

.spp-rankings-display .spp-no-rankings p {
    color: #6b7280;
    margin: 0 0 8px;
}

.spp-rankings-display .spp-rankings-categories {
    margin-top: 30px;
}

.spp-ranking-category-card {
    display: inline-block;
    background: #f9fafb;
    border-radius: 8px;
    padding: 20px;
    margin: 10px;
    text-align: center;
    min-width: 180px;
}

.spp-ranking-category-card .spp-category-link {
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
    font-size: 16px;
}

.spp-ranking-category-card .spp-category-link:hover {
    text-decoration: underline;
}

.spp-ranking-category-card .spp-category-count {
    color: #6b7280;
    font-size: 14px;
    margin-top: 8px;
}

@media (max-width: 768px) {
    .spp-rankings-page {
        padding: 20px 10px;
    }

    .spp-page-title {
        font-size: 28px;
    }

    .spp-rankings-content {
        padding: 20px;
    }

    .spp-rankings-display .spp-rankings-table th,
    .spp-rankings-display .spp-rankings-table td {
        padding: 8px 12px;
    }

    .spp-ranking-category-card {
        min-width: 150px;
        padding: 15px;
        margin: 5px;
    }
}
</style>

<?php
get_footer();
?>