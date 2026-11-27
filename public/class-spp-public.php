<?php
/**
 * Public class for Sports Player Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Public')) {

    final class SPP_Public {

        /**
         * Public instance
         *
         * @var SPP_Public|null
         */
        private static $instance = null;

        /**
         * Post type definitions
         */
        const POST_TYPES = SPP_Content_Types::POST_TYPES;

        /**
         * Get public instance
         *
         * @return SPP_Public
         */
        public static function get_instance(): self {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Private constructor to prevent direct instantiation
         */
        private function __construct() {
            $this->init_hooks();
        }

        /**
         * Initialize WordPress hooks
         */
        private function init_hooks(): void {
            // Content types are registered by the plugin lifecycle.

            // Register approval/rejection actions
            add_action('admin_post_spp_approve_profile', [$this, 'handle_approve_profile']);
            add_action('admin_post_spp_reject_profile', [$this, 'handle_reject_profile']);

            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_shortcode('spp_players', [$this, 'render_players_shortcode']);
            add_shortcode('spp_rankings', [$this, 'render_rankings_shortcode']);
            add_shortcode('spp_teams', [$this, 'render_teams_shortcode']);
            add_shortcode('spp_events', [$this, 'render_events_embed']);
            add_shortcode('spp_articles', function ($atts): string {
                $atts = (array) $atts;
                return $this->render_articles_list(max(1, min(100, (int) ($atts['limit'] ?? 10))));
            });
            add_filter('query_vars', [$this, 'add_query_vars']);
            add_action('template_redirect', [$this, 'template_redirect']);
            add_filter('template_include', [$this, 'load_custom_templates']);
            add_action('wp_ajax_spp_filter_players', [$this, 'ajax_filter_players']);
            add_action('wp_ajax_nopriv_spp_filter_players', [$this, 'ajax_filter_players']);

            // Add rewrite rules for custom pages
            add_action('init', [$this, 'add_rewrite_rules']);
        }

        /**
         * Add rewrite rules for custom public pages
         */
        public function add_rewrite_rules(): void {
            add_rewrite_rule(
                '^rankings/?$',
                'index.php?pagename=rankings',
                'top'
            );
        }

        /**
         * Register custom post status
         */
        public function register_post_status(): void {
            SPP_Content_Types::register_statuses();
        }


        /**
         * Enqueue public-facing scripts
         */
        public function enqueue_scripts(): void {
            wp_enqueue_style(
                'spp-public-css',
                SPP_PLUGIN_URL . 'assets/css/public.css',
                [],
                SPP_VERSION
            );

            wp_enqueue_script(
                'spp-public-js',
                SPP_PLUGIN_URL . 'assets/js/public.js',
                [],
                SPP_VERSION,
                true
            );

            wp_localize_script('spp-public-js', 'SPP_Public', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('spp_public_nonce'),
            ]);
        }

        /**
         * Add custom query vars
         *
         * @param array $vars
         * @return array
         */
        public function add_query_vars(array $vars): array {
            $vars[] = 'spp_action';
            $vars[] = 'spp_id';
            return $vars;
        }

        /**
         * Handle template redirects
         */
        public function template_redirect(): void {
            global $wp_query;

            // Custom template handling for player profiles
            if (isset($wp_query->query_vars['spp_action']) && $wp_query->query_vars['spp_action'] === 'player_profile') {
                $this->render_player_profile($wp_query->query_vars['spp_id']);
            }

            // AJAX search and filtering
            if (isset($wp_query->query_vars['spp_action']) && $wp_query->query_vars['spp_action'] === 'spp_search_players') {
                $this->spp_search_players();
            }
        }

        /**
         * Handle profile approval
         */
        public function handle_approve_profile(): void {
            $this->handle_profile_review('publish');
        }

        /**
         * Handle profile rejection
         */
        public function handle_reject_profile(): void {
            $this->handle_profile_review('rejected');
        }

        private function handle_profile_review(string $status): void {
            check_admin_referer('spp_ajax_nonce', 'nonce');
            $result = $this->review_player_profile(absint($_POST['profile_id'] ?? 0), $status);
            if (is_wp_error($result)) {
                wp_die(esc_html($result->get_error_message()), '', ['response' => $result->get_error_data()]);
            }

            wp_safe_redirect(admin_url('admin.php?page=spp-profile-management&spp_status=' . $result));
            exit;
        }

        public function review_player_profile(int $profile_id, string $status): string|WP_Error {
            if (!in_array($status, ['publish', 'rejected'], true)) {
                return new WP_Error('spp_invalid_status', __('Invalid profile status.', 'sports-player-platform'), 400);
            }

            $post = $profile_id > 0 ? get_post($profile_id) : null;
            if (!$post || $post->post_type !== 'player') {
                return new WP_Error('spp_profile_not_found', __('Player profile not found.', 'sports-player-platform'), 404);
            }

            if (!current_user_can('publish_posts') || !current_user_can('edit_post', $profile_id)) {
                return new WP_Error('spp_review_forbidden', __('You cannot review this profile.', 'sports-player-platform'), 403);
            }

            if ($post->post_status !== 'spp_pending') {
                return new WP_Error('spp_profile_not_pending', __('This profile is no longer pending review.', 'sports-player-platform'), 409);
            }

            $updated = wp_update_post(['ID' => $profile_id, 'post_status' => $status], true);
            if (is_wp_error($updated) || !$updated) {
                return new WP_Error('spp_review_failed', __('Could not update the profile.', 'sports-player-platform'), 500);
            }

            $outcome = $status === 'publish' ? 'approved' : 'rejected';
            do_action('spp_profile_' . $outcome, $profile_id);
            return $outcome;
        }

        /**
         * Render player profile
         *
         * @param int $player_id
         */
        private function render_player_profile(int $player_id): void {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_players';
            $player = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $player_id));

            if (!$player) {
                wp_die(__('Player not found', 'sports-player-platform'));
            }

            locate_template(['spp/player-profile.php']);
        }

        /**
         * Create new player profile with pending status
         *
         * @param int $author_id
         * @return WP_Error|void
         */
        public function create_player_profile(int $author_id = null): void {
            $author_id = $author_id ?? get_current_user_id();

            if (!$author_id || !current_user_can('edit_posts')) {
                wp_die(__('Unauthorized', 'sports-player-platform'));
            }

            $post_id = wp_insert_post([
                'post_title' => '',
                'post_type' => 'player',
                'post_status' => 'spp_pending',
                'post_author' => $author_id,
            ]);

            if (!is_wp_error($post_id)) {
                do_action('spp_profile_created', $post_id);
            }
        }

        /**
         * Render rankings display shortcode
         *
         * @param array $atts Shortcode attributes
         * @return string
         */
        public function render_rankings_shortcode($atts = []): string {
            $atts = shortcode_atts([
                'category' => '',
                'limit' => 50,
                'show_player_link' => true,
            ], $atts, 'spp_rankings');

            global $wpdb;

            // Get active ranking categories (cached for 15 minutes).
            $categories = get_transient('spp_ranking_categories_active');
            if ($categories === false) {
                $categories = $wpdb->get_results(
                    "SELECT * FROM {$wpdb->prefix}spp_ranking_categories WHERE is_active = 1 ORDER BY name ASC"
                );
                set_transient('spp_ranking_categories_active', $categories, 15 * MINUTE_IN_SECONDS);
            }

            $output = '<div class="spp-rankings-display">';

            // Category selector
            if (!empty($categories)) {
                $output .= '<form class="spp-rankings-form" method="get">';
                $output .= '<select name="spp_category" class="spp-category-select">';
                $output .= '<option value="">' . esc_html__('All Categories', 'sports-player-platform') . '</option>';
                foreach ($categories as $cat) {
                    $selected = selected($atts['category'], $cat->id, false);
                    $output .= '<option value="' . esc_attr($cat->id) . '"' . $selected . '>' .
                        esc_html($cat->name) . '</option>';
                }
                $output .= '</select>';
                $output .= '</form>';
            }

            // Get selected category
            $category_id = !empty($atts['category']) ? absint($atts['category']) : (
                isset($_GET['spp_category']) ? absint($_GET['spp_category']) : 0
            );

            if ($category_id > 0) {
                $category = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}spp_ranking_categories WHERE id = %d",
                    $category_id
                ));

                if ($category) {
                    $output .= '<h2 class="spp-rankings-title">' . esc_html(__('Rankings for', 'sports-player-platform')) . ' ' . esc_html($category->name) . '</h2>';

                    // Get rankings with player info (cached via versioned transient).
                    $limit = absint($atts['limit']);
                    $cache_ver = (int) get_option('spp_rankings_cache_version', 1);
                    $cache_key = 'spp_rankings_' . $cache_ver . '_' . $category_id . '_' . $limit;
                    $ranked_players = get_transient($cache_key);
                    if ($ranked_players === false) {
                        $ranked_players = $wpdb->get_results($wpdb->prepare("
                            SELECT r.*, p.post_title as player_name, p.guid as player_guid
                            FROM {$wpdb->prefix}spp_rankings r
                            JOIN {$wpdb->posts} p ON r.player_id = p.ID
                            WHERE r.ranking_category_id = %d
                              AND p.post_status = 'publish'
                            ORDER BY r.ranking_position ASC
                            LIMIT %d
                        ", $category_id, $limit));
                        set_transient($cache_key, $ranked_players, 15 * MINUTE_IN_SECONDS);
                    }

                    if (!empty($ranked_players)) {
                        $output .= '<table class="spp-rankings-table">';
                        $output .= '<thead>';
                        $output .= '<tr>';
                        $output .= '<th class="spp-rank-position">' . esc_html__('Rank', 'sports-player-platform') . '</th>';
                        $output .= '<th class="spp-rank-player">' . esc_html__('Player', 'sports-player-platform') . '</th>';
                        $output .= '<th class="spp-rank-position-info">' . esc_html__('Position', 'sports-player-platform') . '</th>';
                        $output .= '<th class="spp-rank-grade">' . esc_html__('Graduation Year', 'sports-player-platform') . '</th>';
                        $output .= '</tr>';
                        $output .= '</thead>';
                        $output .= '<tbody>';

                        foreach ($ranked_players as $ranking) {
                            // Get player position and graduation year from post meta
                            $position = SPP_Player_Profile::get($ranking->player_id, 'playing_position');
                            $grad_year = get_post_meta($ranking->player_id, 'graduation_year', true);
                            $school = get_post_meta($ranking->player_id, 'school', true);

                            $output .= '<tr class="spp-ranking-row">';
                            $output .= '<td class="spp-rank-position-cell">';
                            $output .= '<span class="spp-rank-badge">' . esc_html($ranking->ranking_position) . '</span>';
                            $output .= '</td>';

                            $output .= '<td class="spp-rank-player-cell">';
                            if ($atts['show_player_link']) {
                                $output .= '<a href="' . esc_url(get_permalink($ranking->player_id)) . '" class="spp-player-link">';
                                $output .= esc_html($ranking->player_name);
                                $output .= '</a>';
                            } else {
                                $output .= esc_html($ranking->player_name);
                            }
                            if (!empty($school)) {
                                $output .= '<span class="spp-player-school">' . esc_html($school) . '</span>';
                            }
                            $output .= '</td>';

                            if (!empty($position)) {
                                $output .= '<td class="spp-rank-position-info-cell">' . esc_html($position) . '</td>';
                            } else {
                                $output .= '<td class="spp-rank-position-info-cell">-</td>';
                            }

                            if (!empty($grad_year)) {
                                $output .= '<td class="spp-rank-grade-cell">' . esc_html($grad_year) . '</td>';
                            } else {
                                $output .= '<td class="spp-rank-grade-cell">-</td>';
                            }
                            $output .= '</tr>';
                        }

                        $output .= '</tbody>';
                        $output .= '</table>';
                    } else {
                        $output .= '<p>' . esc_html__('No players ranked in this category.', 'sports-player-platform') . '</p>';
                    }
                }
            }

            $output .= '</div>';

            return $output;
        }

        /**
         * Render players shortcode
         *
         * @param array $atts
         * @return string
         */
        public function render_players_shortcode($atts = []): string {
            $atts = shortcode_atts([
                'sport' => '',
                'limit' => 10,
                'columns' => 3,
            ], $atts, 'spp_players');

            global $wpdb;

            // Use WordPress posts for players (with rankings from database)
            $args = [
                'post_type' => 'player',
                'post_status' => 'publish',
                'has_password' => false,
                'posts_per_page' => $atts['limit'],
            ];

            if (!empty($atts['sport'])) {
                $args['meta_query'] = [
                    [
                        'key' => 'sport',
                        'value' => $atts['sport'],
                        'compare' => '=',
                    ],
                ];
            }

            $query = new WP_Query($args);
            $output = '';
            $can_save = current_user_can('view_saved_players') || current_user_can('manage_options');

            if ($query->have_posts()) {
                $output = '<div class="spp-players-grid spp-columns-' . intval($atts['columns']) . '">';

                while ($query->have_posts()) {
                    $query->the_post();
                    $player_id = get_the_ID();

                    $height = SPP_Player_Profile::get($player_id, 'height');
                    $position = SPP_Player_Profile::get($player_id, 'playing_position');
                    $sport = get_post_meta($player_id, 'sport', true);

                    $output .= '<div class="spp-player-card">';
                    if (has_post_thumbnail()) {
                        $output .= get_the_post_thumbnail($player_id, 'medium', ['class' => 'spp-player-thumb']);
                    } else {
                        $photo = esc_url(SPP_Player_Profile::get($player_id, 'avatar_url'), ['http', 'https']);
                        if ($photo !== '') {
                            $output .= '<img class="spp-player-thumb" src="' . $photo . '" alt="" loading="lazy" referrerpolicy="no-referrer">';
                        }
                    }
                    $output .= '<h3><a href="' . esc_url(get_permalink($player_id)) . '">' . esc_html(get_the_title()) . '</a></h3>';
                    if (!empty($sport)) {
                        $output .= '<p class="spp-sport">' . esc_html($sport) . '</p>';
                    }
                    if (!empty($position)) {
                        $output .= '<p class="spp-position">' . esc_html($position) . '</p>';
                    }
                    if (!empty($height)) {
                        $output .= '<p class="spp-height">' . esc_html($height) . 'cm</p>';
                    }
                    if ($can_save) {
                        $output .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                            . '<input type="hidden" name="action" value="spp_save_player">'
                            . '<input type="hidden" name="player_id" value="' . esc_attr($player_id) . '">'
                            . wp_nonce_field('spp_save_player_' . $player_id, '_wpnonce', false, false)
                            . '<button type="submit" class="button">' . esc_html__('Save to Shortlist', 'sports-player-platform') . '</button></form>';
                    }
                    $output .= '</div>';
                }

                $output .= '</div>';
                wp_reset_postdata();
            } else {
                $output = '<p>' . esc_html__('No players found.', 'sports-player-platform') . '</p>';
            }

            return $output;
        }

        /**
         * Render teams shortcode
         *
         * @param array $atts
         * @return string
         */
        public function render_teams_shortcode($atts = []): string {
            $atts = shortcode_atts([
                'sport' => '',
                'limit' => 10,
            ], $atts, 'spp_teams');

            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_teams';
            $where = '';

            if (!empty($atts['sport'])) {
                $where = $wpdb->prepare("WHERE sport = %s", $atts['sport']);
            }

            $teams = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name $where ORDER BY team_name ASC LIMIT %d",
                $atts['limit']
            ));

            if (empty($teams)) {
                return '<p>' . esc_html__('No teams found.', 'sports-player-platform') . '</p>';
            }

            $output = '<div class="spp-teams-list">';

            foreach ($teams as $team) {
                $capacity = get_post_meta($team->id, 'team_capacity', true);
                $founded = get_post_meta($team->id, 'team_founded', true);
                $league = get_post_meta($team->id, 'team_league', true);

                $output .= '<div class="spp-team-card">';
                if (!empty($team->logo_url)) {
                    $output .= '<img src="' . esc_url($team->logo_url) . '" alt="' . esc_attr($team->team_name) . '">';
                }
                $output .= '<h3>' . esc_html($team->team_name) . '</h3>';
                if (!empty($team->location)) {
                    $output .= '<p class="spp-location">' . esc_html($team->location) . '</p>';
                }
                $output .= '<p class="spp-meta">';
                if (!empty($league)) {
                    $output .= '<span>League: ' . esc_html($league) . '</span>';
                }
                if (!empty($founded)) {
                    $output .= '<span> Founded: ' . esc_html($founded) . '</span>';
                }
                $output .= '</p>';
                $output .= '</div>';
            }

            $output .= '</div>';

            return $output;
        }

        /**
         * Render events embed shortcode
         *
         * @return string
         */
        public function render_events_embed(): string {
            $settings = get_option('spp_settings', []);
            $provider_url = $settings['spp_events_provider_url'] ?? get_option('spp_events_provider_url', '');

            if (empty($provider_url)) {
                return '<p>' . __('Events integration not configured. Please contact administrator.', 'sports-player-platform') . '</p>';
            }

            $embed_code = sprintf(
                '<iframe src="%s" width="100%%" height="600" frameborder="0" scrolling="auto"></iframe>',
                esc_url($provider_url)
            );

            return $embed_code;
        }

        /**
         * Render articles list shortcode
         *
         * @param int $limit
         * @return string
         */
        public function render_articles_list(int $limit = 10): string {
            $query = new WP_Query([
                'post_type' => 'spp_article',
                'posts_per_page' => $limit,
                'post_status' => 'publish',
            ]);

            if (!$query->have_posts()) {
                return '<p>' . __('No articles found.', 'sports-player-platform') . '</p>';
            }

            ob_start();
            ?>
            <div class="spp-articles-list">
                <h1><?php esc_html_e('Latest Articles', 'sports-player-platform'); ?></h1>
                <?php while ($query->have_posts()): $query->the_post(); ?>
                    <article class="spp-article">
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <div class="spp-article-content">
                            <?php the_excerpt(); ?>
                        </div>
                        <div class="spp-article-meta">
                            <span><?php echo get_the_date(); ?></span>
                        </div>
                    </article>
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * AJAX search and filter players
         *
         * @return void
         */
        public function spp_search_players(): void {
            check_ajax_referer('spp_public_nonce', 'nonce');

            if (!current_user_can('read')) {
                wp_die(json_encode(['error' => 'Unauthorized']), 401);
            }

            global $wpdb;

            // Build WP_Query arguments
            $args = [
                'post_type' => 'player',
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'no_found_rows' => true,
                'cache_results' => false,
            ];

            // Playing position filter
            if (!empty($_POST['playing_position'])) {
                $args['meta_key'] = 'playing_position';
                $args['meta_value'] = sanitize_text_field($_POST['playing_position']);
                $args['meta_compare'] = '=';
            }

            // Graduation year filter
            if (!empty($_POST['graduation_year'])) {
                $year = absint($_POST['graduation_year']);
                if ($year > 0) {
                    $args['meta_key'] = 'graduation_year';
                    $args['meta_value'] = $year;
                    $args['meta_compare'] = '=';
                }
            }

            // Ranking filter
            if (!empty($_POST['ranking'])) {
                $ranking = absint($_POST['ranking']);
                if ($ranking > 0) {
                    $args['meta_key'] = 'spp_ranking';
                    $args['meta_value'] = $ranking;
                    $args['meta_compare'] = '=';
                }
            }

            $query = new WP_Query($args);
            $output = '';

            if ($query->have_posts()) {
                $output = '<div class="spp-players-grid spp-columns-3">';

                while ($query->have_posts()) {
                    $query->the_post();
                    $player_id = get_the_ID();

                    $height = SPP_Player_Profile::get($player_id, 'height');
                    $position = SPP_Player_Profile::get($player_id, 'playing_position');
                    $grad_year = get_post_meta($player_id, 'graduation_year', true);
                    $sport = get_post_meta($player_id, 'sport', true);

                    $output .= '<div class="spp-player-card">';
                    if (has_post_thumbnail()) {
                        $output .= get_the_post_thumbnail(null, 'medium', [], ['class' => 'spp-player-thumb']);
                    }
                    $output .= '<h3>' . esc_html(get_the_title()) . '</h3>';
                    if (!empty($sport)) {
                        $output .= '<p><strong>' . esc_html__('Sport:', 'sports-player-platform') . '</strong> ' . esc_html($sport) . '</p>';
                    }
                    if (!empty($position)) {
                        $output .= '<p><strong>' . esc_html__('Position:', 'sports-player-platform') . '</strong> ' . esc_html($position) . '</p>';
                    }
                    if (!empty($grad_year)) {
                        $output .= '<p><strong>' . esc_html__('Graduation Year:', 'sports-player-platform') . '</strong> ' . esc_html($grad_year) . '</p>';
                    }
                    if (!empty($height)) {
                        $output .= '<p><strong>' . esc_html__('Height:', 'sports-player-platform') . '</strong> ' . esc_html($height) . 'cm</p>';
                    }
                    $output .= '</div>';
                }

                $output .= '</div>';
            } else {
                $output = '<p>' . esc_html__('No players match your criteria.', 'sports-player-platform') . '</p>';
            }

            wp_reset_postdata();
            wp_die($output);
        }

        /**
         * Save player profile metadata
         *
         * @param int $player_id
         * @param array $meta
         * @return bool|WP_Error
         */
        public function save_player_meta(int $player_id, array $meta): bool|WP_Error {
            check_ajax_referer('spp_ajax_nonce', 'nonce');

            if (get_post_type($player_id) !== 'player' || !current_user_can('edit_post', $player_id)) {
                return new WP_Error('unauthorized', __('Unauthorized', 'sports-player-platform'));
            }

            $invalid = SPP_Player_Profile::save($player_id, $meta);
            if ($invalid) {
                return new WP_Error('spp_invalid_player_fields', __('Some player details were invalid.', 'sports-player-platform'), $invalid);
            }

            return true;
        }

        /**
         * Load plugin templates for player/team singles and archives when theme doesn't override.
         */
        public function load_custom_templates(string $template): string {
            if (is_singular('player')) {
                $custom = SPP_PLUGIN_DIR . 'templates/player/single-player.php';
                return file_exists($custom) ? $custom : $template;
            }
            if (is_post_type_archive('player')) {
                $custom = SPP_PLUGIN_DIR . 'templates/player/archive-player.php';
                return file_exists($custom) ? $custom : $template;
            }
            if (is_singular('team')) {
                $custom = SPP_PLUGIN_DIR . 'templates/team/single-team.php';
                return file_exists($custom) ? $custom : $template;
            }
            if (is_post_type_archive('team')) {
                $custom = SPP_PLUGIN_DIR . 'templates/team/archive-team.php';
                return file_exists($custom) ? $custom : $template;
            }

            // Handle custom page template for rankings
            if (is_page('rankings') || (is_page() && strpos($_SERVER['REQUEST_URI'] ?? '', '/rankings/') !== false)) {
                $custom = SPP_PLUGIN_DIR . 'spp/rankings.php';
                return file_exists($custom) ? $custom : $template;
            }

            // Also check for rankings CPT archive
            if (is_post_type_archive('spp_ranking') || (is_page() && basename(get_permalink()) === 'rankings')) {
                $custom = SPP_PLUGIN_DIR . 'spp/rankings.php';
                return file_exists($custom) ? $custom : $template;
            }

            return $template;
        }

        /**
         * Track a player profile view in spp_player_views.
         */
        public function track_player_view(int $player_id): void {
            global $wpdb;

            $viewer_id = get_current_user_id();
            $author_id = (int) get_post_field('post_author', $player_id);
            if ($viewer_id && $viewer_id === $author_id) {
                return; // Don't count self-views
            }

            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
            $transient_key = 'spp_view_' . $player_id . '_' . md5($viewer_id . '|' . $ip);
            if (get_transient($transient_key)) {
                return;
            }
            set_transient($transient_key, 1, HOUR_IN_SECONDS);

            $role = function_exists('spp_get_current_user_role') ? (spp_get_current_user_role() ?? 'guest') : 'guest';

            $wpdb->insert($wpdb->prefix . 'spp_player_views', [
                'player_id'   => $player_id,
                'viewer_id'   => $viewer_id ?: null,
                'viewer_role' => $role,
                'ip_address'  => $ip,
            ]);
        }

        /**
         * AJAX handler for the player directory filter sidebar.
         */
        public function ajax_filter_players(): void {
            check_ajax_referer('spp_public_nonce', 'nonce');

            $search     = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
            $sport      = sanitize_text_field(wp_unslash($_POST['sport'] ?? ''));
            $position   = sanitize_text_field(wp_unslash($_POST['position'] ?? ''));
            $location   = sanitize_text_field(wp_unslash($_POST['location'] ?? ''));
            $grad_min   = absint($_POST['grad_min'] ?? 0);
            $grad_max   = absint($_POST['grad_max'] ?? 0);
            $height_min = absint($_POST['height_min'] ?? 0);
            $height_max = absint($_POST['height_max'] ?? 0);
            $orderby    = sanitize_key($_POST['orderby'] ?? 'recent');
            $paged      = max(1, absint($_POST['paged'] ?? 1));

            $args = [
                'post_type'      => 'player',
                'post_status'    => 'publish',
                'posts_per_page' => 12,
                'paged'          => $paged,
            ];

            if ($search !== '') {
                $args['s'] = $search;
            }

            if ($orderby === 'name') {
                $args['orderby'] = 'title';
                $args['order']   = 'ASC';
            } elseif ($orderby === 'grad_year') {
                $args['meta_key'] = 'graduation_year';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'ASC';
            } else {
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
            }

            $meta_query = [];
            if ($sport !== '') {
                $meta_query[] = ['key' => 'sport', 'value' => $sport, 'compare' => '='];
            }
            if ($position !== '') {
                $meta_query[] = ['key' => 'playing_position', 'value' => $position, 'compare' => '='];
            }
            if ($location !== '') {
                $meta_query[] = ['key' => 'location', 'value' => $location, 'compare' => 'LIKE'];
            }
            if ($grad_min > 0 && $grad_max > 0) {
                $meta_query[] = ['key' => 'graduation_year', 'value' => [$grad_min, $grad_max], 'type' => 'NUMERIC', 'compare' => 'BETWEEN'];
            } elseif ($grad_min > 0) {
                $meta_query[] = ['key' => 'graduation_year', 'value' => $grad_min, 'type' => 'NUMERIC', 'compare' => '>='];
            } elseif ($grad_max > 0) {
                $meta_query[] = ['key' => 'graduation_year', 'value' => $grad_max, 'type' => 'NUMERIC', 'compare' => '<='];
            }
            if ($height_min > 0 && $height_max > 0) {
                $meta_query[] = ['key' => 'height', 'value' => [$height_min, $height_max], 'type' => 'NUMERIC', 'compare' => 'BETWEEN'];
            }

            if (!empty($meta_query)) {
                $meta_query['relation'] = 'AND';
                $args['meta_query']     = $meta_query;
            }

            $query = new WP_Query($args);
            ob_start();
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $player_id = get_the_ID();
                    include SPP_PLUGIN_DIR . 'templates/player/player-card.php';
                }
                wp_reset_postdata();
            } else {
                echo '<p>' . esc_html__('No players match your filters.', 'sports-player-platform') . '</p>';
            }
            $html = (string) ob_get_clean();

            wp_send_json_success([
                'html'  => $html,
                'found' => (int) $query->found_posts,
                'pages' => (int) $query->max_num_pages,
                'paged' => $paged,
            ]);
        }
    }
}
