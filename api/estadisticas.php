<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

/**
 * Construye condiciones SQL y parámetros seguros para filtrar historial_reproducciones.
 *
 * @return array{condiciones: string[], params: array<string, int>}
 */
function construirFiltrosHistorial(
    string $alias,
    string $anio,
    string $trimestre,
    string $usuarioId,
    bool $incluirUsuario = true
): array {
    $condiciones = [];
    $params = [];

    if ($anio !== 'all' && ctype_digit($anio)) {
        $condiciones[] = "YEAR({$alias}.fecha_reproduccion) = :anio";
        $params[':anio'] = (int) $anio;
    }

    $rangosTrimestre = [
        'Q1' => [1, 3],
        'Q2' => [4, 6],
        'Q3' => [7, 9],
        'Q4' => [10, 12],
    ];
    if (isset($rangosTrimestre[$trimestre])) {
        [$mesInicio, $mesFin] = $rangosTrimestre[$trimestre];
        $condiciones[] = "MONTH({$alias}.fecha_reproduccion) BETWEEN :mes_inicio AND :mes_fin";
        $params[':mes_inicio'] = $mesInicio;
        $params[':mes_fin'] = $mesFin;
    }

    if ($incluirUsuario && $usuarioId !== 'all' && ctype_digit($usuarioId)) {
        $condiciones[] = "{$alias}.usuario_id = :usuario_id";
        $params[':usuario_id'] = (int) $usuarioId;
    }

    return ['condiciones' => $condiciones, 'params' => $params];
}

try {
    $pdo = obtenerConexion();

    $anio = isset($_GET['anio']) ? trim((string) $_GET['anio']) : 'all';
    $trimestre = isset($_GET['trimestre']) ? trim((string) $_GET['trimestre']) : 'all';
    $usuarioId = isset($_GET['usuario_id']) ? trim((string) $_GET['usuario_id']) : 'all';

    // Filtros completos (año, trimestre y usuario)
    $filtrosCompletos = construirFiltrosHistorial('h', $anio, $trimestre, $usuarioId, true);
    $whereHistorial = $filtrosCompletos['condiciones']
        ? 'WHERE ' . implode(' AND ', $filtrosCompletos['condiciones'])
        : '';
    $andLeftJoin = $filtrosCompletos['condiciones']
        ? ' AND ' . implode(' AND ', $filtrosCompletos['condiciones'])
        : '';
    $params = $filtrosCompletos['params'];

    // Filtros solo temporales (para la comparativa de usuarios)
    $filtrosTiempo = construirFiltrosHistorial('h', $anio, $trimestre, $usuarioId, false);
    $andLeftJoinTiempo = $filtrosTiempo['condiciones']
        ? ' AND ' . implode(' AND ', $filtrosTiempo['condiciones'])
        : '';
    $paramsTiempo = $filtrosTiempo['params'];

    // ------------------------------------------------------------------------
    // Catálogos auxiliares para controles de la interfaz
    // ------------------------------------------------------------------------
    $aniosDisponibles = array_map(
        'intval',
        $pdo->query('SELECT DISTINCT YEAR(fecha_reproduccion) AS anio FROM historial_reproducciones ORDER BY anio DESC')
            ->fetchAll(PDO::FETCH_COLUMN)
    );

    $usuariosLista = $pdo->query(
        'SELECT id, nombre, correo, pais, fecha_registro FROM usuarios ORDER BY nombre ASC'
    )->fetchAll();

    $cancionesCatalogo = $pdo->query(
        'SELECT c.id, c.titulo, c.duracion_seg, a.nombre AS artista_nombre
         FROM canciones c
         INNER JOIN albumes al ON al.id = c.album_id
         INNER JOIN artistas a ON a.id = al.artista_id
         ORDER BY a.nombre ASC, c.titulo ASC'
    )->fetchAll();

    // ------------------------------------------------------------------------
    // Bloque A: Estadísticas Generales (COUNT, AVG, MIN, MAX, SUM)
    // ------------------------------------------------------------------------
    $statsCatalogo = $pdo->query(
        'SELECT
            COUNT(*) AS total_canciones,
            COALESCE(ROUND(AVG(duracion_seg)), 0) AS promedio_duracion_seg,
            COALESCE(MIN(duracion_seg), 0) AS duracion_min_seg,
            COALESCE(MAX(duracion_seg), 0) AS duracion_max_seg,
            (SELECT COUNT(*) FROM artistas) AS total_artistas,
            (SELECT COUNT(*) FROM generos) AS total_generos,
            (SELECT COUNT(*) FROM usuarios) AS total_usuarios
         FROM canciones'
    )->fetch();

    $stmtResumenHist = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_reproducciones,
            COALESCE(SUM(h.duracion_escuchada_seg), 0) AS tiempo_total_seg
         FROM historial_reproducciones h
         {$whereHistorial}"
    );
    $stmtResumenHist->execute($params);
    $resumenHist = $stmtResumenHist->fetch();

    $generales = [
        'totalCanciones'       => (int) $statsCatalogo['total_canciones'],
        'totalArtistas'        => (int) $statsCatalogo['total_artistas'],
        'totalGeneros'         => (int) $statsCatalogo['total_generos'],
        'totalUsuarios'        => (int) $statsCatalogo['total_usuarios'],
        'totalReproducciones'  => (int) $resumenHist['total_reproducciones'],
        'tiempoTotalSeg'       => (int) $resumenHist['tiempo_total_seg'],
        'promedioDuracionSeg'  => (int) $statsCatalogo['promedio_duracion_seg'],
        'duracionMinSeg'       => (int) $statsCatalogo['duracion_min_seg'],
        'duracionMaxSeg'       => (int) $statsCatalogo['duracion_max_seg'],
    ];

    // ------------------------------------------------------------------------
    // Bloque B: Estadísticas de Canciones (INNER JOIN + HAVING y LEFT JOIN)
    // ------------------------------------------------------------------------
    $stmtMasEscuchadas = $pdo->prepare(
        "SELECT
            c.id,
            c.titulo,
            c.duracion_seg,
            a.nombre AS artista_nombre,
            g.nombre AS genero_nombre,
            COUNT(h.id) AS reproducciones,
            COALESCE(SUM(h.duracion_escuchada_seg), 0) AS tiempo_acumulado_seg
         FROM canciones c
         INNER JOIN albumes al ON al.id = c.album_id
         INNER JOIN artistas a ON a.id = al.artista_id
         INNER JOIN generos g ON g.id = c.genero_id
         INNER JOIN historial_reproducciones h ON h.cancion_id = c.id
         {$whereHistorial}
         GROUP BY c.id, c.titulo, c.duracion_seg, a.nombre, g.nombre
         HAVING COUNT(h.id) > 0
         ORDER BY reproducciones DESC, tiempo_acumulado_seg DESC, c.titulo ASC
         LIMIT 10"
    );
    $stmtMasEscuchadas->execute($params);
    $masEscuchadas = array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['duracion_seg'] = (int) $row['duracion_seg'];
        $row['reproducciones'] = (int) $row['reproducciones'];
        $row['tiempo_acumulado_seg'] = (int) $row['tiempo_acumulado_seg'];
        return $row;
    }, $stmtMasEscuchadas->fetchAll());

    $stmtMenosReproducidas = $pdo->prepare(
        "SELECT
            c.id,
            c.titulo,
            c.duracion_seg,
            a.nombre AS artista_nombre,
            g.nombre AS genero_nombre,
            COUNT(h.id) AS reproducciones,
            COALESCE(SUM(h.duracion_escuchada_seg), 0) AS tiempo_acumulado_seg
         FROM canciones c
         INNER JOIN albumes al ON al.id = c.album_id
         INNER JOIN artistas a ON a.id = al.artista_id
         INNER JOIN generos g ON g.id = c.genero_id
         LEFT JOIN historial_reproducciones h ON h.cancion_id = c.id{$andLeftJoin}
         GROUP BY c.id, c.titulo, c.duracion_seg, a.nombre, g.nombre
         ORDER BY reproducciones ASC, tiempo_acumulado_seg ASC, c.titulo ASC
         LIMIT 10"
    );
    $stmtMenosReproducidas->execute($params);
    $menosReproducidas = array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['duracion_seg'] = (int) $row['duracion_seg'];
        $row['reproducciones'] = (int) $row['reproducciones'];
        $row['tiempo_acumulado_seg'] = (int) $row['tiempo_acumulado_seg'];
        return $row;
    }, $stmtMenosReproducidas->fetchAll());

    // ------------------------------------------------------------------------
    // Bloque C: Estadísticas de Artistas y Países
    // ------------------------------------------------------------------------
    $stmtArtistas = $pdo->prepare(
        "SELECT
            a.id,
            a.nombre,
            a.pais,
            COUNT(DISTINCT c.id) AS canciones_registradas,
            COUNT(h.id) AS reproducciones,
            COALESCE(SUM(h.duracion_escuchada_seg), 0) AS tiempo_acumulado_seg
         FROM artistas a
         LEFT JOIN albumes al ON al.artista_id = a.id
         LEFT JOIN canciones c ON c.album_id = al.id
         LEFT JOIN historial_reproducciones h ON h.cancion_id = c.id{$andLeftJoin}
         GROUP BY a.id, a.nombre, a.pais
         ORDER BY reproducciones DESC, canciones_registradas DESC, a.nombre ASC
         LIMIT 10"
    );
    $stmtArtistas->execute($params);
    $rankingArtistas = array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['canciones_registradas'] = (int) $row['canciones_registradas'];
        $row['reproducciones'] = (int) $row['reproducciones'];
        $row['tiempo_acumulado_seg'] = (int) $row['tiempo_acumulado_seg'];
        return $row;
    }, $stmtArtistas->fetchAll());

    $stmtPaises = $pdo->prepare(
        "SELECT
            a.pais,
            COUNT(DISTINCT a.id) AS artistas_registrados,
            COUNT(h.id) AS reproducciones
         FROM artistas a
         LEFT JOIN albumes al ON al.artista_id = a.id
         LEFT JOIN canciones c ON c.album_id = al.id
         LEFT JOIN historial_reproducciones h ON h.cancion_id = c.id{$andLeftJoin}
         GROUP BY a.pais
         ORDER BY reproducciones DESC, artistas_registrados DESC, a.pais ASC"
    );
    $stmtPaises->execute($params);
    $rankingPaises = array_map(static function (array $row): array {
        $row['artistas_registrados'] = (int) $row['artistas_registrados'];
        $row['reproducciones'] = (int) $row['reproducciones'];
        return $row;
    }, $stmtPaises->fetchAll());

    // ------------------------------------------------------------------------
    // Bloque D: Estadísticas de Géneros Musicales
    // ------------------------------------------------------------------------
    $stmtGeneros = $pdo->prepare(
        "SELECT
            g.id,
            g.nombre,
            COUNT(DISTINCT c.id) AS cantidad_canciones,
            COUNT(h.id) AS reproducciones,
            COALESCE(SUM(h.duracion_escuchada_seg), 0) AS tiempo_escuchado_seg
         FROM generos g
         LEFT JOIN canciones c ON c.genero_id = g.id
         LEFT JOIN historial_reproducciones h ON h.cancion_id = c.id{$andLeftJoin}
         GROUP BY g.id, g.nombre
         ORDER BY tiempo_escuchado_seg DESC, reproducciones DESC, g.nombre ASC"
    );
    $stmtGeneros->execute($params);
    $generosStats = array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['cantidad_canciones'] = (int) $row['cantidad_canciones'];
        $row['reproducciones'] = (int) $row['reproducciones'];
        $row['tiempo_escuchado_seg'] = (int) $row['tiempo_escuchado_seg'];
        return $row;
    }, $stmtGeneros->fetchAll());

    // ------------------------------------------------------------------------
    // Bloque E: Estadísticas Temporales del Historial (MONTH y DAYOFWEEK)
    // ------------------------------------------------------------------------
    $nombresMeses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $valoresMeses = array_fill(0, 12, 0);

    $stmtMensual = $pdo->prepare(
        "SELECT
            MONTH(h.fecha_reproduccion) AS mes_num,
            COUNT(*) AS reproducciones
         FROM historial_reproducciones h
         {$whereHistorial}
         GROUP BY MONTH(h.fecha_reproduccion)
         ORDER BY mes_num ASC"
    );
    $stmtMensual->execute($params);
    foreach ($stmtMensual->fetchAll() as $fila) {
        $idx = (int) $fila['mes_num'] - 1;
        if ($idx >= 0 && $idx < 12) {
            $valoresMeses[$idx] = (int) $fila['reproducciones'];
        }
    }

    $maxMesVal = max($valoresMeses);
    $mesPico = $maxMesVal > 0 ? $nombresMeses[array_search($maxMesVal, $valoresMeses, true)] : '—';

    // MySQL DAYOFWEEK(): 1=Domingo, 2=Lunes, ..., 7=Sábado
    $mapaDias = [
        1 => 'Domingo',
        2 => 'Lunes',
        3 => 'Martes',
        4 => 'Miércoles',
        5 => 'Jueves',
        6 => 'Viernes',
        7 => 'Sábado',
    ];
    $porDiaMysql = array_fill(1, 7, 0);

    $stmtDias = $pdo->prepare(
        "SELECT
            DAYOFWEEK(h.fecha_reproduccion) AS dia_num,
            COUNT(*) AS reproducciones
         FROM historial_reproducciones h
         {$whereHistorial}
         GROUP BY DAYOFWEEK(h.fecha_reproduccion)"
    );
    $stmtDias->execute($params);
    foreach ($stmtDias->fetchAll() as $fila) {
        $diaNum = (int) $fila['dia_num'];
        if (isset($porDiaMysql[$diaNum])) {
            $porDiaMysql[$diaNum] = (int) $fila['reproducciones'];
        }
    }

    $ordenVisualDias = [2, 3, 4, 5, 6, 7, 1]; // Lunes a Domingo
    $etiquetasDias = [];
    $valoresDias = [];
    foreach ($ordenVisualDias as $diaNum) {
        $etiquetasDias[] = $mapaDias[$diaNum];
        $valoresDias[] = $porDiaMysql[$diaNum];
    }

    $maxDiaVal = max($valoresDias);
    $diaPico = $maxDiaVal > 0 ? $etiquetasDias[array_search($maxDiaVal, $valoresDias, true)] : '—';

    // ------------------------------------------------------------------------
    // Bloque F: Estadísticas de Usuarios (comparativa del periodo seleccionado)
    // ------------------------------------------------------------------------
    $stmtUsuarios = $pdo->prepare(
        "SELECT
            u.id,
            u.nombre,
            u.correo,
            u.pais,
            COUNT(h.id) AS reproducciones,
            COUNT(DISTINCT h.cancion_id) AS canciones_distintas,
            COALESCE(SUM(h.duracion_escuchada_seg), 0) AS tiempo_acumulado_seg
         FROM usuarios u
         LEFT JOIN historial_reproducciones h ON h.usuario_id = u.id{$andLeftJoinTiempo}
         GROUP BY u.id, u.nombre, u.correo, u.pais
         ORDER BY reproducciones DESC, tiempo_acumulado_seg DESC, u.nombre ASC"
    );
    $stmtUsuarios->execute($paramsTiempo);
    $rankingUsuarios = array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['reproducciones'] = (int) $row['reproducciones'];
        $row['canciones_distintas'] = (int) $row['canciones_distintas'];
        $row['tiempo_acumulado_seg'] = (int) $row['tiempo_acumulado_seg'];
        return $row;
    }, $stmtUsuarios->fetchAll());

    echo json_encode([
        'ok' => true,
        'filtros' => [
            'anio'       => $anio,
            'trimestre'  => $trimestre,
            'usuario_id' => $usuarioId,
        ],
        'catalogos' => [
            'anios'     => $aniosDisponibles,
            'usuarios'  => $usuariosLista,
            'canciones' => $cancionesCatalogo,
        ],
        'generales' => $generales,
        'canciones' => [
            'masEscuchadas'     => $masEscuchadas,
            'menosReproducidas' => $menosReproducidas,
        ],
        'artistas' => [
            'rankingArtistas' => $rankingArtistas,
            'rankingPaises'   => $rankingPaises,
        ],
        'generos' => $generosStats,
        'temporales' => [
            'etiquetasMeses' => $nombresMeses,
            'valoresMeses'   => $valoresMeses,
            'mesPico'        => $mesPico,
            'etiquetasDias'  => $etiquetasDias,
            'valoresDias'    => $valoresDias,
            'diaPico'        => $diaPico,
        ],
        'usuarios' => $rankingUsuarios,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Error al consultar MySQL: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
