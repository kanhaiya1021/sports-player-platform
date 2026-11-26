<?php
/**
 * Core module bootstrap for Sports Player Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load core functionality
if (!class_exists('SPP_Core')) {
    final class SPP_Core {

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
            add_action('spp_loaded', [$this, 'on_plugin_loaded']);
        }

        public function on_plugin_loaded(): void {
            // Core module initialized
        }
    }
}

SPP_Core::get_instance();