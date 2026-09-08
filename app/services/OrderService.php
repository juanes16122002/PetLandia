<?php
declare(strict_types=1);

/**
 * Servicio de pedidos.
 * Orquesta la creación, consulta y gestión de pedidos, y concentra
 * las reglas de permisos de acceso (dueño o administrador).
 */

class OrderService
{
    private Order $orderModel;
    private Cart $cartModel;

    public function __construct()
    {
        $this->orderModel = new Order();
        $this->cartModel  = new Cart();
    }

    /**
     * Crea un pedido a partir del carrito del usuario.
     * Retorna [success, message, errors?, order?].
     */
    public function create(int $userId, array $data): array
    {
        $validation = $this->cartModel->validateStock($userId);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'No se puede crear el pedido.',
                'errors'  => $validation['errors'],
            ];
        }

        $extraData = [
            'direccion_envio'   => sanitize_string($data['direccion_envio'] ?? ''),
            'telefono_contacto' => sanitize_string($data['telefono_contacto'] ?? ''),
            'observaciones'     => sanitize_string($data['observaciones'] ?? ''),
        ];

        $orderId = $this->orderModel->createFromCart($userId, $extraData);

        if (!$orderId) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el pedido. Intenta nuevamente.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Pedido creado correctamente. Procede al pago.',
            'order'   => $this->orderModel->findById($orderId),
        ];
    }

    /**
     * Historial de pedidos de un cliente.
     */
    public function myOrders(int $userId, array $input): array
    {
        $page   = max(1, (int) ($input['page'] ?? 1));
        $limit  = min(50, max(1, (int) ($input['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        return $this->orderModel->findByUser($userId, $limit, $offset);
    }

    /**
     * Listado de pedidos para el admin con filtros.
     */
    public function list(array $input): array
    {
        $filters = [
            'estado'      => $input['estado'] ?? null,
            'usuario_id'  => !empty($input['usuario_id']) ? (int) $input['usuario_id'] : null,
            'fecha_desde' => $input['fecha_desde'] ?? null,
            'fecha_hasta' => $input['fecha_hasta'] ?? null,
        ];

        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        $page   = max(1, (int) ($input['page'] ?? 1));
        $limit  = min(100, max(1, (int) ($input['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        return $this->orderModel->all($filters, $limit, $offset);
    }

    /**
     * Obtiene un pedido aplicando las reglas de permiso.
     * Retorna [success, message, order?, status_code?].
     */
    public function show(int $orderId, ?int $userId, bool $isAdmin): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'ID de pedido inválido.', 'status_code' => 400];
        }

        $order = $this->orderModel->findById($orderId);

        if (!$order) {
            return ['success' => false, 'message' => 'Pedido no encontrado.', 'status_code' => 404];
        }

        if (!$isAdmin && $userId !== null && (int) $order['usuario_id'] !== $userId) {
            return [
                'success'     => false,
                'message'     => 'No tienes permiso para ver este pedido.',
                'status_code' => 403,
            ];
        }

        return ['success' => true, 'message' => 'Pedido obtenido correctamente.', 'order' => $order];
    }

    /**
     * Actualiza el estado de un pedido.
     * Retorna [success, message, order?, status_code?].
     */
    public function updateStatus(int $orderId, string $status): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'ID de pedido inválido.', 'status_code' => 400];
        }

        $status = strtoupper(trim($status));

        $allowed = ['PENDIENTE', 'PAGADO', 'RECHAZADO'];
        if (!in_array($status, $allowed, true)) {
            return [
                'success'     => false,
                'message'     => 'Estado inválido. Usa: PENDIENTE, PAGADO o RECHAZADO.',
                'status_code' => 422,
            ];
        }

        $order = $this->orderModel->findById($orderId);

        if (!$order) {
            return ['success' => false, 'message' => 'Pedido no encontrado.', 'status_code' => 404];
        }

        $ok = $this->orderModel->updateStatus($orderId, $status);

        if (!$ok) {
            return ['success' => false, 'message' => 'No se pudo actualizar el estado del pedido.'];
        }

        return [
            'success' => true,
            'message' => 'Estado del pedido actualizado correctamente.',
            'order'   => $this->orderModel->findById($orderId),
        ];
    }

    /**
     * Contadores de pedidos por estado (dashboard).
     */
    public function stats(): array
    {
        return $this->orderModel->countByStatus();
    }
}
