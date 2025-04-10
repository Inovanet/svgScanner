#!/usr/bin/env php
<?php
/**
 * SVG-Crawler mit aria-hidden-Filter, Statistik und nummerierter Ausgabe.
 *
 * Aufruf: php crawler.php example.com
 */

$maxPages = 5000;
$visited = [];
$queue = [];

$totalSvgTags = 0;
$visibleSvgTags = 0;
$pagesWithNoVisibleSvg = 0;
$svgOutputCounter = 1;

if ($argc < 2) {
    fwrite(STDERR, "Usage: php " . $argv[0] . " <domain>\n");
    exit(1);
}

$domain = $argv[1];
if (strpos($domain, 'http://') !== 0 && strpos($domain, 'https://') !== 0) {
    $domain = "http://$domain";
}

$queue[] = $domain;
$pagesCrawled = 0;

while (!empty($queue) && $pagesCrawled < $maxPages) {
    $url = array_shift($queue);

    if (isset($visited[$url])) {
        continue;
    }
    $visited[$url] = true;

    $content = @file_get_contents($url);
    if ($content === false) {
        continue;
    }

    $pagesCrawled++;
    $foundVisibleSvgOnPage = false;

    if (preg_match_all('/<svg\b[^>]*>.*?<\/svg>/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $svgTag = $match[0];
            $offset = $match[1];
            $totalSvgTags++;

            $displaySvg = false;
            if (preg_match('/aria-hidden\s*=\s*("|\')(.*?)\1/i', $svgTag, $attrMatch)) {
                if (strtolower($attrMatch[2]) === "false") {
                    $displaySvg = true;
                }
            } else {
                $displaySvg = true;
            }

            if ($displaySvg) {
                $foundVisibleSvgOnPage = true;
                $visibleSvgTags++;
                $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;
                echo "SVG $svgOutputCounter | URL: $url | Zeile: $lineNumber\n";
                $svgOutputCounter++;
            }
        }
    }

    if (!$foundVisibleSvgOnPage) {
        $pagesWithNoVisibleSvg++;
    }

    if (preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]+)"/i', $content, $linkMatches)) {
        foreach ($linkMatches[1] as $link) {
            $link = trim($link);
            if ($link === '') continue;
            $absoluteUrl = resolveUrl($url, $link);
            if (parse_url($absoluteUrl, PHP_URL_HOST) == parse_url($domain, PHP_URL_HOST)) {
                if (!isset($visited[$absoluteUrl])) {
                    $queue[] = $absoluteUrl;
                }
            }
        }
    }
}

echo "\n--- Statistik ---\n";
echo "Gecrawlte Seiten:              $pagesCrawled\n";
echo "Gefundene SVG-Tags gesamt:    $totalSvgTags\n";
echo "Sichtbare SVG-Tags (Ausgabe): $visibleSvgTags\n";
echo "Seiten ohne sichtbare SVGs:   $pagesWithNoVisibleSvg\n";


function resolveUrl($base, $rel) {
    if (parse_url($rel, PHP_URL_SCHEME) !== null) {
        return $rel;
    }

    $baseParts = parse_url($base);
    $baseScheme = $baseParts['scheme'];
    $baseHost   = $baseParts['host'];
    $basePath   = isset($baseParts['path']) ? $baseParts['path'] : '/';
    $basePath   = preg_replace('#/[^/]*$#', '/', $basePath);

    if (substr($rel, 0, 1) === '/') {
        $path = $rel;
    } else {
        $path = $basePath . $rel;
    }

    $segments = explode('/', $path);
    $resolved = [];
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        } elseif ($segment === '..') {
            array_pop($resolved);
        } else {
            $resolved[] = $segment;
        }
    }
    $normalizedPath = '/' . implode('/', $resolved);
    return $baseScheme . '://' . $baseHost . $normalizedPath;
}
