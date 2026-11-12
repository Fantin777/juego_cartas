<?php
/**
 * services/PartidaService.php
 * Une el motor del juego (motor/Arbitro.php) con la base de datos.
 *
 * SEGURIDAD: el navegador nunca informa puntos ni resultados; solo pide
 * acciones ("jugar el 7 de espada", "quiero"…). El motor las valida contra
 * el estado guardado y decide quién gana. Cada acción corre en una
 * transacción con las filas del usuario y de la partida bloqueadas, y los
 * puntos de una victoria se suman una única vez.
 *
 * Todas las respuestas tienen la misma forma:
 *   { ok, eventos[], partida{codigo,enCurso}|null, usuario{puntos}, resultado|null }
 */
declare(strict_types=1);

final class PartidaService
{
    /** Empieza un partido nuevo. Si había uno en curso, queda abandonado. */
    public function nueva(int $usuarioId, int $objetivo, string $nombreJugador, string $nombreOponente): array
    {
        if (!in_array($objetivo, OBJETIVOS_VALIDOS, true)) {
            throw new ErrorHttp(422, 'Puntos para ganar inválidos: usá 15 o 30.');
        }
        return en_transaccion(function () use ($usuarioId, $objetivo, $nombreJugador, $nombreOponente) {
            $puntosUsuario = $this->bloquearUsuario($usuarioId);
            $this->abandonarSiHay($usuarioId);

            $arbitro = Arbitro::nuevoPartido(
                self::limpiarNombre($nombreJugador, 'Vos'),
                self::limpiarNombre($nombreOponente, 'Tata'),
                $objetivo
            );
            $arbitro->iniciarMano();
            $partida = Partida::crear($usuarioId, $objetivo, $arbitro->aArray());

            return $this->respuesta($arbitro, $partida, $puntosUsuario, null);
        });
    }

    /** Estado de la partida en curso (solo lectura: no modifica nada). */
    public function actual(int $usuarioId): array
    {
        $puntos  = Usuario::puntos($usuarioId) ?? 0;
        $partida = Partida::enCurso($usuarioId);
        $arbitro = $partida ? self::cargarArbitro($partida) : null;
        if (!$arbitro) {
            return ['ok' => true, 'eventos' => [], 'partida' => null, 'usuario' => ['puntos' => $puntos], 'resultado' => null];
        }
        $arbitro->emitirEstadoActual($arbitro->mensajeReanudar());
        return $this->respuesta($arbitro, $partida, $puntos, null);
    }

    /** Abandona la partida en curso (no suma puntos). */
    public function abandonar(int $usuarioId): array
    {
        return en_transaccion(function () use ($usuarioId) {
            $puntos = $this->bloquearUsuario($usuarioId);
            $habia = $this->abandonarSiHay($usuarioId);
            return ['ok' => true, 'abandonada' => $habia, 'eventos' => [], 'partida' => null,
                    'usuario' => ['puntos' => $puntos], 'resultado' => null];
        });
    }

    /**
     * Ejecuta una acción de juego sobre la partida en curso.
     *   $servicio->jugar($id, fn (Arbitro $a) => $a->jugarCarta('7espada'));
     */
    public function jugar(int $usuarioId, callable $accion): array
    {
        try {
            return en_transaccion(function () use ($usuarioId, $accion) {
                $puntosUsuario = $this->bloquearUsuario($usuarioId);
                $partida = Partida::enCurso($usuarioId, true);
                $arbitro = $partida ? self::cargarArbitro($partida) : null;
                if (!$arbitro) {
                    throw new ErrorHttp(404, 'No tenés una partida en curso. Volvé al menú y tocá JUGAR.');
                }

                $accion($arbitro);   // las reglas las aplica el motor

                $resultado = null;
                $pts = $arbitro->puntos();
                if ($arbitro->partidoTerminado()) {
                    $gano    = $arbitro->ganadorPartido() === 'jugador';
                    $ganados = $gano ? PUNTOS_POR_VICTORIA : 0;
                    $cerrada = Partida::cerrar((int) $partida['id'], $gano ? 'victoria' : 'derrota', $ganados, $pts, $arbitro->aArray());
                    if (!$cerrada) {
                        $ganados = 0;                  // ya estaba cerrada: no se suma de nuevo
                    } elseif ($ganados > 0) {
                        Usuario::sumarPuntos($usuarioId, $ganados);
                        $puntosUsuario += $ganados;
                    }
                    $resultado = ['resultado' => $gano ? 'victoria' : 'derrota', 'puntosGanados' => $ganados,
                                  'puntosTotales' => $puntosUsuario,
                                  'puntosJugador' => $pts['jugador'], 'puntosTata' => $pts['tata']];
                } else {
                    Partida::guardar((int) $partida['id'], $arbitro->aArray(), $pts);
                }
                return $this->respuesta($arbitro, $partida, $puntosUsuario, $resultado);
            });
        } catch (AccionInvalida $ex) {
            // La transacción ya se deshizo. Devolvemos el estado guardado
            // para que la pantalla se resincronice.
            $partida = Partida::enCurso($usuarioId);
            $arbitro = $partida ? self::cargarArbitro($partida) : null;
            throw new ErrorHttp(409, $ex->getMessage(), ['estado' => $arbitro ? $arbitro->obtenerEstado() : null]);
        }
    }

    // -----------------------------------------------------------------
    // Ayudantes
    // -----------------------------------------------------------------

    /** Bloquea la fila del usuario y devuelve sus puntos (401 si la cuenta ya no existe). */
    private function bloquearUsuario(int $usuarioId): int
    {
        $puntos = Usuario::puntosBloqueando($usuarioId);
        if ($puntos === null) {
            cerrar_sesion_usuario();
            throw new ErrorHttp(401, 'La cuenta ya no existe.', ['login' => true]);
        }
        return $puntos;
    }

    private function abandonarSiHay(int $usuarioId): bool
    {
        $partida = Partida::enCurso($usuarioId, true);
        if (!$partida) {
            return false;
        }
        $arbitro = self::cargarArbitro($partida);
        $pts = $arbitro ? $arbitro->puntos() : ['jugador' => 0, 'tata' => 0];
        return Partida::cerrar((int) $partida['id'], 'abandonada', 0, $pts);
    }

    /** Reconstruye el motor desde la BD. Si el estado está dañado, la partida se descarta. */
    private static function cargarArbitro(array $partida): ?Arbitro
    {
        $estado = json_decode((string) $partida['estado_juego'], true);
        if (is_array($estado)) {
            return Arbitro::desdeArray($estado);
        }
        Partida::cerrar((int) $partida['id'], 'abandonada', 0, ['jugador' => 0, 'tata' => 0]);
        return null;
    }

    private function respuesta(Arbitro $arbitro, array $partida, int $puntosUsuario, ?array $resultado): array
    {
        return [
            'ok'        => true,
            'eventos'   => $arbitro->eventos(),
            'partida'   => ['codigo' => $partida['codigo'], 'enCurso' => !$arbitro->partidoTerminado()],
            'usuario'   => ['puntos' => $puntosUsuario],
            'resultado' => $resultado,
        ];
    }

    /** Limpia los nombres que se muestran en el marcador (máx. 10 caracteres). */
    private static function limpiarNombre(string $valor, string $porDefecto): string
    {
        $v = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $valor));
        $v = mb_substr($v, 0, 10, 'UTF-8');
        return $v === '' ? $porDefecto : $v;
    }
}
