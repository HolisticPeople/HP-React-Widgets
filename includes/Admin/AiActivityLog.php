<?php
namespace HP_RW\Admin;

use HP_RW\Services\FunnelVersionControl;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Activity Log admin page.
 */
class AiActivityLog
{
    /**
     * Initialize the activity log page.
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
            __('AI Activity', 'hp-react-widgets'),
            __('AI Activity', 'hp-react-widgets'),
            'manage_woocommerce',
            'hp-funnel-ai-activity',
            [self::class, 'renderPage']
        );
    }

    /**
     * Render the activity log page.
     */
    public static function renderPage(): void
    {
        $limit = isset($_GET['limit']) ? absint($_GET['limit']) : 100;
        $activities = FunnelVersionControl::getAllAiActivity($limit);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Activity Log', 'hp-react-widgets'); ?></h1>
            
            <p class="description">
                <?php esc_html_e('History of all AI agent actions across funnels.', 'hp-react-widgets'); ?>
            </p>

            <?php if (empty($activities)): ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e('No AI activity recorded yet. Activity will appear here when the AI agent creates or modifies funnels.', 'hp-react-widgets'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 150px;"><?php esc_html_e('Time', 'hp-react-widgets'); ?></th>
                            <th style="width: 150px;"><?php esc_html_e('Funnel', 'hp-react-widgets'); ?></th>
                            <th style="width: 150px;"><?php esc_html_e('Action', 'hp-react-widgets'); ?></th>
                            <th><?php esc_html_e('Description', 'hp-react-widgets'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td>
                                    <span title="<?php echo esc_attr($activity['timestamp']); ?>">
                                        <?php echo esc_html(human_time_diff(strtotime($activity['timestamp']), time()) . ' ago'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $activity['funnel_id'] . '&action=edit')); ?>">
                                        <?php echo esc_html($activity['funnel_name']); ?>
                                    </a>
                                    <br>
                                    <code style="font-size: 11px;"><?php echo esc_html($activity['funnel_slug']); ?></code>
                                </td>
                                <td>
                                    <span class="hp-action-badge hp-action-<?php echo esc_attr(sanitize_title($activity['action'])); ?>">
                                        🤖 <?php echo esc_html(ucwords(str_replace('_', ' ', $activity['action']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($activity['description'] ?: '—'); ?>
                                    <?php if (!empty($activity['details'])): ?>
                                        <br>
                                        <small style="color: #666;">
                                            <?php 
                                            $details = $activity['details'];
                                            if (is_array($details)) {
                                                echo esc_html(json_encode($details));
                                            }
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p style="margin-top: 15px;">
                    <?php esc_html_e('Showing', 'hp-react-widgets'); ?> 
                    <strong><?php echo count($activities); ?></strong> 
                    <?php esc_html_e('activities.', 'hp-react-widgets'); ?>
                    
                    <?php if (count($activities) >= $limit): ?>
                        <a href="<?php echo esc_url(add_query_arg('limit', $limit + 100)); ?>">
                            <?php esc_html_e('Load more', 'hp-react-widgets'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        
        <style>
            .hp-action-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 12px;
                background: #f0f0f1;
            }
            .hp-action-funnel-created { background: #d1fae5; color: #065f46; }
            .hp-action-funnel-updated { background: #dbeafe; color: #1e40af; }
            .hp-action-sections-updated { background: #e0e7ff; color: #3730a3; }
            .hp-action-version-restored { background: #fef3c7; color: #92400e; }
            .hp-action-backup-created { background: #f3e8ff; color: #6b21a8; }
        </style>
        <?php
    }
}















