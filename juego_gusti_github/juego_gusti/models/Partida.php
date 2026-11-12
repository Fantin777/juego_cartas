<?php
/**
 * models/Partida.php
 * Consultas SQL sobre la tabla `partidas`.
 *
 * `estado_juego` guarda el estado COMPLETO del motor (incluida la mano
 * secreta de Tata) en JSON. Nunca se envía al navegador.
 */
declare(strict_types=1);

final class Partida
{
    /**
     * Partida en curso del usuario (como máximo hay una).
     * Con $bloquear = true la fila queda reservada hasta el commit.
     */
    public static function enCurso(int $usuarioId, bool $bloquear = false): ?array
    {
        $sql = "SELECT id, codigo, estado_juego FROM partidas
                 WHERE usuario_id = ? AND resultado = 'en_curso'
                 ORDER BY id DESC LIMIT 1" . ($bloquear ? ' FOR UPDATE' : '');
        $st = db()->prepare($sql);
        $st->execute([$usuarioId]);
        return $st->fetch() ?: null;
    }

    public static function hayEnCurso(int $usuarioId): bool
    {
        $st = db()->prepare("SELECT 1 FROM partidas WHERE usuario_id = ? AND resultado = 'en_curso' LIMIT 1");
        $st->execute([$usuarioId]);
        return (bool) $st->fetchColumn();
    }

    /** Inserta una partida nueva y devuelve ['id' => ..., 'codigo' => ...]. */
    public static function crear(int $usuarioId, int $objetivo, array $estado): array
    {
        $codigo = bin2hex(random_bytes(16));
        db()->prepare('INSERT INTO partidas (codigo, usuario_id, puntos_objetivo, estado_juego) VALUES (?, ?, ?, ?)')
            ->execute([$codigo, $usuarioId, $objetivo, self::json($estado)]);
        return ['id' => (int) db()->lastInsertId(), 'codigo' => $codigo];
    }

    /** Guarda el estado del motor y el marcador de una partida en curso. */
    public static function guardar(int $id, array $estado, array $puntos): void
    {
        db()->prepare('UPDATE partidas SET estado_juego = ?, puntos_jugador = ?, puntos_tata = ? WHERE id = ?')
            ->execute([self::json($estado), self::tope($puntos['jugador']), self::tope($puntos['tata']), $id]);
    }

    /**
     * Cierra la partida con 'victoria', 'derrota' o 'abandonada'.
     * El "AND resultado = 'en_curso'" garantiza que una partida se cierre
     * (y otorgue puntos) UNA sola vez. Devuelve true si la cerró ahora.
     */
    public static function cerrar(int $id, string $resultado, int $puntosGanados, array $puntos, ?array $estado = null): bool
    {
        $st = db()->prepare(
            "UPDATE partidas
                SET resultado = ?, puntos_ganados = ?, puntos_jugador = ?, puntos_tata = ?,
                    estado_juego = COALESCE(?, estado_juego), fecha = NOW()
              WHERE id = ? AND resultado = 'en_curso'"
        );
        $st->execute([$resultado, $puntosGanados, self::tope($puntos['jugador']), self::tope($puntos['tata']),
                      $estado === null ? null : self::json($estado), $id]);
        return $st->rowCount() === 1;
    }

    /** Cantidad de partidas terminadas por resultado: ['victoria' => 3, ...]. */
    public static function conteoResultados(int $usuarioId): array
    {
        $st = db()->prepare(
            "SELECT resultado, COUNT(*) AS n FROM partidas
              WHERE usuario_id = ? AND resultado <> 'en_curso'
              GROUP BY resultado"
        );
        $st->execute([$usuarioId]);
        $conteo = ['victoria' => 0, 'derrota' => 0, 'empate' => 0, 'abandonada' => 0];
        foreach ($st->fetchAll() as $f) {
            $conteo[$f['resultado']] = (int) $f['n'];
        }
        return $conteo;
    }

    private static function json(array $estado): string
    {
        return json_encode($estado, JSON_UNESCAPED_UNICODE);
    }

    /** Las columnas de puntos son TINYINT UNSIGNED (máx. 255). */
    private static function tope(int $n): int
    {
        return max(0, min(255, $n));
    }
}
