<?php
declare(strict_types=1);

/**
 * Helpers de request/HTTP para PetLandia
 */

/**
 * Obtiene los datos de entrada de la petición (JSON o form-data).
 * Detecta automáticamente el Content-Type.
 */
function json_input(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    return $_POST;
}

/**
 * Envía los headers CORS y detiene la ejecución si es una petición OPTIONS.
 * Usado en los puntos de entrada de la API.
 *
 * @param array $allowedMethods Métodos HTTP permitidos.
 */
function cors(array $allowedMethods = ['GET', 'POST', 'OPTIONS']): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
