<?php
/**
 * views/layouts/auth_mensajes.php — Cartel verde de éxito y lista de errores.
 * Variables: $errores (array), $exito (string|null)
 */
if (!empty($exito)): ?>
<div class="auth-exito" role="status"><?= e($exito) ?></div>
<?php endif;
if (!empty($errores)): ?>
<div class="auth-error" role="alert"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif;
