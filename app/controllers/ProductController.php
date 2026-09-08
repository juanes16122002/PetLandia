<?php
declare(strict_types=1);

class ProductController
{
    private ProductService $service;
    private InventoryService $inventoryService;

    public function __construct()
    {
        $this->service          = new ProductService();
        $this->inventoryService = new InventoryService();
    }

    /**
     * GET /api/products.php?action=list
     * Lista productos con filtros (público).
     */
    public function list(): void
    {
        $payload = $this->service->catalog($_GET);

        json_success('Productos obtenidos correctamente.', $payload);
    }

    /**
     * GET /api/products.php?action=show&id=1
     * Muestra un producto específico (público).
     */
    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        $product = $this->service->publicShow($id);

        if (!$product) {
            json_error('Producto no encontrado.', [], 404);
        }

        json_success('Producto obtenido correctamente.', [
            'product' => $product
        ]);
    }

    /**
     * GET /api/products.php?action=admin-show&id=1
     * Muestra cualquier producto para edición (solo Admin), incluso inactivos.
     */
    public function adminShow(): void
    {
        AdminMiddleware::handle();

        $id = (int) ($_GET['id'] ?? 0);

        $product = $this->service->show($id);

        if (!$product) {
            json_error('Producto no encontrado.', [], 404);
        }

        json_success('Producto obtenido correctamente.', [
            'product' => $product
        ]);
    }

    /**
     * GET /api/products.php?action=categories
     * Lista categorías (público).
     */
    public function categories(): void
    {
        $withCount = ($_GET['with_count'] ?? '0') === '1';

        $categories = $this->service->categories($withCount);

        json_success('Categorías obtenidas correctamente.', [
            'categories' => $categories
        ]);
    }

    /**
     * POST /api/products.php?action=create
     * Crea un producto (solo Admin).
     */
    public function create(): void
    {
        AdminMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $result = $this->service->create(json_input());

        if ($result['success']) {
            json_success($result['message'], ['product' => $result['product']], 201);
        }

        json_error($result['message'], $result['errors'] ?? [], 422);
    }

    /**
     * POST /api/products.php?action=update
     * Actualiza un producto (solo Admin).
     */
    public function update(): void
    {
        AdminMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $data = json_input();
        $id   = (int) ($data['id'] ?? $_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID de producto inválido.', [], 400);
        }

        $result = $this->service->update($id, $data);

        if (!empty($result['not_found'])) {
            json_error($result['message'], [], 404);
        }

        if (!$result['success']) {
            json_error($result['message'], $result['errors'] ?? [], 422);
        }

        json_success($result['message'], [
            'product' => $result['product']
        ]);
    }

    /**
     * POST /api/products.php?action=update-stock
     * Actualiza solo el stock (Admin + Inventario).
     */
    public function updateStock(): void
    {
        InventoryMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $data   = json_input();
        $id     = (int) ($data['id'] ?? $data['product_id'] ?? 0);
        $stock  = (int) ($data['stock'] ?? -1);

        $result = $this->inventoryService->updateStock($id, $stock);

        if (isset($result['status_code'])) {
            json_error($result['message'], [], $result['status_code']);
        }

        if (!$result['success']) {
            json_error($result['message'], [], 500);
        }

        json_success($result['message'], [
            'product' => $result['product']
        ]);
    }

    /**
     * POST /api/products.php?action=deactivate
     * Desactiva un producto (solo Admin).
     */
    public function deactivate(): void
    {
        AdminMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $data = json_input();
        $id   = (int) ($data['id'] ?? $_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID de producto inválido.', [], 400);
        }

        $result = $this->service->deactivate($id);

        if (!empty($result['not_found'])) {
            json_error($result['message'], [], 404);
        }

        if (!$result['success']) {
            json_error($result['message'], [], 500);
        }

        json_success($result['message']);
    }
}
