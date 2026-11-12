<?php
/**
 * routes/api.php — Endpoints JSON (los usa script.js y se prueban con Postman).
 *
 * Regla general:
 *   GET    = consultar (no cambia nada)
 *   POST   = crear o ejecutar una acción que cambia el estado
 *   DELETE = borrar / abandonar
 *
 * Todos los POST y DELETE marcados con (*) necesitan sesión iniciada
 * y la cabecera X-CSRF-Token. La guía completa está en docs/POSTMAN.md.
 *
 * @var Router $router
 */
declare(strict_types=1);

// --- Cuenta y sesión ---
$router->post('/api/auth/registro', [AuthApiController::class, 'registro']);   // crear cuenta
$router->post('/api/auth/login', [AuthApiController::class, 'login']);         // iniciar sesión (devuelve el csrf)
$router->get('/api/auth/sesion', [AuthApiController::class, 'sesion']);        // ¿quién soy? + csrf
$router->post('/api/auth/logout', [AuthApiController::class, 'logout']);       // (*) cerrar sesión

// --- Partida contra Tata ---
$router->post('/api/partidas', [PartidaController::class, 'crear']);                           // (*) partido nuevo
$router->get('/api/partidas/actual', [PartidaController::class, 'actual']);                    // estado de la partida en curso
$router->delete('/api/partidas/actual', [PartidaController::class, 'abandonar']);              // (*) abandonar
$router->post('/api/partidas/actual/manos', [PartidaController::class, 'nuevaMano']);          // (*) repartir
$router->post('/api/partidas/actual/cartas', [PartidaController::class, 'jugarCarta']);        // (*) tirar una carta
$router->post('/api/partidas/actual/truco', [PartidaController::class, 'cantarTruco']);        // (*) cantar truco/retruco/vale 4
$router->post('/api/partidas/actual/truco/respuesta', [PartidaController::class, 'responderTruco']);   // (*)
$router->post('/api/partidas/actual/envido', [PartidaController::class, 'cantarEnvido']);      // (*) cantar envido
$router->post('/api/partidas/actual/envido/respuesta', [PartidaController::class, 'responderEnvido']); // (*)
$router->post('/api/partidas/actual/mazo', [PartidaController::class, 'irseAlMazo']);          // (*) irse al mazo

// --- Tabla de puntajes ---
$router->get('/api/ranking', [RankingController::class, 'index']);
