CREATE OR REPLACE VIEW vw_job_offer AS
SELECT
    z.nombre as nivel,
    x.cierre,
    x.salario,
    y.nombre AS departamento,
    x.municipio,
    x.opec,
    x.keywords
FROM job_offer AS x
    JOIN dpto_colombia AS y
ON x.departamento_id = y.id
    JOIN nivel AS z
ON x.nivel_id = z.id;

GRANT SELECT ON simo.vw_job_offer TO 'public'@'127.0.0.1';
