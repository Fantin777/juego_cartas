<?php
/**
 * services/RankingService.php
 * Arma la tabla de puntajes y las estadísticas del jugador conectado.
 * Nunca devuelve contraseñas ni ids internos.
 */
declare(strict_types=1);

final class RankingService
{
    public function tabla(array $yo): array
    {
        $ranking  = [];
        $posicion = 0;
        $anterior = null;
        foreach (Usuario::ranking(RANKING_LIMITE) as $i => $fila) {
            $puntos = (int) $fila['puntos'];
            if ($puntos !== $anterior) {      // ranking de competencia: 1, 2, 2, 4
                $posicion = $i + 1;
                $anterior = $puntos;
            }
            $ranking[] = [
                'posicion'  => $posicion,
                'usuario'   => $fila['usuario'],
                'puntos'    => $puntos,
                'victorias' => (int) $fila['victorias'],
                'esVos'     => (int) $fila['id'] === $yo['id'],
            ];
        }

        $misPuntos = Usuario::puntos($yo['id']) ?? 0;
        $conteo    = Partida::conteoResultados($yo['id']);

        return [
            'ok'             => true,
            'ranking'        => $ranking,
            'totalJugadores' => Usuario::contar(),
            'yo'             => [
                'usuario'     => $yo['usuario'],
                'puntos'      => $misPuntos,
                'posicion'    => Usuario::posicion($misPuntos),
                'victorias'   => $conteo['victoria'],
                'derrotas'    => $conteo['derrota'],
                'abandonadas' => $conteo['abandonada'],
            ],
        ];
    }
}
