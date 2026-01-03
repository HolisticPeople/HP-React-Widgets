<?php
namespace HP_RW\Admin;

use HP_RW\Services\FunnelSeoService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Settings Page for SEO & Tracking configuration.
 * 
 * Menu: HP Funnels → SEO & Tracking
 * 
 * Tabs:
 * - Schema Settings
 * - Analytics Settings
 * - Canonical Settings
 * - General Settings
 * 
 * @since 2.9.0
 */
class SeoTrackingSettings
{
    private const PAGE_SLUG = 'hp-funnel-seo-tracking';

    /**
     * Initialize the settings page.
     */
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'addMenuPage']);
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    /**
     * Add submenu page under HP Funnels.
     */
    public static function addMenuPage(): void
    {
        add_submenu_page(
            'edit.php?post_type=hp-funnel',
            'SEO & Tracking Settings',
            'SEO & Tracking',
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderPage']
        );
    }

    /**
     * Register settings.
     */
    public static function registerSettings(): void
    {
        register_setting(
            self::PAGE_SLUG,
            FunnelSeoService::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [self::class, 'sanitizeSettings'],
                'default'           => [],
            ]
        );
    }

    /**
     * Sanitize settings on save.
     */
    public static function sanitizeSettings($input): array
    {
        $sanitized = [];

        // Booleans
        $boolFields = [
            'enable_schema', 'include_testimonials', 'enable_fibosearch', 'schema_debug_mode',
            'enable_analytics', 'push_to_gtm', 'push_to_ga4',
            'track_view_item', 'track_add_to_cart', 'track_begin_checkout', 'track_purchase',
            'console_debug_mode',
            'enable_canonical_swaps', 'type1_product_swap', 'type2_category_swap', 'show_canonical_column',
            'auto_calculate_price_range',
        ];

        foreach ($boolFields as $field) {
            $sanitized[$field] = !empty($input[$field]);
        }

        // Text fields
        $sanitized['default_brand'] = sanitize_text_field($input['default_brand'] ?? 'HolisticPeople');
        $sanitized['custom_button_selectors'] = sanitize_text_field($input['custom_button_selectors'] ?? '');
        $sanitized['price_display_format'] = sanitize_text_field($input['price_display_format'] ?? 'range');

        // Numbers
        $sanitized['min_price_threshold'] = max(0.01, (float) ($input['min_price_threshold'] ?? 0.01));

        return $sanitized;
    }

    /**
     * Render the settings page.
     */
    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = FunnelSeoService::getSettings();
        $activeTab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'schema';

        ?>
        <div class="wrap">
            <h1>SEO & Tracking Settings</h1>
            <p class="description">Configure Schema.org output, Google Analytics tracking, and canonical URL management for HP Funnels.</p>

            <?php settings_errors(); ?>

            <h2 class="nav-tab-wrapper">
                <a href="?post_type=hp-funnel&page=<?php echo self::PAGE_SLUG; ?>&tab=schema" 
                   class="nav-tab <?php echo $activeTab === 'schema' ? 'nav-tab-active' : ''; ?>">
                    Schema
                </a>
                <a href="?post_type=hp-funnel&page=<?php echo self::PAGE_SLUG; ?>&tab=analytics" 
                   class="nav-tab <?php echo $activeTab === 'analytics' ? 'nav-tab-active' : ''; ?>">
                    Analytics
                </a>
                <a href="?post_type=hp-funnel&page=<?php echo self::PAGE_SLUG; ?>&tab=canonical" 
                   class="nav-tab <?php echo $activeTab === 'canonical' ? 'nav-tab-active' : ''; ?>">
                    Canonical
                </a>
                <a href="?post_type=hp-funnel&page=<?php echo self::PAGE_SLUG; ?>&tab=general" 
                   class="nav-tab <?php echo $activeTab === 'general' ? 'nav-tab-active' : ''; ?>">
                    General
                </a>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE_SLUG); ?>

                <div class="hp-seo-settings-content" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-top:none;">
                    <?php
                    switch ($activeTab) {
                        case 'analytics':
                            self::renderAnalyticsTab($settings);
                            break;
                        case 'canonical':
                            self::renderCanonicalTab($settings);
                            break;
                        case 'general':
                            self::renderGeneralTab($settings);
                            break;
                        default:
                            self::renderSchemaTab($settings);
                    }
                    ?>
                </div>

                <?php submit_button('Save Settings'); ?>
            </form>

            <div style="margin-top:30px; padding:15px; background:#f0f6fc; border-left:4px solid #2271b1;">
                <strong>HP React Widgets v<?php echo HP_RW_VERSION; ?></strong> — Smart Bridge for Funnel SEO & Analytics
            </div>
        </div>

        <style>
            .hp-seo-settings-content table.form-table th { width: 250px; }
            .hp-seo-settings-content .description { color: #666; font-style: italic; }
            .hp-seo-settings-content code { background: #f0f0f1; padding: 2px 6px; }
        </style>
        <?php
    }

    /**
     * Render Schema Settings tab.
     */
    private static function renderSchemaTab(array $settings): void
    {
        $optionKey = FunnelSeoService::OPTION_KEY;
        ?>
        <h3>Schema.org Settings</h3>
        <p>Controls how funnel pages output structured data for Google Search and Shopping.</p>

        <table class="form-table">
            <tr>
                <th scope="row">Enable Schema Output</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[enable_schema]" value="1" 
                            <?php checked($settings['enable_schema']); ?>>
                        Inject Product schema (with AggregateOffer) on funnel pages
                    </label>
                    <p class="description">Master switch for all schema injection.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Default Brand</th>
                <td>
                    <input type="text" name="<?php echo $optionKey; ?>[default_brand]" 
                           value="<?php echo esc_attr($settings['default_brand']); ?>" 
                           class="regular-text">
                    <p class="description">Used when products don't share a single brand. Typically your company name.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Include Testimonials as Reviews</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[include_testimonials]" value="1" 
                            <?php checked($settings['include_testimonials']); ?>>
                        Map <code>testimonials_list</code> repeater to Review schema
                    </label>
                    <p class="description">Adds aggregateRating and review markup for rich results stars.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Enable FiboSearch Integration</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[enable_fibosearch]" value="1" 
                            <?php checked($settings['enable_fibosearch']); ?>>
                        Index funnel posts and hidden product SKUs in site search
                    </label>
                    <p class="description">Allows searching for product SKUs to return funnel pages.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Schema Debug Mode</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[schema_debug_mode]" value="1" 
                            <?php checked($settings['schema_debug_mode']); ?>>
                        Output schema as HTML comment for debugging
                    </label>
                    <p class="description">Adds readable JSON-LD in page source. Disable in production.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Analytics Settings tab.
     */
    private static function renderAnalyticsTab(array $settings): void
    {
        $optionKey = FunnelSeoService::OPTION_KEY;
        ?>
        <h3>Analytics & Tracking Settings</h3>
        <p>Controls GA4/GTM event tracking for funnel pages.</p>

        <table class="form-table">
            <tr>
                <th scope="row">Enable Analytics Tracking</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[enable_analytics]" value="1" 
                            <?php checked($settings['enable_analytics']); ?>>
                        Inject funnel data and event tracking scripts
                    </label>
                    <p class="description">Master switch for all analytics features.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Push to GTM dataLayer</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[push_to_gtm]" value="1" 
                            <?php checked($settings['push_to_gtm']); ?>>
                        Fire events to <code>window.dataLayer</code>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">Push to GA4 gtag</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[push_to_ga4]" value="1" 
                            <?php checked($settings['push_to_ga4']); ?>>
                        Fire events via <code>gtag()</code> function
                    </label>
                    <p class="description">Dual tracking provides redundancy.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Custom Button Selectors</th>
                <td>
                    <input type="text" name="<?php echo $optionKey; ?>[custom_button_selectors]" 
                           value="<?php echo esc_attr($settings['custom_button_selectors']); ?>" 
                           class="large-text">
                    <p class="description">CSS selectors for buy buttons (comma-separated). Default: <code>.hp-funnel-cta-btn, [data-checkout-submit]</code></p>
                </td>
            </tr>

            <tr><td colspan="2"><hr><h4>Event Tracking</h4></td></tr>

            <tr>
                <th scope="row">Track view_item</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[track_view_item]" value="1" 
                            <?php checked($settings['track_view_item']); ?>>
                        Fire on funnel landing page load
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">Track add_to_cart</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[track_add_to_cart]" value="1" 
                            <?php checked($settings['track_add_to_cart']); ?>>
                        Fire on buy button click
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">Track begin_checkout</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[track_begin_checkout]" value="1" 
                            <?php checked($settings['track_begin_checkout']); ?>>
                        Fire on <code>/checkout/</code> URL
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">Track purchase</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[track_purchase]" value="1" 
                            <?php checked($settings['track_purchase']); ?>>
                        Fire on <code>/thank-you/</code> URL with order ID
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">Console Debug Mode</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[console_debug_mode]" value="1" 
                            <?php checked($settings['console_debug_mode']); ?>>
                        Log events to browser console
                    </label>
                    <p class="description">Useful for testing. Disable in production.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Canonical Settings tab.
     */
    private static function renderCanonicalTab(array $settings): void
    {
        $optionKey = FunnelSeoService::OPTION_KEY;
        ?>
        <h3>Canonical URL Settings</h3>
        <p>Controls canonical URL swaps between products/categories and funnels.</p>

        <table class="form-table">
            <tr>
                <th scope="row">Enable Canonical Swaps</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[enable_canonical_swaps]" value="1" 
                            <?php checked($settings['enable_canonical_swaps']); ?>>
                        Allow products and categories to point canonical to funnels
                    </label>
                    <p class="description">Master switch for canonical redirect features.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Type-1: Product → Funnel</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[type1_product_swap]" value="1" 
                            <?php checked($settings['type1_product_swap']); ?>>
                        Single products with <code>product_funnel_override</code> field set
                    </label>
                    <p class="description">Use for single-product funnels that should replace the WooCommerce product page.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Type-2/3: Category → Funnel</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[type2_category_swap]" value="1" 
                            <?php checked($settings['type2_category_swap']); ?>>
                        Categories with <code>category_canonical_funnel</code> field set
                    </label>
                    <p class="description">Use for bundle funnels that represent an entire product category.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Show Canonical Column</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[show_canonical_column]" value="1" 
                            <?php checked($settings['show_canonical_column']); ?>>
                        Display "Funnel SEO" column in WooCommerce Products list
                    </label>
                    <p class="description">Shows which products have funnel overrides.</p>
                </td>
            </tr>
        </table>

        <div style="margin-top:20px; padding:15px; background:#fcf0f1; border-left:4px solid #d63638;">
            <strong>How Canonical Swaps Work:</strong><br>
            When a product or category has a funnel override set, visitors who land on the WooCommerce page 
            will see the canonical URL pointing to the funnel. This tells Google to index the funnel instead.
            The product/category page still works for direct access.
        </div>
        <?php
    }

    /**
     * Render General Settings tab.
     */
    private static function renderGeneralTab(array $settings): void
    {
        $optionKey = FunnelSeoService::OPTION_KEY;
        ?>
        <h3>General Settings</h3>
        <p>Price calculation and display preferences.</p>

        <table class="form-table">
            <tr>
                <th scope="row">Auto-Calculate Price Range</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo $optionKey; ?>[auto_calculate_price_range]" value="1" 
                            <?php checked($settings['auto_calculate_price_range']); ?>>
                        Recalculate min/max prices when funnel is saved
                    </label>
                    <p class="description">Stores prices in post meta for fast schema output.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Minimum Price Threshold</th>
                <td>
                    <input type="number" name="<?php echo $optionKey; ?>[min_price_threshold]" 
                           value="<?php echo esc_attr($settings['min_price_threshold']); ?>" 
                           step="0.01" min="0.01" class="small-text">
                    <p class="description">Prevents $0 prices in schema. Default: 0.01</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Price Display Format</th>
                <td>
                    <select name="<?php echo $optionKey; ?>[price_display_format]">
                        <option value="range" <?php selected($settings['price_display_format'], 'range'); ?>>
                            Range: "$89 – $249"
                        </option>
                        <option value="starting_at" <?php selected($settings['price_display_format'], 'starting_at'); ?>>
                            Starting at: "Starting at $89"
                        </option>
                        <option value="from" <?php selected($settings['price_display_format'], 'from'); ?>>
                            From: "From $89"
                        </option>
                    </select>
                    <p class="description">How price range is displayed in UI (schema always uses lowPrice/highPrice).</p>
                </td>
            </tr>
        </table>
        <?php
    }
}

















