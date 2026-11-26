<?php
/**
 * Sports Player Platform Helper Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('spp_get_user_membership')) {
    function spp_get_user_membership(int $user_id): ?array {
        if (class_exists('SPP_Membership')) {
            return SPP_Membership::get_instance()->get_user_membership($user_id);
        }
        return null;
    }
}

if (!function_exists('spp_is_member')) {
    function spp_is_member(int $user_id, string $required_type = null): bool {
        if (class_exists('SPP_Membership')) {
            return SPP_Membership::get_instance()->is_member($user_id, $required_type);
        }
        return false;
    }
}

if (!function_exists('spp_user_has_role')) {
    function spp_user_has_role(string $role): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        return in_array($role, $user->roles);
    }
}

if (!function_exists('spp_can_view_player')) {
    function spp_can_view_player(int $player_id): bool {
        $post = get_post($player_id);
        if (!$post) {
            return false;
        }

        // Published players are visible to everyone
        if ($post->post_status === 'publish') {
            return true;
        }

        // Pending players are only visible to admins and the player themselves
        if ($post->post_status === 'spp_pending') {
            if (!is_user_logged_in()) {
                return false;
            }
            if (current_user_can('edit_posts')) {
                return true;
            }
            if (get_current_user_id() === $post->post_author) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('spp_get_player_meta')) {
    /**
     * Get player post meta respecting public/private field visibility.
     *
     * @param int    $player_id Player post ID.
     * @param string $key       Meta key.
     * @param mixed  $default   Default value when unauthorized or empty.
     * @return mixed
     */
    function spp_get_player_meta(int $player_id, string $key, $default = null) {
        $public_fields = [
            'location',
            'graduation_year',
            'school',
            'sport',
            'playing_position',
            'height',
            'weight',
            'image_gallery',
            'videos_highlights',
            'social_media',
        ];

        if (in_array($key, $public_fields, true) || is_user_logged_in()) {
            $value = get_post_meta($player_id, $key, true);
            if ($value !== '' && $value !== false) {
                return is_string($value) ? sanitize_text_field($value) : $value;
            }
        }

        return $default;
    }
}

if (!function_exists('spp_get_current_user_role')) {
    /**
     * Get the primary role of the current logged-in user (or null if guest).
     */
    function spp_get_current_user_role(): ?string {
        if (!is_user_logged_in()) {
            return null;
        }
        $user = wp_get_current_user();
        return !empty($user->roles) ? (string) reset($user->roles) : null;
    }
}

if (!function_exists('spp_user_has_membership')) {
    /**
     * Check whether a user has an active membership (optionally of a specific type or 'paid').
     */
    function spp_user_has_membership(int $user_id, ?string $type = null): bool {
        if (!class_exists('SPP_Membership')) {
            return false;
        }

        $membership = SPP_Membership::get_instance()->get_user_membership($user_id);
        if (!$membership || ($membership['status'] ?? '') !== SPP_Membership::STATUS_ACTIVE) {
            return false;
        }

        if (!empty($membership['expires_at']) && strtotime($membership['expires_at']) < current_time('timestamp')) {
            return false;
        }

        if ($type === null) {
            return true;
        }

        if ($type === 'paid') {
            return in_array($membership['type'] ?? '', [SPP_Membership::TYPE_MONTHLY, SPP_Membership::TYPE_ANNUAL], true);
        }

        return ($membership['type'] ?? '') === $type;
    }
}

if (!function_exists('spp_get_avatar_url')) {
    /**
     * Get user's custom avatar URL if set, falling back to WordPress Gravatar.
     */
    function spp_get_avatar_url(int $user_id, int $size = 96): string {
        $custom_avatar = get_user_meta($user_id, 'spp_avatar_url', true);
        if (!empty($custom_avatar) && filter_var($custom_avatar, FILTER_VALIDATE_URL)) {
            return esc_url_raw($custom_avatar);
        }

        return (string) get_avatar_url($user_id, ['size' => $size]);
    }
}

if (!function_exists('spp_format_date')) {
    /**
     * Format a date string using WordPress localized date format.
     */
    function spp_format_date(string $date, string $format = ''): string {
        if (empty($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        $target_format = $format !== '' ? $format : (string) get_option('date_format', 'Y-m-d');
        return date_i18n($target_format, $timestamp);
    }
}

if (!function_exists('spp_log')) {
    /**
     * Write a debug log entry when WP_DEBUG is enabled.
     */
    function spp_log(string $message, string $level = 'info', array $context = []): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $prefix = '[SPP][' . strtoupper($level) . '] ';
        $suffix = !empty($context) ? ' ' . wp_json_encode($context) : '';
        error_log($prefix . $message . $suffix); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}