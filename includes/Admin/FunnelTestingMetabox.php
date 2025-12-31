<?php
namespace HP_RW\Admin;

use HP_RW\Services\FunnelSeoService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Testing Metabox for Funnel Editor.
 * 
 * Provides in-editor testing workflow for SEO and analytics verification.
 * 
 * Features:
 * - External validation links (Google Rich Results, Facebook Debugger)
 * - Analytics journey checklist
 * - Debug panel with stored values
 * 
 * @since 2.9.0
 */
class FunnelTestingMetabox
{
    /**
     * Initialize the metabox.
     */
    public static function init(): void
    {
        add_action('add_meta_boxes', [self::class, 'addMetabox']);
        add_action('admin_footer', [self::class, 'injectStyles']);
    }

    /**
     * Add the testing metabox.
     */
    public static function addMetabox(): void
    {
        add_meta_box(
            'hp_funnel_testing',
            '🧪 SEO & Analytics Testing',
            [self::class, 'renderMetabox'],
            'hp-funnel',
            'side',
            'default'
        );
    }

    /**
     * Render the metabox content.
     */
    public static function renderMetabox($post): void
    {
        $postId = $post->ID;
        $permalink = get_permalink($postId);
        $funnelSlug = get_post_meta($postId, 'funnel_slug', true) ?: $post->post_name;

        // Get stored SEO values
        $minPrice = get_post_meta($postId, 'funnel_min_price', true);
        $maxPrice = get_post_meta($postId, 'funnel_max_price', true);
        $brand = get_post_meta($postId, 'funnel_brand', true);
        $availability = get_post_meta($postId, 'funnel_availability', true);

        // Get offer count
        $offers = get_field('funnel_offers', $postId);
        $offerCount = is_array($offers) ? count($offers) : 0;

        // Build URLs
        $encodedUrl = urlencode($permalink);
        $richResultsUrl = 'https://search.google.com/test/rich-results?url=' . $encodedUrl;
        $fbDebuggerUrl = 'https://developers.facebook.com/tools/debug/?q=' . $encodedUrl;
        $checkoutUrl = home_url('/express-shop/' . $funnelSlug . '/checkout/');
        $thankYouUrl = home_url('/express-shop/' . $funnelSlug . '/thank-you/');

        // GA4 Realtime URL (generic - user needs to be logged in)
        $ga4RealtimeUrl = 'https://analytics.google.com/analytics/web/#/realtime/overview';

        ?>
        <div class="hp-testing-metabox">
            
            <!-- External Validation Links -->
            <div class="hp-test-section">
                <h4>📋 Step 1: Schema Validation</h4>
                <p class="description">Test structured data before going live.</p>
                
                <a href="<?php echo esc_url($richResultsUrl); ?>" target="_blank" class="button button-secondary hp-test-btn">
                    🔍 Google Rich Results Test
                </a>
                <p class="hp-test-hint">Verify: "Product" with AggregateOffer, correct price range</p>
                
                <a href="<?php echo esc_url($fbDebuggerUrl); ?>" target="_blank" class="button button-secondary hp-test-btn">
                    📘 Facebook Debugger
                </a>
                <p class="hp-test-hint">Verify: OG image, title, description</p>
            </div>

            <!-- Analytics Journey Checklist -->
            <div class="hp-test-section">
                <h4>📊 Step 2: Analytics Journey</h4>
                <p class="description">Test the full funnel flow in a new browser.</p>
                
                <div class="hp-checklist">
                    <label class="hp-check-item">
                        <input type="checkbox" class="hp-journey-check" data-step="1">
                        <span>1. Open landing page → check <code>view_item</code></span>
                    </label>
                    <a href="<?php echo esc_url($permalink); ?>" target="_blank" class="hp-test-link">→ Landing</a>
                    
                    <label class="hp-check-item">
                        <input type="checkbox" class="hp-journey-check" data-step="2">
                        <span>2. Click buy button → check <code>add_to_cart</code></span>
                    </label>
                    
                    <label class="hp-check-item">
                        <input type="checkbox" class="hp-journey-check" data-step="3">
                        <span>3. Reach checkout → check <code>begin_checkout</code></span>
                    </label>
                    <a href="<?php echo esc_url($checkoutUrl); ?>" target="_blank" class="hp-test-link">→ Checkout</a>
                    
                    <label class="hp-check-item">
                        <input type="checkbox" class="hp-journey-check" data-step="4">
                        <span>4. Complete order → check <code>purchase</code></span>
                    </label>
                    <a href="<?php echo esc_url($thankYouUrl); ?>?order_id=TEST-123" target="_blank" class="hp-test-link">→ Thank You (test)</a>
                </div>
                
                <a href="<?php echo esc_url($ga4RealtimeUrl); ?>" target="_blank" class="button button-secondary hp-test-btn">
                    📈 GA4 Realtime
                </a>
                <p class="hp-test-hint">Watch events appear as you test</p>
            </div>

            <!-- Debug Panel -->
            <div class="hp-test-section">
                <h4>🔧 Step 3: Debug Info</h4>
                
                <table class="hp-debug-table">
                    <tr>
                        <td>Min Price</td>
                        <td><strong><?php echo $minPrice ? '$' . number_format((float)$minPrice, 2) : '<em>Not set</em>'; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Max Price</td>
                        <td><strong><?php echo $maxPrice ? '$' . number_format((float)$maxPrice, 2) : '<em>Not set</em>'; ?></strong></td>
                    </tr>
                    <tr>
                        <td>Brand</td>
                        <td><strong><?php echo esc_html($brand ?: 'Not detected'); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Availability</td>
                        <td><strong><?php echo esc_html($availability ?: 'Not set'); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Offers</td>
                        <td><strong><?php echo $offerCount; ?> offer(s)</strong></td>
                    </tr>
                </table>

                <?php if (!$minPrice || !$maxPrice): ?>
                    <div class="hp-warning-notice">
                        ⚠️ <strong>Prices not calculated.</strong><br>
                        Save the funnel to calculate price range.
                    </div>
                <?php endif; ?>

                <button type="button" class="button button-small hp-copy-console" 
                        data-command="console.log(window.hpFunnelData)">
                    📋 Copy Console Command
                </button>
                <p class="hp-test-hint">Paste in browser console to inspect data</p>
            </div>

            <!-- Quick Actions -->
            <div class="hp-test-section">
                <h4>⚡ Quick Actions</h4>
                <a href="<?php echo esc_url($permalink); ?>" target="_blank" class="button button-primary" style="width:100%; text-align:center;">
                    👁️ Preview Funnel
                </a>
            </div>

        </div>

        <script>
        jQuery(function($) {
            // Copy console command
            $('.hp-copy-console').on('click', function() {
                var cmd = $(this).data('command');
                navigator.clipboard.writeText(cmd).then(function() {
                    alert('Copied to clipboard: ' + cmd);
                });
            });

            // Persist checklist state in localStorage
            var storageKey = 'hp_testing_checklist_<?php echo $postId; ?>';
            var saved = JSON.parse(localStorage.getItem(storageKey) || '{}');

            $('.hp-journey-check').each(function() {
                var step = $(this).data('step');
                if (saved[step]) {
                    $(this).prop('checked', true);
                }
            });

            $('.hp-journey-check').on('change', function() {
                var step = $(this).data('step');
                saved[step] = $(this).is(':checked');
                localStorage.setItem(storageKey, JSON.stringify(saved));
            });
        });
        </script>
        <?php
    }

    /**
     * Inject metabox styles.
     */
    public static function injectStyles(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'hp-funnel') {
            return;
        }

        ?>
        <style>
            .hp-testing-metabox {
                margin: -6px -12px -12px;
            }
            .hp-test-section {
                padding: 12px;
                border-bottom: 1px solid #eee;
            }
            .hp-test-section:last-child {
                border-bottom: none;
            }
            .hp-test-section h4 {
                margin: 0 0 8px;
                font-size: 13px;
                color: #1e1e1e;
            }
            .hp-test-section .description {
                margin: 0 0 10px;
                font-size: 11px;
            }
            .hp-test-btn {
                display: block;
                width: 100%;
                text-align: center;
                margin-bottom: 4px !important;
            }
            .hp-test-hint {
                font-size: 10px;
                color: #666;
                margin: 2px 0 10px;
                padding-left: 4px;
            }
            .hp-checklist {
                background: #f9f9f9;
                border: 1px solid #eee;
                border-radius: 4px;
                padding: 8px;
                margin-bottom: 10px;
            }
            .hp-check-item {
                display: block;
                font-size: 11px;
                padding: 4px 0;
                cursor: pointer;
            }
            .hp-check-item input {
                margin-right: 6px;
            }
            .hp-check-item code {
                background: #e8f4fc;
                padding: 1px 4px;
                font-size: 10px;
            }
            .hp-test-link {
                display: block;
                font-size: 10px;
                color: #2271b1;
                margin: -2px 0 6px 20px;
                text-decoration: none;
            }
            .hp-test-link:hover {
                text-decoration: underline;
            }
            .hp-debug-table {
                width: 100%;
                font-size: 11px;
                border-collapse: collapse;
            }
            .hp-debug-table td {
                padding: 4px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            .hp-debug-table td:first-child {
                color: #666;
                width: 80px;
            }
            .hp-debug-table td:last-child {
                text-align: right;
            }
            .hp-warning-notice {
                background: #fcf0f1;
                border-left: 3px solid #d63638;
                padding: 8px;
                margin: 10px 0;
                font-size: 11px;
            }
            .hp-copy-console {
                width: 100%;
                margin-top: 10px !important;
            }
        </style>
        <?php
    }
}



