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
        'hp_funnel_checkout_app' => [
            'label'       => 'Funnel Checkout App (SPA)',
            'description' => 'Full checkout SPA with checkout, upsell, and thank you steps.',
            'example'     => '[hp_funnel_checkout_app funnel="illumodine"]',
            'component'   => 'FunnelCheckoutApp',
            'root_id'     => 'hp-funnel-checkout-app-root',
            'hydrator_class' => 'FunnelCheckoutAppShortcode',
        ],
        'hp_funnel_styles' => [
            'label'       => 'Funnel Global Styles',
            'description' => 'Outputs global CSS variables and background for a funnel.',
            'example'     => '[hp_funnel_styles funnel="illumodine"]',
            'component'   => null,
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
            'description' => 'Responsive comparison infographic.',
            'example'     => '[hp_funnel_infographics funnel="illumodine"]',
            'component'   => 'FunnelInfographics',
            'root_id'     => 'hp-funnel-infographics-root',
            'hydrator_class' => 'FunnelInfographicsShortcode',
        ],
        'hp_funnel_scroll_navigation' => [
            'label'       => 'Funnel Scroll Navigation',
            'description' => 'Fixed scroll navigation dots.',
            'example'     => '[hp_funnel_scroll_navigation]',
            'component'   => 'ScrollNavigation',
            'root_id'     => 'hp-funnel-scroll-navigation-root',
            'hydrator_class' => 'FunnelScrollNavigationShortcode',
        ],
        'hp_menu' => [
            'label'       => 'HP Menu',
            'description' => 'Off-canvas navigation menu.',
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
        $assetLoader = new AssetLoader();
        $assetLoader->register();

        // 1. Register Shortcodes IMMEDIATELY
        $shortcodeRegistry = new ShortcodeRegistry($assetLoader);
        $shortcodeRegistry->register();

        // 2. Register Post Type Early
        add_action('init', [FunnelPostType::class, 'register'], 5);

        add_action('init', [self::class, 'checkForUpgrade'], 99);
        add_action('wp_head', [self::class, 'outputElementorFrontendConfigShim'], 0);
        add_filter('body_class', [self::class, 'addFunnelBodyClasses']);
        self::setupFunnelCptHooks();

        Admin\FunnelExportImport::init();
        Admin\ProductLookupApi::init();
        Admin\FunnelOfferFields::init();
        Admin\FunnelStylingFields::init();
        self::setupAcfLocalJson();
        add_action('acf/init', [self::class, 'registerAcfOptionsPages']);
        add_action('acf/init', [self::class, 'maybeImportHpMenuDefaults'], 20);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminScripts']);

        (new AddressApi())->register();
        (new Rest\CheckoutApi())->register();
        (new Rest\UpsellApi())->register();
        (new Rest\ShippingApi())->register();
        (new Rest\FunnelApi())->register();
        (new Rest\AiFunnelApi())->register();

        Admin\AiSettingsPage::init();
        Admin\FunnelListEnhancements::init();
        Admin\FunnelMetaBoxes::init();
        Admin\AiActivityLog::init();
        Admin\EconomicsDashboard::init();
        Admin\SeoTrackingSettings::init();
        Admin\FunnelTestingMetabox::init();
        Services\FunnelSeoService::init();

        (new SettingsPage())->init();
    }

    public static function is_elementor_editor(): bool
    {
        if (!is_admin()) return false;
        if (!class_exists('\\Elementor\\Plugin')) return false;
        $elementor = \Elementor\Plugin::instance();
        if (isset($elementor->editor) && $elementor->editor->is_edit_mode()) {
            if (!isset($_GET['elementor-preview'])) {
                return true;
            }
        }
        return false;
    }

    public static function get_editor_placeholder(string $label): string
    {
        return sprintf(
            '<div class="hp-shortcode-placeholder" style="padding: 20px; background: #f0f7ff; border: 2px dashed #2271b1; border-radius: 8px; color: #2271b1; font-family: sans-serif; text-align: center; margin: 10px 0;">
                <div style="font-weight: bold; margin-bottom: 5px;">HP Widget: %s</div>
                <div style="font-size: 11px; color: #666;">(Live design visible in preview frame)</div>
            </div>',
            esc_html($label)
        );
    }

    public static function addFunnelBodyClasses(array $classes): array
    {
        if (is_singular(self::FUNNEL_POST_TYPE)) {
            $slug = get_post_field('post_name', get_the_ID());
            if ($slug) {
                $classes[] = 'hp-funnel-page';
                $classes[] = 'hp-funnel-' . $slug;
            }
        }
        $funnelRoute = get_query_var('hp_funnel_route');
        $funnelSlug = get_query_var('hp_funnel_slug');
        if ($funnelRoute && $funnelSlug) {
            $classes[] = 'hp-funnel-page';
            $classes[] = 'hp-funnel-' . sanitize_title($funnelSlug);
            $classes[] = 'hp-funnel-route-' . sanitize_title($funnelRoute);
        }
        return $classes;
    }

    public static function outputElementorFrontendConfigShim(): void
    {
        if (is_admin()) return;
        if (!defined('ELEMENTOR_VERSION') && !class_exists('\\Elementor\\Plugin')) return;
        $elementorAssetsUrl = defined('ELEMENTOR_ASSETS_URL') ? (string) ELEMENTOR_ASSETS_URL : plugins_url('elementor/assets/');
        $elementorAssetsUrl = esc_url_raw(trailingslashit($elementorAssetsUrl));
        $ajaxUrl = esc_url_raw(admin_url('admin-ajax.php'));
        echo "<script>(function(){var c=window.elementorFrontendConfig=window.elementorFrontendConfig||{};c.environmentMode=c.environmentMode||{};c.isDebug=!!c.isDebug;c.isElementorDebug=!!c.isElementorDebug;c.urls=c.urls||{};c.urls.assets=c.urls.assets||" . wp_json_encode($elementorAssetsUrl) . ";c.urls.ajaxurl=c.urls.ajaxurl||" . wp_json_encode($ajaxUrl) . ";c.i18n=c.i18n||{};c.responsive=c.responsive||{};c.responsive.breakpoints=c.responsive.breakpoints||{};c.responsive.activeBreakpoints=c.responsive.activeBreakpoints||{};c.breakpoints=c.breakpoints||c.responsive.breakpoints||{};c.kit=c.kit||{};c.kit.active_breakpoints=c.kit.active_breakpoints||c.responsive.activeBreakpoints||{};c.experimentalFeatures=c.experimentalFeatures||{};c.features=c.features||{};if(typeof c.experimentalFeatures['nested-elements']==='undefined')c.experimentalFeatures['nested-elements']=false;if(typeof c.features['nested-elements']==='undefined')c.features['nested-elements']=false;var elementorFrontendConfig=c;})();</script>";
    }

    private static function setupFunnelCptHooks(): void
    {
        add_filter('manage_' . self::FUNNEL_POST_TYPE . '_posts_columns', [self::class, 'addFunnelColumns']);
        add_action('manage_' . self::FUNNEL_POST_TYPE . '_posts_custom_column', [self::class, 'renderFunnelColumn'], 10, 2);
        add_action('save_post_' . self::FUNNEL_POST_TYPE, [self::class, 'onFunnelSave'], 10, 3);
        add_action('acf/save_post', function($post_id) {
            if (get_post_type($post_id) === self::FUNNEL_POST_TYPE) {
                Services\FunnelConfigLoader::autoPopulateSectionBackgrounds($post_id);
            }
        }, 20);
        add_action('init', [self::class, 'addFunnelRewriteRules'], 10);
        add_filter('query_vars', [self::class, 'addFunnelQueryVars']);
        add_action('template_redirect', [self::class, 'handleFunnelSubRoutes']);
    }

    public static function addFunnelRewriteRules(): void
    {
        add_rewrite_rule('^express-shop/([^/]+)/checkout/?$', 'index.php?hp_funnel_route=checkout&hp_funnel_slug=$matches[1]', 'top');
        add_rewrite_rule('^express-shop/([^/]+)/thank-you/?$', 'index.php?hp_funnel_route=thankyou&hp_funnel_slug=$matches[1]', 'top');
        add_rewrite_rule('^express-shop/([^/]+)/thankyou/?$', 'index.php?hp_funnel_route=thankyou&hp_funnel_slug=$matches[1]', 'top');
    }

    public static function addFunnelQueryVars(array $vars): array
    {
        $vars[] = 'hp_funnel_route';
        $vars[] = 'hp_funnel_slug';
        return $vars;
    }

    public static function handleFunnelSubRoutes(): void
    {
        $route = get_query_var('hp_funnel_route');
        $slug = get_query_var('hp_funnel_slug');
        if (empty($route) || empty($slug)) return;
        $funnel = Services\FunnelConfigLoader::getBySlug($slug);
        if (!$funnel || !$funnel['active']) return;
        set_query_var('hp_current_funnel', $funnel);
        $template = self::getFunnelRouteTemplate($route, $funnel);
        if ($template) {
            include $template;
            exit;
        }
    }

    private static function getFunnelRouteTemplate(string $route, array $funnel): ?string
    {
        $themeTemplate = locate_template(["hp-funnel-{$route}.php", "funnel/{$route}.php"]);
        if ($themeTemplate) return $themeTemplate;
        $pluginTemplate = HP_RW_PATH . "templates/funnel-{$route}.php";
        if (file_exists($pluginTemplate)) return $pluginTemplate;
        self::renderFunnelRouteInline($route, $funnel);
        return null;
    }

    private static function renderFunnelRouteInline(string $route, array $funnel): void
    {
        get_header();
        echo '<div id="primary" class="content-area"><main id="main" class="site-main">';
        switch ($route) {
            case 'checkout': echo do_shortcode('[hp_funnel_checkout_app funnel="' . esc_attr($funnel['slug']) . '"]'); break;
            case 'thankyou': echo do_shortcode('[hp_funnel_thankyou funnel="' . esc_attr($funnel['slug']) . '"]'); break;
            default: echo '<p>Unknown funnel route.</p>';
        }
        echo '</main></div>';
        get_footer();
        exit;
    }

    public static function addFunnelColumns(array $columns): array
    {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['funnel_slug'] = __('Slug', 'hp-react-widgets');
                $new_columns['status'] = __('Status', 'hp-react-widgets');
            }
        }
        return $new_columns;
    }

    public static function renderFunnelColumn(string $column, int $post_id): void
    {
        switch ($column) {
            case 'funnel_slug': echo '<code>' . esc_html(get_post_field('post_name', $post_id)) . '</code>'; break;
            case 'status':
                $status = get_field('funnel_status', $post_id);
                echo ($status === 'inactive') ? '<span style="color: #d63638;">●</span> ' . __('Inactive', 'hp-react-widgets') : '<span style="color: #00a32a;">●</span> ' . __('Active', 'hp-react-widgets');
                break;
        }
    }

    public static function onFunnelSave(int $post_id, \WP_Post $post, bool $update): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        $oldSlug = get_post_meta($post_id, '_hp_funnel_previous_slug', true);
        if ($post->post_name) update_post_meta($post_id, '_hp_funnel_previous_slug', $post->post_name);
        if (class_exists('HP_RW\\Services\\FunnelConfigLoader')) Services\FunnelConfigLoader::clearCache($post_id, $oldSlug ?: '');
    }

    public static function checkForUpgrade(): void
    {
        $storedVersion = get_option('hp_rw_version', '0');
        $currentVersion = defined('HP_RW_VERSION') ? HP_RW_VERSION : '0';
        if (version_compare($storedVersion, $currentVersion, '<')) {
            if (version_compare($storedVersion, '2.7.1', '<')) self::syncAllFunnelSlugs();
            if (version_compare($storedVersion, '2.7.3', '<')) flush_rewrite_rules(false);
            if (version_compare($storedVersion, '2.34.0', '<')) flush_rewrite_rules(false);
            update_option('hp_rw_version', $currentVersion);
        }
    }

    public static function activate(): void
    {
        $stored = get_option(self::OPTION_ENABLED_SHORTCODES, null);
        if ($stored === null) update_option(self::OPTION_ENABLED_SHORTCODES, array_keys(self::get_shortcodes()));
        self::addFunnelRewriteRules();
        flush_rewrite_rules(false);
        self::syncAllFunnelSlugs();
    }
    
    public static function syncAllFunnelSlugs(): void
    {
        $funnels = get_posts(['post_type' => self::FUNNEL_POST_TYPE, 'post_status' => ['publish', 'draft', 'private'], 'posts_per_page' => -1, 'fields' => 'ids']);
        foreach ($funnels as $post_id) if (class_exists('HP_RW\\Services\\FunnelConfigLoader')) Services\FunnelConfigLoader::clearCache($post_id);
    }

    public static function enqueueAdminScripts(string $hook): void
    {
        global $post;
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
        if (!$post || $post->post_type !== self::FUNNEL_POST_TYPE) return;
        wp_enqueue_script('hp-rw-funnel-product-lookup', HP_RW_URL . 'assets/admin/funnel-product-lookup.js', ['jquery', 'acf-input'], HP_RW_VERSION, true);
        wp_enqueue_script('hp-rw-funnel-validation', HP_RW_URL . 'assets/admin/funnel-validation.js', ['jquery', 'acf-input'], HP_RW_VERSION, true);
        wp_localize_script('hp-rw-funnel-product-lookup', 'hpRwAdmin', ['restUrl' => rest_url(), 'nonce' => wp_create_nonce('wp_rest')]);
    }

    public static function get_shortcodes(): array { return (new self())->get_shortcodes_internal(); }
    private function get_shortcodes_internal(): array
    {
        $stored = get_option(self::OPTION_SHORTCODES, []);
        foreach (self::DEFAULT_SHORTCODES as $slug => $meta) $stored[$slug] = isset($stored[$slug]) ? array_merge($meta, $stored[$slug]) : $meta;
        if (get_option(self::OPTION_SHORTCODES, null) === null) update_option(self::OPTION_SHORTCODES, $stored);
        $overrides = self::get_shortcode_descriptions();
        foreach ($overrides as $slug => $desc) if (isset($stored[$slug]) && $desc) $stored[$slug]['description'] = $desc;
        return apply_filters('hp_rw_shortcodes', $stored);
    }

    public static function get_enabled_shortcodes(): array
    {
        $all = array_keys(self::get_shortcodes());
        $stored = get_option(self::OPTION_ENABLED_SHORTCODES);
        if (!is_array($stored)) return $all;
        $enabled = array_values(array_intersect($all, $stored));
        return empty($enabled) ? $all : $enabled;
    }

    public static function set_enabled_shortcodes(array $slugs): void { update_option(self::OPTION_ENABLED_SHORTCODES, array_values(array_unique(array_intersect(array_keys(self::get_shortcodes()), $slugs)))); }
    public static function set_shortcodes(array $sc): void { update_option(self::OPTION_SHORTCODES, $sc); }
    public static function get_shortcode_descriptions(): array { $s = get_option(self::OPTION_SHORTCODE_DESCRIPTIONS, []); return is_array($s) ? $s : []; }
    public static function set_shortcode_descriptions(array $d): void { update_option(self::OPTION_SHORTCODE_DESCRIPTIONS, $d); }

    public static function get_responsive_settings(): array { return array_merge(self::DEFAULT_RESPONSIVE_SETTINGS, (array)get_option(self::OPTION_RESPONSIVE_SETTINGS, [])); }
    public static function set_responsive_settings(array $s): void
    {
        $san = [
            'breakpoint_tablet' => max(320, min(1200, absint($s['breakpoint_tablet'] ?? 640))),
            'breakpoint_laptop' => max(640, min(1600, absint($s['breakpoint_laptop'] ?? 1024))),
            'breakpoint_desktop' => max(1024, min(2560, absint($s['breakpoint_desktop'] ?? 1440))),
            'content_max_width' => max(1000, min(1600, absint($s['content_max_width'] ?? 1400))),
            'enable_smooth_scroll' => !empty($s['enable_smooth_scroll']),
            'scroll_duration' => max(200, min(2000, absint($s['scroll_duration'] ?? 800))),
            'scroll_easing' => in_array($s['scroll_easing'] ?? '', ['ease-out-cubic', 'ease-out-quad', 'linear'], true) ? $s['scroll_easing'] : 'ease-out-cubic',
            'enable_scroll_snap' => !empty($s['enable_scroll_snap']),
        ];
        update_option(self::OPTION_RESPONSIVE_SETTINGS, $san);
    }

    public static function registerAcfOptionsPages(): void
    {
        if (!function_exists('acf_add_options_page')) return;
        acf_add_options_page(['page_title' => __('HP Menu Settings', 'hp-react-widgets'), 'menu_title' => __('HP Menu', 'hp-react-widgets'), 'menu_slug' => 'hp-menu-options', 'capability' => 'manage_options', 'parent_slug' => 'options-general.php', 'position' => 80, 'icon_url' => 'dashicons-menu', 'redirect' => false, 'autoload' => true]);
    }

    public static function maybeImportHpMenuDefaults(): void
    {
        if (!function_exists('get_field') || !function_exists('update_field')) return;
        if (!empty(get_field('hp_menu_sections', 'option'))) return;
        $file = HP_RW_PATH . 'data/hp-menu-default-data.json';
        if (!file_exists($file)) return;
        $data = json_decode(file_get_contents($file), true);
        if (empty($data)) return;
        if (isset($data['hp_menu_title'])) update_field('hp_menu_title', $data['hp_menu_title'], 'option');
        if (isset($data['hp_menu_footer_text'])) update_field('hp_menu_footer_text', $data['hp_menu_footer_text'], 'option');
        if (isset($data['hp_menu_sections'])) update_field('hp_menu_sections', $data['hp_menu_sections'], 'option');
    }

    private static function setupAcfLocalJson(): void
    {
        add_filter('acf/settings/save_json', fn() => HP_RW_PATH . 'acf-json');
        add_filter('acf/settings/load_json', fn($paths) => array_merge($paths, [HP_RW_PATH . 'acf-json']));
        add_action('acf/init', [self::class, 'autoSyncAcfJson']);
    }

    public static function autoSyncAcfJson(): void
    {
        if (!function_exists('acf_get_local_json_files')) return;
        if (defined('DOING_AJAX') && DOING_AJAX) return;
        if (class_exists('\\Elementor\\Plugin') && \Elementor\Plugin::instance()->editor->is_edit_mode()) return;
        $ver = defined('HP_RW_VERSION') ? HP_RW_VERSION : '';
        if (get_option('hp_rw_version', '') === $ver && !is_admin()) return;
        foreach (['acf-field-group', 'acf-post-type', 'acf-taxonomy'] as $type) {
            $files = \acf_get_local_json_files($type);
            foreach ($files as $key => $path) {
                $data = \acf_get_field_group($key);
                if (!$data || ($data['local'] ?? '') !== 'json') continue;
                $id = 0;
                if ($type === 'acf-field-group' && function_exists('acf_get_field_group_id')) {
                    $id = \acf_get_field_group_id($key);
                }
                if (!$id) { $p = get_posts(['post_type' => $type, 'name' => $key, 'posts_per_page' => 1, 'fields' => 'ids']); if ($p) $id = $p[0]; }
                if (!$id || version_compare(get_option('hp_rw_version', ''), $ver, '<') || ($data['modified'] ?? 0) > get_post_modified_time('U', true, $id)) {
                    $json = json_decode(file_get_contents($path), true);
                    if ($json) {
                        if ($id) $json['ID'] = $id; else unset($json['ID']);
                        \acf_update_setting('json', false);
                        if (function_exists('acf_import_internal_post_type')) \acf_import_internal_post_type($json, $type);
                        else if ($type === 'acf-field-group' && function_exists('acf_import_field_group')) \acf_import_field_group($json);
                        \acf_update_setting('json', true);
                    }
                }
            }
        }
        if (get_option('hp_rw_version', '') !== $ver) update_option('hp_rw_version', $ver);
    }

    private static function createMissingAcfFields(int $id, array $fields): void {} // Stub for now
    private static function deleteOrphanedAcfFields(int $id, array $fields): void {} // Stub for now
}
