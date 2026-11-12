<?php
/**
 * views/auth/registro.php — Formulario para crear una cuenta.
 * Variables: $errores, $usuario
 */
$titulo = 'Crear cuenta';
$exito  = null;
require BASE_PATH . '/views/layouts/auth_inicio.php';
?>
            <h2 class="auth-subtitulo">📝 Crear cuenta</h2>
            <?php require BASE_PATH . '/views/layouts/auth_mensajes.php'; ?>
            <form method="post" action="<?= e(url('registro')) ?>" class="form-auth" autocomplete="off" novalidate>
                <?= campo_csrf() ?>
                <div class="config-item">
                    <label for="usuario">Nombre de usuario</label>
                    <input type="text" id="usuario" name="usuario" maxlength="20" required
                           pattern="[A-Za-z0-9_]{3,20}" value="<?= e($usuario) ?>" autofocus>
                    <small class="auth-ayuda">3 a 20 caracteres: letras, números o _</small>
                </div>
                <div class="config-item">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" minlength="6" maxlength="72" required autocomplete="new-password">
                </div>
                <div class="config-item">
                    <label for="confirmar">Confirmar contraseña</label>
                    <input type="password" id="confirmar" name="confirmar" minlength="6" maxlength="72" required autocomplete="new-password">
                </div>
                <div class="menu-botones">
                    <button type="submit" class="btn-menu btn-jugar">✅ CREAR CUENTA</button>
                </div>
            </form>
            <p class="auth-link">¿Ya tenés cuenta? <a href="<?= e(url('login')) ?>">Iniciá sesión</a></p>
<?php require BASE_PATH . '/views/layouts/auth_fin.php';
