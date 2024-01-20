CREATE OR REPLACE TABLE activity_monitor (
    id INT AUTO_INCREMENT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    evento TEXT,

    PRIMARY KEY(id),
    INDEX idx_activity_monitor_creado(creado)
);
