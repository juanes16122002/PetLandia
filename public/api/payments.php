<?php
declare(strict_types=1);

/**
 * API de pagos - PetLandia (Simulador)
 *
 * Endpoints:
 *   POST ?action=process      → Procesar pago de un pedido
 *   GET  ?action=show         → Ver pago de un pedido
 *   GET  ?action=test-cards   → Ver tarjetas de prueba
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Headers CORS + manejo de preflight (desarrollo)
cors();

$controller = new PaymentController();
$action = $_GET['action'] ?? '';

match ($action) {
    'process'    => $controller->process(),
    'show'       => $controller->show(),
    'test-cards' => $controller->testCards(),
    default      => json_error('Acción no válida. Usa: process, show, test-cards.', [], 400),
};