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
        add_action('acf/input/admin_enqueue_scripts', [self::class, 'enqueueCalculatorScript']);
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
            'restUrl' => rest_url('hp-react-widgets/v1/'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
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
            'menu_order' => 5, // After General tab
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
                'message' => '<p><strong>Offers</strong> are what customers see and purchase. Each offer can be:</p>
                    <ul style="margin-left: 20px; list-style: disc;">
                        <li><strong>Single Product</strong> - One product with optional discount</li>
                        <li><strong>Fixed Bundle</strong> - Pre-configured set of products</li>
                        <li><strong>Customizable Kit</strong> - Customer picks products (with per-product discounts)</li>
                    </ul>',
                'esc_html' => 0,
            ],
            // Offers Repeater
            [
                'key' => 'field_funnel_offers',
                'label' => 'Offers',
                'name' => 'funnel_offers',
                'type' => 'repeater',
                'instructions' => 'Add offers for this funnel. Drag to reorder.',
                'required' => 0,
                'min' => 0,
                'max' => 10,
                'layout' => 'block',
                'button_label' => 'Add Offer',
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
            // Offer ID (auto-generated)
            [
                'key' => 'field_offer_id',
                'label' => 'Offer ID',
                'name' => 'offer_id',
                'type' => 'text',
                'instructions' => 'Auto-generated unique identifier.',
                'default_value' => '',
                'readonly' => 1,
                'wrapper' => ['width' => '25'],
            ],
            // Offer Name
            [
                'key' => 'field_offer_name',
                'label' => 'Offer Name',
                'name' => 'offer_name',
                'type' => 'text',
                'instructions' => 'Display name shown to customers.',
                'required' => 1,
                'wrapper' => ['width' => '50'],
            ],
            // Is Featured
            [
                'key' => 'field_offer_is_featured',
                'label' => 'Featured',
                'name' => 'offer_is_featured',
                'type' => 'true_false',
                'instructions' => 'Highlight this offer.',
                'ui' => 1,
                'wrapper' => ['width' => '25'],
            ],
            // Offer Type
            [
                'key' => 'field_offer_type',
                'label' => 'Offer Type',
                'name' => 'offer_type',
                'type' => 'select',
                'instructions' => 'What kind of offer is this?',
                'required' => 1,
                'choices' => [
                    'single' => 'Single Product',
                    'fixed_bundle' => 'Fixed Bundle',
                    'customizable_kit' => 'Customizable Kit',
                ],
                'default_value' => 'single',
                'wrapper' => ['width' => '33'],
            ],
            // Badge
            [
                'key' => 'field_offer_badge',
                'label' => 'Badge',
                'name' => 'offer_badge',
                'type' => 'text',
                'instructions' => 'e.g., "BEST VALUE", "20% OFF"',
                'wrapper' => ['width' => '33'],
            ],
            // Discount Label (marketing)
            [
                'key' => 'field_offer_discount_label',
                'label' => 'Discount Label',
                'name' => 'offer_discount_label',
                'type' => 'text',
                'instructions' => 'Marketing label shown to customer (e.g., "Save 25%").',
                'wrapper' => ['width' => '34'],
            ],
            // Description
            [
                'key' => 'field_offer_description',
                'label' => 'Description',
                'name' => 'offer_description',
                'type' => 'textarea',
                'instructions' => 'Optional description for the offer.',
                'rows' => 2,
            ],
            // Image Override
            [
                'key' => 'field_offer_image',
                'label' => 'Image',
                'name' => 'offer_image',
                'type' => 'image',
                'instructions' => 'Override image (optional). Uses product image if empty.',
                'return_format' => 'url',
                'preview_size' => 'thumbnail',
                'wrapper' => ['width' => '33'],
            ],
            // Global Discount Type
            [
                'key' => 'field_offer_discount_type',
                'label' => 'Discount Type',
                'name' => 'offer_discount_type',
                'type' => 'select',
                'instructions' => 'How to apply the global discount.',
                'choices' => [
                    'none' => 'No Discount',
                    'percent' => 'Percentage (%)',
                    'fixed' => 'Fixed Amount ($)',
                ],
                'default_value' => 'none',
                'wrapper' => ['width' => '33'],
            ],
            // Global Discount Value
            [
                'key' => 'field_offer_discount_value',
                'label' => 'Discount Value',
                'name' => 'offer_discount_value',
                'type' => 'number',
                'instructions' => 'Amount (% or $) based on type above.',
                'default_value' => 0,
                'min' => 0,
                'wrapper' => ['width' => '34'],
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
            
            // === SINGLE PRODUCT FIELDS ===
            [
                'key' => 'field_single_product_heading',
                'label' => 'Single Product',
                'name' => '',
                'type' => 'message',
                'message' => '<hr><strong>Product Selection</strong>',
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
                'key' => 'field_single_product_sku',
                'label' => 'Product SKU',
                'name' => 'single_product_sku',
                'type' => 'text',
                'instructions' => 'WooCommerce product SKU.',
                'required' => 1,
                'wrapper' => ['width' => '50'],
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
                'label' => 'Quantity',
                'name' => 'single_product_qty',
                'type' => 'number',
                'instructions' => 'How many of this product.',
                'default_value' => 1,
                'min' => 1,
                'wrapper' => ['width' => '50'],
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
                'key' => 'field_bundle_heading',
                'label' => 'Bundle Items',
                'name' => '',
                'type' => 'message',
                'message' => '<hr><strong>Bundle Contents</strong> - Add products to this bundle.',
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
                'instructions' => '',
                'min' => 1,
                'max' => 10,
                'layout' => 'table',
                'button_label' => 'Add Product',
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
                        'key' => 'field_bundle_item_sku',
                        'label' => 'Product SKU',
                        'name' => 'sku',
                        'type' => 'text',
                        'required' => 1,
                        'wrapper' => ['width' => '70'],
                    ],
                    [
                        'key' => 'field_bundle_item_qty',
                        'label' => 'Qty',
                        'name' => 'qty',
                        'type' => 'number',
                        'default_value' => 1,
                        'min' => 1,
                        'wrapper' => ['width' => '30'],
                    ],
                ],
            ],
            
            // === CUSTOMIZABLE KIT FIELDS ===
            [
                'key' => 'field_kit_heading',
                'label' => 'Kit Configuration',
                'name' => '',
                'type' => 'message',
                'message' => '<hr><strong>Customizable Kit</strong> - Define available products and their roles.',
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
                'instructions' => 'Maximum total items customer can add (0 = no limit).',
                'default_value' => 6,
                'min' => 0,
                'wrapper' => ['width' => '33'],
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
            // Calculator display (read-only message updated by JS)
            [
                'key' => 'field_kit_calculator',
                'label' => 'Discount Calculator',
                'name' => '',
                'type' => 'message',
                'message' => '<div id="hp-kit-calculator" style="background:#f8f9fa; padding:15px; border-radius:4px; font-family:monospace;">
                    <div>Original Total: <span id="calc-original">$0.00</span></div>
                    <div>After Product Discounts: <span id="calc-after-products">$0.00</span></div>
                    <div>After Kit Discount: <span id="calc-final">$0.00</span></div>
                    <hr style="margin:10px 0;">
                    <div><strong>Actual Savings: <span id="calc-savings">$0.00 (0%)</span></strong></div>
                </div>',
                'esc_html' => 0,
                'wrapper' => ['width' => '67'],
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
                'instructions' => 'Define products available in this kit.',
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
                        'key' => 'field_kit_product_sku',
                        'label' => 'Product SKU',
                        'name' => 'sku',
                        'type' => 'text',
                        'required' => 1,
                        'wrapper' => ['width' => '25'],
                    ],
                    [
                        'key' => 'field_kit_product_role',
                        'label' => 'Role',
                        'name' => 'role',
                        'type' => 'select',
                        'instructions' => '',
                        'choices' => [
                            'must' => 'Must (Required)',
                            'default' => 'Default (Pre-selected)',
                            'optional' => 'Optional',
                        ],
                        'default_value' => 'optional',
                        'wrapper' => ['width' => '20'],
                    ],
                    [
                        'key' => 'field_kit_product_qty',
                        'label' => 'Default Qty',
                        'name' => 'qty',
                        'type' => 'number',
                        'instructions' => 'Starting quantity.',
                        'default_value' => 1,
                        'min' => 0,
                        'wrapper' => ['width' => '10'],
                    ],
                    [
                        'key' => 'field_kit_product_max_qty',
                        'label' => 'Max Qty',
                        'name' => 'max_qty',
                        'type' => 'number',
                        'instructions' => 'Max customer can add.',
                        'default_value' => 3,
                        'min' => 1,
                        'wrapper' => ['width' => '10'],
                    ],
                    [
                        'key' => 'field_kit_product_discount_type',
                        'label' => 'Product Discount',
                        'name' => 'discount_type',
                        'type' => 'select',
                        'choices' => [
                            'none' => 'None',
                            'percent' => '%',
                            'fixed' => '$',
                        ],
                        'default_value' => 'none',
                        'wrapper' => ['width' => '15'],
                    ],
                    [
                        'key' => 'field_kit_product_discount_value',
                        'label' => 'Discount Value',
                        'name' => 'discount_value',
                        'type' => 'number',
                        'default_value' => 0,
                        'min' => 0,
                        'wrapper' => ['width' => '20'],
                    ],
                ],
            ],
        ];
    }
}

