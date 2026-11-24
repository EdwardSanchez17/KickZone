<?php
/**
 * KickZone - Agenda del día para el Encargado de sede.
 * Acceso: ENCARGADO y ADMIN. Muestra las reservas de la fecha elegida
 * y el estado de cada cancha. (Confirmar/verificar pagos llega en la Entrega 3.)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requiere_rol(ROL_ENCARGADO, ROL_ADMIN);

$pdo = db();

$fecha = get_texto('fecha');
$fechaObj = DateTime::createFromFormat('!Y-m-d', $fecha);
if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    $fecha = date('Y-m-d');
}

$stmt = $pdo->prepare(
    "SELECT r.codigo_reserva, r.hora_inicio, r.hora_fin, r.es_tarifa_nocturna, r.total_pagar, r.estado,
            r.notas_cliente, CONCAT(u.nombre, ' ', u.apellido) AS cliente, u.telefono,
            c.nombre AS cancha, d.nombre AS deporte,
            (SELECT p.estado_pago FROM pagos p WHERE p.id_reserva = r.id_reserva ORDER BY p.id_pago DESC LIMIT 1) AS estado_pago,
            (SELECT GROUP_CONCAT(CONCAT(ri.cantidad, 'x ', i.nombre) SEPARATOR ', ')
               FROM reserva_implementos ri JOIN implementos i ON i.id_implemento = ri.id_implemento
              WHERE ri.id_reserva = r.id_reserva) AS implementos
       FROM reservas r
       JOIN usuarios u ON u.id_usuario = r.id_cliente
       JOIN canchas c ON c.id_cancha = r.id_cancha
       JOIN deportes d ON d.id_deporte = c.id_deporte
      WHERE r.fecha_reserva = :fecha
      ORDER BY r.hora_inicio, c.nombre"
);
$stmt->execute([':fecha' => $fecha]);
$agenda = $stmt->fetchAll();

$conteo = ['PENDIENTE' => 0, 'CONFIRMADA' => 0, 'COMPLETADA' => 0, 'CANCELADA' => 0];
foreach ($agenda as $r) {
    $conteo[$r['estado']]++;
}

$canchas = $pdo->query(
    'SELECT c.nombre, c.estado, d.nombre AS deporte
       FROM canchas c JOIN deportes d ON d.id_deporte = c.id_deporte
      ORDER BY d.id_deporte, c.nombre'
)->fetchAll();

$titulo = 'Agenda del día';
$activo = 'agenda';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="h3 kz-titulo-seccion mb-0"><i class="fa-solid fa-clipboard-list me-2"></i>Agenda operativa</h1>
            <p class="text-muted small mb-0">Reservas del <?= e(date('d/m/Y', strtotime($fecha))) ?></p>
        </div>
        <form method="get" action="<?= e(url('encargado/dashboard.php')) ?>" class="d-flex gap-2">
            <input type="date" name="fecha" class="form-control" value="<?= e($fecha) ?>" aria-label="Fecha de la agenda">
            <button class="btn btn-kz-azul"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $tarjetas = [
            'PENDIENTE'  => ['kz-icono-ambar', 'fa-hourglass-half', 'Pendientes'],
            'CONFIRMADA' => ['kz-icono-verde', 'fa-circle-check', 'Confirmadas'],
            'COMPLETADA' => ['kz-icono-azul', 'fa-flag-checkered', 'Completadas'],
            'CANCELADA'  => ['kz-icono-rojo', 'fa-ban', 'Canceladas'],
        ];
        foreach ($tarjetas as $estado => [$clase, $icono, $etiqueta]): ?>
            <div class="col-6 col-lg-3">
                <div class="kz-kpi d-flex align-items-center gap-3">
                    <span class="kz-kpi-icono <?= $clase ?>"><i class="fa-solid <?= $icono ?>"></i></span>
                    <div><div class="kz-kpi-valor"><?= $conteo[$estado] ?></div><div class="kz-kpi-label"><?= $etiqueta ?></div></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-9">
            <div class="table-responsive card kz-card">
                <table class="table kz-tabla mb-0">
                    <thead>
                    <tr><th>Hora</th><th>Cancha</th><th>Cliente</th><th>Implementos</th><th class="text-end">Total</th><th>Pago</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$agenda): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay reservas para esta fecha.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($agenda as $r): ?>
                        <tr>
                            <td class="text-nowrap fw-semibold">
                                <?= e(substr($r['hora_inicio'], 0, 5)) ?>–<?= e(substr($r['hora_fin'], 0, 5)) ?>
                                <?php if ((int) $r['es_tarifa_nocturna'] === 1): ?><i class="fa-solid fa-moon text-kz-ambar ms-1" title="Tarifa nocturna"></i><?php endif; ?>
                                <div class="small text-muted"><?= e($r['codigo_reserva']) ?></div>
                            </td>
                            <td><?= e($r['cancha']) ?><div class="small text-muted"><?= e($r['deporte']) ?></div></td>
                            <td><?= e($r['cliente']) ?><div class="small text-muted"><?= e($r['telefono']) ?></div></td>
                            <td class="small"><?= e($r['implementos'] ?? '—') ?></td>
                            <td class="text-end">$<?= e(number_format((float) $r['total_pagar'], 2)) ?></td>
                            <td><span class="badge kz-estado kz-estado-<?= e($r['estado_pago'] ?? 'PENDIENTE') ?>"><?= e($r['estado_pago'] ?? 'SIN PAGO') ?></span></td>
                            <td><span class="badge kz-estado kz-estado-<?= e($r['estado']) ?>"><?= e($r['estado']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card kz-card">
                <div class="card-header bg-white fw-bold"><i class="fa-solid fa-map-location-dot me-1"></i>Estado de canchas</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($canchas as $c): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center small">
                            <span><?= e($c['nombre']) ?></span>
                            <span class="badge kz-estado kz-estado-<?= e($c['estado']) ?>"><?= e($c['estado']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
