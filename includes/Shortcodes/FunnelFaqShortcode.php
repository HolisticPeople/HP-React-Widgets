<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelFaq shortcode - renders FAQ accordion section.
 * 
 * Usage:
 *   [hp_funnel_faq funnel="illumodine"]
 *   [hp_funnel_faq funnel="illumodine" allow_multiple="false"]
 */
class FunnelFaqShortcode
{
    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render(array $atts = []): string
    {
        AssetLoader::enqueue_bundle();

        $atts = shortcode_atts([
            'funnel'         => '',
            'id'             => '',
            'title'          => '',
            'subtitle'       => '',
            'allow_multiple' => 'false',
        ], $atts);

        // Load config
        $config = $this->loadConfig($atts);
        if (!$config) {
            return $this->renderError('Funnel not found or inactive.');
        }

        $faqs = $this->extractFaqs($config);

        if (empty($faqs)) {
            if (current_user_can('manage_options')) {
                return $this->renderError('No FAQs configured for this funnel.');
            }
            return '';
        }

        $props = [
            'title'         => !empty($atts['title']) ? $atts['title'] : (get_field('faq_title', $config['id']) ?: 'Frequently Asked Questions'),
            'subtitle'      => $atts['subtitle'],
            'faqs'          => $faqs,
            'allowMultiple' => filter_var($atts['allow_multiple'], FILTER_VALIDATE_BOOLEAN),
        ];

        return $this->renderWidget('FunnelFaq', $config['slug'], $props);
    }

    /**
     * Extract FAQs from config.
     *
     * @param array $config Funnel config
     * @return array FAQs array
     */
    private function extractFaqs(array $config): array
    {
        $faqList = get_field('faq_list', $config['id']) ?: [];
        $result = [];

        foreach ($faqList as $faq) {
            if (!empty($faq['question']) && !empty($faq['answer'])) {
                $result[] = [
                    'question' => $faq['question'],
                    'answer'   => $faq['answer'],
                ];
            }
        }

        return $result;
    }

    private function loadConfig(array $atts): ?array
    {
        $config = null;
        if (!empty($atts['id'])) {
            $config = FunnelConfigLoader::getById((int) $atts['id']);
        } elseif (!empty($atts['funnel'])) {
            $config = FunnelConfigLoader::getBySlug($atts['funnel']);
        } else {
            // Auto-detect from current post context (for use in CPT templates)
            $config = FunnelConfigLoader::getFromContext();
        }
        return ($config && !empty($config['active'])) ? $config : null;
    }

    private function renderError(string $message): string
    {
        if (current_user_can('manage_options')) {
            return sprintf(
                '<div class="hp-funnel-error" style="padding: 10px; background: #fee; color: #c00; border: 1px solid #c00; border-radius: 4px; font-size: 12px;">%s</div>',
                esc_html($message)
            );
        }
        return '';
    }

    private function renderWidget(string $component, string $slug, array $props): string
    {
        $rootId = 'hp-funnel-faq-' . esc_attr($slug) . '-' . uniqid();
        return sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-faq-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}














