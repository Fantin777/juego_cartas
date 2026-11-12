<?php
/**
 * controllers/AuthController.php
 * Páginas de login, registro y cierre de sesión (formularios HTML).
 * La lógica está en AuthService; acá solo se muestran las vistas y se
 * redirige (patrón PRG: Post → Redirect → Get).
 */
declare(strict_types=1);

final class AuthController
{
    /** GET /login */
    public function formLogin(): void
    {
        $this->siYaEntroIrAlJuego();
        vista('auth/login', [
            'errores' => [],
            'exito'   => flash('exito'),
            'usuario' => (string) (flash('usuario') ?? ''),
        ]);
    }

    /** POST /login */
    public function login(): void
    {
        $this->siYaEntroIrAlJuego();
        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $errores = $this->validarCsrf();

        if (!$errores) {
            try {
                (new AuthService())->login($usuario, (string) ($_POST['password'] ?? ''));
                redirigir(url());
            } catch (ErrorHttp $ex) {
                $errores = [$ex->getMessage()];
            } catch (PDOException $ex) {
                error_log('[truco] Error en login: ' . $ex->getMessage());
                $errores = ['No se pudo iniciar sesión por un error del servidor. Revisá que la base de datos esté funcionando.'];
            }
        }
        vista('auth/login', ['errores' => $errores, 'exito' => null, 'usuario' => $usuario]);
    }

    /** GET /registro */
    public function formRegistro(): void
    {
        $this->siYaEntroIrAlJuego();
        vista('auth/registro', ['errores' => [], 'usuario' => '']);
    }

    /** POST /registro */
    public function registro(): void
    {
        $this->siYaEntroIrAlJuego();
        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $errores = $this->validarCsrf();

        if (!$errores) {
            try {
                (new AuthService())->registrar($usuario, (string) ($_POST['password'] ?? ''), (string) ($_POST['confirmar'] ?? ''));
                flash('exito', '¡Cuenta creada! Ya podés iniciar sesión.');
                flash('usuario', $usuario);
                redirigir(url('login'));
            } catch (ErrorHttp $ex) {
                $errores = $ex->extra['errores'] ?? [$ex->getMessage()];
            } catch (PDOException $ex) {
                error_log('[truco] Error en registro: ' . $ex->getMessage());
                $errores = ['No se pudo crear la cuenta por un error del servidor. Revisá que la base de datos esté funcionando.'];
            }
        }
        vista('auth/registro', ['errores' => $errores, 'usuario' => $usuario]);
    }

    /**
     * POST /logout — solo con token CSRF, así un enlace o una imagen de
     * otra página no puede cerrarte la sesión. La partida en curso queda
     * guardada y se puede continuar la próxima vez.
     */
    public function logout(): void
    {
        if (!csrf_valido($_POST['csrf'] ?? null)) {
            redirigir(url());
        }
        cerrar_sesion_usuario();
        session_start();   // sesión nueva y limpia solo para el mensaje
        flash('exito', 'Cerraste sesión. ¡Hasta la próxima!');
        redirigir(url('login'));
    }

    private function siYaEntroIrAlJuego(): void
    {
        if (usuario_actual() !== null) {
            redirigir(url());
        }
    }

    private function validarCsrf(): array
    {
        return csrf_valido($_POST['csrf'] ?? null) ? [] : ['El formulario expiró. Recargá la página e intentá de nuevo.'];
    }
}
