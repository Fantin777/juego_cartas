<?php
/**
 * core/http.php
 * Funciones para leer la petición y armar la respuesta:
 * URLs, JSON, redirecciones, vistas y escape de HTML.
 */
declare(strict_types=1);

/**
 * Error que se convierte directamente en una respuesta HTTP.
 * Ejemplo: throw new ErrorHttp(404, 'No hay partida en curso.');
 */
class ErrorHttp extends RuntimeException
{
    public $status;
    public $extra;

    public function __construct(int $status, string $mensaje, array $extra = [])
    {
        parent::__construct($mensaje);
        $this->status = $status;
        $this->extra  = $extra;
    }
}

// ---------------------------------------------------------------------
// URLs
// ---------------------------------------------------------------------

/** Carpeta del juego dentro del servidor. Ej.: "/juego_gusti" (o "" si está en la raíz). */
function ruta_base(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($dir, '/');
}

/**
 * ¿Están activas las URLs "lindas" (/juego_gusti/api/ranking)?
 * El .htaccess avisa con la variable APP_REWRITE cuando mod_rewrite está
 * activo. Si no lo está, las URLs llevan index.php en el medio
 * (/juego_gusti/index.php/api/ranking) y todo sigue funcionando igual.
 */
function urls_lindas(): bool
{
    return PHP_SAPI === 'cli-server'
        || !empty($_SERVER['APP_REWRITE'])
        || !empty($_SERVER['REDIRECT_APP_REWRITE']);
}

/** URL de una página o endpoint. url('login') => /juego_gusti/login */
function url(string $ruta = ''): string
{
    $ruta = ltrim($ruta, '/');
    if ($ruta === '') {
        return ruta_base() . '/';
    }
    return ruta_base() . (urls_lindas() ? '/' : '/index.php/') . $ruta;
}

/** URL de un archivo estático de public/. asset('css/styles.css') */
function asset(string $archivo): string
{
    return ruta_base() . '/public/' . ltrim($archivo, '/');
}

/** Ruta pedida, sin la carpeta del juego ni "index.php". Ej.: "/api/ranking" */
function ruta_actual(): string
{
    $ruta = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $base = ruta_base();
    if ($base !== '' && strncasecmp($ruta, $base, strlen($base)) === 0) {
        $ruta = substr($ruta, strlen($base));
    }
    if (strncasecmp($ruta, '/index.php', 10) === 0) {
        $ruta = substr($ruta, 10);
    }
    $ruta = '/' . trim($ruta, '/');
    return $ruta;
}

function metodo_actual(): string
{
    $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    return $m === 'HEAD' ? 'GET' : $m;
}

function es_peticion_api(): bool
{
    return strncmp(ruta_actual(), '/api', 4) === 0;
}

// ---------------------------------------------------------------------
// Entrada
// ---------------------------------------------------------------------

/**
 * Cuerpo JSON de la petición como array.
 * Si el cuerpo viene vacío devuelve []; si no es JSON válido, error 400.
 */
function entrada_json(): array
{
    static $datos = null;
    if ($datos !== null) {
        return $datos;
    }
    $crudo = (string) file_get_contents('php://input');
    if (trim($crudo) === '') {
        return $datos = [];
    }
    $tipo = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if (strpos($tipo, 'application/json') === false) {
        throw new ErrorHttp(415, 'Enviá los datos en formato JSON (Content-Type: application/json).');
    }
    $json = json_decode($crudo, true);
    if (!is_array($json)) {
        throw new ErrorHttp(400, 'El cuerpo de la petición no es un JSON válido.');
    }
    return $datos = $json;
}

/** Un dato de texto del JSON recibido ('' si no vino o no es texto). */
function dato_texto(string $clave): string
{
    $v = entrada_json()[$clave] ?? '';
    return is_string($v) ? $v : '';
}

function ip_cliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

// ---------------------------------------------------------------------
// Salida
// ---------------------------------------------------------------------

/** Escapa texto para insertarlo en HTML (previene XSS). */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function responder_json(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Respuesta de error de la API con formato fijo: { ok:false, error:"..." } */
function responder_error_json(int $codigo, string $mensaje, array $extra = []): void
{
    responder_json(array_merge(['ok' => false, 'error' => $mensaje], $extra), $codigo);
}

function redirigir(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Muestra una vista de la carpeta views/ con las variables de $datos. */
function vista(string $nombre, array $datos = []): void
{
    extract($datos, EXTR_SKIP);
    require BASE_PATH . '/views/' . $nombre . '.php';
}

/** Página de error genérica (sin detalles internos). */
function error_fatal_html(string $mensaje = 'Ocurrió un error interno. Probá de nuevo en unos minutos.', int $codigo = 500): void
{
    http_response_code($codigo);
    vista('error', ['mensaje' => $mensaje]);
    exit;
}
