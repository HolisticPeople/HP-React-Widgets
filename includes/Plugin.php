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
     * Option name used to store responsive settings (breakpoints, max-width, scroll).
     */
    private const OPTION_RESPONSIVE_SETTINGS = 'hp_rw_responsive_settings';

    /**
     * Default responsive settings.
     */
    private const DEFAULT_RESPONSIVE_SETTINGS = [
        // Breakpoint pixel values
        'breakpoint_tablet'  => 640,   // Mobile ends, tablet starts
        'breakpoint_laptop'  => 1024,  // Tablet ends, laptop starts
        'breakpoint_desktop' => 1440,  // Laptop ends, desktop starts
        // Content max-width for boxed text areas
        'content_max_width'  => 1400,  // Default max-width in pixels (range: 1000-1600)
        // Scroll settings
        'enable_smooth_scroll' => true,
        'scroll_duration'      => 800,   // ms
        'scroll_easing'        => 'ease-out-cubic', // ease-out-cubic, ease-out-quad, linear
        'enable_scroll_snap'   => false, // Experimental
    ];

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
        // Checkout SPA (hybrid approach - checkout->upsell->thankyou in one component)
        'hp_funnel_checkout_app' => [
            'label'       => 'Funnel Checkout App (SPA)',
            'description' => 'Full checkout SPA with checkout, upsell, and thank you steps. Use on a dedicated checkout page.',
            'example'     => '[hp_funnel_checkout_app funnel="illumodine"]',
            'component'   => 'FunnelCheckoutApp',
            'root_id'     => 'hp-funnel-checkout-app-root',
            'hydrator_class' => 'FunnelCheckoutAppShortcode',
        ],
        // Modular funnel section components
        'hp_funnel_styles' => [
            'label'       => 'Funnel Global Styles',
            'description' => 'Outputs global CSS variables and background for a funnel. Place at the TOP of your page.',
            'example'     => '[hp_funnel_styles funnel="illumodine"]',
            'component'   => null, // No React component - pure CSS output
            'root_id'     => null,
            'hydrator_class' => 'FunnelStylesShortcode',
        ],
        'hp_funnel_header' => [
            'label'       => 'Funnel Header',
            'description' => 'Header section with logo and optional navigation.',
            'example'     => '[hp_funnel_header funnel="illumodine" sticky="true"]',
            'component'   => 'FunnelHeader',
            'root_id'     => 'hp-funnel-header-root',
            'hydrator_class' => 'FunnelHeaderShortcode',
        ],
        'hp_funnel_hero_section' => [
            'label'       => 'Funnel Hero Section',
            'description' => 'Hero section with headline, image, and CTA button.',
            'example'     => '[hp_funnel_hero_section funnel="illumodine"]',
            'component'   => 'FunnelHeroSection',
            'root_id'     => 'hp-funnel-hero-section-root',
            'hydrator_class' => 'FunnelHeroSectionShortcode',
        ],
        'hp_funnel_benefits' => [
            'label'       => 'Funnel Benefits',
            'description' => 'Benefits section with icon cards.',
            'example'     => '[hp_funnel_benefits funnel="illumodine" columns="3"]',
            'component'   => 'FunnelBenefits',
            'root_id'     => 'hp-funnel-benefits-root',
            'hydrator_class' => 'FunnelBenefitsShortcode',
        ],
        'hp_funnel_products' => [
            'label'       => 'Funnel Products',
            'description' => 'Product showcase section with pricing and features.',
            'example'     => '[hp_funnel_products funnel="illumodine" layout="grid"]',
            'component'   => 'FunnelProducts',
            'root_id'     => 'hp-funnel-products-root',
            'hydrator_class' => 'FunnelProductsShortcode',
        ],
        'hp_funnel_features' => [
            'label'       => 'Funnel Features',
            'description' => 'Features section with icons, titles, and descriptions.',
            'example'     => '[hp_funnel_features funnel="illumodine" columns="3"]',
            'component'   => 'FunnelFeatures',
            'root_id'     => 'hp-funnel-features-root',
            'hydrator_class' => 'FunnelFeaturesShortcode',
        ],
        'hp_funnel_authority' => [
            'label'       => 'Funnel Authority',
            'description' => '"Who We Are" section with expert bio and quotes.',
            'example'     => '[hp_funnel_authority funnel="illumodine" layout="side-by-side"]',
            'component'   => 'FunnelAuthority',
            'root_id'     => 'hp-funnel-authority-root',
            'hydrator_class' => 'FunnelAuthorityShortcode',
        ],
        'hp_funnel_testimonials' => [
            'label'       => 'Funnel Testimonials',
            'description' => 'Customer testimonials with ratings.',
            'example'     => '[hp_funnel_testimonials funnel="illumodine" columns="3"]',
            'component'   => 'FunnelTestimonials',
            'root_id'     => 'hp-funnel-testimonials-root',
            'hydrator_class' => 'FunnelTestimonialsShortcode',
        ],
        'hp_funnel_faq' => [
            'label'       => 'Funnel FAQ',
            'description' => 'FAQ accordion section.',
            'example'     => '[hp_funnel_faq funnel="illumodine"]',
            'component'   => 'FunnelFaq',
            'root_id'     => 'hp-funnel-faq-root',
            'hydrator_class' => 'FunnelFaqShortcode',
        ],
        'hp_funnel_cta' => [
            'label'       => 'Funnel CTA',
            'description' => 'Secondary call-to-action section.',
            'example'     => '[hp_funnel_cta funnel="illumodine" alignment="center"]',
            'component'   => 'FunnelCta',
            'root_id'     => 'hp-funnel-cta-root',
            'hydrator_class' => 'FunnelCtaShortcode',
        ],
        'hp_funnel_footer' => [
            'label'       => 'Funnel Footer',
            'description' => 'Footer with disclaimer and links.',
            'example'     => '[hp_funnel_footer funnel="illumodine"]',
            'component'   => 'FunnelFooter',
            'root_id'     => 'hp-funnel-footer-root',
            'hydrator_class' => 'FunnelFooterShortcode',
        ],
        'hp_funnel_science' => [
            'label'       => 'Funnel Science',
            'description' => 'Scientific/technical information section.',
            'example'     => '[hp_funnel_science funnel="illumodine"]',
            'component'   => 'FunnelScience',
            'root_id'     => 'hp-funnel-science-root',
            'hydrator_class' => 'FunnelScienceShortcode',
        ],
        'hp_funnel_infographics' => [
            'label'       => 'Funnel Infographics',
            'description' => 'Responsive comparison infographic with mobile panel display.',
            'example'     => '[hp_funnel_infographics funnel="illumodine"]',
            'component'   => 'FunnelInfographics',
            'root_id'     => 'hp-funnel-infographics-root',
            'hydrator_class' => 'FunnelInfographicsShortcode',
        ],
        'hp_funnel_scroll_navigation' => [
            'label'       => 'Funnel Scroll Navigation',
            'description' => 'Fixed scroll navigation dots on the right side of the viewport.',
            'example'     => '[hp_funnel_scroll_navigation]',
            'component'   => 'ScrollNavigation',
            'root_id'     => 'hp-funnel-scroll-navigation-root',
            'hydrator_class' => 'FunnelScrollNavigationShortcode',
        ],
        // Navigation components
        'hp_menu' => [
            'label'       => 'HP Menu',
            'description' => 'Off-canvas navigation menu with hamburger trigger. Place in header.',
            'example'     => '[hp_menu]',
            'component'   => 'HpMenu',
            'root_id'     => 'hp-menu-root',
            'hydrator_class' => 'HpMenuShortcode',
        ],
    ];

    /**
     * Plugin bootstrap.
     */
    public static function init(): void
    {
        // Register the HP Funnel custom post type (fallback if not registered by ACF Pro)
        FunnelPostType::init();
        
        // Schedule upgrade check for later (after WP is fully initialized)
        add_action('init', [self::class, 'checkForUpgrade'], 99);

        // Elementor sometimes runs its frontend bundle before localizing `elementorFrontendConfig`,
        // which throws a ReferenceError and can cascade into other plugin errors.
        // This shim is safe and tiny; it just guarantees the global exists early.
        add_action('wp_head', [self::class, 'outputElementorFrontendConfigShim'], 0);
        
        // Add funnel-specific body classes for styling
        add_filter('body_class', [self::class, 'addFunnelBodyClasses']);
        
        // Set up funnel CPT customizations (CPT registered via ACF Pro)
        self::setupFunnelCptHooks();

        // Initialize Funnel Export/Import admin page
        Admin\FunnelExportImport::init();

        // Initialize Product Lookup API for admin
        Admin\ProductLookupApi::init();

        // Initialize Funnel Offer ACF fields
        Admin\FunnelOfferFields::init();

        // Initialize Funnel Styling ACF fields & admin UI
        Admin\FunnelStylingFields::init();

        // Setup ACF Local JSON sync for version control
        self::setupAcfLocalJson();

        // Register ACF options pages
        add_action('acf/init', [self::class, 'registerAcfOptionsPages']);

        // Import default HP Menu data if not configured
        add_action('acf/init', [self::class, 'maybeImportHpMenuDefaults'], 20);

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

        // Register funnel import/export REST API endpoints.
        $funnelApi = new Rest\FunnelApi();
        $funnelApi->register();

        // Register AI Funnel REST API endpoints (Phase 1: Funnel Generation Capability).
        $aiFunnelApi = new Rest\AiFunnelApi();
        $aiFunnelApi->register();

        // Initialize AI admin components.
        Admin\AiSettingsPage::init();
        Admin\FunnelListEnhancements::init();
        Admin\FunnelMetaBoxes::init();
        Admin\AiActivityLog::init();
        Admin\EconomicsDashboard::init();

        // Initialize SEO & Tracking components (Smart Bridge).
        Admin\SeoTrackingSettings::init();
        Admin\FunnelTestingMetabox::init();
        Services\FunnelSeoService::init();

        // Register shortcodes based on current settings.
        $shortcodeRegistry = new ShortcodeRegistry($assetLoader);
        $shortcodeRegistry->register();

        // Register the admin settings page for managing shortcodes.
        $settingsPage = new SettingsPage();
        $settingsPage->init();
    }

    /**
     * Add funnel-specific body classes for CSS targeting.
     * 
     * @param array $classes Existing body classes
     * @return array Modified body classes
     */
    public static function addFunnelBodyClasses(array $classes): array
    {
        // Check if we're on a funnel page (hp-funnel post type)
        if (is_singular(self::FUNNEL_POST_TYPE)) {
            $slug = get_post_field('post_name', get_the_ID());
            if ($slug) {
                $classes[] = 'hp-funnel-page';
                $classes[] = 'hp-funnel-' . $slug;
            }
        }
        
        // Also check for funnel sub-routes (checkout, thankyou)
        $funnelRoute = get_query_var('hp_funnel_route');
        $funnelSlug = get_query_var('hp_funnel_slug');
        if ($funnelRoute && $funnelSlug) {
            $classes[] = 'hp-funnel-page';
            $classes[] = 'hp-funnel-' . sanitize_title($funnelSlug);
            $classes[] = 'hp-funnel-route-' . sanitize_title($funnelRoute);
        }
        
        return $classes;
    }

    /**
     * Ensure Elementor's expected global exists to prevent:
     * "Uncaught ReferenceError: elementorFrontendConfig is not defined"
     */
    public static function outputElementorFrontendConfigShim(): void
    {
        if (is_admin()) {
            return;
        }

        // Avoid emitting more than once.
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        // If Elementor isn't active, don't emit anything.
        if (!defined('ELEMENTOR_VERSION') && !class_exists('\\Elementor\\Plugin')) {
            return;
        }

        // Determine Elementor assets base URL so its dynamic chunk loader doesn't build `undefinedjs/...` URLs.
        $elementorAssetsUrl = '';
        if (defined('ELEMENTOR_ASSETS_URL')) {
            $elementorAssetsUrl = (string) ELEMENTOR_ASSETS_URL;
        } elseif (defined('ELEMENTOR_URL')) {
            $elementorAssetsUrl = rtrim((string) ELEMENTOR_URL, '/') . '/assets/';
        } else {
            // Fallback: common plugin path.
            $elementorAssetsUrl = plugins_url('elementor/assets/');
        }
        $elementorAssetsUrl = esc_url_raw(trailingslashit($elementorAssetsUrl));
        $ajaxUrl = esc_url_raw(admin_url('admin-ajax.php'));

        // Keep this tiny, but include the nested objects Elementor reads early during boot.
        // We only set defaults if missing, so it won't interfere when Elementor later localizes real data.
        echo "<script>(function(){var c=window.elementorFrontendConfig=window.elementorFrontendConfig||{};c.environmentMode=c.environmentMode||{};c.isDebug=!!c.isDebug;c.isElementorDebug=!!c.isElementorDebug;c.urls=c.urls||{};c.urls.assets=c.urls.assets||" . wp_json_encode($elementorAssetsUrl) . ";c.urls.ajaxurl=c.urls.ajaxurl||" . wp_json_encode($ajaxUrl) . ";c.i18n=c.i18n||{};c.responsive=c.responsive||{};c.responsive.breakpoints=c.responsive.breakpoints||{};c.responsive.activeBreakpoints=c.responsive.activeBreakpoints||{};c.breakpoints=c.breakpoints||c.responsive.breakpoints||{};c.kit=c.kit||{};c.kit.active_breakpoints=c.kit.active_breakpoints||c.responsive.activeBreakpoints||{};c.experimentalFeatures=c.experimentalFeatures||{};c.features=c.features||{};if(typeof c.experimentalFeatures['nested-elements']==='undefined')c.experimentalFeatures['nested-elements']=false;if(typeof c.features['nested-elements']==='undefined')c.features['nested-elements']=false;var elementorFrontendConfig=c;})();</script>";
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

        // Auto-populate section_backgrounds on funnel save (v2.33.2)
        add_action('acf/save_post', function($post_id) {
            if (get_post_type($post_id) === self::FUNNEL_POST_TYPE) {
                Services\FunnelConfigLoader::autoPopulateSectionBackgrounds($post_id);
            }
        }, 20);

        // Add rewrite rules for funnel sub-routes (checkout, thankyou, etc.)
        add_action('init', [self::class, 'addFunnelRewriteRules'], 10);
        add_filter('query_vars', [self::class, 'addFunnelQueryVars']);
        add_action('template_redirect', [self::class, 'handleFunnelSubRoutes']);
    }
    
    /**
     * Add rewrite rules for funnel sub-routes.
     * Handles: /express-shop/{slug}/checkout/, /express-shop/{slug}/thankyou/
     */
    public static function addFunnelRewriteRules(): void
    {
        // Pattern: /express-shop/{funnel_slug}/checkout/
        add_rewrite_rule(
            '^express-shop/([^/]+)/checkout/?$',
            'index.php?hp_funnel_route=checkout&hp_funnel_slug=$matches[1]',
            'top'
        );
        
        // Pattern: /express-shop/{funnel_slug}/thank-you/ (with hyphen - matches SPA URL)
        add_rewrite_rule(
            '^express-shop/([^/]+)/thank-you/?$',
            'index.php?hp_funnel_route=thankyou&hp_funnel_slug=$matches[1]',
            'top'
        );
        
        // Legacy pattern without hyphen (for backwards compatibility)
        add_rewrite_rule(
            '^express-shop/([^/]+)/thankyou/?$',
            'index.php?hp_funnel_route=thankyou&hp_funnel_slug=$matches[1]',
            'top'
        );
    }
    
    /**
     * Register custom query vars for funnel routes.
     */
    public static function addFunnelQueryVars(array $vars): array
    {
        $vars[] = 'hp_funnel_route';
        $vars[] = 'hp_funnel_slug';
        return $vars;
    }
    
    /**
     * Handle funnel sub-route requests by loading the appropriate template.
     */
    public static function handleFunnelSubRoutes(): void
    {
        $route = get_query_var('hp_funnel_route');
        $slug = get_query_var('hp_funnel_slug');
        
        if (empty($route) || empty($slug)) {
            return;
        }
        
        // Find the funnel by slug
        $funnel = Services\FunnelConfigLoader::getBySlug($slug);
        if (!$funnel || !$funnel['active']) {
            // Funnel not found - let WordPress handle 404
            return;
        }
        
        // Store funnel data for template use
        set_query_var('hp_current_funnel', $funnel);
        
        // Find the checkout page that has our shortcode
        // Or render directly using a minimal template
        $template = self::getFunnelRouteTemplate($route, $funnel);
        
        if ($template) {
            include $template;
            exit;
        }
    }
    
    /**
     * Get the template file for a funnel route.
     */
    private static function getFunnelRouteTemplate(string $route, array $funnel): ?string
    {
        // First, check if there's a custom template in the theme
        $themeTemplate = locate_template([
            "hp-funnel-{$route}.php",
            "funnel/{$route}.php",
        ]);
        
        if ($themeTemplate) {
            return $themeTemplate;
        }
        
        // Use our built-in template
        $pluginTemplate = HP_RW_PATH . "templates/funnel-{$route}.php";
        if (file_exists($pluginTemplate)) {
            return $pluginTemplate;
        }
        
        // Fallback: render inline
        self::renderFunnelRouteInline($route, $funnel);
        return null;
    }
    
    /**
     * Render a funnel route inline (fallback when no template exists).
     */
    private static function renderFunnelRouteInline(string $route, array $funnel): void
    {
        // Get the header
        get_header();
        
        echo '<div id="primary" class="content-area"><main id="main" class="site-main">';
        
        // Render the appropriate shortcode
        switch ($route) {
            case 'checkout':
                echo do_shortcode('[hp_funnel_checkout_app funnel="' . esc_attr($funnel['slug']) . '"]');
                break;
            case 'thankyou':
                echo do_shortcode('[hp_funnel_thankyou funnel="' . esc_attr($funnel['slug']) . '"]');
                break;
            default:
                echo '<p>Unknown funnel route.</p>';
        }
        
        echo '</main></div>';
        
        get_footer();
        exit;
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
                // Use native WordPress post_name as the slug
                $slug = get_post_field('post_name', $post_id);
                echo '<code>' . esc_html($slug) . '</code>';
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
     * Handle funnel post save - clear caches.
     * 
     * Uses native WordPress post_name (slug) as the canonical identifier.
     * This provides better SEO compatibility with Yoast, FiboSearch, and other plugins.
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

        // Get old slug for cache invalidation
        $oldSlug = get_post_meta($post_id, '_hp_funnel_previous_slug', true);
        
        // Store current post_name for next save comparison
        if ($post->post_name) {
            update_post_meta($post_id, '_hp_funnel_previous_slug', $post->post_name);
        }

        // Clear the funnel config cache (pass old slug for cascade invalidation)
        if (class_exists('HP_RW\\Services\\FunnelConfigLoader')) {
            Services\FunnelConfigLoader::clearCache($post_id, $oldSlug ?: '');
        }
    }

    /**
     * Check for plugin upgrade and run migrations.
     * Called on 'init' hook with priority 99 to ensure WP is fully loaded.
     */
    public static function checkForUpgrade(): void
    {
        $storedVersion = get_option('hp_rw_version', '0');
        $currentVersion = defined('HP_RW_VERSION') ? HP_RW_VERSION : '0';
        
        if (version_compare($storedVersion, $currentVersion, '<')) {
            // Run upgrade migrations
            
            // v2.7.1+: Sync all funnel slugs to ensure URL consistency
            if (version_compare($storedVersion, '2.7.1', '<')) {
                self::syncAllFunnelSlugs();
            }
            
            // v2.7.3+: Flush rewrite rules for new funnel sub-routes
            if (version_compare($storedVersion, '2.7.3', '<')) {
                flush_rewrite_rules(false);
            }
            
            // v2.34.0+: Flush rewrite rules for /thank-you/ hyphenated URL support
            if (version_compare($storedVersion, '2.34.0', '<')) {
                flush_rewrite_rules(false);
            }
            
            // Update stored version
            update_option('hp_rw_version', $currentVersion);
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
        
        // Register rewrite rules and flush to make them active immediately
        self::addFunnelRewriteRules();
        flush_rewrite_rules(false);
        
        // Sync all funnel slugs to ensure consistency
        self::syncAllFunnelSlugs();
    }
    
    /**
     * Clear all funnel caches on plugin upgrade.
     * Previously synced funnel_slug to post_name, now just clears caches
     * since we use native WordPress post_name.
     */
    public static function syncAllFunnelSlugs(): void
    {
        $funnels = get_posts([
            'post_type'      => self::FUNNEL_POST_TYPE,
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        
        if (empty($funnels)) {
            return;
        }
        
        // Clear cache for all funnels
        foreach ($funnels as $post_id) {
            if (class_exists('HP_RW\\Services\\FunnelConfigLoader')) {
                Services\FunnelConfigLoader::clearCache($post_id);
            }
        }
        
        error_log("[HP-RW] syncAllFunnelSlugs: Cleared cache for " . count($funnels) . " funnel(s)");
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

        // Enqueue the validation enhancement script
        wp_enqueue_script(
            'hp-rw-funnel-validation',
            HP_RW_URL . 'assets/admin/funnel-validation.js',
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

    /**
     * Detect if we are currently in the Elementor Editor (main window).
     * This is used to prevent heavy logic and script collisions in the editor UI.
     *
     * @return bool True if in Elementor Editor but NOT the preview frame.
     */
    public static function is_elementor_editor(): bool
    {
        // #region agent log
        $log_path = HP_RW_PATH . '.cursor/debug.log';
        $log_entry = json_encode([
            'location' => 'Plugin.php:884',
            'message' => 'is_elementor_editor start',
            'data' => [
                'is_admin' => is_admin(),
                'GET' => $_GET
            ],
            'timestamp' => (int)(microtime(true) * 1000),
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'C'
        ]);
        @file_put_contents($log_path, $log_entry . PHP_EOL, FILE_APPEND);
        // #endregion

        if (!class_exists('\\Elementor\\Plugin')) {
            return false;
        }

        $elementor = \Elementor\Plugin::instance();
        
        // is_edit_mode() is true both in the editor UI and the preview iframe.
        // We only want to bail if we are NOT in the preview frame.
        $result = false;
        if (isset($elementor->editor) && $elementor->editor->is_edit_mode()) {
            if (!isset($_GET['elementor-preview'])) {
                $result = true;
            }
        }

        // #region agent log
        $log_entry = json_encode([
            'location' => 'Plugin.php:913',
            'message' => 'is_elementor_editor result',
            'data' => ['result' => $result],
            'timestamp' => (int)(microtime(true) * 1000),
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'C'
        ]);
        @file_put_contents($log_path, $log_entry . PHP_EOL, FILE_APPEND);
        // #endregion

        return $result;
    }

    /**
     * Get a placeholder for Elementor Editor display.
     *
     * @param string $label The shortcode label
     * @return string HTML placeholder
     */
    public static function get_editor_placeholder(string $label): string
    {
        return sprintf(
            '<div class="hp-shortcode-placeholder" style="padding: 15px; background: #f9f9f9; border: 1px dashed #2271b1; border-radius: 4px; color: #2271b1; font-family: sans-serif; font-size: 13px; text-align: center; margin: 10px 0;">
                <span class="dashicons dashicons-layout" style="vertical-align: middle; margin-right: 5px;"></span>
                <strong>HP Widget:</strong> %s
                <div style="font-size: 11px; color: #666; margin-top: 4px;">(Preview available in live frame)</div>
            </div>',
            esc_html($label)
        );
    }

    /**
     * Get responsive settings (breakpoints, max-width, scroll settings).
     *
     * @return array Responsive settings merged with defaults
     */
    public static function get_responsive_settings(): array
    {
        $stored = get_option(self::OPTION_RESPONSIVE_SETTINGS, []);
        
        if (!is_array($stored)) {
            $stored = [];
        }
        
        // Merge with defaults to ensure all keys exist
        return array_merge(self::DEFAULT_RESPONSIVE_SETTINGS, $stored);
    }

    /**
     * Persist responsive settings.
     *
     * @param array $settings Responsive settings to save
     */
    public static function set_responsive_settings(array $settings): void
    {
        // Sanitize and validate values
        $sanitized = [
            'breakpoint_tablet'    => max(320, min(1200, absint($settings['breakpoint_tablet'] ?? 640))),
            'breakpoint_laptop'    => max(640, min(1600, absint($settings['breakpoint_laptop'] ?? 1024))),
            'breakpoint_desktop'   => max(1024, min(2560, absint($settings['breakpoint_desktop'] ?? 1440))),
            'content_max_width'    => max(1000, min(1600, absint($settings['content_max_width'] ?? 1400))),
            'enable_smooth_scroll' => !empty($settings['enable_smooth_scroll']),
            'scroll_duration'      => max(200, min(2000, absint($settings['scroll_duration'] ?? 800))),
            'scroll_easing'        => in_array($settings['scroll_easing'] ?? '', ['ease-out-cubic', 'ease-out-quad', 'linear'], true) 
                                       ? $settings['scroll_easing'] 
                                       : 'ease-out-cubic',
            'enable_scroll_snap'   => !empty($settings['enable_scroll_snap']),
        ];
        
        update_option(self::OPTION_RESPONSIVE_SETTINGS, $sanitized);
    }

    /**
     * Get default responsive settings.
     *
     * @return array Default responsive settings
     */
    public static function get_default_responsive_settings(): array
    {
        return self::DEFAULT_RESPONSIVE_SETTINGS;
    }

    /**
     * Register ACF options pages for plugin settings.
     */
    public static function registerAcfOptionsPages(): void
    {
        if (!function_exists('acf_add_options_page')) {
            return;
        }

        // HP Menu Options page
        acf_add_options_page([
            'page_title'    => __('HP Menu Settings', 'hp-react-widgets'),
            'menu_title'    => __('HP Menu', 'hp-react-widgets'),
            'menu_slug'     => 'hp-menu-options',
            'capability'    => 'manage_options',
            'parent_slug'   => 'options-general.php',
            'position'      => 80,
            'icon_url'      => 'dashicons-menu',
            'redirect'      => false,
            'autoload'      => true,
            'update_button' => __('Save Menu Settings', 'hp-react-widgets'),
        ]);
    }

    /**
     * Import default HP Menu data from JSON if ACF options are empty.
     * This auto-populates the menu on first install.
     */
    public static function maybeImportHpMenuDefaults(): void
    {
        if (!function_exists('get_field') || !function_exists('update_field')) {
            return;
        }

        // Check if menu sections already have data
        $existingSections = get_field('hp_menu_sections', 'option');
        if (!empty($existingSections)) {
            return; // Already configured, don't overwrite
        }

        // Load default data from JSON file
        $jsonFile = HP_RW_PATH . 'data/hp-menu-default-data.json';
        if (!file_exists($jsonFile)) {
            return;
        }

        $jsonContent = file_get_contents($jsonFile);
        $defaultData = json_decode($jsonContent, true);

        if (empty($defaultData) || !is_array($defaultData)) {
            return;
        }

        // Import each field
        if (isset($defaultData['hp_menu_title'])) {
            update_field('hp_menu_title', $defaultData['hp_menu_title'], 'option');
        }

        if (isset($defaultData['hp_menu_footer_text'])) {
            update_field('hp_menu_footer_text', $defaultData['hp_menu_footer_text'], 'option');
        }

        if (isset($defaultData['hp_menu_sections'])) {
            update_field('hp_menu_sections', $defaultData['hp_menu_sections'], 'option');
        }

        error_log('[HP-RW] HP Menu default data imported from JSON.');
    }

    /**
     * Setup ACF Local JSON for version-controlled field group sync.
     * 
     * This allows ACF field groups to be saved as JSON files in the plugin,
     * enabling deployment via Git across environments.
     */
    private static function setupAcfLocalJson(): void
    {
        // Tell ACF where to save JSON files (when editing in WP Admin)
        add_filter('acf/settings/save_json', [self::class, 'acfJsonSavePoint']);
        
        // Tell ACF where to load JSON files from (for deployment)
        add_filter('acf/settings/load_json', [self::class, 'acfJsonLoadPoints']);

        // AUTO-SYNC: Import JSON files automatically if they are newer than DB
        add_action('acf/init', [self::class, 'autoSyncAcfJson']);
    }

    /**
     * Automatically sync ACF field groups from JSON files if they are newer than the database versions.
     * This ensures the JSON files remain the "Source of Truth".
     */
    public static function autoSyncAcfJson(): void
    {
        // Only run if ACF is active and we have the required functions
        if (!function_exists('acf_get_local_json_files')) {
            return;
        }

        // Avoid sync during AJAX/Heartbeat
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // Optimization: Skip sync in Elementor editor mode to prevent slow loads
        if (class_exists('\\Elementor\\Plugin') && \Elementor\Plugin::instance()->editor->is_edit_mode()) {
            return;
        }

        // Check if we need a forced sync due to version change
        $current_version = defined('HP_RW_VERSION') ? HP_RW_VERSION : '';
        $stored_version = get_option('hp_rw_version', '');
        $force_sync = ($current_version !== $stored_version);
        
        // On frontend, only run if version changed (to avoid performance hit on every page)
        // On admin, always check for sync opportunities
        if (!is_admin() && !$force_sync) {
            return;
        }

        // List of internal post types supported by ACF for JSON sync
        $acf_internal_types = ['acf-field-group', 'acf-post-type', 'acf-taxonomy'];

        foreach ($acf_internal_types as $internal_type) {
            $files = \acf_get_local_json_files($internal_type);
            if (empty($files)) {
                continue;
            }

            foreach ($files as $key => $file_path) {
                // Get the ID from the database for this key
                $id = 0;
                if ($internal_type === 'acf-field-group') {
                    if (function_exists('acf_get_field_group_id')) {
                        $id = \acf_get_field_group_id($key);
                    }
                    
                    // Fallback: search directly if ACF helper fails
                    if (!$id) {
                        $existing = get_posts([
                            'post_type'      => 'acf-field-group',
                            'post_status'    => 'any',
                            'name'           => $key,
                            'posts_per_page' => 1,
                            'fields'         => 'ids',
                        ]);
                        if (!empty($existing)) {
                            $id = $existing[0];
                        }
                    }
                } else {
                    // For post types and taxonomies, we need to find the ID by post_name
                    $existing_posts = get_posts([
                        'post_type'      => $internal_type,
                        'post_status'    => 'any',
                        'name'           => $key,
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                    ]);
                    if (!empty($existing_posts)) {
                        $id = $existing_posts[0];
                    }
                }

                // Get the current data (might be from JSON or DB)
                $data_from_acf = null;
                if ($internal_type === 'acf-field-group') {
                    $data_from_acf = \acf_get_field_group($key);
                } else if ($internal_type === 'acf-post-type') {
                    $data_from_acf = \acf_get_post_type($key);
                } else if ($internal_type === 'acf-taxonomy') {
                    $data_from_acf = \acf_get_taxonomy($key);
                }

                if (!$data_from_acf) {
                    continue;
                }

                $local = isset($data_from_acf['local']) ? $data_from_acf['local'] : '';
                $modified = isset($data_from_acf['modified']) ? $data_from_acf['modified'] : 0;

                // Only sync if it's a JSON-based object that isn't private
                if ($local !== 'json' || !empty($data_from_acf['private'])) {
                    continue;
                }

                $needs_sync = false;
                if (!$id) {
                    // Not in database yet
                    $needs_sync = true;
                } elseif ($force_sync) {
                    // Forced sync due to version change
                    $needs_sync = true;
                } elseif ($modified && $modified > get_post_modified_time('U', true, $id)) {
                    // JSON is newer than database
                    $needs_sync = true;
                }

                if ($needs_sync) {
                    $json_content = file_get_contents($file_path);
                    $data = json_decode($json_content, true);
                    
                    if ($data) {
                        // Set the ID only if we have an existing post to update
                        // If $id is 0, ACF will create a new post
                        if ($id) {
                            $data['ID'] = $id;
                        } else {
                            unset($data['ID']); // Ensure no ID is set for new imports
                        }
                        
                        // Disable "Local JSON" controller to prevent the .json file from being modified during import
                        \acf_update_setting('json', false);
                        
                        if (function_exists('acf_import_internal_post_type')) {
                            \acf_import_internal_post_type($data, $internal_type);
                        } else if ($internal_type === 'acf-field-group' && function_exists('acf_import_field_group')) {
                            \acf_import_field_group($data);
                        }
                        
                        // Re-enable JSON
                        \acf_update_setting('json', true);
                        
                        // CREATE NEW FIELDS: Add fields that exist in JSON but not in DB
                        if ($internal_type === 'acf-field-group' && $id && !empty($data['fields'])) {
                            self::createMissingAcfFields($id, $data['fields']);
                        }
                        
                        // DELETE ORPHANED FIELDS: Remove fields that exist in DB but not in JSON
                        if ($internal_type === 'acf-field-group' && $id && !empty($data['fields'])) {
                            self::deleteOrphanedAcfFields($id, $data['fields']);
                        }
                        
                        error_log("[HP-RW] ACF Auto-Sync: Updated {$internal_type} '{$data['title']}' ($key) from JSON.");
                    }
                }
            }
        }

        if ($force_sync && $current_version) {
            update_option('hp_rw_version', $current_version);
        }
    }

    /**
     * Set the save point for ACF JSON files.
     *
     * @param string $path Default ACF save path.
     * @return string Plugin's acf-json folder path.
     */
    public static function acfJsonSavePoint(string $path): string
    {
        return HP_RW_PATH . 'acf-json';
    }

    /**
     * Add plugin's acf-json folder to ACF load paths.
     *
     * @param array $paths Existing ACF load paths.
     * @return array Modified paths including plugin's folder.
     */
    public static function acfJsonLoadPoints(array $paths): array
    {
        // Remove the default path (optional - keeps only plugin-managed fields)
        // unset($paths[0]);
        
        // Add our plugin's acf-json folder
        $paths[] = HP_RW_PATH . 'acf-json';
        
        return $paths;
    }

    /**
     * Create ACF fields that exist in JSON but not in the database.
     * This ensures new fields are added during sync to any environment.
     *
     * @param int   $field_group_id The field group post ID.
     * @param array $json_fields    The fields array from the JSON file.
     */
    private static function createMissingAcfFields(int $field_group_id, array $json_fields): void
    {
        global $wpdb;
        
        // Get all existing field keys from the database
        $db_field_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_parent = %d",
            $field_group_id
        ));
        
        // Also get nested field keys
        $all_db_keys = $db_field_keys;
        foreach ($db_field_keys as $key) {
            $field_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_name = %s",
                $key
            ));
            if ($field_id) {
                $nested_keys = self::getNestedFieldKeys((int)$field_id);
                $all_db_keys = array_merge($all_db_keys, $nested_keys);
            }
        }
        
        // Process each field in the JSON
        self::createFieldsRecursively($json_fields, $field_group_id, $all_db_keys);
    }

    /**
     * Recursively create fields from JSON that don't exist in the database.
     *
     * @param array $fields         The fields array from JSON.
     * @param int   $parent_id      The parent post ID (field group or parent field).
     * @param array $existing_keys  Array of field keys that already exist in DB.
     */
    private static function createFieldsRecursively(array $fields, int $parent_id, array $existing_keys): void
    {
        foreach ($fields as $field) {
            if (empty($field['key'])) {
                continue;
            }
            
            // Check if this field exists in the database
            if (!in_array($field['key'], $existing_keys, true)) {
                // Field doesn't exist, create it
                $field_data = $field;
                unset($field_data['ID']); // Remove any existing ID
                unset($field_data['key']); // Key goes in post_name
                unset($field_data['name']); // Name goes in post_excerpt
                unset($field_data['label']); // Label goes in post_title
                unset($field_data['sub_fields']); // Handle separately
                unset($field_data['layouts']); // Handle separately
                
                $post_id = wp_insert_post([
                    'post_type'    => 'acf-field',
                    'post_title'   => $field['label'] ?? '',
                    'post_excerpt' => $field['name'] ?? '',
                    'post_name'    => $field['key'],
                    'post_parent'  => $parent_id,
                    'post_status'  => 'publish',
                    'menu_order'   => isset($field['menu_order']) ? (int)$field['menu_order'] : 0,
                    'post_content' => serialize($field_data),
                ]);
                
                if ($post_id && !is_wp_error($post_id)) {
                    error_log("[HP-RW] ACF Auto-Sync: Created missing field '{$field['key']}' (ID: {$post_id})");
                    
                    // Handle sub_fields for repeaters, groups, etc.
                    if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                        self::createFieldsRecursively($field['sub_fields'], $post_id, $existing_keys);
                    }
                    
                    // Handle layouts for flexible content
                    if (!empty($field['layouts']) && is_array($field['layouts'])) {
                        foreach ($field['layouts'] as $layout) {
                            if (!empty($layout['sub_fields'])) {
                                self::createFieldsRecursively($layout['sub_fields'], $post_id, $existing_keys);
                            }
                        }
                    }
                }
            } else {
                // Field exists, but check if it has sub_fields or layouts that need creating
                global $wpdb;
                $field_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_name = %s",
                    $field['key']
                ));
                
                if ($field_id) {
                    if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                        self::createFieldsRecursively($field['sub_fields'], (int)$field_id, $existing_keys);
                    }
                    if (!empty($field['layouts']) && is_array($field['layouts'])) {
                        foreach ($field['layouts'] as $layout) {
                            if (!empty($layout['sub_fields'])) {
                                self::createFieldsRecursively($layout['sub_fields'], (int)$field_id, $existing_keys);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Get all nested field keys under a parent field.
     *
     * @param int $parent_id The parent field post ID.
     * @return array List of field keys.
     */
    private static function getNestedFieldKeys(int $parent_id): array
    {
        global $wpdb;
        
        $keys = $wpdb->get_col($wpdb->prepare(
            "SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_parent = %d",
            $parent_id
        ));
        
        foreach ($keys as $key) {
            $field_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_name = %s",
                $key
            ));
            if ($field_id) {
                $nested = self::getNestedFieldKeys((int)$field_id);
                $keys = array_merge($keys, $nested);
            }
        }
        
        return $keys;
    }

    /**
     * Delete ACF fields that exist in the database but not in the JSON definition.
     * This ensures removed fields are cleaned up during sync.
     *
     * @param int   $field_group_id The field group post ID.
     * @param array $json_fields    The fields array from the JSON file.
     */
    private static function deleteOrphanedAcfFields(int $field_group_id, array $json_fields): void
    {
        // Recursively collect all field keys from JSON
        $json_field_keys = self::collectFieldKeys($json_fields);
        
        // Get all field posts in the database for this field group
        $db_fields = get_posts([
            'post_type'      => 'acf-field',
            'post_parent'    => $field_group_id,
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ]);
        
        // Also get nested fields (fields inside tabs, groups, repeaters, etc.)
        $all_db_field_ids = [];
        foreach ($db_fields as $field) {
            $all_db_field_ids[] = $field->ID;
            // Get children recursively
            $children = self::getNestedFieldIds($field->ID);
            $all_db_field_ids = array_merge($all_db_field_ids, $children);
        }
        
        // Now check each DB field against JSON keys
        foreach ($all_db_field_ids as $field_id) {
            $field_key = get_post_field('post_name', $field_id);
            if ($field_key && !in_array($field_key, $json_field_keys, true)) {
                wp_delete_post($field_id, true);
                error_log("[HP-RW] ACF Auto-Sync: Deleted orphaned field '{$field_key}' (ID: {$field_id})");
            }
        }
    }

    /**
     * Recursively collect all field keys from a fields array.
     *
     * @param array $fields The fields array.
     * @return array List of field keys.
     */
    private static function collectFieldKeys(array $fields): array
    {
        $keys = [];
        foreach ($fields as $field) {
            if (!empty($field['key'])) {
                $keys[] = $field['key'];
            }
            // Check for nested fields (sub_fields in repeaters/groups, layouts in flexible content)
            if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                $keys = array_merge($keys, self::collectFieldKeys($field['sub_fields']));
            }
            if (!empty($field['layouts']) && is_array($field['layouts'])) {
                foreach ($field['layouts'] as $layout) {
                    if (!empty($layout['key'])) {
                        $keys[] = $layout['key'];
                    }
                    if (!empty($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                        $keys = array_merge($keys, self::collectFieldKeys($layout['sub_fields']));
                    }
                }
            }
        }
        return $keys;
    }

    /**
     * Recursively get all nested field IDs under a parent field.
     *
     * @param int $parent_id The parent field post ID.
     * @return array List of child field post IDs.
     */
    private static function getNestedFieldIds(int $parent_id): array
    {
        $children = get_posts([
            'post_type'      => 'acf-field',
            'post_parent'    => $parent_id,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ]);
        
        $all_ids = $children;
        foreach ($children as $child_id) {
            $nested = self::getNestedFieldIds($child_id);
            $all_ids = array_merge($all_ids, $nested);
        }
        
        return $all_ids;
    }
}
