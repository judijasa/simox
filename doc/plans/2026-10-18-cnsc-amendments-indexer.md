# CNSC job-offer amendments indexer — Plan & Progress

Date: 2026-10-18
Repos: simox (this repo).

## Decision

Add a second indexer, `src/scripts/cnsc/indexer/`, that snapshots the
"Avisos informativos" published on `www.cnsc.gov.co` — the amendments to the
job offers already indexed from `simo.cnsc.gov.co` by
`src/scripts/simo/indexer/`. The site has no API (`/jsonapi` -> 404,
`/node?_format=json` -> 406 "Supported formats: html"), so the indexer crawls
HTML. It stores raw metadata only — title, date, body, the associated
convocatoria, and the attachment URLs — in a new snapshot table mirroring
`empleo_snapshot`. The PDFs themselves live under robots.txt's
`Disallow: /sites/default/files/*` and are not downloaded; their URLs are kept
so a later phase can fetch them without a schema change.

Two agents share one crawl/parse/persist core and differ only in stop
condition and cron:

- `backfill()` — manual, one-time full dump (no `#[CronJob]`).
- `update()` — cron incremental; stops at the first page with no new
  `node_id`.

The HTML fetch uses the framework's `Utils\Crawler\HttpClient`. Parsing uses
`DOMDocument`/`DOMXPath`: `sunra/php-simple-html-dom-parser` is not usable on
PHP 8.4 because its selector regex (`[\w-:\*]`) is rejected by PCRE2, so the
package was removed from `composer.json`.

## Changes

- [x] `src/scripts/cnsc/indexer/main.php` — `backfill()` (`#[Agent]` only) and
      `update()` (`#[CronJob(schedule: '0 2 * * *', scope: 'worker')]` +
      `#[Agent]`), both `dbTarget: 'simo0', dbAccount: 'simox'`, sharing one
      `indexer()` core.
- [x] `src/scripts/cnsc/indexer/helper.php` — crawl/paginate
      `/avisos-informativos`, parse `/node/NNNNN` (title, date, body,
      `field_convocatoria_asociada`, attachments), batch and
      `persist_snapshots()`.
- [x] `pkg/cnsc_aviso_snapshot-<GUID>/default.php` + `upgrade.sql` —
      `cnsc_aviso_snapshot` table: `node_id` (unique key), `aviso` JSON,
      `titulo`, `fecha_publicacion`, `cuerpo`, `convocatoria` JSON,
      `documentos` JSON, `created_at`, `last_seen`; upsert
      `ON DUPLICATE KEY UPDATE last_seen = CURRENT_TIMESTAMP`.
- [x] `pkg/simo-C196A24801D24B16/default.php` — add
      `cnsc_aviso_snapshot-<GUID>` to `dependencies` (mirrors how
      `empleo_snapshot-*` is wired).
- [x] `composer.json` / `composer.lock` — the framework is already at the
      commit that provides `Utils\Crawler\HttpClient`; no re-pin was needed.
- [x] `README.md` — document the two agents and the quick-test
      `EMA_TARGET=sandbox phprun 'src/scripts/cnsc/indexer/main.php:backfill()'`
      (and `:update()`), next to the simo indexer.

## Open items

- **List ordering (newest-first)** — `update()`'s stop-after-no-new-node
  depends on `/avisos-informativos` being ordered newest-first; verify in
  Phase 1. If it is not, fall back to a max-`node_id` watermark or a fixed
  page window per run. Verified 2026-10-19: page 0 runs from node 69621
  (2026-10-15) down to older dates, so the stop condition is sound.
- **Convocatoria join key** — the aviso's `field_convocatoria_asociada` and
  simo's `empleo.convocatoria` do not share numeric ids; the join is
  name/code based (reuse `src/utils/string_ops.php`
  `remove_accents()`/`contains()`). Confirmed during Phase 1; normalization is
  out of scope here.
- **`update()` does not refresh edits** — like simo, content freezes on first
  insert and only `last_seen` is refreshed; an edit to an existing aviso (same
  `node_id`) is not re-captured. Revision tracking is a pipeline concern.
