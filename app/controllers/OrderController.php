<?php
declare(strict_types=1);

class OrderController
{
    private OrderService $service;

    public function __construct()
    {
        $this->service = new OrderService();
    }

    /**
     * POST /api/orders.php?action=create
     * Crea un pedido a partir del carrito del usuario.
     */
    public function create(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $userId = auth()->id();
        $result = $this->service->create($userId, json_input());

        if (!$result['success']) {
            json_error($result['message'], [
                'errors' => $result['errors'] ?? []
            ], 422);
        }

        json_success($result['message'], [
            'order' => $result['order']
        ], 201);
    }

    /**
     * GET /api/orders.php?action=my-orders
     * Historial de pedidos del cliente autenticado.
     */
    public function myOrders(): void
    {
        AuthMiddleware::handle();

        $orders = $this->service->myOrders(auth()->id(), $_GET);

        json_success('Pedidos obtenidos correctamente.', [
            'orders' => $orders
        ]);
    }

    /**
     * GET /api/orders.php?action=show&id=1
     * Muestra un pedido específico.
     * - Cliente: solo puede ver sus propios pedidos
     * - Admin: puede ver cualquier pedido
     */
    public function show(): void
    {
        AuthMiddleware::handle();

        $orderId = (int) ($_GET['id'] ?? 0);

        $result = $this->service->show($orderId, auth()->id(), auth()->isAdmin());

        if (!$result['success']) {
            json_error($result['message'], [], $result['status_code'] ?? 403);
        }

        json_success($result['message'], [
            'order' => $result['order']
        ]);
    }

    /**
     * GET /api/orders.php?action=list
     * Lista todos los pedidos (solo Admin).
     */
    public function list(): void
    {
        AdminMiddleware::handle();

        $orders = $this->service->list($_GET);

        json_success('Pedidos obtenidos correctamente.', [
            'orders' => $orders
        ]);
    }

    /**
     * POST /api/orders.php?action=update-status
     * Actualiza el estado de un pedido (solo Admin).
     */
    public function updateStatus(): void
    {
        AdminMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $data = json_input();

        $orderId = (int) ($data['id'] ?? $data['order_id'] ?? 0);
        $status  = $data['estado'] ?? $data['status'] ?? '';

        $result = $this->service->updateStatus($orderId, $status);

        if (!$result['success']) {
            json_error($result['message'], [], $result['status_code'] ?? 500);
        }

        json_success($result['message'], [
            'order' => $result['order']
        ]);
    }

    /**
     * GET /api/orders.php?action=stats
     * Contadores de pedidos por estado (solo Admin).
     */
    public function stats(): void
    {
        AdminMiddleware::handle();

        json_success('Estadísticas obtenidas correctamente.', [
            'stats' => $this->service->stats()
        ]);
    }
}
