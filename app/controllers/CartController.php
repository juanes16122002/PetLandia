<?php
declare(strict_types=1);

class CartController
{
    private CartService $service;

    public function __construct()
    {
        $this->service = new CartService();
    }

    /**
     * GET /api/cart.php?action=get
     * Obtiene el carrito del usuario autenticado.
     */
    public function get(): void
    {
        AuthMiddleware::handle();

        json_success('Carrito obtenido correctamente.', [
            'cart' => $this->service->get(auth()->id())
        ]);
    }

    /**
     * POST /api/cart.php?action=add
     * Agrega un producto al carrito.
     */
    public function add(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $data = json_input();

        $productId = (int) ($data['product_id'] ?? $data['producto_id'] ?? 0);
        $quantity  = (int) ($data['quantity'] ?? $data['cantidad'] ?? 1);

        $result = $this->service->add(auth()->id(), $productId, $quantity);

        if (!$result['success']) {
            json_error($result['message'], [], 422);
        }

        json_success($result['message'], [
            'cart' => $result['cart']
        ]);
    }

    /**
     * POST /api/cart.php?action=update
     * Actualiza la cantidad de un producto en el carrito.
     */
    public function update(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $data = json_input();

        $productId = (int) ($data['product_id'] ?? $data['producto_id'] ?? 0);
        $quantity  = (int) ($data['quantity'] ?? $data['cantidad'] ?? 0);

        $result = $this->service->update(auth()->id(), $productId, $quantity);

        if (!$result['success']) {
            json_error($result['message'], [], 422);
        }

        json_success($result['message'], [
            'cart' => $result['cart']
        ]);
    }

    /**
     * POST /api/cart.php?action=remove
     * Elimina un producto del carrito.
     */
    public function remove(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $data = json_input();

        $productId = (int) ($data['product_id'] ?? $data['producto_id'] ?? 0);

        $result = $this->service->remove(auth()->id(), $productId);

        json_success($result['message'], [
            'cart' => $result['cart']
        ]);
    }

    /**
     * POST /api/cart.php?action=clear
     * Vacía el carrito completo.
     */
    public function clear(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $result = $this->service->clear(auth()->id());

        json_success($result['message'], [
            'cart' => $result['cart']
        ]);
    }

    /**
     * GET /api/cart.php?action=count
     * Devuelve la cantidad total de ítems en el carrito.
     */
    public function count(): void
    {
        AuthMiddleware::handle();

        json_success('Cantidad de ítems obtenida.', [
            'total_items' => $this->service->count(auth()->id())
        ]);
    }

    /**
     * GET /api/cart.php?action=validate
     * Valida el stock de todos los productos del carrito (antes del checkout).
     */
    public function validate(): void
    {
        AuthMiddleware::handle();

        $result = $this->service->validate(auth()->id());

        if (!$result['valid']) {
            json_error('Hay problemas con el stock del carrito.', [
                'errors' => $result['errors']
            ], 422);
        }

        json_success('El carrito es válido.', [
            'cart' => $result['cart']
        ]);
    }
}
