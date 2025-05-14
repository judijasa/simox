DROP TABLE IF EXISTS municipio;
CREATE TABLE municipio (
    id INT AUTO_INCREMENT,
    nombre VARCHAR(75) NOT NULL,

    PRIMARY KEY (id)
);

INSERT INTO municipio(id, nombre) VALUES (0, 'NONE'), (-1, 'No_Aplica');
