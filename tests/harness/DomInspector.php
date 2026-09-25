<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Harness;

use DOMDocument;
use DOMXPath;

class DomInspector {
    public static function loadHtml(string $html): DOMXPath {
        $dom = new DOMDocument();
        // Suppress warnings from HTML5 elements unknown to old libxml
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    public static function extractCssRules(string $cssContent): array {
        $rules = [];
        // Remove comments
        $clean = preg_replace('/\/\*.*?\*\//s', '', $cssContent);

        // Separate media queries vs top-level rules
        // Match @media blocks
        preg_match_all('/@media[^{]+\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s', $clean, $mediaMatches);
        $mediaBlocks = $mediaMatches[0] ?? [];

        // Remove media blocks to parse standard rules
        $topLevel = preg_replace('/@media[^{]+\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $clean);

        // Parse rules in string
        $parseBlock = function(string $blockStr) {
            $parsed = [];
            preg_match_all('/([^{]+)\{([^}]+)\}/s', $blockStr, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $selectors = array_map('trim', explode(',', $m[1]));
                $declarationsRaw = explode(';', $m[2]);
                $declarations = [];
                foreach ($declarationsRaw as $dec) {
                    if (str_contains($dec, ':')) {
                        [$prop, $val] = explode(':', $dec, 2);
                        $declarations[strtolower(trim($prop))] = strtolower(trim($val));
                    }
                }
                foreach ($selectors as $sel) {
                    if (!isset($parsed[$sel])) {
                        $parsed[$sel] = [];
                    }
                    $parsed[$sel] = array_merge($parsed[$sel], $declarations);
                }
            }
            return $parsed;
        };

        $rules['topLevel'] = $parseBlock($topLevel);
        $rules['media'] = [];
        foreach ($mediaBlocks as $mediaBlock) {
            preg_match('/@media\s*([^{]+)\{(.*)\}/s', $mediaBlock, $m);
            if ($m) {
                $query = trim($m[1]);
                $rules['media'][$query] = $parseBlock($m[2]);
            }
        }

        return $rules;
    }

    public static function getCssDeclaration(array $parsedCss, string $selector, string $property): ?string {
        // Look in top-level first
        foreach ($parsedCss['topLevel'] as $sel => $props) {
            if ($sel === $selector || str_contains($sel, $selector)) {
                if (isset($props[strtolower($property)])) {
                    return $props[strtolower($property)];
                }
            }
        }
        return null;
    }
}
