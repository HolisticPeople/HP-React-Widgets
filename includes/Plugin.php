<?php
namespace HP_RW;

class Plugin
{
    /**
     * Post type for funnels (registered via ACF Pro).
     */
    public const FUNNEL_POST_TYPE = 'hp-funnel';

    /**
     * Option name used to store which shortcodes are enabled.
     */
    private const OPTION_ENABLED_SHORTCODES = 'hp_rw_enabled_shortcodes';
    /**
     * Option name used to store all shortcode metadata (both initial and wizard-created).
     */
    private const OPTION_SHORTCODES = 'hp_rw_shortcodes';
    /**
     * Option name used to store custom shortcode description overrides.
     */
    private const OPTION_SHORTCODE_DESCRIPTIONS = 'hp_rw_shortcode_descriptions';

    /**
     * Registry of all shortcodes this plugin can provide.
     *
     * New shortcodes should be added here so they automatically appear
     * in the Settings screen and can be toggled on/off.
     *
     * @var array<string,array<string,string>>
     */
    private const DEFAULT_SHORTCODES = [
        // Account components
        'hp_multi_address' => [
            'label'       => 'My Account Multi-Address',
            'description' => 'Replaces the WooCommerce My Account addresses section with a React-based multi-address UI.',
            'example'     => '[hp_multi_address]',
            'component'   => 'MultiAddress',
            'root_id'     => 'hp-multi-address-root',
            'hydrator_class' => 'MultiAddressShortcode',
        ],
        'hp_my_account_header' => [
            'label'       => 'My Account Header',
            'description' => 'Displays the My Account header widget with avatar, name and navigation icons.',
            'example'     => '[hp_my_account_header]',
            'component'   => 'MyAccountHeader',
            'root_id'     => 'hp-my-account-header-root',
            'hydrator_class' => 'MyAccountHeaderShortcode',
        ],
        'hp_address_card_picker' => [
            'label'       => 'Address Card Picker',
            'description' => 'Slick horizontal slider for managing billing/shipping addresses.',
            'example'     => '[hp_address_card_picker]',
            'component'   => 'AddressCardPicker',
            'root_id'     => 'hp-address-card-picker-root',
            'hydrator_class' => 'AddressCardPickerShortcode',
        ],
        // Funnel components
        'hp_funnel_hero' => [
            'label'       => 'Funnel Hero',
            'description' => 'Landing page hero section for sales funnels with product selection.',
            'example'     => '[hp_funnel_hero funnel="illumodine"]',
            'component'   => 'FunnelHero',
            'root_id'     => 'hp-funnel-hero-root',
            'hydrator_class' => 'FunnelHeroShortcode',
        ],
        'hp_funnel_checkout' => [
            'label'       => 'Funnel Checkout',
            'description' => 'Checkout page for sales funnels with product selection and payment.',
            'example'     => '[hp_funnel_checkout funnel="illumodine"]',
            'component'   => 'FunnelCheckout',
            'root_id'     => 'hp-funnel-checkout-root',
            'hydrator_class' => 'FunnelCheckoutShortcode',
        ],
        'hp_funnel_thankyou' => [
            'label'       => 'Funnel Thank You',
            'description' => 'Thank you page with order summary and upsell offers.',
            'example'     => '[hp_funnel_thankyou funnel="illumodine"]',
            'component'   => 'FunnelThankYou',
            'root_id'     => 'hp-funnel-thankyou-root',
            'hydrator_class' => 'FunnelThankYouShortcode',
        ],
    ];

    /**
     * Plugin bootstrap.
     */
    public static function init(): void
    {
        // Set up funnel CPT customizations (CPT registered via ACF Pro)
        self::setupFunnelCptHooks();

        // Initialize Funnel Export/Import admin page
        Admin\FunnelExportImport::init();

        // Initialize Product Lookup API for admin
        Admin\ProductLookupApi::init();

        // Enqueue admin scripts for funnel editing
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminScripts']);

        $assetLoader = new AssetLoader();
        $assetLoader->register();

        // Register REST API endpoints for widget interactions.
        $addressApi = new AddressApi();
        $addressApi->register();

        // Register checkout REST API endpoints.
        $checkoutApi = new Rest\CheckoutApi();
        $checkoutApi->register();

        // Register upsell REST API endpoints.
        $upsellApi = new Rest\UpsellApi();
        $upsellApi->register();

        // Register shipping rates REST API endpoints.
        $shippingApi = new Rest\ShippingApi();
        $shippingApi->register();

        // Register shortcodes based on current settings.
        $shortcodeRegistry = new ShortcodeRegistry($assetLoader);
        $shortcodeRegistry->register();

        // Register the admin settings page for managing shortcodes.
        $settingsPage = new SettingsPage();
        $settingsPage->init();
    }

    /**
     * Set up hooks for customizing the funnel CPT admin UI.
     * Note: The CPT itself is registered via ACF Pro.
     */
    private static function setupFunnelCptHooks(): void
    {
        // Add custom columns to the funnels list table
        add_filter('manage_' . self::FUNNEL_POST_TYPE . '_posts_columns', [self::class, 'addFunnelColumns']);
        add_action('manage_' . self::FUNNEL_POST_TYPE . '_posts_custom_column', [self::class, 'renderFunnelColumn'], 10, 2);
        
        // Clear cache on save
        add_action('save_post_' . self::FUNNEL_POST_TYPE, [self::class, 'onFunnelSave'], 10, 3);
    }

    /**
     * Add custom columns to the funnels list table.
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public static function addFunnelColumns(array $columns): array
    {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add our columns after title
            if ($key === 'title') {
                $new_columns['funnel_slug'] = __('Slug', 'hp-react-widgets');
                $new_columns['shortcode'] = __('Shortcode', 'hp-react-widgets');
                $new_columns['status'] = __('Status', 'hp-react-widgets');
            }
        }
        
        return $new_columns;
    }

    /**
     * Render custom column content.
     *
     * @param string $column Column name
     * @param int $post_id Post ID
     */
    public static function renderFunnelColumn(string $column, int $post_id): void
    {
        switch ($column) {
            case 'funnel_slug':
                $slug = get_field('funnel_slug', $post_id);
                if (!$slug) {
                    $slug = get_post_field('post_name', $post_id);
                }
                echo '<code>' . esc_html($slug) . '</code>';
                break;
                
            case 'shortcode':
                $slug = get_field('funnel_slug', $post_id);
                if (!$slug) {
                    $slug = get_post_field('post_name', $post_id);
                }
                $shortcode = '[hp_funnel_hero funnel="' . esc_attr($slug) . '"]';
                echo '<code style="font-size: 11px; background: #f0f0f1; padding: 2px 6px; border-radius: 3px;">' . esc_html($shortcode) . '</code>';
                break;
                
            case 'status':
                $status = get_field('funnel_status', $post_id);
                if ($status === 'inactive') {
                    echo '<span style="color: #d63638;">●</span> ' . __('Inactive', 'hp-react-widgets');
                } else {
                    echo '<span style="color: #00a32a;">●</span> ' . __('Active', 'hp-react-widgets');
                }
                break;
        }
    }

    /**
     * Handle funnel post save - clear caches and auto-generate slug.
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public static function onFunnelSave(int $post_id, \WP_Post $post, bool $update): void
    {
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Clear the funnel config cache
        if (class_exists('HP_RW\\Services\\FunnelConfigLoader')) {
            Services\FunnelConfigLoader::clearCache($post_id);
        }

        // Auto-generate slug from title if not set
        if (function_exists('get_field') && function_exists('update_field')) {
            $slug = get_field('funnel_slug', $post_id);
            if (empty($slug)) {
                $auto_slug = sanitize_title($post->post_title);
                update_field('funnel_slug', $auto_slug, $post_id);
            }
        }
    }

    /**
     * Activation hook callback. Ensures default settings exist.
     */
    public static function activate(): void
    {
        // If no "enabled" option is stored yet, enable all known shortcodes by default.
        $stored = get_option(self::OPTION_ENABLED_SHORTCODES, null);
        if ($stored === null) {
            $shortcodes = array_keys(self::get_shortcodes());
            update_option(self::OPTION_ENABLED_SHORTCODES, $shortcodes);
        }
    }

    /**
     * Enqueue admin scripts for funnel editing.
     *
     * @param string $hook Current admin page hook
     */
    public static function enqueueAdminScripts(string $hook): void
    {
        global $post;

        // Only load on funnel edit screens
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        if (!$post || $post->post_type !== self::FUNNEL_POST_TYPE) {
            return;
        }

        // Enqueue the product lookup script
        wp_enqueue_script(
            'hp-rw-funnel-product-lookup',
            HP_RW_URL . 'assets/admin/funnel-product-lookup.js',
            ['jquery', 'acf-input'],
            HP_RW_VERSION,
            true
        );

        // Pass data to script
        wp_localize_script('hp-rw-funnel-product-lookup', 'hpRwAdmin', [
            'restUrl' => rest_url(),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Get metadata for all available shortcodes.
     *
     * @return array<string,array<string,string>>
     */
    public static function get_shortcodes(): array
    {
        $stored = get_option(self::OPTION_SHORTCODES, null);

        if (!is_array($stored)) {
            $stored = [];
        }

        // Ensure default shortcodes always exist and pick up new metadata.
        foreach (self::DEFAULT_SHORTCODES as $slug => $meta) {
            if (isset($stored[$slug]) && is_array($stored[$slug])) {
                $stored[$slug] = array_merge($meta, $stored[$slug]);
            } else {
                $stored[$slug] = $meta;
            }
        }

        // If the option was missing entirely, persist the seeded defaults.
        if (get_option(self::OPTION_SHORTCODES, null) === null) {
            update_option(self::OPTION_SHORTCODES, $stored);
        }

        // Apply description overrides if any have been configured explicitly.
        $descriptionOverrides = self::get_shortcode_descriptions();
        if (is_array($descriptionOverrides) && !empty($descriptionOverrides)) {
            foreach ($descriptionOverrides as $slug => $description) {
                if (isset($stored[$slug]) && is_string($description) && $description !== '') {
                    $stored[$slug]['description'] = $description;
                }
            }
        }

        /**
         * Filter the list of available HP React Widgets shortcodes.
         *
         * @param array $shortcodes Associative array of shortcode slug => metadata.
         */
        return apply_filters('hp_rw_shortcodes', $stored);
    }

    /**
     * Get the list of enabled shortcode slugs.
     *
     * @return string[]
     */
    public static function get_enabled_shortcodes(): array
    {
        $allShortcodes = array_keys(self::get_shortcodes());
        $stored        = get_option(self::OPTION_ENABLED_SHORTCODES);

        if (!is_array($stored)) {
            // If the option is missing or malformed, treat all as enabled.
            return $allShortcodes;
        }

        // Only keep valid shortcode slugs.
        $enabled = array_values(array_intersect($allShortcodes, $stored));

        if (empty($enabled)) {
            // Safety: never end up with zero enabled shortcodes unintentionally.
            return $allShortcodes;
        }

        return $enabled;
    }

    /**
     * Persist the list of enabled shortcodes.
     *
     * @param string[] $shortcodeSlugs
     */
    public static function set_enabled_shortcodes(array $shortcodeSlugs): void
    {
        $allShortcodes = array_keys(self::get_shortcodes());

        // Keep only known shortcodes and de-duplicate.
        $shortcodeSlugs = array_values(array_unique(array_intersect($allShortcodes, $shortcodeSlugs)));

        update_option(self::OPTION_ENABLED_SHORTCODES, $shortcodeSlugs);
    }

    /**
     * Persist all shortcode metadata.
     *
     * @return array<string,array<string,string>>
     */
    public static function set_shortcodes(array $shortcodes): void
    {
        update_option(self::OPTION_SHORTCODES, $shortcodes);
    }

    /**
     * Get description overrides for shortcodes.
     *
     * @return array<string,string>
     */
    public static function get_shortcode_descriptions(): array
    {
        $stored = get_option(self::OPTION_SHORTCODE_DESCRIPTIONS, []);
        return is_array($stored) ? $stored : [];
    }

    /**
     * Persist description overrides for shortcodes.
     *
     * @param array<string,string> $descriptions
     */
    public static function set_shortcode_descriptions(array $descriptions): void
    {
        update_option(self::OPTION_SHORTCODE_DESCRIPTIONS, $descriptions);
    }
}
