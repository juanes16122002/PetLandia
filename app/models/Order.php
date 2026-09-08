<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/Cart.php';
require_once __DIR__ . '/OrderDetail.php';

class Order
{
    private PDO $db;
    private Product $productModel;
    private Cart $cartModel;
    private OrderDetail $orderDetailModel;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->productModel = new Product();
        $this->cartModel = new Cart();
        $this->orderDetailModel = new OrderDetail();
    }

    /**
     * Busca un pedido por ID (con sus detalles).
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT p.*, 
                       u.nombre AS cliente_nombre,
                       u.apellido AS cliente_apellido,
                       u.email AS cliente_email
                FROM pedidos p
                INNER JOIN usuarios u ON u.id = p.usuario_id
                WHERE p.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $order['detalles'] = $this->orderDetailModel->findByOrderId($id);
        $order['subtotal'] = (float) $order['subtotal'];
        $order['total']    = (float) $order['total'];

        return $order;
    }

    /**
     * Lista los pedidos de un usuario (historial del cliente).
     */
    public function findByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT id, estado, subtotal, total, created_at
                FROM pedidos
                WHERE usuario_id = :usuario_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('usuario_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $order['subtotal'] = (float) $order['subtotal'];
            $order['total']    = (float) $order['total'];
        }

        return $orders;
    }

    /**
     * Lista todos los pedidos (para el administrador).
     */
    public function all(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT p.id, p.estado, p.subtotal, p.total, p.created_at,
                       u.nombre AS cliente_nombre, u.apellido AS cliente_apellido,
                       u.email AS cliente_email
                FROM pedidos p
                INNER JOIN usuarios u ON u.id = p.usuario_id
                WHERE 1=1";

        $params = [];

        if (!empty($filters['estado'])) {
            $sql .= " AND p.estado = :estado";
            $params['estado'] = $filters['estado'];
        }

        if (!empty($filters['usuario_id'])) {
            $sql .= " AND p.usuario_id = :usuario_id";
            $params['usuario_id'] = (int) $filters['usuario_id'];
        }

        if (!empty($filters['fecha_desde'])) {
            $sql .= " AND p.created_at >= :fecha_desde";
            $params['fecha_desde'] = $filters['fecha_desde'];
        }

        if (!empty($filters['fecha_hasta'])) {
            $sql .= " AND p.created_at <= :fecha_hasta";
            $params['fecha_hasta'] = $filters['fecha_hasta'];
        }

        $sql .= " ORDER BY p.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $order['subtotal'] = (float) $order['subtotal'];
            $order['total']    = (float) $order['total'];
        }

        return $orders;
    }

    /**
     * Crea un pedido a partir del carrito del usuario.
     * Retorna el ID del pedido creado o null si falla.
     */
    public function createFromCart(int $userId, array $extraData = []): ?int
    {
        // 1. Validar stock del carrito
        $validation = $this->cartModel->validateStock($userId);

        if (!$validation['valid']) {
            return null;
        }

        $cart = $validation['cart'];

        if (empty($cart['items'])) {
            return null;
        }

        try {
            $this->db->beginTransaction();

            // 2. Crear el pedido
            $sql = "INSERT INTO pedidos (
                        usuario_id,
                        estado,
                        subtotal,
                        total,
                        direccion_envio,
                        telefono_contacto,
                        observaciones
                    ) VALUES (
                        :usuario_id,
                        'PENDIENTE',
                        :subtotal,
                        :total,
                        :direccion_envio,
                        :telefono_contacto,
                        :observaciones
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'usuario_id'         => $userId,
                'subtotal'           => $cart['total'],
                'total'              => $cart['total'],
                'direccion_envio'    => $extraData['direccion_envio'] ?? null,
                'telefono_contacto'  => $extraData['telefono_contacto'] ?? null,
                'observaciones'      => $extraData['observaciones'] ?? null,
            ]);

            $orderId = (int) $this->db->lastInsertId();

            // 3. Preparar los detalles para OrderDetail
            $details = [];

            foreach ($cart['items'] as $item) {
                $details[] = [
                    'producto_id'      => $item['producto_id'],
                    'nombre_producto'  => $item['nombre'],
                    'sku_producto'     => $item['sku'],
                    'precio_unitario'  => $item['precio'],
                    'cantidad'         => $item['cantidad'],
                    'subtotal'         => $item['subtotal'],
                ];

                // Descontar stock
                $stockOk = $this->productModel->decreaseStock(
                    (int) $item['producto_id'],
                    (int) $item['cantidad']
                );

                if (!$stockOk) {
                    throw new RuntimeException(
                        "Stock insuficiente para el producto ID {$item['producto_id']}"
                    );
                }
            }

            // 4. Crear los detalles usando OrderDetail
            $detailsCreated = $this->orderDetailModel->createMany($orderId, $details);

            if (!$detailsCreated) {
                throw new RuntimeException('No se pudieron crear los detalles del pedido.');
            }

            // 5. Vaciar el carrito
            $this->cartModel->clear($userId);

            $this->db->commit();
            return $orderId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al crear pedido: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualiza el estado de un pedido.
     * Solo debe ser usado por el Administrador.
     */
    public function updateStatus(int $orderId, string $status): bool
    {
        $allowed = ['PENDIENTE', 'PAGADO', 'RECHAZADO'];

        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $sql = "UPDATE pedidos SET estado = :estado WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'estado' => $status,
            'id'     => $orderId
        ]);
    }

    /**
     * Marca un pedido como PAGADO.
     */
    public function markAsPaid(int $orderId): bool
    {
        return $this->updateStatus($orderId, 'PAGADO');
    }

    /**
     * Marca un pedido como RECHAZADO.
     */
    public function markAsRejected(int $orderId): bool
    {
        return $this->updateStatus($orderId, 'RECHAZADO');
    }

    /**
     * Cuenta pedidos por estado (útil para dashboard admin).
     */
    public function countByStatus(): array
    {
        $sql = "SELECT estado, COUNT(*) AS total
                FROM pedidos
                GROUP BY estado";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        $result = [
            'PENDIENTE' => 0,
            'PAGADO'    => 0,
            'RECHAZADO' => 0
        ];

        foreach ($rows as $row) {
            $result[$row['estado']] = (int) $row['total'];
        }

        return $result;
    }
}