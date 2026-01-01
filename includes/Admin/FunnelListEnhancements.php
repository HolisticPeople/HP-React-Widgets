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
        add_filter('display_post_states', [self::class, 'addAiPostState'], 10, 2);
        add_filter('post_row_actions', [self::class, 'addRowActions'], 10, 2);
        add_action('admin_head', [self::class, 'addStyles']);
        add_action('admin_footer', [self::class, 'renderAuditModal']);
    }

    /**
     * Add SEO Audit row action.
     */
    public static function addRowActions(array $actions, \WP_Post $post): array
    {
        if ($post->post_type !== 'hp-funnel') {
            return $actions;
        }

        $actions['seo_audit'] = sprintf(
            '<a href="#" class="hp-run-seo-audit" data-post-id="%d" data-slug="%s">%s</a>',
            $post->ID,
            get_field('funnel_slug', $post->ID) ?: $post->post_name,
            __('SEO Audit', 'hp-react-widgets')
        );

        return $actions;
    }

    /**
     * Add AI Generated post state.
     */
    public static function addAiPostState(array $post_states, \WP_Post $post): array
    {
        if ($post->post_type === 'hp-funnel' && get_post_meta($post->ID, '_hp_is_ai_generated', true) === '1') {
            $post_states['ai_generated'] = '🤖 ' . __('AI Generated', 'hp-react-widgets');
        }
        return $post_states;
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
        if (!$screen || $screen->post_type !== 'hp-funnel') {
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

            /* Audit Modal Styles */
            #hp-seo-audit-modal { display: none; }
            .hp-audit-report { padding: 15px; }
            .hp-audit-section { margin-bottom: 20px; }
            .hp-audit-section h3 { margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
            .hp-audit-item { margin-bottom: 5px; padding-left: 20px; position: relative; }
            .hp-audit-item:before { position: absolute; left: 0; }
            .hp-audit-problem { color: #d63638; }
            .hp-audit-problem:before { content: "●"; }
            .hp-audit-improvement { color: #dba617; }
            .hp-audit-improvement:before { content: "○"; }
            .hp-audit-good { color: #00a32a; }
            .hp-audit-good:before { content: "✓"; }
            .hp-audit-score { font-size: 18px; font-weight: bold; margin-bottom: 15px; text-align: center; }
            .hp-audit-status-good { color: #00a32a; }
            .hp-audit-status-needs_improvement { color: #dba617; }
            .hp-audit-status-poor { color: #d63638; }
        </style>
        <script>
        jQuery(document).ready(function($) {
            $('.hp-run-seo-audit').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var slug = btn.data('slug');
                
                btn.text('Auditing...');
                
                $.ajax({
                    url: '/wp-json/hp-abilities/v1/funnels/' + slug + '/seo-audit',
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    },
                    success: function(response) {
                        btn.text('SEO Audit');
                        if (response.success && response.data) {
                            showAuditReport(response.data);
                        } else {
                            alert('Audit failed: ' + (response.error || 'Unknown error'));
                        }
                    },
                    error: function(xhr) {
                        btn.text('SEO Audit');
                        alert('Audit error: ' + xhr.statusText);
                    }
                });
            });

            function showAuditReport(report) {
                var html = '<div class="hp-audit-report">';
                html += '<div class="hp-audit-score hp-audit-status-' + report.status + '">';
                html += 'Status: ' + report.status.replace("_", " ").toUpperCase();
                html += ' (' + report.score.good + '/' + report.score.total + ' Good)';
                html += '</div>';

                if (report.problems.length > 0) {
                    html += '<div class="hp-audit-section"><h3>Problems</h3>';
                    report.problems.forEach(function(p) { html += '<div class="hp-audit-item hp-audit-problem">' + p + '</div>'; });
                    html += '</div>';
                }

                if (report.improvements.length > 0) {
                    html += '<div class="hp-audit-section"><h3>Improvements</h3>';
                    report.improvements.forEach(function(i) { html += '<div class="hp-audit-item hp-audit-improvement">' + i + '</div>'; });
                    html += '</div>';
                }

                if (report.good.length > 0) {
                    html += '<div class="hp-audit-section"><h3>Good</h3>';
                    report.good.forEach(function(g) { html += '<div class="hp-audit-item hp-audit-good">' + g + '</div>'; });
                    html += '</div>';
                }
                html += '</div>';

                // Use thickbox if available, otherwise simple alert
                if (typeof tb_show === "function") {
                    $('#hp-seo-audit-content').html(html);
                    tb_show('SEO Audit Report: ' + report.focus_keyword, '#TB_inline?inlineId=hp-seo-audit-modal&width=600&height=500');
                } else {
                    // Fallback to a simpler display
                    var win = window.open("", "SEO Audit", "width=600,height=600");
                    win.document.write("<html><head><title>SEO Audit</title></head><body>" + html + "</body></html>");
                }
            }
        });
        </script>
        <?php
    }

    /**
     * Render audit modal container.
     */
    public static function renderAuditModal(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'hp-funnel') {
            return;
        }
        add_thickbox();
        ?>
        <div id="hp-seo-audit-modal" style="display:none;">
            <div id="hp-seo-audit-content"></div>
        </div>
        <?php
    }
}

