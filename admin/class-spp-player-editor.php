<?php
/**
 * Profile fields on the native Add New Player and Edit Player screens.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SPP_Player_Editor {

    private array $invalid_fields = [];

    public function __construct() {
        add_action('add_meta_boxes_player', [$this, 'add_profile_boxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('save_post_player', [$this, 'save_profile'], 10, 2);
        add_action('admin_notices', [$this, 'show_validation_notice']);
        add_filter('redirect_post_location', [$this, 'add_validation_result']);
        add_filter('enter_title_here', [$this, 'player_name_placeholder'], 10, 2);
    }

    public function add_profile_boxes(WP_Post $post): void {
        remove_meta_box('postcustom', 'player', 'normal');
        foreach (SPP_Player_Profile::sections() as $key => $section) {
            if ($key === 'administration' && !current_user_can('manage_options')) {
                continue;
            }
            add_meta_box('spp-player-' . $key, $section['title'], [$this, 'render_box'], $post->post_type, 'normal', 'high', [
                'section' => $key,
                'fields' => $section['fields'],
            ]);
        }
    }

    public function enqueue_assets(string $hook): void {
        $screen = get_current_screen();
        if (!in_array($hook, ['post-new.php', 'post.php'], true) || !$screen || $screen->post_type !== 'player') {
            return;
        }

        $can_upload = current_user_can('upload_files');
        if ($can_upload) {
            wp_enqueue_media();
        }
        wp_enqueue_style('spp-player-editor', SPP_PLUGIN_URL . 'assets/css/player-editor.css', [], (string) filemtime(SPP_PLUGIN_DIR . 'assets/css/player-editor.css'));
        wp_enqueue_script('spp-player-editor', SPP_PLUGIN_URL . 'assets/js/player-editor.js', $can_upload ? ['media-editor'] : [], (string) filemtime(SPP_PLUGIN_DIR . 'assets/js/player-editor.js'), true);
        wp_localize_script('spp-player-editor', 'sppPlayerEditor', [
            'galleryTitle' => __('Select player gallery photos', 'sports-player-platform'),
            'galleryButton' => __('Add to gallery', 'sports-player-platform'),
            'removePhoto' => __('Remove photo %d', 'sports-player-platform'),
            'dominantHand' => __('Dominant Hand', 'sports-player-platform'),
            'dominantFoot' => __('Dominant Foot', 'sports-player-platform'),
            'dominantSide' => __('Dominant Hand / Foot', 'sports-player-platform'),
            'statSportAliases' => SPP_Player_Profile::STAT_SPORT_ALIASES,
        ]);
    }

    public function player_name_placeholder(string $label, WP_Post $post): string {
        return $post->post_type === 'player' ? __('Enter player name', 'sports-player-platform') : $label;
    }

    public function render_box(WP_Post $post, array $box): void {
        $section = $box['args']['section'];
        if ($section === 'personal') {
            wp_nonce_field('spp_player_profile_' . $post->ID, 'spp_player_profile_nonce', false);
        }
        if ($section === 'media') {
            echo '<p class="description spp-player-media-help">' . esc_html__('Set the main profile photo in the Featured Image panel. Use the gallery for additional photos.', 'sports-player-platform') . '</p>';
        }
        ?>
        <div class="spp-player-fields">
            <?php foreach ($box['args']['fields'] as $key => $field): ?>
                <?php $this->render_field($post->ID, $key, $field); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_field(int $post_id, string $key, array $field): void {
        $id = 'spp-player-field-' . $key;
        $name = 'spp_player_profile[' . $key . ']';
        $value = SPP_Player_Profile::get($post_id, $key);
        $type = $field['type'] ?? 'text';
        if (in_array($type, ['teams', 'stats'], true)) {
            $this->render_seasons($post_id, $name, $field['label'], $type, is_array($value) ? $value : []);
            return;
        }
        if ($type === 'contact') {
            $this->render_contact($name, $field['label'], is_array($value) ? $value : []);
            return;
        }
        $value = is_scalar($value) ? (string) $value : '';
        $multiline = in_array($type, ['textarea', 'urls'], true);
        $is_preference = in_array($key, ['preferred_location', 'competition_level'], true);
        $hint = $type === 'urls' ? __('Enter one HTTP or HTTPS URL per line.', 'sports-player-platform') : ($field['hint'] ?? '');
        if ($type === 'date' && $value !== '') {
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) ? date_create_immutable_from_format('!Y-m-d', $value) : false;
            if (!$date || $date->format('Y-m-d') !== $value || $value > wp_date('Y-m-d')) {
                $type = 'text';
                $hint = __('Enter the date in YYYY-MM-DD format.', 'sports-player-platform');
            }
        }
        ?>
        <div class="spp-player-field <?php echo $multiline ? 'spp-player-field--wide' : ''; ?>"<?php if ($is_preference): ?> data-spp-recruiting-preference<?php endif; ?>>
            <label for="<?php echo esc_attr($id); ?>">
                <?php if ($key === 'dominant_side'): ?>
                    <span data-spp-dominant-side><?php echo esc_html($field['label']); ?></span>
                <?php else: ?>
                    <?php echo esc_html($field['label']); ?>
                <?php endif; ?>
                <?php if (empty($field['public'])): ?>
                    <span class="spp-player-field-privacy"><?php esc_html_e('Private', 'sports-player-platform'); ?></span>
                <?php endif; ?>
            </label>
            <?php if ($key === 'image_gallery' && current_user_can('upload_files')): ?>
                <button type="button" class="button spp-player-gallery-picker" data-spp-gallery-picker>
                    <?php esc_html_e('Add Photos from Media Library', 'sports-player-platform'); ?>
                </button>
            <?php endif; ?>
            <?php if ($multiline): ?>
                <textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" rows="3"<?php if ($hint !== ''): ?> aria-describedby="<?php echo esc_attr($id . '-hint'); ?>"<?php endif; ?>><?php echo esc_textarea($value); ?></textarea>
            <?php elseif ($type === 'select'): ?>
                <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>">
                    <option value=""><?php esc_html_e('Select an option', 'sports-player-platform'); ?></option>
                    <?php foreach ($field['options'] as $option => $label): ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($value, $option); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($type === 'account'): ?>
                <?php
                wp_dropdown_users([
                    'id' => $id,
                    'name' => $name,
                    'role' => SPP_Auth::ROLE_PLAYER,
                    'show' => 'display_name_with_login',
                    'show_option_none' => __('No linked account', 'sports-player-platform'),
                    'option_none_value' => '',
                    'selected' => (int) $value,
                    'include_selected' => true,
                ]);
                ?>
                <p class="description"><?php esc_html_e('The linked account can edit this profile from its dashboard. Each account can be linked to one profile.', 'sports-player-platform'); ?></p>
            <?php else: ?>
                <input
                    id="<?php echo esc_attr($id); ?>"
                    name="<?php echo esc_attr($name); ?>"
                    type="<?php echo esc_attr(in_array($type, ['date', 'url'], true) ? $type : 'text'); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    <?php if ($type === 'date'): ?>max="<?php echo esc_attr(wp_date('Y-m-d')); ?>"<?php endif; ?>
                    <?php if ($type === 'year'): ?>inputmode="numeric"<?php endif; ?>
                    <?php if ($type === 'decimal'): ?>inputmode="decimal"<?php endif; ?>
                    <?php if ($hint !== ''): ?>aria-describedby="<?php echo esc_attr($id . '-hint'); ?>"<?php endif; ?>
                >
            <?php endif; ?>
            <?php if ($hint !== ''): ?>
                <p id="<?php echo esc_attr($id . '-hint'); ?>" class="description"><?php echo esc_html($hint); ?></p>
            <?php endif; ?>
            <?php if ($key === 'image_gallery'): ?>
                <ul class="spp-player-gallery" data-spp-gallery aria-label="<?php esc_attr_e('Gallery previews', 'sports-player-platform'); ?>" hidden></ul>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_seasons(int $post_id, string $name, string $label, string $type, array $rows): void {
        $teams = [];
        if ($type === 'teams') {
            $posts = get_posts([
                'post_type' => 'team',
                'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
                'orderby' => 'title',
                'order' => 'ASC',
                'posts_per_page' => -1,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);
            foreach ($posts as $team) {
                if (current_user_can('read_post', $team->ID)) {
                    $teams[$team->ID] = get_the_title($team);
                }
            }
        }
        $sports = SPP_Player_Profile::stat_sports();
        $default_sport = SPP_Player_Profile::stat_sport((string) SPP_Player_Profile::get($post_id, 'sport'));
        require SPP_PLUGIN_DIR . 'admin/views/player-seasons.php';
    }

    private function render_contact(string $name, string $label, array $contact): void {
        $options = [
            'guardian' => __('Parent / Guardian', 'sports-player-platform'),
            'coach' => __('Coach', 'sports-player-platform'),
            'player' => __('Adult Player', 'sports-player-platform'),
        ];
        ?>
        <fieldset class="spp-player-field spp-player-field--wide spp-player-group">
            <legend><?php echo esc_html($label); ?> <span class="spp-player-field-privacy"><?php esc_html_e('Private', 'sports-player-platform'); ?></span></legend>
            <p class="description"><?php esc_html_e('Use a guardian or coach for junior players. Add an email address or phone number.', 'sports-player-platform'); ?></p>
            <div class="spp-player-fields">
                <div class="spp-player-field">
                    <label for="spp-contact-role"><?php esc_html_e('Contact Role', 'sports-player-platform'); ?></label>
                    <select id="spp-contact-role" name="<?php echo esc_attr($name . '[role]'); ?>">
                        <option value=""><?php esc_html_e('No contact', 'sports-player-platform'); ?></option>
                        <?php foreach ($options as $role => $title): ?>
                            <option value="<?php echo esc_attr($role); ?>" <?php selected($contact['role'] ?? '', $role); ?>><?php echo esc_html($title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php foreach (['name' => __('Contact Name', 'sports-player-platform'), 'email' => __('Contact Email', 'sports-player-platform'), 'phone' => __('Contact Phone', 'sports-player-platform')] as $part => $title): ?>
                    <div class="spp-player-field">
                        <label for="spp-contact-<?php echo esc_attr($part); ?>"><?php echo esc_html($title); ?></label>
                        <input id="spp-contact-<?php echo esc_attr($part); ?>" type="<?php echo esc_attr(['email' => 'email', 'phone' => 'tel'][$part] ?? 'text'); ?>"
                            name="<?php echo esc_attr($name . '[' . $part . ']'); ?>" value="<?php echo esc_attr($contact[$part] ?? ''); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php
    }

    public function save_profile(int $post_id, WP_Post $post): void {
        $nonce = $_POST['spp_player_profile_nonce'] ?? '';
        $fields = $_POST['spp_player_profile'] ?? null;
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || in_array($post->post_status, ['auto-draft', 'trash'], true)
            || !is_string($nonce)
            || !is_array($fields)
            || absint($_POST['post_ID'] ?? 0) !== $post_id
            || !current_user_can('edit_post', $post_id)
            || !wp_verify_nonce(wp_unslash($nonce), 'spp_player_profile_' . $post_id)) {
            return;
        }

        $this->invalid_fields = SPP_Player_Profile::save($post_id, wp_unslash($fields));
    }

    public function add_validation_result(string $url): string {
        if (!$this->invalid_fields) {
            return $url;
        }

        return add_query_arg('spp_player_invalid', implode(',', $this->invalid_fields), $url);
    }

    public function show_validation_notice(): void {
        $screen = get_current_screen();
        $invalid = $_GET['spp_player_invalid'] ?? '';
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'player' || !is_string($invalid) || $invalid === '') {
            return;
        }

        $keys = explode(',', sanitize_text_field(wp_unslash($invalid)));
        $fields = array_intersect_key(SPP_Player_Profile::fields(), array_flip($keys));
        if ($fields) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html(sprintf(
                    /* translators: %s: comma-separated field names. */
                    __('Check these fields. The new values could not be saved: %s.', 'sports-player-platform'),
                    implode(', ', array_column($fields, 'label'))
                ))
            );
        }
    }
}
