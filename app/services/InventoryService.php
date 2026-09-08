<?php
declare(strict_types=1);

/**
 * Servicio de inventario.
 * Orquesta la gestión de stock y los reportes de inventario
 * (stock bajo, resumen para el dashboard).
 */

class InventoryService
{
    private Product $productModel;

    public function __construct()
    {
        $this->productModel = new Product();
    }

    /**
     * Actualiza el stock de un producto.
     * Retorna [success, message, product?, status_code?].
     */
    public function updateStock(int $productId, int $stock): array
    {
        if ($productId <= 0) {
            return ['success' => false, 'message' => 'ID de producto inválido.', 'status_code' => 400];
        }

        if ($stock < 0) {
            return ['success' => false, 'message' => 'El stock no puede ser negativo.', 'status_code' => 422];
        }

        $product = $this->productModel->findById($productId);

        if (!$product) {
            return ['success' => false, 'message' => 'Producto no encontrado.', 'status_code' => 404];
        }

        $ok = $this->productModel->updateStock($productId, $stock);

        if (!$ok) {
            return ['success' => false, 'message' => 'No se pudo actualizar el stock.'];
        }

        return [
            'success' => true,
            'message' => 'Stock actualizado correctamente.',
            'product' => $this->productModel->findById($productId),
        ];
    }

    /**
     * Productos activos con stock menor o igual al umbral.
     */
    public function lowStock(int $threshold = 5, int $limit = 20): array
    {
        $sql = "SELECT p.id, p.nombre, p.sku, p.stock, p.precio, p.disponible, c.nombre AS categoria
                FROM productos p
                INNER JOIN categorias c ON c.id = p.categoria_id
                WHERE p.activo = 1 AND p.stock <= :threshold
                ORDER BY p.stock ASC
                LIMIT :limit";

        $stmt = db()->prepare($sql);
        $stmt->bindValue('threshold', $threshold, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Resumen de inventario para el dashboard.
     * Retorna total activos, agotados y en stock bajo.
     */
    public function summary(int $threshold = 5): array
    {
        $totalActive = $this->productModel->count(['solo_activos' => true]);

        $lowStock = $this->lowStock($threshold, 100);

        $outOfStock = 0;
        foreach ($lowStock as $product) {
            if ((int) $product['stock'] === 0) {
                $outOfStock++;
            }
        }

        return [
            'total_active' => $totalActive,
            'low_stock'    => count($lowStock),
            'out_of_stock' => $outOfStock,
        ];
    }
}
