<?php
namespace HP_RW;

class Plugin
{
    /**
     * Option name used to store which shortcodes are enabled.
     */
    private const OPTION_ENABLED_SHORTCODES = 'hp_rw_enabled_shortcodes';
    private const OPTION_CUSTOM_SHORTCODES  = 'hp_rw_custom_shortcodes';

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
            'component'   => 'MultiAddress',
            'root_id'     => 'hp-multi-address-root',
        ],
        'hp_my_account_header' => [
            'label'       => 'My Account Header',
            'description' => 'Displays the My Account header widget with avatar, name and navigation icons.',
            'example'     => '[hp_my_account_header]',
            'component'   => 'MyAccountHeader',
            'root_id'     => 'hp-my-account-header-root',
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
            update_option(self::OPTION_ENABLED_SHORTCODES, array_keys(self::get_builtin_shortcodes()));
        }
    }

    /**
     * Get metadata for all available shortcodes.
     *
     * @return array<string,array<string,string>>
     */
    public static function get_shortcodes(): array
    {
        $shortcodes = array_merge(
            self::get_builtin_shortcodes(),
            self::get_custom_shortcodes()
        );

        /**
         * Filter the list of available HP React Widgets shortcodes.
         *
         * @param array $shortcodes Associative array of shortcode slug => metadata.
         */
        return apply_filters('hp_rw_shortcodes', $shortcodes);
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
     * Built-in shortcodes that ship with the plugin.
     *
     * @return array<string,array<string,string>>
     */
    public static function get_builtin_shortcodes(): array
    {
        return self::SHORTCODES;
    }

    /**
     * Custom shortcodes created via the wizard.
     *
     * @return array<string,array<string,string>>
     */
    public static function get_custom_shortcodes(): array
    {
        $stored = get_option(self::OPTION_CUSTOM_SHORTCODES, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * Persist custom shortcode metadata.
     *
     * @param array<string,array<string,string>> $shortcodes
     */
    public static function set_custom_shortcodes(array $shortcodes): void
    {
        update_option(self::OPTION_CUSTOM_SHORTCODES, $shortcodes);
    }
}


