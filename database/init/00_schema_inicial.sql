CREATE TABLE IF NOT EXISTS series (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(255) NOT NULL,
    genero VARCHAR(100) NOT NULL,
    ano_lancamento SMALLINT UNSIGNED NOT NULL,
    temporadas SMALLINT UNSIGNED NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_series_titulo (titulo),
    CONSTRAINT chk_series_ano_lancamento CHECK (ano_lancamento > 1900),
    CONSTRAINT chk_series_temporadas CHECK (temporadas > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
