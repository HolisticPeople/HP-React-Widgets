<?php
namespace HP_RW\Admin;

use HP_RW\Services\EconomicsService;
use HP_RW\Services\FunnelVersionControl;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Settings page for funnel creation configuration.
 */
class AiSettingsPage
{
    /**
     * Initialize the settings page.
     */
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'addAdminMenu']);
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    /**
     * Add admin menu pages.
     */
    public static function addAdminMenu(): void
    {
        // Add submenu under HP Funnels
        add_submenu_page(
            'edit.php?post_type=hp-funnel',
            __('AI Settings', 'hp-react-widgets'),
            __('AI Settings', 'hp-react-widgets'),
            'manage_woocommerce',
            'hp-funnel-ai-settings',
            [self::class, 'renderSettingsPage']
        );
    }

    /**
     * Register settings.
     */
    public static function registerSettings(): void
    {
        // Economic Guidelines Section
        add_settings_section(
            'hp_ai_economics',
            __('Economic Guidelines', 'hp-react-widgets'),
            [self::class, 'renderEconomicsSection'],
            'hp-funnel-ai-settings'
        );

        // Shipping Configuration Section
        add_settings_section(
            'hp_ai_shipping',
            __('Shipping Rules', 'hp-react-widgets'),
            [self::class, 'renderShippingSection'],
            'hp-funnel-ai-settings'
        );

        // Version Control Section
        add_settings_section(
            'hp_ai_version_control',
            __('Version Control', 'hp-react-widgets'),
            [self::class, 'renderVersionControlSection'],
            'hp-funnel-ai-settings'
        );
    }

    /**
     * Render the settings page.
     */
    public static function renderSettingsPage(): void
    {
        // Handle form submission
        if (isset($_POST['hp_ai_settings_nonce']) && wp_verify_nonce($_POST['hp_ai_settings_nonce'], 'hp_ai_settings')) {
            self::saveSettings();
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'hp-react-widgets') . '</p></div>';
        }

        $guidelines = EconomicsService::getGuidelines();
        $vcSettings = FunnelVersionControl::getSettings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Funnel Assistant Settings', 'hp-react-widgets'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('hp_ai_settings', 'hp_ai_settings_nonce'); ?>
                
                <h2><?php esc_html_e('Economic Guidelines', 'hp-react-widgets'); ?></h2>
                <p class="description"><?php esc_html_e('Configure profit requirements that the AI agent will enforce when building offers.', 'hp-react-widgets'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Minimum Profit Percent', 'hp-react-widgets'); ?></th>
                        <td>
                            <input type="number" name="min_profit_percent" value="<?php echo esc_attr($guidelines['profit_requirements']['min_profit_percent']); ?>" min="0" max="100" step="1" class="small-text">%
                            <p class="description"><?php esc_html_e('Minimum profit margin percentage required for offers.', 'hp-react-widgets'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Minimum Profit Dollars', 'hp-react-widgets'); ?></th>
                        <td>
                            $<input type="number" name="min_profit_dollars" value="<?php echo esc_attr($guidelines['profit_requirements']['min_profit_dollars']); ?>" min="0" step="1" class="small-text">
                            <p class="description"><?php esc_html_e('Minimum profit in dollars required for offers.', 'hp-react-widgets'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Apply Rule', 'hp-react-widgets'); ?></th>
                        <td>
                            <select name="apply_rule">
                                <option value="higher" <?php selected($guidelines['profit_requirements']['apply_rule'], 'higher'); ?>><?php esc_html_e('Both must pass (AND)', 'hp-react-widgets'); ?></option>
                                <option value="lower" <?php selected($guidelines['profit_requirements']['apply_rule'], 'lower'); ?>><?php esc_html_e('Either can pass (OR)', 'hp-react-widgets'); ?></option>
                                <option value="percent_only" <?php selected($guidelines['profit_requirements']['apply_rule'], 'percent_only'); ?>><?php esc_html_e('Percent only', 'hp-react-widgets'); ?></option>
                                <option value="dollars_only" <?php selected($guidelines['profit_requirements']['apply_rule'], 'dollars_only'); ?>><?php esc_html_e('Dollars only', 'hp-react-widgets'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Maximum Discount', 'hp-react-widgets'); ?></th>
                        <td>
                            <input type="number" name="max_discount_percent" value="<?php echo esc_attr($guidelines['pricing_strategy']['max_discount_percent']); ?>" min="0" max="100" step="1" class="small-text">%
                            <p class="description"><?php esc_html_e('Maximum discount percentage allowed on offers.', 'hp-react-widgets'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Price Rounding', 'hp-react-widgets'); ?></th>
                        <td>
                            $X.<input type="text" name="round_to" value="<?php echo esc_attr(str_replace('0.', '', (string)$guidelines['pricing_strategy']['round_to'])); ?>" class="small-text" style="width: 40px;">
                            <p class="description"><?php esc_html_e('Round prices to this ending (e.g., 99 for $X.99)', 'hp-react-widgets'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Shipping Rules', 'hp-react-widgets'); ?></h2>
                
                <h3><?php esc_html_e('Domestic (US)', 'hp-react-widgets'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Free Shipping Threshold', 'hp-react-widgets'); ?></th>
                        <td>
                            $<input type="number" name="domestic_free_threshold" value="<?php echo esc_attr($guidelines['shipping']['domestic']['free_shipping_threshold']); ?>" min="0" step="1" class="small-text">
                            <p class="description"><?php esc_html_e('Orders over this amount get free shipping domestically.', 'hp-react-widgets'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Shipping Cost Calculation', 'hp-react-widgets'); ?></th>
                        <td>
                            <?php esc_html_e('Base:', 'hp-react-widgets'); ?> $<input type="number" name="domestic_base_cost" value="<?php echo esc_attr($guidelines['shipping']['domestic']['base_cost']); ?>" step="0.01" class="small-text">
                            + <?php esc_html_e('Per item:', 'hp-react-widgets'); ?> $<input type="number" name="domestic_per_item" value="<?php echo esc_attr($guidelines['shipping']['domestic']['per_item_cost']); ?>" step="0.01" class="small-text">
                            + <?php esc_html_e('Per oz:', 'hp-react-widgets'); ?> $<input type="number" name="domestic_weight_rate" value="<?php echo esc_attr($guidelines['shipping']['domestic']['weight_rate']); ?>" step="0.01" class="small-text">
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e('International Shipping Subsidies', 'hp-react-widgets'); ?></h3>
                <p class="description"><?php esc_html_e('Subsidy percentage based on order profit. Higher profit orders get more shipping subsidy.', 'hp-react-widgets'); ?></p>
                <table class="form-table" id="subsidy-tiers">
                    <tr>
                        <th><?php esc_html_e('Min Profit', 'hp-react-widgets'); ?></th>
                        <th><?php esc_html_e('Subsidy %', 'hp-react-widgets'); ?></th>
                    </tr>
                    <?php foreach ($guidelines['shipping']['international']['subsidy_tiers'] as $i => $tier): ?>
                    <tr>
                        <td>$<input type="number" name="subsidy_tier_profit[<?php echo $i; ?>]" value="<?php echo esc_attr($tier['min_profit']); ?>" class="small-text"></td>
                        <td><input type="number" name="subsidy_tier_percent[<?php echo $i; ?>]" value="<?php echo esc_attr($tier['subsidy_percent']); ?>" min="0" max="100" class="small-text">%</td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <h2><?php esc_html_e('Version Control', 'hp-react-widgets'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-backup on Update', 'hp-react-widgets'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_backup" value="1" <?php checked($vcSettings['auto_backup_on_update']); ?>>
                                <?php esc_html_e('Automatically create a backup before AI modifications', 'hp-react-widgets'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Max Versions', 'hp-react-widgets'); ?></th>
                        <td>
                            <input type="number" name="max_versions" value="<?php echo esc_attr($vcSettings['max_versions']); ?>" min="5" max="100" class="small-text">
                            <p class="description"><?php esc_html_e('Maximum number of versions to keep per funnel.', 'hp-react-widgets'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Retention Period', 'hp-react-widgets'); ?></th>
                        <td>
                            <input type="number" name="retention_days" value="<?php echo esc_attr($vcSettings['retention_days']); ?>" min="7" max="365" class="small-text">
                            <?php esc_html_e('days', 'hp-react-widgets'); ?>
                            <p class="description"><?php esc_html_e('Delete versions older than this (except recent 3 versions).', 'hp-react-widgets'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'hp-react-widgets')); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('AI API Endpoints', 'hp-react-widgets'); ?></h2>
            <p class="description"><?php esc_html_e('These REST API endpoints are available for the AI agent:', 'hp-react-widgets'); ?></p>
            
            <table class="widefat" style="max-width: 800px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Endpoint', 'hp-react-widgets'); ?></th>
                        <th><?php esc_html_e('Purpose', 'hp-react-widgets'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>GET /wp-json/hp-rw/v1/ai/system/explain</code></td>
                        <td><?php esc_html_e('Complete system documentation for AI', 'hp-react-widgets'); ?></td>
                    </tr>
                    <tr>
                        <td><code>GET /wp-json/hp-rw/v1/ai/schema</code></td>
                        <td><?php esc_html_e('Funnel schema with AI generation hints', 'hp-react-widgets'); ?></td>
                    </tr>
                    <tr>
                        <td><code>GET /wp-json/hp-rw/v1/ai/funnels</code></td>
                        <td><?php esc_html_e('List all funnels', 'hp-react-widgets'); ?></td>
                    </tr>
                    <tr>
                        <td><code>GET /wp-json/hp-rw/v1/ai/funnels/{slug}</code></td>
                        <td><?php esc_html_e('Get complete funnel JSON', 'hp-react-widgets'); ?></td>
                    </tr>
                    <tr>
                        <td><code>POST /wp-json/hp-rw/v1/ai/funnels</code></td>
                        <td><?php esc_html_e('Create new funnel', 'hp-react-widgets'); ?></td>
                    </tr>
                    <tr>
                        <td><code>PUT /wp-json/hp-rw/v1/ai/funnels/{slug}</code></td>
                        <td><?php esc_html_e('Update funnel', 'hp-react-widgets'); ?></td>
                    </tr>
                    <tr>
                        <td><code>POST /wp-json/hp-rw/v1/ai/protocols/build-kit</code></td>
                        <td><?php esc_html_e('Build kit from protocol', 'hp-react-widgets'); ?></td>
                    </tr>
                    <tr>
                        <td><code>POST /wp-json/hp-rw/v1/ai/economics/calculate</code></td>
                        <td><?php esc_html_e('Calculate offer profitability', 'hp-react-widgets'); ?></td>
                    </tr>
                    <tr>
                        <td><code>GET /wp-json/hp-rw/v1/ai/products</code></td>
                        <td><?php esc_html_e('Search products with serving info', 'hp-react-widgets'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render economics section description.
     */
    public static function renderEconomicsSection(): void
    {
        echo '<p>' . esc_html__('Configure profit requirements that the AI agent will enforce when building offers.', 'hp-react-widgets') . '</p>';
    }

    /**
     * Render shipping section description.
     */
    public static function renderShippingSection(): void
    {
        echo '<p>' . esc_html__('Configure shipping costs and subsidy tiers for different regions.', 'hp-react-widgets') . '</p>';
    }

    /**
     * Render version control section description.
     */
    public static function renderVersionControlSection(): void
    {
        echo '<p>' . esc_html__('Configure how funnel versions are managed and retained.', 'hp-react-widgets') . '</p>';
    }

    /**
     * Save settings from form submission.
     */
    private static function saveSettings(): void
    {
        // Build guidelines array
        $guidelines = EconomicsService::getGuidelines();
        
        // Update profit requirements
        $guidelines['profit_requirements']['min_profit_percent'] = absint($_POST['min_profit_percent'] ?? 10);
        $guidelines['profit_requirements']['min_profit_dollars'] = absint($_POST['min_profit_dollars'] ?? 50);
        $guidelines['profit_requirements']['apply_rule'] = sanitize_text_field($_POST['apply_rule'] ?? 'higher');
        
        // Update pricing strategy
        $guidelines['pricing_strategy']['max_discount_percent'] = absint($_POST['max_discount_percent'] ?? 40);
        $roundTo = sanitize_text_field($_POST['round_to'] ?? '99');
        $guidelines['pricing_strategy']['round_to'] = floatval('0.' . $roundTo);
        
        // Update domestic shipping
        $guidelines['shipping']['domestic']['free_shipping_threshold'] = absint($_POST['domestic_free_threshold'] ?? 100);
        $guidelines['shipping']['domestic']['base_cost'] = floatval($_POST['domestic_base_cost'] ?? 5.99);
        $guidelines['shipping']['domestic']['per_item_cost'] = floatval($_POST['domestic_per_item'] ?? 0.50);
        $guidelines['shipping']['domestic']['weight_rate'] = floatval($_POST['domestic_weight_rate'] ?? 0.15);
        
        // Update subsidy tiers
        $subsidyProfits = $_POST['subsidy_tier_profit'] ?? [];
        $subsidyPercents = $_POST['subsidy_tier_percent'] ?? [];
        $tiers = [];
        foreach ($subsidyProfits as $i => $profit) {
            $tiers[] = [
                'min_profit' => absint($profit),
                'subsidy_percent' => absint($subsidyPercents[$i] ?? 0),
            ];
        }
        // Sort by profit descending
        usort($tiers, fn($a, $b) => $b['min_profit'] - $a['min_profit']);
        $guidelines['shipping']['international']['subsidy_tiers'] = $tiers;
        
        EconomicsService::saveGuidelines($guidelines);
        
        // Update version control settings
        $vcSettings = [
            'auto_backup_on_update' => isset($_POST['auto_backup']),
            'max_versions' => absint($_POST['max_versions'] ?? 20),
            'retention_days' => absint($_POST['retention_days'] ?? 90),
        ];
        FunnelVersionControl::saveSettings($vcSettings);
    }
}

