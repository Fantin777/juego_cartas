<?php
/**
 * views/error.php — Página de error genérica (sin detalles internos).
 * Variable: $mensaje
 */
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title><link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>"></head><body><div class="menu-principal"><h1 class="titulo-menu">🃏 Truco Argentino</h1><p class="auth-error"><?= e($mensaje) ?></p><p class="auth-link"><a href="<?= e(url()) ?>">Volver al inicio</a></p></div></body></html>
