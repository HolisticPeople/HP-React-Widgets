<?php
namespace HP_RW;

/**
 * Asset loader for the HP React Widgets bundle.
 *
 * IMPORTANT: This class only REGISTERS scripts/styles (not enqueue).
 * The actual enqueueing happens inside each shortcode's render() method
 * by calling wp_enqueue_script(AssetLoader::HANDLE).
 *
 * This prevents our React 18 bundle from loading on pages that don't
 * use our widgets, avoiding conflicts with other React-based plugins
 * (e.g., CheckoutWC side cart).
 */
class AssetLoader
{
    const HANDLE = 'hp-react-widgets-bundle';
    const CSS_HANDLE = 'hp-react-widgets-css';

    /** @var bool Track if assets have been registered this request */
    private static $registered = false;

    public function register()
    {
        // Register (not enqueue) on wp_enqueue_scripts so assets are available
        add_action('wp_enqueue_scripts', [$this, 'register_assets'], 5);
    }

    /**
     * Register scripts and styles so they can be enqueued by shortcodes.
     * This does NOT load them on every page - shortcodes call wp_enqueue_script().
     */
    public function register_assets()
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        $is_dev = defined('HP_RW_DEV_MODE') && HP_RW_DEV_MODE;

        if ($is_dev) {
            // Vite HMR - dev mode
            wp_register_script('vite-client', 'http://localhost:5173/@vite/client', [], null, true);
            wp_register_script(self::HANDLE, 'http://localhost:5173/src/main.tsx', ['vite-client'], null, true);
        } else {
            // Production: Read manifest
            $manifest_path = HP_RW_PATH . 'assets/dist/.vite/manifest.json';
            if (!file_exists($manifest_path)) {
                $manifest_path = HP_RW_PATH . 'assets/dist/manifest.json';
            }

            if (file_exists($manifest_path)) {
                $manifest = json_decode(file_get_contents($manifest_path), true);
                if (isset($manifest['src/main.tsx']['file'])) {
                    $js_file = $manifest['src/main.tsx']['file'];
                    $css_files = isset($manifest['src/main.tsx']['css']) ? $manifest['src/main.tsx']['css'] : [];

                    // REGISTER (not enqueue) JS as ES module
                    wp_register_script(
                        self::HANDLE,
                        HP_RW_URL . 'assets/dist/' . $js_file,
                        [], // No dependencies - we bundle our own React
                        HP_RW_VERSION,
                        true // In footer
                    );
                    
                    // Add type="module" for ES module support (required for dynamic imports)
                    add_filter('script_loader_tag', function($tag, $handle) {
                        if ($handle === self::HANDLE && strpos($tag, 'type="module"') === false) {
                            return str_replace(' src=', ' type="module" src=', $tag);
                        }
                        return $tag;
                    }, 10, 2);

                    // REGISTER (not enqueue) CSS
                    foreach ($css_files as $index => $css_file) {
                        $handle = $index === 0 ? self::CSS_HANDLE : self::CSS_HANDLE . '-' . $index;
                        wp_register_style($handle, HP_RW_URL . 'assets/dist/' . $css_file, [], HP_RW_VERSION);
                    }
                }
            }
        }
    }

    /**
     * Enqueue the bundle and its CSS. Called by shortcode render methods.
     */
    public static function enqueue_bundle()
    {
        // Ensure registered first (in case called before wp_enqueue_scripts)
        if (!self::$registered) {
            (new self())->register_assets();
        }

        // Enqueue JS
        wp_enqueue_script(self::HANDLE);

        // Localize settings (only once)
        static $localized = false;
        if (!$localized) {
            wp_localize_script(self::HANDLE, 'hpReactSettings', [
                'root'    => esc_url_raw(rest_url()),
                'nonce'   => wp_create_nonce('wp_rest'),
                'user_id' => get_current_user_id(),
            ]);
            $localized = true;
        }

        // Enqueue CSS
        wp_enqueue_style(self::CSS_HANDLE);
    }
}
