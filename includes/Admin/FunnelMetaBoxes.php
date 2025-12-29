<?php
namespace HP_RW\Admin;

use HP_RW\Services\EconomicsService;
use HP_RW\Services\FunnelVersionControl;
use HP_RW\Services\FunnelExporter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta boxes for the funnel edit screen.
 */
class FunnelMetaBoxes
{
    /**
     * Initialize meta boxes.
     */
    public static function init(): void
    {
        add_action('add_meta_boxes', [self::class, 'registerMetaBoxes']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueScripts']);
    }

    /**
     * Register meta boxes.
     */
    public static function registerMetaBoxes(): void
    {
        add_meta_box(
            'hp-funnel-versions',
            __('Version History', 'hp-react-widgets'),
            [self::class, 'renderVersionsMetaBox'],
            'hp-funnel',
            'side',
            'high'
        );

        add_meta_box(
            'hp-funnel-economics',
            __('Economics Summary', 'hp-react-widgets'),
            [self::class, 'renderEconomicsMetaBox'],
            'hp-funnel',
            'side',
            'high'
        );

        add_meta_box(
            'hp-funnel-ai-activity',
            __('AI Activity', 'hp-react-widgets'),
            [self::class, 'renderAiActivityMetaBox'],
            'hp-funnel',
            'side',
            'default'
        );
    }

    /**
     * Render version history meta box.
     */
    public static function renderVersionsMetaBox(\WP_Post $post): void
    {
        $versions = FunnelVersionControl::getVersions($post->ID);
        $versionsList = $versions['versions'] ?? [];
        ?>
        <div class="hp-versions-metabox">
            <?php if (empty($versionsList)): ?>
                <p class="hp-no-versions"><?php esc_html_e('No versions yet.', 'hp-react-widgets'); ?></p>
                <p class="description"><?php esc_html_e('Versions are created automatically when the AI agent modifies the funnel.', 'hp-react-widgets'); ?></p>
            <?php else: ?>
                <div class="hp-versions-list">
                    <?php foreach (array_slice($versionsList, 0, 5) as $version): ?>
                        <div class="hp-version-item <?php echo $version['is_current'] ? 'hp-version-current' : ''; ?>">
                            <div class="hp-version-header">
                                <span class="hp-version-id">
                                    <?php echo esc_html(str_replace('v_', 'v', $version['version_id'])); ?>
                                    <?php if ($version['is_current']): ?>
                                        <span class="hp-current-badge"><?php esc_html_e('Current', 'hp-react-widgets'); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="hp-version-by">
                                    <?php echo $version['created_by'] === 'ai_agent' ? '🤖' : '👤'; ?>
                                </span>
                            </div>
                            <div class="hp-version-meta">
                                <span class="hp-version-date">
                                    <?php echo esc_html(human_time_diff(strtotime($version['created_at']), time()) . ' ago'); ?>
                                </span>
                            </div>
                            <?php if ($version['description']): ?>
                                <div class="hp-version-desc">
                                    <?php echo esc_html($version['description']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!$version['is_current']): ?>
                                <div class="hp-version-actions">
                                    <button type="button" 
                                            class="button button-small hp-restore-version" 
                                            data-version-id="<?php echo esc_attr($version['version_id']); ?>"
                                            data-funnel-id="<?php echo esc_attr($post->ID); ?>">
                                        <?php esc_html_e('Restore', 'hp-react-widgets'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($versionsList) > 5): ?>
                    <p class="hp-versions-more">
                        <?php echo sprintf(
                            esc_html__('+ %d more versions', 'hp-react-widgets'),
                            count($versionsList) - 5
                        ); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            
            <hr>
            <button type="button" class="button hp-create-backup" data-funnel-id="<?php echo esc_attr($post->ID); ?>">
                <?php esc_html_e('Create Backup Now', 'hp-react-widgets'); ?>
            </button>
        </div>
        <?php
    }

    /**
     * Render economics summary meta box.
     */
    public static function renderEconomicsMetaBox(\WP_Post $post): void
    {
        $funnelData = FunnelExporter::exportById($post->ID);
        $offers = $funnelData['offers'] ?? [];
        $guidelines = EconomicsService::getGuidelines();
        ?>
        <div class="hp-economics-metabox">
            <?php if (empty($offers)): ?>
                <p class="hp-no-offers"><?php esc_html_e('No offers configured.', 'hp-react-widgets'); ?></p>
            <?php else: ?>
                <table class="hp-economics-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Offer', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Margin', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Status', 'hp-react-widgets'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($offers as $offer): 
                            $validation = EconomicsService::validateOffer($offer);
                            $margin = $validation['economics']['profit']['profit_margin_percent'] ?? 0;
                            $profit = $validation['economics']['profit']['gross_profit'] ?? 0;
                            $valid = $validation['valid'] ?? false;
                        ?>
                            <tr class="<?php echo $valid ? 'hp-econ-pass' : 'hp-econ-fail'; ?>">
                                <td class="hp-offer-name" title="<?php echo esc_attr($offer['name'] ?? ''); ?>">
                                    <?php echo esc_html($offer['name'] ?? $offer['id']); ?>
                                </td>
                                <td class="hp-offer-margin">
                                    <?php echo esc_html(round($margin, 1)); ?>%
                                    <br>
                                    <small>$<?php echo esc_html(number_format($profit, 2)); ?></small>
                                </td>
                                <td class="hp-offer-status">
                                    <?php if ($valid): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="hp-economics-summary">
                    <p>
                        <strong><?php esc_html_e('Guidelines:', 'hp-react-widgets'); ?></strong><br>
                        <?php echo sprintf(
                            esc_html__('Min %d%% or $%d profit', 'hp-react-widgets'),
                            $guidelines['profit_requirements']['min_profit_percent'],
                            $guidelines['profit_requirements']['min_profit_dollars']
                        ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render AI activity meta box.
     */
    public static function renderAiActivityMetaBox(\WP_Post $post): void
    {
        $activities = FunnelVersionControl::getAiActivity($post->ID, 5);
        ?>
        <div class="hp-ai-activity-metabox">
            <?php if (empty($activities)): ?>
                <p class="hp-no-activity"><?php esc_html_e('No AI activity recorded.', 'hp-react-widgets'); ?></p>
            <?php else: ?>
                <ul class="hp-activity-list">
                    <?php foreach ($activities as $activity): ?>
                        <li class="hp-activity-item">
                            <span class="hp-activity-icon">🤖</span>
                            <div class="hp-activity-content">
                                <span class="hp-activity-action"><?php echo esc_html(ucwords(str_replace('_', ' ', $activity['action']))); ?></span>
                                <?php if (!empty($activity['description'])): ?>
                                    <span class="hp-activity-desc"><?php echo esc_html($activity['description']); ?></span>
                                <?php endif; ?>
                                <span class="hp-activity-time">
                                    <?php echo esc_html(human_time_diff(strtotime($activity['timestamp']), time()) . ' ago'); ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <p>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=hp-funnel&page=hp-funnel-ai-activity')); ?>">
                        <?php esc_html_e('View all AI activity →', 'hp-react-widgets'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue scripts and styles.
     */
    public static function enqueueScripts(string $hook): void
    {
        global $post;
        
        if (!in_array($hook, ['post.php', 'post-new.php'], true) || !$post || $post->post_type !== 'hp-funnel') {
            return;
        }

        wp_add_inline_style('wp-admin', self::getStyles());
        wp_add_inline_script('jquery', self::getScripts());
    }

    /**
     * Get inline styles.
     */
    private static function getStyles(): string
    {
        return '
            .hp-versions-metabox .hp-versions-list {
                max-height: 300px;
                overflow-y: auto;
            }
            .hp-version-item {
                padding: 8px;
                margin-bottom: 8px;
                background: #f9f9f9;
                border-left: 3px solid #ddd;
            }
            .hp-version-item.hp-version-current {
                border-left-color: #00a32a;
                background: #f0fff0;
            }
            .hp-version-header {
                display: flex;
                justify-content: space-between;
                font-weight: 600;
            }
            .hp-version-id {
                font-family: monospace;
            }
            .hp-current-badge {
                font-size: 10px;
                background: #00a32a;
                color: #fff;
                padding: 1px 5px;
                border-radius: 3px;
                margin-left: 5px;
            }
            .hp-version-meta {
                font-size: 11px;
                color: #666;
            }
            .hp-version-desc {
                font-size: 12px;
                color: #333;
                margin-top: 4px;
            }
            .hp-version-actions {
                margin-top: 6px;
            }
            .hp-versions-more {
                color: #666;
                font-style: italic;
            }
            
            .hp-economics-table {
                width: 100%;
                font-size: 12px;
                border-collapse: collapse;
            }
            .hp-economics-table th,
            .hp-economics-table td {
                padding: 4px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            .hp-economics-table .hp-econ-pass { background: #f0fff0; }
            .hp-economics-table .hp-econ-fail { background: #fff5f5; }
            .hp-offer-margin { text-align: right; }
            .hp-offer-margin small { color: #666; }
            .hp-offer-status { text-align: center; width: 30px; }
            
            .hp-activity-list {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .hp-activity-item {
                display: flex;
                gap: 8px;
                padding: 6px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            .hp-activity-content {
                display: flex;
                flex-direction: column;
            }
            .hp-activity-action {
                font-weight: 500;
                font-size: 12px;
            }
            .hp-activity-desc {
                font-size: 11px;
                color: #666;
            }
            .hp-activity-time {
                font-size: 10px;
                color: #999;
            }
        ';
    }

    /**
     * Get inline scripts.
     */
    private static function getScripts(): string
    {
        return "
            jQuery(document).ready(function($) {
                // Create backup button
                $('.hp-create-backup').on('click', function() {
                    var btn = $(this);
                    var funnelId = btn.data('funnel-id');
                    
                    btn.prop('disabled', true).text('Creating...');
                    
                    $.ajax({
                        url: '/wp-json/hp-rw/v1/ai/funnels/' + funnelId + '/versions',
                        method: 'POST',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                        },
                        data: JSON.stringify({
                            description: 'Manual backup from admin',
                            created_by: 'admin'
                        }),
                        contentType: 'application/json',
                        success: function(response) {
                            alert('Backup created successfully!');
                            location.reload();
                        },
                        error: function() {
                            alert('Failed to create backup.');
                            btn.prop('disabled', false).text('Create Backup Now');
                        }
                    });
                });
                
                // Restore version button
                $('.hp-restore-version').on('click', function() {
                    var btn = $(this);
                    var versionId = btn.data('version-id');
                    var funnelId = btn.data('funnel-id');
                    
                    if (!confirm('Restore to this version? Current state will be backed up first.')) {
                        return;
                    }
                    
                    btn.prop('disabled', true).text('Restoring...');
                    
                    $.ajax({
                        url: '/wp-json/hp-rw/v1/ai/funnels/' + funnelId + '/versions/' + versionId + '/restore',
                        method: 'POST',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                        },
                        data: JSON.stringify({
                            backup_current: true
                        }),
                        contentType: 'application/json',
                        success: function(response) {
                            alert('Version restored successfully!');
                            location.reload();
                        },
                        error: function() {
                            alert('Failed to restore version.');
                            btn.prop('disabled', false).text('Restore');
                        }
                    });
                });
            });
        ";
    }
}

