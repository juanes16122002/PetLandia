<?php
declare(strict_types=1);

// Configuración general de PetLandia.

/*
|--------------------------------------------------------------------------
| Cargador de variables de entorno
|--------------------------------------------------------------------------
| Si existe un archivo .env en la raíz del proyecto, se carga.
| En producción se pueden inyectar las variables directamente en el entorno.
*/

require_once __DIR__ . '/loadenv.php';

load_env(dirname(__DIR__, 2) . '/.env');

/**
 * Lee una variable de entorno con un valor por defecto.
 */
function env(string $key, string $default = ''): string
{
    $value = getenv($key);

    return $value === false ? $default : (string) $value;
}


/*
|--------------------------------------------------------------------------
| Configuración general de PetLandia
|--------------------------------------------------------------------------
*/

define('APP_NAME', env('APP_NAME', 'PetLandia'));

define('APP_ENV', env('APP_ENV', 'development'));

define('APP_URL', env('APP_URL', 'http://petlandia.local'));


/*
|--------------------------------------------------------------------------
| Base de datos
|--------------------------------------------------------------------------
*/

define('DB_HOST', env('DB_HOST', 'localhost'));

define('DB_PORT', env('DB_PORT', '3306'));

define('DB_NAME', env('DB_NAME', 'petlandia'));

define('DB_USER', env('DB_USER', 'root'));

// Sin valor por defecto sensible: solo se toma del entorno.
define('DB_PASSWORD', env('DB_PASSWORD'));


/*
|--------------------------------------------------------------------------
| Configuración de sesiones
|--------------------------------------------------------------------------
*/

define('SESSION_NAME', env('SESSION_NAME', 'PETLANDIA_SESSION'));


/*
|--------------------------------------------------------------------------
| Rutas
|--------------------------------------------------------------------------
*/

define(
    'BASE_PATH',
    dirname(__DIR__, 2)
);

define(
    'PUBLIC_PATH',
    BASE_PATH . '/public'
);

define(
    'UPLOAD_PATH',
    PUBLIC_PATH . '/uploads/products'
);


/*
|--------------------------------------------------------------------------
| Configuración de archivos
|--------------------------------------------------------------------------
*/

define(
    'MAX_UPLOAD_SIZE',
    5 * 1024 * 1024
);


/*
|--------------------------------------------------------------------------
| Zona horaria
|--------------------------------------------------------------------------
*/

date_default_timezone_set('America/Santiago');
