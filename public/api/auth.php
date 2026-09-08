<?php
declare(strict_types=1);

/**
 * API de autenticación - PetLandia
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Headers CORS + manejo de preflight (desarrollo)
cors();

$controller = new AuthController();
$action = $_GET['action'] ?? '';

match ($action) {
    'register' => $controller->register(),
    'login'    => $controller->login(),
    'logout'   => $controller->logout(),
    'me'       => $controller->me(),
    'check'    => $controller->check(),
    'csrf'     => $controller->csrf(),
    default    => json_error('Acción no válida. Usa: register, login, logout, me, check, csrf.', [], 400),
};