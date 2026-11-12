<?php
/**
 * controllers/JuegoController.php
 * GET / — Pantalla del juego (solo con sesión iniciada).
 */
declare(strict_types=1);

final class JuegoController
{
    public function index(): void
    {
        $yo = requiere_login();
        try {
            $puntos = Usuario::puntos($yo['id']);
            if ($puntos === null) {
                // La cuenta fue borrada mientras la sesión seguía abierta.
                cerrar_sesion_usuario();
                redirigir(url('login'));
            }
            $hayPartida = Partida::hayEnCurso($yo['id']);
        } catch (PDOException $ex) {
            error_log('[truco] Juego: ' . $ex->getMessage());
            error_fatal_html('No se pudo conectar con la base de datos. Revisá que WampServer esté en verde y los datos de .env.');
        }
        vista('juego', ['yo' => $yo, 'misPuntos' => $puntos, 'hayPartidaEnCurso' => $hayPartida]);
    }
}
