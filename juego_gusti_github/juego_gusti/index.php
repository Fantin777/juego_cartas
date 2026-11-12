<?php
/**
 * index.php — Punto de entrada ÚNICO de la aplicación (front controller).
 *
 * Todas las páginas y todos los endpoints de la API pasan por acá:
 *   1. core/bootstrap.php carga la configuración (.env), la base de
 *      datos, la sesión y las funciones comunes.
 *   2. routes/web.php y routes/api.php registran las rutas.
 *   3. El Router busca la ruta pedida y llama al controlador.
 *
 * Es el equivalente del "server.js" de un proyecto Express.
 */
declare(strict_types=1);

// Con el servidor de desarrollo de PHP (php -S) los archivos de public/
// se sirven tal cual.
if (PHP_SAPI === 'cli-server') {
    $archivo = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (strpos(realpath($archivo) ?: '', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR) === 0 && is_file($archivo)) {
        return false;
    }
}

require_once __DIR__ . '/core/bootstrap.php';

$router = new Router();
require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';

$router->despachar(metodo_actual(), ruta_actual());
