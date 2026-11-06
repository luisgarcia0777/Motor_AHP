<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
try {
    $stmt = db()->query('SELECT id, empresa, proyecto, tipo_dato, estado, creado_en FROM evaluaciones ORDER BY id DESC LIMIT 50');
    echo json_encode(['ok' => true, 'items' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['error' => 'No se pudo consultar el historial. Revise la conexión y ejecute database/motor_ahp.sql.', 'detalle' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
