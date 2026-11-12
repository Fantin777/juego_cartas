<?php
/**
 * controllers/AuthApiController.php
 * Cuenta y sesión en formato JSON (/api/auth/...).
 * Pensado para probar el backend con Postman o cualquier cliente HTTP.
 *
 * La sesión se mantiene con la cookie TRUCOSESSID (Postman la guarda
 * solo). login y sesion devuelven el token "csrf" que hay que mandar en
 * la cabecera X-CSRF-Token de cada POST/DELETE posterior.
 */
declare(strict_types=1);

final class AuthApiController
{
    /** POST /api/auth/registro  { usuario, password, confirmar } */
    public function registro(): void
    {
        $this->exigirJson();
        $u = (new AuthService())->registrar(dato_texto('usuario'), dato_texto('password'), dato_texto('confirmar'));
        responder_json(['ok' => true, 'mensaje' => '¡Cuenta creada! Ya podés iniciar sesión.',
                        'usuario' => ['usuario' => $u['usuario'], 'puntos' => 0]], 201);
    }

    /** POST /api/auth/login  { usuario, password } */
    public function login(): void
    {
        $this->exigirJson();
        $u = (new AuthService())->login(dato_texto('usuario'), dato_texto('password'));
        responder_json(['ok' => true, 'usuario' => ['usuario' => $u['usuario'], 'puntos' => $u['puntos']],
                        'partidaEnCurso' => Partida::hayEnCurso($u['id']), 'csrf' => csrf_token()]);
    }

    /** GET /api/auth/sesion — ¿hay sesión? ¿quién soy? */
    public function sesion(): void
    {
        $yo = requiere_login_api();
        $puntos = Usuario::puntos($yo['id']);
        if ($puntos === null) {
            cerrar_sesion_usuario();
            throw new ErrorHttp(401, 'La cuenta ya no existe.', ['login' => true]);
        }
        responder_json(['ok' => true, 'usuario' => ['usuario' => $yo['usuario'], 'puntos' => $puntos],
                        'partidaEnCurso' => Partida::hayEnCurso($yo['id']), 'csrf' => csrf_token()]);
    }

    /** POST /api/auth/logout */
    public function logout(): void
    {
        requiere_login_api();
        exigir_csrf_api();
        cerrar_sesion_usuario();
        responder_json(['ok' => true, 'mensaje' => 'Cerraste sesión. ¡Hasta la próxima!']);
    }

    /**
     * Login y registro aceptan solo JSON. Un formulario de otra página no
     * puede enviar JSON sin permiso del navegador (CORS), así que esto
     * también protege contra peticiones falsificadas.
     */
    private function exigirJson(): void
    {
        if (strpos(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === false) {
            throw new ErrorHttp(415, 'Enviá los datos en formato JSON (Content-Type: application/json).');
        }
        entrada_json();
    }
}
