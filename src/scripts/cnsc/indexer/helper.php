<?php
require 'vendor/autoload.php';

use Utils\Crawler\HttpClient;
use Utils\DatabaseOps\GuardedInsert;
use Utils\Logger;

/**
 * Returns the aviso node ids listed on one page of /avisos-informativos.
 *
 * The site's pager is zero-based and newest-first.
 *
 * @return list<int>
 */
function crawl_aviso_node_ids(HttpClient $client, string $base_url, int $page){
    $url = $base_url. '/avisos-informativos?field_convocatoria_asociada_target_id&page='. $page;
    $response = $client->get($url);

    if ($response['status'] >= 400) {
        throw new RuntimeException("HTTP Error {$response['status']}: $url");
    }

    $dom = parse_html($response['body']);
    if ($dom === null) {
        throw new RuntimeException("Could not parse HTML: $url");
    }

    $xpath = new DOMXPath($dom);
    $links = $xpath->query("//div[contains(@class,'newstitle')]//h2/a");
    if ($links === false) {
        return array();
    }

    $node_ids = array();
    foreach ($links as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }
        if (preg_match('#^/node/(\d+)$#', $link->getAttribute('href'), $matches)) {
            $node_ids[] = (int)$matches[1];
        }
    }

    return array_values(array_unique($node_ids));
}

/**
 * Fetches and parses one aviso node, or null when the node is not available.
 */
function crawl_aviso(HttpClient $client, string $base_url, int $node_id){
    $url = $base_url. '/node/'. $node_id;
    $response = $client->get($url);

    if ($response['status'] >= 400) {
        return null;
    }

    return parse_aviso($response['body'], $base_url, $node_id);
}

/**
 * Parses an aviso node page into the snapshot's metadata.
 *
 * @return array{
 *     node_id: int,
 *     url: string,
 *     titulo: string,
 *     fecha_publicacion: ?string,
 *     cuerpo: string,
 *     convocatoria: ?array{nombre: string, url: ?string},
 *     documentos: list<array{titulo: string, url: string}>
 * }|null
 */
function parse_aviso($html, $base_url, $node_id){
    $dom = parse_html($html);
    if ($dom === null) {
        return null;
    }

    $xpath = new DOMXPath($dom);
    $title_node = first_node($xpath, "//h1[contains(@class,'page-title')]");
    $date_node = first_node(
        $xpath,
        "//*[contains(@class,'node__submitted')]//*[contains(@class,'field--name-created')]");
    $body_node = first_node(
        $xpath,
        "//*[contains(@class,'node__content')]//*[contains(@class,'field--name-body')]");
    $convocatoria_node = first_node(
        $xpath,
        "//*[contains(@class,'field--name-field-convocatoria-asociada')]//a");

    $convocatoria = null;
    if ($convocatoria_node !== null) {
        $convocatoria = array(
            'nombre' => clean_text($convocatoria_node->textContent),
            'url' => absolute_url($convocatoria_node->getAttribute('href'), $base_url),
        );
    }

    $documentos = array();
    $links = $xpath->query("//*[contains(@class,'paragraph--type--documentos')]//a");
    if ($links !== false) {
        foreach ($links as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }
            $url = absolute_url($link->getAttribute('href'), $base_url);
            if ($url === null) {
                continue;
            }
            $documentos[$url] = array(
                'titulo' => clean_text($link->textContent),
                'url' => $url,
            );
        }
    }

    $cuerpo = '';
    if ($body_node !== null) {
        foreach ($body_node->childNodes as $child) {
            $cuerpo .= $dom->saveHTML($child) ?: '';
        }
    }

    return array(
        'node_id' => (int)$node_id,
        'url' => rtrim($base_url, '/'). '/node/'. $node_id,
        'titulo' => $title_node !== null ? clean_text($title_node->textContent) : '',
        'fecha_publicacion' => $date_node !== null
            ? parse_fecha_publicacion($date_node->textContent)
            : null,
        'cuerpo' => trim($cuerpo),
        'convocatoria' => $convocatoria,
        'documentos' => array_values($documentos),
    );
}

/**
 * Parses the site's "Jue, 15/10/2026 - 15:38" date into a SQL DATETIME value.
 */
function parse_fecha_publicacion($text){
    if (!preg_match('#(\d{2})/(\d{2})/(\d{4})\s*-\s*(\d{2}):(\d{2})#', $text, $matches)) {
        return null;
    }

    return "$matches[3]-$matches[2]-$matches[1] $matches[4]:$matches[5]:00";
}

/**
 * Returns the subset of $node_ids not yet stored in cnsc_aviso_snapshot.
 *
 * @param list<int> $node_ids
 * @return list<int>
 */
function new_node_ids($conn, array $node_ids){
    if (count($node_ids) === 0) {
        return array();
    }

    $placeholders = implode(',', array_fill(0, count($node_ids), '?'));
    $stmt = $conn->prepare(
        "SELECT node_id FROM cnsc_aviso_snapshot WHERE node_id IN ($placeholders)");
    $stmt->execute($node_ids);
    $known = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    return array_values(array_diff($node_ids, $known));
}

function persist_snapshots($conn, $avisos){
    if ($avisos === []) return;

    $rows = [];
    foreach ($avisos as $aviso) {
        $rows[] = [
            $aviso['node_id'],
            json_encode($aviso),
            $aviso['titulo'],
            $aviso['fecha_publicacion'],
            $aviso['cuerpo'],
            $aviso['convocatoria'] === null ? null : json_encode($aviso['convocatoria']),
            json_encode($aviso['documentos']),
        ];
    }

    // Only genuinely-new nodes consume a sequence id; the guard on node_id
    // (the table's unique key) filters re-offered rows before id allocation.
    // last_seen stays insert-time-only here, matching the previous behavior.
    try {
        GuardedInsert::insert(
            $conn,
            'cnsc_aviso_snapshot',
            ['id', 'node_id', 'aviso', 'titulo', 'fecha_publicacion', 'cuerpo', 'convocatoria', 'documentos'],
            ['node_id'],
            $rows,
            count($rows),
            ['id' => 'NEXT VALUE FOR cnsc_aviso_snapshot_id_seq']
        );
    } catch (Throwable $e) {
        Logger::info("ERROR: could not persist " . count($avisos) . " aviso snapshot(s).");
        throw new RuntimeException(
            "Could not persist aviso snapshots: {$e->getMessage()}",
            0,
            $e
        );
    }
}

/**
 * Resolves a link found on the CNSC site to an absolute URL.
 */
function absolute_url($href, $base_url){
    $href = trim((string)$href);
    if ($href === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $href)) {
        return $href;
    }
    if (str_starts_with($href, '//')) {
        return 'https:'. $href;
    }

    return rtrim($base_url, '/'). '/'. ltrim($href, '/');
}

function clean_text($text){
    return trim(preg_replace('/\s+/u', ' ', (string)$text) ?? '');
}

/**
 * Loads an HTML string into a DOMDocument, or null when it cannot be parsed.
 *
 * The sunra/php-simple-html-dom-parser package is not usable on PHP 8.4: its
 * selector regex ("[\w-:\*]") is rejected by PCRE2. DOMDocument/XPath covers
 * the same need with no extra dependency, so the package was dropped.
 */
function parse_html($html){
    if ($html === '') {
        return null;
    }

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $loaded ? $dom : null;
}

/**
 * Returns the first node matching an XPath expression, or null.
 */
function first_node(DOMXPath $xpath, $expression){
    $nodes = $xpath->query($expression);
    if ($nodes === false || $nodes->length === 0) {
        return null;
    }

    $node = $nodes->item(0);

    return $node instanceof DOMElement ? $node : null;
}
