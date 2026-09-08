<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Product.php';

class Cart
{
    private PDO $db;
    private Product $productModel;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->productModel = new Product();
    }

    /**
     * Obtiene o crea el carrito de un usuario.
     * Retorna el ID del carrito.
     */
    public function getOrCreateCart(int $userId): int
    {
        $sql = "SELECT id FROM carritos WHERE usuario_id = :usuario_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usuario_id' => $userId]);

        $cart = $stmt->fetch();

        if ($cart) {
            return (int) $cart['id'];
        }

        // Crear carrito nuevo
        $sql = "INSERT INTO carritos (usuario_id) VALUES (:usuario_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usuario_id' => $userId]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Obtiene el carrito completo de un usuario (con productos).
     */
    public function getCartByUser(int $userId): array
    {
        $cartId = $this->getOrCreateCart($userId);

        $sql = "SELECT cd.id AS detalle_id,
                       cd.producto_id,
                       cd.cantidad,
                       p.nombre,
                       p.precio,
                       p.stock,
                       p.imagen,
                       p.sku,
                       p.disponible,
                       p.activo,
                       (cd.cantidad * p.precio) AS subtotal
                FROM carrito_detalle cd
                INNER JOIN productos p ON p.id = cd.producto_id
                WHERE cd.carrito_id = :carrito_id
                ORDER BY cd.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['carrito_id' => $cartId]);

        $items = $stmt->fetchAll();

        $total = 0;
        $totalItems = 0;

        foreach ($items as &$item) {
            $item['subtotal'] = (float) $item['subtotal'];
            $item['precio']   = (float) $item['precio'];
            $total           += $item['subtotal'];
            $totalItems      += (int) $item['cantidad'];
        }

        return [
            'carrito_id'   => $cartId,
            'items'        => $items,
            'total_items'  => $totalItems,
            'total'        => $total
        ];
    }

    /**
     * Agrega un producto al carrito o actualiza la cantidad si ya existe.
     */
    public function addItem(int $userId, int $productId, int $quantity = 1): array
    {
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'La cantidad debe ser mayor a 0.'];
        }

        // Verificar que el producto exista y tenga stock
        $product = $this->productModel->findById($productId);

        if (!$product || !$product['activo'] || !$product['disponible']) {
            return ['success' => false, 'message' => 'El producto no está disponible.'];
        }

        if ((int) $product['stock'] < $quantity) {
            return ['success' => false, 'message' => 'No hay stock suficiente.'];
        }

        $cartId = $this->getOrCreateCart($userId);

        // Verificar si el producto ya está en el carrito
        $sql = "SELECT id, cantidad FROM carrito_detalle 
                WHERE carrito_id = :carrito_id AND producto_id = :producto_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'carrito_id'  => $cartId,
            'producto_id' => $productId
        ]);

        $existing = $stmt->fetch();

        if ($existing) {
            $newQuantity = (int) $existing['cantidad'] + $quantity;

            // Validar stock total
            if ((int) $product['stock'] < $newQuantity) {
                return ['success' => false, 'message' => 'No hay stock suficiente para la cantidad solicitada.'];
            }

            $sql = "UPDATE carrito_detalle 
                    SET cantidad = :cantidad 
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'cantidad' => $newQuantity,
                'id'       => $existing['id']
            ]);
        } else {
            $sql = "INSERT INTO carrito_detalle (carrito_id, producto_id, cantidad)
                    VALUES (:carrito_id, :producto_id, :cantidad)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'carrito_id'  => $cartId,
                'producto_id' => $productId,
                'cantidad'    => $quantity
            ]);
        }

        return [
            'success' => true,
            'message' => 'Producto agregado al carrito.',
            'cart'    => $this->getCartByUser($userId)
        ];
    }

    /**
     * Actualiza la cantidad de un producto en el carrito.
     */
    public function updateItem(int $userId, int $productId, int $quantity): array
    {
        if ($quantity <= 0) {
            return $this->removeItem($userId, $productId);
        }

        $product = $this->productModel->findById($productId);

        if (!$product || !$product['activo']) {
            return ['success' => false, 'message' => 'El producto no existe o no está activo.'];
        }

        if ((int) $product['stock'] < $quantity) {
            return ['success' => false, 'message' => 'No hay stock suficiente.'];
        }

        $cartId = $this->getOrCreateCart($userId);

        $sql = "UPDATE carrito_detalle 
                SET cantidad = :cantidad 
                WHERE carrito_id = :carrito_id AND producto_id = :producto_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'cantidad'    => $quantity,
            'carrito_id'  => $cartId,
            'producto_id' => $productId
        ]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'El producto no está en el carrito.'];
        }

        return [
            'success' => true,
            'message' => 'Cantidad actualizada.',
            'cart'    => $this->getCartByUser($userId)
        ];
    }

    /**
     * Elimina un producto del carrito.
     */
    public function removeItem(int $userId, int $productId): array
    {
        $cartId = $this->getOrCreateCart($userId);

        $sql = "DELETE FROM carrito_detalle 
                WHERE carrito_id = :carrito_id AND producto_id = :producto_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'carrito_id'  => $cartId,
            'producto_id' => $productId
        ]);

        return [
            'success' => true,
            'message' => 'Producto eliminado del carrito.',
            'cart'    => $this->getCartByUser($userId)
        ];
    }

    /**
     * Vacía completamente el carrito de un usuario.
     */
    public function clear(int $userId): bool
    {
        $cartId = $this->getOrCreateCart($userId);

        $sql = "DELETE FROM carrito_detalle WHERE carrito_id = :carrito_id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute(['carrito_id' => $cartId]);
    }

    /**
     * Cuenta la cantidad total de ítems en el carrito.
     */
    public function countItems(int $userId): int
    {
        $cart = $this->getCartByUser($userId);
        return $cart['total_items'];
    }

    /**
     * Verifica que todos los productos del carrito tengan stock suficiente.
     * Útil antes del checkout.
     */
    public function validateStock(int $userId): array
    {
        $cart = $this->getCartByUser($userId);
        $errors = [];

        if (empty($cart['items'])) {
            return [
                'valid'  => false,
                'errors' => ['El carrito está vacío.']
            ];
        }

        foreach ($cart['items'] as $item) {
            if (!$item['activo'] || !$item['disponible']) {
                $errors[] = "El producto \"{$item['nombre']}\" ya no está disponible.";
                continue;
            }

            if ((int) $item['stock'] < (int) $item['cantidad']) {
                $errors[] = "Stock insuficiente para \"{$item['nombre']}\". Disponible: {$item['stock']}.";
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'cart'   => $cart
        ];
    }
}