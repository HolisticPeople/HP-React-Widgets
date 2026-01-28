<?php
namespace HP_RW\Admin;

use HP_RW\Services\EconomicsService;
use HP_RW\Services\FunnelConfigLoader;
use HP_RW\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Economics Dashboard admin page.
 * Provides overview of profitability across all funnels and their offers.
 */
class EconomicsDashboard
{
    /**
     * Initialize the economics dashboard.
     */
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    /**
     * Add admin menu.
     */
    public static function addAdminMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=hp-funnel',
            __('Economics Dashboard', 'hp-react-widgets'),
            __('Economics', 'hp-react-widgets'),
            'manage_woocommerce',
            'hp-funnel-economics',
            [self::class, 'renderPage']
        );
    }

    /**
     * Enqueue assets for the dashboard.
     */
    public static function enqueueAssets(string $hook): void
    {
        if ($hook !== 'hp-funnel_page_hp-funnel-economics') {
            return;
        }

        wp_enqueue_style(
            'hp-economics-dashboard',
            plugins_url('assets/css/economics-dashboard.css', dirname(__DIR__, 2) . '/hp-react-widgets.php'),
            [],
            HP_RW_VERSION
        );
    }

    /**
     * Get all funnels with their economics data.
     */
    private static function getFunnelEconomics(): array
    {
        $funnels = get_posts([
            'post_type' => Plugin::FUNNEL_POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        $results = [];
        $guidelines = EconomicsService::getGuidelines();

        foreach ($funnels as $funnel) {
            $config = FunnelConfigLoader::get($funnel->ID);
            if (!$config) {
                continue;
            }

            $funnelData = [
                'id' => $funnel->ID,
                'title' => $funnel->post_title,
                'slug' => $funnel->post_name, // Single source of truth: WordPress permalink
                'status' => 'healthy',
                'offers' => [],
                'total_products' => 0,
                'avg_margin' => 0,
                'warnings' => [],
            ];

            // Extract offers from config
            $offers = $config['offers'] ?? [];

            $totalMargin = 0;
            $offerCount = 0;

            foreach ($offers as $offer) {
                $offerAnalysis = self::analyzeOffer($offer, $guidelines);
                $funnelData['offers'][] = $offerAnalysis;
                
                // Calculate total products in this funnel
                if ($offer['type'] === 'single') {
                    $funnelData['total_products'] += 1;
                } elseif ($offer['type'] === 'fixed_bundle') {
                    $funnelData['total_products'] += count($offer['bundleItems'] ?? []);
                } elseif ($offer['type'] === 'customizable_kit') {
                    $funnelData['total_products'] += count($offer['kitProducts'] ?? []);
                }

                if (isset($offerAnalysis['margin_percent'])) {
                    $totalMargin += $offerAnalysis['margin_percent'];
                    $offerCount++;
                }

                if (!$offerAnalysis['passes_guidelines']) {
                    $funnelData['status'] = 'warning';
                    
                    if (!$offerAnalysis['passes_margin']) {
                        $funnelData['warnings'][] = sprintf(
                            __('Offer "%s" fails margin: %.1f%% (min: %.1f%%)', 'hp-react-widgets'),
                            $offerAnalysis['name'],
                            $offerAnalysis['margin_percent'],
                            $guidelines['profit_requirements']['min_profit_percent']
                        );
                    }
                    
                    if (!$offerAnalysis['passes_profit']) {
                        $funnelData['warnings'][] = sprintf(
                            __('Offer "%s" fails profit: $%s (min: $%s)', 'hp-react-widgets'),
                            $offerAnalysis['name'],
                            number_format($offerAnalysis['profit'], 2),
                            number_format($guidelines['profit_requirements']['min_profit_dollars'], 2)
                        );
                    }
                }
            }

            $funnelData['avg_margin'] = $offerCount > 0 ? $totalMargin / $offerCount : 0;
            $results[] = $funnelData;
        }

        return $results;
    }

    /**
     * Analyze a single offer for economics.
     */
    private static function analyzeOffer(array $offer, array $guidelines): array
    {
        $offerType = $offer['type'] ?? 'single';
        $price = floatval($offer['calculatedPrice'] ?? $offer['offerPrice'] ?? 0);
        $originalPrice = floatval($offer['originalPrice'] ?? $price);
        $name = $offer['name'] ?? __('Unnamed Offer', 'hp-react-widgets');

        // Calculate cost
        $totalCost = 0;
        $items = [];

        if ($offerType === 'single') {
            $sku = $offer['productSku'] ?? '';
            $qty = intval($offer['quantity'] ?? 1);
            $economics = \HP_RW\Services\ProductCatalogService::getProductEconomics($sku);
            $cost = $economics ? $economics['cost'] : 0;
            $totalCost = $cost * $qty;
            $items[] = ['sku' => $sku, 'quantity' => $qty, 'cost' => $cost];
        } elseif ($offerType === 'fixed_bundle') {
            $bundleItems = $offer['bundleItems'] ?? [];
            foreach ($bundleItems as $bundleItem) {
                $sku = $bundleItem['sku'] ?? '';
                $qty = intval($bundleItem['qty'] ?? 1);
                $economics = \HP_RW\Services\ProductCatalogService::getProductEconomics($sku);
                $cost = $economics ? $economics['cost'] : 0;
                $totalCost += $cost * $qty;
                $items[] = ['sku' => $sku, 'quantity' => $qty, 'cost' => $cost];
            }
        } elseif ($offerType === 'customizable_kit') {
            $kitProducts = $offer['kitProducts'] ?? [];
            foreach ($kitProducts as $kitProduct) {
                // Use default qty for kit analysis
                $sku = $kitProduct['sku'] ?? '';
                $qty = intval($kitProduct['qty'] ?? 0);
                if ($qty <= 0) continue;
                
                $economics = \HP_RW\Services\ProductCatalogService::getProductEconomics($sku);
                $cost = $economics ? $economics['cost'] : 0;
                $totalCost += $cost * $qty;
                $items[] = ['sku' => $sku, 'quantity' => $qty, 'cost' => $cost];
            }
        }

        $profit = $price - $totalCost;
        $marginPercent = $price > 0 ? ($profit / $price) * 100 : 0;
        
        $minMargin = $guidelines['profit_requirements']['min_profit_percent'];
        $minProfit = $guidelines['profit_requirements']['min_profit_dollars'];

        $passesMargin = $marginPercent >= $minMargin;
        $passesProfit = $profit >= $minProfit;
        $passesGuidelines = $passesMargin && $passesProfit;

        return [
            'name' => $name,
            'type' => $offerType,
            'price' => $price,
            'original_price' => $originalPrice,
            'discount_percent' => $originalPrice > 0 ? (($originalPrice - $price) / $originalPrice) * 100 : 0,
            'total_cost' => $totalCost,
            'profit' => $profit,
            'margin_percent' => $marginPercent,
            'passes_margin' => $passesMargin,
            'passes_profit' => $passesProfit,
            'passes_guidelines' => $passesGuidelines,
            'items' => $items,
        ];
    }

    /**
     * Get summary statistics.
     */
    private static function getSummaryStats(array $funnelsData): array
    {
        $totalFunnels = count($funnelsData);
        $healthyFunnels = 0;
        $warningFunnels = 0;
        $totalOffers = 0;
        $passingOffers = 0;
        $totalProfit = 0;
        $totalRevenue = 0;

        foreach ($funnelsData as $funnel) {
            if ($funnel['status'] === 'healthy') {
                $healthyFunnels++;
            } else {
                $warningFunnels++;
            }

            foreach ($funnel['offers'] as $offer) {
                $totalOffers++;
                if ($offer['passes_guidelines']) {
                    $passingOffers++;
                }
                $totalProfit += $offer['profit'];
                $totalRevenue += $offer['price'];
            }
        }

        return [
            'total_funnels' => $totalFunnels,
            'healthy_funnels' => $healthyFunnels,
            'warning_funnels' => $warningFunnels,
            'total_offers' => $totalOffers,
            'passing_offers' => $passingOffers,
            'failing_offers' => $totalOffers - $passingOffers,
            'avg_margin' => $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0,
            'total_potential_profit' => $totalProfit,
        ];
    }

    /**
     * Render the economics dashboard page.
     */
    public static function renderPage(): void
    {
        $guidelines = EconomicsService::getGuidelines();
        $funnelsData = self::getFunnelEconomics();
        $stats = self::getSummaryStats($funnelsData);
        ?>
        <div class="wrap hp-economics-dashboard">
            <h1><?php esc_html_e('Economics Dashboard', 'hp-react-widgets'); ?></h1>
            
            <p class="description">
                <?php esc_html_e('Overview of profitability across all funnels and offers.', 'hp-react-widgets'); ?>
            </p>

            <!-- Current Guidelines -->
            <div class="hp-econ-card hp-econ-guidelines">
                <h3><?php esc_html_e('Current Guidelines', 'hp-react-widgets'); ?></h3>
                <div class="hp-econ-guidelines-grid">
                    <div class="hp-econ-guideline">
                        <span class="label"><?php esc_html_e('Min Margin', 'hp-react-widgets'); ?></span>
                        <span class="value"><?php echo esc_html($guidelines['profit_requirements']['min_profit_percent']); ?>%</span>
                    </div>
                    <div class="hp-econ-guideline">
                        <span class="label"><?php esc_html_e('Min Profit', 'hp-react-widgets'); ?></span>
                        <span class="value">$<?php echo esc_html(number_format($guidelines['profit_requirements']['min_profit_dollars'], 2)); ?></span>
                    </div>
                </div>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=hp-funnel&page=hp-funnel-ai-settings')); ?>" class="button">
                    <?php esc_html_e('Edit Guidelines', 'hp-react-widgets'); ?>
                </a>
            </div>

            <!-- Summary Stats -->
            <div class="hp-econ-stats-grid">
                <div class="hp-econ-stat-card">
                    <span class="hp-econ-stat-value"><?php echo esc_html($stats['total_funnels']); ?></span>
                    <span class="hp-econ-stat-label"><?php esc_html_e('Total Funnels', 'hp-react-widgets'); ?></span>
                </div>
                <div class="hp-econ-stat-card <?php echo $stats['healthy_funnels'] === $stats['total_funnels'] ? 'success' : ''; ?>">
                    <span class="hp-econ-stat-value"><?php echo esc_html($stats['healthy_funnels']); ?></span>
                    <span class="hp-econ-stat-label"><?php esc_html_e('Healthy Funnels', 'hp-react-widgets'); ?></span>
                </div>
                <div class="hp-econ-stat-card <?php echo $stats['warning_funnels'] > 0 ? 'warning' : ''; ?>">
                    <span class="hp-econ-stat-value"><?php echo esc_html($stats['warning_funnels']); ?></span>
                    <span class="hp-econ-stat-label"><?php esc_html_e('Need Attention', 'hp-react-widgets'); ?></span>
                </div>
                <div class="hp-econ-stat-card">
                    <span class="hp-econ-stat-value"><?php echo esc_html($stats['total_offers']); ?></span>
                    <span class="hp-econ-stat-label"><?php esc_html_e('Total Offers', 'hp-react-widgets'); ?></span>
                </div>
                <div class="hp-econ-stat-card <?php echo $stats['failing_offers'] > 0 ? 'warning' : 'success'; ?>">
                    <span class="hp-econ-stat-value"><?php echo esc_html($stats['passing_offers']); ?>/<?php echo esc_html($stats['total_offers']); ?></span>
                    <span class="hp-econ-stat-label"><?php esc_html_e('Passing Guidelines', 'hp-react-widgets'); ?></span>
                </div>
                <div class="hp-econ-stat-card">
                    <span class="hp-econ-stat-value"><?php echo esc_html(number_format($stats['avg_margin'], 1)); ?>%</span>
                    <span class="hp-econ-stat-label"><?php esc_html_e('Avg Margin', 'hp-react-widgets'); ?></span>
                </div>
            </div>

            <!-- Funnel Details -->
            <h2><?php esc_html_e('Funnel Economics', 'hp-react-widgets'); ?></h2>

            <?php if (empty($funnelsData)): ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e('No published funnels found. Create a funnel to see economics data.', 'hp-react-widgets'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($funnelsData as $funnel): ?>
                    <div class="hp-econ-funnel-card <?php echo esc_attr($funnel['status']); ?>">
                        <div class="hp-econ-funnel-header">
                            <h3>
                                <span class="hp-econ-status-indicator"></span>
                                <?php echo esc_html($funnel['title']); ?>
                                <code><?php echo esc_html($funnel['slug']); ?></code>
                            </h3>
                            <div class="hp-econ-funnel-meta">
                                <span class="hp-econ-avg-margin">
                                    <?php esc_html_e('Avg Margin:', 'hp-react-widgets'); ?>
                                    <strong><?php echo esc_html(number_format($funnel['avg_margin'], 1)); ?>%</strong>
                                </span>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . $funnel['id'] . '&action=edit')); ?>" class="button button-small">
                                    <?php esc_html_e('Edit Funnel', 'hp-react-widgets'); ?>
                                </a>
                            </div>
                        </div>

                        <?php if (!empty($funnel['warnings'])): ?>
                            <div class="hp-econ-warnings">
                                <?php foreach ($funnel['warnings'] as $warning): ?>
                                    <div class="hp-econ-warning">⚠️ <?php echo esc_html($warning); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($funnel['offers'])): ?>
                            <table class="hp-econ-offers-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Offer', 'hp-react-widgets'); ?></th>
                                        <th><?php esc_html_e('Type', 'hp-react-widgets'); ?></th>
                                        <th><?php esc_html_e('Price', 'hp-react-widgets'); ?></th>
                                        <th><?php esc_html_e('Cost', 'hp-react-widgets'); ?></th>
                                        <th><?php esc_html_e('Profit', 'hp-react-widgets'); ?></th>
                                        <th><?php esc_html_e('Margin', 'hp-react-widgets'); ?></th>
                                        <th><?php esc_html_e('Status', 'hp-react-widgets'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($funnel['offers'] as $offer): ?>
                                        <tr class="<?php echo $offer['passes_guidelines'] ? 'passing' : 'failing'; ?>">
                                            <td>
                                                <strong><?php echo esc_html($offer['name']); ?></strong>
                                                <?php if ($offer['discount_percent'] > 0): ?>
                                                    <span class="hp-econ-discount">
                                                        -<?php echo esc_html(number_format($offer['discount_percent'], 0)); ?>%
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="hp-econ-type-badge"><?php echo esc_html(ucwords(str_replace('_', ' ', $offer['type']))); ?></span></td>
                                            <td>$<?php echo esc_html(number_format($offer['price'], 2)); ?></td>
                                            <td>$<?php echo esc_html(number_format($offer['total_cost'], 2)); ?></td>
                                            <td class="<?php echo $offer['profit'] >= 0 ? 'positive' : 'negative'; ?>">
                                                $<?php echo esc_html(number_format($offer['profit'], 2)); ?>
                                            </td>
                                            <td class="<?php echo $offer['passes_margin'] ? 'positive' : 'negative'; ?>">
                                                <?php echo esc_html(number_format($offer['margin_percent'], 1)); ?>%
                                            </td>
                                            <td>
                                                <?php if ($offer['passes_guidelines']): ?>
                                                    <span class="hp-econ-status-pass">✓ <?php esc_html_e('Pass', 'hp-react-widgets'); ?></span>
                                                <?php else: ?>
                                                    <span class="hp-econ-status-fail">✗ <?php esc_html_e('Fail', 'hp-react-widgets'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="hp-econ-no-offers"><?php esc_html_e('No offers configured in this funnel.', 'hp-react-widgets'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <style>
            .hp-economics-dashboard { max-width: 1200px; }
            
            .hp-econ-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .hp-econ-card h3 { margin-top: 0; }
            
            .hp-econ-guidelines-grid {
                display: flex;
                gap: 30px;
                margin-bottom: 15px;
            }
            
            .hp-econ-guideline {
                display: flex;
                flex-direction: column;
            }
            
            .hp-econ-guideline .label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
            }
            
            .hp-econ-guideline .value {
                font-size: 24px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .hp-econ-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin-bottom: 30px;
            }
            
            .hp-econ-stat-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
            }
            
            .hp-econ-stat-card.success {
                border-color: #00a32a;
                background: #f0fff4;
            }
            
            .hp-econ-stat-card.warning {
                border-color: #dba617;
                background: #fffbeb;
            }
            
            .hp-econ-stat-value {
                display: block;
                font-size: 28px;
                font-weight: 700;
                color: #1d2327;
            }
            
            .hp-econ-stat-label {
                display: block;
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                margin-top: 5px;
            }
            
            .hp-econ-funnel-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                margin-bottom: 20px;
                overflow: hidden;
            }
            
            .hp-econ-funnel-card.warning {
                border-color: #dba617;
            }
            
            .hp-econ-funnel-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
                background: #f6f7f7;
                border-bottom: 1px solid #c3c4c7;
            }
            
            .hp-econ-funnel-header h3 {
                margin: 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .hp-econ-funnel-header code {
                font-size: 12px;
                background: #e0e0e0;
                padding: 2px 8px;
                border-radius: 3px;
            }
            
            .hp-econ-status-indicator {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: #00a32a;
            }
            
            .hp-econ-funnel-card.warning .hp-econ-status-indicator {
                background: #dba617;
            }
            
            .hp-econ-funnel-meta {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .hp-econ-warnings {
                padding: 10px 20px;
                background: #fffbeb;
                border-bottom: 1px solid #dba617;
            }
            
            .hp-econ-warning {
                color: #92400e;
                font-size: 13px;
                padding: 5px 0;
            }
            
            .hp-econ-offers-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .hp-econ-offers-table th,
            .hp-econ-offers-table td {
                padding: 12px 20px;
                text-align: left;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .hp-econ-offers-table th {
                background: #f9f9f9;
                font-weight: 600;
                font-size: 12px;
                text-transform: uppercase;
                color: #666;
            }
            
            .hp-econ-offers-table tr.failing {
                background: #fff5f5;
            }
            
            .hp-econ-offers-table tr.passing:hover,
            .hp-econ-offers-table tr.failing:hover {
                background: #f0f6fc;
            }
            
            .hp-econ-discount {
                display: inline-block;
                background: #3b82f6;
                color: #fff;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                margin-left: 5px;
            }
            
            .hp-econ-type-badge {
                display: inline-block;
                background: #e0e7ff;
                color: #3730a3;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
            }
            
            .hp-econ-offers-table .positive { color: #00a32a; }
            .hp-econ-offers-table .negative { color: #dc2626; }
            
            .hp-econ-status-pass {
                color: #00a32a;
                font-weight: 600;
            }
            
            .hp-econ-status-fail {
                color: #dc2626;
                font-weight: 600;
            }
            
            .hp-econ-no-offers {
                padding: 20px;
                color: #666;
                font-style: italic;
            }
        </style>
        <?php
    }
}
