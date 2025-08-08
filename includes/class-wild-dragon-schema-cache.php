<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wild_Dragon_Schema_Cache {

    public static function clear_schema_cache($key_prefix = '') {
        global $wpdb;

        $like = !empty($key_prefix) ? $wpdb->esc_like($key_prefix) . '%' : 'wild_dragon_schema_%';

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `$wpdb->options` WHERE `option_name` LIKE %s",
                $like
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `$wpdb->options` WHERE `option_name` LIKE %s",
                $wpdb->esc_like('_transient_timeout_wild_dragon_schema_') . '%'
            )
        );
    }

    public static function clear_all_schema_caches() {
        self::clear_schema_cache();
        
        // Also clear any WordPress object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear any page caching plugins
        self::clear_page_cache();
    }

    /**
     * Clear popular caching plugins
     */
    public static function clear_page_cache() {
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        // LiteSpeed Cache
        if (class_exists('LiteSpeed_Cache_API')) {
            LiteSpeed_Cache_API::purge_all();
        }

        // WP Fastest Cache
        if (class_exists('WpFastestCache')) {
            $cache = new WpFastestCache();
            $cache->deleteCache(true);
        }

        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
        }
    }

    /**
     * Add admin notice after cache clear
     */
    public static function add_cache_cleared_notice() {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Wild Dragon Schema:</strong> All schema caches have been cleared successfully!</p>';
            echo '</div>';
        });
    }
}