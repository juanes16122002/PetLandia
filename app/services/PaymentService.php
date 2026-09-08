<?php
declare(strict_types=1);

/**
 * Servicio de pagos (simulador).
 * Orquesta el procesamiento y la consulta de pagos, y concentra las
 * reglas de permiso y las validaciones de la transacción.
 */

class PaymentService
{
    private Payment $paymentModel;
    private Order $orderModel;

    public function __construct()
    {
        $this->paymentModel = new Payment();
        $this->orderModel   = new Order();
    }

    /**
     * Procesa el pago simulado de un pedido.
     * Retorna [success, message, payment?, status_code?].
     */
    public function process(int $orderId, ?int $userId, bool $isAdmin, array $data): array
    {
        $cardNumber = preg_replace('/\D/', '', $data['card_number'] ?? $data['numero_tarjeta'] ?? '');
        $cardHolder = trim($data['card_holder'] ?? $data['titular'] ?? '');
        $expiry     = trim($data['expiry'] ?? $data['vencimiento'] ?? '');
        $cvv        = trim($data['cvv'] ?? '');

        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'ID de pedido inválido.', 'status_code' => 400];
        }

        if (empty($cardNumber)) {
            return ['success' => false, 'message' => 'El número de tarjeta es obligatorio.', 'status_code' => 422];
        }

        if (empty($cardHolder)) {
            return ['success' => false, 'message' => 'El nombre del titular es obligatorio.', 'status_code' => 422];
        }

        if (empty($expiry)) {
            return ['success' => false, 'message' => 'La fecha de vencimiento es obligatoria.', 'status_code' => 422];
        }

        if (empty($cvv)) {
            return ['success' => false, 'message' => 'El CVV es obligatorio.', 'status_code' => 422];
        }

        $order = $this->orderModel->findById($orderId);

        if (!$order) {
            return ['success' => false, 'message' => 'Pedido no encontrado.', 'status_code' => 404];
        }

        if (!$isAdmin && $userId !== null && (int) $order['usuario_id'] !== $userId) {
            return [
                'success'     => false,
                'message'     => 'No tienes permiso para pagar este pedido.',
                'status_code' => 403,
            ];
        }

        if ($order['estado'] !== 'PENDIENTE') {
            return [
                'success'     => false,
                'message'     => 'Este pedido ya fue procesado.',
                'status_code' => 422,
            ];
        }

        $result = $this->paymentModel->process(
            $orderId,
            $cardNumber,
            $cardHolder,
            $expiry,
            $cvv
        );

        if (isset($result['payment_id'])) {
            return [
                'success' => true,
                'message' => $result['message'],
                'payment' => [
                    'payment_id' => $result['payment_id'],
                    'order_id'   => $result['order_id'],
                    'status'     => $result['status'],
                    'last_four'  => $result['last_four'],
                    'amount'     => $result['amount'],
                ],
            ];
        }

        return [
            'success'     => false,
            'message'     => $result['message'],
            'payment'     => ['status' => $result['status'] ?? 'RECHAZADO'],
            'status_code' => 422,
        ];
    }

    /**
     * Obtiene el pago de un pedido aplicando reglas de permiso.
     */
    public function show(int $orderId, ?int $userId, bool $isAdmin): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'ID de pedido inválido.', 'status_code' => 400];
        }

        $order = $this->orderModel->findById($orderId);

        if (!$order) {
            return ['success' => false, 'message' => 'Pedido no encontrado.', 'status_code' => 404];
        }

        if (!$isAdmin && $userId !== null && (int) $order['usuario_id'] !== $userId) {
            return [
                'success'     => false,
                'message'     => 'No tienes permiso para ver este pago.',
                'status_code' => 403,
            ];
        }

        $payment = $this->paymentModel->findByOrderId($orderId);

        if (!$payment) {
            return [
                'success'     => false,
                'message'     => 'No se encontró un pago para este pedido.',
                'status_code' => 404,
            ];
        }

        return ['success' => true, 'message' => 'Pago obtenido correctamente.', 'payment' => $payment];
    }

    /**
     * Devuelve las tarjetas de prueba del simulador.
     */
    public function testCards(): array
    {
        return [
            'cards'        => Payment::getTestCards(),
            'instructions' => 'Usa estas tarjetas para probar el pago. Cualquier otra será rechazada.',
        ];
    }
}
