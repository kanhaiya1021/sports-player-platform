<?php
/**
 * Single Player Profile Template (/players/{slug}/)
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()):
    the_post();
    $player_id = get_the_ID();

    // Track profile view in spp_player_views
    if (class_exists('SPP_Public')) {
        SPP_Public::get_instance()->track_player_view($player_id);
    }

    $sport      = (string) get_post_meta($player_id, 'sport', true);
    $position   = class_exists('SPP_Player_Profile') ? (string) SPP_Player_Profile::get($player_id, 'playing_position') : (string) get_post_meta($player_id, 'playing_position', true);
    $height     = class_exists('SPP_Player_Profile') ? (string) SPP_Player_Profile::get($player_id, 'height') : (string) get_post_meta($player_id, 'height', true);
    $weight     = class_exists('SPP_Player_Profile') ? (string) SPP_Player_Profile::get($player_id, 'weight') : (string) get_post_meta($player_id, 'weight', true);
    $school     = (string) get_post_meta($player_id, 'school', true);
    $grad_year  = (string) get_post_meta($player_id, 'graduation_year', true);
    $location   = (string) get_post_meta($player_id, 'location', true);
    $gallery    = (string) get_post_meta($player_id, 'image_gallery', true);
    $video_url  = (string) get_post_meta($player_id, 'videos_highlights', true);
    $gpa        = (string) get_post_meta($player_id, 'gpa', true);
    $sat_act    = (string) get_post_meta($player_id, 'sat_act_scores', true);
    $awards     = (string) get_post_meta($player_id, 'awards', true);
    $avatar_url = class_exists('SPP_Player_Profile') ? (string) SPP_Player_Profile::get($player_id, 'avatar_url') : (string) get_post_meta($player_id, 'avatar_url', true);

    $author_id  = (int) get_post_field('post_author', $player_id);
    $author     = $author_id ? get_userdata($author_id) : null;
    $is_paid    = is_user_logged_in() && (current_user_can('manage_options') || (function_exists('spp_user_has_membership') && spp_user_has_membership(get_current_user_id(), 'paid')));
    $is_coach   = is_user_logged_in() && (current_user_can('create_recruiting_list') || current_user_can('manage_options'));
    $can_save   = is_user_logged_in() && (current_user_can('view_saved_players') || current_user_can('manage_options'));
    ?>
    <main class="spp-public-wrap spp-single-player">
        <!-- Hero -->
        <section class="spp-player-hero">
            <div class="spp-hero-avatar">
                <?php if (has_post_thumbnail()): ?>
                    <?php the_post_thumbnail('medium', ['class' => 'spp-hero-img']); ?>
                <?php elseif ($avatar_url !== ''): ?>
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php the_title_attribute(); ?>" class="spp-hero-img">
                <?php else: ?>
                    <div class="spp-player-placeholder spp-hero-placeholder"><?php echo esc_html(strtoupper(substr(get_the_title(), 0, 1))); ?></div>
                <?php endif; ?>
            </div>
            <div class="spp-hero-info">
                <h1><?php the_title(); ?></h1>
                <p class="spp-hero-sub">
                    <?php echo esc_html(implode(' • ', array_filter([$sport, $position, $school]))); ?>
                </p>
                <?php if ($location !== '' || $grad_year !== ''): ?>
                    <p class="spp-hero-meta">
                        <?php echo esc_html(implode(' | ', array_filter([$location, $grad_year ? 'Class of ' . $grad_year : '']))); ?>
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <div class="spp-profile-layout">
            <!-- Stats Sidebar -->
            <aside class="spp-stats-sidebar">
                <div class="spp-box">
                    <h3><?php esc_html_e('Athletic Profile', 'sports-player-platform'); ?></h3>
                    <dl class="spp-stats-list">
                        <?php if ($height !== ''): ?><dt><?php esc_html_e('Height', 'sports-player-platform'); ?></dt><dd><?php echo esc_html($height); ?> cm</dd><?php endif; ?>
                        <?php if ($weight !== ''): ?><dt><?php esc_html_e('Weight', 'sports-player-platform'); ?></dt><dd><?php echo esc_html($weight); ?> kg</dd><?php endif; ?>
                        <?php if ($position !== ''): ?><dt><?php esc_html_e('Position', 'sports-player-platform'); ?></dt><dd><?php echo esc_html($position); ?></dd><?php endif; ?>
                        <?php if ($school !== ''): ?><dt><?php esc_html_e('School / Club', 'sports-player-platform'); ?></dt><dd><?php echo esc_html($school); ?></dd><?php endif; ?>
                        <?php if ($grad_year !== ''): ?><dt><?php esc_html_e('Grad Year', 'sports-player-platform'); ?></dt><dd><?php echo esc_html($grad_year); ?></dd><?php endif; ?>
                    </dl>
                </div>

                <!-- Contact Box (Paid Gated) -->
                <div class="spp-box spp-contact-box">
                    <h3><?php esc_html_e('Contact & Recruiting', 'sports-player-platform'); ?></h3>
                    <?php if ($is_paid && $author): ?>
                        <p><strong><?php esc_html_e('Email:', 'sports-player-platform'); ?></strong> <?php echo esc_html($author->user_email); ?></p>
                        <?php echo do_shortcode('[spp_message_form recipient_id="' . $author_id . '"]'); ?>
                    <?php else: ?>
                        <div class="spp-blurred-contact">
                            <p class="spp-blur-text">player.contact@example.com • (555) 123-4567</p>
                            <a href="<?php echo esc_url(home_url('/pricing/')); ?>" class="spp-btn-primary">
                                <?php esc_html_e('Upgrade to View Contact Info', 'sports-player-platform'); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($can_save): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                            <input type="hidden" name="action" value="spp_save_player">
                            <input type="hidden" name="player_id" value="<?php echo esc_attr((string) $player_id); ?>">
                            <?php wp_nonce_field('spp_save_player_' . $player_id, '_wpnonce'); ?>
                            <button type="submit" class="spp-btn-secondary" style="width:100%;">
                                <?php esc_html_e('Save to Shortlist', 'sports-player-platform'); ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($is_coach && class_exists('SPP_Recruiting')): ?>
                        <?php $coach_lists = SPP_Recruiting::get_instance()->get_recruiting_lists(get_current_user_id()); ?>
                        <?php if (!empty($coach_lists)): ?>
                            <form id="spp-add-to-list-form" style="margin-top:12px;">
                                <input type="hidden" name="player_id" value="<?php echo esc_attr((string) $player_id); ?>">
                                <select name="list_id" required style="width:100%; margin-bottom:8px; padding:6px;">
                                    <option value=""><?php esc_html_e('Select Recruiting List...', 'sports-player-platform'); ?></option>
                                    <?php foreach ($coach_lists as $cl): ?>
                                        <option value="<?php echo esc_attr((string) $cl['id']); ?>"><?php echo esc_html($cl['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="spp-btn-primary" style="width:100%;"><?php esc_html_e('Add to Recruiting List', 'sports-player-platform'); ?></button>
                                <small id="spp-add-to-list-msg" style="display:block; margin-top:4px;"></small>
                            </form>
                        <?php endif; ?>
                        <p style="margin-top:8px;">
                            <a href="<?php echo esc_url(home_url('/dashboard/recruiting/')); ?>"><?php esc_html_e('Manage Recruiting Lists →', 'sports-player-platform'); ?></a>
                        </p>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- Main Content -->
            <section class="spp-profile-main">
                <div class="spp-box">
                    <h2><?php esc_html_e('About', 'sports-player-platform'); ?></h2>
                    <div class="spp-bio-content">
                        <?php the_content(); ?>
                    </div>
                </div>

                <?php if ($video_url !== ''): ?>
                    <div class="spp-box">
                        <h2><?php esc_html_e('Highlight Video', 'sports-player-platform'); ?></h2>
                        <div class="spp-video-embed">
                            <?php
                            $embed = wp_oembed_get($video_url);
                            echo $embed ? $embed : '<a href="' . esc_url($video_url) . '" target="_blank" rel="noopener">' . esc_html($video_url) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($gallery !== ''): ?>
                    <?php $urls = array_filter(array_map('trim', explode(',', $gallery))); ?>
                    <?php if (!empty($urls)): ?>
                        <div class="spp-box">
                            <h2><?php esc_html_e('Photo Gallery', 'sports-player-platform'); ?></h2>
                            <div class="spp-gallery-grid">
                                <?php foreach ($urls as $img_url): ?>
                                    <img src="<?php echo esc_url($img_url); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($gpa !== '' || $sat_act !== '' || $awards !== ''): ?>
                    <div class="spp-box">
                        <h2><?php esc_html_e('Academic Info & Awards', 'sports-player-platform'); ?></h2>
                        <?php if ($gpa !== ''): ?><p><strong><?php esc_html_e('GPA:', 'sports-player-platform'); ?></strong> <?php echo esc_html($gpa); ?></p><?php endif; ?>
                        <?php if ($sat_act !== ''): ?><p><strong><?php esc_html_e('SAT / ACT:', 'sports-player-platform'); ?></strong> <?php echo esc_html($sat_act); ?></p><?php endif; ?>
                        <?php if ($awards !== ''): ?><p><?php echo nl2br(esc_html($awards)); ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <?php
endwhile;

get_footer();
