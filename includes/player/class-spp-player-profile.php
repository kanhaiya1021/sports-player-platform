<?php
/**
 * Player profile metadata shared by the editor and profile readers.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SPP_Player_Profile {

    public const MAX_SEASON_ROWS = 30;

    public const STAT_SPORT_ALIASES = [
        'cricket' => 'cricket',
        'football' => 'football',
        'soccer' => 'football',
        'football / soccer' => 'football',
        'association football' => 'football',
    ];

    private const LEGACY_KEYS = [
        'playing_position' => 'player_position',
        'height' => 'player_height',
        'weight' => 'player_weight',
    ];

    private const PRIVATE_META_KEYS = [
        'linked_player_account' => '_spp_linked_player_account',
        'stats_verification_url' => '_spp_stats_verification_url',
        'recruiting_contact' => '_spp_recruiting_contact',
    ];

    public static function sections(): array {
        return [
            'personal' => [
                'title' => __('Personal Details', 'sports-player-platform'),
                'fields' => [
                    'location' => ['label' => __('Location', 'sports-player-platform'), 'public' => true, 'hint' => __('City and state or country', 'sports-player-platform')],
                    'date_of_birth' => ['label' => __('Date of Birth', 'sports-player-platform'), 'type' => 'date'],
                ],
            ],
            'athletic' => [
                'title' => __('Athletic Details', 'sports-player-platform'),
                'fields' => [
                    'sport' => ['label' => __('Primary Sport', 'sports-player-platform'), 'public' => true],
                    'playing_position' => ['label' => __('Playing Position', 'sports-player-platform'), 'public' => true],
                    'secondary_position' => ['label' => __('Secondary Position', 'sports-player-platform'), 'public' => true],
                    'dominant_side' => ['label' => __('Dominant Hand / Foot', 'sports-player-platform'), 'type' => 'select', 'public' => true, 'options' => [
                        'right' => __('Right', 'sports-player-platform'),
                        'left' => __('Left', 'sports-player-platform'),
                        'both' => __('Both', 'sports-player-platform'),
                    ]],
                    'club' => ['label' => __('Club / Team', 'sports-player-platform'), 'public' => true],
                    'player_jersey' => ['label' => __('Jersey Number', 'sports-player-platform'), 'public' => true],
                    'height' => ['label' => __('Height (cm)', 'sports-player-platform'), 'type' => 'decimal', 'max' => 300, 'public' => true],
                    'weight' => ['label' => __('Weight (kg)', 'sports-player-platform'), 'type' => 'decimal', 'max' => 700, 'public' => true],
                    'athletic_stats' => ['label' => __('Athletic Stats', 'sports-player-platform'), 'type' => 'textarea', 'public' => true, 'hint' => __('Add season, competition and results where applicable.', 'sports-player-platform')],
                ],
            ],
            'seasons' => [
                'title' => __('Teams & Season Stats', 'sports-player-platform'),
                'fields' => [
                    'team_seasons' => ['label' => __('Team History', 'sports-player-platform'), 'type' => 'teams', 'public' => true],
                    'season_stats' => ['label' => __('Season Stats', 'sports-player-platform'), 'type' => 'stats', 'public' => true],
                ],
            ],
            'academic' => [
                'title' => __('Academic Details & Achievements', 'sports-player-platform'),
                'fields' => [
                    'school' => ['label' => __('School / College', 'sports-player-platform'), 'public' => true],
                    'graduation_year' => ['label' => __('Graduation Year', 'sports-player-platform'), 'type' => 'year', 'public' => true],
                    'gpa' => ['label' => __('GPA', 'sports-player-platform'), 'hint' => __('Include the scale, for example 3.8 / 4.0.', 'sports-player-platform')],
                    'sat_act_scores' => ['label' => __('SAT / ACT Scores', 'sports-player-platform')],
                    'awards' => ['label' => __('Awards & Achievements', 'sports-player-platform'), 'type' => 'textarea'],
                ],
            ],
            'media' => [
                'title' => __('Player Media', 'sports-player-platform'),
                'fields' => [
                    'avatar_url' => ['label' => __('Profile Photo URL', 'sports-player-platform'), 'type' => 'url', 'public' => true, 'hint' => __('Optional fallback for the featured image.', 'sports-player-platform')],
                    'image_gallery' => ['label' => __('Photo Gallery', 'sports-player-platform'), 'type' => 'urls', 'public' => true],
                    'videos_highlights' => ['label' => __('Highlight Videos', 'sports-player-platform'), 'type' => 'urls', 'public' => true],
                    'social_media' => ['label' => __('Social Profiles', 'sports-player-platform'), 'type' => 'urls', 'public' => true],
                ],
            ],
            'recruiting' => [
                'title' => __('Recruiting', 'sports-player-platform'),
                'fields' => [
                    'recruiting_status' => ['label' => __('Recruiting Status', 'sports-player-platform'), 'type' => 'select', 'public' => true, 'options' => [
                        'open' => __('Open to offers', 'sports-player-platform'),
                        'committed' => __('Committed', 'sports-player-platform'),
                        'unavailable' => __('Unavailable', 'sports-player-platform'),
                    ]],
                    'preferred_location' => ['label' => __('Preferred Location', 'sports-player-platform'), 'public' => true],
                    'competition_level' => ['label' => __('Preferred Competition Level', 'sports-player-platform'), 'public' => true],
                    'recruiting_info' => ['label' => __('Recruiting Notes', 'sports-player-platform'), 'type' => 'textarea'],
                ],
            ],
            'administration' => [
                'title' => __('Account & Private Contact', 'sports-player-platform'),
                'fields' => [
                    'linked_player_account' => ['label' => __('Linked Player Account', 'sports-player-platform'), 'type' => 'account', 'admin_only' => true],
                    'stats_verification_url' => ['label' => __('Stats Verification Source', 'sports-player-platform'), 'type' => 'url', 'admin_only' => true],
                    'recruiting_contact' => ['label' => __('Recruiting Contact', 'sports-player-platform'), 'type' => 'contact', 'admin_only' => true],
                ],
            ],
        ];
    }

    public static function stat_sports(): array {
        return [
            'cricket' => ['label' => __('Cricket', 'sports-player-platform'), 'metrics' => [
                'matches' => __('Matches', 'sports-player-platform'),
                'runs' => __('Runs', 'sports-player-platform'),
                'wickets' => __('Wickets', 'sports-player-platform'),
            ]],
            'football' => ['label' => __('Football / Soccer', 'sports-player-platform'), 'metrics' => [
                'appearances' => __('Appearances', 'sports-player-platform'),
                'goals' => __('Goals', 'sports-player-platform'),
                'assists' => __('Assists', 'sports-player-platform'),
            ]],
            'other' => ['label' => __('Other Sport', 'sports-player-platform'), 'metrics' => [
                'appearances' => __('Appearances', 'sports-player-platform'),
            ]],
        ];
    }

    public static function stat_sport(string $sport): string {
        return self::STAT_SPORT_ALIASES[strtolower(trim($sport))] ?? 'other';
    }

    public static function fields(): array {
        static $fields = null;
        return $fields ??= array_merge(...array_column(self::sections(), 'fields'));
    }

    public static function get(int $post_id, string $key) {
        if (isset(self::LEGACY_KEYS[$key]) && !metadata_exists('post', $post_id, $key)) {
            $key = self::LEGACY_KEYS[$key];
        }

        return get_post_meta($post_id, self::PRIVATE_META_KEYS[$key] ?? $key, true);
    }

    public static function owner_id(WP_Post $post): int {
        return (int) (self::get($post->ID, 'linked_player_account') ?: $post->post_author);
    }

    public static function account_profile(int $user_id, int $exclude_post = 0): ?WP_Post {
        if ($user_id < 1) {
            return null;
        }

        $query = [
            'post_type' => 'player',
                'post_status' => ['publish', 'spp_pending', 'draft', 'pending', 'future', 'private', 'rejected'],
            'post__not_in' => $exclude_post ? [$exclude_post] : [],
            'posts_per_page' => 1,
        ];
        $account_key = self::PRIVATE_META_KEYS['linked_player_account'];
        $linked = get_posts($query + ['meta_key' => $account_key, 'meta_value' => $user_id]);
        if ($linked) {
            return $linked[0];
        }

        $authored = get_posts($query + [
            'author' => $user_id,
            'meta_query' => [
                'relation' => 'OR',
                ['key' => $account_key, 'compare' => 'NOT EXISTS'],
                ['key' => $account_key, 'value' => ''],
            ],
        ]);
        return $authored[0] ?? null;
    }

    /**
     * Save only submitted fields. Invalid input leaves the previous value intact.
     *
     * @return string[] Keys that could not be saved.
     */
    public static function save(int $post_id, array $input): array {
        $invalid = [];
        foreach (array_intersect_key(self::fields(), $input) as $key => $field) {
            if (!empty($field['admin_only']) && !current_user_can('manage_options')) {
                $invalid[] = $key;
                continue;
            }
            if (in_array($key, ['preferred_location', 'competition_level'], true) && self::get($post_id, 'recruiting_status') !== 'open') {
                continue;
            }

            $previous = self::get($post_id, $key);
            $type = $field['type'] ?? 'text';
            if (in_array($type, ['teams', 'stats', 'contact'], true)) {
                $value = is_array($input[$key])
                    ? self::normalize_group($type, $input[$key], $post_id, is_array($previous) ? $previous : [])
                    : null;
                if ($value === null || !self::store($post_id, $key, $value, $previous)) {
                    $invalid[] = $key;
                }
                continue;
            }

            if (!is_scalar($input[$key])) {
                $invalid[] = $key;
                continue;
            }

            $raw = trim((string) $input[$key]);
            // Keep existing values saved by previous versions when the editor leaves them as entered.
            if (is_scalar($previous) && $raw === trim((string) $previous)) {
                continue;
            }

            if ($type === 'account') {
                if (!self::save_account($post_id, $key, $raw)) {
                    $invalid[] = $key;
                }
                continue;
            }

            $value = self::normalize($raw, $field);
            if ($value === null || !self::store($post_id, $key, $value, $previous)) {
                $invalid[] = $key;
            }
        }

        return $invalid;
    }

    private static function store(int $post_id, string $key, string|array $value, $previous): bool {
        return $previous === $value || ($previous === '' && $value === [])
            || update_post_meta($post_id, self::PRIVATE_META_KEYS[$key] ?? $key, wp_slash($value)) !== false
            || self::get($post_id, $key) === $value;
    }

    private static function save_account(int $post_id, string $key, string $value): bool {
        global $wpdb;

        $user = ctype_digit($value) && (int) $value > 0 ? get_userdata((int) $value) : false;
        if ($value !== '' && (!$user || !in_array(SPP_Auth::ROLE_PLAYER, $user->roles, true))) {
            return false;
        }

        // Serialize explicit account changes so two player edits cannot claim the same account.
        $lock = 'spp_player_account_' . md5(DB_NAME . ':' . $wpdb->prefix);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
            return false;
        }

        try {
            if ($user && self::account_profile($user->ID, $post_id)) {
                return false;
            }
            return self::store($post_id, $key, $user ? (string) $user->ID : '', self::get($post_id, $key));
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    private static function normalize_group(string $type, array $input, int $post_id, array $previous): ?array {
        if ($type === 'contact') {
            return self::normalize_contact($input, $post_id);
        }

        $rows = $input['rows'] ?? [];
        if (($input['present'] ?? '') !== '1' || !is_array($rows) || count($rows) > self::MAX_SEASON_ROWS) {
            return null;
        }

        return $type === 'teams' ? self::normalize_teams($rows, $previous) : self::normalize_stats($rows);
    }

    private static function normalize_teams(array $rows, array $previous): ?array {
        $teams = [];
        $has_current = false;
        $known = array_column($previous, 'team_id', 'team_id');
        foreach ($rows as $row) {
            if (!is_array($row)) {
                return null;
            }
            $team_id = self::text($row['team_id'] ?? '');
            $season = self::text($row['season'] ?? '');
            $current = ($row['current'] ?? '') === '1';
            if ($team_id === null || $season === null || !in_array($row['current'] ?? '', ['', '1'], true)) {
                return null;
            }
            if ($team_id === '' && $season === '' && !$current) {
                continue;
            }
            if (!ctype_digit($team_id) || (int) $team_id < 1 || $season === '' || strlen($season) > 40 || ($has_current && $current)) {
                return null;
            }
            $team_id = (int) $team_id;
            $team = get_post($team_id);
            if (!isset($known[$team_id]) && (
                !$team || $team->post_type !== 'team' || in_array($team->post_status, ['trash', 'auto-draft'], true)
                || !current_user_can('read_post', $team_id)
            )) {
                return null;
            }
            $key = $team_id . ':' . strtolower($season);
            if (isset($teams[$key])) {
                return null;
            }
            $teams[$key] = ['team_id' => $team_id, 'season' => $season, 'current' => $current];
            $has_current = $has_current || $current;
        }
        return array_values($teams);
    }

    private static function normalize_stats(array $rows): ?array {
        $stats = [];
        $sports = self::stat_sports();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                return null;
            }
            $sport = self::text($row['sport'] ?? '');
            $season = self::text($row['season'] ?? '');
            $competition = self::text($row['competition'] ?? '');
            $raw_metrics = $row['metrics'] ?? [];
            if ($sport === null || $season === null || $competition === null || !isset($sports[$sport]) || !is_array($raw_metrics)) {
                return null;
            }
            $metrics = [];
            foreach (array_intersect_key($raw_metrics, $sports[$sport]['metrics']) as $metric => $raw_value) {
                $value = self::text($raw_value);
                if ($value === '') {
                    continue;
                }
                if ($value === null || !ctype_digit($value) || (int) $value > 9999999) {
                    return null;
                }
                $metrics[$metric] = (int) $value;
            }
            if ($season === '' && $competition === '' && !$metrics) {
                continue;
            }
            if ($season === '' || strlen($season) > 40 || strlen($competition) > 120 || !$metrics) {
                return null;
            }
            $key = wp_json_encode([$sport, strtolower($season), strtolower($competition)]);
            if (isset($stats[$key])) {
                return null;
            }
            $stats[$key] = ['sport' => $sport, 'season' => $season, 'competition' => $competition, 'metrics' => $metrics];
        }
        return array_values($stats);
    }

    private static function normalize_contact(array $input, int $post_id): ?array {
        $role = self::text($input['role'] ?? '');
        $name = self::text($input['name'] ?? '');
        $email = self::text($input['email'] ?? '');
        $phone = self::text($input['phone'] ?? '');
        if (in_array(null, [$role, $name, $email, $phone], true)) {
            return null;
        }
        if ($role === '' && $name === '' && $email === '' && $phone === '') {
            return [];
        }

        if (!in_array($role, ['player', 'guardian', 'coach'], true) || ($email === '' && $phone === '')
            || ($email !== '' && !is_email($email)) || ($phone !== '' && !preg_match('/^(?=.*\d)[+\d ().-]{5,40}$/D', $phone))) {
            return null;
        }
        if ($role === 'player') {
            $date = self::get($post_id, 'date_of_birth');
            $validated = is_string($date) ? self::normalize($date, ['type' => 'date']) : null;
            if (!$validated || $validated > (new DateTimeImmutable('today', wp_timezone()))->modify('-18 years')->format('Y-m-d')) {
                return null;
            }
        }
        return compact('role', 'name', 'email', 'phone');
    }

    private static function text($value): ?string {
        return is_scalar($value) ? sanitize_text_field((string) $value) : null;
    }

    /**
     * Parse individual HTTP(S) links from a line-separated or legacy comma-separated list.
     */
    public static function urls(string $input): array {
        $parts = preg_split('/\R+|,\s*(?=https?:\/\/)/i', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        $urls = [];
        foreach ($parts as $part) {
            $url = trim($part);
            if ($url !== '') {
                $urls[] = self::normalize_url($url);
            }
        }

        return $urls;
    }

    /**
     * Sanitize and validate a scalar value against its field definition.
     *
     * @param string $value Trimmed field value.
     * @param array  $field Field definition.
     * @return string|null Normalized value or null when invalid.
     */
    public static function normalize(string $value, array $field): ?string {
        if ($value === '') {
            return '';
        }

        switch ($field['type'] ?? 'text') {
            case 'date':
                if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $date)
                    || !checkdate((int) $date[2], (int) $date[3], (int) $date[1])
                    || $value > wp_date('Y-m-d')) {
                    return null;
                }
                return $value;
            case 'year':
                return preg_match('/^\d{4}$/D', $value) && $value >= 1900 && $value <= 2100 ? $value : null;
            case 'decimal':
                $value = str_replace(',', '.', $value);
                return preg_match('/^\d+(?:\.\d+)?$/D', $value) && (float) $value > 0 && (float) $value <= $field['max'] ? $value : null;
            case 'url':
                return self::normalize_url($value);
            case 'select':
                return isset($field['options'][$value]) ? $value : null;
            case 'urls':
                $urls = self::urls($value);
                return in_array(null, $urls, true) ? null : implode("\n", array_unique($urls));
            case 'textarea':
                return sanitize_textarea_field($value);
            default:
                return sanitize_text_field($value);
        }
    }

    private static function normalize_url(string $value): ?string {
        if (!preg_match('~^https?://~i', $value) || preg_match('/\s/', $value)) {
            return null;
        }

        $url = esc_url_raw($value, ['http', 'https']);
        $parts = wp_parse_url($url);
        return !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) ? $url : null;
    }
}
