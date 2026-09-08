<?php
declare(strict_types=1);

/**
 * Cargador de variables de entorno desde un archivo .env
 * (sin dependencias externas).
 *
 * Las variables ya definidas en el entorno real NO se sobrescriben,
 * de modo que en producción pueden inyectarse por getenv/putenv.
 */

function load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Ignorar comentarios y líneas vacías
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Solo pares clave=valor
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);

        if ($key === '') {
            continue;
        }

        $value = trim($value);

        // Ya está definida en el entorno real → no sobrescribir
        if (getenv($key) !== false) {
            continue;
        }

        // Quitar comillas simples
        if (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") {
            $value = substr($value, 1, -1);
        }

        putenv("{$key}={$value}");
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }
}
