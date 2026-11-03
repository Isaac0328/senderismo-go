-- Republica Dominicana: 31 provincias y el Distrito Nacional.
-- Fuente: Oficina Nacional de Estadistica, Directorio empresarial formal 2024.
-- No sobrescribe nombres ni estados personalizados al ejecutarse nuevamente.
CREATE TABLE IF NOT EXISTS provincias (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    tipo ENUM('provincia', 'distrito_nacional') NOT NULL DEFAULT 'provincia',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provincias_nombre (nombre),
    INDEX idx_provincias_activo_nombre (activo, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET NAMES utf8mb4;
INSERT IGNORE INTO provincias (nombre, tipo) VALUES
('Azua', 'provincia'),
('Baoruco', 'provincia'),
('Barahona', 'provincia'),
('Dajabón', 'provincia'),
('Distrito Nacional', 'distrito_nacional'),
('Duarte', 'provincia'),
('El Seibo', 'provincia'),
('Elías Piña', 'provincia'),
('Espaillat', 'provincia'),
('Hato Mayor', 'provincia'),
('Hermanas Mirabal', 'provincia'),
('Independencia', 'provincia'),
('La Altagracia', 'provincia'),
('La Romana', 'provincia'),
('La Vega', 'provincia'),
('María Trinidad Sánchez', 'provincia'),
('Monseñor Nouel', 'provincia'),
('Monte Cristi', 'provincia'),
('Monte Plata', 'provincia'),
('Pedernales', 'provincia'),
('Peravia', 'provincia'),
('Puerto Plata', 'provincia'),
('Samaná', 'provincia'),
('San Cristóbal', 'provincia'),
('San José de Ocoa', 'provincia'),
('San Juan', 'provincia'),
('San Pedro de Macorís', 'provincia'),
('Sánchez Ramírez', 'provincia'),
('Santiago', 'provincia'),
('Santiago Rodríguez', 'provincia'),
('Santo Domingo', 'provincia'),
('Valverde', 'provincia');
