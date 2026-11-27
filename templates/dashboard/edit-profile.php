<?php
/**
 * Edit Player Profile Template (Tabbed AJAX Form)
 *
 * @var int          $user_id
 * @var WP_Post|null $player_post
 * @var array        $profile
 * @var int          $completion
 * @var string       $active_tab
 */

if (!defined('ABSPATH')) {
    exit;
}

$positions = ['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center', 'Guard', 'Forward', 'Other'];
?>
<div class="spp-dashboard-wrap">
    <?php include SPP_PLUGIN_DIR . 'templates/dashboard/nav.php'; ?>

    <div class="spp-profile-header">
        <h2><?php esc_html_e('Edit Player Profile', 'sports-player-platform'); ?></h2>
        <div class="spp-completion-wrap">
            <span><?php esc_html_e('Profile Completion:', 'sports-player-platform'); ?> <strong id="spp-completion-pct"><?php echo esc_html((string) $completion); ?>%</strong></span>
            <div class="spp-progress-bar">
                <div class="spp-progress-fill" id="spp-completion-bar" style="width: <?php echo esc_attr((string) $completion); ?>%;"></div>
            </div>
        </div>
    </div>

    <?php if ($profile['status'] === 'spp_pending'): ?>
        <div class="spp-notice spp-notice-info">
            <p><?php esc_html_e('Your profile is currently pending admin approval.', 'sports-player-platform'); ?></p>
        </div>
    <?php endif; ?>

    <div id="spp-profile-feedback" class="spp-notice" style="display:none;"></div>

    <div class="spp-tabs" role="tablist">
        <button type="button" class="spp-tab-btn is-active" data-tab="personal"><?php esc_html_e('1. Personal', 'sports-player-platform'); ?></button>
        <button type="button" class="spp-tab-btn" data-tab="athletic"><?php esc_html_e('2. Athletic', 'sports-player-platform'); ?></button>
        <button type="button" class="spp-tab-btn" data-tab="media"><?php esc_html_e('3. Media', 'sports-player-platform'); ?></button>
        <button type="button" class="spp-tab-btn" data-tab="academic"><?php esc_html_e('4. Academic', 'sports-player-platform'); ?></button>
    </div>

    <form id="spp-edit-profile-form" class="spp-form">
        <!-- Tab 1: Personal -->
        <div class="spp-tab-panel is-active" data-panel="personal">
            <p class="spp-form-field">
                <label for="spp_player_name"><?php esc_html_e('Full Name *', 'sports-player-platform'); ?></label>
                <input type="text" id="spp_player_name" name="player_name" value="<?php echo esc_attr($profile['player_name']); ?>" required>
            </p>
            <p class="spp-form-field">
                <label for="spp_dob"><?php esc_html_e('Date of Birth (private)', 'sports-player-platform'); ?></label>
                <input type="date" id="spp_dob" name="date_of_birth" value="<?php echo esc_attr($profile['date_of_birth']); ?>">
            </p>
            <p class="spp-form-field">
                <label for="spp_location"><?php esc_html_e('Location (City, State)', 'sports-player-platform'); ?></label>
                <input type="text" id="spp_location" name="location" value="<?php echo esc_attr($profile['location']); ?>">
            </p>
            <p class="spp-form-field">
                <label for="spp_avatar_url"><?php esc_html_e('Profile Photo URL', 'sports-player-platform'); ?></label>
                <input type="url" id="spp_avatar_url" name="avatar_url" value="<?php echo esc_attr($profile['avatar_url']); ?>" placeholder="https://...">
            </p>
            <p class="spp-form-field">
                <label for="spp_bio"><?php esc_html_e('Player Bio', 'sports-player-platform'); ?></label>
                <textarea id="spp_bio" name="bio" rows="4"><?php echo esc_textarea($profile['bio']); ?></textarea>
            </p>
        </div>

        <!-- Tab 2: Athletic -->
        <div class="spp-tab-panel" data-panel="athletic">
            <p class="spp-form-field">
                <label for="spp_sport"><?php esc_html_e('Primary Sport', 'sports-player-platform'); ?></label>
                <input type="text" id="spp_sport" name="sport" value="<?php echo esc_attr($profile['sport']); ?>">
            </p>
            <p class="spp-form-field">
                <label for="spp_position"><?php esc_html_e('Playing Position', 'sports-player-platform'); ?></label>
                <input type="text" id="spp_position" name="playing_position" list="spp_positions" value="<?php echo esc_attr($profile['playing_position']); ?>">
                <datalist id="spp_positions">
                    <?php foreach ($positions as $pos): ?>
                        <option value="<?php echo esc_attr($pos); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </p>
            <div class="spp-form-row">
                <p class="spp-form-field">
                    <label for="spp_height"><?php esc_html_e('Height (cm)', 'sports-player-platform'); ?></label>
                    <input type="text" id="spp_height" name="height" value="<?php echo esc_attr($profile['height']); ?>">
                </p>
                <p class="spp-form-field">
                    <label for="spp_weight"><?php esc_html_e('Weight (kg)', 'sports-player-platform'); ?></label>
                    <input type="text" id="spp_weight" name="weight" value="<?php echo esc_attr($profile['weight']); ?>">
                </p>
            </div>
            <div class="spp-form-row">
                <p class="spp-form-field">
                    <label for="spp_grad_year"><?php esc_html_e('Graduation Year', 'sports-player-platform'); ?></label>
                    <input type="number" id="spp_grad_year" name="graduation_year" min="1900" max="2100" value="<?php echo esc_attr($profile['graduation_year']); ?>">
                </p>
                <p class="spp-form-field">
                    <label for="spp_school"><?php esc_html_e('School / Club', 'sports-player-platform'); ?></label>
                    <input type="text" id="spp_school" name="school" value="<?php echo esc_attr($profile['school']); ?>">
                </p>
            </div>
        </div>

        <!-- Tab 3: Media -->
        <div class="spp-tab-panel" data-panel="media">
            <p class="spp-form-field">
                <label><?php esc_html_e('Gallery Images', 'sports-player-platform'); ?></label>
                <p style="font-size:13px; color:#6b7280; margin:4px 0 12px;"><?php esc_html_e('Add image URLs or upload up to 6 images (supports multiple files).', 'sports-player-platform'); ?></p>

                <input type="hidden" id="spp_image_count" name="image_count" value="0">
                <div id="spp-image-gallery-wrap" class="spp-image-grid">
                    <?php
                    $image_urls = explode("\n", trim($profile['image_gallery'] ?? ''));
                    $image_urls = array_filter(array_map('trim', $image_urls));
                    foreach ($image_urls as $idx => $url):
                    ?>
                        <div class="spp-image-card" data-url="<?php echo esc_attr($url); ?>" style="position: relative; padding: 12px; background: #fff; border: 1px solid var(--spp-border); border-radius: 8px; display: flex; gap: 12px; align-items: center;">
                            <div class="spp-image-preview" style="background-image: url('<?php echo esc_url($url); ?>'); width: 60px; height: 60px; border-radius: 6px;"></div>
                            <input type="url" name="image_gallery[]" value="<?php echo esc_attr($url); ?>" class="spp-image-url" placeholder="Image URL" style="flex: 1;">
                            <button type="button" class="spp-image-remove" aria-label="Remove" style="position: absolute; top: 8px; right: 8px;">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" id="spp-add-image-gallery" class="spp-btn spp-btn-small spp-btn-secondary" style="margin-top: 12px;">
                    <?php esc_html_e('Add Image URL', 'sports-player-platform'); ?>
                </button>
            </p>

            <div style="margin-top: 16px; padding: 16px; border: 2px dashed var(--spp-border); border-radius: 8px; background: #fff;">
                <label for="spp_image_upload" style="font-weight: 600; display: block; margin-bottom: 8px; color: #374151;"><?php esc_html_e('Upload Image Files', 'sports-player-platform'); ?></label>
                <input type="file" id="spp_image_upload" name="image_gallery_upload[]" accept="image/*" multiple style="width: 100%; margin-bottom: 8px;">
                <div id="spp-image-preview-container" style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;"></div>
                <p style="font-size: 12px; color: #6b7280; margin: 0;"><?php esc_html_e('Supports JPG, PNG, GIF. Max 6 images total. You can select multiple files at once.', 'sports-player-platform'); ?></p>
            </div>

            <p class="spp-form-field" style="margin-top: 20px;">
                <label for="spp_video_urls"><?php esc_html_e('Highlight Videos', 'sports-player-platform'); ?></label>
                <p style="font-size:13px; color:#6b7280; margin:4px 0 8px;"><?php esc_html_e('Add video URLs or upload up to 6 video files (supports multiple files):', 'sports-player-platform'); ?></p>
                <div id="spp-video-gallery-wrap" class="spp-url-fields">
                    <?php
                    $video_urls = explode("\n", trim($profile['videos_highlights'] ?? ''));
                    $video_urls = array_filter(array_map('trim', $video_urls));
                    foreach ($video_urls as $idx => $url):
                    ?>
                        <div class="spp-url-field">
                            <input type="url" name="videos_highlights[]" value="<?php echo esc_attr($url); ?>" placeholder="https://youtube.com/watch?v=..." class="spp-url-input">
                            <button type="button" class="spp-url-remove" aria-label="Remove">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="spp-add-video-gallery" class="spp-btn spp-btn-small spp-btn-secondary" style="margin-top: 8px;">
                    <?php esc_html_e('Add Video URL', 'sports-player-platform'); ?>
                </button>
                <div style="margin-top: 12px; padding: 12px; border: 1px solid var(--spp-border); border-radius: 8px; background: #f9fafb;">
                    <label for="spp_video_upload" style="font-weight: 600; display: block; margin-bottom: 8px;"><?php esc_html_e('Upload Video File', 'sports-player-platform'); ?></label>
                    <input type="file" id="spp_video_upload" name="video_gallery_upload[]" accept="video/*" multiple style="width: 100%;">
                    <p style="font-size: 12px; color: #6b7280; margin: 4px 0 0;"><?php esc_html_e('Supports MP4, MOV, AVI, WEBM. Max 6 videos total. Local files will be uploaded to media library.', 'sports-player-platform'); ?></p>
                </div>
            </p>
        </div>

        <!-- Tab 4: Academic -->
        <div class="spp-tab-panel" data-panel="academic">
            <div class="spp-form-row">
                <p class="spp-form-field">
                    <label for="spp_gpa"><?php esc_html_e('GPA', 'sports-player-platform'); ?></label>
                    <input type="text" id="spp_gpa" name="gpa" value="<?php echo esc_attr($profile['gpa']); ?>" placeholder="e.g., 3.8">
                </p>
                <p class="spp-form-field">
                    <label for="spp_sat_act"><?php esc_html_e('SAT / ACT Score', 'sports-player-platform'); ?></label>
                    <input type="text" id="spp_sat_act" name="sat_act_scores" value="<?php echo esc_attr($profile['sat_act_scores']); ?>">
                </p>
            </div>
            <p class="spp-form-field">
                <label for="spp_awards"><?php esc_html_e('Awards & Achievements', 'sports-player-platform'); ?></label>
                <textarea id="spp_awards" name="awards" rows="4"><?php echo esc_textarea($profile['awards']); ?></textarea>
            </p>
        </div>

        <p class="spp-form-submit">
            <button type="submit" id="spp-save-profile-btn" class="spp-btn spp-btn-primary">
                <?php esc_html_e('Save & Submit for Review', 'sports-player-platform'); ?>
            </button>
        </p>
    </form>
</div>
