<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers ACF fields for the Funnel Offers system.
 * 
 * This replaces the legacy "Products" tab with a new "Offers" system
 * supporting single products, fixed bundles, and customizable kits.
 */
class FunnelOfferFields
{
    /**
     * Initialize the ACF field registration.
     */
    public static function init(): void
    {
        add_action('acf/init', [self::class, 'registerFields']);
        add_action('acf/init', [self::class, 'hideLegacyProductsTab'], 20);
        add_action('acf/input/admin_enqueue_scripts', [self::class, 'enqueueCalculatorScript']);
        
        // Auto-generate offer IDs
        add_filter('acf/update_value/key=field_offer_id', [self::class, 'generateOfferId'], 10, 3);
    }

    /**
     * Hide the legacy Products tab if it exists.
     * This removes the old tab registered via ACF admin UI.
     */
    public static function hideLegacyProductsTab(): void
    {
        // Remove any legacy field group with Products for funnels
        add_filter('acf/get_field_groups', function($groups) {
            return array_filter($groups, function($group) {
                // Keep our new offers group, remove legacy products groups
                if (isset($group['key']) && $group['key'] === 'group_hp_funnel_products_legacy') {
                    return false;
                }
                return true;
            });
        });
    }

    /**
     * Generate unique offer ID if empty.
     */
    public static function generateOfferId($value, $postId, $field)
    {
        if (empty($value)) {
            $value = 'offer-' . substr(md5(uniqid() . $postId), 0, 8);
        }
        return $value;
    }

    /**
     * Enqueue the offer calculator script for admin.
     */
    public static function enqueueCalculatorScript(): void
    {
        global $post;

        if (!$post || $post->post_type !== 'hp-funnel') {
            return;
        }

        wp_enqueue_script(
            'hp-rw-offer-calculator',
            HP_RW_URL . 'assets/admin/offer-calculator.js',
            ['jquery', 'acf-input'],
            HP_RW_VERSION,
            true
        );

        wp_localize_script('hp-rw-offer-calculator', 'hpOfferCalc', [
            'restUrl' => rest_url('hp-rw/v1/'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
        
        // Add inline styles for product display
        wp_add_inline_style('acf-input', self::getAdminStyles());
    }
    
    /**
     * Get admin styles for product display.
     */
    private static function getAdminStyles(): string
    {
        return '
            .hp-product-display {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 6px;
                margin-top: 8px;
            }
            .hp-product-display img {
                width: 50px;
                height: 50px;
                object-fit: cover;
                border-radius: 4px;
            }
            .hp-product-display .product-info {
                flex: 1;
            }
            .hp-product-display .product-name {
                font-weight: 600;
                color: #1e1e1e;
            }
            .hp-product-display .product-sku {
                font-size: 12px;
                color: #757575;
            }
            .hp-product-display .product-price {
                font-weight: 600;
                color: #00a32a;
            }
            .hp-product-remove {
                color: #d63638;
                cursor: pointer;
                padding: 4px 8px;
                border-radius: 4px;
            }
            .hp-product-remove:hover {
                background: #fcf0f1;
            }
        ';
    }

    /**
     * Register the ACF field group for funnel offers.
     */
    public static function registerFields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_hp_funnel_offers',
            'title' => 'Funnel Offers',
            'fields' => self::getOfferFields(),
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'hp-funnel',
                    ],
                ],
            ],
            'menu_order' => 5,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    /**
     * Get the field definitions for offers.
     */
    private static function getOfferFields(): array
    {
        return [
            // Tab
            [
                'key' => 'field_offers_tab',
                'label' => 'Offers',
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
            ],
            // Instructions
            [
                'key' => 'field_offers_instructions',
                'label' => '',
                'name' => '',
                'type' => 'message',
                'message' => '<div style="background:#f0f6fc; padding:15px; border-radius:6px; border-left:4px solid #0073aa; margin-bottom:10px;">
                    <strong style="font-size:14px;">📦 Offers</strong>
                    <p style="margin:8px 0 0;">Each offer can be: <strong>Single Product</strong>, <strong>Fixed Bundle</strong>, or <strong>Customizable Kit</strong>.</p>
                </div>',
                'esc_html' => 0,
            ],
            // Offers Repeater
            [
                'key' => 'field_funnel_offers',
                'label' => 'Offers',
                'name' => 'funnel_offers',
                'type' => 'repeater',
                'instructions' => '',
                'required' => 0,
                'min' => 0,
                'max' => 10,
                'layout' => 'block',
                'button_label' => 'Add Offer',
                'collapsed' => 'field_offer_name',
                'sub_fields' => self::getOfferSubFields(),
            ],
        ];
    }

    /**
     * Get sub-fields for each offer in the repeater.
     */
    private static function getOfferSubFields(): array
    {
        return [
            // Row 1: Core info
            [
                'key' => 'field_offer_name',
                'label' => 'Offer Name',
                'name' => 'offer_name',
                'type' => 'text',
                'instructions' => 'Display name shown to customers.',
                'required' => 1,
                'wrapper' => ['width' => '40'],
            ],
            [
                'key' => 'field_offer_type',
                'label' => 'Offer Type',
                'name' => 'offer_type',
                'type' => 'select',
                'instructions' => 'What kind of offer?',
                'required' => 1,
                'choices' => [
                    'single' => '🛒 Single Product',
                    'fixed_bundle' => '📦 Fixed Bundle',
                    'customizable_kit' => '🎨 Customizable Kit',
                ],
                'default_value' => 'single',
                'wrapper' => ['width' => '30'],
            ],
            [
                'key' => 'field_offer_is_featured',
                'label' => 'Featured',
                'name' => 'offer_is_featured',
                'type' => 'true_false',
                'instructions' => 'Highlight this offer.',
                'ui' => 1,
                'wrapper' => ['width' => '15'],
            ],
            [
                'key' => 'field_offer_id',
                'label' => 'ID',
                'name' => 'offer_id',
                'type' => 'text',
                'instructions' => 'Auto-generated.',
                'readonly' => 1,
                'wrapper' => ['width' => '15'],
            ],
            
            // Row 2: Marketing
            [
                'key' => 'field_offer_badge',
                'label' => 'Badge',
                'name' => 'offer_badge',
                'type' => 'text',
                'instructions' => 'e.g., "BEST VALUE", "POPULAR"',
                'wrapper' => ['width' => '25'],
            ],
            [
                'key' => 'field_offer_discount_label',
                'label' => 'Discount Label',
                'name' => 'offer_discount_label',
                'type' => 'text',
                'instructions' => 'e.g., "Save 25%"',
                'wrapper' => ['width' => '25'],
            ],
            [
                'key' => 'field_offer_discount_type',
                'label' => 'Discount Type',
                'name' => 'offer_discount_type',
                'type' => 'select',
                'choices' => [
                    'none' => 'No Discount',
                    'percent' => 'Percentage (%)',
                    'fixed' => 'Fixed Amount ($)',
                ],
                'default_value' => 'none',
                'wrapper' => ['width' => '25'],
            ],
            [
                'key' => 'field_offer_discount_value',
                'label' => 'Discount Value',
                'name' => 'offer_discount_value',
                'type' => 'number',
                'default_value' => 0,
                'min' => 0,
                'wrapper' => ['width' => '25'],
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_discount_type',
                            'operator' => '!=',
                            'value' => 'none',
                        ],
                    ],
                ],
            ],
            
            // Row 3: Description & Image
            [
                'key' => 'field_offer_description',
                'label' => 'Description',
                'name' => 'offer_description',
                'type' => 'textarea',
                'rows' => 2,
                'wrapper' => ['width' => '70'],
            ],
            [
                'key' => 'field_offer_image',
                'label' => 'Image Override',
                'name' => 'offer_image',
                'type' => 'image',
                'return_format' => 'url',
                'preview_size' => 'thumbnail',
                'wrapper' => ['width' => '30'],
            ],
            
            // === SINGLE PRODUCT FIELDS ===
            [
                'key' => 'field_single_section',
                'label' => '',
                'name' => '',
                'type' => 'message',
                'message' => '<h4 style="margin:0; padding:15px 0 5px; border-top:1px solid #ddd;">🛒 Product Selection</h4>',
                'esc_html' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_type',
                            'operator' => '==',
                            'value' => 'single',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'field_single_product_search',
                'label' => 'Search Product',
                'name' => 'single_product_search',
                'type' => 'text',
                'instructions' => 'Type to search products by name or SKU.',
                'placeholder' => 'Start typing to search...',
                'wrapper' => ['width' => '50', 'class' => 'hp-product-search-field'],
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_type',
                            'operator' => '==',
                            'value' => 'single',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'field_single_product_sku',
                'label' => 'Selected Product SKU',
                'name' => 'single_product_sku',
                'type' => 'text',
                'instructions' => 'WooCommerce product SKU',
                'wrapper' => ['width' => '30'],
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_type',
                            'operator' => '==',
                            'value' => 'single',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'field_single_product_qty',
                'label' => 'Qty',
                'name' => 'single_product_qty',
                'type' => 'number',
                'default_value' => 1,
                'min' => 1,
                'wrapper' => ['width' => '20'],
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_type',
                            'operator' => '==',
                            'value' => 'single',
                        ],
                    ],
                ],
            ],
            // Product display (will be populated by JS)
            [
                'key' => 'field_single_product_display',
                'label' => '',
                'name' => '',
                'type' => 'message',
                'message' => '<div class="hp-selected-product-display" data-target="single_product_sku"></div>',
                'esc_html' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_type',
                            'operator' => '==',
                            'value' => 'single',
                        ],
                    ],
                ],
            ],
            
            // === FIXED BUNDLE FIELDS ===
            [
                'key' => 'field_bundle_section',
                'label' => '',
                'name' => '',
                'type' => 'message',
                'message' => '<h4 style="margin:0; padding:15px 0 5px; border-top:1px solid #ddd;">📦 Bundle Products</h4>',
                'esc_html' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_type',
                            'operator' => '==',
                            'value' => 'fixed_bundle',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'field_bundle_items',
                'label' => 'Bundle Items',
                'name' => 'bundle_items',
                'type' => 'repeater',
                'min' => 1,
                'max' => 10,
                'layout' => 'block',
                'button_label' => 'Add Product to Bundle',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_type',
                            'operator' => '==',
                            'value' => 'fixed_bundle',
                        ],
                    ],
                ],
                'sub_fields' => [
                    [
                        'key' => 'field_bundle_item_search',
                        'label' => 'Search Product',
                        'name' => 'product_search',
                        'type' => 'text',
                        'placeholder' => 'Type to search...',
                        'wrapper' => ['width' => '45', 'class' => 'hp-product-search-field'],
                    ],
                    [
                        'key' => 'field_bundle_item_sku',
                        'label' => 'SKU',
                        'name' => 'sku',
                        'type' => 'text',
                        'wrapper' => ['width' => '35'],
                    ],
                    [
                        'key' => 'field_bundle_item_qty',
                        'label' => 'Qty',
                        'name' => 'qty',
                        'type' => 'number',
                        'default_value' => 1,
                        'min' => 1,
                        'wrapper' => ['width' => '20'],
                    ],
                    // Product info display (populated by JS)
                    [
                        'key' => 'field_bundle_item_display',
                        'label' => '',
                        'name' => '',
                        'type' => 'message',
                        'message' => '<div class="hp-selected-product-display" data-target="sku"></div>',
                        'esc_html' => 0,
                    ],
                ],
            ],
            
            // === CUSTOMIZABLE KIT FIELDS ===
            [
                'key' => 'field_kit_section',
                'label' => '',
                'name' => '',
                'type' => 'message',
                'message' => '<h4 style="margin:0; padding:15px 0 5px; border-top:1px solid #ddd;">🎨 Kit Configuration</h4>',
                'esc_html' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_type',
                            'operator' => '==',
                            'value' => 'customizable_kit',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'field_kit_max_items',
                'label' => 'Max Total Items',
                'name' => 'kit_max_items',
                'type' => 'number',
                'instructions' => 'Maximum items customer can add (0 = unlimited).',
                'default_value' => 6,
                'min' => 0,
                'wrapper' => ['width' => '25'],
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_type',
                            'operator' => '==',
                            'value' => 'customizable_kit',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'field_kit_calculator',
                'label' => 'Discount Calculator',
                'name' => '',
                'type' => 'message',
                'message' => '<div id="hp-kit-calculator" style="background:#f8f9fa; padding:15px; border-radius:6px; font-family:monospace; font-size:13px;">
                    <div style="display:grid; gap:4px;">
                        <div>Original Total: <strong id="calc-original">$0.00</strong></div>
                        <div>After Product Discounts: <strong id="calc-after-products">$0.00</strong></div>
                        <div>After Kit Discount: <strong id="calc-final">$0.00</strong></div>
                        <hr style="margin:8px 0; border:0; border-top:1px solid #ddd;">
                        <div style="font-size:14px; color:#00a32a;">Actual Savings: <strong id="calc-savings">$0.00 (0%)</strong></div>
                    </div>
                </div>',
                'esc_html' => 0,
                'wrapper' => ['width' => '75'],
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_type',
                            'operator' => '==',
                            'value' => 'customizable_kit',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'field_kit_products',
                'label' => 'Kit Products',
                'name' => 'kit_products',
                'type' => 'repeater',
                'instructions' => '',
                'min' => 1,
                'max' => 20,
                'layout' => 'block',
                'button_label' => 'Add Kit Product',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_offer_type',
                            'operator' => '==',
                            'value' => 'customizable_kit',
                        ],
                    ],
                ],
                'sub_fields' => [
                    [
                        'key' => 'field_kit_product_search',
                        'label' => 'Search Product',
                        'name' => 'product_search',
                        'type' => 'text',
                        'placeholder' => 'Type to search...',
                        'wrapper' => ['width' => '30', 'class' => 'hp-product-search-field'],
                    ],
                    [
                        'key' => 'field_kit_product_sku',
                        'label' => 'SKU',
                        'name' => 'sku',
                        'type' => 'text',
                        'wrapper' => ['width' => '20'],
                    ],
                    [
                        'key' => 'field_kit_product_role',
                        'label' => 'Role',
                        'name' => 'role',
                        'type' => 'select',
                        'choices' => [
                            'must' => '🔒 Must (Required)',
                            'default' => '✅ Default (Pre-selected)',
                            'optional' => '➕ Optional',
                        ],
                        'default_value' => 'optional',
                        'wrapper' => ['width' => '20'],
                    ],
                    [
                        'key' => 'field_kit_product_qty',
                        'label' => 'Default Qty',
                        'name' => 'qty',
                        'type' => 'number',
                        'default_value' => 1,
                        'min' => 0,
                        'wrapper' => ['width' => '10'],
                    ],
                    [
                        'key' => 'field_kit_product_max_qty',
                        'label' => 'Max Qty',
                        'name' => 'max_qty',
                        'type' => 'number',
                        'default_value' => 3,
                        'min' => 1,
                        'wrapper' => ['width' => '10'],
                    ],
                    [
                        'key' => 'field_kit_product_discount_type',
                        'label' => 'Discount',
                        'name' => 'discount_type',
                        'type' => 'select',
                        'choices' => [
                            'none' => 'None',
                            'percent' => '%',
                            'fixed' => '$',
                        ],
                        'default_value' => 'none',
                        'wrapper' => ['width' => '10'],
                    ],
                    // Product info display
                    [
                        'key' => 'field_kit_product_display',
                        'label' => '',
                        'name' => '',
                        'type' => 'message',
                        'message' => '<div class="hp-selected-product-display" data-target="sku"></div>',
                        'esc_html' => 0,
                    ],
                ],
            ],
        ];
    }
}
