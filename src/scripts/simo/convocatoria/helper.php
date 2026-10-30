<?php
require 'vendor/autoload.php';

use Utils\Crawler\HttpClient;

/**
 * Fetches one convocatoria by its SIMO id, or null when unavailable.
 *
 * @return array<string, mixed>|null
 */
function fetch_convocatoria(HttpClient $client, string $base_url, int $code){
    $response = $client->get($base_url . '/convocatorias/' . $code);

    if ($response['status'] >= 400) {
        return null;
    }

    $data = json_decode($response['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return null;
    }

    return $data;
}

/**
 * Resolves the authoritative classification flags for a convocatoria.
 *
 * The parent convocatoria (or the convocatoria itself when parentless) is the
 * authority for `publicarOpec`/`opecHistorico`; the offer payload never
 * carries them. Results are cached per parent id to cap the N+2 lookup cost.
 *
 * @param array<string, mixed> $conv
 * @param array $parent_cache
 * @return array{convocatoria_padre_id: ?int, publicar_opec: bool, opec_historico: bool}|null
 */
function authoritative_flags(HttpClient $client, string $base_url, array $conv, array &$parent_cache){
    $parent = $conv['convocatoriaPadre'] ?? null;
    $parent_id = is_array($parent) ? ($parent['id'] ?? null) : null;

    if ($parent_id === null) {
        return array(
            'convocatoria_padre_id' => null,
            'publicar_opec' => (bool)($conv['publicarOpec'] ?? false),
            'opec_historico' => (bool)($conv['opecHistorico'] ?? false),
        );
    }

    $parent_id = (int)$parent_id;

    if (!array_key_exists($parent_id, $parent_cache)) {
        $parent_conv = fetch_convocatoria($client, $base_url, $parent_id);
        $parent_cache[$parent_id] = $parent_conv === null
            ? null
            : array(
                'publicar_opec' => (bool)($parent_conv['publicarOpec'] ?? false),
                'opec_historico' => (bool)($parent_conv['opecHistorico'] ?? false),
            );
    }

    $flags = $parent_cache[$parent_id];
    if ($flags === null) {
        return null;
    }

    return array(
        'convocatoria_padre_id' => (int)$parent_id,
        'publicar_opec' => $flags['publicar_opec'],
        'opec_historico' => $flags['opec_historico'],
    );
}

/**
 * Fetches the flags of a classification source (a parent convocatoria, or a
 * parentless convocatoria acting as its own source) by its SIMO id.
 *
 * @param array $source_cache
 * @return array{publicar_opec: bool, opec_historico: bool}|null
 */
function source_flags(HttpClient $client, string $base_url, int $source_id, array &$source_cache){
    if (array_key_exists($source_id, $source_cache)) {
        return $source_cache[$source_id];
    }

    $conv = fetch_convocatoria($client, $base_url, $source_id);
    $source_cache[$source_id] = $conv === null
        ? null
        : array(
            'publicar_opec' => (bool)($conv['publicarOpec'] ?? false),
            'opec_historico' => (bool)($conv['opecHistorico'] ?? false),
        );

    return $source_cache[$source_id];
}
