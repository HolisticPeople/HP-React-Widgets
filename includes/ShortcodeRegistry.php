<?php
namespace HP_RW;

class ShortcodeRegistry
{
    private $assetLoader;

    public function __construct(AssetLoader $assetLoader)
    {
        $this->assetLoader = $assetLoader;
    }

    /**
     * Register shortcodes based on which ones are enabled in settings.
     */
    public function register(): void
    {
        $enabled       = Plugin::get_enabled_shortcodes();
        $allShortcodes = Plugin::get_shortcodes();

        foreach ($allShortcodes as $slug => $config) {
            // Register every known shortcode so Elementor/WP never shows the raw [shortcode] text.
            // Rendering logic checks whether it is enabled, and if not, returns an empty string.
            add_shortcode(
                $slug,
                function ($atts = []) use ($slug, $config, $enabled) {
                    if (!in_array($slug, $enabled, true)) {
                        // Shortcode is configured but disabled ? render nothing and do not enqueue assets.
                        return '';
                    }

                    // Optimization: If in Elementor Editor UI, render a lightweight placeholder.
                    // This prevents React/Underscore script collisions and speeds up editor load.
                    if (Plugin::is_elementor_editor()) {
                        return Plugin::get_editor_placeholder($config['label'] ?? $slug);
                    }

                    return $this->renderGeneric($config, (array) $atts);
                }
            );
        }
    }

    /**
     * Generic renderer used for custom shortcodes defined via the wizard.
     *
     * @param array<string,mixed> $config
     * @param array<string,mixed> $atts
     */
    private function renderGeneric(array $config, array $atts): string
    {
        // Enqueue the React bundle (only loads on pages with this shortcode).
        AssetLoader::enqueue_bundle();

        $rootId    = isset($config['root_id']) ? (string) $config['root_id'] : 'hp-generic-widget-root';
        $component = isset($config['component']) ? (string) $config['component'] : '';

        // If a hydrator class is configured and available, let it take over completely.
        if (!empty($config['hydrator_class'])) {
            $class = 'HP_RW\\Shortcodes\\' . ltrim((string) $config['hydrator_class'], '\\');
            if (class_exists($class) && method_exists($class, 'render')) {
                $instance = new $class();
                /** @var mixed $output */
                $output = $instance->render($atts);
                if (is_string($output)) {
                    return $output;
                }
            }
        }

        $props = isset($config['default_props']) && is_array($config['default_props'])
            ? $config['default_props']
            : [];

        return sprintf(
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}


