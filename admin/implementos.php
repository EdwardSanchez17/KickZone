<?php
/**
 * KickZone - CRUD de implementos deportivos (listado, búsqueda, filtros, estado y eliminación).
 * Acceso exclusivo: ADMIN.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requiere_rol(ROL_ADMIN);

$pdo = db();

/* ---------------------------------------------------------------------------
 * Acciones POST (CSRF obligatorio)
 * ------------------------------------------------------------------------- */
if (es_post()) {
    csrf_verificar();

    $accion = post_texto('accion');
    $id = filter_var($_POST['id_implemento'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!$id) {
        flash('danger', 'Implemento no válido.');
    } elseif ($accion === 'eliminar') {
        $uso = $pdo->prepare('SELECT COUNT(*) FROM reserva_implementos WHERE id_implemento = :id');
        $uso->execute([':id' => $id]);
        if ((int) $uso->fetchColumn() > 0) {
            flash('warning', 'El implemento ya fue alquilado en reservas y no puede eliminarse. Márcalo como INACTIVO.');
        } else {
            $del = $pdo->prepare('DELETE FROM implementos WHERE id_implemento = :id');
            $del->execute([':id' => $id]);
            flash($del->rowCount() ? 'success' : 'danger', $del->rowCount() ? 'Implemento eliminado.' : 'El implemento no existe.');
        }
    } elseif ($accion === 'estado') {
        $estado = post_texto('estado');
        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            flash('danger', 'Estado no válido.');
        } else {
            $pdo->prepare('UPDATE implementos SET estado = :estado WHERE id_implemento = :id')
                ->execute([':estado' => $estado, ':id' => $id]);
            flash('success', 'Estado actualizado a ' . $estado . '.');
        }
    } else {
        flash('danger', 'Acción no reconocida.');
    }
    redirigir('admin/implementos.php');
}

/* ---------------------------------------------------------------------------
 * Listado con búsqueda y filtros
 * ------------------------------------------------------------------------- */
$busqueda = mb_substr(get_texto('q'), 0, 60);
$filtroDeporte = get_texto('deporte');
$filtroEstado = in_array(get_texto('estado'), ['ACTIVO', 'INACTIVO'], true) ? get_texto('estado') : '';

$condiciones = [];
$parametros = [];
if ($busqueda !== '') {
    $condiciones[] = '(i.nombre LIKE :q1 OR i.descripcion LIKE :q2)';
    $parametros[':q1'] = '%' . $busqueda . '%';
    $parametros[':q2'] = '%' . $busqueda . '%';
}
if ($filtroDeporte === 'multi') {
    $condiciones[] = 'i.id_deporte IS NULL';
} elseif (($idDep = filter_var($filtroDeporte, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) !== false) {
    $condiciones[] = 'i.id_deporte = :deporte';
    $parametros[':deporte'] = $idDep;
}
if ($filtroEstado !== '') {
    $condiciones[] = 'i.estado = :estado';
    $parametros[':estado'] = $filtroEstado;
}

$sql = "SELECT i.id_implemento, i.nombre, i.descripcion, i.precio_alquiler, i.stock_total, i.estado,
               d.nombre AS deporte,
               (SELECT COALESCE(SUM(ri.cantidad), 0) FROM reserva_implementos ri
                  JOIN reservas r ON r.id_reserva = ri.id_reserva
                 WHERE ri.id_implemento = i.id_implemento AND r.estado <> 'CANCELADA') AS veces_alquilado
          FROM implementos i
          LEFT JOIN deportes d ON d.id_deporte = i.id_deporte";
if ($condiciones) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}
$sql .= ' ORDER BY d.nombre IS NULL, d.nombre, i.nombre';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$implementos = $stmt->fetchAll();

$deportes = $pdo->query('SELECT id_deporte, nombre FROM deportes ORDER BY nombre')->fetchAll();

$titulo = 'Implementos';
$activo = 'implementos_admin';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 kz-titulo-seccion mb-0"><i class="fa-solid fa-boxes-stacked me-2"></i>Gestión de implementos</h1>
            <p class="text-muted small mb-0">Inventario de implementos en alquiler, precios y stock.</p>
        </div>
        <a href="<?= e(url('admin/implemento_form.php')) ?>" class="btn btn-kz-verde"><i class="fa-solid fa-plus me-1"></i>Nuevo implemento</a>
    </div>

    <form method="get" action="<?= e(url('admin/implementos.php')) ?>" class="card kz-card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label for="q" class="form-label">Buscar</label>
                <input type="search" class="form-control" id="q" name="q" maxlength="60" placeholder="Nombre o descripción…" value="<?= e($busqueda) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label for="deporte" class="form-label">Deporte</label>
                <select class="form-select" id="deporte" name="deporte" data-autosubmit>
                    <option value="">Todos</option>
                    <?php foreach ($deportes as $d): ?>
                        <option value="<?= (int) $d['id_deporte'] ?>" <?= $filtroDeporte === (string) $d['id_deporte'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
                    <?php endforeach; ?>
                    <option value="multi" <?= $filtroDeporte === 'multi' ? 'selected' : '' ?>>Multideporte</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="estado" class="form-label">Estado</label>
                <select class="form-select" id="estado" name="estado" data-autosubmit>
                    <option value="">Todos</option>
                    <option value="ACTIVO" <?= $filtroEstado === 'ACTIVO' ? 'selected' : '' ?>>ACTIVO</option>
                    <option value="INACTIVO" <?= $filtroEstado === 'INACTIVO' ? 'selected' : '' ?>>INACTIVO</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-kz-azul flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i></button>
                <a href="<?= e(url('admin/implementos.php')) ?>" class="btn btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-eraser"></i></a>
            </div>
        </div>
    </form>

    <p class="small text-muted mb-2"><?= count($implementos) ?> implemento(s)</p>

    <div class="table-responsive card kz-card">
        <table class="table table-hover kz-tabla mb-0">
            <thead>
            <tr><th>Implemento</th><th>Deporte</th><th class="text-end">Precio</th><th class="text-center">Stock</th><th class="text-center">Alquilado</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php if (!$implementos): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No hay implementos que coincidan.</td></tr>
            <?php endif; ?>
            <?php foreach ($implementos as $i): ?>
                <?php $nuevo = $i['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'; ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($i['nombre']) ?></div><div class="small text-muted"><?= e($i['descripcion']) ?></div></td>
                    <td><?= e($i['deporte'] ?? 'Multideporte') ?></td>
                    <td class="text-end kz-tarifa-dia">$<?= e(number_format((float) $i['precio_alquiler'], 2)) ?></td>
                    <td class="text-center"><?= (int) $i['stock_total'] ?></td>
                    <td class="text-center"><?= (int) $i['veces_alquilado'] ?></td>
                    <td><span class="badge kz-estado kz-estado-<?= e($i['estado']) ?>"><?= e($i['estado']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('admin/implemento_form.php?id=' . (int) $i['id_implemento'])) ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" action="<?= e(url('admin/implementos.php')) ?>" class="d-inline">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="estado">
                            <input type="hidden" name="id_implemento" value="<?= (int) $i['id_implemento'] ?>">
                            <input type="hidden" name="estado" value="<?= e($nuevo) ?>">
                            <button class="btn btn-sm btn-outline-secondary" title="<?= $nuevo === 'INACTIVO' ? 'Desactivar' : 'Activar' ?>">
                                <i class="fa-solid <?= $nuevo === 'INACTIVO' ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                            </button>
                        </form>
                        <form method="post" action="<?= e(url('admin/implementos.php')) ?>" class="d-inline"
                              data-confirmar="¿Eliminar «<?= e($i['nombre']) ?>»? Esta acción no se puede deshacer.">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id_implemento" value="<?= (int) $i['id_implemento'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
