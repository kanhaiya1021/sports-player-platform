<?php
/**
 * Player Profile Template
 * Displays an individual player's public profile page
 *
 * @package Sports Player Platform
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="spp-single-player">
    <?php
    while (have_posts()):
        the_post();
        $player_id = get_the_ID();

        // Track profile view in spp_player_views
        if (class_exists('SPP_Public')) {
            SPP_Public::get_instance()->track_player_view($player_id);
        }

        // Get all player meta fields
        $sport            = (string) get_post_meta($player_id, 'sport', true);
        $position         = (string) get_post_meta($player_id, 'playing_position', true);
        $secondary_pos    = (string) get_post_meta($player_id, 'secondary_position', true);
        $dominant_side    = (string) get_post_meta($player_id, 'dominant_side', true);
        $height           = (string) get_post_meta($player_id, 'height', true);
        $weight           = (string) get_post_meta($player_id, 'weight', true);
        $jersey           = (string) get_post_meta($player_id, 'player_jersey', true);
        $club             = (string) get_post_meta($player_id, 'club', true);
        $location         = (string) get_post_meta($player_id, 'location', true);
        $school           = (string) get_post_meta($player_id, 'school', true);
        $dob              = (string) get_post_meta($player_id, 'date_of_birth', true);
        $grad_year        = (string) get_post_meta($player_id, 'graduation_year', true);
        $gpa              = (string) get_post_meta($player_id, 'gpa', true);
        $sat_act          = (string) get_post_meta($player_id, 'sat_act_scores', true);
        $awards           = (string) get_post_meta($player_id, 'awards', true);
        $recruiting_stat  = (string) get_post_meta($player_id, 'recruiting_status', true);
        $pref_location    = (string) get_post_meta($player_id, 'preferred_location', true);
        $comp_level       = (string) get_post_meta($player_id, 'competition_level', true);
        $athletic_stats   = (string) get_post_meta($player_id, 'athletic_stats', true);
        $recruiting_info  = (string) get_post_meta($player_id, 'recruiting_info', true);
        $gallery          = (string) get_post_meta($player_id, 'image_gallery', true);
        $video_url        = (string) get_post_meta($player_id, 'videos_highlights', true);

        $author_id        = (int) get_post_field('post_author', $player_id);
        $author           = $author_id ? get_userdata($author_id) : null;
        $is_paid          = is_user_logged_in() && (current_user_can('manage_options') || (function_exists('spp_user_has_membership') && spp_user_has_membership(get_current_user_id(), 'paid')));
        $is_coach         = is_user_logged_in() && (current_user_can('create_recruiting_list') || current_user_can('manage_options'));
        $can_save         = is_user_logged_in() && (current_user_can('view_saved_players') || current_user_can('manage_options'));

        // Calculate age from DOB
        $age = '';
        if ($dob !== '') {
            $dob_date = strtotime($dob);
            if ($dob_date) {
                $age = 'Age: ' . date_diff(date_create($dob), date_create('now'))->y;
            }
        }

        // Format competition level for display
        $comp_level_display = $comp_level ? 'Competition Level: ' . $comp_level : '';

        // Format recruiting status color
        $status_class = 'recruiting-status-' . esc_attr($recruiting_stat);
        $status_label = ucfirst($recruiting_stat);
        ?>

        <!-- Player Header -->
        <div class="spp-player-header">
            <div class="spp-player-avatar">
                <?php if (has_post_thumbnail()): ?>
                    <?php the_post_thumbnail('medium', ['class' => 'spp-player-img']); ?>
                <?php else: ?>
                    <div class="spp-player-placeholder"><?php echo esc_html(strtoupper(substr(get_the_title(), 0, 1))); ?></div>
                <?php endif; ?>
            </div>
            <div class="spp-player-basic-info">
                <h1 class="spp-player-name"><?php the_title(); ?></h1>
                <p class="spp-player-subtitle"><?php
                    $subtitle_parts = array_filter([
                        $sport ? ucfirst($sport) : null,
                        $position,
                        $secondary_pos ? '(' . $secondary_pos . ')' : null
                    ]);
                    echo esc_html(implode(' • ', $subtitle_parts));
                ?>
                </p>
                <?php if ($location && $grad_year): ?>
                    <p class="spp-player-location"><?php echo esc_html($location); ?> | Class of <?php echo esc_html($grad_year); ?></p>
                <?php elseif ($location): ?>
                    <p class="spp-player-location"><?php echo esc_html($location); ?></p>
                <?php endif; ?>
                <?php if ($age): ?>
                    <p class="spp-player-age"><?php echo esc_html($age); ?></p>
                <?php endif; ?>
                <?php if ($recruiting_stat): ?>
                    <span class="spp-status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?> Recruiting</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="spp-profile-container">
            <!-- Main Content -->
            <div class="spp-profile-main">
                <div class="spp-box spp-athletic-stats">
                    <h2>Athletic Profile</h2>
                    <div class="spp-stats-grid">
                        <?php if ($height && $weight): ?>
                            <div class="spp-stat-item">
                                <span class="spp-stat-value"><?php echo esc_html($height); ?> cm</span>
                                <span class="spp-stat-label">Height</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($weight): ?>
                            <div class="spp-stat-item">
                                <span class="spp-stat-value"><?php echo esc_html($weight); ?> kg</span>
                                <span class="spp-stat-label">Weight</span>
                            </div>
                        <?php endif; ?> ?>
                        <?php if ($jersey): ?>
                            <div class="spp-stat-item">
                                <span class="spp-stat-value">#<?php echo esc_html($jersey); ?></span>
                                <span class="spp-stat-label">Jersey</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($dominant_side): ?>
                            <div class="spp-stat-item">
                                <span class="spp-stat-value"><?php echo esc_html(ucfirst($dominant_side)); ?></span>
                                <span class="spp-stat-label">Dominant Foot</span>
                            </div>
                        <?php endif; ?> ?>
                        <?php if ($comp_level): ?>
                            <div class="spp-stat-item spp-full-width">
                                <span class="spp-stat-value"><?php echo esc_html($comp_level); ?></span>
                                <span class="spp-stat-label">Competition Level</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php the_content(); ?>

                <?php if ($video_url): ?>
                    <div class="spp-box spp-video-section">
                        <h2>Highlight Video</h2>
                        <div class="spp-video-wrapper">
                            <?php
                            $embed = wp_oembed_get($video_url);
                            echo $embed ? $embed : '<a href="' . esc_url($video_url) . '" target="_blank" rel="noopener">Watch Video on YouTube</a>';
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($awards): ?>
                    <div class="spp-box spp-awards-section">
                        <h2>Awards & Achievements</h2>
                        <ul class="spp-awards-list">
                            <?php foreach (explode("\n", $awards) as $award): ?>
                                <?php if (trim($award)): ?>
                                    <li><?php echo esc_html(trim($award)); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($athletic_stats): ?>
                    <div class="spp-box spp-athletics-section">
                        <h2>Athletic Career Stats</h2>
                        <pre class="spp-athletic-stats-content"><?php echo esc_html($athletic_stats); ?></pre>
                    </div>
                <?php endif; ?>

                <?php if ($gpa || $sat_act || $school || $grad_year): ?>
                    <div class="spp-box spp-academic-section">
                        <h2>Academic Information</h2>
                        <div class="spp-academic-grid">
                            <?php if ($school): ?>
                                <div class="spp-academic-item">
                                    <strong>School / College:</strong><br>
                                    <?php echo esc_html($school); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($grad_year): ?>
                                <div class="spp-academic-item">
                                    <strong>Graduation:</strong><br>
                                    Class of <?php echo esc_html($grad_year); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($gpa): ?>
                                <div class="spp-academic-item">
                                    <strong>GPA:</strong><br>
                                    <?php echo esc_html($gpa); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($sat_act): ?>
                                <div class="spp-academic-item">
                                    <strong>SAT / ACT:</strong><br>
                                    <?php echo esc_html($sat_act); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="spp-profile-sidebar">
                <?php if ($is_paid && $author): ?>
                    <div class="spp-box spp-contact-info">
                        <h3>Contact Info</h3>
                        <p><strong>Email:</strong> <?php echo esc_html($author->user_email); ?></p>
                        <?php if ($recruiting_info): ?>
                            <p><strong>Recruiting:</strong></p>
                            <p><?php echo nl2br(esc_html($recruiting_info)); ?></p>
                        <?php endif; ?>
                        <?php echo do_shortcode('[spp_message_form recipient_id="' . $author_id . '"]'); ?>
                    </div>
                <?php elseif (!$is_paid): ?>
                    <div class="spp-box spp-contact-blur">
                        <h3>Full Contact Info</h3>
                        <p class="spp-blur-text">Contact details available with premium membership.</p>
                        <a href="<?php echo esc_url(home_url('/pricing/')); ?>" class="spp-btn-secondary">View Pricing</a>
                    </div>
                <?php endif; ?>

                <?php if ($can_save): ?>
                    <div class="spp-box spp-save-section">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="spp_save_player">
                            <input type="hidden" name="player_id" value="<?php echo esc_attr($player_id); ?>">
                            <?php wp_nonce_field('spp_save_player_' . $player_id, '_wpnonce'); ?>
                            <button type="submit" class="spp-btn-primary spp-btn-block">
                                Save to Shortlist
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($is_coach && class_exists('SPP_Recruiting')): ?>
                    <?php $coach_lists = SPP_Recruiting::get_instance()->get_recruiting_lists(get_current_user_id()); ?>
                    <?php if (!empty($coach_lists)): ?>
                        <div class="spp-box spp-add-to-list">
                            <h3>Add to Recruiting List</h3>
                            <form id="spp-add-to-list-form">
                                <input type="hidden" name="player_id" value="<?php echo esc_attr($player_id); ?>">
                                <select name="list_id" required>
                                    <option value="">Select List...</option>
                                    <?php foreach ($coach_lists as $cl): ?>
                                        <option value="<?php echo esc_attr($cl['id']); ?>"><?php echo esc_html($cl['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="spp-btn-primary spp-btn-block">Add to List</button>
                                <small id="spp-add-to-list-msg"></small>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($gallery): ?>
                    <?php $gallery_images = array_filter(array_map('trim', explode(',', $gallery))); ?>
                    <?php if (!empty($gallery_images)): ?>
                        <div class="spp-box spp-gallery-section">
                            <h3>Gallery</h3>
                            <div class="spp-gallery-thumbnails">
                                <?php foreach ($gallery_images as $img_url): ?>
                                    <img src="<?php echo esc_url($img_url); ?>" alt="Gallery image" loading="lazy">
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </aside>
        </div>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>

<style>
.spp-single-player {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 15px;
}

.spp-player-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 30px;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.spp-player-avatar {
    flex-shrink: 0;
}

.spp-player-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 48px;
    font-weight: bold;
}

.spp-player-img {
    width: 120px;
    height: auto;
    border-radius: 50%;
    object-fit: cover;
}

.spp-player-basic-info {
    flex: 1;
    min-width: 300px;
}

.spp-player-name {
    font-size: 32px;
    margin: 0 0 8px;
    color: #2c3e50;
}

.spp-player-subtitle {
    font-size: 16px;
    color: #7f8c8d;
    margin: 0 0 8px;
}

.spp-player-location {
    font-size: 14px;
    color: #9ca3af;
    margin: 0 0 8px;
}

.spp-player-age {
    font-size: 14px;
    color: #6b7280;
    margin: 0 0 8px;
}

.spp-status-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.spp-status-badge-open {
    background: #d1fae5;
    color: #065f46;
}

.spp-status-badge-committed {
    background: #dbeafe;
    color: #1e40af;
}

.spp-status-badge-closed {
    background: #fee2e2;
    color: #991b1b;
}

.spp-profile-container {
    display: flex;
    gap: 40px;
    flex-wrap: wrap;
}

.spp-profile-main {
    flex: 1;
    min-width: 300px;
}

.spp-profile-sidebar {
    flex-shrink: 0;
    min-width: 280px;
}

.spp-box {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
}

.spp-box h2 {
    font-size: 18px;
    margin: 0 0 16px;
    color: #1f2937;
    font-weight: 600;
    border-bottom: 1px solid #f3f4f6;
    padding-bottom: 8px;
}

.spp-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 16px;
}

.spp-stat-item {
    text-align: center;
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
}

.spp-stat-item.spp-full-width {
    grid-template-columns: 1fr;
}

.spp-stat-value {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
}

.spp-stat-label {
    display: block;
    font-size: 11px;
    color: #6b7280;
    margin-top: 4px;
    text-transform: uppercase;
}

.spp-awards-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.spp-awards-list li {
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
    color: #374151;
}

.spp-awards-list li:last-child {
    border-bottom: none;
}

.spp-athletic-stats-content {
    background: #111827;
    color: #e5e7eb;
    padding: 16px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    overflow-x: auto;
    line-height: 1.6;
}

.spp-academic-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.spp-academic-item {
    background: #f0fdf4;
    padding: 12px;
    border-radius: 8px;
}

.spp-academic-item strong {
    display: block;
    font-size: 12px;
    color: #065f46;
    margin-bottom: 4px;
}

.spp-contact-blur {
    text-align: center;
}

.spp-blur-text {
    background: linear-gradient(45deg, #e5e7eb, #d1d5db);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    font-size: 14px;
    padding: 12px;
    margin: 12px 0;
    border-radius: 8px;
}

.spp-contact-info p {
    margin: 8px 0;
    font-size: 14px;
}

.spp-contact-info strong {
    color: #374151;
}

.spp-gallery-thumbnails {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.spp-gallery-thumbnails img {
    width: 100%;
    height: 80px;
    object-fit: cover;
    border-radius: 6px;
}

.spp-btn-primary, .spp-btn-secondary {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 500;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.spp-btn-primary {
    background: #3b82f6;
    color: white;
    border: none;
}

.spp-btn-primary:hover {
    background: #2563eb;
}

.spp-btn-secondary {
    background: #6b7280;
    color: white;
    border: none;
}

.spp-btn-secondary:hover {
    background: #4b5563;
}

.spp-btn-block {
    width: 100%;
    text-align: center;
}

.spp-video-wrapper {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    overflow: hidden;
}

.spp-video-wrapper iframe,
.spp-video-wrapper embed {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

@media (max-width: 768px) {
    .spp-single-player {
        padding: 20px 10px;
    }

    .spp-player-header {
        flex-direction: column;
        text-align: center;
    }

    .spp-profile-container {
        flex-direction: column;
    }

    .spp-profile-sidebar {
        min-width: 100%;
    }
}
</style>
<?php
// Enqueue player profile styles if needed
wp_enqueue_style('spp-public-css', SPP_PLUGIN_URL . 'assets/css/public.css', [], SPP_VERSION);
?>