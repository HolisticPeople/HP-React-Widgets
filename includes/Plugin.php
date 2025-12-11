<?php
namespace HP_RW;

class Plugin
{
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


