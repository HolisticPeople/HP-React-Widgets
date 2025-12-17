<?php
namespace HP_RW\Rest;

use HP_RW\Services\FunnelSchema;
use HP_RW\Services\FunnelExporter;
use HP_RW\Services\FunnelImporter;
use HP_RW\Services\FunnelConfigLoader;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API endpoints for funnel import/export.
 * 
 * Provides endpoints for AI agents and programmatic funnel management.
 */
class FunnelApi
{
    /**
     * REST namespace.
     */
    private const NAMESPACE = 'hp-rw/v1';

    /**
     * Register the REST API.
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register routes.
     */
    public function registerRoutes(): void
    {
        // Get schema (public - for AI agents)
        register_rest_route(self::NAMESPACE, '/funnels/schema', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSchema'],
                'permission_callback' => '__return_true', // Public endpoint
            ],
        ]);

        // Export single funnel
        register_rest_route(self::NAMESPACE, '/funnels/export/(?P<slug>[a-z0-9-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'exportFunnel'],
                'permission_callback' => [$this, 'canExport'],
                'args' => [
                    'slug' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_title',
                    ],
                ],
            ],
        ]);

        // Export all funnels
        register_rest_route(self::NAMESPACE, '/funnels/export', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'exportAll'],
                'permission_callback' => [$this, 'canExport'],
                'args' => [
                    'active_only' => [
                        'type' => 'boolean',
                        'default' => false,
                    ],
                ],
            ],
        ]);

        // Import funnel
        register_rest_route(self::NAMESPACE, '/funnels/import', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'importFunnel'],
                'permission_callback' => [$this, 'canImport'],
                'args' => [
                    'data' => [
                        'required' => true,
                        'type' => 'object',
                        'description' => 'Funnel JSON data',
                    ],
                    'mode' => [
                        'type' => 'string',
                        'enum' => ['skip', 'update', 'create_new'],
                        'default' => 'skip',
                        'description' => 'How to handle existing funnels with same slug',
                    ],
                ],
            ],
        ]);

        // Validate funnel JSON (public - for AI agents to check before importing)
        register_rest_route(self::NAMESPACE, '/funnels/validate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'validateFunnel'],
                'permission_callback' => '__return_true', // Public endpoint
                'args' => [
                    'data' => [
                        'required' => true,
                        'type' => 'object',
                    ],
                ],
            ],
        ]);

        // List all funnels (basic info only)
        register_rest_route(self::NAMESPACE, '/funnels', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'listFunnels'],
                'permission_callback' => [$this, 'canExport'],
            ],
        ]);
    }

    /**
     * Check if user can export funnels.
     */
    public function canExport(): bool
    {
        return current_user_can('edit_posts');
    }

    /**
     * Check if user can import funnels.
     */
    public function canImport(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * GET /funnels/schema - Get JSON schema for AI agents.
     */
    public function getSchema(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data' => FunnelSchema::getSchemaResponse(),
        ]);
    }

    /**
     * GET /funnels/export/{slug} - Export single funnel.
     */
    public function exportFunnel(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $data = FunnelExporter::exportBySlug($slug);

        if (!$data) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Funnel not found',
            ], 404);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /funnels/export - Export all funnels.
     */
    public function exportAll(WP_REST_Request $request): WP_REST_Response
    {
        $activeOnly = $request->get_param('active_only');
        $funnels = FunnelExporter::exportAll($activeOnly);

        return new WP_REST_Response([
            'success' => true,
            'count' => count($funnels),
            'data' => $funnels,
        ]);
    }

    /**
     * POST /funnels/import - Import funnel from JSON.
     */
    public function importFunnel(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_param('data');
        $mode = $request->get_param('mode');

        // Map mode to importer constant
        $modeMap = [
            'skip' => FunnelImporter::MODE_SKIP,
            'update' => FunnelImporter::MODE_UPDATE,
            'create_new' => FunnelImporter::MODE_CREATE_NEW,
        ];
        $importMode = $modeMap[$mode] ?? FunnelImporter::MODE_SKIP;

        // Check if it's a multi-funnel import
        if (isset($data['funnels']) && is_array($data['funnels'])) {
            $results = FunnelImporter::importMultiple($data['funnels'], $importMode);
            
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($results as $result) {
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
                        $errors++;
                        break;
                }
            }

            return new WP_REST_Response([
                'success' => $errors === 0,
                'summary' => [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ],
                'results' => $results,
            ], $errors > 0 ? 207 : 200);
        }

        // Single funnel import
        $result = FunnelImporter::import($data, $importMode);

        $statusCode = $result['success'] ? 200 : 400;
        if ($result['result'] === FunnelImporter::RESULT_CREATED) {
            $statusCode = 201;
        }

        return new WP_REST_Response($result, $statusCode);
    }

    /**
     * POST /funnels/validate - Validate funnel JSON without importing.
     */
    public function validateFunnel(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_param('data');
        $validation = FunnelSchema::validate($data);

        // Also check if slug exists
        $slugExists = false;
        if (!empty($data['funnel']['slug'])) {
            $existing = FunnelConfigLoader::findPostBySlug($data['funnel']['slug']);
            $slugExists = $existing !== null;
        }

        return new WP_REST_Response([
            'valid' => $validation['valid'],
            'errors' => $validation['errors'],
            'slug_exists' => $slugExists,
            'message' => $validation['valid'] 
                ? 'JSON is valid' . ($slugExists ? ' (funnel with this slug already exists)' : '')
                : 'Validation failed',
        ]);
    }

    /**
     * GET /funnels - List all funnels with basic info.
     */
    public function listFunnels(WP_REST_Request $request): WP_REST_Response
    {
        $posts = FunnelConfigLoader::getAllPosts();
        $funnels = [];

        foreach ($posts as $post) {
            $slug = get_field('funnel_slug', $post->ID) ?: $post->post_name;
            $status = get_field('funnel_status', $post->ID) ?: 'active';

            $funnels[] = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'slug' => $slug,
                'status' => $status,
                'edit_url' => get_edit_post_link($post->ID, 'raw'),
                'export_url' => rest_url(self::NAMESPACE . '/funnels/export/' . $slug),
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'count' => count($funnels),
            'funnels' => $funnels,
        ]);
    }
}














