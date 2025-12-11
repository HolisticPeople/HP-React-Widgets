<?php
namespace HP_RW\Admin;

use HP_RW\FunnelPostType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page for exporting and importing funnel configurations.
 * 
 * Allows syncing funnels between staging and production environments.
 */
class FunnelExportImport
{
    /**
     * Initialize the export/import page.
     */
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'addSubmenuPage']);
        add_action('admin_init', [self::class, 'handleActions']);
    }

    /**
     * Add submenu page under Funnels.
     */
    public static function addSubmenuPage(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . FunnelPostType::POST_TYPE,
            __('Export / Import', 'hp-react-widgets'),
            __('Export / Import', 'hp-react-widgets'),
            'manage_options',
            'hp-funnel-export-import',
            [self::class, 'renderPage']
        );
    }

    /**
     * Handle export and import actions.
     */
    public static function handleActions(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle export
        if (isset($_GET['hp_funnel_export']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'hp_funnel_export')) {
            self::handleExport();
        }

        // Handle import
        if (isset($_POST['hp_funnel_import']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'hp_funnel_import')) {
            self::handleImport();
        }
    }

    /**
     * Handle funnel export.
     */
    private static function handleExport(): void
    {
        $funnelId = isset($_GET['funnel_id']) ? absint($_GET['funnel_id']) : 0;
        $exportAll = isset($_GET['export_all']);

        $data = [];

        if ($exportAll) {
            $funnels = FunnelPostType::getAll();
            foreach ($funnels as $funnel) {
                $data['funnels'][] = self::exportFunnel($funnel->ID);
            }
            $filename = 'hp-funnels-export-' . date('Y-m-d') . '.json';
        } elseif ($funnelId > 0) {
            $data = self::exportFunnel($funnelId);
            $slug = get_field('funnel_slug', $funnelId) ?: get_post_field('post_name', $funnelId);
            $filename = 'hp-funnel-' . $slug . '-' . date('Y-m-d') . '.json';
        } else {
            wp_die(__('Invalid funnel ID', 'hp-react-widgets'));
        }

        // Add metadata
        $data['_export_meta'] = [
            'version'     => HP_RW_VERSION,
            'exported_at' => current_time('mysql'),
            'site_url'    => home_url(),
        ];

        // Send as download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Export a single funnel to array.
     *
     * @param int $postId Funnel post ID
     * @return array Export data
     */
    private static function exportFunnel(int $postId): array
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== FunnelPostType::POST_TYPE) {
            return [];
        }

        // Get all ACF fields
        $fields = get_fields($postId);
        
        // Get meta fields that might not be in ACF
        $meta = [
            '_thumbnail_id' => get_post_thumbnail_id($postId),
        ];

        return [
            'post' => [
                'post_title'   => $post->post_title,
                'post_name'    => $post->post_name,
                'post_status'  => $post->post_status,
                'post_content' => $post->post_content,
                'post_excerpt' => $post->post_excerpt,
            ],
            'acf_fields' => $fields ?: [],
            'meta'       => $meta,
        ];
    }

    /**
     * Handle funnel import.
     */
    private static function handleImport(): void
    {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            add_settings_error(
                'hp_funnel_import',
                'upload_error',
                __('File upload failed. Please try again.', 'hp-react-widgets'),
                'error'
            );
            return;
        }

        $fileContent = file_get_contents($_FILES['import_file']['tmp_name']);
        $data = json_decode($fileContent, true);

        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error(
                'hp_funnel_import',
                'parse_error',
                __('Invalid JSON file. Please check the file format.', 'hp-react-widgets'),
                'error'
            );
            return;
        }

        $updateExisting = !empty($_POST['update_existing']);
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        // Handle multi-funnel export
        if (isset($data['funnels']) && is_array($data['funnels'])) {
            foreach ($data['funnels'] as $funnelData) {
                $result = self::importFunnel($funnelData, $updateExisting);
                if ($result === 'imported') $imported++;
                elseif ($result === 'updated') $updated++;
                else $skipped++;
            }
        } else {
            // Single funnel export
            $result = self::importFunnel($data, $updateExisting);
            if ($result === 'imported') $imported++;
            elseif ($result === 'updated') $updated++;
            else $skipped++;
        }

        $message = sprintf(
            __('Import complete: %d imported, %d updated, %d skipped.', 'hp-react-widgets'),
            $imported,
            $updated,
            $skipped
        );

        add_settings_error(
            'hp_funnel_import',
            'import_success',
            $message,
            'success'
        );
    }

    /**
     * Import a single funnel.
     *
     * @param array $data    Funnel data
     * @param bool  $update  Whether to update existing
     * @return string Result: 'imported', 'updated', 'skipped'
     */
    private static function importFunnel(array $data, bool $update): string
    {
        if (empty($data['post']) || empty($data['acf_fields'])) {
            return 'skipped';
        }

        $postData = $data['post'];
        $acfFields = $data['acf_fields'];
        $slug = $acfFields['funnel_slug'] ?? $postData['post_name'] ?? '';

        if (empty($slug)) {
            return 'skipped';
        }

        // Check if funnel already exists
        $existing = FunnelPostType::getBySlug($slug);

        if ($existing && !$update) {
            return 'skipped';
        }

        if ($existing) {
            // Update existing
            $postId = $existing->ID;
            
            wp_update_post([
                'ID'           => $postId,
                'post_title'   => $postData['post_title'] ?? $existing->post_title,
                'post_status'  => $postData['post_status'] ?? 'publish',
                'post_content' => $postData['post_content'] ?? '',
            ]);

            $result = 'updated';
        } else {
            // Create new
            $postId = wp_insert_post([
                'post_type'    => FunnelPostType::POST_TYPE,
                'post_title'   => $postData['post_title'] ?? ucfirst($slug),
                'post_name'    => $postData['post_name'] ?? $slug,
                'post_status'  => $postData['post_status'] ?? 'publish',
                'post_content' => $postData['post_content'] ?? '',
            ]);

            if (is_wp_error($postId)) {
                return 'skipped';
            }

            $result = 'imported';
        }

        // Update ACF fields
        if (function_exists('update_field')) {
            foreach ($acfFields as $fieldName => $value) {
                update_field($fieldName, $value, $postId);
            }
        }

        // Clear cache
        if (class_exists('HP_RW\\Services\\FunnelConfigLoader')) {
            \HP_RW\Services\FunnelConfigLoader::clearCache($postId);
        }

        return $result;
    }

    /**
     * Render the export/import admin page.
     */
    public static function renderPage(): void
    {
        $funnels = FunnelPostType::getAll();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Funnel Export / Import', 'hp-react-widgets'); ?></h1>

            <?php settings_errors('hp_funnel_import'); ?>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- Export Section -->
                <div class="card" style="flex: 1; min-width: 300px; max-width: 500px;">
                    <h2><?php echo esc_html__('Export Funnels', 'hp-react-widgets'); ?></h2>
                    <p><?php echo esc_html__('Download funnel configurations as JSON files to import on another site.', 'hp-react-widgets'); ?></p>

                    <?php if (empty($funnels)): ?>
                        <p><em><?php echo esc_html__('No funnels to export.', 'hp-react-widgets'); ?></em></p>
                    <?php else: ?>
                        <table class="widefat" style="margin-bottom: 15px;">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Funnel', 'hp-react-widgets'); ?></th>
                                    <th><?php echo esc_html__('Slug', 'hp-react-widgets'); ?></th>
                                    <th><?php echo esc_html__('Action', 'hp-react-widgets'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($funnels as $funnel): 
                                    $slug = get_field('funnel_slug', $funnel->ID) ?: $funnel->post_name;
                                    $exportUrl = wp_nonce_url(
                                        add_query_arg([
                                            'hp_funnel_export' => 1,
                                            'funnel_id' => $funnel->ID,
                                        ], admin_url('edit.php?post_type=' . FunnelPostType::POST_TYPE . '&page=hp-funnel-export-import')),
                                        'hp_funnel_export'
                                    );
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($funnel->post_title); ?></strong></td>
                                    <td><code><?php echo esc_html($slug); ?></code></td>
                                    <td>
                                        <a href="<?php echo esc_url($exportUrl); ?>" class="button button-small">
                                            <?php echo esc_html__('Export', 'hp-react-widgets'); ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php 
                        $exportAllUrl = wp_nonce_url(
                            add_query_arg([
                                'hp_funnel_export' => 1,
                                'export_all' => 1,
                            ], admin_url('edit.php?post_type=' . FunnelPostType::POST_TYPE . '&page=hp-funnel-export-import')),
                            'hp_funnel_export'
                        );
                        ?>
                        <a href="<?php echo esc_url($exportAllUrl); ?>" class="button button-primary">
                            <?php echo esc_html__('Export All Funnels', 'hp-react-widgets'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Import Section -->
                <div class="card" style="flex: 1; min-width: 300px; max-width: 500px;">
                    <h2><?php echo esc_html__('Import Funnels', 'hp-react-widgets'); ?></h2>
                    <p><?php echo esc_html__('Upload a JSON file exported from another site to import funnel configurations.', 'hp-react-widgets'); ?></p>

                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('hp_funnel_import'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="import_file"><?php echo esc_html__('JSON File', 'hp-react-widgets'); ?></label>
                                </th>
                                <td>
                                    <input type="file" name="import_file" id="import_file" accept=".json" required>
                                    <p class="description">
                                        <?php echo esc_html__('Select a .json file exported from HP React Widgets.', 'hp-react-widgets'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Options', 'hp-react-widgets'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="update_existing" value="1">
                                        <?php echo esc_html__('Update existing funnels with matching slugs', 'hp-react-widgets'); ?>
                                    </label>
                                    <p class="description">
                                        <?php echo esc_html__('If unchecked, funnels with existing slugs will be skipped.', 'hp-react-widgets'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" name="hp_funnel_import" class="button button-primary">
                                <?php echo esc_html__('Import', 'hp-react-widgets'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>

            <!-- Help Section -->
            <div class="card" style="margin-top: 20px; max-width: 1020px;">
                <h2><?php echo esc_html__('How to Use', 'hp-react-widgets'); ?></h2>
                <ol>
                    <li><?php echo esc_html__('On your staging site, create and configure funnels using the Funnels menu.', 'hp-react-widgets'); ?></li>
                    <li><?php echo esc_html__('Export the funnels you want to move to production.', 'hp-react-widgets'); ?></li>
                    <li><?php echo esc_html__('On your production site, import the JSON file.', 'hp-react-widgets'); ?></li>
                    <li><?php echo esc_html__('Review and publish the imported funnels.', 'hp-react-widgets'); ?></li>
                </ol>
                <p><strong><?php echo esc_html__('Note:', 'hp-react-widgets'); ?></strong> 
                    <?php echo esc_html__('Image URLs are exported as-is. Make sure images exist on the destination site or update the URLs after import.', 'hp-react-widgets'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}

