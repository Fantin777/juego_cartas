<?php
/**
 * views/layouts/auth_inicio.php — Encabezado compartido por login y
 * registro (mismo estilo que el menú principal del juego).
 * Variable: $titulo
 */
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> · 🃏 Truco Argentino</title>
    <link rel="stylesheet" href="<?= e(asset('css/styles.css')) ?>">
</head>
<body>
    <div class="game-container">
        <div class="menu-principal auth-caja">
            <h1 class="titulo-menu">🃏 Truco Argentino 🃏</h1>
