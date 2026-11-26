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
         * Post types with player profile field definitions
         */
        const POST_TYPES = [
            'player' => [
                'label' => 'Player',
                'labels' => [
                    'name' => 'Players',
                    'singular_name' => 'Player',
                    'menu_name' => 'Players',
                    'all_items' => 'All Players',
                    'add_new' => 'Add New',
                    'add_new_item' => 'Add New Player',
                    'edit_item' => 'Edit Player',
                    'new_item' => 'New Player',
                    'view_item' => 'View Player',
                    'search_items' => 'Search Players',
                ],
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
                'has_archive' => true,
                'rewrite' => ['slug' => 'players'],
                'meta_fields' => [
                    'date_of_birth' => [
                        'label' => 'Date of Birth',
                        'type' => 'date',
                        'description' => 'Player date of birth (kept private by default)',
                        'public' => false,
                    ],
                    'location' => [
                        'label' => 'Location',
                        'type' => 'text',
                        'description' => 'Player hometown or current location',
                        'public' => true,
                    ],
                    'graduation_year' => [
                        'label' => 'Graduation Year',
                        'type' => 'number',
                        'description' => 'Year of graduation',
                        'public' => true,
                        'min' => 1900,
                        'max' => 2030,
                    ],
                    'school' => [
                        'label' => 'School',
                        'type' => 'text',
                        'description' => 'High school or college attended',
                        'public' => true,
                    ],
                    'sport' => [
                        'label' => 'Sport',
                        'type' => 'text',
                        'description' => 'Primary sport',
                        'public' => true,
                    ],
                    'playing_position' => [
                        'label' => 'Playing Position',
                        'type' => 'select',
                        'options' => ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'],
                        'description' => 'Primary playing position',
                        'public' => true,
                    ],
                    'height' => [
                        'label' => 'Height',
                        'type' => 'text',
                        'description' => 'Height in centimeters',
                        'public' => true,
                    ],
                    'weight' => [
                        'label' => 'Weight',
                        'type' => 'text',
                        'description' => 'Weight in kilograms',
                        'public' => true,
                    ],
                    'image_gallery' => [
                        'label' => 'Photo Gallery',
                        'type' => 'textarea',
                        'description' => 'Comma-separated image URLs',
                        'public' => true,
                    ],
                    'videos_highlights' => [
                        'label' => 'Videos/Highlight Links',
                        'type' => 'textarea',
                        'description' => 'Comma-separated video/highlight URLs',
                        'public' => true,
                    ],
                    'social_media' => [
                        'label' => 'Social Media Links',
                        'type' => 'textarea',
                        'description' => 'Comma-separated social media profile URLs',
                        'public' => true,
                    ],
                    'recruiting_info' => [
                        'label' => 'Recruiting Information',
                        'type' => 'textarea',
                        'description' => 'Recruiting-related information',
                        'public' => false,
                    ],
                ],
            ],
            'team' => [
                'label' => 'Team',
                'labels' => [
                    'name' => 'Teams',
                    'singular_name' => 'Team',
                    'menu_name' => 'Teams',
                    'all_items' => 'All Teams',
                    'add_new' => 'Add New',
                    'add_new_item' => 'Add New Team',
                    'edit_item' => 'Edit Team',
                    'new_item' => 'New Team',
                    'view_item' => 'View Team',
                    'search_items' => 'Search Teams',
                ],
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
                'has_archive' => true,
                'rewrite' => ['slug' => 'teams'],
            ],
        ];

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
            // Register post types immediately to ensure they're available in admin
            $this->register_post_types();

            // Register post status on init hook (must be after WordPress core loads)
            add_action('init', [$this, 'register_post_status'], 10);

            // Register approval/rejection actions
            add_action('admin_post_spp_approve_profile', [$this, 'handle_approve_profile']);
            add_action('admin_post_spp_reject_profile', [$this, 'handle_reject_profile']);

            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_shortcode('spp_players', [$this, 'render_players_shortcode']);
            add_shortcode('spp_rankings', [$this, 'render_rankings_shortcode']);
            add_shortcode('spp_teams', [$this, 'render_teams_shortcode']);
            add_shortcode('spp_events', [$this, 'render_events_embed']);
            add_shortcode('spp_articles', [$this, 'render_articles_list']);
            add_filter('query_vars', [$this, 'add_query_vars']);
            add_action('template_redirect', [$this, 'template_redirect']);
        }

        /**
         * Register custom post status
         */
        public function register_post_status(): void {
            register_post_status('spp_pending', [
                'label' => __('Pending Review', 'sports-player-platform'),
                'public' => false,
                'internal' => true,
                'label_count' => __('Pending', 'sports-player-platform'),
            ]);
        }

        /**
         * Initialize on init hook for any remaining init-dependent functionality
         */
        public function init(): void {
            // This method exists for any future init-dependent functionality
        }

        /**
         * Register custom post types
         */
        private function register_post_types(): void {
            foreach (self::POST_TYPES as $post_type => $args) {
                register_post_type($post_type, $args);
            }
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
            check_ajax_referer('spp_ajax_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_die(__('Unauthorized', 'sports-player-platform'));
            }

            $profile_id = isset($_POST['profile_id']) ? absint($_POST['profile_id']) : 0;

            if ($profile_id > 0) {
                $post = get_post($profile_id);

                if ($post && $post->post_type === 'player') {
                    wp_update_post([
                        'ID' => $profile_id,
                        'post_status' => 'publish',
                    ]);

                    do_action('spp_profile_approved', $profile_id);
                }
            }

            wp_redirect(admin_url('edit.php?post_type=player&spp_status=approved'));
            wp_die();
        }

        /**
         * Handle profile rejection
         */
        public function handle_reject_profile(): void {
            check_ajax_referer('spp_ajax_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_die(__('Unauthorized', 'sports-player-platform'));
            }

            $profile_id = isset($_POST['profile_id']) ? absint($_POST['profile_id']) : 0;

            if ($profile_id > 0) {
                $post = get_post($profile_id);

                if ($post && $post->post_type === 'player') {
                    wp_update_post([
                        'ID' => $profile_id,
                        'post_status' => 'rejected',
                    ]);

                    do_action('spp_profile_rejected', $profile_id);
                }
            }

            wp_redirect(admin_url('edit.php?post_type=player&spp_status=rejected'));
            wp_die();
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
        public function render_rankings_shortcode(array $atts = []): string {
            $atts = shortcode_atts([
                'category' => '',
                'limit' => 50,
                'show_player_link' => true,
            ], $atts, 'spp_rankings');

            global $wpdb;

            // Get active ranking categories
            $categories = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}spp_ranking_categories WHERE is_active = 1 ORDER BY name ASC"
                )
            );

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

                    // Get rankings with player info
                    $ranked_players = $wpdb->get_results($wpdb->prepare("
                        SELECT r.*, p.post_title as player_name, p.guid as player_guid
                        FROM {$wpdb->prefix}spp_rankings r
                        JOIN {$wpdb->posts} p ON r.player_id = p.ID
                        WHERE r.ranking_category_id = %d
                          AND p.post_status = 'publish'
                        ORDER BY r.ranking_position ASC
                        LIMIT %d
                    ", $category_id, $atts['limit']));

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
                            $position = get_post_meta($ranking->player_id, 'playing_position', true);
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
        public function render_players_shortcode(array $atts = []): string {
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

            if ($query->have_posts()) {
                $output = '<div class="spp-players-grid spp-columns-' . intval($atts['columns']) . '">';

                while ($query->have_posts()) {
                    $query->the_post();
                    $player_id = get_the_ID();

                    $height = get_post_meta($player_id, 'player_height', true);
                    $weight = get_post_meta($player_id, 'player_weight', true);
                    $position = get_post_meta($player_id, 'playing_position', true);
                    $jersey = get_post_meta($player_id, 'player_jersey', true);
                    $sport = get_post_meta($player_id, 'sport', true);
                    $grad_year = get_post_meta($player_id, 'graduation_year', true);

                    $output .= '<div class="spp-player-card">';
                    if (has_post_thumbnail()) {
                        $output .= get_the_post_thumbnail(null, 'medium', [], ['class' => 'spp-player-thumb']);
                    }
                    $output .= '<h3>' . esc_html(get_the_title()) . '</h3>';
                    if (!empty($sport)) {
                        $output .= '<p class="spp-sport">' . esc_html($sport) . '</p>';
                    }
                    if (!empty($position)) {
                        $output .= '<p class="spp-position">' . esc_html($position) . '</p>';
                    }
                    if (!empty($height)) {
                        $output .= '<p class="spp-height">' . esc_html($height) . 'cm</p>';
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
        public function render_teams_shortcode(array $atts = []): string {
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
            $provider_url = get_option('spp_events_provider_url', '');
            $api_key = get_option('spp_events_api_key', '');

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

                    $height = get_post_meta($player_id, 'player_height', true);
                    $position = get_post_meta($player_id, 'playing_position', true);
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

            if (!current_user_can('edit_posts')) {
                return new WP_Error('unauthorized', __('Unauthorized', 'sports-player-platform'));
            }

            // Date of birth - kept private
            if (isset($meta['date_of_birth'])) {
                $value = isset($meta['date_of_birth']) ? sanitize_text_field($meta['date_of_birth']) : '';
                update_post_meta($player_id, 'date_of_birth', $value);
            }

            // Location - public
            if (isset($meta['location'])) {
                $value = isset($meta['location']) ? sanitize_text_field($meta['location']) : '';
                update_post_meta($player_id, 'location', $value);
            }

            // Graduation year - public
            if (isset($meta['graduation_year'])) {
                $value = isset($meta['graduation_year']) ? absint($meta['graduation_year']) : 0;
                update_post_meta($player_id, 'graduation_year', $value);
            }

            // School - public
            if (isset($meta['school'])) {
                $value = isset($meta['school']) ? sanitize_text_field($meta['school']) : '';
                update_post_meta($player_id, 'school', $value);
            }

            // Sport - public
            if (isset($meta['sport'])) {
                $value = isset($meta['sport']) ? sanitize_text_field($meta['sport']) : '';
                update_post_meta($player_id, 'sport', $value);
            }

            // Playing position - public
            if (isset($meta['playing_position'])) {
                $value = isset($meta['playing_position']) ? sanitize_text_field($meta['playing_position']) : 'Midfielder';
                update_post_meta($player_id, 'playing_position', $value);
            }

            // Height - public
            if (isset($meta['height'])) {
                $value = isset($meta['height']) ? sanitize_text_field($meta['height']) : '';
                update_post_meta($player_id, 'height', $value);
            }

            // Weight - public
            if (isset($meta['weight'])) {
                $value = isset($meta['weight']) ? sanitize_text_field($meta['weight']) : '';
                update_post_meta($player_id, 'weight', $value);
            }

            // Image gallery - public
            if (isset($meta['image_gallery'])) {
                $value = isset($meta['image_gallery']) ? esc_attr__($meta['image_gallery']) : '';
                update_post_meta($player_id, 'image_gallery', $value);
            }

            // Videos/Highlights - public
            if (isset($meta['videos_highlights'])) {
                $value = isset($meta['videos_highlights']) ? esc_attr__($meta['videos_highlights']) : '';
                update_post_meta($player_id, 'videos_highlights', $value);
            }

            // Social media - public
            if (isset($meta['social_media'])) {
                $value = isset($meta['social_media']) ? esc_attr__($meta['social_media']) : '';
                update_post_meta($player_id, 'social_media', $value);
            }

            // Recruiting info - kept private by default
            if (isset($meta['recruiting_info'])) {
                $value = isset($meta['recruiting_info']) ? sanitize_textarea_field($meta['recruiting_info']) : '';
                update_post_meta($player_id, 'recruiting_info', $value);
            }

            return true;
        }
    }
}