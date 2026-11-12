<?php
/**
 * views/auth/login.php — Formulario de inicio de sesión.
 * Variables: $errores, $exito, $usuario
 */
$titulo = 'Iniciar sesión';
require BASE_PATH . '/views/layouts/auth_inicio.php';
?>
            <h2 class="auth-subtitulo">🔐 Iniciar sesión</h2>
            <?php require BASE_PATH . '/views/layouts/auth_mensajes.php'; ?>
            <form method="post" action="<?= e(url('login')) ?>" class="form-auth" novalidate>
                <?= campo_csrf() ?>
                <div class="config-item">
                    <label for="usuario">Nombre de usuario</label>
                    <input type="text" id="usuario" name="usuario" maxlength="20" required
                           value="<?= e($usuario) ?>" autocomplete="username" <?= $usuario === '' ? 'autofocus' : '' ?>>
                </div>
                <div class="config-item">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" maxlength="72" required
                           autocomplete="current-password" <?= $usuario !== '' ? 'autofocus' : '' ?>>
                </div>
                <div class="menu-botones">
                    <button type="submit" class="btn-menu btn-jugar">▶ ENTRAR</button>
                </div>
            </form>
            <p class="auth-link">¿Es tu primera vez? <a href="<?= e(url('registro')) ?>">Creá tu cuenta</a></p>
<?php require BASE_PATH . '/views/layouts/auth_fin.php';
