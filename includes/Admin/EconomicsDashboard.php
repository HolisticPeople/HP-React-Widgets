<?php
namespace HP_RW\Admin;

use HP_RW\Services\EconomicsService;
use HP_RW\Services\FunnelExporter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Economics Dashboard admin page.
 */
class EconomicsDashboard
{
    /**
     * Initialize the dashboard.
     */
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'addAdminMenu']);
    }

    /**
     * Add admin menu.
     */
    public static function addAdminMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=hp-funnel',
            __('Economics', 'hp-react-widgets'),
            __('Economics', 'hp-react-widgets'),
            'manage_woocommerce',
            'hp-funnel-economics',
            [self::class, 'renderPage']
        );
    }

    /**
     * Render the dashboard page.
     */
    public static function renderPage(): void
    {
        $analysis = self::analyzeFunnelEconomics();
        $guidelines = EconomicsService::getGuidelines();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Funnel Economics Dashboard', 'hp-react-widgets'); ?></h1>
            
            <!-- Overview Cards -->
            <div class="hp-econ-cards">
                <div class="hp-econ-card">
                    <div class="hp-econ-card-value"><?php echo esc_html($analysis['total_funnels']); ?></div>
                    <div class="hp-econ-card-label"><?php esc_html_e('Total Funnels', 'hp-react-widgets'); ?></div>
                </div>
                <div class="hp-econ-card">
                    <div class="hp-econ-card-value"><?php echo esc_html(round($analysis['avg_margin'], 1)); ?>%</div>
                    <div class="hp-econ-card-label"><?php esc_html_e('Average Margin', 'hp-react-widgets'); ?></div>
                </div>
                <div class="hp-econ-card <?php echo $analysis['offers_below_threshold'] > 0 ? 'hp-econ-card-warning' : 'hp-econ-card-success'; ?>">
                    <div class="hp-econ-card-value"><?php echo esc_html($analysis['offers_below_threshold']); ?></div>
                    <div class="hp-econ-card-label"><?php esc_html_e('Offers Below Threshold', 'hp-react-widgets'); ?></div>
                </div>
                <div class="hp-econ-card">
                    <div class="hp-econ-card-value"><?php echo esc_html($analysis['total_offers']); ?></div>
                    <div class="hp-econ-card-label"><?php esc_html_e('Total Offers', 'hp-react-widgets'); ?></div>
                </div>
            </div>

            <!-- Current Guidelines -->
            <div class="hp-econ-section">
                <h2><?php esc_html_e('Current Guidelines', 'hp-react-widgets'); ?></h2>
                <p>
                    <?php esc_html_e('Minimum Profit:', 'hp-react-widgets'); ?>
                    <strong>
                        <?php echo esc_html($guidelines['profit_requirements']['min_profit_percent']); ?>%
                        <?php esc_html_e('or', 'hp-react-widgets'); ?>
                        $<?php echo esc_html($guidelines['profit_requirements']['min_profit_dollars']); ?>
                    </strong>
                    (<?php echo esc_html($guidelines['profit_requirements']['apply_rule']); ?>)
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=hp-funnel&page=hp-funnel-ai-settings')); ?>" class="button button-small" style="margin-left: 10px;">
                        <?php esc_html_e('Edit Guidelines', 'hp-react-widgets'); ?>
                    </a>
                </p>
            </div>

            <?php if (!empty($analysis['failing_offers'])): ?>
            <!-- Failing Offers -->
            <div class="hp-econ-section">
                <h2><?php esc_html_e('Offers Below Threshold', 'hp-react-widgets'); ?></h2>
                <p class="description"><?php esc_html_e('These offers do not meet the minimum profit guidelines:', 'hp-react-widgets'); ?></p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Funnel', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Offer', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Margin', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Profit', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Issue', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Suggestion', 'hp-react-widgets'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analysis['failing_offers'] as $offer): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $offer['funnel_id'] . '&action=edit')); ?>">
                                        <?php echo esc_html($offer['funnel_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($offer['offer_name']); ?></td>
                                <td>
                                    <span style="color: #d63638; font-weight: 600;">
                                        <?php echo esc_html(round($offer['margin_percent'], 1)); ?>%
                                    </span>
                                </td>
                                <td>
                                    <span style="color: #d63638;">
                                        $<?php echo esc_html(round($offer['profit_dollars'], 2)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $issues = [];
                                    if ($offer['margin_percent'] < $guidelines['profit_requirements']['min_profit_percent']) {
                                        $issues[] = sprintf(__('Below %d%% margin', 'hp-react-widgets'), $guidelines['profit_requirements']['min_profit_percent']);
                                    }
                                    if ($offer['profit_dollars'] < $guidelines['profit_requirements']['min_profit_dollars']) {
                                        $issues[] = sprintf(__('Below $%d profit', 'hp-react-widgets'), $guidelines['profit_requirements']['min_profit_dollars']);
                                    }
                                    echo esc_html(implode(', ', $issues));
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($offer['suggestion'])): ?>
                                        <?php echo esc_html($offer['suggestion']); ?>
                                    <?php else: ?>
                                        <?php esc_html_e('Increase price or reduce discount', 'hp-react-widgets'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- All Funnels Economics -->
            <div class="hp-econ-section">
                <h2><?php esc_html_e('All Funnel Economics', 'hp-react-widgets'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Funnel', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Offers', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Avg Margin', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Total Retail', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Status', 'hp-react-widgets'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analysis['funnels'] as $funnel): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $funnel['id'] . '&action=edit')); ?>">
                                        <?php echo esc_html($funnel['name']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($funnel['offer_count']); ?></td>
                                <td>
                                    <span style="color: <?php echo $funnel['avg_margin'] >= $guidelines['profit_requirements']['min_profit_percent'] ? '#00a32a' : '#d63638'; ?>; font-weight: 600;">
                                        <?php echo esc_html(round($funnel['avg_margin'], 1)); ?>%
                                    </span>
                                </td>
                                <td>$<?php echo esc_html(number_format($funnel['total_retail'], 2)); ?></td>
                                <td>
                                    <?php if ($funnel['all_valid']): ?>
                                        <span style="color: #00a32a;">✓ <?php esc_html_e('All pass', 'hp-react-widgets'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #d63638;">⚠️ <?php echo esc_html(sprintf(__('%d failing', 'hp-react-widgets'), $funnel['failing_count'])); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
            .hp-econ-cards {
                display: flex;
                gap: 20px;
                margin: 20px 0;
            }
            .hp-econ-card {
                background: #fff;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 4px;
                min-width: 150px;
                text-align: center;
            }
            .hp-econ-card-warning {
                border-color: #d63638;
                background: #fff5f5;
            }
            .hp-econ-card-success {
                border-color: #00a32a;
                background: #f0fff0;
            }
            .hp-econ-card-value {
                font-size: 32px;
                font-weight: 600;
                color: #1d2327;
            }
            .hp-econ-card-label {
                color: #666;
                margin-top: 5px;
            }
            .hp-econ-section {
                background: #fff;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin: 20px 0;
            }
            .hp-econ-section h2 {
                margin-top: 0;
            }
        </style>
        <?php
    }

    /**
     * Analyze economics across all funnels.
     */
    private static function analyzeFunnelEconomics(): array
    {
        $posts = get_posts([
            'post_type' => 'hp-funnel',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => -1,
        ]);

        $funnels = [];
        $allMargins = [];
        $totalOffers = 0;
        $failingOffers = [];
        $offersBelowThreshold = 0;

        foreach ($posts as $post) {
            $funnelData = FunnelExporter::exportById($post->ID);
            $offers = $funnelData['offers'] ?? [];
            
            $funnelMargins = [];
            $funnelRetail = 0;
            $funnelFailingCount = 0;
            
            foreach ($offers as $offer) {
                $validation = EconomicsService::validateOffer($offer);
                $margin = $validation['economics']['profit']['profit_margin_percent'] ?? 0;
                $profit = $validation['economics']['profit']['gross_profit'] ?? 0;
                $retail = $validation['economics']['retail_total'] ?? 0;
                $valid = $validation['valid'] ?? false;
                
                $funnelMargins[] = $margin;
                $allMargins[] = $margin;
                $funnelRetail += $retail;
                $totalOffers++;
                
                if (!$valid) {
                    $funnelFailingCount++;
                    $offersBelowThreshold++;
                    
                    $suggestion = '';
                    if (!empty($validation['suggestions'])) {
                        $suggestion = $validation['suggestions'][0]['message'] ?? '';
                    }
                    
                    $failingOffers[] = [
                        'funnel_id' => $post->ID,
                        'funnel_name' => $post->post_title,
                        'offer_id' => $offer['id'] ?? '',
                        'offer_name' => $offer['name'] ?? 'Unnamed',
                        'margin_percent' => $margin,
                        'profit_dollars' => $profit,
                        'suggestion' => $suggestion,
                    ];
                }
            }
            
            $funnels[] = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'offer_count' => count($offers),
                'avg_margin' => count($funnelMargins) > 0 ? array_sum($funnelMargins) / count($funnelMargins) : 0,
                'total_retail' => $funnelRetail,
                'all_valid' => $funnelFailingCount === 0,
                'failing_count' => $funnelFailingCount,
            ];
        }

        return [
            'total_funnels' => count($posts),
            'total_offers' => $totalOffers,
            'avg_margin' => count($allMargins) > 0 ? array_sum($allMargins) / count($allMargins) : 0,
            'offers_below_threshold' => $offersBelowThreshold,
            'failing_offers' => $failingOffers,
            'funnels' => $funnels,
        ];
    }
}

