<?php
/**
 * controllers/PartidaController.php
 * Endpoints de la partida contra Tata (/api/partidas/...).
 *
 * Cada método hace solo tres cosas: verificar la sesión (y el CSRF),
 * leer los datos de la petición y pedirle el trabajo a PartidaService.
 */
declare(strict_types=1);

final class PartidaController
{
    /** @var PartidaService */
    private $servicio;

    public function __construct()
    {
        $this->servicio = new PartidaService();
    }

    /** POST /api/partidas  { objetivo, nombreJugador, nombreOponente } */
    public function crear(): void
    {
        $yo = $this->usuarioConCsrf();
        $datos = entrada_json();
        $respuesta = $this->servicio->nueva(
            $yo['id'],
            (int) ($datos['objetivo'] ?? 15),
            dato_texto('nombreJugador'),
            dato_texto('nombreOponente')
        );
        responder_json($respuesta, 201);
    }

    /** GET /api/partidas/actual */
    public function actual(): void
    {
        $yo = requiere_login_api();
        responder_json($this->servicio->actual($yo['id']));
    }

    /** DELETE /api/partidas/actual */
    public function abandonar(): void
    {
        $yo = $this->usuarioConCsrf();
        responder_json($this->servicio->abandonar($yo['id']));
    }

    /** POST /api/partidas/actual/manos */
    public function nuevaMano(): void
    {
        $this->accion(function (Arbitro $a) { $a->iniciarMano(); });
    }

    /** POST /api/partidas/actual/cartas  { carta: "7espada" } */
    public function jugarCarta(): void
    {
        $carta = $this->requerido('carta');
        $this->accion(function (Arbitro $a) use ($carta) { $a->jugarCarta($carta); });
    }

    /** POST /api/partidas/actual/truco */
    public function cantarTruco(): void
    {
        $this->accion(function (Arbitro $a) { $a->cantarTruco(); });
    }

    /** POST /api/partidas/actual/truco/respuesta  { respuesta: aceptar|rechazar|subir } */
    public function responderTruco(): void
    {
        $r = $this->requerido('respuesta', ['aceptar', 'rechazar', 'subir']);
        $this->accion(function (Arbitro $a) use ($r) { $a->responderTruco($r); });
    }

    /** POST /api/partidas/actual/envido  { tipo: envido|realenvido|faltaenvido } */
    public function cantarEnvido(): void
    {
        $tipo = $this->requerido('tipo', ['envido', 'realenvido', 'faltaenvido']);
        $this->accion(function (Arbitro $a) use ($tipo) { $a->cantarEnvido($tipo); });
    }

    /** POST /api/partidas/actual/envido/respuesta  { respuesta: quiero|noquiero|envido|realenvido|faltaenvido } */
    public function responderEnvido(): void
    {
        $r = $this->requerido('respuesta', ['quiero', 'noquiero', 'envido', 'realenvido', 'faltaenvido']);
        $this->accion(function (Arbitro $a) use ($r) { $a->responderEnvido($r); });
    }

    /** POST /api/partidas/actual/mazo */
    public function irseAlMazo(): void
    {
        $this->accion(function (Arbitro $a) { $a->irseAlMazo(); });
    }

    // -----------------------------------------------------------------

    private function usuarioConCsrf(): array
    {
        $yo = requiere_login_api();
        exigir_csrf_api();
        return $yo;
    }

    private function accion(callable $jugada): void
    {
        $yo = $this->usuarioConCsrf();
        responder_json($this->servicio->jugar($yo['id'], $jugada));
    }

    /** Lee un dato obligatorio del JSON; si se indican $permitidos, lo valida. */
    private function requerido(string $clave, array $permitidos = []): string
    {
        requiere_login_api();   // primero la sesión, para dar el error correcto
        $v = dato_texto($clave);
        if ($v === '') {
            throw new ErrorHttp(422, "Falta el dato \"$clave\".");
        }
        if ($permitidos && !in_array($v, $permitidos, true)) {
            throw new ErrorHttp(422, "Valor inválido para \"$clave\". Usá: " . implode(', ', $permitidos) . '.');
        }
        return $v;
    }
}
