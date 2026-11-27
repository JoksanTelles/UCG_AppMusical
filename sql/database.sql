-- ============================================================================
-- PROYECTO FINAL INTEGRADOR BDDR
-- Base de datos musical relacional con historial de reproducciones y usuarios
-- Traducción relacional desde data/database.json para MySQL 8.0+
-- ============================================================================

DROP DATABASE IF EXISTS ucg_music;
CREATE DATABASE ucg_music
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ucg_music;

-- ============================================================================
-- 1. DEFINICIÓN DE TABLAS Y RELACIONES (DDL)
-- ============================================================================

-- Tabla: generos
CREATE TABLE generos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Tabla: artistas
CREATE TABLE artistas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  pais VARCHAR(80) NOT NULL,
  fecha_nacimiento DATE NOT NULL,
  INDEX idx_artistas_pais (pais)
) ENGINE=InnoDB;

-- Tabla: albumes (N:1 con artistas)
CREATE TABLE albumes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  fecha_lanzamiento DATE NOT NULL,
  artista_id INT UNSIGNED NOT NULL,
  CONSTRAINT fk_albumes_artista
    FOREIGN KEY (artista_id) REFERENCES artistas(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  INDEX idx_albumes_artista (artista_id)
) ENGINE=InnoDB;

-- Tabla: canciones (N:1 con albumes, N:1 con generos)
CREATE TABLE canciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(150) NOT NULL,
  duracion_seg INT UNSIGNED NOT NULL,
  album_id INT UNSIGNED NOT NULL,
  genero_id INT UNSIGNED NOT NULL,
  CONSTRAINT chk_canciones_duracion CHECK (duracion_seg > 0),
  CONSTRAINT fk_canciones_album
    FOREIGN KEY (album_id) REFERENCES albumes(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_canciones_genero
    FOREIGN KEY (genero_id) REFERENCES generos(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  INDEX idx_canciones_album (album_id),
  INDEX idx_canciones_genero (genero_id)
) ENGINE=InnoDB;

-- Tabla: usuarios (entidad de demostración sin autenticación)
CREATE TABLE usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  pais VARCHAR(80) NOT NULL,
  fecha_registro DATE NOT NULL
) ENGINE=InnoDB;

-- Tabla: historial_reproducciones (N:1 con usuarios, N:1 con canciones)
CREATE TABLE historial_reproducciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  cancion_id INT UNSIGNED NOT NULL,
  fecha_reproduccion DATETIME NOT NULL,
  duracion_escuchada_seg INT UNSIGNED NOT NULL,
  CONSTRAINT chk_historial_duracion CHECK (duracion_escuchada_seg >= 0),
  CONSTRAINT fk_historial_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT fk_historial_cancion
    FOREIGN KEY (cancion_id) REFERENCES canciones(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  INDEX idx_historial_usuario (usuario_id),
  INDEX idx_historial_cancion (cancion_id),
  INDEX idx_historial_fecha (fecha_reproduccion)
) ENGINE=InnoDB;

-- ============================================================================
-- 2. INSERCIÓN DE DATOS (DML)
-- ============================================================================

-- 6 Géneros musicales (requisito mínimo: 5)
INSERT INTO generos (id, nombre) VALUES
  (1, 'Synthwave'),
  (2, 'Rock Alternativo'),
  (3, 'Electrónica'),
  (4, 'Indie Pop'),
  (5, 'Post-Punk'),
  (6, 'Lo-Fi Beats');

-- 10 Artistas (requisito mínimo: 10)
INSERT INTO artistas (id, nombre, pais, fecha_nacimiento) VALUES
  (1, 'The Midnight', 'Estados Unidos', '2012-01-01'),
  (2, 'Zoé', 'México', '1997-11-28'),
  (3, 'Daft Punk', 'Francia', '1993-01-01'),
  (4, 'Soda Stereo', 'Argentina', '1982-01-01'),
  (5, 'CHVRCHES', 'Reino Unido', '2011-09-01'),
  (6, 'Molchat Doma', 'Bielorrusia', '2017-01-01'),
  (7, 'Porter', 'México', '2004-05-01'),
  (8, 'Kavinsky', 'Francia', '1975-07-31'),
  (9, 'Nujabes', 'Japón', '1974-02-07'),
  (10, 'Tame Impala', 'Australia', '2007-01-01');

-- 12 Álbumes
INSERT INTO albumes (id, nombre, fecha_lanzamiento, artista_id) VALUES
  (1, 'Days of Thunder', '2014-07-15', 1),
  (2, 'Endless Summer', '2016-08-05', 1),
  (3, 'Memo Rex Commander', '2006-07-12', 2),
  (4, 'Random Access Memories', '2013-05-17', 3),
  (5, 'Discovery', '2001-03-12', 3),
  (6, 'Canción Animal', '1990-08-07', 4),
  (7, 'The Bones of What You Believe', '2013-09-20', 5),
  (8, 'Etazhi', '2018-09-07', 6),
  (9, 'Moctezuma', '2014-05-20', 7),
  (10, 'OutRun', '2013-02-22', 8),
  (11, 'Modal Soul', '2005-11-11', 9),
  (12, 'Currents', '2015-07-17', 10);

-- 26 Canciones (requisito mínimo: 25)
INSERT INTO canciones (id, titulo, duracion_seg, album_id, genero_id) VALUES
  (1, 'Gloria', 292, 1, 1),
  (2, 'Los Angeles', 391, 1, 1),
  (3, 'Sunset', 333, 2, 1),
  (4, 'Vampires', 317, 2, 1),
  (5, 'Vía Láctea', 248, 3, 2),
  (6, 'No Me Destruyas', 236, 3, 2),
  (7, 'Paula', 255, 3, 2),
  (8, 'Instant Crush', 337, 4, 3),
  (9, 'Get Lucky', 369, 4, 3),
  (10, 'One More Time', 320, 5, 3),
  (11, 'Digital Love', 301, 5, 3),
  (12, 'De Música Ligera', 212, 6, 2),
  (13, 'En el Séptimo Día', 265, 6, 2),
  (14, 'The Mother We Share', 192, 7, 4),
  (15, 'Recover', 225, 7, 4),
  (16, 'Sudno', 141, 8, 5),
  (17, 'Kletka', 283, 8, 5),
  (18, 'Huitzil', 243, 9, 2),
  (19, 'Palapa', 322, 9, 2),
  (20, 'Nightcall', 258, 10, 1),
  (21, 'Pacific Coast Highway', 382, 10, 1),
  (22, 'Feather', 175, 11, 6),
  (23, 'Luv(sic) Part 3', 336, 11, 6),
  (24, 'Let It Happen', 466, 12, 4),
  (25, 'The Less I Know The Better', 216, 12, 4),
  (26, 'Té Para Tres', 148, 6, 2);

-- 5 Usuarios de demostración
INSERT INTO usuarios (id, nombre, correo, pais, fecha_registro) VALUES
  (1, 'Ana Martínez', 'ana.martinez@demo.com', 'México', '2024-11-15'),
  (2, 'Carlos Gómez', 'carlos.gomez@demo.com', 'Argentina', '2024-12-01'),
  (3, 'Lucía Fernández', 'lucia.fernandez@demo.com', 'España', '2025-01-05'),
  (4, 'Diego Ramírez', 'diego.ramirez@demo.com', 'Colombia', '2025-01-20'),
  (5, 'Sofía Torres', 'sofia.torres@demo.com', 'Chile', '2025-02-10');

-- 65 Reproducciones en el historial (requisito mínimo: 60)
INSERT INTO historial_reproducciones (id, usuario_id, cancion_id, fecha_reproduccion, duracion_escuchada_seg) VALUES
  (1, 1, 1, '2025-01-12 09:15:00', 292),
  (2, 1, 1, '2025-01-18 14:20:00', 292),
  (3, 3, 8, '2025-02-05 19:45:00', 337),
  (4, 2, 16, '2025-02-14 22:10:00', 141),
  (5, 1, 20, '2025-03-03 23:00:00', 258),
  (6, 2, 5, '2025-03-19 11:30:00', 248),
  (7, 4, 24, '2025-04-08 16:05:00', 466),
  (8, 4, 25, '2025-04-22 17:40:00', 216),
  (9, 2, 12, '2025-05-10 12:15:00', 212),
  (10, 5, 22, '2025-05-27 08:50:00', 175),
  (11, 1, 3, '2025-06-14 20:10:00', 333),
  (12, 3, 10, '2025-06-29 21:00:00', 320),
  (13, 2, 18, '2025-07-04 15:25:00', 243),
  (14, 4, 14, '2025-07-19 18:10:00', 192),
  (15, 1, 1, '2025-08-02 10:00:00', 292),
  (16, 3, 9, '2025-08-16 19:30:00', 369),
  (17, 5, 23, '2025-09-09 09:45:00', 336),
  (18, 2, 16, '2025-09-21 23:15:00', 141),
  (19, 1, 4, '2025-10-11 17:50:00', 317),
  (20, 3, 20, '2025-10-31 23:55:00', 258),
  (21, 2, 6, '2025-11-15 13:20:00', 236),
  (22, 3, 11, '2025-12-24 20:00:00', 301),
  (23, 1, 1, '2026-01-05 10:15:00', 292),
  (24, 1, 3, '2026-01-12 18:40:00', 333),
  (25, 3, 8, '2026-01-25 21:10:00', 337),
  (26, 4, 20, '2026-02-02 22:30:00', 258),
  (27, 5, 22, '2026-02-14 08:15:00', 175),
  (28, 4, 24, '2026-02-20 16:45:00', 466),
  (29, 1, 1, '2026-03-07 11:05:00', 292),
  (30, 1, 2, '2026-03-07 11:12:00', 391),
  (31, 2, 16, '2026-03-15 23:20:00', 141),
  (32, 2, 5, '2026-03-28 14:50:00', 248),
  (33, 3, 8, '2026-04-04 19:00:00', 337),
  (34, 2, 12, '2026-04-11 12:30:00', 212),
  (35, 4, 14, '2026-04-18 17:15:00', 192),
  (36, 1, 1, '2026-05-01 09:30:00', 292),
  (37, 1, 3, '2026-05-09 20:20:00', 333),
  (38, 4, 25, '2026-05-16 15:10:00', 216),
  (39, 2, 18, '2026-05-23 18:45:00', 243),
  (40, 3, 20, '2026-06-06 21:50:00', 258),
  (41, 3, 9, '2026-06-13 22:10:00', 369),
  (42, 3, 10, '2026-06-20 22:20:00', 320),
  (43, 5, 22, '2026-06-27 08:30:00', 175),
  (44, 1, 1, '2026-07-04 11:40:00', 292),
  (45, 3, 8, '2026-07-11 19:15:00', 337),
  (46, 2, 16, '2026-07-18 23:05:00', 141),
  (47, 4, 24, '2026-07-25 16:55:00', 466),
  (48, 1, 3, '2026-08-01 20:10:00', 333),
  (49, 2, 5, '2026-08-08 13:45:00', 248),
  (50, 2, 7, '2026-08-15 14:20:00', 255),
  (51, 5, 20, '2026-08-16 21:00:00', 258),
  (52, 1, 1, '2026-08-22 10:30:00', 292),
  (53, 4, 15, '2026-08-29 17:40:00', 225),
  (54, 5, 17, '2026-09-05 22:50:00', 283),
  (55, 2, 19, '2026-09-12 15:35:00', 322),
  (56, 3, 21, '2026-09-19 23:10:00', 382),
  (57, 5, 23, '2026-09-26 09:15:00', 336),
  (58, 1, 1, '2026-10-03 11:20:00', 292),
  (59, 1, 3, '2026-10-10 18:55:00', 333),
  (60, 3, 8, '2026-10-17 20:40:00', 337),
  (61, 2, 12, '2026-10-24 12:10:00', 212),
  (62, 5, 16, '2026-10-31 23:45:00', 141),
  (63, 1, 1, '2026-11-07 10:50:00', 292),
  (64, 4, 24, '2026-11-14 16:30:00', 466),
  (65, 5, 20, '2026-11-21 21:15:00', 258);
