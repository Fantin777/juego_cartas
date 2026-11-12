<?php
/**
 * core/bootstrap.php
 * Arranque de la aplicación. Lo incluye index.php (el único archivo PHP
 * que se ejecuta desde el navegador) antes de atender cada petición.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/core/http.php';
require_once BASE_PATH . '/core/sesion.php';
require_once BASE_PATH . '/core/Router.php';

// ---------------------------------------------------------------------
// Carga automática de clases: "new PartidaService" busca el archivo
// PartidaService.php en estas carpetas. Así no hace falta escribir un
// require por cada clase.
// ---------------------------------------------------------------------
spl_autoload_register(function (string $clase): void {
    foreach (['controllers', 'models', 'services', 'motor'] as $carpeta) {
        $archivo = BASE_PATH . "/$carpeta/$clase.php";
        if (is_file($archivo)) {
            require_once $archivo;
            return;
        }
    }
});

// ---------------------------------------------------------------------
// Errores: en producción nunca se muestran al jugador; van al log.
// ---------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// ---------------------------------------------------------------------
// Cabeceras de seguridad básicas
// ---------------------------------------------------------------------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}

iniciar_sesion();
