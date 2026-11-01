# 2026-10-31 — Drop convocatoria classification columns

Supersedes `2026-10-31-convocatoria-own-flag-parent-rows.md`. That effort's
"store the convocatoria's own flags, parent link, and descripcion" is reversed
entirely: every classification/hydration column is dropped and the module that
wrote them is deleted.

## Summary

Remove every convocatoria "classification" column and the module that wrote them:

- `convocatoria.publicar_opec`, `convocatoria.opec_historico`,
  `convocatoria.convocatoria_padre_id`, `convocatoria.descripcion` — dropped.
- `empleo.opec_historico` and `ix_empleo_opec_historico` — dropped.
- `src/scripts/simo/convocatoria/` (module) — deleted.

`convocatoria` reverts to a pure identity/dimension table — only the
pipeline-written columns (`id, code, nombre, agno, codigo, entidad, entidad_id,
es_tipo_fase, tipo_proceso, no_cobro_nivel, no_cobro_opec, tipo_proc_sele_id,
created_at`).

`empleo` becomes source-agnostic: a unified pool of every offer, with no flag
distinguishing publico- from historico-sourced rows. `source` stays on
`empleo_snapshot` as crawl provenance only, never propagated onto `empleo`.

## Rationale

`publicar_opec`/`opec_historico` are time-varying SIMO status flags; storing
them as plain columns records only the current value — every flip is silently
lost, a permanent integrity gap. Closing it needs a convocatoria history table,
which isn't worth it: the flags were never a reliable signal anyway.

`convocatoria_padre_id` and `descripcion` were hydration-only fields available
only via a dedicated `/convocatorias/{id}` fetch (the offer payload does not
carry them — verified: 0 of 55,143 snapshots have either key), and they buy
nothing the website uses. Drop the columns and the module.

## Decisions

1. `empleo` treats publico- and historico-sourced data equally (no source flag).
2. Drop `convocatoria.publicar_opec` and `convocatoria.opec_historico`.
3. Drop `convocatoria.convocatoria_padre_id` and `convocatoria.descripcion`.
4. Drop `empleo.opec_historico` and `ix_empleo_opec_historico`.
5. Delete the `src/scripts/simo/convocatoria/` module.
6. Website drops the `opec_historico = 0` predicate; the closure/date filters
   already define the listings.

## Changes

### A. Schema

Fresh installs (`pkg/*/upgrade.sql`) and existing DBs (chained `rev/`
revisions) both change.

1. `pkg/convocatoria-CP5LH0NIV4WY9J5B/upgrade.sql`: remove
   `convocatoria_padre_id INT,`, `publicar_opec BOOL,`, `opec_historico BOOL,`,
   and `descripcion TEXT,` from `CREATE OR REPLACE TABLE convocatoria`.

2. `pkg/empleo-9A645583F8F24B28/upgrade.sql`: remove the
   `-- opec_historico = 1 implies ...` comment, the
   `opec_historico BOOL NOT NULL DEFAULT 0,` line, and
   `INDEX ix_empleo_opec_historico(opec_historico),`.

3. Scaffold one new revision per package (`vendor/bin/ema revision pkg/<pkg>`,
   which chains to the current head and rewires the package `default.php`):

   - convocatoria (chains off `2026-10-31-convocatoria-R0W8ODGCOAXZBPOF`):
     ```sql
     ALTER TABLE convocatoria
         DROP COLUMN convocatoria_padre_id,
         DROP COLUMN publicar_opec,
         DROP COLUMN opec_historico,
         DROP COLUMN descripcion;
     ```
   - empleo (chains off `2026-10-27-empleo-R02TT67TBZJZ80TS`):
     ```sql
     ALTER TABLE empleo
         DROP INDEX ix_empleo_opec_historico,
         DROP COLUMN opec_historico;
     ```

No other code reads these convocatoria columns (verified by grep: the only
readers are the module being deleted).

### B. Pipeline — `src/scripts/simo/pipeline/main.php`

In `insert_empleos()`:

- Remove the source stamp `$row['source'] === 'historico' ? 1 : 0`.
- Remove `'opec_historico'` from the `GuardedInsert::insert` column list.
- Drop `source` from the `main()` SELECT (it was only consumed by the stamp).

`insert_convocatorias()` already writes only identity fields — unchanged.

### C. Delete the convocatoria module

Remove `src/scripts/simo/convocatoria/main.php` and `helper.php`. The module
existed only to write the dropped columns; nothing else calls it.

### D. Website

- `public/_table.php`: remove `AND e.opec_historico = 0` from the count query
  and the main query.
- `public/insight.php`: remove `opec_historico = 0 AND` from the three count
  queries.

## Validation

- `php -l` on changed files; full-repo `phpstan` (level 5);
  `hooks/check-php-attributes.php`.
- `ema upgrade srv/<name>-<GUID>` on each target instance; assert the columns
  are gone (`DESCRIBE convocatoria`, `DESCRIBE empleo`).
- Website renders without `opec_historico`.
- Grep confirms no remaining references to the dropped columns or the deleted
  module.
