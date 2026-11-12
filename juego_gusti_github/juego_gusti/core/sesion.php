<?php
/**
 * core/sesion.php
 * Sesión del jugador, token CSRF, mensajes "flash" y control de acceso.
 */
declare(strict_types=1);

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('TRUCOSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------------------------------------------------------------
// CSRF: un código secreto por sesión que debe acompañar cada acción.
// Una página ajena no lo conoce, así que no puede actuar en tu nombre.
// ---------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Para la API: el token viaja en la cabecera X-CSRF-Token. */
function exigir_csrf_api(): void
{
    if (!csrf_valido($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        throw new ErrorHttp(403, 'Token de seguridad (X-CSRF-Token) inválido o ausente. Recargá la página.');
    }
}

// ---------------------------------------------------------------------
// Mensajes de una sola lectura entre redirecciones (patrón PRG)
// ---------------------------------------------------------------------
function flash(string $clave, ?string $valor = null): ?string
{
    if ($valor !== null) {
        $_SESSION['flash'][$clave] = $valor;
        return null;
    }
    $v = $_SESSION['flash'][$clave] ?? null;
    unset($_SESSION['flash'][$clave]);
    return $v;
}

// ---------------------------------------------------------------------
// Autenticación
// ---------------------------------------------------------------------
function usuario_actual(): ?array
{
    if (empty($_SESSION['usuario_id']) || empty($_SESSION['usuario'])) {
        return null;
    }
    return ['id' => (int) $_SESSION['usuario_id'], 'usuario' => (string) $_SESSION['usuario']];
}

/** Para páginas HTML: si no hay sesión, manda al login. */
function requiere_login(): array
{
    $u = usuario_actual();
    if ($u === null) {
        redirigir(url('login'));
    }
    return $u;
}

/** Para endpoints JSON: si no hay sesión, responde 401. */
function requiere_login_api(): array
{
    $u = usuario_actual();
    if ($u === null) {
        throw new ErrorHttp(401, 'Tu sesión expiró. Volvé a iniciar sesión.', ['login' => true]);
    }
    return $u;
}

function iniciar_sesion_usuario(int $id, string $usuario): void
{
    session_regenerate_id(true);            // evita fijación de sesión
    $_SESSION['usuario_id'] = $id;
    $_SESSION['usuario']    = $usuario;
    unset($_SESSION['csrf']);               // token nuevo para la sesión nueva
}

function cerrar_sesion_usuario(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
