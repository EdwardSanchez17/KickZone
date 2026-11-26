<?php
/**
 * KickZone - Gestión operativa de reservas y pagos (ENCARGADO y ADMIN).
 * Confirmar, completar o cancelar reservas y verificar / registrar pagos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/reservas.php';
requiere_rol(ROL_ENCARGADO, ROL_ADMIN);

$pdo = db();
$idOperador = (int) $_SESSION['usuario']['id'];

/** Conserva los filtros actuales al redirigir después de una acción. */
$volver = 'encargado/reservas.php' . (isset($_POST['volver']) && is_string($_POST['volver']) && preg_match('/^\?[\w=&%\-.+]*$/', $_POST['volver']) ? $_POST['volver'] : '');

/* ---------------------------------------------------------------------------
 * Acciones POST (CSRF obligatorio)
 * ------------------------------------------------------------------------- */
if (es_post()) {
    csrf_verificar();

    $accion = post_texto('accion');
    $idReserva = filter_var($_POST['id_reserva'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    try {
        if (!$idReserva) {
            throw new ReservaException('Reserva no válida.');
        }

        switch ($accion) {
            case 'confirmar':
            case 'completar':
            case 'cancelar':
                $mapa = ['confirmar' => 'CONFIRMADA', 'completar' => 'COMPLETADA', 'cancelar' => 'CANCELADA'];
                $codigo = cambiar_estado_reserva($idReserva, $mapa[$accion]);
                flash('success', "Reserva {$codigo} marcada como {$mapa[$accion]}.");
                break;

            case 'verificar_pago':
                $referencia = mb_substr(post_texto('referencia'), 0, 80);
                $upd = $pdo->prepare(
                    "UPDATE pagos p
                       JOIN reservas r ON r.id_reserva = p.id_reserva
                        SET p.estado_pago = 'VERIFICADO', p.registrado_por = :operador,
                            p.referencia_pago = COALESCE(NULLIF(:ref, ''), p.referencia_pago), p.fecha_pago = NOW()
                      WHERE p.id_reserva = :reserva AND p.estado_pago = 'PENDIENTE' AND r.estado <> 'CANCELADA'"
                );
                $upd->execute([':operador' => $idOperador, ':ref' => $referencia, ':reserva' => $idReserva]);
                if (!$upd->rowCount()) {
                    throw new ReservaException('No hay un pago pendiente para verificar en esa reserva.');
                }
                flash('success', 'Pago verificado correctamente.');
                break;

            case 'registrar_pago':
                $metodo = post_texto('metodo_pago');
                if (!array_key_exists($metodo, METODOS_PAGO)) {
                    throw new ReservaException('Método de pago no válido.');
                }
                $stmt = $pdo->prepare(
                    "SELECT r.total_pagar,
                            (SELECT COUNT(*) FROM pagos p WHERE p.id_reserva = r.id_reserva AND p.estado_pago <> 'REEMBOLSADO') AS pagos
                       FROM reservas r WHERE r.id_reserva = :id AND r.estado IN ('PENDIENTE', 'CONFIRMADA', 'COMPLETADA')"
                );
                $stmt->execute([':id' => $idReserva]);
                $r = $stmt->fetch();
                if (!$r || (int) $r['pagos'] > 0) {
                    throw new ReservaException('Esta reserva ya tiene un pago registrado o no admite pagos.');
                }
                $pdo->prepare(
                    "INSERT INTO pagos (id_reserva, metodo_pago, referencia_pago, monto, estado_pago, registrado_por)
                     VALUES (:reserva, :metodo, NULLIF(:ref, ''), :monto, 'VERIFICADO', :operador)"
                )->execute([
                    ':reserva' => $idReserva, ':metodo' => $metodo, ':ref' => mb_substr(post_texto('referencia'), 0, 80),
                    ':monto' => $r['total_pagar'], ':operador' => $idOperador,
                ]);
                flash('success', 'Pago registrado y verificado.');
                break;

            default:
                throw new ReservaException('Acción no reconocida.');
        }
    } catch (ReservaException $ex) {
        flash('danger', $ex->getMessage());
    }

    redirigir($volver);
}

/* ---------------------------------------------------------------------------
 * Listado con filtros
 * ------------------------------------------------------------------------- */
$estados = ['PENDIENTE', 'CONFIRMADA', 'COMPLETADA', 'CANCELADA'];
$filtroEstado = in_array(get_texto('estado'), $estados, true) ? get_texto('estado') : '';
$busqueda = mb_substr(get_texto('q'), 0, 60);
$fecha = get_texto('fecha');
$fechaOk = DateTime::createFromFormat('!Y-m-d', $fecha);
$fecha = ($fechaOk && $fechaOk->format('Y-m-d') === $fecha) ? $fecha : '';
$filtroPago = get_texto('pago') === 'PENDIENTE' ? 'PENDIENTE' : '';

$condiciones = [];
$params = [];
if ($filtroEstado !== '') {
    $condiciones[] = 'r.estado = :estado';
    $params[':estado'] = $filtroEstado;
}
if ($fecha !== '') {
    $condiciones[] = 'r.fecha_reserva = :fecha';
    $params[':fecha'] = $fecha;
}
if ($busqueda !== '') {
    $condiciones[] = "(r.codigo_reserva LIKE :q1 OR CONCAT(u.nombre, ' ', u.apellido) LIKE :q2)";
    $params[':q1'] = '%' . $busqueda . '%';
    $params[':q2'] = '%' . $busqueda . '%';
}
if ($filtroPago !== '') {
    $condiciones[] = "EXISTS (SELECT 1 FROM pagos px WHERE px.id_reserva = r.id_reserva AND px.estado_pago = 'PENDIENTE')";
}

$sql = "SELECT r.id_reserva, r.codigo_reserva, r.fecha_reserva, r.hora_inicio, r.hora_fin, r.es_tarifa_nocturna,
               r.total_pagar, r.estado, CONCAT(u.nombre, ' ', u.apellido) AS cliente, u.telefono, c.nombre AS cancha,
               p.metodo_pago, p.estado_pago, p.referencia_pago
          FROM reservas r
          JOIN usuarios u ON u.id_usuario = r.id_cliente
          JOIN canchas c ON c.id_cancha = r.id_cancha
          LEFT JOIN pagos p ON p.id_pago = (SELECT MAX(p2.id_pago) FROM pagos p2 WHERE p2.id_reserva = r.id_reserva)";
if ($condiciones) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}
$sql .= ' ORDER BY r.fecha_reserva DESC, r.hora_inicio DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservas = $stmt->fetchAll();

$qs = $_SERVER['QUERY_STRING'] ?? '';
$volverQs = $qs !== '' ? '?' . $qs : '';

$titulo = 'Reservas y pagos';
$activo = 'reservas_gestion';
require __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-lg-5 py-4">
    <h1 class="h3 kz-titulo-seccion mb-0"><i class="fa-solid fa-list-check me-2"></i>Reservas y pagos</h1>
    <p class="text-muted small">Confirma reservas, verifica pagos y marca los partidos como completados.</p>

    <form method="get" action="<?= e(url('encargado/reservas.php')) ?>" class="card kz-card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="q" class="form-label">Buscar</label>
                <input type="search" class="form-control" id="q" name="q" maxlength="60" placeholder="Código o cliente…" value="<?= e($busqueda) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="fecha" class="form-label">Fecha</label>
                <input type="date" class="form-control" id="fecha" name="fecha" value="<?= e($fecha) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="estado" class="form-label">Estado</label>
                <select class="form-select" id="estado" name="estado" data-autosubmit>
                    <option value="">Todos</option>
                    <?php foreach ($estados as $est): ?>
                        <option value="<?= e($est) ?>" <?= $filtroEstado === $est ? 'selected' : '' ?>><?= e($est) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="pago" class="form-label">Pago</label>
                <select class="form-select" id="pago" name="pago" data-autosubmit>
                    <option value="">Todos</option>
                    <option value="PENDIENTE" <?= $filtroPago ? 'selected' : '' ?>>Pendientes</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-kz-azul flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i></button>
                <a href="<?= e(url('encargado/reservas.php')) ?>" class="btn btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-eraser"></i></a>
            </div>
        </div>
    </form>

    <div class="table-responsive card kz-card">
        <table class="table kz-tabla mb-0 align-middle">
            <thead>
            <tr><th>Código</th><th>Cliente</th><th>Cancha</th><th>Fecha y hora</th><th class="text-end">Total</th><th>Pago</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php if (!$reservas): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No hay reservas con esos filtros.</td></tr>
            <?php endif; ?>
            <?php foreach ($reservas as $r): ?>
                <?php $id = (int) $r['id_reserva']; ?>
                <tr>
                    <td class="fw-semibold">
                        <a href="<?= e(url('cliente/comprobante.php?codigo=' . urlencode($r['codigo_reserva']))) ?>"><?= e($r['codigo_reserva']) ?></a>
                    </td>
                    <td><?= e($r['cliente']) ?><div class="small text-muted"><?= e($r['telefono']) ?></div></td>
                    <td class="small"><?= e($r['cancha']) ?></td>
                    <td class="small text-nowrap">
                        <?= e(date('d/m/Y', strtotime($r['fecha_reserva']))) ?><br>
                        <?= e(substr($r['hora_inicio'], 0, 5)) ?>–<?= e(substr($r['hora_fin'], 0, 5)) ?>
                        <?php if ((int) $r['es_tarifa_nocturna'] === 1): ?><i class="fa-solid fa-moon text-kz-ambar ms-1" title="Tarifa nocturna"></i><?php endif; ?>
                    </td>
                    <td class="text-end">$<?= e(number_format((float) $r['total_pagar'], 2)) ?></td>
                    <td class="small">
                        <?php if ($r['estado_pago']): ?>
                            <span class="badge kz-estado kz-estado-<?= e($r['estado_pago']) ?>"><?= e($r['estado_pago']) ?></span>
                            <div class="text-muted"><?= e(METODOS_PAGO[$r['metodo_pago']] ?? $r['metodo_pago']) ?><?= $r['referencia_pago'] ? ' · ' . e($r['referencia_pago']) : '' ?></div>
                        <?php else: ?>
                            <span class="text-muted">Sin pago</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge kz-estado kz-estado-<?= e($r['estado']) ?>"><?= e($r['estado']) ?></span></td>
                    <td class="text-end">
                        <div class="d-flex flex-wrap justify-content-end gap-1">
                            <?php if ($r['estado_pago'] === 'PENDIENTE' && $r['estado'] !== 'CANCELADA'): ?>
                                <form method="post" action="<?= e(url('encargado/reservas.php')) ?>" class="d-flex gap-1">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="volver" value="<?= e($volverQs) ?>">
                                    <input type="hidden" name="accion" value="verificar_pago">
                                    <input type="hidden" name="id_reserva" value="<?= $id ?>">
                                    <input type="text" name="referencia" class="form-control form-control-sm kz-input-ref" maxlength="80" placeholder="Ref." aria-label="Referencia de pago">
                                    <button class="btn btn-sm btn-outline-success text-nowrap" title="Verificar pago"><i class="fa-solid fa-money-check-dollar me-1"></i>Verificar</button>
                                </form>
                            <?php elseif (!$r['estado_pago'] && $r['estado'] !== 'CANCELADA'): ?>
                                <form method="post" action="<?= e(url('encargado/reservas.php')) ?>" class="d-flex gap-1">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="volver" value="<?= e($volverQs) ?>">
                                    <input type="hidden" name="accion" value="registrar_pago">
                                    <input type="hidden" name="id_reserva" value="<?= $id ?>">
                                    <select name="metodo_pago" class="form-select form-select-sm" aria-label="Método de pago">
                                        <?php foreach (METODOS_PAGO as $clave => $nombre): ?>
                                            <option value="<?= e($clave) ?>"><?= e($nombre) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-success text-nowrap"><i class="fa-solid fa-cash-register me-1"></i>Cobrar</button>
                                </form>
                            <?php endif; ?>

                            <?php
                            $acciones = match ($r['estado']) {
                                'PENDIENTE'  => [['confirmar', 'btn-outline-primary', 'fa-circle-check', 'Confirmar'], ['cancelar', 'btn-outline-danger', 'fa-ban', 'Cancelar']],
                                'CONFIRMADA' => [['completar', 'btn-outline-primary', 'fa-flag-checkered', 'Completar'], ['cancelar', 'btn-outline-danger', 'fa-ban', 'Cancelar']],
                                default      => [],
                            };
                            foreach ($acciones as [$acc, $clase, $icono, $texto]): ?>
                                <form method="post" action="<?= e(url('encargado/reservas.php')) ?>"
                                      <?= $acc === 'cancelar' ? 'data-confirmar="¿Cancelar la reserva ' . e($r['codigo_reserva']) . '?"' : '' ?>>
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="volver" value="<?= e($volverQs) ?>">
                                    <input type="hidden" name="accion" value="<?= e($acc) ?>">
                                    <input type="hidden" name="id_reserva" value="<?= $id ?>">
                                    <button class="btn btn-sm <?= $clase ?> text-nowrap"><i class="fa-solid <?= $icono ?> me-1"></i><?= e($texto) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
