-- ============================================================================
-- CONSULTAS SQL DE ESTADÍSTICAS MUSICALES (Entregable BDDR)
-- Demuestra: COUNT, SUM, AVG, MIN, MAX, GROUP BY, HAVING,
--            INNER JOIN, LEFT JOIN, ORDER BY, LIMIT, WHERE, BETWEEN,
--            y funciones de fecha y hora (YEAR, MONTH, DAYOFWEEK, QUARTER).
-- ============================================================================

USE musica_estadisticas;

-- ----------------------------------------------------------------------------
-- A. ESTADÍSTICAS GENERALES
-- ----------------------------------------------------------------------------

-- A1. Cantidad total de canciones, promedio, mínima y máxima duración
SELECT
  COUNT(*) AS total_canciones,
  ROUND(AVG(duracion_seg)) AS promedio_duracion_seg,
  MIN(duracion_seg) AS duracion_minima_seg,
  MAX(duracion_seg) AS duracion_maxima_seg
FROM canciones;

-- A2. Cantidad total de artistas, géneros y usuarios registrados
SELECT
  (SELECT COUNT(*) FROM artistas) AS total_artistas,
  (SELECT COUNT(*) FROM generos) AS total_generos,
  (SELECT COUNT(*) FROM usuarios) AS total_usuarios;

-- A3. Cantidad total de reproducciones y tiempo total acumulado (con filtro de rango)
SELECT
  COUNT(*) AS total_reproducciones,
  COALESCE(SUM(duracion_escuchada_seg), 0) AS tiempo_total_seg
FROM historial_reproducciones
WHERE fecha_reproduccion BETWEEN '2026-01-01 00:00:00' AND '2026-12-31 23:59:59';

-- ----------------------------------------------------------------------------
-- B. ESTADÍSTICAS DE CANCIONES
-- ----------------------------------------------------------------------------

-- B1. Top 10 canciones más escuchadas (INNER JOIN + GROUP BY + HAVING + ORDER BY + LIMIT)
SELECT
  c.id,
  c.titulo,
  a.nombre AS artista_nombre,
  g.nombre AS genero_nombre,
  COUNT(h.id) AS reproducciones,
  SUM(h.duracion_escuchada_seg) AS tiempo_acumulado_seg
FROM canciones c
INNER JOIN albumes al ON al.id = c.album_id
INNER JOIN artistas a ON a.id = al.artista_id
INNER JOIN generos g ON g.id = c.genero_id
INNER JOIN historial_reproducciones h ON h.cancion_id = c.id
GROUP BY c.id, c.titulo, a.nombre, g.nombre
HAVING COUNT(h.id) > 0
ORDER BY reproducciones DESC, tiempo_acumulado_seg DESC
LIMIT 10;

-- B2. Canción con mayor cantidad de reproducciones
SELECT
  c.titulo,
  a.nombre AS artista_nombre,
  COUNT(h.id) AS reproducciones
FROM canciones c
INNER JOIN albumes al ON al.id = c.album_id
INNER JOIN artistas a ON a.id = al.artista_id
INNER JOIN historial_reproducciones h ON h.cancion_id = c.id
GROUP BY c.id, c.titulo, a.nombre
ORDER BY reproducciones DESC, SUM(h.duracion_escuchada_seg) DESC
LIMIT 1;

-- B3. Canciones con mayor tiempo acumulado de escucha
SELECT
  c.titulo,
  a.nombre AS artista_nombre,
  SUM(h.duracion_escuchada_seg) AS tiempo_acumulado_seg
FROM canciones c
INNER JOIN albumes al ON al.id = c.album_id
INNER JOIN artistas a ON a.id = al.artista_id
INNER JOIN historial_reproducciones h ON h.cancion_id = c.id
GROUP BY c.id, c.titulo, a.nombre
ORDER BY tiempo_acumulado_seg DESC
LIMIT 10;

-- B4. Canciones menos reproducidas (LEFT JOIN para incluir canciones con 0 reproducciones)
SELECT
  c.id,
  c.titulo,
  a.nombre AS artista_nombre,
  g.nombre AS genero_nombre,
  COUNT(h.id) AS reproducciones,
  COALESCE(SUM(h.duracion_escuchada_seg), 0) AS tiempo_acumulado_seg
FROM canciones c
INNER JOIN albumes al ON al.id = c.album_id
INNER JOIN artistas a ON a.id = al.artista_id
INNER JOIN generos g ON g.id = c.genero_id
LEFT JOIN historial_reproducciones h ON h.cancion_id = c.id
GROUP BY c.id, c.titulo, a.nombre, g.nombre
ORDER BY reproducciones ASC, tiempo_acumulado_seg ASC, c.titulo ASC
LIMIT 10;

-- ----------------------------------------------------------------------------
-- C. ESTADÍSTICAS DE ARTISTAS Y PAÍSES
-- ----------------------------------------------------------------------------

-- C1. Top 10 artistas más escuchados y cantidad de canciones registradas
SELECT
  a.id,
  a.nombre,
  a.pais,
  COUNT(DISTINCT c.id) AS canciones_registradas,
  COUNT(h.id) AS reproducciones,
  COALESCE(SUM(h.duracion_escuchada_seg), 0) AS tiempo_acumulado_seg
FROM artistas a
LEFT JOIN albumes al ON al.artista_id = a.id
LEFT JOIN canciones c ON c.album_id = al.id
LEFT JOIN historial_reproducciones h ON h.cancion_id = c.id
GROUP BY a.id, a.nombre, a.pais
ORDER BY reproducciones DESC, canciones_registradas DESC
LIMIT 10;

-- C2. Cantidad de artistas registrados por país y reproducciones por país
SELECT
  a.pais,
  COUNT(DISTINCT a.id) AS artistas_registrados,
  COUNT(h.id) AS reproducciones,
  COALESCE(SUM(h.duracion_escuchada_seg), 0) AS tiempo_acumulado_seg
FROM artistas a
LEFT JOIN albumes al ON al.artista_id = a.id
LEFT JOIN canciones c ON c.album_id = al.id
LEFT JOIN historial_reproducciones h ON h.cancion_id = c.id
GROUP BY a.pais
ORDER BY reproducciones DESC, artistas_registrados DESC;

-- C3. Artista con mayor cantidad de canciones registradas
SELECT
  a.nombre,
  COUNT(c.id) AS canciones_registradas
FROM artistas a
INNER JOIN albumes al ON al.artista_id = a.id
INNER JOIN canciones c ON c.album_id = al.id
GROUP BY a.id, a.nombre
ORDER BY canciones_registradas DESC
LIMIT 1;

-- ----------------------------------------------------------------------------
-- D. ESTADÍSTICAS DE GÉNEROS MUSICALES
-- ----------------------------------------------------------------------------

-- D1. Géneros más escuchados por duración, cantidad de canciones y reproducciones
SELECT
  g.id,
  g.nombre,
  COUNT(DISTINCT c.id) AS cantidad_canciones,
  COUNT(h.id) AS reproducciones,
  COALESCE(SUM(h.duracion_escuchada_seg), 0) AS tiempo_escuchado_seg
FROM generos g
LEFT JOIN canciones c ON c.genero_id = g.id
LEFT JOIN historial_reproducciones h ON h.cancion_id = c.id
GROUP BY g.id, g.nombre
ORDER BY tiempo_escuchado_seg DESC, reproducciones DESC;

-- D2. Género musical con mayor número de reproducciones
SELECT
  g.nombre,
  COUNT(h.id) AS reproducciones
FROM generos g
INNER JOIN canciones c ON c.genero_id = g.id
INNER JOIN historial_reproducciones h ON h.cancion_id = c.id
GROUP BY g.id, g.nombre
HAVING COUNT(h.id) > 0
ORDER BY reproducciones DESC
LIMIT 1;

-- ----------------------------------------------------------------------------
-- E. ESTADÍSTICAS DEL HISTORIAL DE REPRODUCCIONES (FUNCIONES DE FECHA)
-- ----------------------------------------------------------------------------

-- E1. Cantidad de reproducciones por mes (filtrable por año y trimestre con BETWEEN)
SELECT
  MONTH(fecha_reproduccion) AS mes_num,
  COUNT(*) AS reproducciones,
  SUM(duracion_escuchada_seg) AS tiempo_escuchado_seg
FROM historial_reproducciones
WHERE YEAR(fecha_reproduccion) = 2026
  AND MONTH(fecha_reproduccion) BETWEEN 1 AND 12
GROUP BY MONTH(fecha_reproduccion)
ORDER BY mes_num ASC;

-- E2. Mes con mayor cantidad de reproducciones
SELECT
  MONTH(fecha_reproduccion) AS mes_num,
  COUNT(*) AS reproducciones
FROM historial_reproducciones
GROUP BY MONTH(fecha_reproduccion)
ORDER BY reproducciones DESC
LIMIT 1;

-- E3. Actividad por día de la semana (1=Domingo .. 7=Sábado en MySQL DAYOFWEEK)
SELECT
  DAYOFWEEK(fecha_reproduccion) AS dia_num,
  COUNT(*) AS reproducciones
FROM historial_reproducciones
GROUP BY DAYOFWEEK(fecha_reproduccion)
ORDER BY reproducciones DESC;

-- ----------------------------------------------------------------------------
-- F. ESTADÍSTICAS DE USUARIOS
-- ----------------------------------------------------------------------------

-- F1. Actividad musical por usuario (reproducciones, canciones distintas y tiempo)
SELECT
  u.id,
  u.nombre,
  u.correo,
  u.pais,
  COUNT(h.id) AS reproducciones,
  COUNT(DISTINCT h.cancion_id) AS canciones_distintas,
  COALESCE(SUM(h.duracion_escuchada_seg), 0) AS tiempo_escuchado_seg
FROM usuarios u
LEFT JOIN historial_reproducciones h ON h.usuario_id = u.id
GROUP BY u.id, u.nombre, u.correo, u.pais
ORDER BY reproducciones DESC, tiempo_escuchado_seg DESC;
