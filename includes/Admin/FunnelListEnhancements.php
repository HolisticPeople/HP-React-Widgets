<?php
namespace HP_RW\Admin;

use HP_RW\Services\EconomicsService;
use HP_RW\Services\FunnelVersionControl;
use HP_RW\Services\FunnelExporter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhancements for the funnel list table in admin.
 */
class FunnelListEnhancements
{
    /**
     * Initialize list enhancements.
     */
    public static function init(): void
    {
        add_filter('manage_hp-funnel_posts_columns', [self::class, 'addColumns'], 20);
        add_action('manage_hp-funnel_posts_custom_column', [self::class, 'renderColumn'], 10, 2);
        add_action('admin_head', [self::class, 'addStyles']);
    }

    /**
     * Add custom columns.
     */
    public static function addColumns(array $columns): array
    {
        $newColumns = [];
        
        foreach ($columns as $key => $value) {
            $newColumns[$key] = $value;
            
            // Add after status column
            if ($key === 'status') {
                $newColumns['economics'] = __('Economics', 'hp-react-widgets');
                $newColumns['versions'] = __('Versions', 'hp-react-widgets');
                $newColumns['last_ai_action'] = __('Last Modified', 'hp-react-widgets');
            }
        }
        
        // If status column wasn't found, add at end
        if (!isset($newColumns['economics'])) {
            $newColumns['economics'] = __('Economics', 'hp-react-widgets');
            $newColumns['versions'] = __('Versions', 'hp-react-widgets');
            $newColumns['last_ai_action'] = __('Last Modified', 'hp-react-widgets');
        }

        return $newColumns;
    }

    /**
     * Render custom column content.
     */
    public static function renderColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'economics':
                self::renderEconomicsColumn($postId);
                break;
                
            case 'versions':
                self::renderVersionsColumn($postId);
                break;
                
            case 'last_ai_action':
                self::renderLastModifiedColumn($postId);
                break;
        }
    }

    /**
     * Render economics column showing average margin.
     */
    private static function renderEconomicsColumn(int $postId): void
    {
        $funnelData = FunnelExporter::exportById($postId);
        
        if (!$funnelData || empty($funnelData['offers'])) {
            echo '<span class="hp-econ-na">—</span>';
            return;
        }

        $margins = [];
        $hasFailure = false;
        $guidelines = EconomicsService::getGuidelines();
        $minPercent = $guidelines['profit_requirements']['min_profit_percent'];

        foreach ($funnelData['offers'] as $offer) {
            $validation = EconomicsService::validateOffer($offer);
            
            if (isset($validation['economics']['profit']['profit_margin_percent'])) {
                $margin = $validation['economics']['profit']['profit_margin_percent'];
                $margins[] = $margin;
                
                if (!$validation['valid']) {
                    $hasFailure = true;
                }
            }
        }

        if (empty($margins)) {
            echo '<span class="hp-econ-na">—</span>';
            return;
        }

        $avgMargin = round(array_sum($margins) / count($margins), 1);
        $statusClass = $hasFailure ? 'hp-econ-warn' : 'hp-econ-ok';
        $statusIcon = $hasFailure ? '⚠️' : '✓';

        echo '<span class="' . esc_attr($statusClass) . '">';
        echo '<span class="hp-econ-margin">' . esc_html($avgMargin) . '%</span> ';
        echo '<span class="hp-econ-status">' . esc_html($statusIcon) . '</span>';
        echo '</span>';
    }

    /**
     * Render versions column.
     */
    private static function renderVersionsColumn(int $postId): void
    {
        $versions = FunnelVersionControl::getVersions($postId);
        $count = $versions['versions_count'];
        
        if ($count === 0) {
            echo '<span class="hp-versions-none">v0</span>';
            return;
        }

        $currentVersion = $versions['current_version'] ?? '';
        $versionNum = str_replace('v_', 'v', $currentVersion);
        
        // Make it clickable to open version history
        $editUrl = admin_url("post.php?post={$postId}&action=edit#hp-funnel-versions");
        
        echo '<a href="' . esc_url($editUrl) . '" class="hp-versions-link" title="' . sprintf(esc_attr__('%d versions', 'hp-react-widgets'), $count) . '">';
        echo 'v' . esc_html($count);
        echo '</a>';
    }

    /**
     * Render last modified column.
     */
    private static function renderLastModifiedColumn(int $postId): void
    {
        $post = get_post($postId);
        $modified = strtotime($post->post_modified);
        $now = time();
        $diff = $now - $modified;

        // Format time ago
        if ($diff < 3600) {
            $timeAgo = sprintf(__('%d min ago', 'hp-react-widgets'), ceil($diff / 60));
        } elseif ($diff < 86400) {
            $timeAgo = sprintf(__('%d hr ago', 'hp-react-widgets'), ceil($diff / 3600));
        } elseif ($diff < 604800) {
            $timeAgo = sprintf(__('%d days ago', 'hp-react-widgets'), ceil($diff / 86400));
        } else {
            $timeAgo = date_i18n(get_option('date_format'), $modified);
        }

        // Check if last action was by AI
        $isAiGenerated = get_post_meta($postId, '_hp_is_ai_generated', true) === '1';
        $aiActivity = FunnelVersionControl::getAiActivity($postId, 1);
        $byAi = $isAiGenerated;
        
        if (!empty($aiActivity)) {
            $lastActivity = $aiActivity[0];
            $activityTime = strtotime($lastActivity['timestamp']);
            // If AI activity was within 60 seconds of post modified, consider it AI-modified
            if (abs($activityTime - $modified) < 60) {
                $byAi = true;
            }
        }

        echo '<span class="hp-modified">';
        echo '<span class="hp-modified-time">' . esc_html($timeAgo) . '</span>';
        if ($byAi) {
            echo ' <span class="hp-modified-by hp-modified-ai" title="' . esc_attr__('Modified by AI Agent', 'hp-react-widgets') . '">🤖</span>';
        } else {
            echo ' <span class="hp-modified-by hp-modified-admin" title="' . esc_attr__('Modified by Admin', 'hp-react-widgets') . '">👤</span>';
        }
        echo '</span>';
    }

    /**
     * Add CSS styles for the columns.
     */
    public static function addStyles(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'edit-hp-funnel') {
            return;
        }
        ?>
        <style>
            .column-economics { width: 90px; }
            .column-versions { width: 70px; }
            .column-last_ai_action { width: 120px; }
            
            .hp-econ-ok { color: #00a32a; }
            .hp-econ-warn { color: #d63638; }
            .hp-econ-na { color: #999; }
            .hp-econ-margin { font-weight: 600; }
            
            .hp-versions-link {
                text-decoration: none;
                background: #f0f0f1;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 12px;
            }
            .hp-versions-link:hover {
                background: #dcdcde;
            }
            .hp-versions-none { color: #999; }
            
            .hp-modified-time { color: #666; }
            .hp-modified-by { margin-left: 3px; }
            .hp-modified-ai { cursor: help; }
            .hp-modified-admin { cursor: help; }
        </style>
        <?php
    }
}

