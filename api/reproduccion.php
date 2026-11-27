<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido. Usa POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $entrada = json_decode((string) file_get_contents('php://input'), true);
    $usuarioId = isset($entrada['usuario_id']) ? (int) $entrada['usuario_id'] : 0;
    $cancionId = isset($entrada['cancion_id']) ? (int) $entrada['cancion_id'] : 0;

    if ($usuarioId <= 0 || $cancionId <= 0) {
        http_response_code(400);
        echo json_encode([
            'ok'    => false,
            'error' => 'Debes seleccionar un usuario y una canción válidos.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = obtenerConexion();

    $stmtUsuario = $pdo->prepare('SELECT id, nombre FROM usuarios WHERE id = :id');
    $stmtUsuario->execute([':id' => $usuarioId]);
    $usuario = $stmtUsuario->fetch();

    $stmtCancion = $pdo->prepare('SELECT id, titulo, duracion_seg FROM canciones WHERE id = :id');
    $stmtCancion->execute([':id' => $cancionId]);
    $cancion = $stmtCancion->fetch();

    if (!$usuario || !$cancion) {
        http_response_code(404);
        echo json_encode([
            'ok'    => false,
            'error' => 'El usuario o la canción especificados no existen.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmtInsert = $pdo->prepare(
        'INSERT INTO historial_reproducciones (usuario_id, cancion_id, fecha_reproduccion, duracion_escuchada_seg)
         VALUES (:usuario_id, :cancion_id, NOW(), :duracion_seg)'
    );
    $stmtInsert->execute([
        ':usuario_id'   => $usuarioId,
        ':cancion_id'   => $cancionId,
        ':duracion_seg' => (int) $cancion['duracion_seg'],
    ]);

    http_response_code(201);
    echo json_encode([
        'ok'      => true,
        'id'      => (int) $pdo->lastInsertId(),
        'mensaje' => sprintf(
            'Reproducción registrada: %s escuchó "%s".',
            $usuario['nombre'],
            $cancion['titulo']
        ),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'No se pudo registrar la reproducción: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
