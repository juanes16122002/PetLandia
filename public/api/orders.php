<?php
declare(strict_types=1);

/**
 * API de pedidos - PetLandia
 *
 * Endpoints cliente:
 *   POST ?action=create
 *   GET  ?action=my-orders
 *   GET  ?action=show&id=1
 *
 * Endpoints admin:
 *   GET  ?action=list
 *   POST ?action=update-status
 *   GET  ?action=stats
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Headers CORS + manejo de preflight (desarrollo)
cors();

$controller = new OrderController();
$action = $_GET['action'] ?? '';

match ($action) {
    'create'        => $controller->create(),
    'my-orders'     => $controller->myOrders(),
    'show'          => $controller->show(),
    'list'          => $controller->list(),
    'update-status' => $controller->updateStatus(),
    'stats'         => $controller->stats(),
    default         => json_error('Acción no válida.', [], 400),
};