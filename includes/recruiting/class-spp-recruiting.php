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
            add_shortcode('spp_recruiting_lists', [$this, 'render_recruiting_lists']);
        }

        public function create_recruiting_tables(): void {
            if (class_exists('SPP_DB')) {
                SPP_DB::get_instance()->create_tables();
            }
        }

        public function save_player(int $user_id, int $player_id, string $notes = ''): bool {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_saved_players';

            return (bool) $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'player_id' => $player_id,
                    'notes' => $notes,
                ]
            );
        }

        public function get_saved_players(int $user_id): array {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_saved_players';

            return $wpdb->get_results($wpdb->prepare(
                "SELECT sp.*, p.post_title as player_name
                FROM $table_name sp
                JOIN {$wpdb->posts} p ON sp.player_id = p.ID
                WHERE sp.user_id = %d
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

        public function create_recruiting_list(int $coach_id, string $name, string $description = ''): int {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_recruiting_lists';

            $wpdb->insert(
                $table_name,
                [
                    'coach_id' => $coach_id,
                    'name' => $name,
                    'description' => $description,
                ]
            );

            return $wpdb->insert_id;
        }

        public function get_recruiting_lists(int $coach_id): array {
            global $wpdb;

            $table_name = $wpdb->prefix . 'spp_recruiting_lists';

            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE coach_id = %d AND is_active = 1 ORDER BY created_at DESC",
                $coach_id
            ), ARRAY_A);
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
