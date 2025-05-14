/* To DROP a parent tbl, first you need to DROP its child tbls */
DROP TABLE IF EXISTS job_offer;
CREATE OR REPLACE TABLE job_offer_snapshot (
    id INT AUTO_INCREMENT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pagina SMALLINT NOT NULL DEFAULT -1,
    nivel VARCHAR(100) NOT NULL DEFAULT 'NONE',
    denominacion VARCHAR(250) NOT NULL DEFAULT 'NONE',
    grado TINYINT NOT NULL DEFAULT -1,
    codigo VARCHAR(10) NOT NULL DEFAULT 'NONE',
    opec INT NOT NULL DEFAULT -1,
    salario VARCHAR(50) NOT NULL DEFAULT 'NONE',
    vigencia_salarial YEAR NOT NULL DEFAULT 0000,
    convocatoria VARCHAR(250) NOT NULL DEFAULT 'NONE',
    entidad_codigo SMALLINT NOT NULL DEFAULT -1,
    cierre DATE NOT NULL DEFAULT '0000-00-00',
    vacantes SMALLINT NOT NULL DEFAULT -1,
    estudio TEXT NOT NULL DEFAULT 'NONE',
    experiencia TEXT NOT NULL DEFAULT 'NONE',
    dependencia VARCHAR(10000) NOT NULL DEFAULT 'NONE',
    municipio VARCHAR(10000) NOT NULL DEFAULT 'NONE',
    /*  e.g. `Cali`, `Buga, Cartago`, `Leticia`
        all at once for non-api approaches.
        For non-api approaches we are unable to
        link a municipio with a departamento.

        The field departamento is obtained using
        "search by departamento".
        Using SET or JSON because MariaDB has no array types.
        There're 33 departamentos. The id 34 is for 'No_Aplica'.
    */
    departamento_ids SET(
        '1', '2', '3', '4', '5', '6', '7', '8', '9', '10',
        '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
        '21', '22', '23', '24', '25', '26', '27', '28', '29', '30',
        '31', '32', '33', '34') NOT NULL DEFAULT '',
    otros TEXT NOT NULL DEFAULT 'NONE',

    PRIMARY KEY(id),
    UNIQUE KEY unique_job_offer_snapshot (
    /*  Don't include departamento_ids here since
        you always want to UPDATE new values of
        departamento when the rest of values is
        the same. */
        nivel,
        denominacion,
        grado,
        codigo,
        opec,
        salario,
        vigencia_salarial,
        convocatoria,
        entidad_codigo,
        cierre,
        vacantes,
        estudio,
        experiencia,
        dependencia,
        municipio,
        departamento_ids,
        otros
    )
);
/*
    Note:
    We use pseudo-NULL values as default values
    because unique keys are unable to prevent
    duplicates in the presence of NULL values.

*/
