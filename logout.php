<?php
/**
 * KickZone - Cierre de sesión.
 * Solo acepta POST con token CSRF (un enlace o imagen externa no puede cerrar la sesión).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (!es_post()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido.');
}

csrf_verificar();

cerrar_sesion();          // limpia datos, regenera y destruye la sesión
iniciar_sesion_segura();  // sesión nueva y vacía solo para el mensaje flash
session_regenerate_id(true);

flash('info', 'Cerraste sesión correctamente. ¡Hasta pronto!');
redirigir('login.php');
