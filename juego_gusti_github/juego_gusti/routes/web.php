<?php
/**
 * routes/web.php — Páginas HTML que ve el jugador en el navegador.
 *
 *   GET  /          Juego (requiere sesión; si no hay, manda al login)
 *   GET  /login     Formulario de inicio de sesión
 *   POST /login     Procesa el formulario
 *   GET  /registro  Formulario de cuenta nueva
 *   POST /registro  Procesa el formulario
 *   POST /logout    Cierra la sesión
 *
 * @var Router $router
 */
declare(strict_types=1);

$router->get('/', [JuegoController::class, 'index']);

$router->get('/login', [AuthController::class, 'formLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/registro', [AuthController::class, 'formRegistro']);
$router->post('/registro', [AuthController::class, 'registro']);
$router->post('/logout', [AuthController::class, 'logout']);
