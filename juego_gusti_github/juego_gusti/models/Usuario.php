<?php
/**
 * models/Usuario.php
 * Consultas SQL sobre la tabla `usuarios`.
 * Los modelos SOLO hablan con la base de datos: no validan formularios ni
 * deciden reglas del juego (eso lo hacen los servicios).
 */
declare(strict_types=1);

final class Usuario
{
    public static function buscarPorNombre(string $usuario): ?array
    {
        $st = db()->prepare('SELECT id, usuario, password, puntos FROM usuarios WHERE usuario = ? LIMIT 1');
        $st->execute([$usuario]);
        return $st->fetch() ?: null;
    }

    public static function existe(string $usuario): bool
    {
        $st = db()->prepare('SELECT 1 FROM usuarios WHERE usuario = ? LIMIT 1');
        $st->execute([$usuario]);
        return (bool) $st->fetchColumn();
    }

    /** Puntos del usuario, o null si la cuenta ya no existe. */
    public static function puntos(int $id): ?int
    {
        $st = db()->prepare('SELECT puntos FROM usuarios WHERE id = ?');
        $st->execute([$id]);
        $v = $st->fetchColumn();
        return $v === false ? null : (int) $v;
    }

    /**
     * Igual que puntos(), pero bloquea la fila hasta el final de la
     * transacción (SELECT ... FOR UPDATE). Así dos peticiones del mismo
     * jugador al mismo tiempo se atienden de a una.
     */
    public static function puntosBloqueando(int $id): ?int
    {
        $st = db()->prepare('SELECT puntos FROM usuarios WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $v = $st->fetchColumn();
        return $v === false ? null : (int) $v;
    }

    /** Crea la cuenta y devuelve su id. */
    public static function crear(string $usuario, string $hash): int
    {
        db()->prepare('INSERT INTO usuarios (usuario, password) VALUES (?, ?)')->execute([$usuario, $hash]);
        return (int) db()->lastInsertId();
    }

    public static function actualizarPassword(int $id, string $hash): void
    {
        db()->prepare('UPDATE usuarios SET password = ? WHERE id = ?')->execute([$hash, $id]);
    }

    public static function registrarLogin(int $id): void
    {
        db()->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?')->execute([$id]);
    }

    public static function sumarPuntos(int $id, int $puntos): void
    {
        db()->prepare('UPDATE usuarios SET puntos = puntos + ? WHERE id = ?')->execute([$puntos, $id]);
    }

    /**
     * Top del ranking: puntos de mayor a menor, y a igualdad, alfabético.
     * Las victorias se cuentan con un JOIN agrupado (una sola pasada por
     * la tabla partidas en lugar de una subconsulta por cada jugador).
     */
    public static function ranking(int $limite): array
    {
        $st = db()->prepare(
            "SELECT u.id, u.usuario, u.puntos, COALESCE(v.victorias, 0) AS victorias
               FROM usuarios u
               LEFT JOIN (SELECT usuario_id, COUNT(*) AS victorias
                            FROM partidas WHERE resultado = 'victoria'
                           GROUP BY usuario_id) v ON v.usuario_id = u.id
              WHERE u.puntos > 0
              ORDER BY u.puntos DESC, u.usuario ASC
              LIMIT ?"
        );
        $st->bindValue(1, $limite, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    /** Posición en el ranking (1 = primero). Los empatados comparten posición. */
    public static function posicion(int $puntos): ?int
    {
        if ($puntos <= 0) {
            return null;
        }
        $st = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE puntos > ?');
        $st->execute([$puntos]);
        return (int) $st->fetchColumn() + 1;
    }

    public static function contar(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    }
}
