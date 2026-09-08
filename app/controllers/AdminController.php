<?php
declare(strict_types=1);

class AdminController
{
    private OrderService $orderService;
    private ProductService $productService;
    private InventoryService $inventoryService;
    private User $userModel;
    private Category $categoryModel;

    public function __construct()
    {
        $this->orderService     = new OrderService();
        $this->productService   = new ProductService();
        $this->inventoryService = new InventoryService();
        $this->userModel        = new User();
        $this->categoryModel    = new Category();
    }

    /**
     * GET /api/admin.php?action=dashboard
     * Resumen general del panel de administración.
     */
    public function dashboard(): void
    {
        AdminMiddleware::handle();

        $orderStats = $this->orderService->stats();
        $invSummary = $this->inventoryService->summary(5);

        // Total de usuarios clientes
        $users = $this->userModel->all(1000, 0);
        $totalClients = 0;
        foreach ($users as $user) {
            if (strtoupper($user['rol']) === 'CLIENTE') {
                $totalClients++;
            }
        }

        $recentOrders = $this->orderService->list(['limit' => 5]);
        $categories   = $this->categoryModel->allWithProductCount();

        json_success('Dashboard cargado correctamente.', [
            'summary' => [
                'orders' => [
                    'pending'  => $orderStats['PENDIENTE'] ?? 0,
                    'paid'     => $orderStats['PAGADO'] ?? 0,
                    'rejected' => $orderStats['RECHAZADO'] ?? 0,
                    'total'    => array_sum($orderStats)
                ],
                'products' => [
                    'total_active' => $invSummary['total_active'],
                    'low_stock'    => $invSummary['low_stock'],
                    'out_of_stock' => $invSummary['out_of_stock'],
                ],
                'clients' => [
                    'total' => $totalClients
                ]
            ],
            'recent_orders'      => $recentOrders,
            'low_stock_products' => $this->inventoryService->lowStock(5, 20),
            'categories'         => $categories
        ]);
    }

    /**
     * GET /api/admin.php?action=orders
     * Lista de pedidos con filtros (atajo al consultar pedidos del admin).
     */
    public function orders(): void
    {
        AdminMiddleware::handle();

        $orders = $this->orderService->list($_GET);

        json_success('Pedidos obtenidos correctamente.', [
            'orders' => $orders
        ]);
    }

    /**
     * GET /api/admin.php?action=products
     * Lista de productos para el panel admin (incluye inactivos).
     * Disponible también para el rol Inventario (para gestionar stock).
     */
    public function products(): void
    {
        InventoryMiddleware::handle();

        json_success('Productos obtenidos correctamente.', $this->productService->adminProducts($_GET));
    }

    /**
     * GET /api/admin.php?action=users
     * Lista de usuarios registrados.
     */
    public function users(): void
    {
        AdminMiddleware::handle();

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $users = $this->userModel->all($limit, $offset);

        json_success('Usuarios obtenidos correctamente.', [
            'users' => $users
        ]);
    }

    /**
     * GET /api/admin.php?action=low-stock
     * Productos con stock bajo (≤ 5).
     */
    public function lowStock(): void
    {
        InventoryMiddleware::handle(); // Admin + Inventario

        $products = $this->inventoryService->lowStock(5, 20);

        json_success('Productos con stock bajo.', [
            'products' => $products
        ]);
    }
}
