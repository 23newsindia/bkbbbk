<?php
/**
 * Plugin Name: Wild Dragon Schema
 * Plugin URI: https://wilddragon.in 
 * Description: Adds structured data (schema.org) for Home, Category & Product pages on WooCommerce.
 * Version: 1.0.3
 * Author: Wild Dragon Dev Team
 * Author URI: https://wilddragon.in 
 * License: GPL2+
 * Text Domain: wild-dragon-schema
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('WILD_DRAGON_SCHEMA_VERSION', '1.0.3');
define('WILD_DRAGON_SCHEMA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WILD_DRAGON_SCHEMA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Wild Dragon Schema:</strong> WooCommerce is required for this plugin to work.</p></div>';
    });
    return;
}

// Include schema generator class
require_once WILD_DRAGON_SCHEMA_PLUGIN_DIR . 'includes/class-wild-dragon-schema-generator.php';

// Include settings class
require_once WILD_DRAGON_SCHEMA_PLUGIN_DIR . 'includes/class-wild-dragon-settings.php';

// Include cache class
require_once WILD_DRAGON_SCHEMA_PLUGIN_DIR . 'includes/class-wild-dragon-schema-cache.php';

/**
 * Outputs schema JSON-LD in head section based on current page type.
 */
// Enable debug mode?
define('WILD_DRAGON_SCHEMA_DEBUG', false); // Set to true to disable schema output

function wild_dragon_output_schema_jsonld() {
    if (defined('WILD_DRAGON_SCHEMA_DEBUG') && WILD_DRAGON_SCHEMA_DEBUG) {
        return;
    }

    $generator = new Wild_Dragon_Schema_Generator();

    if (is_front_page()) {
        echo $generator->get_cached_schema('homepage');
    } elseif (is_product_category()) {
        echo $generator->get_cached_schema('category_page');
    } elseif (is_product()) {
        echo $generator->get_cached_schema('product_page');
    }
}
add_action('wp_head', 'wild_dragon_output_schema_jsonld');

/**
 * Clear cache when products are updated
 */
function wild_dragon_clear_product_cache($post_id) {
    if (get_post_type($post_id) === 'product') {
        Wild_Dragon_Schema_Cache::clear_all_schema_caches(); // Clear all cache when product updates
    }
}
add_action('save_post', 'wild_dragon_clear_product_cache');
add_action('woocommerce_update_product', 'wild_dragon_clear_product_cache');

/**
 * Clear cache when settings are updated
 */
function wild_dragon_clear_cache_on_settings_update() {
    Wild_Dragon_Schema_Cache::clear_all_schema_caches();
}
add_action('update_option_wild_dragon_organization_name', 'wild_dragon_clear_cache_on_settings_update');
add_action('update_option_wild_dragon_logo_url', 'wild_dragon_clear_cache_on_settings_update');
add_action('update_option_wild_dragon_faq_processing_time', 'wild_dragon_clear_cache_on_settings_update');
add_action('update_option_wild_dragon_faq_delivery_time', 'wild_dragon_clear_cache_on_settings_update');
add_action('update_option_wild_dragon_default_material', 'wild_dragon_clear_cache_on_settings_update');

/**
 * Add admin bar cache clear button
 */
function wild_dragon_admin_bar_cache_clear($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $wp_admin_bar->add_node([
        'id' => 'wild-dragon-clear-cache',
        'title' => '🔄 Clear Schema Cache',
        'href' => wp_nonce_url(admin_url('admin-post.php?action=wild_dragon_clear_cache'), 'clear_schema_cache'),
        'meta' => [
            'title' => 'Clear Wild Dragon Schema Cache'
        ]
    ]);
}
add_action('admin_bar_menu', 'wild_dragon_admin_bar_cache_clear', 100);

/**
 * Handle admin bar cache clear
 */
function wild_dragon_handle_admin_cache_clear() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'clear_schema_cache')) {
        wp_die('Unauthorized');
    }

    Wild_Dragon_Schema_Cache::clear_all_schema_caches();
    
    // Force regeneration by adding timestamp to cache key
    update_option('wild_dragon_schema_force_refresh', time());
    
    wp_redirect(wp_get_referer() ?: home_url());
    exit;
}
add_action('admin_post_wild_dragon_clear_cache', 'wild_dragon_handle_admin_cache_clear');

/**
 * Register activation hook
 */
function wild_dragon_schema_activate() {
    // Check WooCommerce dependency
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Wild Dragon Schema requires WooCommerce to be installed and activated.');
    }
    
    // Ensure default settings exist
    $defaults = Wild_Dragon_Settings::get_default_settings();
    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            update_option($key, $value);
        }
    }
    
    // Clear any existing cache
    Wild_Dragon_Schema_Cache::clear_all_schema_caches();
    
    // Force refresh
    update_option('wild_dragon_schema_force_refresh', time());
}
register_activation_hook(__FILE__, 'wild_dragon_schema_activate');

/**
 * Register deactivation hook
 */
function wild_dragon_schema_deactivate() {
    // Clear all schema cache on deactivation
    Wild_Dragon_Schema_Cache::clear_all_schema_caches();
}
register_deactivation_hook(__FILE__, 'wild_dragon_schema_deactivate');

/**
 * Add plugin action links
 */
function wild_dragon_schema_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=wild-dragon-schema') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wild_dragon_schema_action_links');

/**
 * Show admin notice if WooCommerce is not active
 */
function wild_dragon_schema_wc_missing_notice() {
    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error"><p><strong>Wild Dragon Schema:</strong> This plugin requires WooCommerce to be installed and activated.</p></div>';
    }
}
add_action('admin_notices', 'wild_dragon_schema_wc_missing_notice');