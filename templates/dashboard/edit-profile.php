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

$positions = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward', 'Guard', 'Center', 'Pitcher', 'Quarterback', 'Other'];
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
                <label for="spp_image_gallery"><?php esc_html_e('Photo Gallery URLs (one per line)', 'sports-player-platform'); ?></label>
                <textarea id="spp_image_gallery" name="image_gallery" rows="3"><?php echo esc_textarea($profile['image_gallery']); ?></textarea>
            </p>
            <p class="spp-form-field">
                <label for="spp_videos_highlights"><?php esc_html_e('Highlight Video URLs (one per line)', 'sports-player-platform'); ?></label>
                <textarea id="spp_videos_highlights" name="videos_highlights" rows="3"><?php echo esc_textarea($profile['videos_highlights']); ?></textarea>
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
