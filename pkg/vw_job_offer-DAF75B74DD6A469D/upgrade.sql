CREATE OR REPLACE VIEW vw_job_offer AS
SELECT
    (
      SELECT n.nombre
      FROM nivel n
      WHERE n.id = jo.nivel_id
    ) AS nivel,
    jo.cierre,
    jo.salario,
    /*
    (
        SELECT GROUP_CONCAT(nombre SEPARATOR ', ')
        FROM departamento y
        WHERE y.id IN (
            SELECT jol.departamento_id
            FROM job_offer_location AS jol
            WHERE jol.opec = jo.opec
    ) AS departamento,
    */
    /*
    (
        SELECT GROUP_CONCAT(d.iso SEPARATOR ', ')
        FROM departamento d
        WHERE d.id IN (
            SELECT jol.departamento_id
            FROM job_offer_location jol
            WHERE jol.opec = jo.opec
        )
    ) AS departamento_iso, -- use when not in search by dpto
    (
        WITH location_ids AS (
            SELECT jol.municipio_id, jol.departamento_id
            FROM job_offer_location jol
            WHERE jol.opec = jo.opec
        ),
        location AS (
            SELECT
                (
                    SELECT m.nombre
                    FROM municipio m
                    WHERE m.id = li.municipio_id
                ) AS municipio_nombre,
                (
                    SELECT d.iso
                    FROM departamento d
                    WHERE d.id = li.departamento_id
                ) AS departamento_iso
            FROM location_ids li
        ),
        location_agg AS (
            SELECT
                l.departamento_iso,
                GROUP_CONCAT(
                    l.municipio_nombre
                    ORDER BY l.municipio_nombre
                    SEPARATOR ','
                ) AS municipios_per_departamento
            FROM location l
            GROUP BY l.departamento_iso
        )
        SELECT
            JSON_OBJECTAGG(
                la.departamento_iso,
                la.municipios_per_departamento
            ) AS json_data
        FROM location_agg la
    ) AS municipio, -- use when in search by departamento
    */
    jo.opec,
    jo.keywords,
    (
        SELECT d.nombre
        FROM denominacion d
        WHERE d.id = jo.denominacion_id
    ) AS denominacion,
    (
        SELECT e.descripcion -- TODO: replace by more succint
        FROM estudio e
        WHERE e.id = (
            SELECT r.estudio_id
            FROM requisito r
            WHERE r.id = jo.vacante_id
        )
    ) AS estudio
FROM job_offer jo;

GRANT SELECT ON {{dbname}}.vw_job_offer TO 'public'@'{{servername}}';
