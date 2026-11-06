<?php
declare(strict_types=1);

final class AdaptiveRules
{
    /** Reglas operativas iniciales, editables y pendientes de validación empírica. */
    public const RULES = [
        'C1' => [
            'nombre' => 'Volatilidad de los requisitos',
            'bajo' => 'Mantener una línea base de requisitos y gestionar los cambios mediante un procedimiento formal.',
            'medio' => 'Combinar una línea base inicial con revisiones periódicas e iteraciones de ajuste.',
            'alto' => 'Priorizar ciclos iterativos cortos, refinamiento frecuente de requisitos y retroalimentación continua de interesados.',
        ],
        'C2' => [
            'nombre' => 'Criticidad de la arquitectura técnica',
            'bajo' => 'Permitir una definición arquitectónica incremental, conservando revisiones básicas de calidad.',
            'medio' => 'Definir primero los componentes arquitectónicos principales y completar detalles durante las iteraciones.',
            'alto' => 'Establecer una arquitectura base, registrar decisiones técnicas y realizar revisiones formales antes de componentes críticos.',
        ],
        'C3' => [
            'nombre' => 'Restricciones de costo y tiempo',
            'bajo' => 'Mantener planificación flexible y revisar periódicamente las estimaciones de costo y plazo.',
            'medio' => 'Utilizar planificación progresiva, hitos de seguimiento y actualización periódica de estimaciones.',
            'alto' => 'Establecer una línea base de presupuesto y plazo, priorizar el alcance y controlar desviaciones en hitos definidos.',
        ],
        'C4' => [
            'nombre' => 'Experiencia del equipo de trabajo',
            'bajo' => 'Incorporar roles y procedimientos explícitos, acompañamiento, plantillas y revisiones técnicas frecuentes.',
            'medio' => 'Combinar prácticas estandarizadas con autonomía gradual del equipo y acompañamiento en decisiones críticas.',
            'alto' => 'Permitir mayor autonomía para organizar iteraciones y seleccionar prácticas, conservando seguimiento de resultados.',
        ],
    ];

    public static function normalizeLevel(mixed $level, string $code): string
    {
        if (!is_string($level)) {
            throw new InvalidArgumentException("Falta clasificar {$code} en bajo, medio o alto.");
        }
        $level = strtolower(trim($level));
        $aliases = ['moderado' => 'medio', 'moderada' => 'medio', 'medium' => 'medio', 'low' => 'bajo', 'high' => 'alto'];
        $level = $aliases[$level] ?? $level;
        if (!in_array($level, ['bajo', 'medio', 'alto'], true)) {
            throw new InvalidArgumentException("Nivel inválido para {$code}. Use bajo, medio o alto.");
        }
        return $level;
    }

    /**
     * Integración propuesta AHP-reglas:
     * C1-C3: bajo=1/3, medio=2/3, alto=1 (mayor nivel = mayor necesidad de adaptación).
     * C4 (experiencia): bajo=1, medio=2/3, alto=1/3 (menor experiencia = mayor necesidad de apoyo).
     * prioridad_bruta_i = peso_AHP_i * factor_necesidad_i.
     * prioridad_normalizada_i = prioridad_bruta_i / suma(prioridades_brutas).
     * Esta operacionalización es una propuesta del prototipo y requiere validación.
     */
    public static function evaluate(array $criteriaWeights, array $context): array
    {
        $factorNeedMaps = [
            'C1' => ['bajo' => 1/3, 'medio' => 2/3, 'alto' => 1.0],
            'C2' => ['bajo' => 1/3, 'medio' => 2/3, 'alto' => 1.0],
            'C3' => ['bajo' => 1/3, 'medio' => 2/3, 'alto' => 1.0],
            'C4' => ['bajo' => 1.0, 'medio' => 2/3, 'alto' => 1/3],
        ];
        $rows = [];
        $sumRaw = 0.0;
        foreach (self::RULES as $code => $rule) {
            $level = self::normalizeLevel($context[$code] ?? null, $code);
            $weight = (float)($criteriaWeights[$code] ?? 0.0);
            $factorNeed = $factorNeedMaps[$code][$level];
            $raw = $weight * $factorNeed;
            $sumRaw += $raw;
            $rows[] = [
                'codigo' => $code,
                'criterio' => $rule['nombre'],
                'nivel' => $level,
                'factor_necesidad_adaptacion' => $factorNeed,
                'peso_ahp' => $weight,
                'prioridad_regla_bruta' => $raw,
                'accion' => $rule[$level],
            ];
        }
        foreach ($rows as &$row) {
            $row['prioridad_regla_normalizada'] = $sumRaw > 0 ? $row['prioridad_regla_bruta'] / $sumRaw : 0.0;
        }
        unset($row);
        usort($rows, fn($a, $b) => $b['prioridad_regla_normalizada'] <=> $a['prioridad_regla_normalizada']);
        return [
            'metodo' => 'prioridad de acción = peso AHP del criterio × factor de necesidad de adaptación',
            'factores_necesidad' => $factorNeedMaps,
            'advertencia' => 'Los factores por nivel son una operacionalización propuesta. En C4 se invierte la escala porque una menor experiencia implica mayor necesidad de apoyo. La tabla y las reglas deben justificarse y validarse con especialistas antes de considerarse definitivas.',
            'reglas' => $rows,
        ];
    }
}
