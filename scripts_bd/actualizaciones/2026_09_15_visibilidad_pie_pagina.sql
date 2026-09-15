-- Agrega preferencias independientes para mostrar u ocultar elementos del pie de pagina.

ALTER TABLE configuracion_sitio
    ADD COLUMN IF NOT EXISTS footer_mostrar_identidad TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_copyright,
    ADD COLUMN IF NOT EXISTS footer_mostrar_logo TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_mostrar_identidad,
    ADD COLUMN IF NOT EXISTS footer_mostrar_descripcion TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_mostrar_logo,
    ADD COLUMN IF NOT EXISTS footer_mostrar_enlaces TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_mostrar_descripcion,
    ADD COLUMN IF NOT EXISTS footer_mostrar_contacto TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_mostrar_enlaces,
    ADD COLUMN IF NOT EXISTS footer_mostrar_ubicacion TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_mostrar_contacto,
    ADD COLUMN IF NOT EXISTS footer_mostrar_telefono TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_mostrar_ubicacion,
    ADD COLUMN IF NOT EXISTS footer_mostrar_email TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_mostrar_telefono,
    ADD COLUMN IF NOT EXISTS footer_mostrar_whatsapp TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_mostrar_email,
    ADD COLUMN IF NOT EXISTS footer_mostrar_instagram TINYINT(1) NOT NULL DEFAULT 0 AFTER footer_mostrar_whatsapp,
    ADD COLUMN IF NOT EXISTS footer_mostrar_copyright TINYINT(1) NOT NULL DEFAULT 1 AFTER footer_mostrar_instagram;
