<?php
namespace HP_RW\Rest;

use HP_RW\Services\FunnelSystemExplainer;
use HP_RW\Services\FunnelSchema;
use HP_RW\Services\FunnelConfigLoader;
use HP_RW\Services\FunnelExporter;
use HP_RW\Services\FunnelImporter;
use HP_RW\Services\FunnelVersionControl;
use HP_RW\Services\ProductCatalogService;
use HP_RW\Services\ProtocolKitBuilder;
use HP_RW\Services\EconomicsService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API endpoints for AI funnel operations.
 */
class AiFunnelApi
{
    /**
     * API namespace.
     */
    private const NAMESPACE = 'hp-rw/v1';

    /**
     * Register API endpoints.
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST routes.
     */
    public function registerRoutes(): void
    {
        // System explanation endpoint
        register_rest_route(self::NAMESPACE, '/ai/system/explain', [
            'methods' => 'GET',
            'callback' => [$this, 'getSystemExplanation'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // Schema with AI hints
        register_rest_route(self::NAMESPACE, '/ai/schema', [
            'methods' => 'GET',
            'callback' => [$this, 'getSchemaWithHints'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // Styling schema and presets
        register_rest_route(self::NAMESPACE, '/ai/styling/schema', [
            'methods' => 'GET',
            'callback' => [$this, 'getStylingSchema'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // Funnel CRUD
        register_rest_route(self::NAMESPACE, '/ai/funnels', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listFunnels'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createFunnel'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/ai/funnels/(?P<slug>[a-z0-9-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getFunnel'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateFunnel'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteFunnel'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
        ]);

        // Update specific sections
        register_rest_route(self::NAMESPACE, '/ai/funnels/(?P<slug>[a-z0-9-]+)/sections', [
            'methods' => 'POST',
            'callback' => [$this, 'updateFunnelSections'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // Get specific section
        register_rest_route(self::NAMESPACE, '/ai/funnels/(?P<slug>[a-z0-9-]+)/section/(?P<section>[a-z_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getFunnelSection'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // Version control endpoints
        register_rest_route(self::NAMESPACE, '/ai/funnels/(?P<slug>[a-z0-9-]+)/versions', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'listVersions'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createVersion'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/ai/funnels/(?P<slug>[a-z0-9-]+)/versions/(?P<version_id>[a-z0-9_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getVersion'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/ai/funnels/(?P<slug>[a-z0-9-]+)/versions/(?P<version_id>[a-z0-9_]+)/restore', [
            'methods' => 'POST',
            'callback' => [$this, 'restoreVersion'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/ai/funnels/(?P<slug>[a-z0-9-]+)/versions/diff', [
            'methods' => 'GET',
            'callback' => [$this, 'diffVersions'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // Product endpoints
        register_rest_route(self::NAMESPACE, '/ai/products', [
            'methods' => 'GET',
            'callback' => [$this, 'searchProducts'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/ai/products/(?P<sku>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getProduct'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/ai/products/calculate-supply', [
            'methods' => 'POST',
            'callback' => [$this, 'calculateSupply'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/ai/products/categories', [
            'methods' => 'GET',
            'callback' => [$this, 'getProductCategories'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // Protocol kit builder
        register_rest_route(self::NAMESPACE, '/ai/protocols/build-kit', [
            'methods' => 'POST',
            'callback' => [$this, 'buildProtocolKit'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // Economics endpoints
        register_rest_route(self::NAMESPACE, '/ai/economics/calculate', [
            'methods' => 'POST',
            'callback' => [$this, 'calculateEconomics'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/ai/economics/validate-offer', [
            'methods' => 'POST',
            'callback' => [$this, 'validateOffer'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/ai/economics/guidelines', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getEconomicsGuidelines'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateEconomicsGuidelines'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/ai/economics/shipping-strategy', [
            'methods' => 'POST',
            'callback' => [$this, 'getShippingStrategy'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // AI Activity log
        register_rest_route(self::NAMESPACE, '/ai/activity', [
            'methods' => 'GET',
            'callback' => [$this, 'getAiActivity'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    /**
     * Check admin permission.
     */
    public function checkAdminPermission(): bool
    {
        return current_user_can('manage_woocommerce');
    }

    // =========================================================================
    // System & Schema Endpoints
    // =========================================================================

    /**
     * GET /ai/system/explain - Complete system documentation
     */
    public function getSystemExplanation(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(FunnelSystemExplainer::getSystemExplanation());
    }

    /**
     * GET /ai/schema - Schema with AI generation hints
     */
    public function getSchemaWithHints(WP_REST_Request $request): WP_REST_Response
    {
        $response = FunnelSchema::getSchemaResponse();
        
        // Add AI generation hints
        $response['ai_generation_hints'] = $this->getAiGenerationHints();
        
        return new WP_REST_Response($response);
    }

    /**
     * GET /ai/styling/schema - Styling schema and presets
     */
    public function getStylingSchema(WP_REST_Request $request): WP_REST_Response
    {
        $explanation = FunnelSystemExplainer::getSystemExplanation();
        
        return new WP_REST_Response([
            'color_fields' => $explanation['styling']['key_colors'],
            'theme_presets' => $explanation['styling']['theme_presets'],
            'recommendations' => $explanation['styling']['recommendations'],
        ]);
    }

    // =========================================================================
    // Funnel CRUD Endpoints
    // =========================================================================

    /**
     * GET /ai/funnels - List all funnels
     */
    public function listFunnels(WP_REST_Request $request): WP_REST_Response
    {
        $posts = get_posts([
            'post_type' => 'hp-funnel',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => -1,
        ]);

        $funnels = [];
        foreach ($posts as $post) {
            $slug = get_field('funnel_slug', $post->ID) ?: $post->post_name;
            $status = get_field('funnel_status', $post->ID) ?: 'active';
            
            // Get version count
            $versions = FunnelVersionControl::getVersions($post->ID);
            
            $funnels[] = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'slug' => $slug,
                'status' => $status,
                'versions_count' => $versions['versions_count'],
                'modified' => $post->post_modified_gmt,
                'urls' => [
                    'landing' => "/express-shop/{$slug}/",
                    'checkout' => "/express-shop/{$slug}/checkout/",
                    'edit' => admin_url("post.php?post={$post->ID}&action=edit"),
                ],
            ];
        }

        return new WP_REST_Response([
            'count' => count($funnels),
            'funnels' => $funnels,
        ]);
    }

    /**
     * GET /ai/funnels/{slug} - Get complete funnel JSON
     */
    public function getFunnel(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $post = FunnelConfigLoader::findPostBySlug($slug);

        if (!$post) {
            return new WP_REST_Response(['error' => 'Funnel not found'], 404);
        }

        $funnelData = FunnelExporter::exportFunnel($post->ID);
        
        if (!$funnelData) {
            return new WP_REST_Response(['error' => 'Failed to export funnel'], 500);
        }

        // Add metadata
        $funnelData['metadata'] = [
            'created' => $post->post_date_gmt,
            'last_modified' => $post->post_modified_gmt,
            'sections_used' => $this->getUsedSections($funnelData),
            'offer_count' => count($funnelData['offers'] ?? []),
            'has_upsell' => !empty($funnelData['thankyou']['show_upsell']),
        ];

        $funnelData['urls'] = [
            'landing' => "/express-shop/{$slug}/",
            'checkout' => "/express-shop/{$slug}/checkout/",
            'thankyou' => "/express-shop/{$slug}/thankyou/",
            'edit' => admin_url("post.php?post={$post->ID}&action=edit"),
        ];

        return new WP_REST_Response($funnelData);
    }

    /**
     * POST /ai/funnels - Create new funnel
     */
    public function createFunnel(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_json_params();
        
        // Validate
        $validation = FunnelSchema::validate($data);
        if (!$validation['valid']) {
            return new WP_REST_Response([
                'error' => 'Validation failed',
                'errors' => $validation['errors'],
            ], 400);
        }

        // Import as new funnel
        $result = FunnelImporter::importFunnel($data, 'new');

        if (!$result['success']) {
            return new WP_REST_Response([
                'error' => $result['message'],
            ], 400);
        }

        // Log AI activity
        FunnelVersionControl::logAiActivity($result['post_id'], 'funnel_created', 'Created via AI API', [
            'funnel_name' => $data['funnel']['name'] ?? '',
        ]);

        // Create initial version
        FunnelVersionControl::createVersion($result['post_id'], 'Initial creation', 'ai_agent');

        $slug = $data['funnel']['slug'];

        return new WP_REST_Response([
            'success' => true,
            'post_id' => $result['post_id'],
            'slug' => $slug,
            'urls' => [
                'landing' => "/express-shop/{$slug}/",
                'checkout' => "/express-shop/{$slug}/checkout/",
                'edit' => admin_url("post.php?post={$result['post_id']}&action=edit"),
            ],
        ], 201);
    }

    /**
     * PUT /ai/funnels/{slug} - Update funnel
     */
    public function updateFunnel(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $data = $request->get_json_params();
        
        $post = FunnelConfigLoader::findPostBySlug($slug);
        if (!$post) {
            return new WP_REST_Response(['error' => 'Funnel not found'], 404);
        }

        // Auto-backup before update
        $settings = FunnelVersionControl::getSettings();
        if ($settings['auto_backup_on_update']) {
            FunnelVersionControl::createVersion($post->ID, 'Before AI update', 'ai_agent');
        }

        // Import as update
        $result = FunnelImporter::importFunnel($data, 'update', $post->ID);

        if (!$result['success']) {
            return new WP_REST_Response([
                'error' => $result['message'],
            ], 400);
        }

        // Log AI activity
        FunnelVersionControl::logAiActivity($post->ID, 'funnel_updated', 'Updated via AI API');

        return new WP_REST_Response([
            'success' => true,
            'post_id' => $post->ID,
            'message' => 'Funnel updated successfully',
        ]);
    }

    /**
     * DELETE /ai/funnels/{slug} - Delete funnel
     */
    public function deleteFunnel(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $post = FunnelConfigLoader::findPostBySlug($slug);

        if (!$post) {
            return new WP_REST_Response(['error' => 'Funnel not found'], 404);
        }

        $deleted = wp_delete_post($post->ID, true);

        if (!$deleted) {
            return new WP_REST_Response(['error' => 'Failed to delete funnel'], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => "Funnel '{$slug}' deleted",
        ]);
    }

    /**
     * POST /ai/funnels/{slug}/sections - Update specific sections
     */
    public function updateFunnelSections(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $sections = $request->get_json_params();
        
        $post = FunnelConfigLoader::findPostBySlug($slug);
        if (!$post) {
            return new WP_REST_Response(['error' => 'Funnel not found'], 404);
        }

        // Get current funnel data
        $currentData = FunnelExporter::exportFunnel($post->ID);
        if (!$currentData) {
            return new WP_REST_Response(['error' => 'Failed to load funnel'], 500);
        }

        // Auto-backup
        $settings = FunnelVersionControl::getSettings();
        if ($settings['auto_backup_on_update']) {
            $changedSections = array_keys($sections);
            FunnelVersionControl::createVersion($post->ID, 'Before updating: ' . implode(', ', $changedSections), 'ai_agent');
        }

        // Merge sections
        foreach ($sections as $section => $sectionData) {
            $currentData[$section] = $sectionData;
        }

        // Import merged data
        $result = FunnelImporter::importFunnel($currentData, 'update', $post->ID);

        if (!$result['success']) {
            return new WP_REST_Response(['error' => $result['message']], 400);
        }

        // Log activity
        FunnelVersionControl::logAiActivity($post->ID, 'sections_updated', 'Updated sections: ' . implode(', ', array_keys($sections)));

        return new WP_REST_Response([
            'success' => true,
            'updated_sections' => array_keys($sections),
        ]);
    }

    /**
     * GET /ai/funnels/{slug}/section/{section} - Get specific section
     */
    public function getFunnelSection(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $section = $request->get_param('section');
        
        $post = FunnelConfigLoader::findPostBySlug($slug);
        if (!$post) {
            return new WP_REST_Response(['error' => 'Funnel not found'], 404);
        }

        $funnelData = FunnelExporter::exportFunnel($post->ID);
        
        if (!isset($funnelData[$section])) {
            return new WP_REST_Response(['error' => "Section '{$section}' not found"], 404);
        }

        return new WP_REST_Response([
            'section' => $section,
            'data' => $funnelData[$section],
        ]);
    }

    // =========================================================================
    // Version Control Endpoints
    // =========================================================================

    /**
     * GET /ai/funnels/{slug}/versions - List versions
     */
    public function listVersions(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $post = FunnelConfigLoader::findPostBySlug($slug);

        if (!$post) {
            return new WP_REST_Response(['error' => 'Funnel not found'], 404);
        }

        $versions = FunnelVersionControl::getVersions($post->ID);
        $versions['funnel_slug'] = $slug;

        return new WP_REST_Response($versions);
    }

    /**
     * POST /ai/funnels/{slug}/versions - Create backup
     */
    public function createVersion(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $params = $request->get_json_params();
        
        $post = FunnelConfigLoader::findPostBySlug($slug);
        if (!$post) {
            return new WP_REST_Response(['error' => 'Funnel not found'], 404);
        }

        $description = $params['description'] ?? 'Manual backup';
        $createdBy = $params['created_by'] ?? 'ai_agent';

        $versionId = FunnelVersionControl::createVersion($post->ID, $description, $createdBy);

        if (!$versionId) {
            return new WP_REST_Response(['error' => 'Failed to create backup'], 500);
        }

        $versions = FunnelVersionControl::getVersions($post->ID);

        return new WP_REST_Response([
            'success' => true,
            'version_id' => $versionId,
            'message' => 'Backup created',
            'versions_count' => $versions['versions_count'],
        ], 201);
    }

    /**
     * GET /ai/funnels/{slug}/versions/{version_id} - Get version snapshot
     */
    public function getVersion(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $versionId = $request->get_param('version_id');
        
        $post = FunnelConfigLoader::findPostBySlug($slug);
        if (!$post) {
            return new WP_REST_Response(['error' => 'Funnel not found'], 404);
        }

        $snapshot = FunnelVersionControl::getVersionSnapshot($post->ID, $versionId);

        if (!$snapshot) {
            return new WP_REST_Response(['error' => 'Version not found'], 404);
        }

        return new WP_REST_Response([
            'version_id' => $versionId,
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * POST /ai/funnels/{slug}/versions/{version_id}/restore - Restore version
     */
    public function restoreVersion(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $versionId = $request->get_param('version_id');
        $params = $request->get_json_params();
        
        $post = FunnelConfigLoader::findPostBySlug($slug);
        if (!$post) {
            return new WP_REST_Response(['error' => 'Funnel not found'], 404);
        }

        $backupCurrent = $params['backup_current'] ?? true;

        $result = FunnelVersionControl::restoreVersion($post->ID, $versionId, $backupCurrent);

        if (!$result['success']) {
            return new WP_REST_Response(['error' => $result['error']], 400);
        }

        // Log activity
        FunnelVersionControl::logAiActivity($post->ID, 'version_restored', "Restored to {$versionId}");

        return new WP_REST_Response($result);
    }

    /**
     * GET /ai/funnels/{slug}/versions/diff - Compare versions
     */
    public function diffVersions(WP_REST_Request $request): WP_REST_Response
    {
        $slug = $request->get_param('slug');
        $fromId = $request->get_param('from');
        $toId = $request->get_param('to');
        
        $post = FunnelConfigLoader::findPostBySlug($slug);
        if (!$post) {
            return new WP_REST_Response(['error' => 'Funnel not found'], 404);
        }

        if (!$fromId || !$toId) {
            return new WP_REST_Response(['error' => 'Both from and to version IDs required'], 400);
        }

        $diff = FunnelVersionControl::diffVersions($post->ID, $fromId, $toId);

        return new WP_REST_Response($diff);
    }

    // =========================================================================
    // Product Endpoints
    // =========================================================================

    /**
     * GET /ai/products - Search products
     */
    public function searchProducts(WP_REST_Request $request): WP_REST_Response
    {
        $filters = [
            'category' => $request->get_param('category'),
            'search' => $request->get_param('search'),
            'sku' => $request->get_param('sku'),
            'tag' => $request->get_param('tag'),
            'has_serving_info' => $request->get_param('has_serving_info'),
            'limit' => $request->get_param('limit') ?: 50,
        ];

        $includeEconomics = $request->get_param('include_economics');

        if ($includeEconomics) {
            $products = ProductCatalogService::getProductsWithEconomics($filters);
        } else {
            $products = ProductCatalogService::searchProducts($filters);
        }

        return new WP_REST_Response([
            'count' => count($products),
            'products' => $products,
        ]);
    }

    /**
     * GET /ai/products/{sku} - Get product details
     */
    public function getProduct(WP_REST_Request $request): WP_REST_Response
    {
        $sku = $request->get_param('sku');
        
        $product = ProductCatalogService::getProductDetails($sku);
        
        if (!$product) {
            return new WP_REST_Response(['error' => 'Product not found'], 404);
        }

        $economics = ProductCatalogService::getProductEconomics($sku);

        return new WP_REST_Response(array_merge($product, [
            'economics' => $economics,
        ]));
    }

    /**
     * POST /ai/products/calculate-supply - Calculate supply needs
     */
    public function calculateSupply(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        $sku = $params['sku'] ?? '';
        $days = $params['days'] ?? 30;
        $servingsPerDay = $params['servings_per_day'] ?? 1;

        if (empty($sku)) {
            return new WP_REST_Response(['error' => 'SKU is required'], 400);
        }

        $result = ProductCatalogService::calculateSupply($sku, $days, $servingsPerDay);

        if (!$result) {
            return new WP_REST_Response(['error' => 'Product not found'], 404);
        }

        return new WP_REST_Response($result);
    }

    /**
     * GET /ai/products/categories - Get product categories
     */
    public function getProductCategories(WP_REST_Request $request): WP_REST_Response
    {
        $categories = ProductCatalogService::getCategories();

        return new WP_REST_Response([
            'count' => count($categories),
            'categories' => $categories,
        ]);
    }

    // =========================================================================
    // Protocol Kit Builder Endpoints
    // =========================================================================

    /**
     * POST /ai/protocols/build-kit - Build kit from protocol
     */
    public function buildProtocolKit(WP_REST_Request $request): WP_REST_Response
    {
        $protocol = $request->get_json_params();

        if (empty($protocol['supplements'])) {
            return new WP_REST_Response(['error' => 'Protocol must include supplements array'], 400);
        }

        $result = ProtocolKitBuilder::buildKit($protocol);

        if (isset($result['error'])) {
            return new WP_REST_Response($result, 400);
        }

        return new WP_REST_Response($result);
    }

    // =========================================================================
    // Economics Endpoints
    // =========================================================================

    /**
     * POST /ai/economics/calculate - Calculate offer profitability
     */
    public function calculateEconomics(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        $offer = $params['offer'] ?? [];
        $items = $offer['items'] ?? [];
        $proposedPrice = $offer['proposed_price'] ?? 0;
        $shippingScenario = $params['shipping_scenario'] ?? 'domestic';

        if (empty($items) || $proposedPrice <= 0) {
            return new WP_REST_Response(['error' => 'Items and proposed_price are required'], 400);
        }

        $result = EconomicsService::calculateOfferProfitability($items, $proposedPrice, $shippingScenario);

        return new WP_REST_Response($result);
    }

    /**
     * POST /ai/economics/validate-offer - Validate offer against guidelines
     */
    public function validateOffer(WP_REST_Request $request): WP_REST_Response
    {
        $offer = $request->get_json_params();

        if (empty($offer['type'])) {
            return new WP_REST_Response(['error' => 'Offer type is required'], 400);
        }

        $result = EconomicsService::validateOffer($offer);

        return new WP_REST_Response($result);
    }

    /**
     * GET /ai/economics/guidelines - Get economic guidelines
     */
    public function getEconomicsGuidelines(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(EconomicsService::getGuidelines());
    }

    /**
     * PUT /ai/economics/guidelines - Update economic guidelines
     */
    public function updateEconomicsGuidelines(WP_REST_Request $request): WP_REST_Response
    {
        $guidelines = $request->get_json_params();
        
        $success = EconomicsService::saveGuidelines($guidelines);

        return new WP_REST_Response([
            'success' => $success,
            'guidelines' => EconomicsService::getGuidelines(),
        ]);
    }

    /**
     * POST /ai/economics/shipping-strategy - Get shipping strategy recommendation
     */
    public function getShippingStrategy(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        $profit = $params['offer_profit'] ?? 0;
        $destination = $params['destination'] ?? 'international';
        
        // Calculate shipping cost (estimate)
        $itemCount = $params['item_count'] ?? 1;
        $weight = $params['weight_oz'] ?? 16;
        
        $shippingCost = EconomicsService::calculateShippingCost($destination, $itemCount, $weight);
        $subsidyPercent = EconomicsService::getSubsidyPercent($profit);
        
        $weAbsorb = $shippingCost * ($subsidyPercent / 100);
        $customerPays = $shippingCost - $weAbsorb;
        $effectiveProfit = $profit - $weAbsorb;

        $guidelines = EconomicsService::getGuidelines();
        $minProfit = $guidelines['profit_requirements']['min_profit_dollars'];
        $stillMeetsGuidelines = $effectiveProfit >= $minProfit;

        return new WP_REST_Response([
            'calculated_shipping_cost' => round($shippingCost, 2),
            'profit_tier' => $profit >= 100 ? 'high' : ($profit >= 50 ? 'medium' : 'low'),
            'subsidy_percent' => $subsidyPercent,
            'recommendation' => [
                'customer_pays' => round($customerPays, 2),
                'we_absorb' => round($weAbsorb, 2),
                'effective_profit_after_shipping' => round($effectiveProfit, 2),
                'still_meets_guidelines' => $stillMeetsGuidelines,
            ],
            'alternatives' => [
                [
                    'strategy' => 'full_subsidy',
                    'customer_pays' => 0,
                    'we_absorb' => round($shippingCost, 2),
                    'effective_profit' => round($profit - $shippingCost, 2),
                    'note' => 'Maximum customer appeal',
                ],
                [
                    'strategy' => 'no_subsidy',
                    'customer_pays' => round($shippingCost, 2),
                    'we_absorb' => 0,
                    'effective_profit' => round($profit, 2),
                    'note' => 'Maximum margin, may reduce international conversions',
                ],
            ],
        ]);
    }

    // =========================================================================
    // AI Activity Endpoints
    // =========================================================================

    /**
     * GET /ai/activity - Get all AI activity
     */
    public function getAiActivity(WP_REST_Request $request): WP_REST_Response
    {
        $limit = $request->get_param('limit') ?: 50;
        
        $activities = FunnelVersionControl::getAllAiActivity($limit);

        return new WP_REST_Response([
            'count' => count($activities),
            'activities' => $activities,
        ]);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Get AI generation hints for content creation.
     */
    private function getAiGenerationHints(): array
    {
        return [
            'hero' => [
                'title' => [
                    'max_words' => 8,
                    'style' => 'action-oriented, benefit-focused',
                    'examples' => ['Transform Your Health Today', 'Unlock Your Natural Energy'],
                ],
                'subtitle' => [
                    'max_words' => 12,
                    'style' => 'expands on title, adds specificity',
                ],
                'cta_text' => [
                    'max_words' => 6,
                    'style' => 'action verb, urgency',
                    'examples' => ['Get Your Special Offer Now', 'Start Your Journey Today'],
                ],
            ],
            'benefits' => [
                'count' => ['min' => 6, 'max' => 12],
                'item_style' => 'specific outcome, 5-15 words each',
                'derive_from' => 'article claims, research findings, product benefits',
            ],
            'science' => [
                'sections' => ['min' => 2, 'max' => 4],
                'style' => 'accessible scientific language, cite mechanisms',
                'derive_from' => 'research methodology, active ingredients, clinical references',
            ],
            'testimonials' => [
                'count' => ['min' => 3, 'max' => 6],
                'note' => 'AI should NOT generate fake testimonials - use placeholders or skip',
            ],
            'faq' => [
                'count' => ['min' => 4, 'max' => 8],
                'derive_from' => 'common objections, usage questions, ingredient queries',
            ],
        ];
    }

    /**
     * Get list of sections used in funnel data.
     */
    private function getUsedSections(array $funnelData): array
    {
        $allSections = ['header', 'hero', 'benefits', 'offers', 'features', 'authority', 'science', 'testimonials', 'faq', 'cta', 'checkout', 'thankyou', 'styling', 'footer'];
        $used = [];

        foreach ($allSections as $section) {
            if (!empty($funnelData[$section])) {
                $used[] = $section;
            }
        }

        return $used;
    }
}




















