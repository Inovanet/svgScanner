#!/usr/bin/env php
<?php
/**
 * Crawler, der nach <form>-Tags auf Unterseiten sucht und diese ausgibt.
 *
 * Aufruf: php crawler.php example.com
 */

$maxPages = 5000;
$visited = [];
$queue = [];

$totalFormTags = 0;
$pagesWithNoForm = 0;
$formOutputCounter = 1;

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
    $foundFormOnPage = false;

    // Suche nach <form>-Tags
    if (preg_match_all('/<form\b[^>]*>.*?<\/form>/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $formTag = $match[0];
            $offset = $match[1];
            $totalFormTags++;

            $foundFormOnPage = true;
            $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;
            echo "Formular $formOutputCounter | URL: $url | Zeile: $lineNumber\n";
            $formOutputCounter++;
        }
    }

    if (!$foundFormOnPage) {
        $pagesWithNoForm++;
    }

    // Suche nach Links auf der Seite, um weitere Seiten zu crawlen
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
echo "Gecrawlte Seiten:            $pagesCrawled\n";
echo "Gefundene <form>-Tags gesamt: $totalFormTags\n";
echo "Seiten ohne Formulare:       $pagesWithNoForm\n";


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
