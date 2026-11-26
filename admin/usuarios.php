<?php
/**
 * KickZone - Gestión de usuarios: listado, búsqueda, filtro por rol,
 * cambio de rol, activación/desactivación y desbloqueo. Acceso exclusivo: ADMIN.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requiere_rol(ROL_ADMIN);

$pdo = db();
$idPropio = (int) $_SESSION['usuario']['id'];

$roles = $pdo->query('SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol')->fetchAll(PDO::FETCH_KEY_PAIR);

/* ---------------------------------------------------------------------------
 * Acciones POST (todas protegidas con CSRF)
 * ------------------------------------------------------------------------- */
if (es_post()) {
    csrf_verificar();

    $accion = post_texto('accion');
    $idUsuario = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!$idUsuario) {
        flash('danger', 'Usuario no válido.');
    } elseif ($idUsuario === $idPropio && in_array($accion, ['rol', 'estado', 'eliminar'], true)) {
        // Evita que el administrador se quite el acceso a sí mismo por error.
        flash('warning', 'No puedes cambiar tu propio rol, desactivar ni eliminar tu propia cuenta.');
    } elseif ($accion === 'rol') {
        $idRol = filter_var($_POST['id_rol'] ?? null, FILTER_VALIDATE_INT);
        if ($idRol === false || !array_key_exists($idRol, $roles)) {
            flash('danger', 'Rol no válido.');
        } else {
            $pdo->prepare('UPDATE usuarios SET id_rol = :rol WHERE id_usuario = :id')
                ->execute([':rol' => $idRol, ':id' => $idUsuario]);
            flash('success', 'Rol actualizado a ' . $roles[$idRol] . '.');
        }
    } elseif ($accion === 'estado') {
        $estado = post_texto('estado');
        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            flash('danger', 'Estado no válido.');
        } else {
            $pdo->prepare('UPDATE usuarios SET estado = :estado WHERE id_usuario = :id')
                ->execute([':estado' => $estado, ':id' => $idUsuario]);
            flash('success', $estado === 'ACTIVO' ? 'Usuario activado.' : 'Usuario desactivado: ya no podrá iniciar sesión.');
        }
    } elseif ($accion === 'desbloquear') {
        $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = :id')
            ->execute([':id' => $idUsuario]);
        flash('success', 'Bloqueo por intentos fallidos eliminado.');
    } elseif ($accion === 'eliminar') {
        // Con historial de reservas o pagos no se elimina: se perdería la trazabilidad.
        $uso = $pdo->prepare(
            'SELECT (SELECT COUNT(*) FROM reservas WHERE id_cliente = :id1)
                  + (SELECT COUNT(*) FROM pagos WHERE registrado_por = :id2)'
        );
        $uso->execute([':id1' => $idUsuario, ':id2' => $idUsuario]);
        if ((int) $uso->fetchColumn() > 0) {
            flash('warning', 'El usuario tiene reservas o pagos registrados y no puede eliminarse. Desactívalo en su lugar.');
        } else {
            $del = $pdo->prepare('DELETE FROM usuarios WHERE id_usuario = :id');
            $del->execute([':id' => $idUsuario]);
            flash($del->rowCount() ? 'success' : 'danger', $del->rowCount() ? 'Usuario eliminado.' : 'El usuario no existe.');
        }
    } else {
        flash('danger', 'Acción no reconocida.');
    }

    redirigir('admin/usuarios.php');
}

/* ---------------------------------------------------------------------------
 * Listado con búsqueda y filtro por rol
 * ------------------------------------------------------------------------- */
$busqueda = mb_substr(get_texto('q'), 0, 60);
$filtroRol = filter_var($_GET['rol'] ?? null, FILTER_VALIDATE_INT);
$filtroRol = ($filtroRol !== false && array_key_exists($filtroRol, $roles)) ? $filtroRol : null;

$condiciones = [];
$parametros = [];
if ($busqueda !== '') {
    $condiciones[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :q1 OR u.correo LIKE :q2)";
    $parametros[':q1'] = '%' . $busqueda . '%';
    $parametros[':q2'] = '%' . $busqueda . '%';
}
if ($filtroRol) {
    $condiciones[] = 'u.id_rol = :rol';
    $parametros[':rol'] = $filtroRol;
}

$sql = 'SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.telefono, u.estado, u.id_rol,
               u.fecha_registro, r.nombre_rol,
               (u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW()) AS bloqueado
          FROM usuarios u
          JOIN roles r ON r.id_rol = u.id_rol';
if ($condiciones) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}
$sql .= ' ORDER BY u.id_rol, u.nombre';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$usuarios = $stmt->fetchAll();

$titulo = 'Usuarios';
$activo = 'usuarios';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 kz-titulo-seccion mb-0"><i class="fa-solid fa-users-gear me-2"></i>Gestión de usuarios</h1>
            <p class="text-muted small mb-0">Crea, edita y elimina usuarios; cambia roles, estados y libera bloqueos.</p>
        </div>
        <a href="<?= e(url('admin/usuario_form.php')) ?>" class="btn btn-kz-verde"><i class="fa-solid fa-user-plus me-1"></i>Nuevo usuario</a>
    </div>

    <form method="get" action="<?= e(url('admin/usuarios.php')) ?>" class="card kz-card p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="q" class="form-label">Buscar</label>
                <input type="search" class="form-control" id="q" name="q" maxlength="60"
                       placeholder="Nombre o correo…" value="<?= e($busqueda) ?>">
            </div>
            <div class="col-md-4">
                <label for="rol" class="form-label">Rol</label>
                <select class="form-select" id="rol" name="rol" data-autosubmit>
                    <option value="">Todos</option>
                    <?php foreach ($roles as $idR => $nombreR): ?>
                        <option value="<?= (int) $idR ?>" <?= $filtroRol === (int) $idR ? 'selected' : '' ?>><?= e($nombreR) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-kz-azul flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i></button>
                <a href="<?= e(url('admin/usuarios.php')) ?>" class="btn btn-outline-secondary" title="Limpiar"><i class="fa-solid fa-eraser"></i></a>
            </div>
        </div>
    </form>

    <div class="table-responsive card kz-card">
        <table class="table table-hover kz-tabla mb-0">
            <thead>
            <tr><th>Usuario</th><th>Teléfono</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php if (!$usuarios): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No se encontraron usuarios.</td></tr>
            <?php endif; ?>
            <?php foreach ($usuarios as $u): ?>
                <?php $esPropio = (int) $u['id_usuario'] === $idPropio; ?>
                <tr>
                    <td>
                        <div class="fw-semibold">
                            <?= e($u['nombre'] . ' ' . $u['apellido']) ?>
                            <?php if ($esPropio): ?><span class="badge text-bg-secondary ms-1">Tú</span><?php endif; ?>
                            <?php if ((int) $u['bloqueado'] === 1): ?><span class="badge text-bg-danger ms-1"><i class="fa-solid fa-lock"></i> Bloqueado</span><?php endif; ?>
                        </div>
                        <div class="small text-muted"><?= e($u['correo']) ?></div>
                    </td>
                    <td class="small"><?= e($u['telefono']) ?></td>
                    <td>
                        <form method="post" action="<?= e(url('admin/usuarios.php')) ?>" class="m-0">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="rol">
                            <input type="hidden" name="id_usuario" value="<?= (int) $u['id_usuario'] ?>">
                            <select name="id_rol" class="form-select form-select-sm" data-autosubmit <?= $esPropio ? 'disabled' : '' ?>
                                    aria-label="Rol de <?= e($u['nombre']) ?>">
                                <?php foreach ($roles as $idR => $nombreR): ?>
                                    <option value="<?= (int) $idR ?>" <?= (int) $u['id_rol'] === (int) $idR ? 'selected' : '' ?>><?= e($nombreR) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td><span class="badge kz-estado kz-estado-<?= e($u['estado']) ?>"><?= e($u['estado']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('admin/usuario_form.php?id=' . (int) $u['id_usuario'])) ?>"
                           class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                        <?php if ((int) $u['bloqueado'] === 1): ?>
                            <form method="post" action="<?= e(url('admin/usuarios.php')) ?>" class="d-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="desbloquear">
                                <input type="hidden" name="id_usuario" value="<?= (int) $u['id_usuario'] ?>">
                                <button class="btn btn-sm btn-outline-warning" title="Desbloquear"><i class="fa-solid fa-unlock"></i></button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$esPropio): ?>
                            <?php $nuevo = $u['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'; ?>
                            <form method="post" action="<?= e(url('admin/usuarios.php')) ?>" class="d-inline"
                                  data-confirmar="¿Cambiar el estado de <?= e($u['nombre']) ?> a <?= e($nuevo) ?>?">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="estado">
                                <input type="hidden" name="id_usuario" value="<?= (int) $u['id_usuario'] ?>">
                                <input type="hidden" name="estado" value="<?= e($nuevo) ?>">
                                <button class="btn btn-sm <?= $nuevo === 'INACTIVO' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                        title="<?= $nuevo === 'INACTIVO' ? 'Desactivar' : 'Activar' ?>">
                                    <i class="fa-solid <?= $nuevo === 'INACTIVO' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                </button>
                            </form>
                            <form method="post" action="<?= e(url('admin/usuarios.php')) ?>" class="d-inline"
                                  data-confirmar="¿Eliminar a <?= e($u['nombre'] . ' ' . $u['apellido']) ?>? Esta acción no se puede deshacer.">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_usuario" value="<?= (int) $u['id_usuario'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
