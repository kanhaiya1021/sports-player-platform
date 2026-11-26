<?php
/**
 * Recruiting Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Recruiting')) {

    final class SPP_Recruiting {

        private static $instance = null;

        const STATUS_INTERESTED = 'interested';
        const STATUS_CONTACTED = 'contacted';
        const STATUS_UNDER_CONSIDERATION = 'under_consideration';
        const STATUS_SCOUTED = 'scouted';
        const STATUS_COMMITTED = 'committed';

        public static function get_instance(): self {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->init_hooks();
        }

        private function init_hooks(): void {
            add_shortcode('spp_recruiting', [$this, 'render_recruiting_dashboard']);
            add_shortcode('spp_recruiting_lists', [$this, 'render_recruiting_lists']);
            add_action('admin_post_spp_save_player', [$this, 'handle_save_player']);

            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('rest_api_init', [$this, 'register_rest_routes']);
            add_action('admin_post_spp_export_recruiting_list', [$this, 'handle_export_csv']);

            add_action('wp_ajax_spp_create_recruiting_list', [$this, 'ajax_create_list']);
            add_action('wp_ajax_spp_add_player_to_list', [$this, 'ajax_add_player_to_list']);
            add_action('wp_ajax_spp_remove_player_from_list', [$this, 'ajax_remove_player_from_list']);
            add_action('wp_ajax_spp_update_list_player_notes', [$this, 'ajax_update_list_player_notes']);
        }

        public function enqueue_assets(): void {
            if (!is_user_logged_in()) {
                return;
            }

            $request_path = trim((string) parse_url(add_query_arg([]), PHP_URL_PATH), '/');
            if ($request_path !== 'dashboard/recruiting' && !is_singular('player')) {
                return;
            }

            wp_enqueue_style('spp-dashboard', SPP_PLUGIN_URL . 'assets/css/dashboard.css', [], SPP_VERSION);
            wp_enqueue_script('spp-recruiting', SPP_PLUGIN_URL . 'assets/js/recruiting.js', [], SPP_VERSION, true);
            wp_localize_script('spp-recruiting', 'sppRecruiting', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('spp_recruiting_nonce'),
                'i18n'    => [
                    'added' => __('Added to list!', 'sports-player-platform'),
                    'saved' => __('Notes saved!', 'sports-player-platform'),
                    'error' => __('Action failed. Please try again.', 'sports-player-platform'),
                ],
            ]);
        }

        public function register_rest_routes(): void {
            register_rest_route('spp/v1', '/recruiting-lists', [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'rest_get_lists'],
                    'permission_callback' => [$this, 'rest_coach_permission'],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'rest_create_list'],
                    'permission_callback' => [$this, 'rest_coach_permission'],
                ],
            ]);

            register_rest_route('spp/v1', '/recruiting-lists/(?P<id>\d+)/players', [
                'methods'             => 'POST',
                'callback'            => [$this, 'rest_add_player'],
                'permission_callback' => [$this, 'rest_coach_permission'],
            ]);
        }

        public function rest_coach_permission(): bool {
            return is_user_logged_in() && (current_user_can('create_recruiting_list') || current_user_can('manage_options'));
        }

        public function handle_save_player(): void {
            $player_id = absint($_POST['player_id'] ?? 0);
            check_admin_referer('spp_save_player_' . $player_id);

            if (!current_user_can('view_saved_players') && !current_user_can('manage_options')) {
                wp_die(esc_html__('You cannot save players.', 'sports-player-platform'), '', ['response' => 403]);
            }

            if (get_post_status($player_id) !== 'publish' || !spp_can_view_player($player_id)) {
                wp_die(esc_html__('Player profile is unavailable.', 'sports-player-platform'), '', ['response' => 404]);
            }

            if (!$this->save_player(get_current_user_id(), $player_id)) {
                wp_die(esc_html__('Could not save this player.', 'sports-player-platform'), '', ['response' => 500]);
            }

            wp_safe_redirect(spp_page_url('dashboard/shortlist'));
            exit;
        }

        public function create_recruiting_tables(): void {
            if (class_exists('SPP_DB')) {
                SPP_DB::get_instance()->create_tables();
            }
        }

        public function save_player(int $user_id, int $player_id, string $notes = ''): bool {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_saved_players';

            return $wpdb->query($wpdb->prepare(
                "INSERT INTO $table_name (user_id, player_id, notes) VALUES (%d, %d, %s)
                 ON DUPLICATE KEY UPDATE id = id",
                $user_id,
                $player_id,
                $notes
            )) !== false;
        }

        public function get_saved_players(int $user_id): array {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_saved_players';

            return $wpdb->get_results($wpdb->prepare(
                "SELECT sp.*, p.post_title as player_name
                FROM $table_name sp
                JOIN {$wpdb->posts} p ON sp.player_id = p.ID
                WHERE sp.user_id = %d
                    AND p.post_type = 'player' AND p.post_status = 'publish' AND p.post_password = ''
                ORDER BY sp.saved_at DESC",
                $user_id
            ), ARRAY_A);
        }

        public function remove_saved_player(int $user_id, int $player_id): bool {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_saved_players';

            return (bool) $wpdb->delete(
                $table_name,
                [
                    'user_id' => $user_id,
                    'player_id' => $player_id,
                ]
            );
        }

        public function track_profile_view(int $coach_id, int $player_id): void {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_player_interest';

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE coach_id = %d AND player_id = %d",
                $coach_id,
                $player_id
            ));

            if ($existing) {
                $wpdb->update(
                    $table_name,
                    ['last_viewed' => current_time('mysql')],
                    ['id' => $existing]
                );
            } else {
                $wpdb->insert(
                    $table_name,
                    [
                        'coach_id' => $coach_id,
                        'player_id' => $player_id,
                    ]
                );
            }
        }

        public function update_player_status(int $coach_id, int $player_id, string $status, string $notes = ''): bool {
            global $wpdb;

            if (!in_array($status, ['interested', 'contacted', 'under_consideration', 'scouted', 'committed'])) {
                return false;
            }

            $table_name = $wpdb->prefix . 'spp_player_interest';

            return (bool) $wpdb->update(
                $table_name,
                [
                    'status' => $status,
                    'notes' => $notes,
                    'last_viewed' => current_time('mysql'),
                ],
                [
                    'coach_id' => $coach_id,
                    'player_id' => $player_id,
                ]
            );
        }

        public function create_recruiting_list(int $coach_id, string $name, string $description = '', string $sport = '', int $is_private = 1): int {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_recruiting_lists';

            $wpdb->insert(
                $table_name,
                [
                    'coach_id'    => $coach_id,
                    'name'        => sanitize_text_field($name),
                    'description' => sanitize_textarea_field($description),
                    'sport'       => sanitize_text_field($sport),
                    'is_private'  => $is_private ? 1 : 0,
                ]
            );

            return (int) $wpdb->insert_id;
        }

        public function get_recruiting_lists(int $coach_id): array {
            global $wpdb;

            $table_name   = $wpdb->prefix . 'spp_recruiting_lists';
            $players_tbl  = $wpdb->prefix . 'spp_recruiting_players';

            $lists = $wpdb->get_results($wpdb->prepare(
                "SELECT l.*, (SELECT COUNT(*) FROM {$players_tbl} rp WHERE rp.list_id = l.id) AS player_count
                FROM {$table_name} l
                WHERE l.coach_id = %d AND l.is_active = 1
                ORDER BY l.created_at DESC",
                $coach_id
            ), ARRAY_A);

            return $lists ?: [];
        }

        public function add_player_to_list(int $list_id, int $player_id, int $coach_id, string $notes = ''): bool {
            global $wpdb;

            if (!$this->coach_owns_list($coach_id, $list_id)) {
                return false;
            }

            $table = $wpdb->prefix . 'spp_recruiting_players';
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE list_id = %d AND player_id = %d",
                $list_id,
                $player_id
            ));

            if ($exists) {
                return true;
            }

            return (bool) $wpdb->insert($table, [
                'list_id'   => $list_id,
                'player_id' => $player_id,
                'added_by'  => $coach_id,
                'notes'     => sanitize_textarea_field($notes),
            ]);
        }

        public function remove_player_from_list(int $list_id, int $player_id, int $coach_id): bool {
            global $wpdb;
            if (!$this->coach_owns_list($coach_id, $list_id)) {
                return false;
            }

            return (bool) $wpdb->delete($wpdb->prefix . 'spp_recruiting_players', [
                'list_id'   => $list_id,
                'player_id' => $player_id,
            ]);
        }

        public function update_list_player_notes(int $list_id, int $player_id, int $coach_id, string $notes): bool {
            global $wpdb;
            if (!$this->coach_owns_list($coach_id, $list_id)) {
                return false;
            }

            return false !== $wpdb->update(
                $wpdb->prefix . 'spp_recruiting_players',
                ['notes' => sanitize_textarea_field($notes)],
                ['list_id' => $list_id, 'player_id' => $player_id]
            );
        }

        public function get_list_players(int $list_id, int $coach_id): array {
            global $wpdb;
            if (!$this->coach_owns_list($coach_id, $list_id)) {
                return [];
            }

            $table = $wpdb->prefix . 'spp_recruiting_players';
            return $wpdb->get_results($wpdb->prepare(
                "SELECT rp.*, p.post_title AS player_name
                FROM {$table} rp
                INNER JOIN {$wpdb->posts} p ON rp.player_id = p.ID
                WHERE rp.list_id = %d
                ORDER BY rp.sort_order ASC, rp.created_at DESC",
                $list_id
            ), ARRAY_A) ?: [];
        }

        public function coach_owns_list(int $coach_id, int $list_id): bool {
            global $wpdb;
            $table = $wpdb->prefix . 'spp_recruiting_lists';
            return (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND coach_id = %d AND is_active = 1",
                $list_id,
                $coach_id
            ));
        }

        /**
         * Count distinct coach views in last 30 days for a player (identity hidden).
         */
        public function get_coach_views_count(int $player_id): int {
            global $wpdb;
            $table = $wpdb->prefix . 'spp_player_views';

            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT viewer_id) FROM {$table} WHERE player_id = %d AND viewer_role = 'spp_coach' AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                $player_id
            ));
        }

        public function ajax_create_list(): void {
            check_ajax_referer('spp_recruiting_nonce', 'nonce');
            if (!$this->rest_coach_permission()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 403);
            }

            $coach_id = get_current_user_id();
            $name     = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
            if ($name === '') {
                wp_send_json_error(['message' => __('List name is required.', 'sports-player-platform')], 400);
            }

            // Enforce monthly plan 5 lists limit
            if (!current_user_can('manage_options') && function_exists('spp_get_user_membership')) {
                $membership = spp_get_user_membership($coach_id);
                if (($membership['type'] ?? 'free') === 'monthly' && count($this->get_recruiting_lists($coach_id)) >= 5) {
                    wp_send_json_error(['message' => __('Monthly plan allows up to 5 recruiting lists. Upgrade to Annual for unlimited lists.', 'sports-player-platform')], 403);
                }
            }

            $list_id = $this->create_recruiting_list(
                $coach_id,
                $name,
                (string) wp_unslash($_POST['description'] ?? ''),
                (string) wp_unslash($_POST['sport'] ?? ''),
                !empty($_POST['is_private']) ? 1 : 0
            );

            wp_send_json_success(['list_id' => $list_id]);
        }

        public function ajax_add_player_to_list(): void {
            check_ajax_referer('spp_recruiting_nonce', 'nonce');
            if (!$this->rest_coach_permission()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 403);
            }

            $list_id   = absint($_POST['list_id'] ?? 0);
            $player_id = absint($_POST['player_id'] ?? 0);
            $notes     = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

            if (!$list_id || !$player_id) {
                wp_send_json_error(['message' => __('Invalid list or player.', 'sports-player-platform')], 400);
            }

            $ok = $this->add_player_to_list($list_id, $player_id, get_current_user_id(), $notes);
            $ok ? wp_send_json_success(['message' => __('Player added to list.', 'sports-player-platform')])
                : wp_send_json_error(['message' => __('Could not add player to list.', 'sports-player-platform')], 400);
        }

        public function ajax_remove_player_from_list(): void {
            check_ajax_referer('spp_recruiting_nonce', 'nonce');
            if (!$this->rest_coach_permission()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 403);
            }

            $list_id   = absint($_POST['list_id'] ?? 0);
            $player_id = absint($_POST['player_id'] ?? 0);
            $ok        = $this->remove_player_from_list($list_id, $player_id, get_current_user_id());
            $ok ? wp_send_json_success() : wp_send_json_error(['message' => __('Failed to remove player.', 'sports-player-platform')], 400);
        }

        public function ajax_update_list_player_notes(): void {
            check_ajax_referer('spp_recruiting_nonce', 'nonce');
            if (!$this->rest_coach_permission()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'sports-player-platform')], 403);
            }

            $list_id   = absint($_POST['list_id'] ?? 0);
            $player_id = absint($_POST['player_id'] ?? 0);
            $notes     = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
            $ok        = $this->update_list_player_notes($list_id, $player_id, get_current_user_id(), $notes);
            $ok ? wp_send_json_success(['message' => __('Notes saved!', 'sports-player-platform')])
                : wp_send_json_error(['message' => __('Failed to save notes.', 'sports-player-platform')], 400);
        }

        public function handle_export_csv(): void {
            check_admin_referer('spp_export_list_nonce');
            if (!$this->rest_coach_permission()) {
                wp_die(esc_html__('Unauthorized.', 'sports-player-platform'), 403);
            }

            $list_id = absint($_GET['list_id'] ?? 0);
            $players = $this->get_list_players($list_id, get_current_user_id());

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=recruiting-list-' . $list_id . '.csv');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['Player Name', 'Sport', 'Position', 'School', 'Graduation Year', 'Notes']);
            foreach ($players as $p) {
                $pid = (int) $p['player_id'];
                fputcsv($out, [
                    $p['player_name'],
                    get_post_meta($pid, 'sport', true),
                    get_post_meta($pid, 'playing_position', true),
                    get_post_meta($pid, 'school', true),
                    get_post_meta($pid, 'graduation_year', true),
                    $p['notes'] ?? '',
                ]);
            }
            fclose($out);
            exit;
        }

        public function rest_get_lists(): WP_REST_Response {
            return new WP_REST_Response($this->get_recruiting_lists(get_current_user_id()), 200);
        }

        public function rest_create_list(WP_REST_Request $request): WP_REST_Response {
            $name = sanitize_text_field((string) $request->get_param('name'));
            if ($name === '') {
                return new WP_REST_Response(['error' => 'List name required'], 400);
            }
            $id = $this->create_recruiting_list(
                get_current_user_id(),
                $name,
                (string) $request->get_param('description'),
                (string) $request->get_param('sport'),
                $request->get_param('is_private') !== null ? (int) $request->get_param('is_private') : 1
            );
            return new WP_REST_Response(['id' => $id], 201);
        }

        public function rest_add_player(WP_REST_Request $request): WP_REST_Response {
            $list_id   = absint($request['id']);
            $player_id = absint($request->get_param('player_id'));
            $notes     = sanitize_textarea_field((string) $request->get_param('notes'));
            $ok        = $this->add_player_to_list($list_id, $player_id, get_current_user_id(), $notes);
            return new WP_REST_Response(['success' => $ok], $ok ? 200 : 400);
        }

        /**
         * Render [spp_recruiting] coach dashboard shortcode.
         */
        public function render_recruiting_dashboard(): string {
            if (!is_user_logged_in()) {
                return '<div class="spp-notice spp-notice-error"><p>' . esc_html__('Please log in to access Recruiting Lists.', 'sports-player-platform') . '</p></div>';
            }
            if (!$this->rest_coach_permission()) {
                return '<div class="spp-notice spp-notice-error"><p>' . esc_html__('Recruiting Lists are available for Coach accounts.', 'sports-player-platform') . '</p></div>';
            }

            $coach_id       = get_current_user_id();
            $lists          = $this->get_recruiting_lists($coach_id);
            $active_list_id = absint($_GET['list_id'] ?? ($lists[0]['id'] ?? 0));
            $active_list    = null;
            foreach ($lists as $l) {
                if ((int) $l['id'] === $active_list_id) {
                    $active_list = $l;
                    break;
                }
            }
            $list_players = $active_list_id ? $this->get_list_players($active_list_id, $coach_id) : [];

            $template_path = SPP_PLUGIN_DIR . 'templates/dashboard/recruiting.php';
            if (!file_exists($template_path)) {
                return '';
            }

            $active_tab = 'recruiting';
            ob_start();
            include $template_path;
            return (string) ob_get_clean();
        }

        public function render_recruiting_lists(): string {
            if (!is_user_logged_in()) {
                return '<p>' . __('You must be logged in to view recruiting lists.', 'sports-player-platform') . '</p>';
            }

            $user_id = get_current_user_id();
            $user = wp_get_current_user();

            ob_start();
            ?>
            <div class="spp-recruiting-lists">
                <h1><?php esc_html_e('Recruiting Lists', 'sports-player-platform'); ?></h1>

                <?php if (!empty($user->roles) && in_array('spp_coach', $user->roles)): ?>
                    <h2><?php esc_html_e('My Recruiting Lists', 'sports-player-platform'); ?></h2>
                    <a href="#" class="button button-primary" id="spp-create-list"><?php esc_html_e('Create New List', 'sports-player-platform'); ?></a>

                    <?php
                    $lists = $this->get_recruiting_lists($user_id);
                    if ($lists):
                    ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Name', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Description', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Created', 'sports-player-platform'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lists as $list): ?>
                                    <tr>
                                        <td><?php echo esc_html($list['name']); ?></td>
                                        <td><?php echo esc_html($list['description']); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d', strtotime($list['created_at']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php esc_html_e('No recruiting lists found.', 'sports-player-platform'); ?></p>
                    <?php endif; ?>

                <?php else: ?>
                    <h2><?php esc_html_e('My Saved Players', 'sports-player-platform'); ?></h2>

                    <?php
                    $saved = $this->get_saved_players($user_id);
                    if ($saved):
                    ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Player', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Saved', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Notes', 'sports-player-platform'); ?></th>
                                    <th><?php esc_html_e('Actions', 'sports-player-platform'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saved as $item): ?>
                                    <tr>
                                        <td><?php echo esc_html($item['player_name']); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d', strtotime($item['saved_at']))); ?></td>
                                        <td><?php echo esc_html($item['notes']); ?></td>
                                        <td>
                                            <button class="button button-small spp-remove-saved"
                                                    data-player="<?php echo esc_attr($item['player_id']); ?>">
                                                <?php esc_html_e('Remove', 'sports-player-platform'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php esc_html_e('You haven\'t saved any players yet.', 'sports-player-platform'); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }
    }}
