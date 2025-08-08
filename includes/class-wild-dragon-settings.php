<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wild_Dragon_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_init', [__CLASS__, 'handle_cache_clear']);
        
        // Add material meta box to products
        add_action('add_meta_boxes', [__CLASS__, 'add_material_meta_box']);
        add_action('save_post', [__CLASS__, 'save_material_meta']);
    }

    public static function handle_cache_clear() {
        if (isset($_POST['clear_schema_cache']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_schema_cache')) {
            Wild_Dragon_Schema_Cache::clear_all_schema_caches();
            Wild_Dragon_Schema_Cache::add_cache_cleared_notice();
            
            // Redirect to prevent resubmission
            wp_redirect(admin_url('options-general.php?page=wild-dragon-schema&cache_cleared=1'));
            exit;
        }
    }

    public static function add_admin_menu() {
        add_options_page(
            'Wild Dragon Schema Settings',
            'Schema Settings',
            'manage_options',
            'wild-dragon-schema',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Add material meta box to product edit page
     */
    public static function add_material_meta_box() {
        add_meta_box(
            'wild_dragon_material',
            'Product Material',
            [__CLASS__, 'render_material_meta_box'],
            'product',
            'side',
            'default'
        );
    }

    /**
     * Render material meta box
     */
    public static function render_material_meta_box($post) {
        wp_nonce_field('wild_dragon_material_meta', 'wild_dragon_material_nonce');
        
        $material = get_post_meta($post->ID, '_wild_dragon_material', true);
        $default_material = get_option('wild_dragon_default_material', '100% Cotton');
        
        echo '<div style="margin: 10px 0;">';
        echo '<label for="wild_dragon_material"><strong>Material:</strong></label><br>';
        echo '<input type="text" id="wild_dragon_material" name="wild_dragon_material" value="' . esc_attr($material) . '" style="width: 100%; margin-top: 5px;" placeholder="' . esc_attr($default_material) . '">';
        echo '<p class="description">Leave blank to use default: ' . esc_html($default_material) . '</p>';
        echo '</div>';
    }

    /**
     * Save material meta
     */
    public static function save_material_meta($post_id) {
        // Check nonce
        if (!isset($_POST['wild_dragon_material_nonce']) || !wp_verify_nonce($_POST['wild_dragon_material_nonce'], 'wild_dragon_material_meta')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check post type
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        // Save material
        $material = isset($_POST['wild_dragon_material']) ? sanitize_text_field($_POST['wild_dragon_material']) : '';
        update_post_meta($post_id, '_wild_dragon_material', $material);
        
        // Clear schema cache when material is updated
        Wild_Dragon_Schema_Cache::clear_all_schema_caches();
    }

    public static function register_settings() {
        // Organization settings
        register_setting('wild_dragon_schema', 'wild_dragon_organization_name', [
            'type' => 'string',
            'default' => 'Veirdo',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_logo_url', [
            'type' => 'string',
            'default' => 'https://cdn.shopify.com/s/files/1/1982/7331/files/veirdologotrans_180x.png', 
            'sanitize_callback' => 'esc_url_raw'
        ]);

        // Social media settings
        register_setting('wild_dragon_schema', 'wild_dragon_facebook_url', [
            'type' => 'string',
            'default' => 'https://www.facebook.com/profile.php?id=100076398736432',
            'sanitize_callback' => 'esc_url_raw'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_twitter_url', [
            'type' => 'string',
            'default' => 'https://twitter.com/VeirdoVenture',
            'sanitize_callback' => 'esc_url_raw'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_instagram_url', [
            'type' => 'string',
            'default' => 'https://www.instagram.com/veirdo.in/',
            'sanitize_callback' => 'esc_url_raw'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_youtube_url', [
            'type' => 'string',
            'default' => 'https://www.youtube.com/channel/UCZUkqeonhghcFbLS9VxD8EQ',
            'sanitize_callback' => 'esc_url_raw'
        ]);

        // Contact settings
        register_setting('wild_dragon_schema', 'wild_dragon_contact_number', [
            'type' => 'string',
            'default' => '+91-6352449482',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_contact_type', [
            'type' => 'string',
            'default' => 'Customer Service',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        // Material settings
        register_setting('wild_dragon_schema', 'wild_dragon_default_material', [
            'type' => 'string',
            'default' => '100% Cotton',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        // Shipping settings
        register_setting('wild_dragon_schema', 'wild_dragon_shipping_rate', [
            'type' => 'string',
            'default' => '0',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_shipping_country', [
            'type' => 'string',
            'default' => 'IN',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_handling_time_min', [
            'type' => 'integer',
            'default' => 1,
            'sanitize_callback' => 'intval'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_handling_time_max', [
            'type' => 'integer',
            'default' => 2,
            'sanitize_callback' => 'intval'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_transit_time_min', [
            'type' => 'integer',
            'default' => 4,
            'sanitize_callback' => 'intval'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_transit_time_max', [
            'type' => 'integer',
            'default' => 7,
            'sanitize_callback' => 'intval'
        ]);

        // Return policy settings
        register_setting('wild_dragon_schema', 'wild_dragon_return_country', [
            'type' => 'string',
            'default' => 'IN',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_return_days', [
            'type' => 'integer',
            'default' => 14,
            'sanitize_callback' => 'intval'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_return_fees', [
            'type' => 'string',
            'default' => 'https://schema.org/FreeReturn',
            'sanitize_callback' => 'sanitize_text_field'
        ]);

        // FAQ settings
        register_setting('wild_dragon_schema', 'wild_dragon_faq_processing_time', [
            'type' => 'string',
            'default' => 'The estimated order processing time is 24 to 48 hours.',
            'sanitize_callback' => 'sanitize_textarea_field'
        ]);

        register_setting('wild_dragon_schema', 'wild_dragon_faq_delivery_time', [
            'type' => 'string',
            'default' => 'The estimated time of delivery is 4 to 7 days, depending on your location.',
            'sanitize_callback' => 'sanitize_textarea_field'
        ]);
    }

    public static function render_settings_page() {
        // Show success message if cache was cleared
        if (isset($_GET['cache_cleared'])) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Success!</strong> All schema caches have been cleared. Your changes should now be visible.</p>';
            echo '</div>';
        }
        ?>
        <div class="wrap">
            <h1>Wild Dragon Schema Settings</h1>
            
            <!-- Cache Clear Section -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 class="title">🔄 Clear Schema Cache</h2>
                <p>If you don't see your schema changes, clear the cache to force regeneration:</p>
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('clear_schema_cache'); ?>
                    <input type="submit" name="clear_schema_cache" class="button button-secondary" 
                           value="Clear All Schema Cache" 
                           onclick="return confirm('This will clear all cached schema data. Continue?');">
                </form>
                <p class="description">This will clear all cached schema data and force regeneration on next page load.</p>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('wild_dragon_schema');
                do_settings_sections('wild_dragon_schema');
                ?>

                <div class="nav-tab-wrapper">
                    <a href="#organization" class="nav-tab nav-tab-active">Organization</a>
                    <a href="#product" class="nav-tab">Product Settings</a>
                    <a href="#shipping" class="nav-tab">Shipping & Returns</a>
                    <a href="#faq" class="nav-tab">FAQ Settings</a>
                </div>

                <div id="organization" class="tab-content">
                    <h2>Organization Information</h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="wild_dragon_organization_name">Organization Name</label></th>
                                <td>
                                    <input name="wild_dragon_organization_name" type="text"
                                           id="wild_dragon_organization_name"
                                           class="regular-text"
                                           value="<?php echo esc_attr(get_option('wild_dragon_organization_name')); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wild_dragon_logo_url">Logo URL</label></th>
                                <td>
                                    <input name="wild_dragon_logo_url" type="text"
                                           id="wild_dragon_logo_url"
                                           class="regular-text"
                                           value="<?php echo esc_url(get_option('wild_dragon_logo_url')); ?>">
                                    <p class="description">Full URL to your organization's logo image</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wild_dragon_facebook_url">Facebook URL</label></th>
                                <td>
                                    <input name="wild_dragon_facebook_url" type="text"
                                           id="wild_dragon_facebook_url"
                                           class="regular-text"
                                           value="<?php echo esc_url(get_option('wild_dragon_facebook_url')); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wild_dragon_twitter_url">Twitter URL</label></th>
                                <td>
                                    <input name="wild_dragon_twitter_url" type="text"
                                           id="wild_dragon_twitter_url"
                                           class="regular-text"
                                           value="<?php echo esc_url(get_option('wild_dragon_twitter_url')); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wild_dragon_instagram_url">Instagram URL</label></th>
                                <td>
                                    <input name="wild_dragon_instagram_url" type="text"
                                           id="wild_dragon_instagram_url"
                                           class="regular-text"
                                           value="<?php echo esc_url(get_option('wild_dragon_instagram_url')); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wild_dragon_youtube_url">YouTube Channel URL</label></th>
                                <td>
                                    <input name="wild_dragon_youtube_url" type="text"
                                           id="wild_dragon_youtube_url"
                                           class="regular-text"
                                           value="<?php echo esc_url(get_option('wild_dragon_youtube_url')); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wild_dragon_contact_number">Contact Number</label></th>
                                <td>
                                    <input name="wild_dragon_contact_number" type="text"
                                           id="wild_dragon_contact_number"
                                           class="regular-text"
                                           value="<?php echo esc_attr(get_option('wild_dragon_contact_number')); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wild_dragon_contact_type">Contact Type</label></th>
                                <td>
                                    <input name="wild_dragon_contact_type" type="text"
                                           id="wild_dragon_contact_type"
                                           class="regular-text"
                                           value="<?php echo esc_attr(get_option('wild_dragon_contact_type')); ?>">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="product" class="tab-content" style="display:none;">
                    <h2>Product Settings</h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="wild_dragon_default_material">Default Material</label></th>
                                <td>
                                    <input name="wild_dragon_default_material" type="text"
                                           id="wild_dragon_default_material"
                                           class="regular-text"
                                           value="<?php echo esc_attr(get_option('wild_dragon_default_material')); ?>">
                                    <p class="description">Default material for products (e.g., "100% Cotton"). Can be overridden per product.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="card">
                        <h3>📝 How to Set Product Material</h3>
                        <p>You can set material for individual products in two ways:</p>
                        <ol>
                            <li><strong>Per Product:</strong> Edit any product and look for the "Product Material" box in the sidebar</li>
                            <li><strong>Product Attributes:</strong> Use the "pa_material" attribute in WooCommerce</li>
                            <li><strong>Default:</strong> If neither is set, the default material above will be used</li>
                        </ol>
                        <p><strong>Priority:</strong> Custom field → Product attribute → Default material</p>
                    </div>
                </div>

                <div id="shipping" class="tab-content" style="display:none;">
                    <h2>Shipping & Return Policy</h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="wild_dragon_shipping_rate">Shipping Rate</label></th>
                                <td>
                                    <input name="wild_dragon_shipping_rate" type="number"
                                           id="wild_dragon_shipping_rate"
                                           class="small-text"
                                           value="<?php echo esc_attr(get_option('wild_dragon_shipping_rate')); ?>">
                                    <p class="description">Enter 0 for free shipping</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wild_dragon_shipping_country">Shipping Country</label></th>
                                <td>
                                    <input name="wild_dragon_shipping_country" type="text"
                                           id="wild_dragon_shipping_country"
                                           class="small-text"
                                           value="<?php echo esc_attr(get_option('wild_dragon_shipping_country')); ?>">
                                    <p class="description">Country code (e.g., IN for India, US for United States)</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Handling Time</th>
                                <td>
                                    <input name="wild_dragon_handling_time_min" type="number"
                                           class="small-text"
                                           value="<?php echo esc_attr(get_option('wild_dragon_handling_time_min')); ?>">
                                    to
                                    <input name="wild_dragon_handling_time_max" type="number"
                                           class="small-text"
                                           value="<?php echo esc_attr(get_option('wild_dragon_handling_time_max')); ?>">
                                    days
                                    <p class="description">Time to process orders before shipping</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Transit Time</th>
                                <td>
                                    <input name="wild_dragon_transit_time_min" type="number"
                                           class="small-text"
                                           value="<?php echo esc_attr(get_option('wild_dragon_transit_time_min')); ?>">
                                    to
                                    <input name="wild_dragon_transit_time_max" type="number"
                                           class="small-text"
                                           value="<?php echo esc_attr(get_option('wild_dragon_transit_time_max')); ?>">
                                    days
                                    <p class="description">Shipping/delivery time</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wild_dragon_return_days">Return Period</label></th>
                                <td>
                                    <input name="wild_dragon_return_days" type="number"
                                           id="wild_dragon_return_days"
                                           class="small-text"
                                           value="<?php echo esc_attr(get_option('wild_dragon_return_days')); ?>">
                                    days
                                    <p class="description">Number of days customers have to return items</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wild_dragon_return_fees">Return Fees</label></th>
                                <td>
                                    <select name="wild_dragon_return_fees" id="wild_dragon_return_fees">
                                        <option value="https://schema.org/FreeReturn" <?php selected(get_option('wild_dragon_return_fees'), 'https://schema.org/FreeReturn'); ?>>Free Return</option>
                                        <option value="https://schema.org/ReturnFeesCustomerResponsibility" <?php selected(get_option('wild_dragon_return_fees'), 'https://schema.org/ReturnFeesCustomerResponsibility'); ?>>Customer Pays Return Fees</option>
                                        <option value="https://schema.org/RestockingFee" <?php selected(get_option('wild_dragon_return_fees'), 'https://schema.org/RestockingFee'); ?>>Restocking Fee</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="faq" class="tab-content" style="display:none;">
                    <h2>FAQ Settings</h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="wild_dragon_faq_processing_time">Processing Time Answer</label></th>
                                <td>
                                    <textarea name="wild_dragon_faq_processing_time" 
                                              id="wild_dragon_faq_processing_time"
                                              class="large-text"
                                              rows="3"><?php echo esc_textarea(get_option('wild_dragon_faq_processing_time')); ?></textarea>
                                    <p class="description">Answer for "What is the Estimated Order Processing Time?"</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="wild_dragon_faq_delivery_time">Delivery Time Answer</label></th>
                                <td>
                                    <textarea name="wild_dragon_faq_delivery_time" 
                                              id="wild_dragon_faq_delivery_time"
                                              class="large-text"
                                              rows="3"><?php echo esc_textarea(get_option('wild_dragon_faq_delivery_time')); ?></textarea>
                                    <p class="description">Answer for "How long does delivery take?"</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php submit_button('Save Settings'); ?>
            </form>

            <script>
            jQuery(document).ready(function($) {
                $('.nav-tab').click(function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs
                    $('.nav-tab').removeClass('nav-tab-active');
                    $('.tab-content').hide();
                    
                    // Add active class to clicked tab
                    $(this).addClass('nav-tab-active');
                    
                    // Show corresponding content
                    var target = $(this).attr('href');
                    $(target).show();
                });
            });
            </script>

            <style>
            .tab-content {
                margin-top: 20px;
            }
            .nav-tab-wrapper {
                margin-bottom: 0;
            }
            .card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin-top: 20px;
            }
            .card .title {
                margin-top: 0;
                font-size: 1.3em;
            }
            .card h3 {
                margin-top: 0;
                color: #1d2327;
            }
            </style>
        </div>
        <?php
    }

    public static function get_default_settings() {
        return [
            'wild_dragon_organization_name' => 'Veirdo',
            'wild_dragon_logo_url' => 'https://cdn.shopify.com/s/files/1/1982/7331/files/veirdologotrans_180x.png',
            'wild_dragon_facebook_url' => 'https://www.facebook.com/profile.php?id=100076398736432',
            'wild_dragon_twitter_url' => 'https://twitter.com/VeirdoVenture',
            'wild_dragon_instagram_url' => 'https://www.instagram.com/veirdo.in/',
            'wild_dragon_youtube_url' => 'https://www.youtube.com/channel/UCZUkqeonhghcFbLS9VxD8EQ',
            'wild_dragon_contact_number' => '+91-6352449482',
            'wild_dragon_contact_type' => 'Customer Service',
            'wild_dragon_default_material' => '100% Cotton',
            'wild_dragon_shipping_rate' => '0',
            'wild_dragon_shipping_country' => 'IN',
            'wild_dragon_handling_time_min' => 1,
            'wild_dragon_handling_time_max' => 2,
            'wild_dragon_transit_time_min' => 4,
            'wild_dragon_transit_time_max' => 7,
            'wild_dragon_return_country' => 'IN',
            'wild_dragon_return_days' => 14,
            'wild_dragon_return_fees' => 'https://schema.org/FreeReturn',
            'wild_dragon_faq_processing_time' => 'The estimated order processing time is 24 to 48 hours.',
            'wild_dragon_faq_delivery_time' => 'The estimated time of delivery is 4 to 7 days, depending on your location.'
        ];
    }
}

Wild_Dragon_Settings::init();