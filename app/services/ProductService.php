<?php
declare(strict_types=1);

/**
 * Servicio de productos.
 * Orquesta el catálogo de productos y concentra las reglas de negocio
 * (filtros, paginación y validaciones) que antes vivían en el controller.
 */

class ProductService
{
    private Product $productModel;
    private Category $categoryModel;

    public function __construct()
    {
        $this->productModel  = new Product();
        $this->categoryModel = new Category();
    }

    /**
     * Normaliza y depura los filtros de listado disponibles para la API pública.
     */
    public function buildCatalogFilters(array $input): array
    {
        $filters = [
            'categoria_id'     => !empty($input['categoria_id']) ? (int) $input['categoria_id'] : null,
            'nombre'           => $input['nombre'] ?? $input['q'] ?? null,
            'precio_min'       => isset($input['precio_min']) ? (float) $input['precio_min'] : null,
            'precio_max'       => isset($input['precio_max']) ? (float) $input['precio_max'] : null,
            'solo_disponibles' => !isset($input['solo_disponibles']) || $input['solo_disponibles'] !== '0',
            'solo_activos'     => true,
            'order_by'         => $input['order_by'] ?? 'p.nombre',
            'order_dir'        => $input['order_dir'] ?? 'ASC',
        ];

        return array_filter($filters, fn($v) => $v !== null && $v !== '');
    }

    /**
     * Lista productos de catálogo con paginación.
     */
    public function catalog(array $input): array
    {
        $filters = $this->buildCatalogFilters($input);

        $page   = max(1, (int) ($input['page'] ?? 1));
        $limit  = min(50, max(1, (int) ($input['limit'] ?? 12)));
        $offset = ($page - 1) * $limit;

        $products = $this->productModel->all($filters, $limit, $offset);
        $total    = $this->productModel->count($filters);

        return [
            'products'   => $products,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $limit,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * Lista productos para el panel admin (incluye inactivos).
     */
    public function adminProducts(array $input): array
    {
        $filters = [
            'categoria_id' => !empty($input['categoria_id']) ? (int) $input['categoria_id'] : null,
            'nombre'       => $input['nombre'] ?? $input['q'] ?? null,
            'solo_activos' => false,
        ];

        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        $page   = max(1, (int) ($input['page'] ?? 1));
        $limit  = min(100, max(1, (int) ($input['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $products = $this->productModel->all($filters, $limit, $offset);
        $total    = $this->productModel->count($filters);

        return [
            'products'   => $products,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $limit,
                'current_page' => $page,
                'last_page'    => (int) ceil(max(1, $total) / $limit),
            ],
        ];
    }

    /**
     * Obtiene un producto activo para la vista pública.
     * Retorna null si no existe o no está activo.
     */
    public function publicShow(int $id): ?array
    {
        $product = $this->productModel->findById($id);

        if (!$product || !(bool) $product['activo']) {
            return null;
        }

        return $product;
    }

    /**
     * Obtiene un producto, exista o no, sin restricción de estado.
     */
    public function show(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->productModel->findById($id);
    }

    /**
     * Crea un producto.
     * Retorna [success, message, errors?, product?].
     */
    public function create(array $data): array
    {
        $validator = new Validator();
        $validator
            ->required('nombre', $data['nombre'] ?? null)
            ->min('nombre', $data['nombre'] ?? '', 2)
            ->max('nombre', $data['nombre'] ?? '', 150)
            ->required('categoria_id', isset($data['categoria_id']) ? (string) $data['categoria_id'] : null)
            ->required('precio', isset($data['precio']) ? (string) $data['precio'] : null)
            ->required('sku', $data['sku'] ?? null);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors'  => $validator->errors(),
            ];
        }

        if (!is_valid_price($data['precio'])) {
            return [
                'success' => false,
                'message' => 'El precio no es válido.',
                'errors'  => ['precio' => 'Precio inválido.'],
            ];
        }

        if ($this->productModel->skuExists($data['sku'])) {
            return [
                'success' => false,
                'message' => 'El SKU ya existe.',
                'errors'  => ['sku' => 'Este SKU ya está en uso.'],
            ];
        }

        $category = $this->categoryModel->findById((int) $data['categoria_id']);
        if (!$category) {
            return [
                'success' => false,
                'message' => 'La categoría no existe.',
                'errors'  => ['categoria_id' => 'Categoría inválida.'],
            ];
        }

        try {
            $stock = (int) ($data['stock'] ?? 0);

            $productId = $this->productModel->create([
                'categoria_id' => (int) $data['categoria_id'],
                'nombre'       => sanitize_string($data['nombre']),
                'descripcion'  => sanitize_string($data['descripcion'] ?? ''),
                'precio'       => (float) $data['precio'],
                'stock'        => $stock,
                'disponible'   => $stock > 0,
                'imagen'       => $data['imagen'] ?? null,
                'sku'          => strtoupper(sanitize_string($data['sku'])),
                'activo'       => true,
            ]);

            return [
                'success' => true,
                'message' => 'Producto creado correctamente.',
                'product' => $this->productModel->findById($productId),
            ];

        } catch (Exception $e) {
            error_log('Error al crear producto: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'No se pudo crear el producto.',
            ];
        }
    }

    /**
     * Actualiza un producto.
     * Retorna [success, message, errors?, product?].
     */
    public function update(int $id, array $data): array
    {
        $product = $this->productModel->findById($id);

        if (!$product) {
            return ['success' => false, 'message' => 'Producto no encontrado.', 'not_found' => true];
        }

        if (!empty($data['sku']) && $this->productModel->skuExists($data['sku'], $id)) {
            return [
                'success' => false,
                'message' => 'El SKU ya existe.',
                'errors'  => ['sku' => 'Este SKU ya está en uso.'],
            ];
        }

        $updateData = [];

        if (isset($data['nombre'])) {
            $updateData['nombre'] = sanitize_string($data['nombre']);
        }
        if (isset($data['descripcion'])) {
            $updateData['descripcion'] = sanitize_string($data['descripcion']);
        }
        if (isset($data['precio'])) {
            if (!is_valid_price($data['precio'])) {
                return [
                    'success' => false,
                    'message' => 'El precio no es válido.',
                    'errors'  => ['precio' => 'Precio inválido.'],
                ];
            }
            $updateData['precio'] = (float) $data['precio'];
        }
        if (isset($data['categoria_id'])) {
            $updateData['categoria_id'] = (int) $data['categoria_id'];
        }
        if (isset($data['sku'])) {
            $updateData['sku'] = strtoupper(sanitize_string($data['sku']));
        }
        if (isset($data['imagen'])) {
            $updateData['imagen'] = $data['imagen'];
        }
        if (isset($data['activo'])) {
            $updateData['activo'] = (bool) $data['activo'];
        }
        if (isset($data['stock'])) {
            $updateData['stock'] = (int) $data['stock'];
            $updateData['disponible'] = (int) $data['stock'] > 0;
        }

        if (empty($updateData)) {
            return [
                'success' => false,
                'message' => 'No se enviaron datos para actualizar.',
            ];
        }

        $ok = $this->productModel->update($id, $updateData);

        if (!$ok) {
            return ['success' => false, 'message' => 'No se pudo actualizar el producto.'];
        }

        return [
            'success' => true,
            'message' => 'Producto actualizado correctamente.',
            'product' => $this->productModel->findById($id),
        ];
    }

    /**
     * Desactiva un producto.
     * Retorna [success, message, not_found?].
     */
    public function deactivate(int $id): array
    {
        $product = $this->productModel->findById($id);

        if (!$product) {
            return ['success' => false, 'message' => 'Producto no encontrado.', 'not_found' => true];
        }

        $ok = $this->productModel->deactivate($id);

        if (!$ok) {
            return ['success' => false, 'message' => 'No se pudo desactivar el producto.'];
        }

        return ['success' => true, 'message' => 'Producto desactivado correctamente.'];
    }

    /**
     * Lista categorías (con opción de contar productos activos).
     */
    public function categories(bool $withCount = false): array
    {
        return $withCount
            ? $this->categoryModel->allWithProductCount()
            : $this->categoryModel->all();
    }
}
