<?php
/**
 * KickZone - CRUD de canchas (listado, búsqueda, filtros y eliminación).
 * Acceso exclusivo: ADMIN.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requiere_rol(ROL_ADMIN);

$pdo = db();
const ESTADOS_CANCHA = ['DISPONIBLE', 'MANTENIMIENTO', 'INACTIVA'];

/* ---------------------------------------------------------------------------
 * Acciones POST: eliminar / cambiar estado (todas con token CSRF)
 * ------------------------------------------------------------------------- */
if (es_post()) {
    csrf_verificar();

    $accion = post_texto('accion');
    $idCancha = filter_var($_POST['id_cancha'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($idCancha === false || $idCancha === null) {
        flash('danger', 'Cancha no válida.');
        redirigir('admin/canchas.php');
    }

    if ($accion === 'eliminar') {
        // Con reservas asociadas no se borra: se perdería el historial y los pagos.
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM reservas WHERE id_cancha = :id');
        $stmt->execute([':id' => $idCancha]);

        if ((int) $stmt->fetchColumn() > 0) {
            flash('warning', 'La cancha tiene reservas registradas y no puede eliminarse. Cámbiala a INACTIVA para ocultarla del catálogo.');
        } else {
            $del = $pdo->prepare('DELETE FROM canchas WHERE id_cancha = :id');
            $del->execute([':id' => $idCancha]);
            flash($del->rowCount() ? 'success' : 'danger', $del->rowCount() ? 'Cancha eliminada correctamente.' : 'La cancha no existe.');
        }
    } elseif ($accion === 'estado') {
        $nuevoEstado = post_texto('estado');
        if (!in_array($nuevoEstado, ESTADOS_CANCHA, true)) {
            flash('danger', 'Estado no válido.');
        } else {
            $upd = $pdo->prepare('UPDATE canchas SET estado = :estado WHERE id_cancha = :id');
            $upd->execute([':estado' => $nuevoEstado, ':id' => $idCancha]);
            flash('success', 'Estado actualizado a ' . $nuevoEstado . '.');
        }
    } else {
        flash('danger', 'Acción no reconocida.');
    }

    redirigir('admin/canchas.php');
}

/* ---------------------------------------------------------------------------
 * Listado con búsqueda y filtros (parámetros siempre enlazados, nunca concatenados)
 * ------------------------------------------------------------------------- */
$busqueda = mb_substr(get_texto('q'), 0, 60);
$filtroDeporte = filter_var($_GET['deporte'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$filtroEstado = in_array(get_texto('estado'), ESTADOS_CANCHA, true) ? get_texto('estado') : '';

$condiciones = [];
$parametros = [];

if ($busqueda !== '') {
    $condiciones[] = '(c.nombre LIKE :q1 OR c.superficie LIKE :q2)';
    $parametros[':q1'] = '%' . $busqueda . '%';
    $parametros[':q2'] = '%' . $busqueda . '%';
}
if ($filtroDeporte) {
    $condiciones[] = 'c.id_deporte = :deporte';
    $parametros[':deporte'] = $filtroDeporte;
}
if ($filtroEstado !== '') {
    $condiciones[] = 'c.estado = :estado';
    $parametros[':estado'] = $filtroEstado;
}

$sql = 'SELECT c.id_cancha, c.nombre, c.superficie, c.capacidad_jugadores, c.tarifa_diurna,
               c.tarifa_nocturna, c.hora_inicio_nocturna, c.estado, d.nombre AS deporte,
               (SELECT COUNT(*) FROM reservas r WHERE r.id_cancha = c.id_cancha) AS total_reservas
          FROM canchas c
          JOIN deportes d ON d.id_deporte = c.id_deporte';
if ($condiciones) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones); // solo fragmentos fijos con marcadores
}
$sql .= ' ORDER BY d.nombre, c.nombre';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$canchas = $stmt->fetchAll();

$deportes = $pdo->query('SELECT id_deporte, nombre FROM deportes ORDER BY nombre')->fetchAll();

$titulo = 'Gestión de canchas';
$activo = 'admin_canchas';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 kz-titulo-seccion mb-0"><i class="fa-solid fa-map-location-dot me-2"></i>Gestión de canchas</h1>
            <p class="text-muted small mb-0">Crea, edita, filtra y administra el estado de las canchas del centro.</p>
        </div>
        <a href="<?= e(url('admin/cancha_form.php')) ?>" class="btn btn-kz-verde">
            <i class="fa-solid fa-plus me-1"></i>Nueva cancha
        </a>
    </div>

    <form method="get" action="<?= e(url('admin/canchas.php')) ?>" class="card kz-card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label for="q" class="form-label">Buscar</label>
                <input type="search" class="form-control" id="q" name="q" maxlength="60"
                       placeholder="Nombre o superficie…" value="<?= e($busqueda) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label for="deporte" class="form-label">Deporte</label>
                <select class="form-select" id="deporte" name="deporte" data-autosubmit>
                    <option value="">Todos</option>
                    <?php foreach ($deportes as $d): ?>
                        <option value="<?= (int) $d['id_deporte'] ?>" <?= $filtroDeporte === (int) $d['id_deporte'] ? 'selected' : '' ?>>
                            <?= e($d['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="estado" class="form-label">Estado</label>
                <select class="form-select" id="estado" name="estado" data-autosubmit>
                    <option value="">Todos</option>
                    <?php foreach (ESTADOS_CANCHA as $est): ?>
                        <option value="<?= e($est) ?>" <?= $filtroEstado === $est ? 'selected' : '' ?>><?= e($est) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-kz-azul flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i></button>
                <a href="<?= e(url('admin/canchas.php')) ?>" class="btn btn-outline-secondary" title="Limpiar filtros">
                    <i class="fa-solid fa-eraser"></i>
                </a>
            </div>
        </div>
    </form>

    <p class="small text-muted mb-2"><?= count($canchas) ?> cancha(s) encontradas</p>

    <div class="table-responsive card kz-card">
        <table class="table table-hover kz-tabla mb-0">
            <thead>
            <tr>
                <th>Cancha</th>
                <th>Deporte</th>
                <th class="text-center">Capacidad</th>
                <th class="text-end">Día</th>
                <th class="text-end">Noche</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$canchas): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No hay canchas que coincidan con la búsqueda.</td></tr>
            <?php endif; ?>
            <?php foreach ($canchas as $c): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($c['nombre']) ?></div>
                        <div class="small text-muted"><?= e($c['superficie']) ?> · <?= (int) $c['total_reservas'] ?> reserva(s)</div>
                    </td>
                    <td><?= e($c['deporte']) ?></td>
                    <td class="text-center"><?= (int) $c['capacidad_jugadores'] ?></td>
                    <td class="text-end kz-tarifa-dia">$<?= e(number_format((float) $c['tarifa_diurna'], 2)) ?></td>
                    <td class="text-end kz-tarifa-noche">
                        $<?= e(number_format((float) $c['tarifa_nocturna'], 2)) ?>
                        <div class="small text-muted">desde <?= e(substr($c['hora_inicio_nocturna'], 0, 5)) ?></div>
                    </td>
                    <td>
                        <form method="post" action="<?= e(url('admin/canchas.php')) ?>" class="m-0">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="estado">
                            <input type="hidden" name="id_cancha" value="<?= (int) $c['id_cancha'] ?>">
                            <select name="estado" class="form-select form-select-sm" data-autosubmit
                                    aria-label="Estado de <?= e($c['nombre']) ?>">
                                <?php foreach (ESTADOS_CANCHA as $est): ?>
                                    <option value="<?= e($est) ?>" <?= $c['estado'] === $est ? 'selected' : '' ?>><?= e($est) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('admin/cancha_form.php?id=' . (int) $c['id_cancha'])) ?>"
                           class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" action="<?= e(url('admin/canchas.php')) ?>" class="d-inline"
                              data-confirmar="¿Eliminar la cancha «<?= e($c['nombre']) ?>»? Esta acción no se puede deshacer.">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id_cancha" value="<?= (int) $c['id_cancha'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
