<?php
/**
 * motor/Arbitro.php
 * Motor del Truco: reglas, turnos, rondas, truco, envido y mazo. Port regla por regla del Arbitro que antes vivía en script.js. El mazo se baraja en el servidor y la mano de Tata nunca se envía al navegador.
 */
declare(strict_types=1);

final class Arbitro
{
    /** Estado completo (incluye la mano secreta de Tata). Se guarda en la BD. */
    private $s;
    /** @var EstrategiaIA */
    private $ia;
    /** Eventos generados durante la acción actual. */
    private $eventos = [];
    /** Pausa acumulada (en múltiplos de delay()) antes del próximo evento. */
    private $pausaPendiente = 0.0;

    private const NOMBRES_ENVIDO = ['envido' => 'Envido', 'realenvido' => 'Real Envido', 'faltaenvido' => 'Falta Envido'];

    private function __construct(array $estado)
    {
        // Partidas guardadas con la versión anterior: completar campos nuevos.
        $estado['envido'] = array_merge(self::envidoInicial(), $estado['envido'] ?? []);
        $this->s  = $estado;
        $this->ia = new EstrategiaIA();
    }

    public static function nuevoPartido(string $nombreJugador, string $nombreCPU, int $puntosObjetivo): self
    {
        return new self([
            'version'          => 2,
            'puntosObjetivo'   => $puntosObjetivo,
            'jugador'          => ['nombre' => $nombreJugador, 'mano' => [], 'jugadas' => [], 'puntos' => 0],
            'tata'             => ['nombre' => $nombreCPU,     'mano' => [], 'jugadas' => [], 'puntos' => 0],
            'truco'            => self::trucoInicial(),
            'envido'           => self::envidoInicial(),
            'ronda'            => 0,
            'ganadorRondas'    => [],
            // Se sortea quién es mano en la primera mano; después se alterna.
            'quienEsMano'      => random_int(0, 1) === 0 ? 'jugador' : 'tata',
            'manosJugadas'     => 0,
            'turno'            => 'jugador',
            'juegoIniciado'    => false,
            'partidoTerminado' => false,
            'ganadorPartido'   => null,
        ]);
    }

    public static function desdeArray(array $estado): self
    {
        return new self($estado);
    }

    public function aArray(): array
    {
        return $this->s;
    }

    public function eventos(): array
    {
        return $this->eventos;
    }

    public function partidoTerminado(): bool
    {
        return (bool) $this->s['partidoTerminado'];
    }

    public function ganadorPartido(): ?string
    {
        return $this->s['ganadorPartido'];
    }

    public function puntos(): array
    {
        return ['jugador' => (int) $this->s['jugador']['puntos'], 'tata' => (int) $this->s['tata']['puntos']];
    }

    public function puntosObjetivo(): int
    {
        return (int) $this->s['puntosObjetivo'];
    }

    private static function trucoInicial(): array
    {
        return ['valor' => 1, 'nivel' => 0, 'ultimoQueCanto' => null, 'esperandoRespuesta' => false,
                'quienCanto' => null, 'paraResponder' => null];
    }

    private static function envidoInicial(): array
    {
        return ['disponible' => true, 'cantado' => false, 'puntosEnJuego' => 0,
                'esperandoRespuesta' => false, 'paraResponder' => null,
                'cadena' => [], 'quienCanto' => null, 'puntosJugador' => null, 'tataPaso' => false];
    }

    // -----------------------------------------------------------------
    // Eventos para el navegador
    // -----------------------------------------------------------------
    /** Equivale a los setTimeout(..., delay() * factor) del JS original. */
    private function pausa(float $factor): void
    {
        $this->pausaPendiente += $factor;
    }

    /** Equivale a onMessage(msg) + onUpdate(obtenerEstado()). */
    private function emitir(?string $mensaje, array $extra = []): void
    {
        $this->eventos[] = array_merge([
            'pausa'   => round($this->pausaPendiente, 3),
            'mensaje' => $mensaje,
            'estado'  => $this->obtenerEstado(),
        ], $extra);
        $this->pausaPendiente = 0.0;
    }

    /** Evento único con el estado actual (para reanudar una partida). */
    public function emitirEstadoActual(string $mensaje): void
    {
        $this->emitir($mensaje);
    }

    /** Mensaje que se muestra al retomar una partida guardada. */
    public function mensajeReanudar(): string
    {
        $tata = $this->nombreTata();
        $env  = $this->s['envido'];
        if ($env['esperandoRespuesta'] && $env['cadena']) {
            return "🗣 {$tata} canta: \"¡" . self::NOMBRES_ENVIDO[end($env['cadena'])] . '!" - ¿Querés?';
        }
        if ($this->s['truco']['esperandoRespuesta'] && !empty($this->s['truco']['paraResponder'])) {
            return "🎲 ¡{$tata} canta {$this->s['truco']['paraResponder']['nombre']}!";
        }
        return $this->s['juegoIniciado'] ? 'Partida recuperada - Tu turno' : 'Partida recuperada - Repartí una nueva mano';
    }

    /**
     * Estado PÚBLICO (lo que ve el navegador). Igual al obtenerEstado()
     * original pero SIN la mano de Tata: solo la cantidad de cartas.
     */
    public function obtenerEstado(): array
    {
        $s = $this->s;
        $truco = $s['truco'];
        $envido = $s['envido'];
        return [
            'turno'            => $s['turno'],
            'ronda'            => $s['ronda'],
            'quienEsMano'      => $s['quienEsMano'],
            'ganadorRondas'    => $s['ganadorRondas'],
            'puntosObjetivo'   => $s['puntosObjetivo'],
            'jugador'          => [
                'nombre'  => $s['jugador']['nombre'],
                'puntos'  => $s['jugador']['puntos'],
                'mano'    => array_values($s['jugador']['mano']),
                'jugadas' => array_values($s['jugador']['jugadas']),
            ],
            'tata'             => [
                'nombre'        => $s['tata']['nombre'],
                'puntos'        => $s['tata']['puntos'],
                'cantidadMano'  => count($s['tata']['mano']),
                'jugadas'       => array_values($s['tata']['jugadas']),
            ],
            'truco'            => $truco,
            'envido'           => [
                'disponible'         => $envido['disponible'],
                'cantado'            => $envido['cantado'],
                'puntosEnJuego'      => $envido['puntosEnJuego'],
                'esperandoRespuesta' => $envido['esperandoRespuesta'],
                'quienCanto'         => $envido['quienCanto'],
                'cadena'             => $envido['cadena'],
                // Subidas posibles al responder (Envido, Real Envido, Falta Envido)
                'subidas'            => $envido['esperandoRespuesta'] ? self::subidasPermitidas($envido['cadena']) : [],
                'jugadorPuedeCantar' => $this->jugadorPuedeCantarEnvido(),
            ],
            'juegoIniciado'    => $s['juegoIniciado'],
            'partidoTerminado' => $s['partidoTerminado'],
            'ganadorPartido'   => $s['ganadorPartido'],
        ];
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------
    private function nombreTata(): string
    {
        return $this->s['tata']['nombre'];
    }

    private static function plural(int $pts): string
    {
        return $pts !== 1 ? 's' : '';
    }

    /** Saca una carta de la mano y la pasa a "jugadas". Devuelve la carta o null. */
    private function sacarCarta(string $quien, string $id): ?array
    {
        foreach ($this->s[$quien]['mano'] as $i => $c) {
            if ($c['id'] === $id) {
                array_splice($this->s[$quien]['mano'], $i, 1);
                $this->s[$quien]['jugadas'][] = $c;
                return $c;
            }
        }
        return null;
    }

    private function ultimaJugada(string $quien): ?array
    {
        $j = $this->s[$quien]['jugadas'];
        return $j ? $j[count($j) - 1] : null;
    }

    /** Las 3 cartas con las que empezó la mano (para el envido). */
    private function manoInicial(string $quien): array
    {
        return array_merge($this->s[$quien]['jugadas'], $this->s[$quien]['mano']);
    }

    /**
     * Lo que Tata puede saber de la partida (nunca tu mano real).
     */
    private function contextoIA(): array
    {
        return [
            'manoT'    => array_values($this->s['tata']['mano']),
            'jugadasT' => array_values($this->s['tata']['jugadas']),
            'jugadasJ' => array_values($this->s['jugador']['jugadas']),
            'cantJ'    => count($this->s['jugador']['mano']),
            'gr'       => $this->s['ganadorRondas'],
            'turno'    => $this->s['turno'],
            'mano'     => $this->s['quienEsMano'],
            'envidoJ'  => $this->s['envido']['puntosJugador'],
        ];
    }

    private function datosTrucoPorNivel(int $n): array
    {
        $t = [[1, 2, 'Truco'], [2, 3, 'Retruco'], [3, 4, 'Vale 4']];
        if (!isset($t[$n])) {
            throw new AccionInvalida('No se puede subir más el truco.');
        }
        return ['nivel' => $t[$n][0], 'valor' => $t[$n][1], 'nombre' => $t[$n][2]];
    }

    /** Tata puede cantar si el último "quiero" lo tiene él (o nadie cantó todavía). */
    private function tataPuedeCantarTruco(): bool
    {
        $t = $this->s['truco'];
        if ($t['nivel'] >= 3 || ($t['nivel'] > 0 && $t['ultimoQueCanto'] === 'tata')) {
            return false;
        }
        if ($this->s['envido']['esperandoRespuesta']) {
            return false;
        }
        if ($t['nivel'] === 0) {
            // Igual que el jugador: el truco se canta después de la primera carta.
            if (!$this->s['jugador']['jugadas'] && !$this->s['tata']['jugadas']) {
                return false;
            }
            // "El envido está primero": mientras el jugador todavía puede
            // cantar envido (no tiró su primera carta), Tata no le canta truco.
            $e = $this->s['envido'];
            if ($e['disponible'] && !$e['cantado'] && !$this->s['jugador']['jugadas']) {
                return false;
            }
        }
        return true;
    }

    private function exigirManoEnJuego(): void
    {
        if ($this->s['partidoTerminado']) {
            throw new AccionInvalida('El partido ya terminó.');
        }
        if (!$this->s['juegoIniciado']) {
            throw new AccionInvalida('No hay una mano en juego.');
        }
    }

    /**
     * CORRECCIÓN respecto del original: el partido se verifica después de
     * CUALQUIER suma de puntos (envido, no quiero, mazo), no solo al final
     * de la mano. Reemplaza los alert() del JS por un evento "finPartido".
     */
    private function verificarFinPartido(): bool
    {
        if ($this->s['partidoTerminado']) {
            return true;
        }
        $obj = $this->s['puntosObjetivo'];
        $pj = $this->s['jugador']['puntos'];
        $pt = $this->s['tata']['puntos'];
        if ($pj >= $obj) {
            $this->s['ganadorPartido'] = 'jugador';
        } elseif ($pt >= $obj) {
            $this->s['ganadorPartido'] = 'tata';
        } else {
            return false;
        }
        $this->s['partidoTerminado'] = true;
        $this->s['juegoIniciado'] = false;
        $this->s['truco']['esperandoRespuesta'] = false;
        $this->s['envido']['esperandoRespuesta'] = false;
        $this->pausa(0.4);
        $msg = $this->s['ganadorPartido'] === 'jugador'
            ? "🏆 ¡FELICITACIONES! ¡Ganaste el partido {$pj} a {$pt}!"
            : "😔 {$this->nombreTata()} ganó el partido {$pt} a {$pj}";
        $this->emitir($msg, ['finPartido' => true]);
        return true;
    }

    // -----------------------------------------------------------------
    // MANO
    // -----------------------------------------------------------------
    public function iniciarMano(): void
    {
        if ($this->s['partidoTerminado']) {
            throw new AccionInvalida('El partido ya terminó. Empezá uno nuevo.');
        }
        if ($this->s['juegoIniciado']) {
            throw new AccionInvalida('La mano actual todavía no terminó.');
        }
        // La mano se alterna en cada reparto. Las partidas guardadas antes
        // de este cambio no tienen 'manosJugadas' y simplemente alternan.
        $manosJugadas = $this->s['manosJugadas'] ?? 1;
        if ($manosJugadas > 0) {
            $this->s['quienEsMano'] = $this->s['quienEsMano'] === 'jugador' ? 'tata' : 'jugador';
        }
        $this->s['manosJugadas'] = $manosJugadas + 1;

        $mazo = Cartas::mazoBarajado();
        $this->s['jugador']['mano'] = array_splice($mazo, 0, 3);
        $this->s['tata']['mano']    = array_splice($mazo, 0, 3);
        $this->s['jugador']['jugadas'] = [];
        $this->s['tata']['jugadas']    = [];
        $this->s['truco']  = self::trucoInicial();
        $this->s['envido'] = self::envidoInicial();
        $this->s['ronda'] = 0;
        $this->s['ganadorRondas'] = [];
        $this->s['turno'] = $this->s['quienEsMano'];
        $this->s['juegoIniciado'] = true;

        $this->emitir($this->s['turno'] === 'jugador'
            ? 'Tu turno - Sos mano, jugá primero'
            : "{$this->nombreTata()} es mano - Juega primero");

        if ($this->s['turno'] === 'tata') {
            $this->pausa(1);
            $this->jugarTurnoIA(null);
        }
    }

    /** Acción del jugador humano: jugar la carta con id $id. */
    public function jugarCarta(string $id): void
    {
        $this->exigirManoEnJuego();
        if ($this->s['turno'] !== 'jugador') {
            throw new AccionInvalida('Todavía no es tu turno.');
        }
        if ($this->s['truco']['esperandoRespuesta'] || $this->s['envido']['esperandoRespuesta']) {
            throw new AccionInvalida('Primero respondé el canto.');
        }
        $c = $this->sacarCarta('jugador', $id);
        if ($c === null) {
            throw new AccionInvalida('Esa carta no está en tu mano.');
        }
        // (El envido NO se cierra acá: si sos mano y ya tiraste, Tata, que
        // es pie, todavía puede cantarlo antes de tirar su primera carta.)
        $this->s['turno'] = 'tata';
        $this->emitir(null);

        $jLen = count($this->s['jugador']['jugadas']);
        $tLen = count($this->s['tata']['jugadas']);
        if ($jLen === $tLen) {
            $this->pausa(0.5);
            $this->resolverRonda();
        } else {
            $this->pausa(1 / 1.5);
            $this->jugarTurnoIA($c);
        }
    }

    private function tataPuedeCantarEnvido(): bool
    {
        $e = $this->s['envido'];
        return $e['disponible'] && !$e['cantado'] && !$e['tataPaso'] && !$e['esperandoRespuesta']
            && !$this->s['tata']['jugadas'] && $this->s['truco']['nivel'] === 0
            && !$this->s['truco']['esperandoRespuesta'];
    }

    private function jugadorPuedeCantarEnvido(): bool
    {
        $e = $this->s['envido'];
        return $this->s['juegoIniciado'] && $e['disponible'] && !$e['cantado'] && !$e['esperandoRespuesta']
            && $this->s['turno'] === 'jugador' && !$this->s['jugador']['jugadas']
            && $this->s['truco']['nivel'] === 0 && !$this->s['truco']['esperandoRespuesta'];
    }

    private function jugarTurnoIA(?array $cartaJ): void
    {
        if (!$this->s['juegoIniciado'] || $this->s['turno'] !== 'tata' || !$this->s['tata']['mano']) {
            return;
        }

        // NUEVO: Tata canta envido por su cuenta antes de tirar su primera carta.
        if ($this->tataPuedeCantarEnvido()) {
            $tipo = $this->ia->decideCantarEnvido($this->contextoIA());
            if ($tipo !== null) {
                $this->proponerEnvido('tata', $tipo);
                return;   // espera tu respuesta
            }
            $this->s['envido']['tataPaso'] = true;
        }

        if (!$this->s['truco']['esperandoRespuesta'] && $this->tataPuedeCantarTruco()
            && $this->ia->decideCantarTruco($this->s['truco']['nivel'], $this->contextoIA())) {
            $this->cantarTrucoInterno('tata');
            return;
        }

        $cartaIA = $this->ia->elegirCarta($this->contextoIA());
        $this->sacarCarta('tata', $cartaIA['id']);
        $this->s['turno'] = 'jugador';
        $this->emitir(null);

        $jLen = count($this->s['jugador']['jugadas']);
        $tLen = count($this->s['tata']['jugadas']);
        if ($jLen === $tLen) {
            $this->pausa(0.5);
            $this->resolverRonda();
        } else {
            $this->pausa(1 / 1.5);
            if (!$this->s['truco']['esperandoRespuesta'] && $this->tataPuedeCantarTruco()
                && $this->ia->decideCantarTruco($this->s['truco']['nivel'], $this->contextoIA())) {
                $this->cantarTrucoInterno('tata');
            } else {
                $this->emitir('Tu turno - Jugá una carta');
            }
        }
    }

    /**
     * Después de que se resuelve un canto, si le toca a Tata, Tata juega.
     */
    private function continuarTataSiCorresponde(): void
    {
        if (!$this->s['juegoIniciado'] || $this->s['turno'] !== 'tata' || !$this->s['tata']['mano']) {
            return;
        }
        $cartaJ = count($this->s['tata']['jugadas']) < count($this->s['jugador']['jugadas'])
            ? $this->ultimaJugada('jugador')
            : null;
        $this->pausa(1);
        $this->jugarTurnoIA($cartaJ);
    }

    private function resolverRonda(): void
    {
        $cJ = $this->ultimaJugada('jugador');
        $cT = $this->ultimaJugada('tata');
        $r = Cartas::valorTruco($cJ) <=> Cartas::valorTruco($cT);
        $ganador = $r > 0 ? 'jugador' : ($r < 0 ? 'tata' : 'empate');
        $this->s['ganadorRondas'][] = $ganador;
        $this->s['ronda'] += 1;
        // Terminada la primera ronda ya no se puede cantar envido.
        $this->s['envido']['disponible'] = false;

        $this->emitir($ganador === 'jugador' ? '¡Ganaste esta ronda!'
            : ($ganador === 'tata' ? "{$this->nombreTata()} ganó esta ronda" : 'Parda (empate) en esta ronda'));

        // CORRECCIÓN: la mano termina apenas está definida ("parda la
        // primera, gana la segunda", etc.), sin jugar rondas de más.
        $decidido = EstrategiaIA::ganadorDecidido($this->s['ganadorRondas'], $this->s['quienEsMano']);
        if ($decidido !== null) {
            $this->pausa(1);
            $this->resolverMano($decidido);
            return;
        }

        if ($ganador === 'jugador') {
            $this->s['turno'] = 'jugador';
            $this->emitir('Ganaste la ronda - Jugás primero');
        } elseif ($ganador === 'tata') {
            $this->s['turno'] = 'tata';
            $this->emitir("{$this->nombreTata()} ganó la ronda - Juega primero");
            $this->pausa(1);
            $this->jugarTurnoIA(null);
        } else {
            $this->s['turno'] = $this->s['quienEsMano'];
            $quien = $this->s['turno'] === 'jugador' ? 'Jugás vos' : "Juega {$this->nombreTata()}";
            $mano  = $this->s['quienEsMano'] === 'jugador' ? 'sos' : 'es';
            $this->emitir("Parda - {$quien} ({$mano} mano)");
            if ($this->s['turno'] === 'tata') {
                $this->pausa(1);
                $this->jugarTurnoIA(null);
            }
        }
    }

    private function resolverMano(string $ganador): void
    {
        $pts = (int) $this->s['truco']['valor'];
        $this->s[$ganador]['puntos'] += $pts;
        $this->s['juegoIniciado'] = false;
        $this->emitir($ganador === 'jugador'
            ? "🎉 ¡GANASTE LA MANO! (+{$pts} punto" . self::plural($pts) . ')'
            : "😢 {$this->nombreTata()} ganó la mano (+{$pts} punto" . self::plural($pts) . ')');
        $this->verificarFinPartido();
    }

    // -----------------------------------------------------------------
    // TRUCO
    // -----------------------------------------------------------------
    /** Acción del jugador: cantar Truco / Retruco / Vale 4. */
    public function cantarTruco(): void
    {
        $this->exigirManoEnJuego();
        if ($this->s['truco']['esperandoRespuesta'] || $this->s['envido']['esperandoRespuesta']) {
            throw new AccionInvalida('Hay un canto esperando respuesta.');
        }
        if (!$this->s['jugador']['jugadas'] && !$this->s['tata']['jugadas']) {
            throw new AccionInvalida('El truco se canta después de la primera carta.');
        }
        $t = $this->s['truco'];
        if ($t['nivel'] >= 3 || ($t['nivel'] > 0 && $t['ultimoQueCanto'] === 'jugador')) {
            throw new AccionInvalida('Ahora no podés subir el truco: el quiero lo tiene Tata.');
        }
        $this->cantarTrucoInterno('jugador');
    }

    private function cantarTrucoInterno(string $by): void
    {
        $t = $this->s['truco'];
        if ($t['nivel'] >= 3 || ($t['nivel'] > 0 && $t['ultimoQueCanto'] === $by)) {
            return;
        }
        $datos = $this->datosTrucoPorNivel($t['nivel']);
        $this->s['truco']['esperandoRespuesta'] = true;
        $this->s['truco']['quienCanto'] = $by;
        $this->s['truco']['paraResponder'] = $datos;
        $this->emitir($by === 'jugador' ? "¡Cantaste {$datos['nombre']}!" : "🎲 ¡{$this->nombreTata()} canta {$datos['nombre']}!");

        if ($by === 'jugador') {
            $this->pausa(1);
            $this->tataRespondeTruco();
        }
    }

    private function aceptarTruco(string $ultimoQueCanto): array
    {
        $info = $this->s['truco']['paraResponder'];
        $this->s['truco']['valor'] = $info['valor'];
        $this->s['truco']['nivel'] = $info['nivel'];
        $this->s['truco']['ultimoQueCanto'] = $ultimoQueCanto;
        $this->s['truco']['esperandoRespuesta'] = false;
        $this->s['truco']['quienCanto'] = null;
        $this->s['truco']['paraResponder'] = null;
        // Con el truco querido ya no se puede cantar envido.
        $this->s['envido']['disponible'] = false;
        return $info;
    }

    /**
     * Subir (retruco / vale 4) en respuesta a un canto. CORRECCIÓN: el canto
     * anterior queda querido, así que si después el otro no quiere, pierde
     * lo que valía ese canto (antes perdía solo 1 punto).
     */
    private function subirTruco(string $by): array
    {
        $t = $this->s['truco'];
        $prop = $t['paraResponder'];
        if ((int) $prop['nivel'] >= 3) {
            throw new AccionInvalida('No se puede subir más que Vale 4.');
        }
        $this->s['truco']['valor'] = $prop['valor'];
        $this->s['truco']['nivel'] = $prop['nivel'];
        $this->s['truco']['ultimoQueCanto'] = $t['quienCanto'];
        $this->s['envido']['disponible'] = false;
        $nuevo = $this->datosTrucoPorNivel((int) $prop['nivel']);
        $this->s['truco']['quienCanto'] = $by;
        $this->s['truco']['paraResponder'] = $nuevo;
        $this->s['truco']['esperandoRespuesta'] = true;
        return $nuevo;
    }

    private function tataNoQuiere(): void
    {
        $pts = (int) $this->s['truco']['valor'];
        $this->s['jugador']['puntos'] += $pts;
        $this->s['juegoIniciado'] = false;
        $this->s['truco']['esperandoRespuesta'] = false;
        $this->emitir("❌ {$this->nombreTata()}: \"NO QUIERO\" - ¡Ganaste la mano! +{$pts} punto" . self::plural($pts));
        $this->verificarFinPartido();
    }

    private function tataRespondeTruco(): void
    {
        $t = $this->s['truco'];
        if (!$this->s['juegoIniciado'] || !$t['esperandoRespuesta'] || $t['quienCanto'] !== 'jugador') {
            return;
        }
        $decision = $this->ia->decideResponderTruco($t['paraResponder'], (int) $t['valor'], $this->contextoIA());
        if ($decision === 'subir' && (int) $t['paraResponder']['nivel'] < 3) {
            $nuevo = $this->subirTruco('tata');
            $this->emitir("🎲 {$this->nombreTata()}: \"¡QUIERO {$nuevo['nombre']}!\"");
        } elseif ($decision === 'aceptar') {
            $this->aceptarTruco('jugador');
            $this->emitir("🎯 {$this->nombreTata()}: \"¡QUIERO!\" - Ahora vale {$this->s['truco']['valor']} puntos");
            $this->continuarTataSiCorresponde();
        } else {
            $this->tataNoQuiere();
        }
    }

    /** Acción del jugador: responder a un canto de Tata ('aceptar' | 'rechazar' | 'subir'). */
    public function responderTruco(string $accion): void
    {
        $this->exigirManoEnJuego();
        $t = $this->s['truco'];
        if (!$t['esperandoRespuesta'] || $t['quienCanto'] !== 'tata') {
            throw new AccionInvalida('No hay ningún truco para responder.');
        }

        if ($accion === 'aceptar') {
            $info = $this->aceptarTruco('tata');
            $this->emitir("Aceptaste el {$info['nombre']} - Ahora vale {$info['valor']} puntos");
            $this->continuarTataSiCorresponde();
            return;
        }

        if ($accion === 'subir') {
            $nuevo = $this->subirTruco('jugador');
            $this->emitir("¡Quiero {$nuevo['nombre']}!");
            $this->pausa(1 / 1.3);
            $this->tataRespondeTruco();
            return;
        }

        if ($accion === 'rechazar') {
            $pts = (int) $t['valor'];
            $this->s['tata']['puntos'] += $pts;
            $this->s['juegoIniciado'] = false;
            $this->s['truco']['esperandoRespuesta'] = false;
            $this->emitir("No quisiste - {$this->nombreTata()} gana la mano. +{$pts} punto" . self::plural($pts));
            $this->verificarFinPartido();
            return;
        }

        throw new AccionInvalida('Respuesta de truco desconocida.');
    }

    // -----------------------------------------------------------------
    // ENVIDO
    // -----------------------------------------------------------------
    /** Qué se puede cantar encima de lo ya cantado. */
    private static function subidasPermitidas(array $cadena): array
    {
        if (in_array('faltaenvido', $cadena, true)) {
            return [];
        }
        $hayReal = in_array('realenvido', $cadena, true);
        $nEnvido = count(array_keys($cadena, 'envido', true));
        $r = [];
        if (!$hayReal && $nEnvido < 2) $r[] = 'envido';
        if (!$hayReal) $r[] = 'realenvido';
        $r[] = 'faltaenvido';
        return $r;
    }

    /** Puntos si se quiere: suma de lo cantado (la Falta Envido vale lo suyo). */
    private function valorQuerido(array $cadena, ?string $ganador): int
    {
        if (in_array('faltaenvido', $cadena, true)) {
            return $this->puntosFaltaEnvido($ganador);
        }
        $v = 0;
        foreach ($cadena as $tipo) {
            $v += $tipo === 'realenvido' ? 3 : 2;
        }
        return $v;
    }

    /** Puntos si no se quiere: lo que valía antes del último canto (mínimo 1). */
    private function valorNoQuerido(array $cadena): int
    {
        if (count($cadena) <= 1) {
            return 1;
        }
        return $this->valorQuerido(array_slice($cadena, 0, -1), null);
    }

    /** Acción del jugador: cantar envido (primer canto de la mano). */
    public function cantarEnvido(string $tipo): void
    {
        $this->exigirManoEnJuego();
        if (!isset(self::NOMBRES_ENVIDO[$tipo])) {
            throw new AccionInvalida('Tipo de envido desconocido.');
        }
        if (!$this->jugadorPuedeCantarEnvido()) {
            throw new AccionInvalida('Ahora no se puede cantar envido.');
        }
        $this->proponerEnvido('jugador', $tipo);
    }

    /** Acción del jugador: responder un envido de Tata ('quiero' | 'noquiero' | tipo para subir). */
    public function responderEnvido(string $resp): void
    {
        $this->exigirManoEnJuego();
        $e = $this->s['envido'];
        if (!$e['esperandoRespuesta'] || $e['quienCanto'] !== 'tata') {
            throw new AccionInvalida('No hay ningún envido para responder.');
        }
        if ($resp === 'quiero') {
            $this->emitir('¡Quiero!');
            $this->pausa(1 / 1.2);
            $this->resolverEnvido(true);
            return;
        }
        if ($resp === 'noquiero') {
            $this->resolverEnvido(false);
            return;
        }
        if (in_array($resp, self::subidasPermitidas($e['cadena']), true)) {
            $this->proponerEnvido('jugador', $resp);
            return;
        }
        throw new AccionInvalida('Respuesta de envido inválida.');
    }

    /** Canta (o sube) un envido y, si lo cantó el jugador, responde Tata. */
    private function proponerEnvido(string $by, string $tipo): void
    {
        $this->s['envido']['cantado'] = true;
        $this->s['envido']['cadena'][] = $tipo;
        $this->s['envido']['quienCanto'] = $by;
        $this->s['envido']['esperandoRespuesta'] = true;
        $puntos = $this->valorQuerido($this->s['envido']['cadena'], null);
        $this->s['envido']['puntosEnJuego'] = $puntos;
        $this->s['envido']['paraResponder'] = ['tipo' => $tipo, 'puntos' => $puntos];
        $nombre = self::NOMBRES_ENVIDO[$tipo];
        $this->emitir($by === 'jugador'
            ? "¡{$nombre}!"
            : "🗣 {$this->nombreTata()} canta: \"¡{$nombre}!\" - ¿Querés?");

        if ($by === 'jugador') {
            $this->pausa(1 / 1.2);
            $this->tataRespondeEnvido();
        }
    }

    /**
     * Puntos de la Falta Envido:
     *  - Partido a 15, o a 30 cuando alguien ya está "en las buenas"
     *    (15 o más): lo que le falta al que va ganando para llegar al
     *    objetivo.
     *  - Partido a 30 con los dos "en las malas" (menos de 15): la falta
     *    envido gana el partido, o sea, lo que le falta al ganador del envido.
     * Con $ganador = null devuelve el valor estimado para mostrar al cantar.
     */
    private function puntosFaltaEnvido(?string $ganador): int
    {
        $obj = (int) $this->s['puntosObjetivo'];
        $pj  = (int) $this->s['jugador']['puntos'];
        $pt  = (int) $this->s['tata']['puntos'];
        $lider = max($pj, $pt);
        if ($obj === 30 && $lider < 15 && $ganador !== null) {
            return $obj - (int) $this->s[$ganador]['puntos'];
        }
        return max(1, $obj - $lider);
    }

    private function tataRespondeEnvido(): void
    {
        $e = $this->s['envido'];
        if (!$this->s['juegoIniciado'] || !$e['esperandoRespuesta'] || $e['quienCanto'] !== 'jugador') {
            return;
        }
        $tata = $this->nombreTata();
        $dec = $this->ia->decideResponderEnvido(
            $this->contextoIA(),
            $e['cadena'],
            $this->valorQuerido($e['cadena'], null),
            $this->valorNoQuerido($e['cadena']),
            self::subidasPermitidas($e['cadena'])
        );
        if ($dec === 'quiero') {
            $this->emitir("{$tata}: \"¡Quiero!\"");
            $this->pausa(1 / 1.2);
            $this->resolverEnvido(true);
        } elseif ($dec === 'noquiero') {
            $this->resolverEnvido(false);
        } else {
            $this->proponerEnvido('tata', $dec);   // Tata sube y espera tu respuesta
        }
    }

    /** Cierra el envido (querido o no) y sigue la mano. */
    private function resolverEnvido(bool $querido): void
    {
        $e = $this->s['envido'];
        $tata = $this->nombreTata();
        $quienCanto = $e['quienCanto'];

        if ($querido) {
            // Los tantos se cuentan con las 3 cartas de la mano, aunque ya
            // se haya tirado alguna.
            $ptsJ = EstrategiaIA::envidoDe($this->manoInicial('jugador'));
            $ptsT = EstrategiaIA::envidoDe($this->manoInicial('tata'));
            $ganador = $ptsJ !== $ptsT ? ($ptsJ > $ptsT ? 'jugador' : 'tata') : $this->s['quienEsMano'];
            $pts = $this->valorQuerido($e['cadena'], $ganador);
            $empate = $ptsJ === $ptsT
                ? ' (empate: gana ' . ($ganador === 'jugador' ? 'vos, que sos mano' : "{$tata}, que es mano") . ')'
                : '';
            $this->s[$ganador]['puntos'] += $pts;
            $this->s['envido']['puntosJugador'] = $ptsJ;   // Tata ahora sabe tus tantos
            $msg = $ganador === 'jugador'
                ? "{$tata} tiene {$ptsT} - Vos tenés {$ptsJ}{$empate} - ¡Ganaste {$pts} punto" . self::plural($pts) . '!'
                : "Vos tenés {$ptsJ} - {$tata} tiene {$ptsT}{$empate} - {$tata} gana {$pts} punto" . self::plural($pts);
        } else {
            $pts = $this->valorNoQuerido($e['cadena']);
            $this->s[$quienCanto]['puntos'] += $pts;
            $msg = $quienCanto === 'jugador'
                ? "{$tata}: \"No quiero\" - Ganás {$pts} punto" . self::plural($pts)
                : "No quisiste - {$tata} gana {$pts} punto" . self::plural($pts);
        }

        $this->s['envido']['esperandoRespuesta'] = false;
        $this->s['envido']['disponible'] = false;
        $this->s['envido']['quienCanto'] = null;
        $this->s['envido']['paraResponder'] = null;
        $this->s['envido']['puntosEnJuego'] = $pts;
        $this->emitir($msg);
        if ($this->verificarFinPartido()) {
            return;
        }
        // Si Tata cantó el envido en su turno, ahora tira su carta.
        $this->continuarTataSiCorresponde();
    }

    // -----------------------------------------------------------------
    // MAZO
    // -----------------------------------------------------------------
    public function irseAlMazo(): void
    {
        $this->exigirManoEnJuego();
        $tata = $this->nombreTata();
        $e = $this->s['envido'];
        // Irse con un envido de Tata sin responder equivale a no quererlo.
        if ($e['esperandoRespuesta'] && $e['quienCanto'] === 'tata') {
            $ptsE = $this->valorNoQuerido($e['cadena']);
            $this->s['tata']['puntos'] += $ptsE;
            $this->s['envido']['esperandoRespuesta'] = false;
            $this->s['envido']['disponible'] = false;
            $this->emitir("No quisiste el envido - {$tata} gana {$ptsE} punto" . self::plural($ptsE));
            if ($this->verificarFinPartido()) {
                return;
            }
        }
        $pts = (int) $this->s['truco']['valor'];
        $this->s['tata']['puntos'] += $pts;
        $this->s['juegoIniciado'] = false;
        $this->s['truco']['esperandoRespuesta'] = false;
        $this->emitir("Te fuiste al mazo - {$tata} gana {$pts} punto" . self::plural($pts));
        $this->verificarFinPartido();
    }
}
