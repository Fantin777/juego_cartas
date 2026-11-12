<?php
/**
 * motor/EstrategiaIA.php
 * Inteligencia artificial de Tata (decide cartas, truco y envido).
 */
declare(strict_types=1);

/**
 * CAMBIO IMPORTANTE respecto del original: antes Tata decidía mirando
 * solamente su carta más alta y aceptaba el truco o el envido "a la
 * suerte" (50 % de las veces aunque no tuviera nada).
 *
 * Ahora Tata razona como un jugador:
 *   - Conoce sus cartas, las que ya se jugaron en la mesa, las rondas que
 *     ganó/perdió y, si el envido se jugó, los tantos que dijiste.
 *   - Imagina cientos de manos posibles tuyas (con las cartas que todavía
 *     no vio) y, para cada una, calcula si ganaría la mano jugando bien.
 *     Así obtiene su PROBABILIDAD REAL de ganar la mano.
 *   - Con esa probabilidad decide si canta, quiere, no quiere o sube, y
 *     qué carta tirar. Sigue blufeando de vez en cuando, pero poco.
 *   - Para el envido hace lo mismo con los tantos: compara los suyos con
 *     los que podrías tener vos (y tiene en cuenta quién es mano).
 *
 * Nunca hace trampa: jamás mira tu mano real, solo lo que está a la vista.
 */
final class EstrategiaIA
{
    private const MUESTRAS_TRUCO  = 220;
    private const MUESTRAS_ENVIDO = 400;

    /** Número al azar en [0, 1). */
    private function azar(): float
    {
        return random_int(0, PHP_INT_MAX - 1) / PHP_INT_MAX;
    }

    // ---------------------------------------------------------------
    // Reglas puras (también las usa el Árbitro)
    // ---------------------------------------------------------------
    /** Tantos de envido de un conjunto de cartas (la mano inicial de 3). */
    public static function envidoDe(array $cartas): int
    {
        $porPalo = [];
        foreach ($cartas as $c) {
            $porPalo[$c['palo']][] = $c['valor'] <= 7 ? (int) $c['valor'] : 0;
        }
        $mejor = 0;
        foreach ($porPalo as $arr) {
            if (count($arr) >= 2) {
                rsort($arr);
                $v = 20 + $arr[0] + $arr[1];
            } else {
                $v = $arr[0];
            }
            $mejor = max($mejor, $v);
        }
        return $mejor;
    }

    public function calcularEnvido(array $mano): int
    {
        return self::envidoDe($mano);
    }

    /**
     * Ganador de la mano según las rondas jugadas, o null si todavía no
     * está definido. Reglas del truco:
     *   - el que gana dos rondas, gana;
     *   - "parda la primera, gana la segunda" (y al revés: si la primera
     *     la ganó alguien y la segunda es parda, gana el de la primera);
     *   - 1 a 1 y parda la tercera: gana el que ganó la primera;
     *   - las tres pardas: gana el que es mano.
     */
    public static function ganadorDecidido(array $gr, string $mano): ?string
    {
        $vJ = 0;
        $vT = 0;
        foreach ($gr as $g) {
            if ($g === 'jugador') $vJ++;
            elseif ($g === 'tata') $vT++;
        }
        if ($vJ >= 2) return 'jugador';
        if ($vT >= 2) return 'tata';
        $n = count($gr);
        if ($n >= 2) {
            if ($gr[0] === 'empate' && $gr[1] !== 'empate') return $gr[1];
            if ($gr[0] !== 'empate' && $gr[1] === 'empate') return $gr[0];
        }
        if ($n >= 3) {
            if ($gr[2] !== 'empate') return $gr[2];
            if ($gr[0] !== 'empate') return $gr[0];
            return $mano;
        }
        return null;
    }

    // ---------------------------------------------------------------
    // Lo que Tata "imagina" de tu mano
    // ---------------------------------------------------------------
    /** Cartas que Tata no vio (pueden estar en tu mano). */
    private static function cartasDesconocidas(array $ctx): array
    {
        $vistas = [];
        foreach (array_merge($ctx['manoT'], $ctx['jugadasT'], $ctx['jugadasJ']) as $c) {
            $vistas[$c['id']] = true;
        }
        $r = [];
        foreach (Cartas::PALOS as $p) {
            foreach (Cartas::VALORES as $v) {
                if (!isset($vistas[$v . $p])) {
                    $r[] = Cartas::crear($v, $p);
                }
            }
        }
        return $r;
    }

    private static function tomarAlAzar(array $pool, int $k): array
    {
        $n = count($pool);
        $res = [];
        for ($i = 0; $i < $k && $i < $n; $i++) {
            $j = mt_rand($i, $n - 1);
            [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
            $res[] = $pool[$i];
        }
        return $res;
    }

    /**
     * Manos posibles tuyas (solo las cartas que todavía tenés), coherentes
     * con lo que Tata sabe. Si ya dijiste tus tantos de envido, solo
     * imagina manos que den esos tantos.
     */
    private function muestras(array $ctx, int $n): array
    {
        $pool = self::cartasDesconocidas($ctx);
        $k = (int) $ctx['cantJ'];
        $envJ = $ctx['envidoJ'] ?? null;
        if ($envJ !== null) {
            $res = [];
            for ($i = 0; $i < $n * 30 && count($res) < $n; $i++) {
                $m = self::tomarAlAzar($pool, $k);
                if (self::envidoDe(array_merge($ctx['jugadasJ'], $m)) === $envJ) {
                    $res[] = $m;
                }
            }
            if (count($res) >= 20) {
                return $res;
            }
        }
        $res = [];
        for ($i = 0; $i < $n; $i++) {
            $res[] = self::tomarAlAzar($pool, $k);
        }
        return $res;
    }

    private static function valores(array $cartas): array
    {
        return array_map([Cartas::class, 'valorTruco'], array_values($cartas));
    }

    /** Cartas ya tiradas en la ronda actual (todavía sin resolver). */
    private static function pendientes(array $ctx): array
    {
        $n = count($ctx['gr']);
        $pT = count($ctx['jugadasT']) > $n ? Cartas::valorTruco($ctx['jugadasT'][count($ctx['jugadasT']) - 1]) : null;
        $pJ = count($ctx['jugadasJ']) > $n ? Cartas::valorTruco($ctx['jugadasJ'][count($ctx['jugadasJ']) - 1]) : null;
        return [$pT, $pJ];
    }

    /**
     * ¿Gana Tata la mano si los dos juegan lo mejor posible con estas
     * cartas? (búsqueda completa: como mucho 3 cartas por lado).
     */
    private static function ganaTata(array $mT, array $mJ, ?int $pT, ?int $pJ, array $gr, string $turno, string $mano): bool
    {
        if ($pT !== null && $pJ !== null) {
            $g = $pJ > $pT ? 'jugador' : ($pJ < $pT ? 'tata' : 'empate');
            $gr[] = $g;
            $d = self::ganadorDecidido($gr, $mano);
            if ($d !== null) {
                return $d === 'tata';
            }
            $turno = $g === 'empate' ? $mano : $g;
            $pT = null;
            $pJ = null;
        }
        if ($turno === 'tata') {
            if (!$mT) return false;
            foreach (array_unique($mT) as $i => $v) {
                $resto = $mT;
                unset($resto[$i]);
                if (self::ganaTata(array_values($resto), $mJ, $v, $pJ, $gr, 'jugador', $mano)) {
                    return true;
                }
            }
            return false;
        }
        if (!$mJ) return true;
        foreach (array_unique($mJ) as $i => $v) {
            $resto = $mJ;
            unset($resto[$i]);
            if (!self::ganaTata($mT, array_values($resto), $pT, $v, $gr, 'tata', $mano)) {
                return false;
            }
        }
        return true;
    }

    /** Probabilidad (0..1) de que Tata gane la mano desde la situación actual. */
    public function probGanarMano(array $ctx): float
    {
        $muestras = $this->muestras($ctx, self::MUESTRAS_TRUCO);
        [$pT, $pJ] = self::pendientes($ctx);
        $mT = self::valores($ctx['manoT']);
        $gana = 0;
        foreach ($muestras as $m) {
            if (self::ganaTata($mT, self::valores($m), $pT, $pJ, $ctx['gr'], $ctx['turno'], $ctx['mano'])) {
                $gana++;
            }
        }
        return $muestras ? $gana / count($muestras) : 0.0;
    }

    /** Probabilidad (0..1) de que Tata gane el envido (empate: gana la mano). */
    public function probGanarEnvido(array $ctx): float
    {
        $ptsT = self::envidoDe(array_merge($ctx['jugadasT'], $ctx['manoT']));
        $muestras = $this->muestras(array_merge($ctx, ['envidoJ' => null]), self::MUESTRAS_ENVIDO);
        $gana = 0;
        foreach ($muestras as $m) {
            $ptsJ = self::envidoDe(array_merge($ctx['jugadasJ'], $m));
            if ($ptsT > $ptsJ || ($ptsT === $ptsJ && $ctx['mano'] === 'tata')) {
                $gana++;
            }
        }
        return $gana / count($muestras);
    }

    // ---------------------------------------------------------------
    // Decisiones
    // ---------------------------------------------------------------
    /**
     * Qué carta tirar: la que más chances le da de ganar la mano. Si
     * varias dan lo mismo, tira la más baja (guarda las buenas).
     */
    public function elegirCarta(array $ctx): array
    {
        $muestras = $this->muestras($ctx, self::MUESTRAS_TRUCO);
        [, $pJ] = self::pendientes($ctx);
        $cands = array_values($ctx['manoT']);
        usort($cands, function ($a, $b) {
            return Cartas::valorTruco($a) - Cartas::valorTruco($b);
        });
        $vals = self::valores($cands);
        $mejor = $cands[0];
        $mejorP = -1.0;
        foreach ($cands as $i => $c) {
            $resto = $vals;
            unset($resto[$i]);
            $resto = array_values($resto);
            $gana = 0;
            foreach ($muestras as $m) {
                if (self::ganaTata($resto, self::valores($m), $vals[$i], $pJ, $ctx['gr'], 'jugador', $ctx['mano'])) {
                    $gana++;
                }
            }
            $p = $gana / max(1, count($muestras));
            if ($p > $mejorP + 0.03) {
                $mejor = $c;
                $mejorP = $p;
            }
        }
        return $mejor;
    }

    /** ¿Tata canta truco / retruco / vale 4 ahora? */
    public function decideCantarTruco(int $nivelActual, array $ctx): bool
    {
        if ($nivelActual >= 3) return false;
        $p = $this->probGanarMano($ctx);
        if ($p >= 0.8) {
            $prob = 0.8;
        } elseif ($p >= 0.6) {
            $prob = 0.4;
        } elseif ($p >= 0.4) {
            $prob = $nivelActual === 0 ? 0.12 : 0.05;
        } else {
            $prob = $nivelActual === 0 ? 0.06 : 0.02;   // farol, poco frecuente
        }
        return $this->azar() < $prob;
    }

    /**
     * Respuesta al truco que le cantaste: 'aceptar' | 'rechazar' | 'subir'.
     * Compara lo que arriesga (perder $nuevo['valor']) con lo que pierde
     * seguro si no quiere ($valorActual), y le suma prudencia porque si
     * cantaste es probable que tengas buenas cartas.
     */
    public function decideResponderTruco(array $nuevo, int $valorActual, array $ctx): string
    {
        $p = $this->probGanarMano($ctx);
        $nivel = (int) $nuevo['nivel'];
        $V = (int) $nuevo['valor'];
        $Vc = max(1, $valorActual);
        $prudencia = [1 => 0.22, 2 => 0.28, 3 => 0.33][$nivel] ?? 0.3;
        $umbral = ($V - $Vc) / (2 * $V) + $prudencia + ($this->azar() - 0.5) * 0.08;

        if ($nivel < 3 && $p >= 0.82 && $this->azar() < ($p >= 0.95 ? 0.75 : 0.5)) {
            return 'subir';
        }
        if ($p >= $umbral) {
            return 'aceptar';
        }
        if ($p >= $umbral - 0.12 && $this->azar() < 0.1) {
            return 'aceptar';   // a veces se la juega
        }
        return 'rechazar';
    }

    /** ¿Tata canta envido por su cuenta? Devuelve el tipo o null. */
    public function decideCantarEnvido(array $ctx): ?string
    {
        $p = $this->probGanarEnvido($ctx);
        $r = $this->azar();
        if ($p >= 0.9) {
            if ($r >= 0.9) return null;
            $t = $this->azar();
            return $t < 0.15 ? 'faltaenvido' : ($t < 0.45 ? 'realenvido' : 'envido');
        }
        if ($p >= 0.7) {
            if ($r >= 0.8) return null;
            return $this->azar() < 0.25 ? 'realenvido' : 'envido';
        }
        if ($p >= 0.5) {
            return $r < 0.45 ? 'envido' : null;
        }
        return $r < 0.06 ? 'envido' : null;   // farol
    }

    /**
     * Respuesta a un envido: 'quiero' | 'noquiero' | tipo para subir.
     * $q = puntos si se quiere, $n = puntos que se pierden si no se quiere.
     */
    public function decideResponderEnvido(array $ctx, array $cadena, int $q, int $n, array $subidas): string
    {
        $p = $this->probGanarEnvido($ctx);
        if ($subidas && $p >= 0.85 && $this->azar() < 0.5) {
            if (in_array('realenvido', $subidas, true)) return 'realenvido';
            if ($p >= 0.93 && in_array('faltaenvido', $subidas, true)) return 'faltaenvido';
        }
        $q = max(1, $q);
        $prudencia = 0.25 + 0.05 * (count($cadena) - 1);
        $umbral = ($q - $n) / (2 * $q) + $prudencia + ($this->azar() - 0.5) * 0.08;
        return $p >= $umbral ? 'quiero' : 'noquiero';
    }
}
