<?php
/**
 * config/db.php
 * Conexión a la base de datos MySQL/MariaDB (PDO).
 *
 * db() devuelve SIEMPRE la misma conexión durante una petición: se abre
 * la primera vez que se usa y se reutiliza después.
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // los errores lanzan excepciones
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // filas como arrays asociativos
        PDO::ATTR_EMULATE_PREPARES   => false,                   // consultas preparadas reales
    ]);
    return $pdo;
}

/**
 * Ejecuta $trabajo dentro de una transacción.
 * Si $trabajo lanza una excepción se deshace todo (rollBack) y la
 * excepción sigue su camino; si termina bien se confirma (commit).
 */
function en_transaccion(callable $trabajo)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $resultado = $trabajo($pdo);
        $pdo->commit();
        return $resultado;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}
