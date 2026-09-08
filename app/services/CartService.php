<?php
declare(strict_types=1);

/**
 * Servicio del carrito.
 * Orquesta las operaciones del carrito y expone una API de negocio
 * independiente de HTTP para que el controller solo adapte la respuesta.
 */

class CartService
{
    private Cart $cartModel;

    public function __construct()
    {
        $this->cartModel = new Cart();
    }

    /**
     * Obtiene el carrito completo del usuario.
     */
    public function get(int $userId): array
    {
        return $this->cartModel->getCartByUser($userId);
    }

    /**
     * Agrega un producto al carrito.
     * Valida el ID antes de delegar al modelo (regla de negocio).
     */
    public function add(int $userId, int $productId, int $quantity): array
    {
        if ($productId <= 0) {
            return ['success' => false, 'message' => 'ID de producto inválido.'];
        }

        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'La cantidad debe ser mayor a 0.'];
        }

        return $this->cartModel->addItem($userId, $productId, $quantity);
    }

    /**
     * Actualiza la cantidad de un producto en el carrito.
     */
    public function update(int $userId, int $productId, int $quantity): array
    {
        if ($productId <= 0) {
            return ['success' => false, 'message' => 'ID de producto inválido.'];
        }

        return $this->cartModel->updateItem($userId, $productId, $quantity);
    }

    /**
     * Elimina un producto del carrito.
     */
    public function remove(int $userId, int $productId): array
    {
        if ($productId <= 0) {
            return ['success' => false, 'message' => 'ID de producto inválido.'];
        }

        return $this->cartModel->removeItem($userId, $productId);
    }

    /**
     * Vacía el carrito del usuario.
     */
    public function clear(int $userId): array
    {
        $this->cartModel->clear($userId);

        return [
            'success' => true,
            'message' => 'Carrito vaciado correctamente.',
            'cart'    => $this->cartModel->getCartByUser($userId),
        ];
    }

    /**
     * Cantidad total de ítems del carrito.
     */
    public function count(int $userId): int
    {
        return $this->cartModel->countItems($userId);
    }

    /**
     * Valida el stock de todos los ítems del carrito (previo al checkout).
     */
    public function validate(int $userId): array
    {
        $validation = $this->cartModel->validateStock($userId);

        return [
            'valid'  => $validation['valid'],
            'errors' => $validation['errors'],
            'cart'   => $validation['cart'],
        ];
    }
}
