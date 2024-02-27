CREATE OR REPLACE VIEW vw_job_offer AS
SELECT
    (SELECT nombre FROM nivel z WHERE z.id = x.nivel_id) AS nivel,
    x.cierre,
    x.salario,
    (
        SELECT nombre
        FROM dpto_colombia y
        WHERE y.id = x.departamento_id
    ) AS departamento,
    x.municipio,
    x.opec,
    x.keywords,
    x.denominacion,
    x.estudio
FROM job_offer AS x WHERE x.vacantes > 0;
/*
SELECT
    z.nombre as nivel,
    x.cierre,
    x.salario,
    y.nombre AS departamento,
    x.municipio,
    x.opec,
    x.keywords,
    x.denominacion,
    x.estudio
FROM job_offer AS x
    JOIN dpto_colombia AS y
ON x.departamento_id = y.id
    JOIN nivel AS z
ON x.nivel_id = z.id;
*/

GRANT SELECT ON simo.vw_job_offer TO 'public'@'127.0.0.1';
