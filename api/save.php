<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/DecisionEngine.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $input = $payload['entrada'] ?? null;
    if (!is_array($input)) {
        throw new InvalidArgumentException('Se requiere el objeto entrada con las comparaciones y el contexto del proyecto.');
    }
    // Recalcular en el servidor: no se confía en el resultado enviado por el navegador.
    $result = DecisionEngine::calculate($input);
    $empresa = trim((string)($input['metadatos']['empresa'] ?? 'Proyecto sin nombre'));
    $proyecto = trim((string)($input['metadatos']['proyecto'] ?? 'Proyecto de software'));
    $tipo = trim((string)($input['metadatos']['tipo_dato'] ?? 'simulacion'));
    $estado = trim((string)($result['estado'] ?? 'sin_estado'));
    if ($empresa === '') $empresa = 'Proyecto sin nombre';
    if ($proyecto === '') $proyecto = 'Proyecto de software';

    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO evaluaciones (empresa, proyecto, tipo_dato, estado, entrada_json, resultado_json) VALUES (:empresa, :proyecto, :tipo, :estado, :entrada, :resultado)');
    $stmt->execute([
        ':empresa' => substr($empresa, 0, 180),
        ':proyecto' => substr($proyecto, 0, 255),
        ':tipo' => substr($tipo, 0, 40),
        ':estado' => substr($estado, 0, 60),
        ':entrada' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ':resultado' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'mensaje' => 'Evaluación guardada.'], JSON_UNESCAPED_UNICODE);
} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['error' => 'No se pudo guardar. Verifique MySQL, la base de datos y config/database.php. Detalle: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
