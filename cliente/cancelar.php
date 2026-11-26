<?php
/**
 * KickZone - Cancelación de una reserva por el propio cliente.
 * Solo POST + CSRF. Se verifica que la reserva pertenezca al cliente en sesión
 * y que falten al menos HORAS_MIN_CANCELACION horas para el inicio.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/reservas.php';
requiere_rol(ROL_CLIENTE);

if (!es_post()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido.');
}
csrf_verificar();

$idReserva = filter_var($_POST['id_reserva'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$idReserva) {
    flash('danger', 'Reserva no válida.');
} else {
    try {
        $codigo = cambiar_estado_reserva($idReserva, 'CANCELADA', (int) $_SESSION['usuario']['id']);
        flash('success', 'La reserva ' . $codigo . ' fue cancelada.');
    } catch (ReservaException $ex) {
        flash('danger', $ex->getMessage());
    }
}

redirigir('cliente/panel.php');
