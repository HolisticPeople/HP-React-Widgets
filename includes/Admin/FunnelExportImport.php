<?php
namespace HP_RW\Admin;

use HP_RW\Plugin;
use HP_RW\Services\FunnelConfigLoader;
use HP_RW\Services\FunnelExporter;
use HP_RW\Services\FunnelImporter;
use HP_RW\Services\FunnelSchema;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin page for exporting and importing funnel configurations.
 * 
 * Supports JSON import/export for AI agents and cross-environment sync.
 */
class FunnelExportImport
{
    /**
     * Transient key for import messages.
     */
    private const MESSAGE_TRANSIENT = 'hp_funnel_import_message';

    /**
     * Initialize the export/import page.
     */
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'addSubmenuPage']);
        add_action('admin_init', [self::class, 'handleActions']);
        add_action('admin_notices', [self::class, 'displayImportNotices']);
    }

    /**
     * Display import notices from transient.
     */
    public static function displayImportNotices(): void
    {
        $message = get_transient(self::MESSAGE_TRANSIENT);
        if ($message) {
            delete_transient(self::MESSAGE_TRANSIENT);
            $type = $message['type'] ?? 'info';
            $text = $message['text'] ?? '';
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . wp_kses_post($text) . '</p></div>';
        }
    }

    /**
     * Message to pass via redirect URL.
     */
    private static ?array $redirectMessage = null;

    /**
     * Store a message for display after redirect.
     */
    private static function storeMessage(string $text, string $type = 'success'): void
    {
        // Store in static var - will be encoded in redirect URL
        self::$redirectMessage = ['text' => $text, 'type' => $type];
    }

    /**
     * Get the redirect URL with message encoded.
     */
    private static function getRedirectUrl(): string
    {
        $url = admin_url('edit.php?post_type=hp-funnel&page=hp-funnel-export-import');
        
        if (self::$redirectMessage) {
            $url = add_query_arg([
                'import_msg' => urlencode(self::$redirectMessage['text']),
                'import_type' => self::$redirectMessage['type'],
            ], $url);
        }
        
        return $url;
    }

    /**
     * Add submenu page under Funnels.
     */
    public static function addSubmenuPage(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . Plugin::FUNNEL_POST_TYPE,
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

        // Handle file import
        if (isset($_POST['hp_funnel_import'])) {
            $nonce = $_POST['_wpnonce_file'] ?? '';
            if (wp_verify_nonce($nonce, 'hp_funnel_import')) {
                self::handleImport();
            } else {
                self::storeMessage(__('Security verification failed. Please refresh and try again.', 'hp-react-widgets'), 'error');
            }
            // Redirect with message in URL
            wp_safe_redirect(self::getRedirectUrl());
            exit;
        }

        // Handle JSON text import
        if (isset($_POST['hp_funnel_import_json'])) {
            $nonce = $_POST['_wpnonce_json'] ?? '';
            if (wp_verify_nonce($nonce, 'hp_funnel_import_json')) {
                self::handleJsonImport();
            } else {
                self::storeMessage(__('Security verification failed. Please refresh and try again.', 'hp-react-widgets'), 'error');
            }
            // Redirect with message in URL
            wp_safe_redirect(self::getRedirectUrl());
            exit;
        }
    }

    /**
     * Handle funnel export using new exporter.
     */
    private static function handleExport(): void
    {
        $funnelId = isset($_GET['funnel_id']) ? absint($_GET['funnel_id']) : 0;
        $exportAll = isset($_GET['export_all']);

        if ($exportAll) {
            $data = [
                '$schema' => FunnelSchema::VERSION,
                'funnels' => FunnelExporter::exportAll(),
                '_export_meta' => [
                    'version' => HP_RW_VERSION,
                    'exported_at' => current_time('mysql'),
                    'site_url' => home_url(),
                ],
            ];
            $filename = 'hp-funnels-export-' . date('Y-m-d') . '.json';
        } elseif ($funnelId > 0) {
            $data = FunnelExporter::exportById($funnelId);
            if (!$data) {
                wp_die(__('Funnel not found', 'hp-react-widgets'));
            }
            $slug = $data['funnel']['slug'] ?? 'funnel';
            $filename = 'hp-funnel-' . $slug . '-' . date('Y-m-d') . '.json';
        } else {
            wp_die(__('Invalid funnel ID', 'hp-react-widgets'));
        }

        // Send as download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Handle file upload import.
     */
    private static function handleImport(): void
    {
        if (!isset($_FILES['import_file'])) {
            self::storeMessage(__('No file was uploaded. Please select a file and try again.', 'hp-react-widgets'), 'error');
            return;
        }

        if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
            ];
            $errorCode = $_FILES['import_file']['error'];
            $errorMsg = $errorMessages[$errorCode] ?? "Unknown error (code: {$errorCode})";
            
            self::storeMessage(sprintf(__('File upload failed: %s', 'hp-react-widgets'), $errorMsg), 'error');
            return;
        }

        $fileContent = file_get_contents($_FILES['import_file']['tmp_name']);
        if (empty($fileContent)) {
            self::storeMessage(__('The uploaded file is empty.', 'hp-react-widgets'), 'error');
            return;
        }

        self::processImport($fileContent);
    }

    /**
     * Handle JSON text import.
     */
    private static function handleJsonImport(): void
    {
        if (empty($_POST['json_content'])) {
            self::storeMessage(__('No JSON content provided.', 'hp-react-widgets'), 'error');
            return;
        }

        self::processImport(wp_unslash($_POST['json_content']));
    }

    /**
     * Process import from JSON string.
     */
    private static function processImport(string $jsonContent): void
    {
        $data = json_decode($jsonContent, true);

        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            self::storeMessage(__('Invalid JSON: ', 'hp-react-widgets') . json_last_error_msg(), 'error');
            return;
        }

        // Determine import mode
        $mode = $_POST['import_mode'] ?? 'skip';
        $modeMap = [
            'skip' => FunnelImporter::MODE_SKIP,
            'update' => FunnelImporter::MODE_UPDATE,
            'create_new' => FunnelImporter::MODE_CREATE_NEW,
        ];
        $importMode = $modeMap[$mode] ?? FunnelImporter::MODE_SKIP;

        // Handle multi-funnel import
        if (isset($data['funnels']) && is_array($data['funnels'])) {
            $results = FunnelImporter::importMultiple($data['funnels'], $importMode);
            
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            foreach ($results as $slug => $result) {
                switch ($result['result'] ?? '') {
                    case FunnelImporter::RESULT_CREATED:
                        $created++;
                        break;
                    case FunnelImporter::RESULT_UPDATED:
                        $updated++;
                        break;
                    case FunnelImporter::RESULT_SKIPPED:
                        $skipped++;
                        break;
                    case FunnelImporter::RESULT_ERROR:
                        $errors[] = $slug . ': ' . ($result['error'] ?? 'Unknown error');
                        break;
                }
            }

            $message = sprintf(
                __('Import complete: %d created, %d updated, %d skipped.', 'hp-react-widgets'),
                $created, $updated, $skipped
            );

            if (!empty($errors)) {
                $message .= ' ' . __('Errors:', 'hp-react-widgets') . ' ' . implode('; ', $errors);
                self::storeMessage($message, 'warning');
            } else {
                self::storeMessage($message, 'success');
            }
        } else {
            // Single funnel import
            $result = FunnelImporter::import($data, $importMode);

            if ($result['success']) {
                $message = $result['message'] ?? __('Funnel imported successfully.', 'hp-react-widgets');
                if (!empty($result['edit_url'])) {
                    $message .= ' <a href="' . esc_url($result['edit_url']) . '">' . __('Edit funnel', 'hp-react-widgets') . '</a>';
                }
                self::storeMessage($message, 'success');
            } else {
                self::storeMessage($result['error'] ?? __('Import failed.', 'hp-react-widgets'), 'error');
            }
        }
    }

    /**
     * Render the export/import admin page.
     */
    public static function renderPage(): void
    {
        $funnels = FunnelConfigLoader::getAllPosts();
        $schemaUrl = rest_url('hp-rw/v1/funnels/schema');
        
        // Display message from URL (immune to object cache and admin notice hiding plugins)
        $message = null;
        if (isset($_GET['import_msg']) && isset($_GET['import_type'])) {
            $message = [
                'text' => sanitize_text_field(urldecode($_GET['import_msg'])),
                'type' => sanitize_key($_GET['import_type']),
            ];
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Funnel Export / Import', 'hp-react-widgets'); ?></h1>
            <p style="color: #666; margin-top: -10px;">HP React Widgets v<?php echo esc_html(HP_RW_VERSION); ?></p>

            <?php if ($message): ?>
                <div class="hp-import-message" style="padding: 12px 15px; margin: 15px 0; border-left: 4px solid <?php echo $message['type'] === 'success' ? '#00a32a' : ($message['type'] === 'error' ? '#d63638' : '#dba617'); ?>; background: <?php echo $message['type'] === 'success' ? '#edfaef' : ($message['type'] === 'error' ? '#fcf0f1' : '#fcf9e8'); ?>; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <strong><?php echo $message['type'] === 'success' ? '✓' : ($message['type'] === 'error' ? '✗' : '!'); ?></strong>
                    <?php echo esc_html($message['text']); ?>
                </div>
            <?php endif; ?>

            <?php settings_errors('hp_funnel_import'); ?>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- Export Section -->
                <div class="card" style="flex: 1; min-width: 300px; max-width: 500px;">
                    <h2><?php echo esc_html__('Export Funnels', 'hp-react-widgets'); ?></h2>
                    <p><?php echo esc_html__('Download funnel configurations as JSON files.', 'hp-react-widgets'); ?></p>

                    <?php if (empty($funnels)): ?>
                        <p><em><?php echo esc_html__('No funnels to export.', 'hp-react-widgets'); ?></em></p>
                    <?php else: ?>
                        <table class="widefat" style="margin-bottom: 15px;">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Funnel', 'hp-react-widgets'); ?></th>
                                    <th><?php echo esc_html__('Slug', 'hp-react-widgets'); ?></th>
                                    <th><?php echo esc_html__('Status', 'hp-react-widgets'); ?></th>
                                    <th><?php echo esc_html__('Action', 'hp-react-widgets'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($funnels as $funnel): 
                                    $slug = get_field('funnel_slug', $funnel->ID) ?: $funnel->post_name;
                                    $status = get_field('funnel_status', $funnel->ID) ?: 'active';
                                    $exportUrl = wp_nonce_url(
                                        add_query_arg([
                                            'hp_funnel_export' => 1,
                                            'funnel_id' => $funnel->ID,
                                        ], admin_url('edit.php?post_type=' . Plugin::FUNNEL_POST_TYPE . '&page=hp-funnel-export-import')),
                                        'hp_funnel_export'
                                    );
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($funnel->post_title); ?></strong></td>
                                    <td><code><?php echo esc_html($slug); ?></code></td>
                                    <td>
                                        <span style="color: <?php echo $status === 'active' ? '#00a32a' : '#d63638'; ?>;">●</span>
                                        <?php echo esc_html(ucfirst($status)); ?>
                                    </td>
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
                            ], admin_url('edit.php?post_type=' . Plugin::FUNNEL_POST_TYPE . '&page=hp-funnel-export-import')),
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
                    <h2><?php echo esc_html__('Import Funnel', 'hp-react-widgets'); ?></h2>
                    
                    <!-- File Upload -->
                    <h3 style="margin-top: 0;"><?php echo esc_html__('From File', 'hp-react-widgets'); ?></h3>
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('hp_funnel_import', '_wpnonce_file'); ?>
                        
                        <p>
                            <input type="file" name="import_file" accept=".json" required>
                        </p>

                        <p>
                            <label><strong><?php echo esc_html__('When funnel slug exists:', 'hp-react-widgets'); ?></strong></label><br>
                            <label>
                                <input type="radio" name="import_mode" value="skip" checked>
                                <?php echo esc_html__('Skip (keep existing)', 'hp-react-widgets'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="import_mode" value="update">
                                <?php echo esc_html__('Update existing funnel', 'hp-react-widgets'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="import_mode" value="create_new">
                                <?php echo esc_html__('Create new with different slug', 'hp-react-widgets'); ?>
                            </label>
                        </p>

                        <p>
                            <button type="submit" name="hp_funnel_import" class="button button-primary">
                                <?php echo esc_html__('Import File', 'hp-react-widgets'); ?>
                            </button>
                        </p>
                    </form>

                    <hr>

                    <!-- JSON Paste -->
                    <h3><?php echo esc_html__('From JSON', 'hp-react-widgets'); ?></h3>
                    <form method="post">
                        <?php wp_nonce_field('hp_funnel_import_json', '_wpnonce_json'); ?>
                        
                        <p>
                            <textarea name="json_content" rows="8" style="width: 100%; font-family: monospace; font-size: 12px;" placeholder='{"funnel": {"name": "My Funnel", "slug": "my-funnel"}, ...}'></textarea>
                        </p>

                        <p>
                            <label><strong><?php echo esc_html__('When funnel slug exists:', 'hp-react-widgets'); ?></strong></label><br>
                            <label>
                                <input type="radio" name="import_mode" value="skip" checked>
                                <?php echo esc_html__('Skip', 'hp-react-widgets'); ?>
                            </label>
                            <label>
                                <input type="radio" name="import_mode" value="update">
                                <?php echo esc_html__('Update', 'hp-react-widgets'); ?>
                            </label>
                            <label>
                                <input type="radio" name="import_mode" value="create_new">
                                <?php echo esc_html__('Create New', 'hp-react-widgets'); ?>
                            </label>
                        </p>

                        <p>
                            <button type="submit" name="hp_funnel_import_json" class="button button-primary">
                                <?php echo esc_html__('Import JSON', 'hp-react-widgets'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>

            <!-- API Documentation -->
            <div class="card" style="margin-top: 20px; max-width: 1020px;">
                <h2><?php echo esc_html__('REST API for AI Agents', 'hp-react-widgets'); ?></h2>
                <p><?php echo esc_html__('Use these endpoints to programmatically manage funnels:', 'hp-react-widgets'); ?></p>

                <table class="widefat" style="margin-bottom: 15px;">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Endpoint', 'hp-react-widgets'); ?></th>
                            <th><?php echo esc_html__('Method', 'hp-react-widgets'); ?></th>
                            <th><?php echo esc_html__('Auth', 'hp-react-widgets'); ?></th>
                            <th><?php echo esc_html__('Description', 'hp-react-widgets'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>/wp-json/hp-rw/v1/funnels/schema</code></td>
                            <td>GET</td>
                            <td>Public</td>
                            <td><?php echo esc_html__('Get JSON schema with example (for AI agents)', 'hp-react-widgets'); ?></td>
                        </tr>
                        <tr>
                            <td><code>/wp-json/hp-rw/v1/funnels/validate</code></td>
                            <td>POST</td>
                            <td>Public</td>
                            <td><?php echo esc_html__('Validate JSON before importing', 'hp-react-widgets'); ?></td>
                        </tr>
                        <tr>
                            <td><code>/wp-json/hp-rw/v1/funnels</code></td>
                            <td>GET</td>
                            <td>Auth</td>
                            <td><?php echo esc_html__('List all funnels', 'hp-react-widgets'); ?></td>
                        </tr>
                        <tr>
                            <td><code>/wp-json/hp-rw/v1/funnels/export/{slug}</code></td>
                            <td>GET</td>
                            <td>Auth</td>
                            <td><?php echo esc_html__('Export single funnel', 'hp-react-widgets'); ?></td>
                        </tr>
                        <tr>
                            <td><code>/wp-json/hp-rw/v1/funnels/import</code></td>
                            <td>POST</td>
                            <td>Admin</td>
                            <td><?php echo esc_html__('Import funnel from JSON', 'hp-react-widgets'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <p>
                    <a href="<?php echo esc_url($schemaUrl); ?>" target="_blank" class="button">
                        <?php echo esc_html__('View Schema', 'hp-react-widgets'); ?> ↗
                    </a>
                </p>

                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; font-weight: bold;">
                        <?php echo esc_html__('AI Agent Instructions', 'hp-react-widgets'); ?>
                    </summary>
                    <div style="background: #f0f0f1; padding: 15px; margin-top: 10px; border-radius: 4px;">
                        <p><?php echo esc_html__('To create a funnel programmatically:', 'hp-react-widgets'); ?></p>
                        <ol>
                            <li><?php echo esc_html__('GET /wp-json/hp-rw/v1/funnels/schema to understand the structure', 'hp-react-widgets'); ?></li>
                            <li><?php echo esc_html__('Generate JSON based on the schema and example', 'hp-react-widgets'); ?></li>
                            <li><?php echo esc_html__('POST to /wp-json/hp-rw/v1/funnels/validate to check for errors', 'hp-react-widgets'); ?></li>
                            <li><?php echo esc_html__('POST to /wp-json/hp-rw/v1/funnels/import with {"data": YOUR_JSON, "mode": "update"}', 'hp-react-widgets'); ?></li>
                        </ol>
                        <p><strong><?php echo esc_html__('Authentication:', 'hp-react-widgets'); ?></strong> 
                            <?php echo esc_html__('Use WordPress application passwords or cookie-based auth.', 'hp-react-widgets'); ?>
                        </p>
                    </div>
                </details>
            </div>
        </div>
        <?php
    }
}
