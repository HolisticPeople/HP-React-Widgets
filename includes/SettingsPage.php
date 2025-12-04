<?php
namespace HP_RW;

class SettingsPage
{
    /**
     * Hook into WordPress admin.
     */
    public function init(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    /**
     * Register the plugin settings page under the Settings menu.
     */
    public function register_menu(): void
    {
        add_options_page(
            'HP React Widgets',
            'HP React Widgets',
            'manage_options',
            'hp-react-widgets',
            [$this, 'render_page']
        );
    }

    /**
     * Render the settings page.
     */
    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $savedNotice = '';

        // Handle form submission.
        if (isset($_POST['hp_rw_settings_submitted'])) {
            check_admin_referer('hp_rw_settings');

            $enabled = [];
            if (isset($_POST['hp_rw_enabled_shortcodes']) && is_array($_POST['hp_rw_enabled_shortcodes'])) {
                $enabled = array_map('sanitize_text_field', $_POST['hp_rw_enabled_shortcodes']);
            }

            Plugin::set_enabled_shortcodes($enabled);
            $savedNotice = '<div class="notice notice-success is-dismissible"><p>HP React Widgets settings saved.</p></div>';
        }

        $allShortcodes = Plugin::get_shortcodes();
        $enabled       = Plugin::get_enabled_shortcodes();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html('HP React Widgets'); ?></h1>

            <?php
            // Output "settings saved" notice if applicable.
            if ($savedNotice) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $savedNotice;
            }
            ?>

            <h2><?php echo esc_html('Available Shortcodes'); ?></h2>
            <p>
                <?php echo esc_html('Toggle which React-powered shortcodes are active. Disabled shortcodes will not render on the front-end even if present in Elementor or content.'); ?>
            </p>

            <form method="post">
                <?php wp_nonce_field('hp_rw_settings'); ?>
                <input type="hidden" name="hp_rw_settings_submitted" value="1" />

                <table class="widefat striped" style="max-width: 900px;">
                    <thead>
                    <tr>
                        <th style="width: 80px;"><?php echo esc_html('Enabled'); ?></th>
                        <th><?php echo esc_html('Shortcode'); ?></th>
                        <th><?php echo esc_html('Description'); ?></th>
                        <th><?php echo esc_html('Usage'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allShortcodes as $slug => $meta) : ?>
                        <?php
                        $isEnabled = in_array($slug, $enabled, true);
                        $label     = isset($meta['label']) ? $meta['label'] : $slug;
                        $desc      = isset($meta['description']) ? $meta['description'] : '';
                        $example   = isset($meta['example']) ? $meta['example'] : '[' . $slug . ']';
                        ?>
                        <tr>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="hp_rw_enabled_shortcodes[]"
                                           value="<?php echo esc_attr($slug); ?>"
                                        <?php checked($isEnabled); ?>
                                    />
                                </label>
                            </td>
                            <td>
                                <strong><?php echo esc_html($label); ?></strong><br />
                                <code><?php echo esc_html($slug); ?></code>
                            </td>
                            <td><?php echo esc_html($desc); ?></td>
                            <td><code><?php echo esc_html($example); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html('Save Changes'); ?>
                    </button>
                </p>
            </form>

            <hr />

            <h2><?php echo esc_html('How to add a new shortcode'); ?></h2>
            <p><?php echo esc_html('Use this workflow to bring a new React UI widget into the Elementor/WordPress skeleton via this plugin:'); ?></p>

            <ol style="max-width: 900px; list-style: decimal; padding-left: 20px;">
                <li><?php echo esc_html('Create a React component in src/components/YourWidgetName.tsx.'); ?></li>
                <li><?php echo esc_html('In src/main.tsx, mount the component into a unique DOM element ID (for example hp-your-widget-root) and read any initial props from data attributes.'); ?></li>
                <li><?php echo esc_html('In PHP, add a render method for the shortcode (in a registry or dedicated class) which:'); ?>
                    <ul style="list-style: disc; padding-left: 20px;">
                        <li><?php echo esc_html('Enqueues the React bundle via AssetLoader::HANDLE.'); ?></li>
                        <li><?php echo esc_html('Builds initial data from WordPress / WooCommerce for hydration.'); ?></li>
                        <li><?php echo esc_html('Returns a container such as <div id=\"hp-your-widget-root\" data-props=\"{...}\"></div>.'); ?></li>
                    </ul>
                </li>
                <li><?php echo esc_html('Register a shortcode tag like hp_your_widget and add an entry to Plugin::SHORTCODES so it shows up in this Settings screen.'); ?></li>
                <li><?php echo esc_html('Bump the plugin version in hp-react-widgets.php, commit on the dev branch, and push so the staging deploy runs.'); ?></li>
            </ol>
        </div>
        <?php
    }
}


