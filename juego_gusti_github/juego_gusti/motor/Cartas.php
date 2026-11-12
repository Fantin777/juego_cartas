<?php
/**
 * motor/Cartas.php
 * Mazo español de 40 cartas: valores de truco y barajado seguro (random_int).
 */
declare(strict_types=1);

final class Cartas
{
    // Mismo orden de valores que la constante valorTruco de script.js
    private const VALOR_TRUCO = [
        '1espada' => 14, '1basto' => 13, '7espada' => 12, '7oro' => 11,
        '3' => 10, '2' => 9, '1copa' => 8, '1oro' => 8, '12' => 7, '11' => 6,
        '10' => 5, '7basto' => 4, '7copa' => 4, '6' => 3, '5' => 2, '4' => 1,
    ];
    public const PALOS   = ['espada', 'basto', 'oro', 'copa'];
    public const VALORES = [1, 2, 3, 4, 5, 6, 7, 10, 11, 12];

    public static function crear(int $valor, string $palo): array
    {
        return ['valor' => $valor, 'palo' => $palo, 'id' => $valor . $palo];
    }

    /** Equivale a Carta.valorTruco() del JS original. */
    public static function valorTruco(array $c): int
    {
        $clave = $c['valor'] . $c['palo'];
        return self::VALOR_TRUCO[$clave] ?? self::VALOR_TRUCO[(string) $c['valor']] ?? 0;
    }

    /** Mazo de 40 cartas barajado con Fisher-Yates + random_int (CSPRNG). */
    public static function mazoBarajado(): array
    {
        $cartas = [];
        foreach (self::PALOS as $p) {
            foreach (self::VALORES as $v) {
                $cartas[] = self::crear($v, $p);
            }
        }
        for ($i = count($cartas) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$cartas[$i], $cartas[$j]] = [$cartas[$j], $cartas[$i]];
        }
        return $cartas;
    }
}
