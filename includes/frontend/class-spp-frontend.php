<?php
/**
 * Frontend Display and SEO Class
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SPP_Frontend')) {

    final class SPP_Frontend {

        private static $instance = null;

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
            add_filter('query_vars', [$this, 'register_query_vars'], 1);
            add_action('wp_head', [$this, 'add_seo_metadata'], 5);
            add_filter('document_title_parts', [$this, 'filter_document_title']);
            add_filter('wp_sitemaps_post_types', [$this, 'filter_sitemap_post_types']);
            add_filter('wp_sitemaps_posts_query_args', [$this, 'filter_sitemap_query_args'], 10, 2);
            add_action('template_redirect', [$this, 'handle_events_embed']);
            add_action('parse_request', [$this, 'parse_player_request'], 1);
            add_filter('template_include', [$this, 'load_player_template']);
            add_action('pre_get_posts', [$this, 'maybe_handle_player_query']);
            $this->create_articles_post_type();
        }

        public function register_query_vars(array $vars): array {
            $vars[] = 'player';
            $vars[] = 'team';
            return $vars;
        }

        public function parse_player_request($wp): void {
            if (!isset($wp->query_vars['player'])) {
                return;
            }

            $player_slug = $wp->query_vars['player'];
            $player_post = get_page_by_path($player_slug, OBJECT, 'player');

            if ($player_post && $player_post instanceof WP_Post) {
                $wp->query_vars['name'] = $player_slug;
                $wp->query_vars['post_type'] = 'player';
                unset($wp->query_vars['player']);
                $wp->query_vars['p'] = $player_post->ID;
                $wp->is_single = true;
                $wp->is_page = false;
                $wp->is_archive = false;
                $wp->is_404 = false;
                $wp->requested_query_id = $player_post->ID;
            }
        }

        public function load_player_template(string $template): string {
            if (is_singular('player')) {
                $plugin_template = SPP_PLUGIN_DIR . 'spp/player-profile.php';
                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
            return $template;
        }

        /**
         * Customize document <title> parts for player and team pages.
         *
         * @param array<string, string> $title Title parts.
         * @return array<string, string>
         */
        public function filter_document_title(array $title): array {
            if (is_singular('player')) {
                $post_id  = get_queried_object_id();
                $sport    = (string) spp_get_player_meta($post_id, 'sport', '');
                $position = (string) spp_get_player_meta($post_id, 'playing_position', '');
                $suffix   = trim($sport . ' ' . $position);
                if ($suffix !== '') {
                    $title['title'] = get_the_title($post_id) . ' - ' . $suffix;
                }
            } elseif (is_singular('team')) {
                $post_id = get_queried_object_id();
                $sport   = (string) get_post_meta($post_id, 'sport', true);
                if ($sport !== '') {
                    $title['title'] = get_the_title($post_id) . ' - ' . $sport . ' ' . __('Team', 'sports-player-platform');
                }
            } elseif (is_post_type_archive('player')) {
                $title['title'] = __('Player Directory', 'sports-player-platform');
            } elseif (is_post_type_archive('team')) {
                $title['title'] = __('Teams Directory', 'sports-player-platform');
            }

            return $title;
        }

        /**
         * Output meta description, OpenGraph, Twitter Card, canonicals, and JSON-LD schema.
         */
        public function add_seo_metadata(): void {
            $site_name = get_bloginfo('name') ?: __('Sports Player Platform', 'sports-player-platform');

            if (is_post_type_archive(['player', 'team'])) {
                $is_player   = is_post_type_archive('player');
                $paged       = max(1, (int) get_query_var('paged'));
                $canonical   = get_pagenum_link($paged);
                $og_title    = ($is_player ? __('Player Directory', 'sports-player-platform') : __('Teams Directory', 'sports-player-platform')) . ' - ' . $site_name;
                $description = $is_player
                    ? __('Browse verified athlete profiles, stats, highlights, and recruiting information.', 'sports-player-platform')
                    : __('Explore sports teams, rosters, and club profiles.', 'sports-player-platform');

                echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
                $this->output_social_meta('website', $og_title, $description, $canonical, '');
                return;
            }

            if (!is_singular(['player', 'team'])) {
                return;
            }

            $post = get_queried_object();
            if (!$post instanceof WP_Post) {
                return;
            }

            $url   = (string) get_permalink($post->ID);
            $image = (string) get_the_post_thumbnail_url($post->ID, 'large');

            if ($post->post_type === 'player') {
                $position  = (string) spp_get_player_meta($post->ID, 'playing_position', '');
                $sport     = (string) spp_get_player_meta($post->ID, 'sport', '');
                $grad_year = (string) spp_get_player_meta($post->ID, 'graduation_year', '');
                $school    = (string) spp_get_player_meta($post->ID, 'school', '');
                $club      = (string) spp_get_player_meta($post->ID, 'club', '');

                $title = trim($post->post_title . ($sport !== '' ? ' - ' . $sport . ' Player' : '')) . ' - ' . $site_name;
                $description = $position !== ''
                    ? sprintf(__('Profile of %s, %s in %s%s.', 'sports-player-platform'), $post->post_title, $position, $sport ?: __('sports', 'sports-player-platform'), $grad_year !== '' ? ' (Class of ' . $grad_year . ')' : '')
                    : sprintf(__('Official athlete profile of %s.', 'sports-player-platform'), $post->post_title);

                $this->output_social_meta('profile', $title, $description, $url, $image);

                $schema = array_filter([
                    '@context'    => 'https://schema.org',
                    '@type'       => 'Person',
                    'name'        => $post->post_title,
                    'url'         => $url,
                    'description' => $description,
                    'image'       => $image ?: null,
                    'jobTitle'    => $position ?: null,
                    'knowsAbout'  => $sport ?: null,
                    'alumniOf'    => $school ? ['@type' => 'EducationalOrganization', 'name' => $school] : null,
                    'memberOf'    => $club ? ['@type' => 'SportsTeam', 'name' => $club] : null,
                ]);

                echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
                return;
            }

            // Team singular SEO + SportsTeam JSON-LD.
            $sport       = (string) get_post_meta($post->ID, 'sport', true);
            $location    = (string) get_post_meta($post->ID, 'location', true);
            $title       = $post->post_title . ($sport !== '' ? ' - ' . $sport . ' Team' : '') . ' - ' . $site_name;
            $description = sprintf(
                __('Official team profile and roster for %s%s.', 'sports-player-platform'),
                $post->post_title,
                $sport !== '' ? ' (' . $sport . ')' : ''
            );

            $this->output_social_meta('website', $title, $description, $url, $image);

            $schema = array_filter([
                '@context'    => 'https://schema.org',
                '@type'       => 'SportsTeam',
                'name'        => $post->post_title,
                'url'         => $url,
                'description' => $description,
                'sport'       => $sport ?: null,
                'logo'        => $image ?: null,
                'location'    => $location ? ['@type' => 'Place', 'name' => $location] : null,
            ]);

            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }

        /**
         * Output standard meta description, OpenGraph, and Twitter Card tags.
         */
        private function output_social_meta(string $og_type, string $title, string $description, string $url, string $image): void {
            $site_name = get_bloginfo('name') ?: __('Sports Player Platform', 'sports-player-platform');

            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
            echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
            echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
            if ($image !== '') {
                echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
                echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
                echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
            } else {
                echo '<meta name="twitter:card" content="summary">' . "\n";
            }
            echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        }

        /**
         * Ensure player, team, and article post types are included in WordPress core XML sitemaps.
         *
         * @param array<string, WP_Post_Type> $post_types Registered public post types.
         * @return array<string, WP_Post_Type>
         */
        public function filter_sitemap_post_types(array $post_types): array {
            foreach (['player', 'team', 'spp_article'] as $cpt) {
                $obj = get_post_type_object($cpt);
                if ($obj && $obj->public) {
                    $post_types[$cpt] = $obj;
                }
            }
            return $post_types;
        }

        /**
         * Restrict player sitemap entries strictly to published profiles.
         *
         * @param array<string, mixed> $args      WP_Query arguments.
         * @param string               $post_type Post type slug.
         * @return array<string, mixed>
         */
        public function filter_sitemap_query_args(array $args, string $post_type): array {
            if (in_array($post_type, ['player', 'team'], true)) {
                $args['post_status'] = ['publish'];
            }
            return $args;
        }

        public function handle_events_embed(): void {
            if (is_home() || is_front_page()) {
                // The events page will use shortcode
            }
        }

        public function create_articles_post_type(): void {
            register_post_type('spp_article', [
                'labels' => [
                    'name' => __('Articles', 'sports-player-platform'),
                    'singular_name' => __('Article', 'sports-player-platform'),
                    'add_new' => __('Add New', 'sports-player-platform'),
                    'add_new_item' => __('Add New Article', 'sports-player-platform'),
                    'edit_item' => __('Edit Article', 'sports-player-platform'),
                ],
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
                'has_archive' => true,
                'rewrite' => ['slug' => 'articles'],
            ]);
        }

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
    }}
