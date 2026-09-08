<?php
declare(strict_types=1);

/**
 * API de productos - PetLandia
 *
 * Endpoints públicos:
 *   GET  ?action=list
 *   GET  ?action=show&id=1
 *   GET  ?action=categories
 *
 * Endpoints protegidos:
 *   POST ?action=create          (Admin)
 *   POST ?action=update          (Admin)
 *   POST ?action=update-stock    (Admin + Inventario)
 *   POST ?action=deactivate      (Admin)
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Headers CORS + manejo de preflight (desarrollo)
cors();

$controller = new ProductController();
$action = $_GET['action'] ?? '';

match ($action) {
    'list'         => $controller->list(),
    'show'         => $controller->show(),
    'admin-show'   => $controller->adminShow(),
    'categories'   => $controller->categories(),
    'create'       => $controller->create(),
    'update'       => $controller->update(),
    'update-stock' => $controller->updateStock(),
    'deactivate'   => $controller->deactivate(),
    default        => json_error('Acción no válida.', [], 400),
};