# Convocatoria flags: own-flag resolution & parent rows

Date: 2026-10-31
Repos: simox (this repo).
Status: final — decisions made, ready to implement.

## Context

`convocatoria.opec_historico` currently stores the flag of the convocatoria's
**parent** (or of itself when parentless), resolved by
`src/scripts/simo/convocatoria/helper.php::authoritative_flags()`. This amends
the "parent is authoritative" decision in
`doc/plans/2026-10-21-crawl-historico-provision.md`.

Empirically that resolution is wrong. A live `simo0` query joins
`empleo` (`opec_historico = 0`) with `empleo_snapshot` (`source = 'historico'`)
and returns 248 rows — offers the historico archive contains but which are
classified as current.

## Findings (2026-10-31, live simo0 + SIMO API)

- The 248 mismatch opecs sit in exactly **5 convocatorias, all children**
  (`convocatoria_padre_id IS NOT NULL`); 0 parentless. They collapse to only
  **2 distinct parents**.
- No convocatoria mixes sources: `source` is homogeneous per convocatoria.
- No reverse mismatch: 0 `opec_historico = 1` rows have `source = 'publico'`.
- Child `1173`: its own `opecHistorico = true`, parent `38201920`
  `opecHistorico = false`; its offers are present in
  `empleos/ofertaPublica/historico`. Archive membership tracks the **child's**
  own flag, not the parent's — the opposite of the current resolution.
- The child's own flag is never stored; only the parent-resolved value is.

## Working model (hypothesis)

The parent flag is a **roll-up**: `parent.opecHistorico = true` only after all
its children are `true`. Archive membership is per-child (each offer follows
its own child's flag); the parent is an aggregate, not the atomic signal.

Consistent with the data: every child whose parent flag is `1` is
`source='historico'` (0 publico) — the forward direction holds. The reverse is
**not** automatic: parent `38201920` is `false` although its 3 children are all
`historico` (confirmed via `search_convocatoria`). So the parent flag is not a
live AND-roll-up — it lags or is set independently (which one is unproven).

Recording parents makes this observable over time.

## Decisions

1. **Own flags.** Store the convocatoria's own `opecHistorico` and
   `publicarOpec` — never the parent's.
2. **Record parents as rows.** Persist parent convocatorias in `convocatoria`.
3. **Add `convocatoria.descripcion`.** The one API field we lack.

## Schema change

Add to `convocatoria`:

```sql
descripcion TEXT,
```

Notes:

- `entidad JSON` already stores the full `entidad` object (`nit`, `nombre`, …)
  returned by the API — no change needed there.
- `convocatoria_padre_id` already holds the parent's SIMO id, which equals the
  parent's `code`. Once parent rows exist, the link resolves to a real row.

## Implementation steps

### A. Own flags (`src/scripts/simo/convocatoria/`)

- `helper.php::authoritative_flags()` — resolve `opec_historico` /
  `publicar_opec` from the convocatoria itself, not its parent.
- `helper.php::source_flags()` — re-check the stored classification source on
  self, not parent.
- `main.php::classify_row()` — unchanged structurally; it already writes
  `convocatoria_padre_id`, `publicar_opec`, `opec_historico`.
- `main.php::propagate_empleo_flags()` — unchanged (one-way 0→1). Re-running
  `backfill()`/`update()` flips the 248 `opec_historico` rows 0→1.

### B. Record parents

- After classifying a child with a non-null `convocatoriaPadre`, fetch the
  parent via `helper.php::fetch_convocatoria()` (the same `/convocatorias/{id}`
  endpoint) and upsert it with `code = parent.id`, its own flags, `nombre`,
  `codigo`, `agno`, `entidad`, `descripcion`. Its `convocatoria_padre_id` is
  whatever the API returns (null, or a grandparent id — no recursion needed;
  0 three-level chains observed).

### C. `descripcion`

- Populate `descripcion` from the API's `descripcion` for every convocatoria
  row (child and parent) on upsert, via the same fetch.

### D. Validation

- Re-run `backfill()` (or `update()`), then assert 0 rows with
  `opec_historico = 0` and `source = 'historico'`.
- Assert all 58 known parents are present as rows with `code = parent SIMO id`.
- Assert `descripcion` is populated on both child and parent rows.

## Open questions (post-implementation)

- Does parent `38201920`'s `opecHistorico` eventually flip to `true` now that
  its children are all historico? (Observation settles lag vs independent.)
- Is "child own-flag drives archive membership" robust beyond `1173`? Hunt
  counterexamples in the other 4 mismatched children (`554`, `2429`,
  `807831710`, `807838595`).
