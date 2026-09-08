<?php
// Conexión PDO a MySQL.

declare(strict_types=1);

require_once __DIR__ . '/config.php';


class Database
{
    private static ?PDO $connection = null;


    /**
     * Obtiene una conexión PDO a MySQL.
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            try {

                self::$connection = new PDO(
                    $dsn,
                    DB_USER,
                    DB_PASSWORD,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                        PDO::ATTR_DEFAULT_FETCH_MODE =>
                            PDO::FETCH_ASSOC,

                        PDO::ATTR_EMULATE_PREPARES => false,

                        PDO::ATTR_PERSISTENT => false
                    ]
                );

            } catch (PDOException $e) {

                error_log(
                    'Error de conexión a MySQL: ' . $e->getMessage()
                );

                throw new RuntimeException(
                    'No fue posible conectar con la base de datos.'
                );
            }
        }

        return self::$connection;
    }
}