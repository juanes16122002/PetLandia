<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Order.php';

class Payment
{
    private PDO $db;
    private Order $orderModel;

    /**
     * Tarjetas de prueba del simulador.
     * Estas son las únicas que se aceptan en el sistema.
     */
    private const TEST_CARDS = [
        // Tarjeta VÁLIDA
        '4111111111111111' => [
            'type'     => 'VISA',
            'result'   => 'APROBADO',
            'code'     => '00',
            'message'  => 'Transacción aprobada'
        ],
        // Tarjeta RECHAZADA
        '4000000000000002' => [
            'type'     => 'VISA',
            'result'   => 'RECHAZADO',
            'code'     => '05',
            'message'  => 'Tarjeta rechazada por el emisor'
        ],
        // Otra tarjeta válida (Mastercard de prueba)
        '5555555555554444' => [
            'type'     => 'MASTERCARD',
            'result'   => 'APROBADO',
            'code'     => '00',
            'message'  => 'Transacción aprobada'
        ],
    ];

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->orderModel = new Order();
    }

    /**
     * Busca un pago por ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM pagos WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $payment = $stmt->fetch();
        return $payment ?: null;
    }

    /**
     * Busca el pago asociado a un pedido.
     */
    public function findByOrderId(int $orderId): ?array
    {
        $sql = "SELECT * FROM pagos WHERE pedido_id = :pedido_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['pedido_id' => $orderId]);

        $payment = $stmt->fetch();
        return $payment ?: null;
    }

    /**
     * Procesa el pago simulado de un pedido.
     *
     * @param int    $orderId
     * @param string $cardNumber   Número de tarjeta (solo dígitos)
     * @param string $cardHolder   Nombre del titular
     * @param string $expiry       Formato MM/YY
     * @param string $cvv
     */
    public function process(
        int $orderId,
        string $cardNumber,
        string $cardHolder,
        string $expiry,
        string $cvv
    ): array {
        // 1. Verificar que el pedido exista y esté pendiente
        $order = $this->orderModel->findById($orderId);

        if (!$order) {
            return [
                'success' => false,
                'message' => 'El pedido no existe.'
            ];
        }

        if ($order['estado'] !== 'PENDIENTE') {
            return [
                'success' => false,
                'message' => 'Este pedido ya fue procesado.'
            ];
        }

        // Verificar que no exista un pago aprobado previo
        $existingPayment = $this->findByOrderId($orderId);
        if ($existingPayment && $existingPayment['estado'] === 'APROBADO') {
            return [
                'success' => false,
                'message' => 'Este pedido ya tiene un pago aprobado.'
            ];
        }

        // Un pago rechazado previo se elimina para permitir reintentar con otra tarjeta
        if ($existingPayment && $existingPayment['estado'] === 'RECHAZADO') {
            $sql = "DELETE FROM pagos WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => (int) $existingPayment['id']]);
        }

        // 2. Limpiar número de tarjeta
        $cardNumber = preg_replace('/\D/', '', $cardNumber);

        // 3. Validaciones básicas de formato
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            return [
                'success' => false,
                'message' => 'Número de tarjeta inválido.'
            ];
        }

        if (empty(trim($cardHolder))) {
            return [
                'success' => false,
                'message' => 'El nombre del titular es obligatorio.'
            ];
        }

        if (!preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
            return [
                'success' => false,
                'message' => 'Fecha de vencimiento inválida (use MM/YY).'
            ];
        }

        if (!preg_match('/^\d{3,4}$/', $cvv)) {
            return [
                'success' => false,
                'message' => 'CVV inválido.'
            ];
        }

        // 4. Simular el resultado según la tarjeta de prueba
        $simulation = $this->simulate($cardNumber);

        $status        = $simulation['result'];          // APROBADO o RECHAZADO
        $responseCode  = $simulation['code'];
        $responseMsg   = $simulation['message'];
        $lastFour      = substr($cardNumber, -4);

        try {
            $this->db->beginTransaction();

            // 5. Registrar el pago
            $sql = "INSERT INTO pagos (
                        pedido_id,
                        metodo,
                        estado,
                        monto,
                        ultimos_digitos,
                        codigo_respuesta,
                        mensaje_respuesta,
                        fecha_pago
                    ) VALUES (
                        :pedido_id,
                        'TARJETA_SIMULADA',
                        :estado,
                        :monto,
                        :ultimos_digitos,
                        :codigo_respuesta,
                        :mensaje_respuesta,
                        :fecha_pago
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'pedido_id'          => $orderId,
                'estado'             => $status,
                'monto'              => $order['total'],
                'ultimos_digitos'    => $lastFour,
                'codigo_respuesta'   => $responseCode,
                'mensaje_respuesta'  => $responseMsg,
                'fecha_pago'         => $status === 'APROBADO' ? date('Y-m-d H:i:s') : null,
            ]);

            $paymentId = (int) $this->db->lastInsertId();

            // 6. Actualizar estado del pedido según resultado
            //    Solo un pago aprobado cambia el estado (a PAGADO).
            //    El rechazo deja el pedido en PENDIENTE para permitir reintentar.
            if ($status === 'APROBADO') {
                $this->orderModel->markAsPaid($orderId);
            }

            $this->db->commit();

            return [
                'success'       => $status === 'APROBADO',
                'message'       => $responseMsg,
                'payment_id'    => $paymentId,
                'order_id'      => $orderId,
                'status'        => $status,
                'last_four'     => $lastFour,
                'amount'        => (float) $order['total']
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error al procesar pago: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Ocurrió un error al procesar el pago. Intente nuevamente.'
            ];
        }
    }

    /**
     * Simula el resultado del pago según el número de tarjeta.
     */
    private function simulate(string $cardNumber): array
    {
        // Si es una de las tarjetas de prueba conocidas
        if (isset(self::TEST_CARDS[$cardNumber])) {
            return self::TEST_CARDS[$cardNumber];
        }

        // Cualquier otra tarjeta se rechaza
        return [
            'type'    => 'DESCONOCIDA',
            'result'  => 'RECHAZADO',
            'code'    => '14',
            'message' => 'Tarjeta no válida para el simulador. Use una tarjeta de prueba.'
        ];
    }

    /**
     * Devuelve las tarjetas de prueba disponibles (para mostrar en el frontend).
     */
    public static function getTestCards(): array
    {
        return [
            [
                'number'  => '4111 1111 1111 1111',
                'result'  => 'Aprobada',
                'type'    => 'Visa'
            ],
            [
                'number'  => '5555 5555 5555 4444',
                'result'  => 'Aprobada',
                'type'    => 'Mastercard'
            ],
            [
                'number'  => '4000 0000 0000 0002',
                'result'  => 'Rechazada',
                'type'    => 'Visa'
            ]
        ];
    }
}