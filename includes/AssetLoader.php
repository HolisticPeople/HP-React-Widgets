<?php
namespace HP_RW;

class AssetLoader
{
    const HANDLE = 'hp-react-widgets-bundle';

    public function register()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue()
    {
        // Check if we are in Dev Mode (Vite server running)
        // For POC, we can toggle this manually or check for a file/constant.
        // Let's assume production unless a constant is defined.
        $is_dev = defined('HP_RW_DEV_MODE') && HP_RW_DEV_MODE;

        if ($is_dev) {
            // Vite HMR
            wp_enqueue_script('vite-client', 'http://localhost:5173/@vite/client', [], null, true);
            wp_enqueue_script(self::HANDLE, 'http://localhost:5173/src/main.tsx', ['vite-client'], null, true);

            // Inject settings for Dev
            $this->localize(self::HANDLE);
        } else {
            // Production: Read manifest
            $manifest_path = HP_RW_PATH . 'assets/dist/.vite/manifest.json';
            if (!file_exists($manifest_path)) {
                // Fallback for older vite versions or different config
                $manifest_path = HP_RW_PATH . 'assets/dist/manifest.json';
            }

            if (file_exists($manifest_path)) {
                $manifest = json_decode(file_get_contents($manifest_path), true);
                if (isset($manifest['src/main.tsx']['file'])) {
                    $js_file = $manifest['src/main.tsx']['file'];
                    $css_files = isset($manifest['src/main.tsx']['css']) ? $manifest['src/main.tsx']['css'] : [];

                    // Enqueue JS
                    wp_enqueue_script(self::HANDLE, HP_RW_URL . 'assets/dist/' . $js_file, [], HP_RW_VERSION, true);
                    $this->localize(self::HANDLE);

                    // Enqueue CSS
                    foreach ($css_files as $css_file) {
                        wp_enqueue_style(self::HANDLE . '-css', HP_RW_URL . 'assets/dist/' . $css_file, [], HP_RW_VERSION);
                    }
                }
            }
        }
    }

    private function localize($handle)
    {
        wp_localize_script($handle, 'hpReactSettings', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'user_id' => get_current_user_id(),
        ]);
    }
}
