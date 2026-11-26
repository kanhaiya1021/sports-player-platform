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
            add_action('wp_head', [$this, 'add_seo_metadata']);
            add_action('template_redirect', [$this, 'handle_events_embed']);
            // Register post type early to ensure it's available in admin
            add_action('init', [$this, 'create_articles_post_type'], 0);
        }

        public function add_seo_metadata(): void {
            if (!is_singular()) {
                return;
            }

            global $post;

            if ($post->post_type === 'player') {
                $position = get_post_meta($post->ID, 'playing_position', true);
                $sport = get_post_meta($post->ID, 'sport', true);
                $grad_year = get_post_meta($post->ID, 'graduation_year', true);
                $school = get_post_meta($post->ID, 'school', true);

                $title = $post->post_title . ' - ' . $sport . ' Player - Sports Player Platform';
                $description = $position ? "Profile of " . $post->post_title . ", " . $position . " for " . $sport . ". Class of " . $grad_year . "." : "Profile of " . $post->post_title . ", " . $sport . " player.";

                echo '<meta name="title" content="' . esc_attr($title) . '">' . "\n";
                echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";

                // Structured data for player profile
                echo '<script type="application/ld+json">' . json_encode([
                    '@context' => 'https://schema.org',
                    '@type' => 'Person',
                    'name' => $post->post_title,
                    'jobTitle' => $position ?? null,
                    'sportsTeam' => $sport,
                ], JSON_PRETTY_PRINT) . '</script>' . "\n";
            }

            if ($post->post_type === 'team') {
                $title = $post->post_title . ' - Team Profile - Sports Player Platform';

                echo '<meta name="title" content="' . esc_attr($title) . '">' . "\n";
                echo '<meta name="description" content="Official profile for ' . esc_attr($post->post_title) . '.">' . "\n";
            }
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
