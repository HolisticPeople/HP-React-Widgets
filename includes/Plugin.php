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
     * Registry of all shortcodes this plugin can provide.
     *
     * New shortcodes should be added here so they automatically appear
     * in the Settings screen and can be toggled on/off.
     *
     * @var array<string,array<string,string>>
     */
    private const DEFAULT_SHORTCODES = [
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
    ];

    /**
     * Plugin bootstrap.
     */
    public static function init(): void
    {
        $assetLoader = new AssetLoader();
        $assetLoader->register();

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
}


