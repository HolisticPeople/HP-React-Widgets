<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for performing automated SEO audits on funnels.
 * 
 * Mimics Yoast logic to provide instant feedback to AI agents.
 */
class FunnelSeoAuditor
{
    /**
     * Audit a funnel by its ID or data array.
     * 
     * @param int|array $funnel Post ID or funnel data array
     * @return array Audit report
     */
    public static function audit($funnel): array
    {
        if (is_numeric($funnel)) {
            $data = FunnelConfigLoader::getById((int) $funnel);
        } else {
            $data = $funnel;
        }

        if (empty($data)) {
            return [
                'status' => 'error',
                'message' => 'No funnel data provided for audit.',
            ];
        }

        $focusKeyword = $data['seo']['focus_keyword'] ?? '';
        $problems = [];
        $improvements = [];
        $good = [];

        // 1. Keyword Analysis
        self::analyzeKeywords($data, $focusKeyword, $problems, $improvements, $good);

        // 2. Link Analysis
        self::analyzeLinks($data, $problems, $improvements, $good);

        // 3. Media Analysis
        self::analyzeMedia($data, $focusKeyword, $problems, $improvements, $good);

        // 4. Readability Analysis
        self::analyzeReadability($data, $problems, $improvements, $good);

        $score = count($good);
        $total = count($problems) + count($improvements) + count($good);
        $status = 'needs_improvement';
        
        if (empty($problems) && empty($improvements)) {
            $status = 'good';
        } elseif (count($problems) > 3) {
            $status = 'poor';
        }

        return [
            'status' => $status,
            'score' => [
                'good' => count($good),
                'improvements' => count($improvements),
                'problems' => count($problems),
                'total' => $total,
            ],
            'problems' => $problems,
            'improvements' => $improvements,
            'good' => $good,
            'focus_keyword' => $focusKeyword,
        ];
    }

    /**
     * Analyze focus keyword presence and density.
     */
    private static function analyzeKeywords(array $data, string $kw, array &$problems, array &$improvements, array &$good): void
    {
        if (empty($kw)) {
            $problems[] = "No focus keyword set. SEO analysis cannot be performed properly.";
            return;
        }

        $kw = strtolower($kw);

        // SEO Title
        $metaTitle = $data['seo']['meta_title'] ?? $data['funnel']['name'] ?? '';
        if (strpos(strtolower($metaTitle), $kw) === false) {
            $problems[] = "Focus keyword not found in SEO Title.";
        } else {
            $good[] = "Focus keyword found in SEO Title.";
        }

        // Meta Description
        $metaDesc = $data['seo']['meta_description'] ?? '';
        if (empty($metaDesc)) {
            $problems[] = "Meta description is missing.";
        } elseif (strpos(strtolower($metaDesc), $kw) === false) {
            $improvements[] = "Focus keyword not found in Meta Description.";
        } else {
            $good[] = "Focus keyword found in Meta Description.";
        }

        // Slug
        $slug = $data['funnel']['slug'] ?? '';
        if (strpos(strtolower(str_replace('-', ' ', $slug)), $kw) === false) {
            $improvements[] = "Focus keyword (or parts of it) not found in URL slug.";
        } else {
            $good[] = "Focus keyword found in URL slug.";
        }

        // Hero H1
        $heroTitle = $data['hero']['title'] ?? '';
        if (strpos(strtolower($heroTitle), $kw) === false) {
            $problems[] = "Focus keyword not found in Hero Title (H1).";
        } else {
            $good[] = "Focus keyword found in Hero Title (H1).";
        }
    }

    /**
     * Analyze internal and outbound links.
     */
    private static function analyzeLinks(array $data, array &$problems, array &$improvements, array &$good): void
    {
        $html = self::gatherAllContent($data);
        $host = $_SERVER['HTTP_HOST'] ?? 'holisticpeople.com';
        
        // Outbound links (external)
        if (preg_match_all('/href=["\'](https?:\/\/(?!' . preg_quote($host, '/') . ')[^"\']+)["\']/', $html, $matches)) {
            $good[] = "Outbound links found (" . count($matches[1]) . ").";
        } else {
            $improvements[] = "No outbound links found. Link to authority sources to improve SEO.";
        }

        // Internal links
        if (preg_match_all('/href=["\'](https?:\/\/' . preg_quote($host, '/') . '[^"\']+|\/[^"\']+)["\']/', $html, $matches)) {
            $good[] = "Internal links found.";
        } else {
            $improvements[] = "No internal links found. Link to other parts of the shop or protocol.";
        }
    }

    /**
     * Analyze images and ALT tags.
     */
    private static function analyzeMedia(array $data, string $kw, array &$problems, array &$improvements, array &$good): void
    {
        $images = [];
        if (!empty($data['hero']['image'])) $images[] = ['src' => $data['hero']['image'], 'alt' => $data['hero']['image_alt'] ?? ''];
        if (!empty($data['authority']['image'])) $images[] = ['src' => $data['authority']['image'], 'alt' => $data['authority']['image_alt'] ?? ''];
        
        if (isset($data['offers'])) {
            foreach ($data['offers'] as $o) {
                if (!empty($o['image'])) $images[] = ['src' => $o['image'], 'alt' => $o['image_alt'] ?? ''];
            }
        }

        if (empty($images)) {
            $improvements[] = "No images found in the funnel. Visual content improves engagement.";
            return;
        }

        $missingAlt = 0;
        $kwInAlt = 0;
        $kw = strtolower($kw);

        foreach ($images as $img) {
            if (empty($img['alt'])) {
                $missingAlt++;
            } elseif (!empty($kw) && strpos(strtolower($img['alt']), $kw) !== false) {
                $kwInAlt++;
            }
        }

        if ($missingAlt > 0) {
            $problems[] = "$missingAlt image(s) missing ALT text.";
        } else {
            $good[] = "All images have ALT text.";
        }

        if (!empty($kw)) {
            if ($kwInAlt > 0) {
                $good[] = "Focus keyword found in image ALT text.";
            } else {
                $improvements[] = "Focus keyword not found in any image ALT text.";
            }
        }
    }

    /**
     * Analyze readability (basic checks).
     */
    private static function analyzeReadability(array $data, array &$problems, array &$improvements, array &$good): void
    {
        $html = self::gatherAllContent($data);
        $text = strip_tags($html);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($sentences)) return;

        // Sentence length
        $longSentences = 0;
        foreach ($sentences as $s) {
            $words = str_word_count(trim($s));
            if ($words > 20) $longSentences++;
        }

        $percentLong = ($longSentences / count($sentences)) * 100;
        if ($percentLong > 25) {
            $improvements[] = sprintf("%.1f%% of sentences are longer than 20 words. Try shortening them.", $percentLong);
        } else {
            $good[] = "Sentence length is good.";
        }

        // Subheadings
        if (preg_match_all('/<h[2-4]/i', $html, $matches)) {
            $good[] = "Subheadings are used properly.";
        } else {
            $improvements[] = "No subheadings found (H2-H4). Break up long text with subheadings.";
        }
    }

    /**
     * Gather all text content into one HTML string for analysis.
     */
    private static function gatherAllContent(array $data): string
    {
        $content = [];
        if (!empty($data['hero']['title'])) $content[] = "<h1>{$data['hero']['title']}</h1>";
        if (!empty($data['hero']['subtitle'])) $content[] = "<h2>{$data['hero']['subtitle']}</h2>";
        if (!empty($data['hero']['description'])) $content[] = "<div>{$data['hero']['description']}</div>";
        
        if (isset($data['benefits']['items'])) {
            foreach ($data['benefits']['items'] as $b) {
                if (!empty($b['text'])) $content[] = "<p>{$b['text']}</p>";
            }
        }

        if (isset($data['features']['items'])) {
            foreach ($data['features']['items'] as $f) {
                if (!empty($f['title'])) $content[] = "<h3>{$f['title']}</h3>";
                if (!empty($f['description'])) $content[] = "<p>{$f['description']}</p>";
            }
        }

        if (!empty($data['authority']['bio'])) $content[] = "<div>{$data['authority']['bio']}</div>";

        if (isset($data['science']['sections'])) {
            foreach ($data['science']['sections'] as $s) {
                if (!empty($s['title'])) $content[] = "<h3>{$s['title']}</h3>";
                if (!empty($s['description'])) $content[] = "<p>{$s['description']}</p>";
            }
        }

        return implode("\n", $content);
    }
}

