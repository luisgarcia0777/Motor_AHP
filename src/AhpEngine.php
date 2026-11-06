<?php
declare(strict_types=1);

final class AhpEngine
{
    public const CR_THRESHOLD = 0.10;
    public const RI_TABLE = [
        1 => 0.00, 2 => 0.00, 3 => 0.58, 4 => 0.90, 5 => 1.12,
        6 => 1.24, 7 => 1.32, 8 => 1.41, 9 => 1.45, 10 => 1.49,
    ];

    public static function allowedValues(): array
    {
        $values = [1,2,3,4,5,6,7,8,9];
        foreach ([2,3,4,5,6,7,8,9] as $n) {
            $values[] = 1 / $n;
        }
        return $values;
    }

    public static function validateSaaty(mixed $value, string $label): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Falta una valoración numérica válida en {$label}.");
        }
        $x = (float)$value;
        if (!is_finite($x) || $x <= 0) {
            throw new InvalidArgumentException("La valoración en {$label} debe ser positiva y finita.");
        }
        foreach (self::allowedValues() as $allowed) {
            if (abs($x - $allowed) < 1e-8) {
                return $x;
            }
        }
        throw new InvalidArgumentException("Valor {$x} no permitido en {$label}. Use 1–9 o sus recíprocos.");
    }

    /** Construye matriz recíproca a partir de las comparaciones independientes. */
    public static function buildMatrix(array $labels, array $comparisons, string $prefix): array
    {
        $n = count($labels);
        $matrix = array_fill(0, $n, array_fill(0, $n, 1.0));
        $expected = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $key = $labels[$i] . '_' . $labels[$j];
                $expected[] = $key;
                if (!array_key_exists($key, $comparisons)) {
                    throw new InvalidArgumentException("Falta la comparación {$prefix}.{$key}.");
                }
                $value = self::validateSaaty($comparisons[$key], $prefix . '.' . $key);
                $matrix[$i][$j] = $value;
                $matrix[$j][$i] = 1.0 / $value;
            }
        }
        $extra = array_diff(array_keys($comparisons), $expected);
        if ($extra) {
            throw new InvalidArgumentException('Comparaciones no reconocidas en ' . $prefix . ': ' . implode(', ', $extra));
        }
        return $matrix;
    }

    /**
     * Calcula el vector propio principal por iteración de potencia.
     * Retorna pesos normalizados, lambda máximo, CI, RI y CR.
     */
    public static function priorities(array $matrix): array
    {
        $n = count($matrix);
        if ($n < 1) {
            throw new InvalidArgumentException('La matriz AHP está vacía.');
        }
        foreach ($matrix as $i => $row) {
            if (count($row) !== $n) {
                throw new InvalidArgumentException('La matriz AHP debe ser cuadrada.');
            }
            foreach ($row as $value) {
                if (!is_numeric($value) || !is_finite((float)$value) || (float)$value <= 0) {
                    throw new InvalidArgumentException('La matriz AHP debe contener valores positivos y finitos.');
                }
            }
            if (abs((float)$row[$i] - 1.0) > 1e-8) {
                throw new InvalidArgumentException('La diagonal de la matriz AHP debe contener unos.');
            }
        }
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (abs(((float)$matrix[$i][$j] * (float)$matrix[$j][$i]) - 1.0) > 1e-6) {
                    throw new InvalidArgumentException('La matriz no cumple reciprocidad a[i,j] = 1/a[j,i].');
                }
            }
        }

        $w = array_fill(0, $n, 1.0 / $n);
        $converged = false;
        $iteration = 0;
        for ($iteration = 0; $iteration < 10000; $iteration++) {
            $next = array_fill(0, $n, 0.0);
            for ($i = 0; $i < $n; $i++) {
                for ($j = 0; $j < $n; $j++) {
                    $next[$i] += (float)$matrix[$i][$j] * $w[$j];
                }
            }
            $sum = array_sum($next);
            if ($sum <= 0 || !is_finite($sum)) {
                throw new RuntimeException('No fue posible normalizar el vector de prioridades.');
            }
            foreach ($next as $i => $v) {
                $next[$i] = $v / $sum;
            }
            $delta = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $delta = max($delta, abs($next[$i] - $w[$i]));
            }
            $w = $next;
            if ($delta < 1e-12) {
                $converged = true;
                break;
            }
        }
        if (!$converged) {
            throw new RuntimeException('El cálculo del vector de prioridades no convergió dentro del límite de iteraciones.');
        }

        $ratios = [];
        for ($i = 0; $i < $n; $i++) {
            $aw = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $aw += (float)$matrix[$i][$j] * $w[$j];
            }
            $ratios[] = $aw / $w[$i];
        }
        $lambda = array_sum($ratios) / $n;
        $ci = $n <= 1 ? 0.0 : max(0.0, ($lambda - $n) / ($n - 1));
        $ri = self::RI_TABLE[$n] ?? null;
        if ($ri === null) {
            throw new InvalidArgumentException("No hay índice aleatorio RI configurado para matriz de orden {$n}.");
        }
        $cr = ($n <= 2 || $ri == 0.0) ? 0.0 : $ci / $ri;
        return [
            'weights' => $w,
            'lambda_max' => $lambda,
            'ci' => $ci,
            'ri' => $ri,
            'cr' => $cr,
            'consistent' => $cr < self::CR_THRESHOLD,
            'iterations' => $iteration + 1,
        ];
    }
}
