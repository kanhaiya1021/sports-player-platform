<?php
/**
 * Admin class for Sports Player Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Admin')) {

    final class SPP_Admin {

        /**
         * Admin instance
         *
         * @var SPP_Admin|null
         */
        private static $instance = null;

        /**
         * Plugin pages
         *
         * @var array
         */
        private $pages = [];

        /**
         * Get admin instance
         *
         * @return SPP_Admin
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
            add_action('admin_menu', [$this, 'init_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('rest_api_init', [$this, 'register_rest_routes']);
            add_action('wp_ajax_spp_approve_profile', [$this, 'handle_approve_profile']);
            add_action('wp_ajax_spp_reject_profile', [$this, 'handle_reject_profile']);
            add_action('wp_ajax_spp_search_players', [$this, 'spp_search_players']);
            add_action('wp_ajax_nopriv_spp_search_players', [$this, 'spp_search_players']);
        }

        /**
         * Initialize admin menu
         */
        public function init_admin_menu(): void {
            // Main menu page
            $this->pages['players'] = add_menu_page(
                __('Players', 'sports-player-platform'),
                __('Players', 'sports-player-platform'),
                'manage_options',
                'spp-players',
                [$this, 'render_players_page'],
                'dashicons-admin-users',
                6
            );

            // Profile management submenu
            $this->pages['profile-management'] = add_submenu_page(
                'spp-players',
                __('Profile Management', 'sports-player-platform'),
                __('Profiles', 'sports-player-platform'),
                'manage_options',
                'spp-profile-management',
                [$this, 'render_profile_management_page']
            );

            // Teams submenu
            $this->pages['teams'] = add_submenu_page(
                'spp-players',
                __('Teams', 'sports-player-platform'),
                __('Teams', 'sports-player-platform'),
                'manage_options',
                'spp-teams',
                [$this, 'render_teams_page']
            );

            // Rankings submenu
            $this->pages['rankings'] = add_submenu_page(
                'spp-players',
                __('Rankings', 'sports-player-platform'),
                __('Rankings', 'sports-player-platform'),
                'manage_options',
                'spp-rankings',
                [$this, 'render_rankings_page']
            );

            do_action('spp_admin_menu_created', $this->pages);
        }

        /**
         * Enqueue admin scripts
         */
        public function enqueue_scripts(string $hook): void {
            // Only enqueue on our plugin pages
            // WordPress generates hook suffixes like: toplevel_page_spp-players, spp-players_page_spp-profile-management
            $plugin_hooks = [
                'toplevel_page_spp-players',
                'spp-players_page_spp-profile-management',
                'spp-players_page_spp-rankings',
                'spp-players_page_spp-teams',
            ];

            // Also allow standard post edit screens (edit.php and post.php appear as page file names)
            // For custom post types, the hook is like edit.php?post_type=XXX or post.php?post=XXX
            $allow_post_screens = (
                $hook === 'edit.php' ||
                $hook === 'post-new.php' ||
                $hook === 'post.php' ||
                strpos($hook, 'edit-player.php') !== false
            );

            if (!in_array($hook, $plugin_hooks) && !$allow_post_screens) {
                return;
            }

            wp_enqueue_style(
                'spp-admin-css',
                SPP_PLUGIN_URL . 'assets/css/admin.css',
                [],
                SPP_VERSION
            );

            wp_enqueue_script(
                'spp-admin-js',
                SPP_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                SPP_VERSION,
                true
            );

            wp_localize_script('spp-admin-js', 'SPP_Ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('spp_ajax_nonce'),
            ]);
        }

        /**
         * Register REST API routes
         */
        public function register_rest_routes(): void {
            // Public read access - only player basic info (no private data)
            register_rest_route('spp/v1', '/players', [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_players'],
                'permission_callback' => function() {
                    return current_user_can('read');
                },
            ]);

            // Authenticated read/write for teams and advanced player data
            register_rest_route('spp/v1', '/teams', [
                'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
                'callback' => [$this, 'manage_teams'],
                'permission_callback' => 'current_user_can',
            ]);
        }

        /**
         * Render players page
         */
        public function render_players_page(): void {
            global $wpdb;

            // Handle search/filter
            $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

            // Get published players
            $players = [];
            $players_query = new WP_Query([
                'post_type' => 'player',
                'post_status' => ['publish', 'spp_pending'],
                'posts_per_page' => 50,
                's' => $search ? $search : '',
                'no_found_rows' => true,
            ]);

            if ($players_query->have_posts()) {
                while ($players_query->have_posts()) {
                    $players_query->the_post();
                    $players[] = [
                        'id' => get_the_ID(),
                        'title' => get_the_title(),
                        'sport' => get_post_meta(get_the_ID(), 'sport', true),
                        'position' => get_post_meta(get_the_ID(), 'playing_position', true),
                        'grad_year' => get_post_meta(get_the_ID(), 'graduation_year', true),
                        'status' => get_post_status(),
                    ];
                }
                wp_reset_postdata();
            }

            // Get all active ranking categories for dropdown
            $categories = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}spp_ranking_categories WHERE is_active = 1 ORDER BY name ASC");

            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Players', 'sports-player-platform'); ?></h1>

                <div class="spp-players-actions">
                    <form method="get" style="display:inline;">
                        <input type="hidden" name="page" value="spp-players">
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search players...', 'sports-player-platform'); ?>">
                        <button type="submit" class="button"><?php esc_html_e('Search', 'sports-player-platform'); ?></button>
                    </form>

                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=player')); ?>" class="button button-primary">
                        <?php esc_html_e('Add New Player', 'sports-player-platform'); ?>
                    </a>
                </div>

                <?php if (!empty($players)): ?>
                    <table class="widefat striped" style="margin-top:20px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Sport', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Position', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Grad Year', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Status', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Actions', 'sports-player-platform'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $player): ?>
                                <tr>
                                    <td><?php echo esc_html($player['title']); ?></td>
                                    <td><?php echo esc_html($player['sport']); ?></td>
                                    <td><?php echo esc_html($player['position']); ?></td>
                                    <td><?php echo esc_html($player['grad_year']); ?></td>
                                    <td>
                                        <?php if ($player['status'] === 'publish'): ?>
                                            <span class="spp-status spp-status-published"><?php esc_html_e('Published', 'sports-player-platform'); ?></span>
                                        <?php else: ?>
                                            <span class="spp-status spp-status-pending"><?php esc_html_e('Pending', 'sports-player-platform'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url(get_edit_post_link($player['id'])); ?>" class="button button-small"><?php esc_html_e('Edit', 'sports-player-platform'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php esc_html_e('No players found.', 'sports-player-platform'); ?></p>
                <?php endif; ?>
            </div>
            <?php
        }

        /**
         * Render profile management page
         */
        public function render_profile_management_page(): void {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Profile Management', 'sports-player-platform'); ?></h1>

                <?php if (isset($_GET['spp_status'])): ?>
                    <div class="notice notice-<?php echo esc_attr($_GET['spp_status']); ?>">
                        <p><?php
                        $status = $_GET['spp_status'];
                        if ($status === 'approved') {
                            esc_html_e('Profile approved successfully.', 'sports-player-platform');
                        } elseif ($status === 'rejected') {
                            esc_html_e('Profile rejected.', 'sports-player-platform');
                        }
                        ?></p>
                    </div>
                <?php endif; ?>

                <p><?php esc_html_e('Manage player profile approval workflow here.', 'sports-player-platform'); ?></p>

                <?php if (current_user_can('edit_posts')): ?>
                    <div class="spp-profile-actions">
                        <h2><?php esc_html_e('Approve/Reject Profiles', 'sports-player-platform'); ?></h2>
                        <p><?php esc_html_e('Select a profile to approve or reject.', 'sports-player-platform'); ?></p>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
                            <?php wp_nonce_field('spp_ajax_nonce', 'nonce'); ?>
                            <input type="hidden" name="action" value="spp_approve_profile">
                            <input type="hidden" name="profile_id" id="spp-profile-id">
                            <button type="button" class="button button-primary" id="spp-approve-btn"><?php esc_html_e('Approve', 'sports-player-platform'); ?></button>
                            <button type="button" class="button button-secondary" id="spp-reject-btn"><?php esc_html_e('Reject', 'sports-player-platform'); ?></button>
                        </form>
                    </div>

                    <div class="spp-profile-list">
                        <h2><?php esc_html_e('Pending Profiles', 'sports-player-platform'); ?></h2>
                        <table class="widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Name', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Email', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Role', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Status', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Actions', 'sports-player-platform'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                global $wpdb;
                                $players = $wpdb->get_results($wpdb->prepare(
                                    "SELECT ID, post_author, post_status FROM {$wpdb->posts} WHERE post_type = 'player' AND post_status = 'spp_pending' ORDER BY post_date DESC"
                                ));

                                foreach ($players as $player):
                                    $author = get_userdata($player->post_author);
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($author->display_name ?? 'Unknown'); ?></td>
                                        <td><?php echo esc_html($author->user_email ?? 'N/A'); ?></td>
                                        <td><?php echo esc_html( $author->roles[0] ?? 'subscriber'); ?></td>
                                        <td><span class="spp-status spp-status-pending"><?php esc_html_e('Pending', 'sports-player-platform'); ?></span></td>
                                        <td>
                                            <button class="spp-approve-row button button-small" data-id="<?php echo esc_attr($player->ID); ?>"><?php esc_html_e('Approve', 'sports-player-platform'); ?></button>
                                            <button class="spp-reject-row button button-small" data-id="<?php echo esc_attr($player->ID); ?>"><?php esc_html_e('Reject', 'sports-player-platform'); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p><?php esc_html_e('You do not have permission to access profile management.', 'sports-player-platform'); ?></p>
                <?php endif; ?>
            </div>
            <?php
        }

        /**
         * Render teams page
         */
        public function render_teams_page(): void {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Teams', 'sports-player-platform'); ?></h1>
                <p><?php esc_html_e('Manage team information and rosters.', 'sports-player-platform'); ?></p>
            </div>
            <?php
        }

        /**
         * Render rankings page with admin management
         */
        public function render_rankings_page(): void {
            global $wpdb;

            // Handle form submissions
            if (isset($_POST['spp_nonce']) && wp_verify_nonce($_POST['spp_nonce'], 'spp_ranking_category_nonce')) {
                if (isset($_POST['spp_action']) && $_POST['spp_action'] === 'add_ranking_category') {
                    $name = sanitize_text_field($_POST['name'] ?? '');
                    $description = sanitize_textarea_field($_POST['description'] ?? '');

                    if (!empty($name)) {
                        $wpdb->insert(
                            $wpdb->prefix . 'spp_ranking_categories',
                            [
                                'name' => $name,
                                'description' => $description,
                                'is_active' => 1,
                            ],
                            ['%s', '%s', '%d']
                        );
                    }
                } elseif (isset($_POST['spp_action']) && $_POST['spp_action'] === 'edit_ranking_category') {
                    $category_id = absint($_POST['category_id'] ?? 0);
                    $name = sanitize_text_field($_POST['name'] ?? '');
                    $description = sanitize_textarea_field($_POST['description'] ?? '');

                    if ($category_id > 0 && !empty($name)) {
                        // Check for duplicate name (excluding current category)
                        $existing = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}spp_ranking_categories WHERE name = %s AND id != %d",
                            $name,
                            $category_id
                        ));

                        if (!$existing) {
                            $wpdb->update(
                                $wpdb->prefix . 'spp_ranking_categories',
                                [
                                    'name' => $name,
                                    'description' => $description,
                                    'updated_at' => current_time('mysql'),
                                ],
                                ['id' => $category_id]
                            );
                        }
                    }
                } elseif (isset($_POST['spp_action']) && $_POST['spp_action'] === 'deactivate_ranking_category') {
                    $category_id = absint($_POST['category_id'] ?? 0);

                    if ($category_id > 0) {
                        $wpdb->update(
                            $wpdb->prefix . 'spp_ranking_categories',
                            ['is_active' => 0],
                            ['id' => $category_id]
                        );
                    }
                } elseif (isset($_POST['spp_action']) && $_POST['spp_action'] === 'activate_ranking_category') {
                    $category_id = absint($_POST['category_id'] ?? 0);

                    if ($category_id > 0) {
                        $wpdb->update(
                            $wpdb->prefix . 'spp_ranking_categories',
                            ['is_active' => 1],
                            ['id' => $category_id]
                        );
                    }
                } elseif (isset($_POST['spp_action']) && $_POST['spp_action'] === 'import_csv') {
                    if (!isset($_FILES['rankings_csv']) || $_FILES['rankings_csv']['error'] !== 0) {
                        $import_error = $_FILES['rankings_csv']['error'] ?? 'upload_error';
                    } else {
                        $csv_file = $_FILES['rankings_csv']['tmp_name'];
                        $handle = fopen($csv_file, 'r');

                        if (!$handle) {
                            $import_error = 'open_error';
                        } else {
                            $header = fgetcsv($handle);

                            $imported = 0;

                            while (($row = fgetcsv($handle)) !== false) {
                                if (count($row) < 3) {
                                    continue;
                                }

                                $player_name = sanitize_text_field($row[0]);
                                $ranking_position = absint($row[1]);
                                $ranking_category = sanitize_text_field($row[2]);
                                $notes = isset($row[3]) ? sanitize_textarea_field($row[3]) : '';
                                $effective_date = isset($row[4]) ? sanitize_text_field($row[4]) : current_time('Y-m-d');

                                if (empty($player_name) || $ranking_position < 1) {
                                    continue;
                                }

                                $player = $wpdb->get_row($wpdb->prepare(
                                    "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'player' LIMIT 1",
                                    $player_name
                                ));

                                if (!$player) {
                                    continue;
                                }

                                $category = $wpdb->get_row($wpdb->prepare(
                                    "SELECT id FROM {$wpdb->prefix}spp_ranking_categories WHERE name = %s LIMIT 1",
                                    $ranking_category
                                ));

                                if (!$category) {
                                    $wpdb->insert(
                                        $wpdb->prefix . 'spp_ranking_categories',
                                        [
                                            'name' => $ranking_category,
                                            'description' => '',
                                            'is_active' => 1,
                                        ]
                                    );
                                    $category_id = $wpdb->insert_id;
                                } else {
                                    $category_id = $category->id;
                                }

                                $existing = $wpdb->get_row($wpdb->prepare(
                                    "SELECT id, ranking_position FROM {$wpdb->prefix}spp_rankings WHERE player_id = %d AND ranking_category_id = %d",
                                    $player->ID,
                                    $category_id
                                ));

                                if ($existing && $existing->ranking_position != $ranking_position) {
                                    $wpdb->insert(
                                        $wpdb->prefix . 'spp_rankings_history',
                                        [
                                            'ranking_id' => $existing->id,
                                            'player_id' => $player->ID,
                                            'ranking_category' => $ranking_category,
                                            'previous_position' => $existing->ranking_position,
                                            'new_position' => $ranking_position,
                                            'effective_date' => $effective_date,
                                            'updated_by' => get_current_user_id(),
                                        ]
                                    );

                                    $wpdb->update(
                                        $wpdb->prefix . 'spp_rankings',
                                        ['ranking_position' => $ranking_position, 'notes' => $notes, 'effective_date' => $effective_date, 'updated_at' => current_time('mysql')],
                                        ['id' => $existing->id]
                                    );
                                } elseif (!$existing) {
                                    $wpdb->insert(
                                        $wpdb->prefix . 'spp_rankings',
                                        [
                                            'player_id' => $player->ID,
                                            'ranking_category_id' => $category_id,
                                            'ranking_position' => $ranking_position,
                                            'notes' => $notes,
                                            'effective_date' => $effective_date,
                                        ]
                                    );

                                    $new_id = $wpdb->insert_id;
                                    $wpdb->insert(
                                        $wpdb->prefix . 'spp_rankings_history',
                                        [
                                            'ranking_id' => $new_id,
                                            'player_id' => $player->ID,
                                            'ranking_category' => $ranking_category,
                                            'previous_position' => NULL,
                                            'new_position' => $ranking_position,
                                            'effective_date' => $effective_date,
                                            'updated_by' => get_current_user_id(),
                                        ]
                                    );
                                }

                                $imported++;
                            }

                            fclose($handle);
                            wp_redirect(admin_url('admin.php?page=spp-rankings&import_success=' . $imported));
                            wp_die();
                        }
                    }

                    $error_message = match($import_error) {
                        'upload_error' => __('No CSV file uploaded.', 'sports-player-platform'),
                        'open_error' => __('Failed to open CSV file.', 'sports-player-platform'),
                        default => __('CSV import failed.', 'sports-player-platform'),
                    };
                    wp_redirect(admin_url('admin.php?page=spp-rankings&import_error=' . urlencode($error_message)));
                    wp_die();
                }
            }

            if (isset($_POST['spp_nonce']) && wp_verify_nonce($_POST['spp_nonce'], 'spp_assign_ranking_nonce')) {
                if (isset($_POST['spp_action']) && $_POST['spp_action'] === 'assign_ranking') {
                    $category_id = absint($_POST['ranking_category_id'] ?? 0);
                    $category = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}spp_ranking_categories WHERE id = %d", $category_id));

                    if ($category) {
                        $updated_by = get_current_user_id();

                        foreach ($_POST['rankings'] ?? [] as $player_id => $ranking) {
                            $player_id = absint($player_id);
                            $ranking = absint($ranking);

                            if ($ranking > 0) {
                                $existing = $wpdb->get_row($wpdb->prepare(
                                    "SELECT * FROM {$wpdb->prefix}spp_rankings WHERE player_id = %d AND ranking_category_id = %d",
                                    $player_id,
                                    $category_id
                                ));

                                if ($existing && $existing->ranking_position != $ranking) {
                                    $wpdb->insert(
                                        $wpdb->prefix . 'spp_rankings_history',
                                        [
                                            'ranking_id' => $existing->id,
                                            'player_id' => $player_id,
                                            'ranking_category' => $category->name,
                                            'previous_position' => $existing->ranking_position,
                                            'new_position' => $ranking,
                                            'effective_date' => current_time('Y-m-d'),
                                            'updated_by' => $updated_by,
                                        ]
                                    );

                                    $wpdb->update(
                                        $wpdb->prefix . 'spp_rankings',
                                        ['ranking_position' => $ranking, 'updated_at' => current_time('mysql')],
                                        ['id' => $existing->id]
                                    );
                                } elseif (!$existing) {
                                    $wpdb->insert(
                                        $wpdb->prefix . 'spp_rankings',
                                        [
                                            'player_id' => $player_id,
                                            'ranking_category_id' => $category_id,
                                            'ranking_position' => $ranking,
                                            'effective_date' => current_time('Y-m-d'),
                                        ]
                                    );

                                    $new_id = $wpdb->insert_id;
                                    $wpdb->insert(
                                        $wpdb->prefix . 'spp_rankings_history',
                                        [
                                            'ranking_id' => $new_id,
                                            'player_id' => $player_id,
                                            'ranking_category' => $category->name,
                                            'previous_position' => NULL,
                                            'new_position' => $ranking,
                                            'effective_date' => current_time('Y-m-d'),
                                            'updated_by' => $updated_by,
                                        ]
                                    );
                                }
                            }
                        }

                        wp_redirect(admin_url('admin.php?page=spp-rankings&message=' . urlencode(__('Rankings updated successfully.', 'sports-player-platform'))));
                        wp_die();
                    }
                }
            }

            if (isset($_GET['message'])) {
                $message = sanitize_text_field($_GET['message']);
            }

            if (isset($_GET['import_success'])) {
                $imported = absint($_GET['import_success']);
                $message = sprintf(__('Successfully imported %d rankings from CSV.', 'sports-player-platform'), $imported);
            } elseif (isset($_GET['import_error'])) {
                $error = sanitize_text_field($_GET['import_error']);
                $message = sprintf(__('CSV Import Error: %s', 'sports-player-platform'), $error);
            }

            $categories = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}spp_ranking_categories ORDER BY name ASC");

            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Rankings', 'sports-player-platform'); ?></h1>

                <?php if (isset($message)): ?>
                    <div class="notice notice-<?php echo isset($_GET['import_error']) ? 'error' : 'success'; ?>">
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php endif; ?>

                <h2><?php esc_html_e('Ranking Categories', 'sports-player-platform'); ?></h2>
                <p><?php esc_html_e('Manage ranking categories for organizing player rankings.', 'sports-player-platform'); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field('spp_ranking_category_nonce', 'spp_nonce'); ?>
                    <input type="hidden" name="spp_action" value="add_ranking_category">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Description', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Status', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Actions', 'sports-player-platform'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($categories as $cat):
                            ?>
                                <tr>
                                    <td><?php echo esc_html($cat->name); ?></td>
                                    <td><?php echo esc_html($cat->description); ?></td>
                                    <td><?php echo $cat->is_active ? 'Active' : 'Inactive'; ?></td>
                                    <td>
                                        <button type="button" class="button button-small spp-edit-category" data-id="<?php echo esc_attr($cat->id); ?>" data-name="<?php echo esc_attr($cat->name); ?>" data-description="<?php echo esc_attr($cat->description); ?>">
                                            <?php esc_html_e('Edit', 'sports-player-platform'); ?>
                                        </button>
                                        <?php if ($cat->is_active): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to deactivate this category?', 'sports-player-platform'); ?>');">
                                                <?php wp_nonce_field('spp_ranking_category_nonce', 'spp_nonce'); ?>
                                                <input type="hidden" name="spp_action" value="deactivate_ranking_category">
                                                <input type="hidden" name="category_id" value="<?php echo esc_attr($cat->id); ?>">
                                                <button type="submit" class="button button-secondary button-small"><?php esc_html_e('Deactivate', 'sports-player-platform'); ?></button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to activate this category?', 'sports-player-platform'); ?>');">
                                                <?php wp_nonce_field('spp_ranking_category_nonce', 'spp_nonce'); ?>
                                                <input type="hidden" name="spp_action" value="activate_ranking_category">
                                                <input type="hidden" name="category_id" value="<?php echo esc_attr($cat->id); ?>">
                                                <button type="submit" class="button button-secondary button-small"><?php esc_html_e('Activate', 'sports-player-platform'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="4"><?php esc_html_e('No ranking categories found.', 'sports-player-platform'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <h3><?php esc_html_e('Add New Category', 'sports-player-platform'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Name', 'sports-player-platform'); ?></th>
                            <td><input type="text" name="name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Description', 'sports-player-platform'); ?></th>
                            <td><textarea name="description" class="large-text"></textarea></td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Add Category', 'sports-player-platform'); ?></button>

                    <h3><?php esc_html_e('CSV Import', 'sports-player-platform'); ?></h3>
                    <p><?php esc_html_e('Import rankings from a CSV file. The CSV should have columns: Player Name, Position, Ranking, Notes (optional), Date (optional).', 'sports-player-platform'); ?></p>
                    <form method="post" action="" enctype="multipart/form-data">
                        <?php wp_nonce_field('spp_ranking_category_nonce', 'spp_nonce'); ?>
                        <input type="hidden" name="spp_action" value="import_csv">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('CSV File', 'sports-player-platform'); ?></th>
                                <td><input type="file" name="rankings_csv" accept=".csv" required></td>
                            </tr>
                        </table>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Import Rankings', 'sports-player-platform'); ?></button>
                    </form>

                    <!-- Edit Category Modal -->
                    <div id="spp-edit-category-modal" class="modal" style="display:none;">
                        <div class="modal-content" style="background:#fff; padding:20px; border-radius:8px; max-width:500px; margin:0 auto;">
                            <h3><?php esc_html_e('Edit Ranking Category', 'sports-player-platform'); ?></h3>
                            <form method="post" action="">
                                <?php wp_nonce_field('spp_ranking_category_nonce', 'spp_nonce'); ?>
                                <input type="hidden" name="spp_action" value="edit_ranking_category">
                                <input type="hidden" name="category_id" id="edit-category-id">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Name', 'sports-player-platform'); ?></th>
                                        <td><input type="text" name="name" id="edit-category-name" class="regular-text" required></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Description', 'sports-player-platform'); ?></th>
                                        <td><textarea name="description" id="edit-category-description" class="large-text"></textarea></td>
                                    </tr>
                                </table>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes', 'sports-player-platform'); ?></button>
                                <button type="button" class="button button-secondary" id="spp-close-edit-modal"><?php esc_html_e('Cancel', 'sports-player-platform'); ?></button>
                            </form>
                        </div>
                        <div style="position:absolute; top:10px; right:15px; cursor:pointer;" id="spp-close-modal">&times;</div>
                    </div>
                    <style>
                    .modal {position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:10000;}
                    .modal-content {position:relative; background:#fff; padding:20px; border-radius:8px; max-width:500px; margin:0 auto;}
                    .modal-content h3 {margin-top:0;}
                    </style>

                </form>

                <?php if (isset($_GET['ranking_category_id']) && $_GET['ranking_category_id'] > 0): ?>
                    <?php
                    $category_id = absint($_GET['ranking_category_id']);
                    $category = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spp_ranking_categories WHERE id = %d", $category_id));
                    ?>

                    <h3><?php esc_html_e('Rankings for', 'sports-player-platform'); ?>: <?php echo esc_html($category->name); ?></h3>

                    <form method="post" action="">
                        <?php wp_nonce_field('spp_assign_ranking_nonce', 'spp_nonce'); ?>
                        <input type="hidden" name="spp_action" value="assign_ranking">
                        <input type="hidden" name="ranking_category_id" value="<?php echo esc_attr($category_id); ?>">

                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Player', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Position', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Ranking', 'sports-player-platform'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ranked_players = $wpdb->get_results($wpdb->prepare(
                                    "SELECT p.ID, p.post_title as player_name,
                                     COALESCE(pm.meta_value, 'N/A') as position,
                                     r.ranking_position
                                     FROM {$wpdb->posts} p
                                     LEFT JOIN {$wpdb->prefix}spp_rankings r ON p.ID = r.player_id AND r.ranking_category_id = %d
                                     LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = 'player_position'
                                     WHERE p.post_type = 'player' AND (p.post_status = 'publish' OR p.post_status = 'spp_pending')
                                     ORDER BY COALESCE(r.ranking_position, 999), p.post_title ASC",
                                    $category_id
                                ));

                                foreach ($ranked_players as $player):
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($player->player_name); ?></td>
                                        <td><?php echo esc_html($player->position); ?></td>
                                        <td>
                                            <input type="number" name="rankings[<?php echo esc_attr($player->ID); ?>]"
                                                   value="<?php echo esc_attr($player->ranking_position ?: ''); ?>"
                                                   min="1" class="small-text"
                                                   placeholder="<?php esc_attr_e('Enter rank', 'sports-player-platform'); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save Rankings', 'sports-player-platform'); ?></button>
                    </form>

                    <h3><?php esc_html_e('Ranking History', 'sports-player-platform'); ?></h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Player', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Old Rank', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('New Rank', 'sports-player-platform'); ?></th>
                                <th><?php esc_html_e('Updated By', 'sports-player-platform'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $history = $wpdb->get_results($wpdb->prepare(
                                "SELECT h.*, p.post_title as player_name, u.display_name as updated_by_name
                                 FROM {$wpdb->prefix}spp_rankings_history h
                                 JOIN {$wpdb->posts} p ON h.player_id = p.ID
                                 JOIN {$wpdb->users} u ON h.updated_by = u.ID
                                 WHERE h.ranking_category = %s
                                 ORDER BY h.updated_at DESC
                                 LIMIT 50",
                                $category->name
                            ));

                            foreach ($history as $hist):
                            ?>
                                <tr>
                                    <td><?php echo esc_html($hist->updated_at); ?></td>
                                    <td><?php echo esc_html($hist->player_name); ?></td>
                                    <td><?php echo esc_html($hist->previous_position ?: '-'); ?></td>
                                    <td><?php echo esc_html($hist->new_position); ?></td>
                                    <td><?php echo esc_html($hist->updated_by_name); ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="5"><?php esc_html_e('No ranking history for this category.', 'sports-player-platform'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <p><a href="<?php echo esc_url(add_query_arg('ranking_category_id', false, admin_url('admin.php?page=spp-rankings'))); ?>" class="button"><?php esc_html_e('Back to Categories', 'sports-player-platform'); ?></a></p>
                <?php endif; ?>
            </div>
            <?php
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
         * AJAX search players for public directory
         */
        public function spp_search_players(): void {
            check_ajax_referer('spp_ajax_nonce', 'nonce');

            if (!current_user_can('read')) {
                wp_die(json_encode(['error' => 'Unauthorized']), 401);
            }

            global $wpdb;

            // Build query arguments
            $position = isset($_POST['playing_position']) ? sanitize_text_field($_POST['playing_position']) : '';
            $grad_year = isset($_POST['graduation_year']) ? absint($_POST['graduation_year']) : 0;
            $ranking = isset($_POST['ranking']) ? absint($_POST['ranking']) : 0;

            $args = [
                'post_type' => 'player',
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'no_found_rows' => true,
                'cache_results' => false,
            ];

            if (!empty($position)) {
                $args['meta_key'] = 'playing_position';
                $args['meta_value'] = $position;
                $args['meta_compare'] = '=';
            }

            if ($grad_year > 0) {
                $args['meta_key'] = 'graduation_year';
                $args['meta_value'] = $grad_year;
                $args['meta_compare'] = '=';
            }

            if ($ranking > 0) {
                $args['meta_key'] = 'spp_ranking';
                $args['meta_value'] = $ranking;
                $args['meta_compare'] = '=';
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
         * Get players from database
         *
         * @param WP_REST_Request $request
         * @return WP_REST_Response
         */
        public function get_players(WP_REST_Request $request): WP_REST_Response {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_players';
            $players = $wpdb->get_results("SELECT * FROM $table_name ORDER BY player_name ASC");

            return new WP_REST_Response($players, 200);
        }

        /**
         * Get teams from database
         *
         * @param WP_REST_Request $request
         * @return WP_REST_Response
         */
        public function get_teams(WP_REST_Request $request): WP_REST_Response {
            global $wpdb;

            if (!current_user_can('read')) {
                return new WP_REST_Response(['error' => 'Unauthorized'], 401);
            }

            $table_name = $wpdb->prefix . 'spp_teams';
            $teams = $wpdb->get_results("SELECT id, team_name, sport, logo_url, location, founded FROM $table_name ORDER BY team_name ASC");

            return new WP_REST_Response($teams, 200);
        }

        /**
         * Manage teams - authenticated operations
         *
         * @param WP_REST_Request $request
         * @return WP_REST_Response
         */
        public function manage_teams(WP_REST_Request $request): WP_REST_Response {
            if (!current_user_can('edit_posts')) {
                return new WP_REST_Response(['error' => 'Unauthorized: Edit capability required'], 401);
            }

            $action = $request->get_param('action');

            switch ($action) {
                case 'create':
                    $team_name = $request->get_param('team_name');
                    $sport = $request->get_param('sport');
                    $location = $request->get_param('location');
                    $founded = $request->get_param('founded');

                    if (empty($team_name) || empty($sport)) {
                        return new WP_REST_Response(['error' => 'team_name and sport are required'], 400);
                    }

                    global $wpdb;
                    $wpdb->insert($wpdb->prefix . 'spp_teams', [
                        'team_name' => $team_name,
                        'sport' => $sport,
                        'location' => $location ?? '',
                        'founded' => $founded,
                    ]);

                    $team_id = $wpdb->insert_id;

                    return new WP_REST_Response([
                        'id' => $team_id,
                        'team_name' => $team_name,
                        'sport' => $sport,
                        'message' => 'Team created successfully'
                    ], 201);

                case 'update':
                    $team_id = $request->get_param('id');
                    $team_name = $request->get_param('team_name');
                    $sport = $request->get_param('sport');

                    if (empty($team_id) || empty($team_name)) {
                        return new WP_REST_Response(['error' => 'team_id and team_name are required'], 400);
                    }

                    $wpdb->update($wpdb->prefix . 'spp_teams', [
                        'team_name' => $team_name,
                        'sport' => $sport,
                    ], ['id' => $team_id]);

                    return new WP_REST_Response([
                        'id' => $team_id,
                        'team_name' => $team_name,
                        'message' => 'Team updated successfully'
                    ], 200);

                case 'delete':
                    $team_id = $request->get_param('id');

                    if (empty($team_id)) {
                        return new WP_REST_Response(['error' => 'team_id is required'], 400);
                    }

                    $wpdb->delete($wpdb->prefix . 'spp_teams', ['id' => $team_id]);

                    return new WP_REST_Response([
                        'id' => $team_id,
                        'message' => 'Team deleted successfully'
                    ], 200);

                default:
                    return new WP_REST_Response(['error' => 'Invalid action'], 400);
            }
        }
    }
}