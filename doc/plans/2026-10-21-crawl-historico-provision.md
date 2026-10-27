# Crawl historico & provision offers — Plan & Progress

Date: 2026-10-21 (updated 2026-10-26)
Repos: simox (this repo).

Status: **design settled for publico + historico; provision deferred (Q4).**
All open questions resolved. Nothing implemented. Resume from "Changes".

## Context

`simo.cnsc.gov.co` exposes job offers through disjoint endpoints backed by
different views/stores (source of truth: the live app asset
`js/release/sigeca/sigeca.js`):

| Store | Target | View | Observed size |
|---|---|---|---|
| `ofertaPublicaStore` | `empleos/ofertaPublica/` | `#ofertaEmpleo` (current) | 3,571 |
| `historicoOfertaPublicaStore` | `empleos/ofertaPublica/historico` | `#historicoOfertaEmpleo` | ~66,255 |
| `provisionOfertaPublicaStore` | `empleos/ofertaPublica/provision` | — | unknown |

`src/scripts/simo/indexer/` crawls only the current endpoint, so historical
offers (e.g. OPEC `71275`) are invisible to the system. The current indexer
derives its total from the `#ofertaEmpleo` view (`get_total_job_offers()`);
a historical crawler needs its own total/page logic.

The three endpoints share the same top-level record schema — `access`,
`empleo`, `estadoInscripcion`, `favorito`, `fechaInscripcion`, `id`,
`inscripcionId`, `nivelNombre` — so the existing pipeline
(`simo/pipeline/main.php`) normalizes them identically. That pipeline is
source-agnostic: it batch-scans `empleo_snapshot` and runs the same
`process_batch()` regardless of origin.

Live-site quirks: `curl -k` is required (incomplete cert chain) and responses
need `Accept: application/json`. `search_numeroOPEC` is the correct exact-OPEC
parameter (`search_opec` does not filter by OPEC).

Live-site findings (probed 2026-10-26):

- The historico endpoint has **no stable sort order**: the default order is
  roughly ascending `id` for ~the first 500 pages, then scrambles, and
  `?sort=`/`?dir=` are ignored. A cnsc-style "stop at the first page with
  nothing new" incremental crawl is therefore **not** possible.
- `search_numeroOPEC=<opec>` works on historico (exact match; 1 or 0 rows).
- `search_convocatoria=<id>` works on historico and returns the whole subtree
  under a convocatoria (its children included), which enables bulk
  per-convocatoria fetches.
- The classification flags live on the **parent** convocatoria, not the offer
  payload, and must be fetched via `convocatorias/{child_id}` →
  `convocatoriaPadre.id` → `convocatorias/{parent_id}` (2 calls; 1 when
  parentless).

## Discriminator

Membership is **not** derived from dates or OPEC number. It is the parent
convocatoria's two admin flags, which are mutually exclusive:

| `convocatoriaPadre` flags | Endpoint |
|---|---|
| `publicarOpec=true`, `opecHistorico=false` | current |
| `opecHistorico=true` | historico |

The flags are **not** in the offer payload — `empleo.convocatoria.convocatoriaPadre`
is always `null`. The child convocatoria's own flags are not decisive.

Confirmed 2026-10-26:

- historico OPEC `785` → child `516` → parent `171352852`
  (`publicarOpec=false`, `opecHistorico=true`).
- publico OPEC `193063` → child `548594064` → parent `1770574257`
  (`publicarOpec=true`, `opecHistorico=false`).

## Decision

Give `historico` the same full normalization treatment `publico` already has
(dimension tables + `empleo` + junctures). Keep `empleo` as the full archive;
the website serves current offers by filtering `empleo.opec_historico = 0`
inline (no dedicated projection). Provision is deferred until its enumeration
strategy is understood.

Layout:

```
src/scripts/simo/
  publico/     indexer + pipeline  (moved from src/scripts/simo/*)
  historico/   backfill indexer
  provision/   deferred (Q4)
  convocatoria/  flag-updater (authoritative classifier + relocation driver)
```

Data flow:

```
publico crawler (full scan, newest-first, ~3.5k)  ->  empleo_snapshot (source='publico')
historico backfill (one-shot full crawl, ~66k)   ->  empleo_snapshot (source='historico')
flag-updater (cron, authoritative)               ->  convocatoria.publicar_opec / opec_historico
                                                    detects publico->historico flips,
                                                    bulk-fetches relocated OPECs via
                                                    search_convocatoria=<parent_id>
                                                    -> empleo_snapshot (source='historico')
pipeline (source-agnostic)                       ->  dimension tables + empleo
website (`opec_historico = 0` inline)            ->  current-only listing
```

Key mechanics:

- **Relocation is detected by flag flip, not timestamps.** The flag-updater
  re-checks the parent flags of every convocatoria currently classified
  `publicar_opec=true`, plus any new convocatoria the publico crawler
  discovers. A flip `publicar_opec=true → opec_historico=true` is the
  relocation event. One-way assumption (Q7): a convocatoria never flips back,
  so the historical set is never re-checked. `last_seen` is retained only as
  an observability timestamp, never as a classifier — a timeout heuristic is
  rejected (transient crawl failures read as relocations, and it has the
  inverse blind spot for records relocated before first sight).

- **`empleo_snapshot`** gains `source ENUM('publico','historico','provision')`,
  unique key `(opec, source, fecha_inscripcion)`. `source` is **provenance
  only** (which crawler wrote the row); it is not a website signal.

- **`empleo`** does **not** gain `source`. It gains a single indexed
  discriminator `opec_historico BOOL NOT NULL DEFAULT 0`, mirroring the parent
  convocatoria's flag. The flag-updater is the sole writer of this column
  (denormalized from `convocatoria`); the pipeline leaves it at the default.
  There is **no** precedence-based upsert — `insert_empleos()` keeps the
  framework `BatchInsert`.

- **`convocatoria`** gains `convocatoria_padre_id INT`, `publicar_opec BOOL`,
  `opec_historico BOOL`. The flag-updater fills them from the parent (or self
  when parentless), caching `parent → flags` to cap the N+2 cost. Only the
  current set is tracked (one-way); `backfill()` classifies everything once.

- **Relocation fetch is bulk**: `search_convocatoria=<parent_id>` on historico
  returns every OPEC under the flipped parent, so a flip is one paginated
  crawl rather than per-OPEC `search_numeroOPEC` lookups.

- **Current-only listing**: the website queries `empleo` directly and filters
  inline on `opec_historico = 0` (indexed, join-free) — the **flag**
  (authoritative), never `source`. No dedicated view/table is introduced; a
  materialized `empleo_publico` table is added only if the website shows
  measured latency.

- **Born-historical convocatorias** (created directly `opec_historico=true`,
  never passing through publico) are ignored: the one-shot backfill covers the
  existing archive and steady-state growth is relocation-only. No periodic
  full historico re-crawl.

## Changes (all pending)

- [ ] Move `src/scripts/simo/indexer/` and `src/scripts/simo/pipeline/` to
      `src/scripts/simo/publico/` (keep `#[CronJob]` schedules and
      `#[Agent(dbTarget: 'simo0', dbAccount: 'simox')]`).
- [ ] `src/scripts/simo/historico/indexer/` — `backfill()` (`#[Agent]` only,
      no `#[CronJob]`), sharing the current indexer core but pointed at
      `empleos/ofertaPublica/historico` with its own total/page logic
      (resumable by page cursor; the unstable order makes cross-run page
      cursors unreliable, so dedupe via the snapshot unique key and finish with
      a full pass that reports zero new rows).
- [ ] `src/scripts/simo/provision/` — deferred (Q4).
- [ ] `empleo_snapshot` — add `source`; change unique key to
      `(opec, source, fecha_inscripcion)`.
- [ ] `empleo` — add `opec_historico BOOL NOT NULL DEFAULT 0`, indexed.
      No `source` column, no precedence upsert.
- [ ] `convocatoria` — add `convocatoria_padre_id`, `publicar_opec`,
      `opec_historico`.
- [ ] `src/scripts/simo/convocatoria/` flag-updater — `backfill()` +
      `update()` cron at `0 4 * * *` (worker scope; clears the publico indexer
      `0 2`, pipeline `0 5`, cnsc `0 18`, and the Saturday `0 6`/`0 8`
      maintenance jobs): fetch parent flags, cache parent→flags, detect
      `publicar_opec → opec_historico` flips, flip `empleo` rows, and
      bulk-fetch relocated OPECs via `search_convocatoria=<parent_id>`.
- [ ] Current-only listing — website queries filter `empleo.opec_historico = 0`
      inline; no dedicated view/table unless latency is measured (Q3 decided:
      inline filter).
- [ ] One-time non-destructive `ALTER TABLE` migration for the new columns,
      delivered as ema `rev/` revisions — one revision per schema package, so
      three revisions: `empleo`, `empleo_snapshot`, `convocatoria`. The
      updated `CREATE OR REPLACE` definitions in `pkg/` remain for fresh
      installs. Requires the ema `rev/` revision mechanism (built in `../ema`);
      re-pin `composer` to pull the new ema.
- [ ] `README.md` — document the new agents and quick-test
      `phprun 'src/scripts/simo/historico/indexer/main.php:backfill()'`
      (mirroring the cnsc indexer entry).

## Open items & questions

None — all open questions resolved (Q1-A … Q10). Resume from "Changes".

Resolved and folded into "Decision": Q1-A (full normalization), Q2 (backfill +
flag-driven relocation update, no full re-scan), Q3 (inline filter), Q4
(provision — defer), Q5 (one-time `ALTER`, not re-bootstrap, via ema `rev/`
revisions — one per schema), Q6 (flag-updater `0 4 * * *` worker scope, cached
parent→flags; no cron collision), Q7 (one-way, option A), Q8 (precedence order —
removed), Q9 (`source` semantics — provenance only), Q10 (total/page logic —
backfill only).
