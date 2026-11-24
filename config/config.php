<?php
/**
 * KickZone - Configuración general de la aplicación.
 * Ajustar las credenciales de MySQL según el entorno local (XAMPP por defecto).
 */

declare(strict_types=1);

// --- Base de datos ---
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'kickzone_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- Aplicación ---
define('APP_NOMBRE', 'KickZone');
define('APP_ZONA_HORARIA', 'America/Panama');
define('APP_DEBUG', false); // true solo en desarrollo: muestra errores detallados

// --- Seguridad ---
define('SESION_NOMBRE', 'KZSESSID');
define('SESION_INACTIVIDAD', 1800);   // 30 minutos sin actividad cierran la sesión
define('LOGIN_MAX_INTENTOS', 5);      // intentos fallidos antes del bloqueo
define('LOGIN_MINUTOS_BLOQUEO', 15);  // duración del bloqueo temporal

// --- Roles (coinciden con la tabla roles) ---
define('ROL_ADMIN', 'ADMIN');
define('ROL_ENCARGADO', 'ENCARGADO');
define('ROL_CLIENTE', 'CLIENTE');
define('ID_ROL_CLIENTE', 3);

date_default_timezone_set(APP_ZONA_HORARIA);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

/**
 * URL base del proyecto calculada a partir de la carpeta real,
 * así funciona igual en http://localhost/KickZone/ o en otra subcarpeta.
 */
function calcular_base_url(): string
{
    $raiz = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));

    if ($docRoot !== '' && str_starts_with($raiz, $docRoot)) {
        $ruta = substr($raiz, strlen($docRoot));
        return rtrim($ruta, '/') . '/';
    }
    return '/KickZone/';
}

define('BASE_URL', calcular_base_url());
