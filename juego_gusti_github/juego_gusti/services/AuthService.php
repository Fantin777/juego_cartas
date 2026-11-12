<?php
/**
 * services/AuthService.php
 * Reglas de registro e inicio de sesión. Las usan tanto los formularios
 * HTML (AuthController) como la API JSON (AuthApiController), así la
 * lógica está escrita una sola vez.
 *
 * Si algo está mal lanza ErrorHttp con el código y el mensaje adecuados:
 *   422 datos inválidos · 409 usuario repetido · 401 credenciales
 *   incorrectas · 429 demasiados intentos.
 */
declare(strict_types=1);

final class AuthService
{
    /** Hash ficticio: se verifica contra él si el usuario no existe, para no delatar por tiempo qué nombres están registrados. */
    private const HASH_FICTICIO = '$2y$10$5Q2ytYFJN89.Og2brat5Ze5J.HNAOdbaN4Mhc0CmGKDqp/RL38FSm';

    /** Crea una cuenta. Devuelve ['id' => ..., 'usuario' => ...]. */
    public function registrar(string $usuario, string $password, string $confirmar): array
    {
        $usuario = trim($usuario);
        $errores = [];

        if ($usuario === '' || $password === '' || $confirmar === '') {
            $errores[] = 'Completá todos los campos.';
        }
        if ($usuario !== '' && !preg_match('/^[A-Za-z0-9_]{3,20}$/', $usuario)) {
            $errores[] = 'El usuario debe tener entre 3 y 20 caracteres: letras, números o guion bajo (_).';
        }
        if ($password !== '' && strlen($password) < 6) {
            $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
        }
        if (strlen($password) > 72) {
            $errores[] = 'La contraseña no puede superar los 72 caracteres.';   // bcrypt usa solo 72 bytes
        }
        if ($password !== '' && $confirmar !== '' && !hash_equals($password, $confirmar)) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
        if ($errores) {
            throw new ErrorHttp(422, $errores[0], ['errores' => $errores]);
        }

        $repetido = 'Ese nombre de usuario ya está registrado. Elegí otro.';
        if (Usuario::existe($usuario)) {
            throw new ErrorHttp(409, $repetido, ['errores' => [$repetido]]);
        }
        try {
            $id = Usuario::crear($usuario, password_hash($password, PASSWORD_DEFAULT));
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {   // otro registro igual entró al mismo tiempo
                throw new ErrorHttp(409, $repetido, ['errores' => [$repetido]]);
            }
            throw $ex;
        }
        return ['id' => $id, 'usuario' => $usuario];
    }

    /** Verifica usuario y contraseña e inicia la sesión. Devuelve el usuario. */
    public function login(string $usuario, string $password): array
    {
        $usuario = trim($usuario);
        if ($usuario === '' || $password === '') {
            throw new ErrorHttp(422, 'Completá usuario y contraseña.');
        }
        if (strlen($usuario) > 20 || strlen($password) > 72) {
            throw new ErrorHttp(401, 'Usuario o contraseña incorrectos.');
        }

        $ip = ip_cliente();
        $intentos = IntentoLogin::recientes($usuario, $ip, LOGIN_VENTANA_MINUTOS);
        if ($intentos['por_usuario'] >= LOGIN_MAX_INTENTOS_USUARIO || $intentos['por_ip'] >= LOGIN_MAX_INTENTOS_IP) {
            throw new ErrorHttp(429, 'Demasiados intentos fallidos. Esperá ' . LOGIN_VENTANA_MINUTOS . ' minutos e intentá de nuevo.');
        }

        $fila = Usuario::buscarPorNombre($usuario);
        $ok = password_verify($password, $fila ? $fila['password'] : self::HASH_FICTICIO) && $fila;
        if (!$ok) {
            IntentoLogin::registrar($usuario, $ip);
            throw new ErrorHttp(401, 'Usuario o contraseña incorrectos.');
        }

        if (password_needs_rehash($fila['password'], PASSWORD_DEFAULT)) {
            Usuario::actualizarPassword((int) $fila['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        IntentoLogin::limpiar($fila['usuario'], LOGIN_VENTANA_MINUTOS);
        Usuario::registrarLogin((int) $fila['id']);

        iniciar_sesion_usuario((int) $fila['id'], $fila['usuario']);
        return ['id' => (int) $fila['id'], 'usuario' => $fila['usuario'], 'puntos' => (int) $fila['puntos']];
    }
}
