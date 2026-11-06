<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/DecisionEngine.php';

$base = dirname(__DIR__);
$files = [
    'CONCE_SUR' => $base . '/examples/01_CONCE_SUR.json',
    'ANDESSOFT' => $base . '/examples/02_ANDESSOFT.json',
    'RUTASUR' => $base . '/examples/03_RUTASUR_LOGISTICA.json',
    'CR_INCONSISTENTE' => $base . '/examples/04_PRUEBA_CR_INCONSISTENTE.json',
];
$expectedWinners = ['CONCE_SUR' => 'adaptativa', 'ANDESSOFT' => 'adaptativa', 'RUTASUR' => 'predictiva'];
$expectedScores = [
    'CONCE_SUR' => ['adaptativa' => 0.392857142857, 'hibrida' => 0.321428571429, 'predictiva' => 0.285714285714],
    'ANDESSOFT' => ['adaptativa' => 0.545454545455, 'hibrida' => 0.285714285714, 'predictiva' => 0.168831168831],
    'RUTASUR' => ['predictiva' => 0.396825396825, 'hibrida' => 0.380952380952, 'adaptativa' => 0.222222222222],
];
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

foreach ($files as $name => $path) {
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $result = DecisionEngine::calculate($data);
    $cr = $result['diagnostico_matriz_criterios']['cr'];
    echo $name . ': estado=' . $result['estado'] . ', CR_criterios=' . number_format($cr, 4, '.', '') . ', ganador=' . ($result['alternativa_prioritaria'] ?? 'ninguno') . PHP_EOL;

    $assert(abs(array_sum(array_column($result['criterios'], 'peso_ahp')) - 1.0) < 1e-8, "Los pesos de criterios de {$name} no suman 1.");
    foreach ($result['prioridades_locales_alternativas'] as $criterion => $priorities) {
        $assert(abs(array_sum($priorities) - 1.0) < 1e-8, "Las prioridades locales {$criterion} de {$name} no suman 1.");
    }
    $assert(abs(array_sum($result['puntuaciones_globales_alternativas']) - 1.0) < 1e-8, "Las puntuaciones globales de {$name} no suman 1.");

    if ($name === 'CR_INCONSISTENTE') {
        $assert($result['estado'] === 'requiere_revision_de_consistencia', 'La matriz inconsistente no produjo el estado esperado.');
        $assert($cr >= 0.10, 'La matriz deliberadamente inconsistente no superó el umbral CR.');
        $assert($result['alternativa_prioritaria'] === null, 'No debe declararse ganadora una matriz inconsistente.');
        continue;
    }

    $assert($result['estado'] === 'calculado_consistente', "El caso {$name} no alcanzó estado consistente.");
    $assert(($result['alternativa_prioritaria'] ?? null) === $expectedWinners[$name], "El ganador del caso {$name} no coincide.");
    foreach ($expectedScores[$name] as $alternative => $score) {
        $actual = $result['puntuaciones_globales_alternativas'][$alternative];
        $assert(abs($actual - $score) <= 1e-6, "La puntuación de {$alternative} en {$name} no coincide.");
    }
}

// Comprueba que el criterio C4 se interprete en sentido inverso: poca experiencia implica mayor necesidad de apoyo.
$conce = json_decode(file_get_contents($files['CONCE_SUR']), true, 512, JSON_THROW_ON_ERROR);
$conceResult = DecisionEngine::calculate($conce);
$c4 = null;
foreach ($conceResult['integracion_reglas_adaptativas']['reglas'] as $rule) {
    if ($rule['codigo'] === 'C4') $c4 = $rule;
}
$assert($c4 !== null && abs($c4['factor_necesidad_adaptacion'] - 1.0) < 1e-8, 'C4 bajo debe activar el mayor factor de necesidad de apoyo.');

// Comprueba rechazo de una valoración fuera de la escala de Saaty.
$bad = $conce;
$bad['comparaciones_criterios']['C1_C2'] = 1.7;
$rejected = false;
try { DecisionEngine::calculate($bad); } catch (InvalidArgumentException $e) { $rejected = true; }
$assert($rejected, 'El motor no rechazó una valoración fuera de la escala de Saaty.');

echo "Todas las pruebas automáticas del motor finalizaron correctamente.\n";
