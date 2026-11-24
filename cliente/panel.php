<?php
/**
 * KickZone - Panel del cliente: historial de sus propias reservas.
 * Acceso: CLIENTE. La consulta filtra SIEMPRE por el id de la sesión,
 * así un cliente nunca puede ver reservas de otro.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requiere_rol(ROL_CLIENTE);

$pdo = db();
$idCliente = (int) $_SESSION['usuario']['id'];

$stmt = $pdo->prepare(
    "SELECT r.codigo_reserva, r.fecha_reserva, r.hora_inicio, r.hora_fin, r.es_tarifa_nocturna,
            r.total_pagar, r.estado, c.nombre AS cancha, d.nombre AS deporte,
            (SELECT p.estado_pago FROM pagos p WHERE p.id_reserva = r.id_reserva ORDER BY p.id_pago DESC LIMIT 1) AS estado_pago
       FROM reservas r
       JOIN canchas c ON c.id_cancha = r.id_cancha
       JOIN deportes d ON d.id_deporte = c.id_deporte
      WHERE r.id_cliente = :cliente
      ORDER BY r.fecha_reserva DESC, r.hora_inicio DESC"
);
$stmt->execute([':cliente' => $idCliente]);
$reservas = $stmt->fetchAll();

$proximas = array_filter($reservas, static fn($r) => in_array($r['estado'], ['PENDIENTE', 'CONFIRMADA'], true));
$completadas = array_filter($reservas, static fn($r) => $r['estado'] === 'COMPLETADA');

$titulo = 'Mis reservas';
$activo = 'mis_reservas';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <h1 class="h3 kz-titulo-seccion mb-1"><i class="fa-solid fa-calendar-days me-2"></i>Mis reservas</h1>
    <p class="text-muted">Hola, <?= e($_SESSION['usuario']['nombre']) ?>. Aquí tienes el historial de tus reservas.</p>

    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="kz-kpi d-flex align-items-center gap-3">
                <span class="kz-kpi-icono kz-icono-azul"><i class="fa-solid fa-list"></i></span>
                <div><div class="kz-kpi-valor"><?= count($reservas) ?></div><div class="kz-kpi-label">Total</div></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="kz-kpi d-flex align-items-center gap-3">
                <span class="kz-kpi-icono kz-icono-ambar"><i class="fa-solid fa-hourglass-half"></i></span>
                <div><div class="kz-kpi-valor"><?= count($proximas) ?></div><div class="kz-kpi-label">Próximas</div></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="kz-kpi d-flex align-items-center gap-3">
                <span class="kz-kpi-icono kz-icono-verde"><i class="fa-solid fa-trophy"></i></span>
                <div><div class="kz-kpi-valor"><?= count($completadas) ?></div><div class="kz-kpi-label">Jugadas</div></div>
            </div>
        </div>
    </div>

    <div class="alert alert-info small">
        <i class="fa-solid fa-circle-info me-1"></i>
        El módulo de nuevas reservas con tarifa nocturna e implementos se habilita en la siguiente entrega.
    </div>

    <div class="table-responsive card kz-card">
        <table class="table kz-tabla mb-0">
            <thead>
            <tr>
                <th>Código</th>
                <th>Cancha</th>
                <th>Fecha</th>
                <th>Horario</th>
                <th class="text-end">Total</th>
                <th>Pago</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$reservas): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aún no tienes reservas. <a href="<?= e(url('index.php#canchas')) ?>">Explora las canchas</a>.</td></tr>
            <?php endif; ?>
            <?php foreach ($reservas as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['codigo_reserva']) ?></td>
                    <td><?= e($r['cancha']) ?><div class="small text-muted"><?= e($r['deporte']) ?></div></td>
                    <td><?= e(date('d/m/Y', strtotime($r['fecha_reserva']))) ?></td>
                    <td>
                        <?= e(substr($r['hora_inicio'], 0, 5)) ?>–<?= e(substr($r['hora_fin'], 0, 5)) ?>
                        <?php if ((int) $r['es_tarifa_nocturna'] === 1): ?>
                            <i class="fa-solid fa-moon text-kz-ambar ms-1" title="Tarifa nocturna"></i>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">$<?= e(number_format((float) $r['total_pagar'], 2)) ?></td>
                    <td>
                        <?php if ($r['estado_pago']): ?>
                            <span class="badge kz-estado kz-estado-<?= e($r['estado_pago']) ?>"><?= e($r['estado_pago']) ?></span>
                        <?php else: ?>
                            <span class="small text-muted">Sin registrar</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge kz-estado kz-estado-<?= e($r['estado']) ?>"><?= e($r['estado']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
