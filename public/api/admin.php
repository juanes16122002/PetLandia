<?php
declare(strict_types=1);

/**
 * API del panel de administración - PetLandia
 *
 * Endpoints:
 *   GET ?action=dashboard     → Resumen general (Admin)
 *   GET ?action=orders        → Lista de pedidos (Admin)
 *   GET ?action=products      → Lista de productos (Admin)
 *   GET ?action=users         → Lista de usuarios (Admin)
 *   GET ?action=low-stock     → Productos con stock bajo (Admin + Inventario)
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Headers CORS + manejo de preflight (desarrollo)
cors(['GET', 'POST', 'OPTIONS']);

$controller = new AdminController();
$action = $_GET['action'] ?? '';

match ($action) {
    'dashboard'  => $controller->dashboard(),
    'orders'     => $controller->orders(),
    'products'   => $controller->products(),
    'users'      => $controller->users(),
    'low-stock'  => $controller->lowStock(),
    default      => json_error('Acción no válida. Usa: dashboard, orders, products, users, low-stock.', [], 400),
};