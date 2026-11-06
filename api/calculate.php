<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../src/DecisionEngine.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new InvalidArgumentException('El cuerpo debe ser un objeto JSON.');
    }
    $result = DecisionEngine::calculate($data);
    echo json_encode(['ok' => true, 'resultado' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
