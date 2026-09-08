<?php
declare(strict_types=1);

/**
 * API del carrito - PetLandia
 *
 * Todos los endpoints requieren autenticación.
 *
 * Endpoints:
 *   GET  ?action=get
 *   GET  ?action=count
 *   GET  ?action=validate
 *   POST ?action=add
 *   POST ?action=update
 *   POST ?action=remove
 *   POST ?action=clear
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Headers CORS + manejo de preflight (desarrollo)
cors();

$controller = new CartController();
$action = $_GET['action'] ?? '';

match ($action) {
    'get'      => $controller->get(),
    'add'      => $controller->add(),
    'update'   => $controller->update(),
    'remove'   => $controller->remove(),
    'clear'    => $controller->clear(),
    'count'    => $controller->count(),
    'validate' => $controller->validate(),
    default    => json_error('Acción no válida. Usa: get, add, update, remove, clear, count, validate.', [], 400),
};