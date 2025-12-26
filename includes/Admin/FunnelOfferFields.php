<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers ACF fields for the Funnel Offers system.
 * Uses EAO-style single search field with product list.
 */
class FunnelOfferFields
{
    public static function init(): void
    {
        add_action('acf/init', [self::class, 'registerFields']);
        add_action('acf/init', [self::class, 'removeLegacyProductsTab'], 99);
        add_action('acf/input/admin_enqueue_scripts', [self::class, 'enqueueScripts']);
        add_filter('acf/update_value/key=field_offer_id', [self::class, 'generateOfferId'], 10, 3);
        add_action('edit_form_top', [self::class, 'displayVersionLabel']);
        add_action('admin_footer', [self::class, 'injectSavedProductsData']);
    }

    /**
     * Display plugin version under the Edit Funnel heading.
     */
    public static function displayVersionLabel($post): void
    {
        if ($post->post_type !== 'hp-funnel') {
            return;
        }
        
        // Position it below the title input using JS to avoid overlap with Add New button
        echo '<script>
        jQuery(function($) {
            var $titleWrap = $("#titlewrap");
            if ($titleWrap.length) {
                $titleWrap.after("<p style=\"color: #666; font-size: 12px; margin: 5px 0 15px 0;\">HP React Widgets v' . HP_RW_VERSION . '</p>");
            }
        });
        </script>';
    }

    /**
     * Inject saved products data via JS since ACF textarea values aren't pre-populated.
     */
    public static function injectSavedProductsData(): void
    {
        global $post;
        if (!$post || $post->post_type !== 'hp-funnel') {
            return;
        }

        // Get saved offers data - try both get_field and direct meta
        $offers = get_field('funnel_offers', $post->ID);
        
        // Build map of offer index => products_data
        $productsMap = [];
        
        if ($offers && is_array($offers)) {
            foreach ($offers as $index => $offer) {
                $productsData = $offer['products_data'] ?? '';
                if ($productsData) {
                    $productsMap[$index] = $productsData;
                }
            }
        }
        
        // Also try direct meta lookup as fallback
        if (empty($productsMap)) {
            $numOffers = (int) get_post_meta($post->ID, 'funnel_offers', true);
            for ($i = 0; $i < $numOffers; $i++) {
                $productsData = get_post_meta($post->ID, "funnel_offers_{$i}_products_data", true);
                if ($productsData) {
                    $productsMap[$i] = $productsData;
                }
            }
        }

        ?>
        <script>
        window.hpOfferSavedProducts = <?php echo json_encode($productsMap); ?>;
        console.log('[HP Offer PHP] Injected saved products:', window.hpOfferSavedProducts);
        console.log('[HP Offer PHP] Post ID:', <?php echo $post->ID; ?>);
        </script>
        <?php
    }

    /**
     * Remove the legacy Products tab from the Funnel Configuration field group.
     */
    public static function removeLegacyProductsTab(): void
    {
        add_action('admin_head', function() {
            global $post;
            if (!$post || $post->post_type !== 'hp-funnel') return;
            
            echo '<style>
                .acf-tab-wrap a[data-key*="products_tab"],
                .acf-field[data-name="funnel_products"],
                .acf-field[data-name="products_tab"] { display: none !important; }
            </style>';
            
            echo '<script>
                jQuery(function($) {
                    $(".acf-tab-wrap a").filter(function() {
                        return $(this).text().trim() === "Products";
                    }).hide();
                    $(".acf-field[data-name=\'funnel_products\']").hide();
                });
            </script>';
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

        // Enqueue Tabulator CSS and JS from CDN
        wp_enqueue_style(
            'tabulator-css',
            'https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator.min.css',
            [],
            '5.5.0'
        );

        wp_enqueue_script(
            'tabulator-js',
            'https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js',
            [],
            '5.5.0',
            true
        );

        // Enqueue offer tabulator wrapper
        wp_enqueue_script(
            'hp-rw-offer-tabulator',
            HP_RW_URL . 'assets/admin/offer-tabulator.js',
            ['jquery', 'tabulator-js'],
            HP_RW_VERSION,
            true
        );

        // Enqueue main offer calculator
        wp_enqueue_script(
            'hp-rw-offer-admin',
            HP_RW_URL . 'assets/admin/offer-calculator.js',
            ['jquery', 'acf-input', 'hp-rw-offer-tabulator'],
            HP_RW_VERSION,
            true
        );

        wp_localize_script('hp-rw-offer-admin', 'hpOfferCalc', [
            'restUrl' => rest_url('hp-rw/v1/'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);

        wp_add_inline_style('acf-input', self::getStyles());
    }

    private static function getStyles(): string
    {
        return '
            /* Offer row styling */
            .acf-field[data-key="field_funnel_offers"] .acf-row {
                background: #fafafa;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                margin-bottom: 12px;
                position: relative;
            }
            .acf-field[data-key="field_funnel_offers"] .acf-row.-collapsed {
                background: #fff;
            }
            .acf-field[data-key="field_funnel_offers"] .acf-row-handle .acf-icon.-minus {
                display: block !important;
            }

            /*
             * Repeater layout tweaks:
             * - When EXPANDED: hide the left handle column so fields use full width
             * - When COLLAPSED: make the collapsed summary take full width (minus the remove handle)
             */
            .acf-field[data-key="field_funnel_offers"] .acf-row:not(.-collapsed) > .acf-row-handle.order,
            .acf-field[data-name="funnel_offers"] .acf-row:not(.-collapsed) > .acf-row-handle.order {
                /* We provide our own collapse control, so we can fully hide the left handle column */
                display: none !important;
            }
            .acf-field[data-key="field_funnel_offers"] .acf-row:not(.-collapsed) > .acf-fields,
            .acf-field[data-name="funnel_offers"] .acf-row:not(.-collapsed) > .acf-fields {
                margin-left: 0 !important;
            }
            .acf-field[data-key="field_funnel_offers"] .acf-row:not(.-collapsed) > .acf-row-handle.remove,
            .acf-field[data-name="funnel_offers"] .acf-row:not(.-collapsed) > .acf-row-handle.remove {
                position: relative !important;
                top: auto !important;
                right: auto !important;
                height: auto !important;
            }

            .acf-field[data-key="field_funnel_offers"] .acf-row.-collapsed,
            .acf-field[data-name="funnel_offers"] .acf-row.-collapsed {
                /* keep ACF default layout; we only adjust widths */
            }
            .acf-field[data-key="field_funnel_offers"] .acf-row.-collapsed > .acf-row-handle.order,
            .acf-field[data-name="funnel_offers"] .acf-row.-collapsed > .acf-row-handle.order {
                display: block !important;
                width: calc(100% - 48px) !important;
                text-align: left !important;
                padding-left: 12px !important;
                display: flex !important;
                align-items: center;
                justify-content: flex-start;
            }
            .acf-field[data-key="field_funnel_offers"] .acf-row.-collapsed > .acf-row-handle.remove,
            .acf-field[data-name="funnel_offers"] .acf-row.-collapsed > .acf-row-handle.remove {
                width: 48px !important;
                height: auto !important;
            }
            .acf-field[data-key="field_funnel_offers"] .acf-row.-collapsed > .acf-fields,
            .acf-field[data-name="funnel_offers"] .acf-row.-collapsed > .acf-fields {
                display: none !important;
            }

            /* Ensure collapsed handle content aligns left and is larger */
            .acf-field[data-key="field_funnel_offers"] .acf-row.-collapsed > .acf-row-handle.order .acf-row-number,
            .acf-field[data-name="funnel_offers"] .acf-row.-collapsed > .acf-row-handle.order .acf-row-number {
                margin-right: 10px;
            }

            /* Hide the small inline "+" add-row icons (we already have the big "Add Offer" button) */
            .acf-field[data-key="field_funnel_offers"] .acf-row-handle.remove .acf-icon.-plus,
            .acf-field[data-name="funnel_offers"] .acf-row-handle.remove .acf-icon.-plus,
            .acf-field[data-key="field_funnel_offers"] .acf-row-handle.remove [data-event="add-row"],
            .acf-field[data-name="funnel_offers"] .acf-row-handle.remove [data-event="add-row"] {
                display: none !important;
            }
            
            /* Products section */
            .hp-products-section {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 6px;
                margin: 12px;
                padding: 0;
            }
            .hp-products-header {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                border-bottom: 1px solid #eee;
                background: #f9f9f9;
                border-radius: 6px 6px 0 0;
            }
            .hp-products-header .hp-search-wrapper {
                flex: 1;
                position: relative;
            }
            .hp-products-header input[type="text"] {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .hp-products-header .hp-max-notice {
                color: #d63638;
                font-size: 12px;
                padding: 4px 8px;
                background: #fcf0f1;
                border-radius: 4px;
            }
            
            /* Search dropdown */
            .hp-search-dropdown {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 0 0 4px 4px;
                max-height: 300px;
                overflow-y: auto;
                z-index: 1000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            .hp-search-dropdown .hp-search-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 12px;
                cursor: pointer;
                border-bottom: 1px solid #f0f0f0;
            }
            .hp-search-dropdown .hp-search-item:hover {
                background: #f0f7ff;
            }
            .hp-search-dropdown .hp-search-item img {
                width: 40px;
                height: 40px;
                object-fit: cover;
                border-radius: 4px;
            }
            .hp-search-dropdown .hp-search-item-info {
                flex: 1;
            }
            .hp-search-dropdown .hp-search-item-name {
                font-weight: 500;
            }
            .hp-search-dropdown .hp-search-item-sku {
                color: #0073aa;
                font-size: 12px;
            }
            .hp-search-dropdown .hp-search-item-price {
                font-weight: 600;
                color: #00a32a;
            }
            
            /* Products list */
            .hp-products-list {
                padding: 0;
            }
            .hp-products-list:empty::after {
                content: "No products added. Use search above to add products.";
                display: block;
                padding: 20px;
                text-align: center;
                color: #999;
                font-style: italic;
            }
            .hp-product-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                border-bottom: 1px solid #f0f0f0;
            }
            .hp-product-item:last-child {
                border-bottom: none;
            }
            .hp-product-item img {
                width: 48px;
                height: 48px;
                object-fit: cover;
                border-radius: 4px;
                flex-shrink: 0;
            }
            .hp-product-item .hp-product-info {
                flex: 1;
                min-width: 0;
            }
            .hp-product-item .hp-product-name {
                font-weight: 600;
                color: #1e1e1e;
                margin-bottom: 2px;
            }
            .hp-product-item .hp-product-sku {
                font-size: 12px;
                color: #0073aa;
            }
            .hp-product-item .hp-product-controls {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .hp-product-item .hp-qty-control {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .hp-product-item .hp-qty-control label {
                font-size: 12px;
                color: #666;
            }
            .hp-product-item .hp-qty-control input {
                width: 60px;
                text-align: center;
                padding: 4px;
            }
            .hp-product-item .hp-role-control select {
                padding: 4px 8px;
            }
            /* Tabulator role select */
            .hp-role-select {
                padding: 4px 8px;
                border: 1px solid #8c8f94;
                border-radius: 3px;
                font-size: 12px;
                min-width: 90px;
            }
            /* Pricing group */
            .hp-product-item .hp-price-group {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 2px;
                min-width: 120px;
            }
            .hp-product-item .hp-original-price {
                font-size: 12px;
                color: #666;
            }
            .hp-product-item .hp-original-price.strikethrough {
                text-decoration: line-through;
                color: #999;
            }
            .hp-product-item .hp-sale-price-control {
                display: flex;
                align-items: center;
                gap: 2px;
            }
            .hp-product-item .hp-sale-price-control .hp-currency {
                font-weight: 600;
                color: #00a32a;
            }
            .hp-product-item .hp-sale-price-input {
                width: 70px;
                text-align: right;
                padding: 4px 6px;
                font-weight: 600;
                color: #00a32a;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .hp-product-item .hp-sale-price-input:focus {
                border-color: #2271b1;
                outline: none;
            }
            .hp-product-item .hp-line-discount {
                font-size: 11px;
                color: #d63638;
                font-weight: 500;
            }
            .hp-product-item .hp-product-remove {
                color: #d63638;
                cursor: pointer;
                padding: 6px;
                border-radius: 4px;
                background: transparent;
                border: none;
                font-size: 18px;
            }
            .hp-product-item .hp-product-remove:hover {
                background: #fcf0f1;
            }
            
            /* Offer summary */
            .hp-offer-summary {
                background: #f9f9f9;
                border-top: 2px solid #ddd;
                padding: 12px;
                margin-top: 8px;
            }
            .hp-summary-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 4px 0;
            }
            .hp-summary-label {
                color: #666;
                font-size: 13px;
            }
            .hp-summary-value {
                font-weight: 500;
            }
            .hp-summary-value.strikethrough {
                text-decoration: line-through;
                color: #999;
            }
            .hp-discount-row .hp-discount-value {
                color: #d63638;
                font-weight: 600;
            }
            .hp-total-row {
                border-top: 1px solid #ddd;
                margin-top: 4px;
                padding-top: 8px;
            }
            .hp-total-row .hp-summary-label {
                font-weight: 600;
                color: #1e1e1e;
            }
            .hp-total-row .hp-total-value {
                font-size: 16px;
                font-weight: 700;
                color: #00a32a;
            }
            
            /* Kit-specific controls */
            .hp-product-item.is-kit .hp-kit-controls {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            /* Tabulator table styling */
            .hp-products-list .tabulator {
                border: none;
                background: transparent;
            }
            .hp-products-list .tabulator-header {
                background: #f5f5f5;
                border-bottom: 2px solid #ddd;
            }
            .hp-products-list .tabulator-header .tabulator-col {
                background: transparent;
                border-right: 1px solid #e0e0e0;
            }
            .hp-products-list .tabulator-header .tabulator-col-title {
                font-weight: 600;
                color: #333;
            }
            .hp-products-list .tabulator-row {
                border-bottom: 1px solid #f0f0f0;
            }
            .hp-products-list .tabulator-row:hover {
                background: #f9f9f9;
            }
            .hp-products-list .tabulator-cell {
                padding: 8px;
                border-right: 1px solid #f0f0f0;
            }
            
            /* Tabulator cell content styling */
            .hp-item-thumb img {
                width: 40px;
                height: 40px;
                object-fit: cover;
                border-radius: 4px;
            }
            .hp-item-content {
                display: flex;
                flex-direction: column;
            }
            .hp-item-name {
                font-weight: 600;
                color: #1e1e1e;
            }
            .hp-item-meta {
                font-size: 12px;
            }
            .hp-item-sku {
                color: #0073aa;
            }
            .hp-qty-input {
                width: 60px;
                text-align: center;
                padding: 4px;
            }
            .hp-price-original {
                color: #666;
            }
            .hp-discount-control {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .hp-discount-input {
                width: 70px;
                text-align: right;
                padding: 4px 6px;
            }
            /* Make sure Tabulator column is wide enough */
            .tabulator .tabulator-col[tabulator-field="discount_percent"] {
                min-width: 90px !important;
            }
            .hp-percent-symbol {
                color: #666;
                margin-left: 2px;
            }
            .hp-sale-price-control {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .hp-sale-price-control .hp-currency {
                color: #333;
            }
            .hp-sale-price-input {
                width: 70px;
                text-align: right;
                padding: 4px 6px;
            }
            .hp-line-total {
                color: #333;
            }
            .hp-price-original {
                color: #333;
            }
            .hp-remove-btn {
                color: #d63638;
                background: transparent;
                border: none;
                cursor: pointer;
                padding: 4px;
            }
            .hp-remove-btn:hover {
                background: #fcf0f1;
                border-radius: 4px;
            }
            
            /* Tabulator summary */
            .hp-offer-table-summary {
                background: #f9f9f9;
                border-top: 2px solid #ddd;
                padding: 10px 12px;
                margin-top: 0;
                max-width: 280px;
                margin-left: auto;
            }
            .hp-offer-table-summary .hp-summary-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 3px 0;
                gap: 20px;
            }
            .hp-offer-table-summary .hp-summary-label {
                color: #666;
                font-size: 13px;
            }
            .hp-offer-table-summary .hp-summary-value {
                font-weight: 500;
                text-align: right;
            }
            .hp-offer-table-summary .hp-summary-value.strikethrough {
                text-decoration: line-through;
                color: #999;
            }
            .hp-offer-table-summary .hp-discount-value {
                color: #333;
            }
            .hp-offer-table-summary .hp-total-row {
                border-top: 1px solid #ddd;
                margin-top: 4px;
                padding-top: 6px;
            }
            .hp-offer-table-summary .hp-total-row .hp-summary-label {
                font-weight: 600;
                color: #1e1e1e;
            }
            .hp-offer-table-summary .hp-total-value {
                font-size: 15px;
                font-weight: 600;
                color: #333;
            }
            
            /* Hide ACF field placeholders */
            .acf-field[data-key="field_offer_products_data"] {
                display: none !important;
            }
            .hp-products-container-field {
                padding: 0 !important;
                margin: 0 !important;
            }
            .hp-products-container-field > .acf-label {
                display: none !important;
            }
            
            /* Collapsed summary */
            .hp-offer-collapsed-summary {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                margin-left: 0;
                font-size: 14px;
                color: #1e1e1e;
                justify-content: flex-start;
            }
            .hp-offer-collapsed-summary .hp-summary-count {
                background: #0073aa;
                color: #fff;
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 11px;
            }
            .hp-offer-collapsed-summary .hp-summary-price {
                color: #00a32a;
                font-weight: 600;
            }

            .hp-offer-collapsed-thumb {
                width: 26px;
                height: 26px;
                border-radius: 4px;
                object-fit: cover;
                border: 1px solid #e0e0e0;
                background: #f5f5f5;
                flex-shrink: 0;
            }
            .hp-offer-collapsed-thumb--empty {
                display: inline-block;
            }
            .hp-offer-collapsed-text {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                line-height: 1.2;
                white-space: nowrap;
            }
            .hp-offer-collapsed-name {
                font-weight: 700;
                color: #1e1e1e;
                font-size: 15px;
            }
            .hp-offer-collapsed-type {
                font-weight: 600;
                color: #1e1e1e;
            }
            .hp-offer-collapsed-price {
                color: #00a32a;
                font-weight: 700;
            }
            .hp-offer-collapsed-dot {
                color: #999;
            }

            /* Collapse button shown on expanded rows (right side) */
            .hp-offer-collapse-toggle {
                margin-left: 8px;
            }

            /* Right-side handle layout: stack collapse + remove vertically and prevent overlap */
            .acf-field[data-key="field_funnel_offers"] .acf-row-handle.remove,
            .acf-field[data-name="funnel_offers"] .acf-row-handle.remove {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: flex-start !important;
                gap: 6px !important;
                padding-top: 6px !important;
            }

            /* Show collapse button only when expanded */
            .acf-field[data-key="field_funnel_offers"] .acf-row.-collapsed .hp-offer-collapse-toggle,
            .acf-field[data-name="funnel_offers"] .acf-row.-collapsed .hp-offer-collapse-toggle {
                display: none !important;
            }

            /* Make the collapse button look like a small icon (no extra padding) */
            .hp-offer-collapse-toggle {
                padding: 0 !important;
                line-height: 1 !important;
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
            
            // Row 2: Image and Offer Price
            [
                'key' => 'field_offer_image',
                'label' => 'Image Override',
                'name' => 'offer_image',
                'type' => 'image',
                'return_format' => 'url',
                'preview_size' => 'thumbnail',
                'wrapper' => ['width' => '20'],
            ],
            [
                'key' => 'field_offer_price',
                'label' => 'Offer Price',
                'name' => 'offer_price',
                'type' => 'number',
                'min' => 0,
                'step' => '0.01',
                'prepend' => '$',
                'instructions' => 'Final price shown to customer. Auto-filled from product table totals.',
                'wrapper' => ['width' => '20', 'class' => 'hp-offer-price-field'],
            ],
            
            // Row 3: Discount Label (auto-generated but overridable)
            [
                'key' => 'field_offer_discount_label',
                'label' => 'Discount Label',
                'name' => 'offer_discount_label',
                'type' => 'text',
                'placeholder' => 'Auto: Save X%',
                'instructions' => 'Auto-filled from table discount. Override if needed.',
                'wrapper' => ['width' => '25', 'class' => 'hp-discount-label-field'],
            ],
            [
                'key' => 'field_offer_description',
                'label' => 'Description',
                'name' => 'offer_description',
                'type' => 'text',
                'wrapper' => ['width' => '35'],
            ],
            
            // Bonus message shown in Quantity card
            [
                'key' => 'field_offer_bonus_message',
                'label' => 'Bonus Message (Qty Card)',
                'name' => 'offer_bonus_message',
                'type' => 'text',
                'instructions' => 'Shown when quantity > 1. Use {qty} for quantity.',
                'placeholder' => 'e.g. You\'ll receive {qty} FREE bonus item(s)!',
                'wrapper' => ['width' => '100'],
            ],
            
            // Kit max items (only for kit type)
            [
                'key' => 'field_kit_max_items',
                'label' => 'Max Items',
                'name' => 'kit_max_items',
                'type' => 'number',
                'default_value' => 6,
                'min' => 1,
                'wrapper' => ['width' => '15'],
                'conditional_logic' => [
                    [['field' => 'field_offer_type', 'operator' => '==', 'value' => 'customizable_kit']],
                ],
            ],
            
            // Products data (JSON stored, hidden)
            [
                'key' => 'field_offer_products_data',
                'label' => '',
                'name' => 'products_data',
                'type' => 'textarea',
                'wrapper' => ['class' => 'hp-hidden-field'],
                'rows' => 2,
            ],
            
            // Products UI container - JS will inject the search input
            [
                'key' => 'field_offer_products_wrapper',
                'label' => 'Products',
                'name' => 'products_wrapper',
                'type' => 'group',
                'wrapper' => ['width' => '100'],
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'key' => 'field_offer_products_container',
                        'label' => '',
                        'name' => 'container',
                        'type' => 'message',
                        'message' => '<div class="hp-products-section" data-offer-products></div>',
                        'esc_html' => 0,
                    ],
                ],
            ],
        ];
    }
}
