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
 * The convocatoria's own `publicarOpec`/`opecHistorico` are authoritative; the
 * offer payload never carries them. The parent link is recorded (its SIMO id)
 * but the parent's flags are never read here: archive membership follows each
 * convocatoria's own flag, not its parent's roll-up.
 *
 * @param array<string, mixed> $conv a fetched `/convocatorias/{id}` payload
 * @return array{convocatoria_padre_id: ?int, publicar_opec: bool, opec_historico: bool}
 */
function authoritative_flags(array $conv){
    $parent = $conv['convocatoriaPadre'] ?? null;
    $parent_id = is_array($parent) ? ($parent['id'] ?? null) : null;

    return array(
        'convocatoria_padre_id' => $parent_id === null ? null : (int)$parent_id,
        'publicar_opec' => (bool)($conv['publicarOpec'] ?? false),
        'opec_historico' => (bool)($conv['opecHistorico'] ?? false),
    );
}

/**
 * Re-reads the classification flags of a convocatoria from its own payload —
 * the classification source is always the convocatoria itself, never its
 * parent.
 *
 * @param array<string, mixed> $conv a fetched `/convocatorias/{id}` payload
 * @return array{publicar_opec: bool, opec_historico: bool}
 */
function source_flags(array $conv){
    return array(
        'publicar_opec' => (bool)($conv['publicarOpec'] ?? false),
        'opec_historico' => (bool)($conv['opecHistorico'] ?? false),
    );
}
