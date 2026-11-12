<?php
/**
 * motor/AccionInvalida.php
 * Excepción: acción no permitida por las reglas o por el estado del juego. El controlador la convierte en una respuesta HTTP 409.
 */
declare(strict_types=1);

/** Acción no permitida por las reglas o por el estado actual del juego. */
class AccionInvalida extends RuntimeException
{
}
