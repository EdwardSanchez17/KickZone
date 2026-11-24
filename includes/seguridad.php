<?php
/**
 * KickZone - Núcleo de seguridad.
 *  - Cabeceras HTTP de seguridad (CSP, X-Frame-Options, X-Content-Type-Options...)
 *  - Sesión endurecida (cookies HttpOnly/SameSite, expiración por inactividad)
 *  - Tokens CSRF
 *  - Escape de salida contra XSS
 *  - Autenticación y control de acceso por rol (del lado del servidor)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/* ---------------------------------------------------------------------------
 * Cabeceras de seguridad
 * ------------------------------------------------------------------------- */
function enviar_cabeceras_seguridad(): void
{
    if (headers_sent()) {
        return;
    }

    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' https://cdn.jsdelivr.net",
        "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
        "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com",
        "img-src 'self' data:",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ]);

    header('Content-Security-Policy: ' . $csp);
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header_remove('X-Powered-By');
}

/* ---------------------------------------------------------------------------
 * Sesión
 * ------------------------------------------------------------------------- */
function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $esHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(SESION_NOMBRE);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL,
        'secure'   => $esHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    // Expiración por inactividad
    $ahora = time();
    if (isset($_SESSION['ultima_actividad']) && ($ahora - $_SESSION['ultima_actividad']) > SESION_INACTIVIDAD) {
        cerrar_sesion();
        session_start();
        flash('warning', 'Tu sesión expiró por inactividad. Inicia sesión nuevamente.');
    }
    $_SESSION['ultima_actividad'] = $ahora;
}

/**
 * Cierra la sesión por completo: limpia datos, invalida la cookie
 * y destruye la sesión en el servidor.
 */
function cerrar_sesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = [];
    session_regenerate_id(true); // el ID anterior queda inutilizable

    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'],
    ]);
    session_destroy();
}

/* ---------------------------------------------------------------------------
 * Escape de salida (XSS)
 * ------------------------------------------------------------------------- */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Valida el token CSRF de una petición POST. Si no coincide, corta la ejecución.
 */
function csrf_verificar(): void
{
    $enviado = $_POST['csrf_token'] ?? '';
    $esperado = $_SESSION['csrf_token'] ?? '';

    if (!is_string($enviado) || $esperado === '' || !hash_equals($esperado, $enviado)) {
        error_log('[KickZone] Token CSRF inválido desde ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ' en ' . ($_SERVER['REQUEST_URI'] ?? '?'));
        http_response_code(403);
        exit('Solicitud rechazada: token de seguridad (CSRF) inválido o expirado. Recarga la página e inténtalo de nuevo.');
    }
}

function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* ---------------------------------------------------------------------------
 * Mensajes flash y redirecciones
 * ------------------------------------------------------------------------- */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function obtener_flash(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

function url(string $ruta = ''): string
{
    return BASE_URL . ltrim($ruta, '/');
}

function redirigir(string $ruta): never
{
    header('Location: ' . url($ruta));
    exit;
}

/* ---------------------------------------------------------------------------
 * Autenticación y autorización
 * ------------------------------------------------------------------------- */
function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function esta_autenticado(): bool
{
    return isset($_SESSION['usuario']['id']);
}

function tiene_rol(string ...$roles): bool
{
    return esta_autenticado() && in_array($_SESSION['usuario']['rol'], $roles, true);
}

/** Página de inicio que corresponde a cada rol. */
function ruta_inicio_rol(string $rol): string
{
    return match ($rol) {
        ROL_ADMIN     => 'admin/dashboard.php',
        ROL_ENCARGADO => 'encargado/dashboard.php',
        default       => 'cliente/panel.php',
    };
}

/**
 * Exige sesión iniciada. Además vuelve a consultar la BD para que un usuario
 * desactivado o con rol cambiado por el administrador pierda el acceso al instante.
 */
function requiere_login(): void
{
    if (!esta_autenticado()) {
        flash('warning', 'Debes iniciar sesión para continuar.');
        redirigir('login.php');
    }

    $stmt = db()->prepare(
        'SELECT u.estado, r.nombre_rol
           FROM usuarios u
           JOIN roles r ON r.id_rol = u.id_rol
          WHERE u.id_usuario = :id'
    );
    $stmt->execute([':id' => $_SESSION['usuario']['id']]);
    $fila = $stmt->fetch();

    if (!$fila || $fila['estado'] !== 'ACTIVO') {
        cerrar_sesion();
        iniciar_sesion_segura();
        flash('danger', 'Tu cuenta no está activa. Contacta al administrador.');
        redirigir('login.php');
    }

    $_SESSION['usuario']['rol'] = $fila['nombre_rol'];
}

/**
 * Exige uno de los roles indicados. El control se hace en el servidor:
 * ocultar un botón no es suficiente.
 */
function requiere_rol(string ...$roles): void
{
    requiere_login();

    if (!tiene_rol(...$roles)) {
        error_log(sprintf(
            '[KickZone] Acceso denegado: usuario %d (%s) a %s',
            $_SESSION['usuario']['id'],
            $_SESSION['usuario']['rol'],
            $_SERVER['REQUEST_URI'] ?? '?'
        ));
        http_response_code(403);
        require __DIR__ . '/../acceso_denegado.php';
        exit;
    }
}

/* ---------------------------------------------------------------------------
 * Validaciones comunes
 * ------------------------------------------------------------------------- */

/**
 * Política mínima de contraseña: 8+ caracteres, mayúscula, minúscula, número y símbolo.
 * Devuelve la lista de reglas que no se cumplen (vacía si es válida).
 */
function validar_politica_password(string $password): array
{
    $errores = [];
    if (mb_strlen($password) < 8) {
        $errores[] = 'Debe tener al menos 8 caracteres.';
    }
    if (mb_strlen($password) > 72) {
        $errores[] = 'No puede superar 72 caracteres.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errores[] = 'Debe incluir al menos una letra mayúscula.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errores[] = 'Debe incluir al menos una letra minúscula.';
    }
    if (!preg_match('/\d/', $password)) {
        $errores[] = 'Debe incluir al menos un número.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errores[] = 'Debe incluir al menos un carácter especial (ej. * ! # $).';
    }
    return $errores;
}

/** Lee un campo de texto de $_POST recortado y como string seguro. */
function post_texto(string $campo): string
{
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

/** Lee un campo de texto de $_GET recortado y como string seguro. */
function get_texto(string $campo): string
{
    $valor = $_GET[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
}
