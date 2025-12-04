<?php
namespace HP_RW;

class Plugin
{
    /**
     * Option name used to store which shortcodes are enabled.
     */
    private const OPTION_ENABLED_SHORTCODES = 'hp_rw_enabled_shortcodes';

    /**
     * Registry of all shortcodes this plugin can provide.
     *
     * New shortcodes should be added here so they automatically appear
     * in the Settings screen and can be toggled on/off.
     *
     * @var array<string,array<string,string>>
     */
    private const SHORTCODES = [
        'hp_multi_address' => [
            'label'       => 'My Account Multi-Address',
            'description' => 'Replaces the WooCommerce My Account addresses section with a React-based multi-address UI.',
            'example'     => '[hp_multi_address]',
        ],
        'hp_my_account_header' => [
            'label'       => 'My Account Header',
            'description' => 'Displays the My Account header widget with avatar, name and navigation icons.',
            'example'     => '[hp_my_account_header]',
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
        // If no option is stored yet, enable all known shortcodes by default.
        $stored = get_option(self::OPTION_ENABLED_SHORTCODES, null);
        if ($stored === null) {
            update_option(self::OPTION_ENABLED_SHORTCODES, array_keys(self::SHORTCODES));
        }
    }

    /**
     * Get metadata for all available shortcodes.
     *
     * @return array<string,array<string,string>>
     */
    public static function get_shortcodes(): array
    {
        /**
         * Filter the list of available HP React Widgets shortcodes.
         *
         * @param array $shortcodes Associative array of shortcode slug => metadata.
         */
        return apply_filters('hp_rw_shortcodes', self::SHORTCODES);
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
}


