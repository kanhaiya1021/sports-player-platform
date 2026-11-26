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
    function spp_get_player_meta(int $player_id, string $key, bool $default = null) {
        $public_fields = ['location', 'graduation_year', 'school', 'sport', 'playing_position', 'height', 'weight', 'image_gallery', 'videos_highlights', 'social_media'];

        if (in_array($key, $public_fields) || is_user_logged_in()) {
            return get_post_meta($player_id, $key, $default);
        }

        return $default;
    }
}