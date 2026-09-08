<?php
declare(strict_types=1);

class PaymentController
{
    private PaymentService $service;

    public function __construct()
    {
        $this->service = new PaymentService();
    }

    /**
     * POST /api/payments.php?action=process
     * Procesa el pago simulado de un pedido.
     */
    public function process(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_error('Método no permitido.', [], 405);
        }

        CsrfMiddleware::handle();

        $data    = json_input();
        $orderId = (int) ($data['order_id'] ?? $data['pedido_id'] ?? 0);

        $result = $this->service->process($orderId, auth()->id(), auth()->isAdmin(), $data);

        if (!$result['success']) {
            json_error($result['message'], [
                'status' => $result['payment']['status'] ?? 'RECHAZADO'
            ], $result['status_code'] ?? 422);
        }

        json_success($result['message'], [
            'payment' => $result['payment']
        ]);
    }

    /**
     * GET /api/payments.php?action=show&order_id=1
     * Obtiene el pago de un pedido.
     */
    public function show(): void
    {
        AuthMiddleware::handle();

        $orderId = (int) ($_GET['order_id'] ?? $_GET['pedido_id'] ?? 0);

        $result = $this->service->show($orderId, auth()->id(), auth()->isAdmin());

        if (!$result['success']) {
            json_error($result['message'], [], $result['status_code'] ?? 403);
        }

        json_success($result['message'], [
            'payment' => $result['payment']
        ]);
    }

    /**
     * GET /api/payments.php?action=test-cards
     * Devuelve las tarjetas de prueba del simulador.
     */
    public function testCards(): void
    {
        AuthMiddleware::handle();

        $payload = $this->service->testCards();

        json_success('Tarjetas de prueba del simulador.', $payload);
    }
}
