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
        $errorNotice = '';

        // Handle responsive settings submission.
        if (isset($_POST['hp_rw_responsive_submitted'])) {
            check_admin_referer('hp_rw_responsive_settings');

            $responsiveSettings = [
                'breakpoint_tablet'    => isset($_POST['breakpoint_tablet']) ? absint($_POST['breakpoint_tablet']) : 640,
                'breakpoint_laptop'    => isset($_POST['breakpoint_laptop']) ? absint($_POST['breakpoint_laptop']) : 1024,
                'breakpoint_desktop'   => isset($_POST['breakpoint_desktop']) ? absint($_POST['breakpoint_desktop']) : 1440,
                'content_max_width'    => isset($_POST['content_max_width']) ? absint($_POST['content_max_width']) : 1400,
                'enable_smooth_scroll' => isset($_POST['enable_smooth_scroll']),
                'scroll_duration'      => isset($_POST['scroll_duration']) ? absint($_POST['scroll_duration']) : 800,
                'scroll_easing'        => isset($_POST['scroll_easing']) ? sanitize_text_field($_POST['scroll_easing']) : 'ease-out-cubic',
                'enable_scroll_snap'   => isset($_POST['enable_scroll_snap']),
            ];

            Plugin::set_responsive_settings($responsiveSettings);
            $savedNotice = '<div class="notice notice-success is-dismissible"><p>Responsive settings saved.</p></div>';
        }

        // Handle enable/disable submission.
        if (isset($_POST['hp_rw_settings_submitted'])) {
            check_admin_referer('hp_rw_settings');

            $enabled = [];
            if (isset($_POST['hp_rw_enabled_shortcodes']) && is_array($_POST['hp_rw_enabled_shortcodes'])) {
                $enabled = array_map('sanitize_text_field', $_POST['hp_rw_enabled_shortcodes']);
            }

            Plugin::set_enabled_shortcodes($enabled);
            $savedNotice = '<div class="notice notice-success is-dismissible"><p>HP React Widgets settings saved.</p></div>';
        }

        // Handle shortcode deletion.
        if (isset($_GET['hp_rw_action'], $_GET['hp_rw_slug']) && $_GET['hp_rw_action'] === 'delete') {
            $slug = sanitize_key((string) $_GET['hp_rw_slug']);
            if (wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), 'hp_rw_delete_shortcode_' . $slug)) {
                $shortcodes = Plugin::get_shortcodes();
                if (isset($shortcodes[$slug])) {
                    unset($shortcodes[$slug]);
                    Plugin::set_shortcodes($shortcodes);

                    // Also remove from enabled list.
                    $enabled = Plugin::get_enabled_shortcodes();
                    $enabled = array_values(array_diff($enabled, [$slug]));
                    Plugin::set_enabled_shortcodes($enabled);

                    $savedNotice = '<div class="notice notice-success is-dismissible"><p>Shortcode deleted.</p></div>';
                }
            }
        }

        // Handle "Add new shortcode" wizard submission.
        // Detect by presence of the slug field instead of a hidden flag so this
        // still works if another admin plugin rewrites the form method or strips
        // hidden inputs.
        if (isset($_REQUEST['hp_rw_new_slug']) && $_REQUEST['hp_rw_new_slug'] !== '') {
            check_admin_referer('hp_rw_new_shortcode');

            $errors = [];

            // WordPress adds magic quotes to REQUEST data; wp_unslash() removes them.
            $slug       = isset($_REQUEST['hp_rw_new_slug']) ? sanitize_key((string) $_REQUEST['hp_rw_new_slug']) : '';
            $label      = isset($_REQUEST['hp_rw_new_label']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['hp_rw_new_label'])) : '';
            $desc       = isset($_REQUEST['hp_rw_new_description']) ? sanitize_textarea_field(wp_unslash((string) $_REQUEST['hp_rw_new_description'])) : '';
            $component  = isset($_REQUEST['hp_rw_new_component']) ? preg_replace('/[^A-Za-z0-9_]/', '', (string) $_REQUEST['hp_rw_new_component']) : '';
            $root_id    = isset($_REQUEST['hp_rw_new_root_id']) ? sanitize_html_class((string) $_REQUEST['hp_rw_new_root_id']) : '';
            $hydrator   = isset($_REQUEST['hp_rw_new_hydrator']) ? preg_replace('/[^A-Za-z0-9_\\\\]/', '', (string) $_REQUEST['hp_rw_new_hydrator']) : '';

            $editingSlug = isset($_REQUEST['hp_rw_editing_slug']) ? sanitize_key((string) $_REQUEST['hp_rw_editing_slug']) : '';

            if ($slug === '' || strpos($slug, 'hp_') !== 0) {
                $errors[] = 'Shortcode tag is required and must start with the "hp_" prefix (for example: hp_my_new_widget).';
            }

            $allShortcodes = Plugin::get_shortcodes();
            if ($slug && isset($allShortcodes[$slug]) && $slug !== $editingSlug) {
                $errors[] = sprintf('The shortcode "%s" is already registered. Please choose a different tag.', esc_html($slug));
            }

            if ($component === '') {
                $errors[] = 'Component name is required (for example: MyNewWidget).';
            }
            // Note: We no longer validate that the .tsx source file exists because
            // on deployed sites only the compiled bundle is present, not the src/ folder.

            if ($root_id === '') {
                $root_id = 'hp-' . str_replace('_', '-', $slug) . '-root';
            }

            // Note: Hydrator file check removed for same reason - on deployed sites
            // the PHP files exist but the validation was incorrectly blocking saves.

            if (empty($errors)) {
                $shortcodes = Plugin::get_shortcodes();

                // If we are renaming an existing shortcode, remove the old key first.
                if ($editingSlug && $editingSlug !== $slug && isset($shortcodes[$editingSlug])) {
                    unset($shortcodes[$editingSlug]);
                }

                $shortcodes[$slug] = [
                    'label'          => $label !== '' ? $label : $slug,
                    'description'    => $desc,
                    'example'        => '[' . $slug . ']',
                    'component'      => $component,
                    'root_id'        => $root_id,
                    'hydrator_class' => $hydrator !== '' ? $hydrator : '',
                ];

                Plugin::set_shortcodes($shortcodes);

                // Auto-enable the new shortcode.
                $enabled = Plugin::get_enabled_shortcodes();
                if (!in_array($slug, $enabled, true)) {
                    $enabled[] = $slug;
                    Plugin::set_enabled_shortcodes($enabled);
                }

                $savedNotice = '<div class="notice notice-success is-dismissible"><p>Shortcode settings saved.</p></div>';
            } else {
                $errorLines = '<ul><li>' . implode('</li><li>', array_map('esc_html', $errors)) . '</li></ul>';
                $errorNotice = '<div class="notice notice-error"><p><strong>Could not save shortcode.</strong></p>' . $errorLines . '</div>';
            }
        }

        $allShortcodes = Plugin::get_shortcodes();
        $enabled       = Plugin::get_enabled_shortcodes();

        // Editing context for the wizard (when clicking the settings cog on a shortcode row).
        $editingSlug = '';
        $editingMeta = [
            'slug'           => '',
            'label'          => '',
            'description'    => '',
            'component'      => '',
            'root_id'        => '',
            'hydrator_class' => '',
        ];

        if (isset($_GET['hp_rw_edit'])) {
            $candidate = sanitize_key((string) $_GET['hp_rw_edit']);
            if (isset($allShortcodes[$candidate])) {
                $editingSlug                = $candidate;
                $editingMeta['slug']        = $candidate;
                $editingMeta['label']       = isset($allShortcodes[$candidate]['label']) ? (string) $allShortcodes[$candidate]['label'] : '';
                $editingMeta['description'] = isset($allShortcodes[$candidate]['description']) ? (string) $allShortcodes[$candidate]['description'] : '';
                $editingMeta['component']   = isset($allShortcodes[$candidate]['component']) ? (string) $allShortcodes[$candidate]['component'] : '';
                $editingMeta['root_id']     = isset($allShortcodes[$candidate]['root_id']) ? (string) $allShortcodes[$candidate]['root_id'] : '';
                $editingMeta['hydrator_class'] = isset($allShortcodes[$candidate]['hydrator_class']) ? (string) $allShortcodes[$candidate]['hydrator_class'] : '';
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html('HP React Widgets'); ?></h1>
            <p class="description"><?php echo esc_html(sprintf('Version %s', defined('HP_RW_VERSION') ? HP_RW_VERSION : '')); ?></p>

            <?php
            // Output "error" notice if applicable.
            if ($errorNotice) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $errorNotice;
            }
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
                        <th><?php echo esc_html('Actions'); ?></th>
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
                            <td>
                                <?php
                                $baseUrl = menu_page_url('hp-react-widgets', false);
                                $editUrl = add_query_arg(['hp_rw_edit' => $slug], $baseUrl);
                                ?>
                                <a href="<?php echo esc_url($editUrl); ?>" class="dashicons dashicons-admin-generic" title="<?php echo esc_attr__('Edit shortcode', 'hp-react-widgets'); ?>"></a>
                                <?php
                                $deleteUrl = add_query_arg(
                                    [
                                        'hp_rw_action' => 'delete',
                                        'hp_rw_slug'   => $slug,
                                    ],
                                    $baseUrl
                                );
                                $deleteUrl = wp_nonce_url($deleteUrl, 'hp_rw_delete_shortcode_' . $slug);
                                ?>
                                <a href="<?php echo esc_url($deleteUrl); ?>" class="dashicons dashicons-trash"
                                   onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this shortcode? This cannot be undone.', 'hp-react-widgets')); ?>');"
                                   title="<?php echo esc_attr__('Delete shortcode', 'hp-react-widgets'); ?>"></a>
                            </td>
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

            <?php
            // Responsive Settings Section
            $responsiveSettings = Plugin::get_responsive_settings();
            $defaults = Plugin::get_default_responsive_settings();
            ?>
            <h2><?php echo esc_html('Responsive Settings'); ?></h2>
            <p class="description">
                <?php echo esc_html('Configure breakpoint values and scroll behavior for funnel sections. These are plugin-wide defaults that can be overridden per-funnel.'); ?>
            </p>

            <form method="post" style="max-width: 900px;">
                <?php wp_nonce_field('hp_rw_responsive_settings'); ?>
                <input type="hidden" name="hp_rw_responsive_submitted" value="1" />

                <h3><?php echo esc_html('Breakpoints'); ?></h3>
                <p class="description" style="margin-bottom: 15px;">
                    <?php echo esc_html('Define the pixel values where each screen size begins. Mobile is always 0 to Tablet breakpoint.'); ?>
                </p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="breakpoint_tablet"><?php echo esc_html('Tablet starts at (px)'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="breakpoint_tablet" id="breakpoint_tablet" 
                                   value="<?php echo esc_attr($responsiveSettings['breakpoint_tablet']); ?>" 
                                   min="320" max="1200" step="1" class="small-text" />
                            <span class="description"><?php echo esc_html('Default: ' . $defaults['breakpoint_tablet'] . 'px. Mobile: 0-' . ($responsiveSettings['breakpoint_tablet'] - 1) . 'px'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="breakpoint_laptop"><?php echo esc_html('Laptop starts at (px)'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="breakpoint_laptop" id="breakpoint_laptop" 
                                   value="<?php echo esc_attr($responsiveSettings['breakpoint_laptop']); ?>" 
                                   min="640" max="1600" step="1" class="small-text" />
                            <span class="description"><?php echo esc_html('Default: ' . $defaults['breakpoint_laptop'] . 'px. Tablet: ' . $responsiveSettings['breakpoint_tablet'] . '-' . ($responsiveSettings['breakpoint_laptop'] - 1) . 'px'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="breakpoint_desktop"><?php echo esc_html('Desktop starts at (px)'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="breakpoint_desktop" id="breakpoint_desktop" 
                                   value="<?php echo esc_attr($responsiveSettings['breakpoint_desktop']); ?>" 
                                   min="1024" max="2560" step="1" class="small-text" />
                            <span class="description"><?php echo esc_html('Default: ' . $defaults['breakpoint_desktop'] . 'px. Laptop: ' . $responsiveSettings['breakpoint_laptop'] . '-' . ($responsiveSettings['breakpoint_desktop'] - 1) . 'px'); ?></span>
                        </td>
                    </tr>
                </table>

                <h3><?php echo esc_html('Content Layout'); ?></h3>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="content_max_width"><?php echo esc_html('Content Max Width (px)'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="content_max_width" id="content_max_width" 
                                   value="<?php echo esc_attr($responsiveSettings['content_max_width']); ?>" 
                                   min="1000" max="1600" step="50" class="small-text" />
                            <span class="description"><?php echo esc_html('Default: ' . $defaults['content_max_width'] . 'px. Max width for boxed text content on desktop/laptop.'); ?></span>
                        </td>
                    </tr>
                </table>

                <h3><?php echo esc_html('Scroll Behavior'); ?></h3>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="enable_smooth_scroll"><?php echo esc_html('Enable Smooth Scroll'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_smooth_scroll" id="enable_smooth_scroll" 
                                       value="1" <?php checked($responsiveSettings['enable_smooth_scroll']); ?> />
                                <?php echo esc_html('Apply easing animation when scrolling between sections'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="scroll_duration"><?php echo esc_html('Scroll Duration (ms)'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="scroll_duration" id="scroll_duration" 
                                   value="<?php echo esc_attr($responsiveSettings['scroll_duration']); ?>" 
                                   min="200" max="2000" step="50" class="small-text" />
                            <span class="description"><?php echo esc_html('Default: ' . $defaults['scroll_duration'] . 'ms. Duration of scroll animation.'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="scroll_easing"><?php echo esc_html('Scroll Easing'); ?></label>
                        </th>
                        <td>
                            <select name="scroll_easing" id="scroll_easing">
                                <option value="ease-out-cubic" <?php selected($responsiveSettings['scroll_easing'], 'ease-out-cubic'); ?>>
                                    <?php echo esc_html('Ease Out Cubic (fast start, slow landing)'); ?>
                                </option>
                                <option value="ease-out-quad" <?php selected($responsiveSettings['scroll_easing'], 'ease-out-quad'); ?>>
                                    <?php echo esc_html('Ease Out Quad (gentler deceleration)'); ?>
                                </option>
                                <option value="linear" <?php selected($responsiveSettings['scroll_easing'], 'linear'); ?>>
                                    <?php echo esc_html('Linear (constant speed)'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="enable_scroll_snap"><?php echo esc_html('Enable Scroll Snap'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_scroll_snap" id="enable_scroll_snap" 
                                       value="1" <?php checked($responsiveSettings['enable_scroll_snap']); ?> />
                                <?php echo esc_html('Experimental: Snap to section boundaries when scrolling'); ?>
                            </label>
                            <p class="description"><?php echo esc_html('Uses CSS scroll-snap with proximity mode. May affect natural scrolling feel.'); ?></p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html('Save Responsive Settings'); ?>
                    </button>
                </p>
            </form>

            <hr />

            <?php
            // Handle PayPal settings submission
            if (isset($_POST['hp_rw_paypal_submitted'])) {
                check_admin_referer('hp_rw_paypal_settings');

                $paypalSettings = [
                    'enabled'           => isset($_POST['paypal_enabled']),
                    'sandbox_client_id' => isset($_POST['paypal_sandbox_client_id']) ? sanitize_text_field($_POST['paypal_sandbox_client_id']) : '',
                    'sandbox_secret'    => isset($_POST['paypal_sandbox_secret']) ? sanitize_text_field($_POST['paypal_sandbox_secret']) : '',
                    'live_client_id'    => isset($_POST['paypal_live_client_id']) ? sanitize_text_field($_POST['paypal_live_client_id']) : '',
                    'live_secret'       => isset($_POST['paypal_live_secret']) ? sanitize_text_field($_POST['paypal_live_secret']) : '',
                ];

                update_option('hp_rw_paypal_settings', $paypalSettings);
                echo '<div class="notice notice-success is-dismissible"><p>PayPal settings saved.</p></div>';
            }

            $paypalSettings = get_option('hp_rw_paypal_settings', []);
            ?>

            <h2><?php echo esc_html('PayPal Settings'); ?></h2>
            <p class="description">
                <?php echo esc_html('Configure PayPal credentials for funnel checkout. Get your REST API credentials from the PayPal Developer Dashboard.'); ?>
            </p>

            <form method="post" style="max-width: 900px;">
                <?php wp_nonce_field('hp_rw_paypal_settings'); ?>
                <input type="hidden" name="hp_rw_paypal_submitted" value="1" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="paypal_enabled"><?php echo esc_html('Enable PayPal'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="paypal_enabled" id="paypal_enabled" 
                                       value="1" <?php checked(!empty($paypalSettings['enabled'])); ?> />
                                <?php echo esc_html('Show PayPal button in funnel checkout'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h3><?php echo esc_html('Sandbox Credentials (for testing)'); ?></h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="paypal_sandbox_client_id"><?php echo esc_html('Client ID'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="paypal_sandbox_client_id" id="paypal_sandbox_client_id" 
                                   value="<?php echo esc_attr($paypalSettings['sandbox_client_id'] ?? ''); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="paypal_sandbox_secret"><?php echo esc_html('Secret'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="paypal_sandbox_secret" id="paypal_sandbox_secret" 
                                   value="<?php echo esc_attr($paypalSettings['sandbox_secret'] ?? ''); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>

                <h3><?php echo esc_html('Live Credentials (for production)'); ?></h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="paypal_live_client_id"><?php echo esc_html('Client ID'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="paypal_live_client_id" id="paypal_live_client_id" 
                                   value="<?php echo esc_attr($paypalSettings['live_client_id'] ?? ''); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="paypal_live_secret"><?php echo esc_html('Secret'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="paypal_live_secret" id="paypal_live_secret" 
                                   value="<?php echo esc_attr($paypalSettings['live_secret'] ?? ''); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html('Save PayPal Settings'); ?>
                    </button>
                </p>
            </form>

            <hr />

            <?php
            // Handle Advanced settings submission
            if (isset($_POST['hp_rw_advanced_submitted'])) {
                check_admin_referer('hp_rw_advanced_settings');

                $advancedSettings = get_option('hp_rw_advanced_settings', []);
                $advancedSettings['disable_subroute_query_hijack'] = isset($_POST['disable_subroute_query_hijack']);

                update_option('hp_rw_advanced_settings', $advancedSettings);
                echo '<div class="notice notice-success is-dismissible"><p>Advanced settings saved.</p></div>';
            }

            $advancedSettings = get_option('hp_rw_advanced_settings', []);
            ?>

            <h2><?php echo esc_html('Advanced Settings'); ?></h2>
            <p class="description">
                <?php echo esc_html('Developer options and troubleshooting toggles.'); ?>
            </p>

            <form method="post" style="max-width: 900px;">
                <?php wp_nonce_field('hp_rw_advanced_settings'); ?>
                <input type="hidden" name="hp_rw_advanced_submitted" value="1" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="disable_subroute_query_hijack"><?php echo esc_html('Disable Sub-Route Query Hijack'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="disable_subroute_query_hijack" id="disable_subroute_query_hijack" 
                                       value="1" <?php checked(!empty($advancedSettings['disable_subroute_query_hijack'])); ?> />
                                <?php echo esc_html('Disable the WP query hijack for funnel sub-routes (checkout/thank-you)'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html('When enabled, the plugin makes WordPress believe /checkout and /thank-you sub-routes are viewing the funnel CPT. This allows Elementor Theme Builder conditions (header/footer) to apply correctly. Disable this if you experience unexpected behavior on funnel pages.'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html('Save Advanced Settings'); ?>
                    </button>
                </p>
            </form>

            <hr />

            <?php $isEditing = ($editingSlug !== ''); ?>
            <h2>
                <?php
                echo esc_html(
                    $isEditing
                        ? sprintf('Edit shortcode: %s', $editingSlug)
                        : 'Add a new shortcode (wizard)'
                );
                ?>
            </h2>
            <p>
                <?php
                echo esc_html(
                    $isEditing
                        ? 'Update the configuration for this React widget shortcode. The wizard validates file locations and wiring.'
                        : 'Follow these steps to register a new React widget shortcode. The wizard will validate file locations and wire up mounting automatically.'
                );
                ?>
            </p>

            <form method="post" style="max-width: 900px;">
                <?php wp_nonce_field('hp_rw_new_shortcode'); ?>
                <input type="hidden" name="hp_rw_new_shortcode_submitted" value="1" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="hp_rw_new_slug"><?php echo esc_html('Shortcode tag'); ?></label></th>
                        <td>
                            <input name="hp_rw_new_slug" id="hp_rw_new_slug" type="text" class="regular-text"
                                   placeholder="hp_my_new_widget"
                                   value="<?php echo esc_attr($editingMeta['slug']); ?>" />
                            <p class="description"><?php echo esc_html('Must start with "hp_" and contain only lowercase letters, numbers and underscores.'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hp_rw_new_label"><?php echo esc_html('Label'); ?></label></th>
                        <td>
                            <input name="hp_rw_new_label" id="hp_rw_new_label" type="text" class="regular-text"
                                   placeholder="My New Widget"
                                   value="<?php echo esc_attr($editingMeta['label']); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hp_rw_new_description"><?php echo esc_html('Description'); ?></label></th>
                        <td>
                            <textarea name="hp_rw_new_description" id="hp_rw_new_description" class="large-text" rows="3"><?php echo esc_textarea($editingMeta['description']); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hp_rw_new_component"><?php echo esc_html('React component name'); ?></label></th>
                        <td>
                            <input name="hp_rw_new_component" id="hp_rw_new_component" type="text" class="regular-text"
                                   placeholder="MyNewWidget"
                                   value="<?php echo esc_attr($editingMeta['component']); ?>" />
                            <p class="description">
                                <?php echo esc_html('The component must be exported from src/components/MyNewWidget.tsx.'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hp_rw_new_root_id"><?php echo esc_html('Root DOM ID'); ?></label></th>
                        <td>
                            <input name="hp_rw_new_root_id" id="hp_rw_new_root_id" type="text" class="regular-text"
                                   placeholder="hp-my-new-widget-root"
                                   value="<?php echo esc_attr($editingMeta['root_id']); ?>" />
                            <p class="description">
                                <?php echo esc_html('Optional. If left empty, a default based on the shortcode tag will be used.'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hp_rw_new_hydrator"><?php echo esc_html('Hydrator PHP class (optional)'); ?></label></th>
                        <td>
                            <input name="hp_rw_new_hydrator" id="hp_rw_new_hydrator" type="text" class="regular-text"
                                   placeholder="MyNewWidgetShortcode"
                                   value="<?php echo esc_attr($editingMeta['hydrator_class']); ?>" />
                            <p class="description">
                                <?php echo esc_html('Optional backend class under namespace HP_RW\\Shortcodes, file includes/Shortcodes/MyNewWidgetShortcode.php with a public render($atts) method.'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p>
                    <input type="hidden" name="hp_rw_editing_slug" value="<?php echo esc_attr($editingSlug); ?>" />
                    <button type="submit" class="button button-secondary">
                        <?php echo esc_html($isEditing ? 'Save changes' : 'Register shortcode'); ?>
                    </button>
                    <?php if ($isEditing) : ?>
                        <?php $newUrl = menu_page_url('hp-react-widgets', false); ?>
                        <a href="<?php echo esc_url($newUrl); ?>" class="button">
                            <?php echo esc_html('Add new shortcode'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>

            <hr />

            <h2><?php echo esc_html('Developer workflow overview'); ?></h2>
            <p><?php echo esc_html('At a high level, creating a new shortcode involves:'); ?></p>

            <p>
                <a class="button" href="<?php echo esc_url(HP_RW_URL . 'SHORTCODES_DEVELOPER_GUIDE.md'); ?>" download="HP-React-Widgets-Shortcodes-Guide.md">
                    <?php echo esc_html('Download developer guide (Markdown)'); ?>
                </a>
            </p>

            <ol style="max-width: 900px; list-style: decimal; padding-left: 20px;">
                <li><?php echo esc_html('Copy your React component into src/components/YourWidgetName.tsx, exporting YourWidgetName.'); ?></li>
                <li><?php echo esc_html('(Optional but recommended) Create a hydrator PHP class in includes/Shortcodes/YourWidgetShortcode.php that queries WooCommerce / WordPress and returns a hydrated <div> markup.'); ?></li>
                <li><?php echo esc_html('Use the wizard above to register the shortcode tag, component name and root DOM ID. This only updates configuration and uses the files you have added; no core plugin PHP files are modified.'); ?></li>
                <li><?php echo esc_html('Place the new shortcode (for example [hp_my_new_widget]) inside Elementor or any content area.'); ?></li>
            </ol>
        </div>
        <?php
    }
}


