<?php
declare(strict_types=1);

/**
 * Bootstrap de PetLandia
 * Carga la configuración, helpers y prepara el entorno.
 */

// ============================================================
// 1. Configuración principal
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// ============================================================
// 2. Helpers
// ============================================================
require_once __DIR__ . '/helpers/security.php';
require_once __DIR__ . '/helpers/validation.php';
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/request.php';

// ============================================================
// 3. Iniciar sesión de forma segura
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);

    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode'  => true,
    ]);
}

// ============================================================
// 4. Autoload simple de Models, Services y Controllers
// ============================================================
spl_autoload_register(function (string $class) {
    $paths = [
        __DIR__ . '/models/' . $class . '.php',
        __DIR__ . '/services/' . $class . '.php',
        __DIR__ . '/controllers/' . $class . '.php',
        __DIR__ . '/middleware/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// ============================================================
// 5. Funciones de utilidad globales (opcionales)
// ============================================================

/**
 * Obtiene la instancia de AuthService (singleton simple).
 */
function auth(): AuthService
{
    static $auth = null;

    if ($auth === null) {
        $auth = new AuthService();
    }

    return $auth;
}

/**
 * Obtiene la conexión a la base de datos.
 */
function db(): PDO
{
    return Database::getConnection();
}