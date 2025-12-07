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

        foreach ($enabled as $slug) {
            if (!isset($allShortcodes[$slug])) {
                continue;
            }

            // All shortcodes are handled via the generic renderer and optional hydrator class.
            $config = $allShortcodes[$slug];

            add_shortcode($slug, function ($atts = []) use ($config) {
                return $this->renderGeneric($config, (array) $atts);
            });
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
        wp_enqueue_script(AssetLoader::HANDLE);

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


