<?php
/**
 * config/config.php
 * Lee el archivo .env y define las constantes que usa todo el proyecto.
 *
 * Para cambiar la contraseña de MySQL, el puerto, etc. NO edites este
 * archivo: editá .env en la carpeta principal del juego.
 */
declare(strict_types=1);

/**
 * Lector mínimo de archivos .env (formato CLAVE=valor).
 * Ignora líneas vacías y comentarios (#). Acepta valores entre comillas.
 */
function cargar_env(string $archivo): array
{
    if (!is_file($archivo)) {
        return [];
    }
    $valores = [];
    foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
            continue;
        }
        [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
        if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && substr($valor, -1) === $valor[0]) {
            $valor = substr($valor, 1, -1);
        }
        $valores[$clave] = $valor;
    }
    return $valores;
}

// .env tiene prioridad; .env.example sirve de respaldo con los valores por defecto.
$ENV = array_merge(cargar_env(BASE_PATH . '/.env.example'), cargar_env(BASE_PATH . '/.env'));

function env(string $clave, $porDefecto = null)
{
    global $ENV;
    return array_key_exists($clave, $ENV) ? $ENV[$clave] : $porDefecto;
}

function env_bool(string $clave, bool $porDefecto = false): bool
{
    $v = env($clave);
    return $v === null ? $porDefecto : in_array(strtolower((string) $v), ['1', 'true', 'on', 'yes', 'si'], true);
}

// --- Base de datos ---
define('DB_HOST', (string) env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_NAME', (string) env('DB_NAME', 'truco_argentino'));
define('DB_USER', (string) env('DB_USER', 'root'));
define('DB_PASS', (string) env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// --- Aplicación ---
define('APP_DEBUG', env_bool('APP_DEBUG', false));

// --- Juego ---
define('PUNTOS_POR_VICTORIA', (int) env('PUNTOS_POR_VICTORIA', 10));
define('RANKING_LIMITE', max(1, (int) env('RANKING_LIMITE', 50)));
define('OBJETIVOS_VALIDOS', [15, 30]);

// --- Login ---
define('LOGIN_MAX_INTENTOS_USUARIO', (int) env('LOGIN_MAX_INTENTOS_USUARIO', 5));
define('LOGIN_MAX_INTENTOS_IP', (int) env('LOGIN_MAX_INTENTOS_IP', 20));
define('LOGIN_VENTANA_MINUTOS', (int) env('LOGIN_VENTANA_MINUTOS', 15));
