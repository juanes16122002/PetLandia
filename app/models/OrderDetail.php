<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class OrderDetail
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Busca un detalle por ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM pedido_detalle WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $detail = $stmt->fetch();
        return $detail ?: null;
    }

    /**
     * Obtiene todos los detalles de un pedido.
     */
    public function findByOrderId(int $orderId): array
    {
        $sql = "SELECT pd.*,
                       p.imagen AS producto_imagen
                FROM pedido_detalle pd
                LEFT JOIN productos p ON p.id = pd.producto_id
                WHERE pd.pedido_id = :pedido_id
                ORDER BY pd.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['pedido_id' => $orderId]);

        $details = $stmt->fetchAll();

        foreach ($details as &$detail) {
            $detail['precio_unitario'] = (float) $detail['precio_unitario'];
            $detail['subtotal']        = (float) $detail['subtotal'];
            $detail['cantidad']        = (int) $detail['cantidad'];
        }

        return $details;
    }

    /**
     * Crea un detalle de pedido.
     * Retorna el ID del detalle creado.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO pedido_detalle (
                    pedido_id,
                    producto_id,
                    nombre_producto,
                    sku_producto,
                    precio_unitario,
                    cantidad,
                    subtotal
                ) VALUES (
                    :pedido_id,
                    :producto_id,
                    :nombre_producto,
                    :sku_producto,
                    :precio_unitario,
                    :cantidad,
                    :subtotal
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'pedido_id'        => $data['pedido_id'],
            'producto_id'      => $data['producto_id'],
            'nombre_producto'  => $data['nombre_producto'],
            'sku_producto'     => $data['sku_producto'],
            'precio_unitario'  => $data['precio_unitario'],
            'cantidad'         => $data['cantidad'],
            'subtotal'         => $data['subtotal'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Crea varios detalles de una vez (útil al crear un pedido).
     * Recibe un array de detalles.
     */
    public function createMany(int $orderId, array $items): bool
    {
        $sql = "INSERT INTO pedido_detalle (
                    pedido_id,
                    producto_id,
                    nombre_producto,
                    sku_producto,
                    precio_unitario,
                    cantidad,
                    subtotal
                ) VALUES (
                    :pedido_id,
                    :producto_id,
                    :nombre_producto,
                    :sku_producto,
                    :precio_unitario,
                    :cantidad,
                    :subtotal
                )";

        $stmt = $this->db->prepare($sql);

        try {
            foreach ($items as $item) {
                $stmt->execute([
                    'pedido_id'        => $orderId,
                    'producto_id'      => $item['producto_id'],
                    'nombre_producto'  => $item['nombre_producto'],
                    'sku_producto'     => $item['sku_producto'],
                    'precio_unitario'  => $item['precio_unitario'],
                    'cantidad'         => $item['cantidad'],
                    'subtotal'         => $item['subtotal'],
                ]);
            }
            return true;
        } catch (Exception $e) {
            error_log('Error al crear detalles de pedido: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Calcula el total de un pedido a partir de sus detalles.
     */
    public function calculateOrderTotal(int $orderId): float
    {
        $sql = "SELECT COALESCE(SUM(subtotal), 0) AS total
                FROM pedido_detalle
                WHERE pedido_id = :pedido_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['pedido_id' => $orderId]);

        $result = $stmt->fetch();
        return (float) ($result['total'] ?? 0);
    }

    /**
     * Cuenta la cantidad de ítems de un pedido.
     */
    public function countItems(int $orderId): int
    {
        $sql = "SELECT COALESCE(SUM(cantidad), 0) AS total
                FROM pedido_detalle
                WHERE pedido_id = :pedido_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['pedido_id' => $orderId]);

        $result = $stmt->fetch();
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Elimina todos los detalles de un pedido.
     * (Normalmente no se usa, solo en casos de rollback manual).
     */
    public function deleteByOrderId(int $orderId): bool
    {
        $sql = "DELETE FROM pedido_detalle WHERE pedido_id = :pedido_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['pedido_id' => $orderId]);
    }
}