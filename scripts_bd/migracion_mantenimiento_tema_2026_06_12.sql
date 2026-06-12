CREATE TABLE IF NOT EXISTS configuracion_tema (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    tema VARCHAR(40) NOT NULL DEFAULT 'senderismo',
    primary_color VARCHAR(7) NOT NULL DEFAULT '#255f38',
    primary_dark_color VARCHAR(7) NOT NULL DEFAULT '#102617',
    accent_color VARCHAR(7) NOT NULL DEFAULT '#e10600',
    accent_dark_color VARCHAR(7) NOT NULL DEFAULT '#b90000',
    background_color VARCHAR(7) NOT NULL DEFAULT '#f3f6ef',
    surface_color VARCHAR(7) NOT NULL DEFAULT '#ffffff',
    text_color VARCHAR(7) NOT NULL DEFAULT '#111111',
    muted_color VARCHAR(7) NOT NULL DEFAULT '#5f6d64',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO configuracion_tema (id, tema)
VALUES (1, 'senderismo')
ON DUPLICATE KEY UPDATE tema = tema;
