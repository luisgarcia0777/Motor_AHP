<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
try {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) throw new InvalidArgumentException('Identificador inválido.');
    $stmt = db()->prepare('SELECT id, empresa, proyecto, tipo_dato, estado, entrada_json, resultado_json, creado_en FROM evaluaciones WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Evaluación no encontrada.');
    echo json_encode([
        'ok' => true,
        'registro' => [
            'id' => (int)$row['id'], 'empresa' => $row['empresa'], 'proyecto' => $row['proyecto'],
            'tipo_dato' => $row['tipo_dato'], 'estado' => $row['estado'], 'creado_en' => $row['creado_en'],
            'entrada' => json_decode($row['entrada_json'], true, 512, JSON_THROW_ON_ERROR),
            'resultado' => json_decode($row['resultado_json'], true, 512, JSON_THROW_ON_ERROR),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(404);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
