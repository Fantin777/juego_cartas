<?php
/**
 * models/IntentoLogin.php
 * Intentos fallidos de inicio de sesión (protección contra fuerza bruta).
 */
declare(strict_types=1);

final class IntentoLogin
{
    /** Intentos fallidos recientes: ['por_usuario' => n, 'por_ip' => n]. */
    public static function recientes(string $usuario, string $ip, int $minutos): array
    {
        $st = db()->prepare(
            'SELECT COALESCE(SUM(usuario = ?), 0) AS por_usuario,
                    COALESCE(SUM(ip = ?), 0)      AS por_ip
               FROM intentos_login
              WHERE fecha > (NOW() - INTERVAL ? MINUTE)'
        );
        $st->execute([$usuario, $ip, $minutos]);
        $f = $st->fetch() ?: [];
        return ['por_usuario' => (int) ($f['por_usuario'] ?? 0), 'por_ip' => (int) ($f['por_ip'] ?? 0)];
    }

    public static function registrar(string $usuario, string $ip): void
    {
        db()->prepare('INSERT INTO intentos_login (usuario, ip) VALUES (?, ?)')->execute([$usuario, $ip]);
    }

    /** Borra los intentos del usuario (al entrar bien) y los muy viejos de todos. */
    public static function limpiar(string $usuario, int $minutos): void
    {
        db()->prepare('DELETE FROM intentos_login WHERE usuario = ? OR fecha < (NOW() - INTERVAL ? MINUTE)')
            ->execute([$usuario, $minutos * 4]);
    }
}
