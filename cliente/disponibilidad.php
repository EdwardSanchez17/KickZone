<?php
/**
 * KickZone - Disponibilidad en JSON para la pantalla de reserva.
 * GET cancha, fecha [, hora, duracion] -> bloques del día y stock libre de implementos.
 * Solo lectura; requiere sesión de CLIENTE.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/reservas.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!tiene_rol(ROL_CLIENTE)) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$idCancha = filter_var($_GET['cancha'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$cancha = $idCancha ? obtener_cancha_reservable($idCancha) : null;
$fecha = validar_fecha_reserva(get_texto('fecha'));

if (!$cancha || !$fecha) {
    http_response_code(422);
    echo json_encode(['error' => 'Cancha o fecha no válida']);
    exit;
}

$respuesta = ['fecha' => $fecha->format('Y-m-d'), 'bloques' => bloques_disponibles($cancha, $fecha)];

$hora = filter_var($_GET['hora'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => HORA_APERTURA, 'max_range' => HORA_CIERRE - 1]]);
$duracion = filter_var($_GET['duracion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => DURACION_MAX_HORAS]]);
if ($hora !== false && $hora !== null && $duracion !== false && $duracion !== null) {
    $respuesta['implementos'] = array_map(static fn(array $i): array => [
        'id'         => (int) $i['id_implemento'],
        'disponible' => max(0, (int) $i['disponible']),
    ], implementos_para_cancha($cancha, $fecha->format('Y-m-d'), $hora, $duracion));
    $respuesta['tarifa'] = calcular_tarifa($cancha, $hora, $duracion);
}

echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
