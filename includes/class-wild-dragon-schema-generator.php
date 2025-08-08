<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wild_Dragon_Schema_Generator {

    protected $cache_expiration = 12 * HOUR_IN_SECONDS;

    /**
     * Get cached schema or generate new one
     */
    public function get_cached_schema($type) {
        // For debugging - disable cache temporarily
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $method = "generate_{$type}_schema";
            if (!method_exists($this, $method)) {
                return '';
            }
            return $this->$method();
        }

        $cache_key = "wild_dragon_schema_{$type}_" . md5(get_the_permalink() . time()); // Add time to force refresh
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $method = "generate_{$type}_schema";
        if (!method_exists($this, $method)) {
            return '';
        }

        $schema = $this->$method();

        set_transient($cache_key, $schema, $this->cache_expiration);

        return $schema;
    }

    /**
     * Generates Organization schema for homepage
     */
    public function generate_homepage_schema() {
        $schema = [
            '@context' => 'https://schema.org', 
            '@type' => 'Organization',
            'name' => apply_filters('the_title', get_option('wild_dragon_organization_name', 'Veirdo')),
            'url' => home_url('/'),
            'logo' => esc_url(get_option('wild_dragon_logo_url', 'https://cdn.shopify.com/s/files/1/1982/7331/files/veirdologotrans_180x.png')), 
            'sameAs' => array_filter([
                esc_url(get_option('wild_dragon_facebook_url')),
                esc_url(get_option('wild_dragon_twitter_url')),
                esc_url(get_option('wild_dragon_instagram_url')),
                esc_url(get_option('wild_dragon_youtube_url'))
            ]),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => get_option('wild_dragon_contact_number', '+91-6352449482'),
                'contactType' => get_option('wild_dragon_contact_type', 'Customer Service')
            ]
        ];

        return $this->render_json_ld($schema);
    }

    /**
     * Generates BreadcrumbList + Organization schema for category pages
     */
    public function generate_category_page_schema() {
        global $wp_query;

        $current_term = $wp_query->get_queried_object();

        $breadcrumb = [
            '@context' => 'https://schema.org', 
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => apply_filters('the_title', get_option('wild_dragon_organization_name', 'Veirdo')),
                    'item' => [
                        '@type' => 'Thing',
                        '@id' => home_url('/')
                    ]
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $current_term->name,
                    'item' => [
                        '@type' => 'Thing',
                        '@id' => get_term_link($current_term)
                    ]
                ]
            ]
        ];

        $organization = [
            '@context' => 'https://schema.org', 
            '@type' => 'Organization',
            'name' => apply_filters('the_title', get_option('wild_dragon_organization_name', 'Veirdo')),
            'url' => home_url('/'),
            'logo' => esc_url(get_option('wild_dragon_logo_url', 'https://cdn.shopify.com/s/files/1/1982/7331/files/veirdologotrans_180x.png')), 
            'sameAs' => array_filter([
                esc_url(get_option('wild_dragon_facebook_url')),
                esc_url(get_option('wild_dragon_twitter_url')),
                esc_url(get_option('wild_dragon_instagram_url')),
                esc_url(get_option('wild_dragon_youtube_url'))
            ]),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => get_option('wild_dragon_contact_number', '+91-6352449482'),
                'contactType' => get_option('wild_dragon_contact_type', 'Customer Service')
            ]
        ];

        return $this->render_json_ld([
            '@context' => 'https://schema.org', 
            '@graph' => [$breadcrumb, $organization]
        ]);
    }

    /**
     * Generates comprehensive ProductGroup schema for product page
     */
    public function generate_product_page_schema() {
        global $post;

        $product_id = $post->ID;
        $product = wc_get_product($product_id);

        if (!$product) {
            return '';
        }

        // Handle both variable and simple products
        if ($product->is_type('variable')) {
            return $this->generate_variable_product_schema($product);
        } else {
            return $this->generate_simple_product_schema($product);
        }
    }

    /**
     * Generate schema for variable products (ProductGroup)
     */
    private function generate_variable_product_schema($product) {
        $product_id = $product->get_id();
        
        // Get CLEAN description without FAQ content
        $description = $this->get_ultra_clean_description($product);
        
        // Get product images as array
        $images = $this->get_product_images($product);
        
        // Build base product group
        $product_group = [
            '@context' => 'https://schema.org',
            '@type' => 'ProductGroup',
            'name' => $product->get_name(),
            'description' => $description,
            'url' => get_permalink($product_id),
            'image' => $images,
            'productGroupID' => (string) $product_id,
            'brand' => [
                '@type' => 'Brand',
                'name' => get_option('wild_dragon_organization_name', 'Wild Dragon')
            ],
            'hasVariant' => []
        ];

        // Add material if set
        $material = $this->get_product_material($product);
        if (!empty($material)) {
            $product_group['material'] = $material;
        }

        // Add aggregate rating ONLY if reviews exist
        $average_rating = $product->get_average_rating();
        $rating_count = $product->get_review_count();

        if ($average_rating > 0 && $rating_count > 0) {
            $product_group['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) floatval($average_rating),
                'reviewCount' => (string) intval($rating_count)
            ];

            // Add individual reviews if they exist
            $reviews = $this->get_product_reviews($product);
            if (!empty($reviews)) {
                $product_group['review'] = $reviews;
            }
        }

        // Add video if available
        $video_url = get_post_meta($product_id, '_product_video_url', true);
        if (!empty($video_url)) {
            $product_group['video'] = $this->get_video_schema($product, $video_url);
        }

        // Get variants with proper SKUs and attributes
        $variants = $product->get_available_variations();
        foreach ($variants as $variant_data) {
            $variant = new WC_Product_Variation($variant_data['variation_id']);
            $product_group['hasVariant'][] = $this->build_product_variant_with_sku($variant, $product);
        }

        // Build breadcrumb
        $breadcrumb = $this->generate_product_breadcrumb($product);

        // Organization
        $organization = $this->get_organization_schema();

        // FAQ Schema ONLY if FAQ content exists
        $faq_schema = $this->generate_faq_schema_if_exists($product);

        // Combine all schemas
        $graph = [$product_group, $breadcrumb, $organization];
        if (!empty($faq_schema)) {
            $graph[] = $faq_schema;
        }

        return $this->render_json_ld([
            '@context' => 'https://schema.org',
            '@graph' => $graph
        ]);
    }

    /**
     * Generate schema for simple products
     */
    private function generate_simple_product_schema($product) {
        $product_id = $product->get_id();
        
        // Get CLEAN description
        $description = $this->get_ultra_clean_description($product);
        
        // Get product images as array
        $images = $this->get_product_images($product);

        // Build product schema
        $product_schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'description' => $description,
            'url' => get_permalink($product_id),
            'image' => $images,
            'sku' => $product->get_sku() ?: (string) $product_id,
            'mpn' => $product->get_sku() ?: (string) $product_id,
            'brand' => [
                '@type' => 'Brand',
                'name' => get_option('wild_dragon_organization_name', 'Wild Dragon')
            ],
            'offers' => $this->build_simple_product_offer($product)
        ];

        // Add material if set
        $material = $this->get_product_material($product);
        if (!empty($material)) {
            $product_schema['material'] = $material;
        }

        // Add aggregate rating ONLY if reviews exist
        $average_rating = $product->get_average_rating();
        $rating_count = $product->get_review_count();

        if ($average_rating > 0 && $rating_count > 0) {
            $product_schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) floatval($average_rating),
                'reviewCount' => (string) intval($rating_count)
            ];

            // Add individual reviews
            $reviews = $this->get_product_reviews($product);
            if (!empty($reviews)) {
                $product_schema['review'] = $reviews;
            }
        }

        // Add product attributes
        $attributes = $this->get_product_attributes($product);
        $product_schema = array_merge($product_schema, $attributes);

        // Build other schemas
        $breadcrumb = $this->generate_product_breadcrumb($product);
        $organization = $this->get_organization_schema();
        $faq_schema = $this->generate_faq_schema_if_exists($product);

        $graph = [$product_schema, $breadcrumb, $organization];
        if (!empty($faq_schema)) {
            $graph[] = $faq_schema;
        }

        return $this->render_json_ld([
            '@context' => 'https://schema.org',
            '@graph' => $graph
        ]);
    }

    /**
     * Get product material from custom field, attribute, or default
     */
    private function get_product_material($product) {
        // First check for custom material field
        $material = get_post_meta($product->get_id(), '_wild_dragon_material', true);
        
        // If not set, check for material attribute
        if (empty($material)) {
            $material = $product->get_attribute('pa_material');
        }
        
        // If still empty, use default
        if (empty($material)) {
            $material = get_option('wild_dragon_default_material', '100% Cotton');
        }
        
        return !empty($material) ? ucwords($material) : '';
    }

    /**
     * Build product variant schema with proper SKU and attributes
     */
    private function build_product_variant_with_sku($variant, $parent_product) {
        // Get variant attributes
        $size = $variant->get_attribute('pa_size');
        $color = $variant->get_attribute('pa_color') ?: $variant->get_attribute('pa_colour');
        $material = $variant->get_attribute('pa_material');
        
        // Generate unique SKU for variant
        $parent_sku = $parent_product->get_sku() ?: (string) $parent_product->get_id();
        $variant_sku = $variant->get_sku();
        
        // If variant doesn't have SKU, create one: ParentSKU-SIZE or ParentID-VariantID
        if (empty($variant_sku)) {
            if (!empty($size)) {
                $variant_sku = $parent_sku . '-' . strtoupper($size);
            } else {
                $variant_sku = $parent_sku . '-' . $variant->get_id();
            }
        }
        
        // Clean description without FAQ content
        $clean_description = $this->get_ultra_clean_variant_description($variant, $parent_product);
        
        // Get variant image or fallback to parent
        $variant_image = wp_get_attachment_url($variant->get_image_id());
        $image_url = $variant_image ?: wp_get_attachment_url($parent_product->get_image_id());
        
        $variant_schema = [
            '@type' => 'Product',
            'name' => $parent_product->get_name() . ($size ? ' - ' . ucwords($size) : ''),
            'sku' => $variant_sku,
            'mpn' => $variant_sku,
            'description' => $clean_description,
            'image' => $image_url,
            'offers' => $this->build_variant_offer($variant)
        ];

        // Add specific attributes as structured properties
        if ($size) $variant_schema['size'] = ucwords($size);
        if ($color) $variant_schema['color'] = ucwords($color);
        
        // Add material - check variant first, then parent, then default
        $variant_material = $material;
        if (empty($variant_material)) {
            $variant_material = $this->get_product_material($parent_product);
        }
        if (!empty($variant_material)) {
            $variant_schema['material'] = $variant_material;
        }

        return $variant_schema;
    }

    /**
     * Get ULTRA clean description - removes ALL FAQ and shipping content
     */
    private function get_ultra_clean_description($product) {
        $description = $product->get_description();
        
        // Remove FAQ section completely - multiple patterns
        $description = preg_replace('/FAQ.*$/is', '', $description);
        $description = preg_replace('/Frequently Asked Questions.*$/is', '', $description);
        
        // Remove shipping/delivery info patterns - be very aggressive
        $description = preg_replace('/Estimated.*?Location/is', '', $description);
        $description = preg_replace('/\d+\s*to\s*\d+\s*Day.*?Location/is', '', $description);
        $description = preg_replace('/Time to Reached.*?Location/is', '', $description);
        $description = preg_replace('/Estimated Order Processing.*$/is', '', $description);
        $description = preg_replace('/Estimated Time of.*$/is', '', $description);
        $description = preg_replace('/\d+\s*to\s*\d+\s*hours.*$/is', '', $description);
        $description = preg_replace('/24 to 48 hours.*$/is', '', $description);
        $description = preg_replace('/4 to 7 Day.*$/is', '', $description);
        
        // Clean HTML tags
        $description = wp_strip_all_tags($description);
        
        // Clean up extra whitespace and newlines
        $description = preg_replace('/\s+/', ' ', $description);
        $description = trim($description);
        
        // If description is too short after cleaning, provide a basic one
        if (strlen($description) < 50) {
            $description = $product->get_name() . ' - High quality oversize fit t-shirt made with premium materials for comfort and style.';
        }
        
        return $description;
    }

    /**
     * Get ultra clean variant description
     */
    private function get_ultra_clean_variant_description($variant, $parent_product) {
        // Start with parent description
        $description = $parent_product->get_description();
        
        // If variant has its own description, use that
        $variant_description = $variant->get_description();
        if (!empty($variant_description)) {
            $description = $variant_description;
        }
        
        // Apply the same cleaning as the main description
        // Remove FAQ section completely - multiple patterns
        $description = preg_replace('/FAQ.*$/is', '', $description);
        $description = preg_replace('/Frequently Asked Questions.*$/is', '', $description);
        
        // Remove shipping/delivery info patterns - be very aggressive
        $description = preg_replace('/Estimated.*?Location/is', '', $description);
        $description = preg_replace('/\d+\s*to\s*\d+\s*Day.*?Location/is', '', $description);
        $description = preg_replace('/Time to Reached.*?Location/is', '', $description);
        $description = preg_replace('/Estimated Order Processing.*$/is', '', $description);
        $description = preg_replace('/Estimated Time of.*$/is', '', $description);
        $description = preg_replace('/\d+\s*to\s*\d+\s*hours.*$/is', '', $description);
        $description = preg_replace('/24 to 48 hours.*$/is', '', $description);
        $description = preg_replace('/4 to 7 Day.*$/is', '', $description);
        
        // Clean HTML tags
        $description = wp_strip_all_tags($description);
        
        // Clean up extra whitespace and newlines
        $description = preg_replace('/\s+/', ' ', $description);
        $description = trim($description);
        
        // Add variant-specific info if description is too short
        if (strlen($description) < 50) {
            $size = $variant->get_attribute('pa_size');
            $color = $variant->get_attribute('pa_color') ?: $variant->get_attribute('pa_colour');
            $material = $variant->get_attribute('pa_material');
            
            $description = $parent_product->get_name() . ': ';
            if ($size) $description .= 'Size ' . ucwords($size) . ', ';
            if ($color) $description .= 'Color ' . ucwords($color) . ', ';
            if ($material) $description .= 'Material ' . ucwords($material) . ', ';
            $description .= 'High quality oversize fit.';
        }
        
        return $description;
    }

    /**
     * Build offer for product variant
     */
    private function build_variant_offer($variant) {
        $price = $variant->get_price();
        $regular_price = $variant->get_regular_price();
        $stock_status = $variant->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

        $offer = [
            '@type' => 'Offer',
            'url' => $variant->get_permalink(),
            'priceCurrency' => get_woocommerce_currency(),
            'price' => (string) $price,
            'priceValidUntil' => date('Y-m-d', strtotime('+1 year')),
            'itemCondition' => 'https://schema.org/NewCondition',
            'availability' => $stock_status
        ];

        // Add strikethrough price if on sale
        if (!empty($regular_price) && floatval($regular_price) > floatval($price)) {
            $offer['priceSpecification'] = [
                '@type' => 'UnitPriceSpecification',
                'priceType' => 'https://schema.org/StrikethroughPrice',
                'price' => (string) $regular_price,
                'priceCurrency' => get_woocommerce_currency()
            ];
        }

        // Add shipping details
        $offer['shippingDetails'] = $this->get_shipping_details();

        // Add return policy
        $offer['hasMerchantReturnPolicy'] = $this->get_return_policy();

        return $offer;
    }

    /**
     * Build offer for simple product
     */
    private function build_simple_product_offer($product) {
        $price = $product->get_price();
        $regular_price = $product->get_regular_price();
        $stock_status = $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

        $offer = [
            '@type' => 'Offer',
            'url' => get_permalink($product->get_id()),
            'priceCurrency' => get_woocommerce_currency(),
            'price' => (string) $price,
            'priceValidUntil' => date('Y-m-d', strtotime('+1 year')),
            'itemCondition' => 'https://schema.org/NewCondition',
            'availability' => $stock_status
        ];

        // Add strikethrough price if on sale
        if (!empty($regular_price) && floatval($regular_price) > floatval($price)) {
            $offer['priceSpecification'] = [
                '@type' => 'UnitPriceSpecification',
                'priceType' => 'https://schema.org/StrikethroughPrice',
                'price' => (string) $regular_price,
                'priceCurrency' => get_woocommerce_currency()
            ];
        }

        // Add shipping details
        $offer['shippingDetails'] = $this->get_shipping_details();

        // Add return policy
        $offer['hasMerchantReturnPolicy'] = $this->get_return_policy();

        return $offer;
    }

    /**
     * Get shipping details schema
     */
    private function get_shipping_details() {
        return [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => get_option('wild_dragon_shipping_rate', '0'),
                'currency' => get_woocommerce_currency()
            ],
            'shippingDestination' => [
                '@type' => 'DefinedRegion',
                'addressCountry' => get_option('wild_dragon_shipping_country', 'IN')
            ],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => intval(get_option('wild_dragon_handling_time_min', '1')),
                    'maxValue' => intval(get_option('wild_dragon_handling_time_max', '2')),
                    'unitCode' => 'DAY'
                ],
                'transitTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => intval(get_option('wild_dragon_transit_time_min', '4')),
                    'maxValue' => intval(get_option('wild_dragon_transit_time_max', '7')),
                    'unitCode' => 'DAY'
                ]
            ]
        ];
    }

    /**
     * Get return policy schema
     */
    private function get_return_policy() {
        return [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => get_option('wild_dragon_return_country', 'IN'),
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => intval(get_option('wild_dragon_return_days', '14')),
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => get_option('wild_dragon_return_fees', 'https://schema.org/FreeReturn')
        ];
    }

    /**
     * Generate product breadcrumb
     */
    private function generate_product_breadcrumb($product) {
        $breadcrumb_items = [];
        
        // Home
        $breadcrumb_items[] = [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => get_option('wild_dragon_organization_name', 'Wild Dragon'),
            'item' => [
                '@type' => 'Thing',
                '@id' => home_url('/')
            ]
        ];

        // Product categories
        $categories = get_the_terms($product->get_id(), 'product_cat');
        if ($categories && !is_wp_error($categories)) {
            $position = 2;
            foreach ($categories as $category) {
                if ($category->parent == 0) continue; // Skip root categories
                
                $breadcrumb_items[] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'name' => $category->name,
                    'item' => [
                        '@type' => 'Thing',
                        '@id' => get_term_link($category)
                    ]
                ];
                $position++;
            }
        }

        // Current product
        $breadcrumb_items[] = [
            '@type' => 'ListItem',
            'position' => count($breadcrumb_items) + 1,
            'name' => $product->get_name(),
            'item' => [
                '@type' => 'Thing',
                '@id' => get_permalink($product->get_id())
            ]
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumb_items
        ];
    }

    /**
     * Generate FAQ schema ONLY if FAQ content exists
     */
    private function generate_faq_schema_if_exists($product) {
        $content = $product->get_description();
        
        // Check if FAQ content actually exists
        if (strpos($content, 'FAQ') === false && 
            strpos($content, 'Frequently Asked') === false &&
            strpos($content, 'Estimated Order Processing') === false) {
            return null;
        }
        
        // Generate FAQ questions based on settings
        $faq_questions = [];
        
        $processing_time = get_option('wild_dragon_faq_processing_time');
        $delivery_time = get_option('wild_dragon_faq_delivery_time');
        
        if (!empty($processing_time)) {
            $faq_questions[] = [
                '@type' => 'Question',
                'name' => 'What is the Estimated Order Processing Time?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $processing_time
                ]
            ];
        }
        
        if (!empty($delivery_time)) {
            $faq_questions[] = [
                '@type' => 'Question',
                'name' => 'How long does delivery take?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $delivery_time
                ]
            ];
        }

        if (empty($faq_questions)) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faq_questions
        ];
    }

    /**
     * Get product images array - FIXED to return proper array format
     */
    private function get_product_images($product) {
        $images = [];
        
        // Main image
        $main_image = wp_get_attachment_url($product->get_image_id());
        if ($main_image) {
            $images[] = $main_image;
        }

        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $gallery_id) {
            $gallery_image = wp_get_attachment_url($gallery_id);
            if ($gallery_image) {
                $images[] = $gallery_image;
            }
        }

        // Return array of images, or single image if only one exists
        if (count($images) > 1) {
            return $images; // Multiple images as array
        } elseif (count($images) === 1) {
            return $images; // Single image still as array for consistency
        } else {
            return [$main_image]; // Fallback
        }
    }

    /**
     * Get product attributes as structured properties
     */
    private function get_product_attributes($product) {
        $attributes = [];
        
        $product_attributes = $product->get_attributes();
        foreach ($product_attributes as $attribute) {
            $name = $attribute->get_name();
            $values = $attribute->get_options();
            
            if ($name === 'pa_color' || $name === 'pa_colour') {
                $attributes['color'] = is_array($values) ? implode(', ', $values) : $values;
            } elseif ($name === 'pa_material') {
                $attributes['material'] = is_array($values) ? implode(', ', $values) : $values;
            } elseif ($name === 'pa_size') {
                $attributes['size'] = is_array($values) ? implode(', ', $values) : $values;
            }
        }

        return $attributes;
    }

    /**
     * Get product reviews
     */
    private function get_product_reviews($product) {
        $reviews = [];
        
        $comments = get_comments([
            'post_id' => $product->get_id(),
            'status' => 'approve',
            'type' => 'review',
            'number' => 5 // Limit to 5 most recent reviews
        ]);

        foreach ($comments as $comment) {
            $rating = get_comment_meta($comment->comment_ID, 'rating', true);
            
            if ($rating) {
                $reviews[] = [
                    '@type' => 'Review',
                    'author' => [
                        '@type' => 'Person',
                        'name' => $comment->comment_author
                    ],
                    'datePublished' => date('Y-m-d', strtotime($comment->comment_date)),
                    'reviewBody' => $comment->comment_content,
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => (string) $rating
                    ]
                ];
            }
        }

        return $reviews;
    }

    /**
     * Get video schema
     */
    private function get_video_schema($product, $video_url) {
        return [
            '@type' => 'VideoObject',
            'name' => $product->get_name() . ' - Product Video',
            'description' => 'See the ' . $product->get_name() . ' in action.',
            'thumbnailUrl' => wp_get_attachment_url($product->get_image_id()),
            'uploadDate' => date('Y-m-d'),
            'duration' => 'PT1M30S', // Default duration
            'contentUrl' => $video_url
        ];
    }

    /**
     * Get organization schema
     */
    private function get_organization_schema() {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => apply_filters('the_title', get_option('wild_dragon_organization_name', 'Wild Dragon')),
            'url' => home_url('/'),
            'logo' => esc_url(get_option('wild_dragon_logo_url', 'https://cdn.shopify.com/s/files/1/1982/7331/files/veirdologotrans_180x.png')),
            'sameAs' => array_filter([
                esc_url(get_option('wild_dragon_facebook_url')),
                esc_url(get_option('wild_dragon_twitter_url')),
                esc_url(get_option('wild_dragon_instagram_url')),
                esc_url(get_option('wild_dragon_youtube_url'))
            ]),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => get_option('wild_dragon_contact_number', '+91-6352449482'),
                'contactType' => get_option('wild_dragon_contact_type', 'Customer Service')
            ]
        ];
    }

    /**
     * Renders JSON-LD script tag
     */
    protected function render_json_ld($data) {
        return '<script type="application/ld+json">' .
               wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) .
               '</script>';
    }
}