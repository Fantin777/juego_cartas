<?php
/**
 * core/Router.php
 * Router mínimo: relaciona "MÉTODO + ruta" con el método de un controlador.
 *
 *   $router->get('/api/ranking', [RankingController::class, 'index']);
 *
 * Si la ruta existe pero con otro método responde 405 (Método no
 * permitido); si no existe, 404. Los errores de la API salen en JSON y
 * los de las páginas en HTML.
 */
declare(strict_types=1);

final class Router
{
    /** @var array<string, array<string, array{0:string,1:string}>>  [ruta][MÉTODO] => [Clase, método] */
    private $rutas = [];

    public function get(string $ruta, array $accion): void    { $this->agregar('GET', $ruta, $accion); }
    public function post(string $ruta, array $accion): void   { $this->agregar('POST', $ruta, $accion); }
    public function delete(string $ruta, array $accion): void { $this->agregar('DELETE', $ruta, $accion); }

    private function agregar(string $metodo, string $ruta, array $accion): void
    {
        $this->rutas['/' . trim($ruta, '/')][$metodo] = $accion;
    }

    /** Busca la ruta pedida y ejecuta su controlador. */
    public function despachar(string $metodo, string $ruta): void
    {
        try {
            $metodos = $this->rutas[$ruta] ?? null;
            if ($metodos === null) {
                throw new ErrorHttp(404, 'La ruta ' . $ruta . ' no existe.');
            }
            if (!isset($metodos[$metodo])) {
                header('Allow: ' . implode(', ', array_keys($metodos)));
                throw new ErrorHttp(405, 'Método ' . $metodo . ' no permitido en ' . $ruta . '. Usá: ' . implode(', ', array_keys($metodos)) . '.');
            }
            [$clase, $funcion] = $metodos[$metodo];
            (new $clase())->$funcion();
        } catch (ErrorHttp $ex) {
            $this->responderError($ex->status, $ex->getMessage(), $ex->extra);
        } catch (Throwable $ex) {
            error_log('[truco] ' . $metodo . ' ' . $ruta . ': ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
            $detalle = APP_DEBUG ? ' (' . $ex->getMessage() . ')' : '';
            $esBD = $ex instanceof PDOException;
            $mensaje = $esBD
                ? 'No se pudo conectar con la base de datos. Revisá que WampServer esté en verde y los datos de .env.'
                : 'Error interno del servidor. Probá de nuevo.';
            $this->responderError(500, $mensaje . $detalle);
        }
    }

    private function responderError(int $status, string $mensaje, array $extra = []): void
    {
        if (es_peticion_api()) {
            responder_error_json($status, $mensaje, $extra);
        }
        if ($status === 401) {
            redirigir(url('login'));
        }
        error_fatal_html($status === 404 ? 'La página que buscás no existe.' : $mensaje, $status);
    }
}
