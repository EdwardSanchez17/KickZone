<?php
/**
 * KickZone - Conexión única a MySQL mediante PDO.
 * Todas las consultas del sistema usan consultas preparadas sobre esta conexión.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // preparadas reales en el servidor
        ]);
        $pdo->exec("SET time_zone = '-05:00'");
    } catch (PDOException $ex) {
        // Nunca mostrar detalles de conexión al usuario final.
        error_log('[KickZone] Error de conexión PDO: ' . $ex->getMessage());
        http_response_code(500);
        exit(APP_DEBUG
            ? 'Error de conexión: ' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8')
            : 'No fue posible conectar con la base de datos. Intente más tarde.');
    }

    return $pdo;
}
