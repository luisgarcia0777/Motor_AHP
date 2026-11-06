<?php
declare(strict_types=1);

require_once __DIR__ . '/AhpEngine.php';
require_once __DIR__ . '/AdaptiveRules.php';

final class DecisionEngine
{
    public const CRITERIA = [
        'C1' => 'Volatilidad de los requisitos',
        'C2' => 'Criticidad de la arquitectura técnica',
        'C3' => 'Restricciones de costo y tiempo',
        'C4' => 'Experiencia del equipo de trabajo',
    ];
    public const ALTERNATIVES = ['predictiva' => 'Predictiva', 'adaptativa' => 'Adaptativa', 'hibrida' => 'Híbrida'];

    public static function calculate(array $data): array
    {
        if (!isset($data['comparaciones_criterios'], $data['comparaciones_alternativas'], $data['contexto_proyecto'])) {
            throw new InvalidArgumentException('El JSON debe contener comparaciones_criterios, comparaciones_alternativas y contexto_proyecto.');
        }
        $criteriaLabels = array_keys(self::CRITERIA);
        $criteriaMatrix = AhpEngine::buildMatrix($criteriaLabels, $data['comparaciones_criterios'], 'comparaciones_criterios');
        $criteriaResult = AhpEngine::priorities($criteriaMatrix);
        $criteriaWeights = array_combine($criteriaLabels, $criteriaResult['weights']);

        $alternativeLabels = array_keys(self::ALTERNATIVES);
        $localPriorities = [];
        $diagnostics = [];
        $allConsistent = $criteriaResult['consistent'];
        foreach ($criteriaLabels as $code) {
            $comparisons = $data['comparaciones_alternativas'][$code] ?? null;
            if (!is_array($comparisons)) {
                throw new InvalidArgumentException("Faltan comparaciones para las alternativas bajo {$code}.");
            }
            $matrix = AhpEngine::buildMatrix($alternativeLabels, $comparisons, 'comparaciones_alternativas.' . $code);
            $result = AhpEngine::priorities($matrix);
            $localPriorities[$code] = array_combine($alternativeLabels, $result['weights']);
            $diagnostics[$code] = [
                'lambda_max' => $result['lambda_max'],
                'ci' => $result['ci'],
                'ri' => $result['ri'],
                'cr' => $result['cr'],
                'consistente' => $result['consistent'],
            ];
            $allConsistent = $allConsistent && $result['consistent'];
        }

        // Prioridad global: vector de pesos de criterios × matriz de prioridades locales.
        $global = [];
        foreach ($alternativeLabels as $alternative) {
            $score = 0.0;
            foreach ($criteriaLabels as $code) {
                $score += $criteriaWeights[$code] * $localPriorities[$code][$alternative];
            }
            $global[$alternative] = $score;
        }
        arsort($global, SORT_NUMERIC);
        $ranking = [];
        foreach ($global as $key => $score) {
            $ranking[] = ['alternativa' => $key, 'nombre' => self::ALTERNATIVES[$key], 'puntuacion' => $score];
        }
        $values = array_values($global);
        $tie = count($values) > 1 && abs($values[0] - $values[1]) < 1e-9;

        $ruleResult = AdaptiveRules::evaluate($criteriaWeights, $data['contexto_proyecto']);
        $levelRows = [];
        foreach (self::CRITERIA as $code => $name) {
            $levelRows[$code] = [
                'nombre' => $name,
                'nivel' => AdaptiveRules::normalizeLevel($data['contexto_proyecto'][$code] ?? null, $code),
                'peso_ahp' => $criteriaWeights[$code],
                'porcentaje_ahp' => $criteriaWeights[$code] * 100,
            ];
        }

        $warnings = [];
        if (!$criteriaResult['consistent']) {
            $warnings[] = 'La matriz de criterios no cumple CR < 0.10. Revise los juicios antes de aceptar los pesos.';
        }
        foreach ($diagnostics as $code => $diagnostic) {
            if (!$diagnostic['consistente']) {
                $warnings[] = "La matriz de alternativas de {$code} no cumple CR < 0.10.";
            }
        }
        if (!$allConsistent) {
            $warnings[] = 'El ranking global es provisional porque al menos una matriz no es consistente.';
        }
        if ($tie && $allConsistent) {
            $warnings[] = 'Existe empate técnico entre las dos alternativas con mayor prioridad.';
        }
        $winner = ($allConsistent && !$tie) ? $ranking[0]['alternativa'] : null;

        return [
            'metadatos' => $data['metadatos'] ?? [],
            'estado' => !$allConsistent ? 'requiere_revision_de_consistencia' : ($tie ? 'empate_entre_alternativas' : 'calculado_consistente'),
            'criterios' => $levelRows,
            'diagnostico_matriz_criterios' => [
                'lambda_max' => $criteriaResult['lambda_max'], 'ci' => $criteriaResult['ci'],
                'ri' => $criteriaResult['ri'], 'cr' => $criteriaResult['cr'],
                'consistente' => $criteriaResult['consistent'],
            ],
            'prioridades_locales_alternativas' => $localPriorities,
            'diagnostico_matrices_alternativas' => $diagnostics,
            'puntuaciones_globales_alternativas' => $global,
            'orden_alternativas' => $ranking,
            'alternativa_prioritaria' => $winner,
            'integracion_reglas_adaptativas' => $ruleResult,
            'advertencias' => $warnings,
            'interpretacion' => [
                'ranking' => 'La prioridad global de las alternativas se calcula exclusivamente con las ponderaciones AHP y las prioridades locales.',
                'reglas' => 'Las reglas adaptativas utilizan el nivel de cada criterio, su peso AHP y un factor de necesidad específico. Para C4 la orientación se invierte porque una menor experiencia implica mayor necesidad de apoyo. En esta versión complementan el ranking; no alteran la puntuación AHP de las alternativas.',
                'hibridacion' => 'El modelo prioriza la alternativa híbrida como categoría, pero no calcula todavía un porcentaje de mezcla entre prácticas predictivas y adaptativas.',
            ],
        ];
    }
}
