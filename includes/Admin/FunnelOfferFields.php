<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers ACF fields for the Funnel Offers system.
 * Replaces the legacy "Products" tab.
 */
class FunnelOfferFields
{
    public static function init(): void
    {
        add_action('acf/init', [self::class, 'registerFields']);
        add_action('acf/init', [self::class, 'removeLegacyProductsTab'], 99);
        add_action('acf/input/admin_enqueue_scripts', [self::class, 'enqueueScripts']);
        add_filter('acf/update_value/key=field_offer_id', [self::class, 'generateOfferId'], 10, 3);
    }

    /**
     * Remove the legacy Products tab from the Funnel Configuration field group.
     */
    public static function removeLegacyProductsTab(): void
    {
        // Remove the Products tab field from any field group
        add_filter('acf/load_field', function($field) {
            // Remove the Products tab itself
            if (isset($field['name']) && $field['name'] === 'products_tab') {
                return false;
            }
            // Remove funnel_products repeater
            if (isset($field['name']) && $field['name'] === 'funnel_products') {
                return false;
            }
            return $field;
        });
    }

    public static function generateOfferId($value, $postId, $field)
    {
        if (empty($value)) {
            $value = 'offer-' . substr(md5(uniqid() . $postId), 0, 8);
        }
        return $value;
    }

    public static function enqueueScripts(): void
    {
        global $post;
        if (!$post || $post->post_type !== 'hp-funnel') {
            return;
        }

        wp_enqueue_script(
            'hp-rw-offer-admin',
            HP_RW_URL . 'assets/admin/offer-calculator.js',
            ['jquery', 'acf-input'],
            HP_RW_VERSION,
            true
        );

        wp_localize_script('hp-rw-offer-admin', 'hpOfferCalc', [
            'restUrl' => rest_url('hp-rw/v1/'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);

        // Compact styles
        wp_add_inline_style('acf-input', self::getStyles());
    }

    private static function getStyles(): string
    {
        return '
            /* Compact offer form */
            .acf-field[data-key="field_funnel_offers"] .acf-row {
                background: #fafafa;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                margin-bottom: 12px;
            }
            .acf-field[data-key="field_funnel_offers"] .acf-row.-collapsed {
                background: #fff;
            }
            
            /* Product display card */
            .hp-product-card {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                margin-top: 8px;
            }
            .hp-product-card img {
                width: 48px;
                height: 48px;
                object-fit: cover;
                border-radius: 4px;
                flex-shrink: 0;
            }
            .hp-product-card .hp-product-info {
                flex: 1;
                min-width: 0;
            }
            .hp-product-card .hp-product-name {
                font-weight: 600;
                color: #1e1e1e;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .hp-product-card .hp-product-sku {
                font-size: 12px;
                color: #757575;
            }
            .hp-product-card .hp-product-price {
                font-weight: 600;
                color: #00a32a;
                font-size: 15px;
            }
            .hp-product-card .hp-product-qty {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .hp-product-card .hp-product-qty input {
                width: 60px;
                text-align: center;
            }
            .hp-product-card .hp-product-remove {
                color: #d63638;
                cursor: pointer;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 18px;
            }
            .hp-product-card .hp-product-remove:hover {
                background: #fcf0f1;
            }
            
            /* Hide instruction text, show on hover */
            .acf-field[data-key^="field_offer"] > .acf-label .description,
            .acf-field[data-key^="field_single"] > .acf-label .description,
            .acf-field[data-key^="field_bundle"] > .acf-label .description,
            .acf-field[data-key^="field_kit"] > .acf-label .description {
                display: none;
            }
            
            /* Collapsed offer summary */
            .hp-offer-summary {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 4px 0;
            }
            .hp-offer-summary img {
                width: 40px;
                height: 40px;
                object-fit: cover;
                border-radius: 4px;
            }
            .hp-offer-summary-name {
                font-weight: 600;
            }
            .hp-offer-summary-product {
                color: #666;
                font-size: 13px;
            }
            .hp-offer-summary-price {
                color: #00a32a;
                font-weight: 600;
            }
        ';
    }

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
                    ['param' => 'post_type', 'operator' => '==', 'value' => 'hp-funnel'],
                ],
            ],
            'menu_order' => 5,
            'position' => 'normal',
            'style' => 'default',
        ]);
    }

    private static function getOfferFields(): array
    {
        return [
            [
                'key' => 'field_offers_tab',
                'label' => 'Offers',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'key' => 'field_funnel_offers',
                'label' => '',
                'name' => 'funnel_offers',
                'type' => 'repeater',
                'min' => 0,
                'max' => 10,
                'layout' => 'block',
                'button_label' => 'Add Offer',
                'collapsed' => 'field_offer_name',
                'sub_fields' => self::getOfferSubFields(),
            ],
        ];
    }

    private static function getOfferSubFields(): array
    {
        return [
            // Row 1: Core fields
            [
                'key' => 'field_offer_name',
                'label' => 'Offer Name',
                'name' => 'offer_name',
                'type' => 'text',
                'required' => 1,
                'wrapper' => ['width' => '35'],
            ],
            [
                'key' => 'field_offer_type',
                'label' => 'Type',
                'name' => 'offer_type',
                'type' => 'select',
                'required' => 1,
                'choices' => [
                    'single' => 'Single Product',
                    'fixed_bundle' => 'Fixed Bundle',
                    'customizable_kit' => 'Custom Kit',
                ],
                'default_value' => 'single',
                'wrapper' => ['width' => '20'],
            ],
            [
                'key' => 'field_offer_badge',
                'label' => 'Badge',
                'name' => 'offer_badge',
                'type' => 'text',
                'placeholder' => 'e.g. BEST VALUE',
                'wrapper' => ['width' => '20'],
            ],
            [
                'key' => 'field_offer_is_featured',
                'label' => 'Featured',
                'name' => 'offer_is_featured',
                'type' => 'true_false',
                'ui' => 1,
                'wrapper' => ['width' => '10'],
            ],
            [
                'key' => 'field_offer_id',
                'label' => 'ID',
                'name' => 'offer_id',
                'type' => 'text',
                'readonly' => 1,
                'wrapper' => ['width' => '15'],
            ],
            
            // Row 2: Discount
            [
                'key' => 'field_offer_discount_type',
                'label' => 'Discount',
                'name' => 'offer_discount_type',
                'type' => 'select',
                'choices' => [
                    'none' => 'None',
                    'percent' => '% Off',
                    'fixed' => '$ Off',
                ],
                'default_value' => 'none',
                'wrapper' => ['width' => '20'],
            ],
            [
                'key' => 'field_offer_discount_value',
                'label' => 'Amount',
                'name' => 'offer_discount_value',
                'type' => 'number',
                'default_value' => 0,
                'min' => 0,
                'wrapper' => ['width' => '15'],
                'conditional_logic' => [
                    [['field' => 'field_offer_discount_type', 'operator' => '!=', 'value' => 'none']],
                ],
            ],
            [
                'key' => 'field_offer_discount_label',
                'label' => 'Label',
                'name' => 'offer_discount_label',
                'type' => 'text',
                'placeholder' => 'Save 25%',
                'wrapper' => ['width' => '20'],
                'conditional_logic' => [
                    [['field' => 'field_offer_discount_type', 'operator' => '!=', 'value' => 'none']],
                ],
            ],
            [
                'key' => 'field_offer_description',
                'label' => 'Description',
                'name' => 'offer_description',
                'type' => 'text',
                'wrapper' => ['width' => '45'],
            ],
            
            // === SINGLE PRODUCT ===
            [
                'key' => 'field_single_product_search',
                'label' => 'Product',
                'name' => 'single_product_search',
                'type' => 'text',
                'placeholder' => 'Search by name or SKU...',
                'wrapper' => ['width' => '100', 'class' => 'hp-product-search-field'],
                'conditional_logic' => [
                    [['field' => 'field_offer_type', 'operator' => '==', 'value' => 'single']],
                ],
            ],
            [
                'key' => 'field_single_product_sku',
                'name' => 'single_product_sku',
                'type' => 'hidden',
                'conditional_logic' => [
                    [['field' => 'field_offer_type', 'operator' => '==', 'value' => 'single']],
                ],
            ],
            [
                'key' => 'field_single_product_qty',
                'name' => 'single_product_qty',
                'type' => 'hidden',
                'default_value' => 1,
                'conditional_logic' => [
                    [['field' => 'field_offer_type', 'operator' => '==', 'value' => 'single']],
                ],
            ],
            // Product display container (populated by JS)
            [
                'key' => 'field_single_product_display',
                'name' => '',
                'type' => 'message',
                'label' => '',
                'message' => '<div class="hp-single-product-container" data-type="single"></div>',
                'esc_html' => 0,
                'wrapper' => ['class' => 'hp-product-display-wrapper'],
                'conditional_logic' => [
                    [['field' => 'field_offer_type', 'operator' => '==', 'value' => 'single']],
                ],
            ],
            
            // === FIXED BUNDLE ===
            [
                'key' => 'field_bundle_items',
                'label' => 'Bundle Items',
                'name' => 'bundle_items',
                'type' => 'repeater',
                'min' => 1,
                'max' => 10,
                'layout' => 'block',
                'button_label' => 'Add Product',
                'conditional_logic' => [
                    [['field' => 'field_offer_type', 'operator' => '==', 'value' => 'fixed_bundle']],
                ],
                'sub_fields' => [
                    [
                        'key' => 'field_bundle_item_search',
                        'label' => 'Search Product',
                        'name' => 'product_search',
                        'type' => 'text',
                        'placeholder' => 'Search...',
                        'wrapper' => ['width' => '100', 'class' => 'hp-product-search-field'],
                    ],
                    [
                        'key' => 'field_bundle_item_sku',
                        'name' => 'sku',
                        'type' => 'hidden',
                    ],
                    [
                        'key' => 'field_bundle_item_qty',
                        'name' => 'qty',
                        'type' => 'hidden',
                        'default_value' => 1,
                    ],
                    [
                        'key' => 'field_bundle_item_display',
                        'name' => '',
                        'type' => 'message',
                        'label' => '',
                        'message' => '<div class="hp-bundle-product-container" data-type="bundle"></div>',
                        'esc_html' => 0,
                    ],
                ],
            ],
            
            // === CUSTOMIZABLE KIT ===
            [
                'key' => 'field_kit_max_items',
                'label' => 'Max Items',
                'name' => 'kit_max_items',
                'type' => 'number',
                'default_value' => 6,
                'min' => 0,
                'wrapper' => ['width' => '20'],
                'conditional_logic' => [
                    [['field' => 'field_offer_type', 'operator' => '==', 'value' => 'customizable_kit']],
                ],
            ],
            [
                'key' => 'field_kit_products',
                'label' => 'Kit Products',
                'name' => 'kit_products',
                'type' => 'repeater',
                'min' => 1,
                'max' => 20,
                'layout' => 'block',
                'button_label' => 'Add Product',
                'conditional_logic' => [
                    [['field' => 'field_offer_type', 'operator' => '==', 'value' => 'customizable_kit']],
                ],
                'sub_fields' => [
                    [
                        'key' => 'field_kit_product_search',
                        'label' => 'Product',
                        'name' => 'product_search',
                        'type' => 'text',
                        'placeholder' => 'Search...',
                        'wrapper' => ['width' => '40', 'class' => 'hp-product-search-field'],
                    ],
                    [
                        'key' => 'field_kit_product_sku',
                        'name' => 'sku',
                        'type' => 'hidden',
                    ],
                    [
                        'key' => 'field_kit_product_role',
                        'label' => 'Role',
                        'name' => 'role',
                        'type' => 'select',
                        'choices' => [
                            'must' => 'Required',
                            'default' => 'Default',
                            'optional' => 'Optional',
                        ],
                        'default_value' => 'optional',
                        'wrapper' => ['width' => '20'],
                    ],
                    [
                        'key' => 'field_kit_product_qty',
                        'label' => 'Qty',
                        'name' => 'qty',
                        'type' => 'number',
                        'default_value' => 1,
                        'min' => 0,
                        'wrapper' => ['width' => '10'],
                    ],
                    [
                        'key' => 'field_kit_product_max_qty',
                        'label' => 'Max',
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
                        'choices' => ['none' => '-', 'percent' => '%', 'fixed' => '$'],
                        'default_value' => 'none',
                        'wrapper' => ['width' => '10'],
                    ],
                    [
                        'key' => 'field_kit_product_discount_value',
                        'label' => 'Value',
                        'name' => 'discount_value',
                        'type' => 'number',
                        'default_value' => 0,
                        'wrapper' => ['width' => '10'],
                        'conditional_logic' => [
                            [['field' => 'field_kit_product_discount_type', 'operator' => '!=', 'value' => 'none']],
                        ],
                    ],
                    [
                        'key' => 'field_kit_product_display',
                        'name' => '',
                        'type' => 'message',
                        'label' => '',
                        'message' => '<div class="hp-kit-product-container" data-type="kit"></div>',
                        'esc_html' => 0,
                    ],
                ],
            ],
            
            // Image override (optional)
            [
                'key' => 'field_offer_image',
                'label' => 'Image Override',
                'name' => 'offer_image',
                'type' => 'image',
                'return_format' => 'url',
                'preview_size' => 'thumbnail',
                'wrapper' => ['width' => '30'],
            ],
        ];
    }
}
