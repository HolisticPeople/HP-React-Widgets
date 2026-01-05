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
        
        // Initialize Funnel Styling ACF fields (text colors)
        // Admin\FunnelStylingFields::init();
        
        // Initialize Testimonials display settings
        Admin\FunnelTestimonialsFields::init();
        
        // Setup ACF Local JSON sync for version control
        self::setupAcfLocalJson();

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
        Admin\FunnelSeoFields::init();
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
        
        // Pattern: /express-shop/{funnel_slug}/thankyou/
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
                $slug = get_field('funnel_slug', $post_id);
                if (!$slug) {
                    $slug = get_post_field('post_name', $post_id);
                }
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
     * Note: We no longer auto-generate funnel_slug as we now use the native
     * WordPress post_name (slug) as the canonical identifier. This provides
     * better SEO compatibility with Yoast, FiboSearch, and other plugins.
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

        // Prevent infinite loop when we update meta
        static $saving = [];
        if (isset($saving[$post_id])) {
            return;
        }
        $saving[$post_id] = true;

        // Get old slug before any changes (for cache invalidation)
        $oldSlug = get_post_meta($post_id, '_hp_funnel_previous_slug', true);
        
        // Get current funnel_slug from ACF/meta
        $currentSlug = '';
        if (function_exists('get_field')) {
            $currentSlug = get_field('funnel_slug', $post_id);
        }
        if (empty($currentSlug)) {
            $currentSlug = get_post_meta($post_id, 'funnel_slug', true);
        }

        // AUTO-GENERATE: If funnel_slug is empty, derive from post title
        if (empty($currentSlug) && !empty($post->post_title)) {
            $currentSlug = sanitize_title($post->post_title);
            
            // Ensure uniqueness by checking for conflicts
            $currentSlug = self::ensureUniqueFunnelSlug($currentSlug, $post_id);
            
            // Save the auto-generated slug
            if (function_exists('update_field')) {
                update_field('funnel_slug', $currentSlug, $post_id);
            } else {
                update_post_meta($post_id, 'funnel_slug', $currentSlug);
            }
        }

        // Store current slug for next save comparison
        if ($currentSlug) {
            update_post_meta($post_id, '_hp_funnel_previous_slug', $currentSlug);
        }

        // SYNC: Keep post_name in sync with funnel_slug (this controls the WordPress URL)
        if ($currentSlug && $post->post_name !== $currentSlug) {
            // Use wpdb directly to avoid triggering save_post again
            global $wpdb;
            $wpdb->update(
                $wpdb->posts,
                ['post_name' => $currentSlug],
                ['ID' => $post_id],
                ['%s'],
                ['%d']
            );
            
            // Clean post cache so WP sees the updated post_name
            clean_post_cache($post_id);
            
            // Flush rewrite rules to update permalinks
            flush_rewrite_rules(false);
            
            error_log("[HP-RW] Synced post_name to funnel_slug: {$currentSlug} for post {$post_id}");
        }

        // Clear the funnel config cache (pass old slug for cascade invalidation)
        if (class_exists('HP_RW\\Services\\FunnelConfigLoader')) {
            Services\FunnelConfigLoader::clearCache($post_id, $oldSlug ?: '');
        }

        unset($saving[$post_id]);
    }

    /**
     * Ensure a funnel slug is unique across all funnels.
     *
     * @param string $slug Base slug to check
     * @param int $excludePostId Post ID to exclude from check
     * @return string Unique slug (may have suffix added)
     */
    private static function ensureUniqueFunnelSlug(string $slug, int $excludePostId): string
    {
        $originalSlug = $slug;
        $suffix = 1;

        while (self::funnelSlugExists($slug, $excludePostId)) {
            $slug = $originalSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Check if a funnel_slug already exists.
     *
     * @param string $slug Slug to check
     * @param int $excludePostId Post ID to exclude
     * @return bool True if exists
     */
    private static function funnelSlugExists(string $slug, int $excludePostId): bool
    {
        $posts = get_posts([
            'post_type'      => self::FUNNEL_POST_TYPE,
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'post__not_in'   => [$excludePostId],
            'meta_query'     => [
                [
                    'key'   => 'funnel_slug',
                    'value' => $slug,
                ],
            ],
            'fields' => 'ids',
        ]);

        return !empty($posts);
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
     * Sync all funnel post_names to match their funnel_slug values.
     * This ensures URLs align with the admin-defined slugs.
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
        
        global $wpdb;
        $updated = 0;
        
        foreach ($funnels as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }
            
            // Get funnel_slug
            $funnelSlug = '';
            if (function_exists('get_field')) {
                $funnelSlug = get_field('funnel_slug', $post_id);
            }
            if (empty($funnelSlug)) {
                $funnelSlug = get_post_meta($post_id, 'funnel_slug', true);
            }
            
            // If no funnel_slug, generate from title
            if (empty($funnelSlug) && !empty($post->post_title)) {
                $funnelSlug = sanitize_title($post->post_title);
                $funnelSlug = self::ensureUniqueFunnelSlug($funnelSlug, $post_id);
                
                if (function_exists('update_field')) {
                    update_field('funnel_slug', $funnelSlug, $post_id);
                } else {
                    update_post_meta($post_id, 'funnel_slug', $funnelSlug);
                }
            }
            
            // Sync post_name if different
            if ($funnelSlug && $post->post_name !== $funnelSlug) {
                $wpdb->update(
                    $wpdb->posts,
                    ['post_name' => $funnelSlug],
                    ['ID' => $post_id],
                    ['%s'],
                    ['%d']
                );
                clean_post_cache($post_id);
                $updated++;
                error_log("[HP-RW] syncAllFunnelSlugs: Updated post {$post_id} from '{$post->post_name}' to '{$funnelSlug}'");
            }
        }
        
        if ($updated > 0) {
            flush_rewrite_rules(false);
            error_log("[HP-RW] syncAllFunnelSlugs: Synced {$updated} funnel(s)");
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
        if (!function_exists('acf_get_local_json_files') || !is_admin()) {
            return;
        }

        // Avoid sync during AJAX/Heartbeat
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        $post_type = 'acf-field-group';
        $files = acf_get_local_json_files($post_type);
        if (empty($files)) {
            return;
        }

        // Get all groups (handles both DB and JSON-only groups)
        $groups = function_exists('acf_get_internal_post_type_posts') 
            ? acf_get_internal_post_type_posts($post_type) 
            : acf_get_field_groups();

        if (empty($groups)) {
            return;
        }

        foreach ($groups as $group) {
            $key = isset($group['key']) ? $group['key'] : '';
            if (!$key || !isset($files[$key])) {
                continue;
            }

            $id = isset($group['ID']) ? $group['ID'] : 0;
            $local = isset($group['local']) ? $group['local'] : '';
            $modified = isset($group['modified']) ? $group['modified'] : 0;

            // Only sync if it's a JSON-based group that isn't private
            if ($local !== 'json' || !empty($group['private'])) {
                continue;
            }

            $needs_sync = false;
            if (!$id) {
                // Not in database yet
                $needs_sync = true;
            } elseif ($modified && $modified > get_post_modified_time('U', true, $id)) {
                // JSON is newer than database
                $needs_sync = true;
            }

            if ($needs_sync) {
                $json_content = file_get_contents($files[$key]);
                $data = json_decode($json_content, true);
                
                if ($data) {
                    $data['ID'] = $id;
                    
                    // Disable "Local JSON" controller to prevent the .json file from being modified during import
                    acf_update_setting('json', false);
                    
                    if (function_exists('acf_import_internal_post_type')) {
                        acf_import_internal_post_type($data, $post_type);
                    } else if (function_exists('acf_import_field_group')) {
                        acf_import_field_group($data);
                    }
                    
                    // Re-enable JSON
                    acf_update_setting('json', true);
                    
                    error_log("[HP-RW] ACF Auto-Sync: Updated field group '{$group['title']}' ($key) from JSON.");
                }
            }
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
}
