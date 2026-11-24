<?php
/**
 * KickZone - Dashboard del Administrador: indicadores clave (KPI)
 * y últimas reservas registradas. Acceso exclusivo: ADMIN.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requiere_rol(ROL_ADMIN);

$pdo = db();

$kpi = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM reservas WHERE fecha_reserva = CURDATE() AND estado <> 'CANCELADA') AS reservas_hoy,
        (SELECT COALESCE(SUM(monto), 0) FROM pagos
          WHERE estado_pago = 'VERIFICADO'
            AND YEAR(fecha_pago) = YEAR(CURDATE()) AND MONTH(fecha_pago) = MONTH(CURDATE())) AS ingresos_mes,
        (SELECT COUNT(*) FROM canchas WHERE estado = 'DISPONIBLE') AS canchas_activas,
        (SELECT COUNT(*) FROM canchas) AS canchas_total,
        (SELECT COUNT(*) FROM pagos WHERE estado_pago = 'PENDIENTE') AS pagos_pendientes,
        (SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE r.nombre_rol = 'CLIENTE') AS clientes"
)->fetch();

$ingresosTurno = $pdo->query(
    "SELECT r.es_tarifa_nocturna, COALESCE(SUM(p.monto), 0) AS total
       FROM pagos p
       JOIN reservas r ON r.id_reserva = p.id_reserva
      WHERE p.estado_pago = 'VERIFICADO'
      GROUP BY r.es_tarifa_nocturna"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$ingresoDia = (float) ($ingresosTurno[0] ?? 0);
$ingresoNoche = (float) ($ingresosTurno[1] ?? 0);

$topCanchas = $pdo->query(
    "SELECT c.nombre, COUNT(r.id_reserva) AS total
       FROM canchas c
       LEFT JOIN reservas r ON r.id_cancha = c.id_cancha AND r.estado <> 'CANCELADA'
      GROUP BY c.id_cancha, c.nombre
      ORDER BY total DESC, c.nombre
      LIMIT 5"
)->fetchAll();
$maxReservas = max(1, (int) ($topCanchas[0]['total'] ?? 1));

$ultimas = $pdo->query(
    "SELECT r.codigo_reserva, r.fecha_reserva, r.hora_inicio, r.total_pagar, r.estado,
            CONCAT(u.nombre, ' ', u.apellido) AS cliente, c.nombre AS cancha,
            (SELECT p.estado_pago FROM pagos p WHERE p.id_reserva = r.id_reserva ORDER BY p.id_pago DESC LIMIT 1) AS estado_pago
       FROM reservas r
       JOIN usuarios u ON u.id_usuario = r.id_cliente
       JOIN canchas c ON c.id_cancha = r.id_cancha
      ORDER BY r.fecha_creacion DESC, r.id_reserva DESC
      LIMIT 8"
)->fetchAll();

$titulo = 'Dashboard';
$activo = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 kz-titulo-seccion mb-0"><i class="fa-solid fa-gauge-high me-2"></i>Panel de administración</h1>
            <p class="text-muted small mb-0">Resumen operativo al <?= e(date('d/m/Y')) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e(url('admin/canchas.php')) ?>" class="btn btn-kz-azul btn-sm"><i class="fa-solid fa-map-location-dot me-1"></i>Canchas</a>
            <a href="<?= e(url('admin/usuarios.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-users-gear me-1"></i>Usuarios</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="kz-kpi d-flex align-items-center gap-3">
                <span class="kz-kpi-icono kz-icono-azul"><i class="fa-solid fa-calendar-day"></i></span>
                <div><div class="kz-kpi-valor"><?= (int) $kpi['reservas_hoy'] ?></div><div class="kz-kpi-label">Reservas hoy</div></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="kz-kpi d-flex align-items-center gap-3">
                <span class="kz-kpi-icono kz-icono-verde"><i class="fa-solid fa-sack-dollar"></i></span>
                <div><div class="kz-kpi-valor">$<?= e(number_format((float) $kpi['ingresos_mes'], 2)) ?></div><div class="kz-kpi-label">Ingresos del mes</div></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="kz-kpi d-flex align-items-center gap-3">
                <span class="kz-kpi-icono kz-icono-ambar"><i class="fa-solid fa-futbol"></i></span>
                <div><div class="kz-kpi-valor"><?= (int) $kpi['canchas_activas'] ?>/<?= (int) $kpi['canchas_total'] ?></div><div class="kz-kpi-label">Canchas disponibles</div></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="kz-kpi d-flex align-items-center gap-3">
                <span class="kz-kpi-icono kz-icono-rojo"><i class="fa-solid fa-hourglass-half"></i></span>
                <div><div class="kz-kpi-valor"><?= (int) $kpi['pagos_pendientes'] ?></div><div class="kz-kpi-label">Pagos pendientes</div></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card kz-card">
                <div class="card-header bg-white fw-bold"><i class="fa-solid fa-clock-rotate-left me-1"></i>Últimas reservas</div>
                <div class="table-responsive">
                    <table class="table table-hover kz-tabla mb-0">
                        <thead>
                        <tr><th>Código</th><th>Cliente</th><th>Cancha</th><th>Fecha</th><th class="text-end">Total</th><th>Pago</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!$ultimas): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Sin reservas registradas.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($ultimas as $r): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($r['codigo_reserva']) ?></td>
                                <td><?= e($r['cliente']) ?></td>
                                <td class="small"><?= e($r['cancha']) ?></td>
                                <td class="small"><?= e(date('d/m', strtotime($r['fecha_reserva']))) ?> <?= e(substr($r['hora_inicio'], 0, 5)) ?></td>
                                <td class="text-end">$<?= e(number_format((float) $r['total_pagar'], 2)) ?></td>
                                <td><span class="badge kz-estado kz-estado-<?= e($r['estado_pago'] ?? 'PENDIENTE') ?>"><?= e($r['estado_pago'] ?? '—') ?></span></td>
                                <td><span class="badge kz-estado kz-estado-<?= e($r['estado']) ?>"><?= e($r['estado']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card kz-card mb-4">
                <div class="card-header bg-white fw-bold"><i class="fa-solid fa-chart-simple me-1"></i>Canchas más reservadas</div>
                <div class="card-body">
                    <?php foreach ($topCanchas as $t): ?>
                        <?php $pct = (int) round(((int) $t['total'] / $maxReservas) * 100 / 10) * 10; ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span><?= e($t['nombre']) ?></span><span class="fw-semibold"><?= (int) $t['total'] ?></span>
                            </div>
                            <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-success w-<?= $pct >= 100 ? 100 : ($pct >= 75 ? 75 : ($pct >= 50 ? 50 : ($pct >= 25 ? 25 : 0))) ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card kz-card">
                <div class="card-header bg-white fw-bold"><i class="fa-solid fa-sun me-1"></i>Ingresos diurnos vs. nocturnos</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="fa-solid fa-sun text-kz-verde me-1"></i>Diurno</span>
                        <span class="kz-tarifa-dia">$<?= e(number_format($ingresoDia, 2)) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span><i class="fa-solid fa-moon text-kz-ambar me-1"></i>Nocturno</span>
                        <span class="kz-tarifa-noche">$<?= e(number_format($ingresoNoche, 2)) ?></span>
                    </div>
                    <div class="small text-muted"><i class="fa-solid fa-users me-1"></i><?= (int) $kpi['clientes'] ?> clientes registrados</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
