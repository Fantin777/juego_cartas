<?php
/**
 * controllers/RankingController.php
 * GET /api/ranking — Tabla de puntajes (requiere sesión).
 */
declare(strict_types=1);

final class RankingController
{
    public function index(): void
    {
        $yo = requiere_login_api();
        responder_json((new RankingService())->tabla($yo));
    }
}
