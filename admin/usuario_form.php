<?php
/**
 * KickZone - Crear / editar usuario (cualquier rol). Acceso exclusivo: ADMIN.
 * En edición la contraseña es opcional: si se deja vacía se conserva la actual.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requiere_rol(ROL_ADMIN);

$pdo = db();
$idPropio = (int) $_SESSION['usuario']['id'];
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$esEdicion = $id !== null;
$esPropio = $esEdicion && $id === $idPropio;

$roles = $pdo->query('SELECT id_rol, nombre_rol FROM roles ORDER BY id_rol')->fetchAll(PDO::FETCH_KEY_PAIR);
$u = ['nombre' => '', 'apellido' => '', 'correo' => '', 'telefono' => '', 'id_rol' => (string) ID_ROL_CLIENTE, 'estado' => 'ACTIVO'];

if ($esEdicion) {
    $stmt = $pdo->prepare('SELECT nombre, apellido, correo, telefono, id_rol, estado FROM usuarios WHERE id_usuario = :id');
    $stmt->execute([':id' => $id]);
    $fila = $stmt->fetch();
    if (!$fila) {
        flash('danger', 'El usuario no existe.');
        redirigir('admin/usuarios.php');
    }
    $u = $fila;
}

$errores = [];

if (es_post()) {
    csrf_verificar();

    $u = [
        'nombre'   => post_texto('nombre'),
        'apellido' => post_texto('apellido'),
        'correo'   => mb_strtolower(post_texto('correo')),
        'telefono' => post_texto('telefono'),
        'id_rol'   => post_texto('id_rol'),
        'estado'   => post_texto('estado'),
    ];
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $soloLetras = '/^[\p{L} \'-]{2,80}$/u';
    if (!preg_match($soloLetras, $u['nombre'])) {
        $errores['nombre'] = 'El nombre debe tener entre 2 y 80 letras.';
    }
    if (!preg_match($soloLetras, $u['apellido'])) {
        $errores['apellido'] = 'El apellido debe tener entre 2 y 80 letras.';
    }
    if (!filter_var($u['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($u['correo']) > 120) {
        $errores['correo'] = 'Ingresa un correo válido.';
    } else {
        $dup = $pdo->prepare('SELECT 1 FROM usuarios WHERE correo = :correo AND id_usuario <> :id LIMIT 1');
        $dup->execute([':correo' => $u['correo'], ':id' => $id ?? 0]);
        if ($dup->fetchColumn()) {
            $errores['correo'] = 'Ya existe otro usuario con ese correo.';
        }
    }
    if (!preg_match('/^\+?[0-9 \-]{7,20}$/', $u['telefono'])) {
        $errores['telefono'] = 'Ingresa un teléfono válido (ej. 6000-0000).';
    }
    $idRol = filter_var($u['id_rol'], FILTER_VALIDATE_INT);
    if ($idRol === false || !array_key_exists($idRol, $roles)) {
        $errores['id_rol'] = 'Selecciona un rol válido.';
    }
    if (!in_array($u['estado'], ['ACTIVO', 'INACTIVO'], true)) {
        $errores['estado'] = 'Selecciona un estado válido.';
    }
    // El administrador no puede quitarse su propio rol ni desactivarse.
    if ($esPropio && ((int) $idRol !== (int) $fila['id_rol'] || $u['estado'] !== 'ACTIVO')) {
        $errores['id_rol'] = 'No puedes cambiar tu propio rol ni desactivar tu cuenta.';
    }
    if (!$esEdicion || $password !== '') {
        $reglas = validar_politica_password($password);
        if ($reglas) {
            $errores['password'] = implode(' ', $reglas);
        }
    }

    if (!$errores) {
        $params = [
            ':rol' => $idRol, ':nombre' => $u['nombre'], ':apellido' => $u['apellido'],
            ':correo' => $u['correo'], ':telefono' => $u['telefono'], ':estado' => $u['estado'],
        ];
        if ($esEdicion) {
            $sql = 'UPDATE usuarios SET id_rol = :rol, nombre = :nombre, apellido = :apellido, correo = :correo,
                           telefono = :telefono, estado = :estado';
            if ($password !== '') {
                $sql .= ', password_hash = :hash, intentos_fallidos = 0, bloqueado_hasta = NULL';
                $params[':hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $params[':id'] = $id;
            $pdo->prepare($sql . ' WHERE id_usuario = :id')->execute($params);
            flash('success', 'Usuario ' . $u['nombre'] . ' ' . $u['apellido'] . ' actualizado.');
        } else {
            $params[':hash'] = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                'INSERT INTO usuarios (id_rol, nombre, apellido, correo, telefono, estado, password_hash)
                 VALUES (:rol, :nombre, :apellido, :correo, :telefono, :estado, :hash)'
            )->execute($params);
            flash('success', 'Usuario ' . $u['nombre'] . ' ' . $u['apellido'] . ' creado.');
        }
        redirigir('admin/usuarios.php');
    }
}

$invalido = static fn(string $c): string => isset($errores[$c]) ? ' is-invalid' : '';
$titulo = $esEdicion ? 'Editar usuario' : 'Nuevo usuario';
$activo = 'usuarios';
$accionForm = $esEdicion ? 'admin/usuario_form.php?id=' . $id : 'admin/usuario_form.php';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('admin/usuarios.php')) ?>">Usuarios</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($titulo) ?></li>
        </ol>
    </nav>
    <h1 class="h3 kz-titulo-seccion mb-3"><?= e($titulo) ?></h1>

    <?php if ($errores): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-1"></i>Corrige los campos marcados en rojo.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url($accionForm)) ?>" class="card kz-card p-4" novalidate>
        <?= csrf_campo() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="nombre" class="form-label">Nombre *</label>
                <input type="text" class="form-control<?= $invalido('nombre') ?>" id="nombre" name="nombre" maxlength="80" required value="<?= e($u['nombre']) ?>">
                <div class="invalid-feedback"><?= e($errores['nombre'] ?? '') ?></div>
            </div>
            <div class="col-md-6">
                <label for="apellido" class="form-label">Apellido *</label>
                <input type="text" class="form-control<?= $invalido('apellido') ?>" id="apellido" name="apellido" maxlength="80" required value="<?= e($u['apellido']) ?>">
                <div class="invalid-feedback"><?= e($errores['apellido'] ?? '') ?></div>
            </div>
            <div class="col-md-6">
                <label for="correo" class="form-label">Correo *</label>
                <input type="email" class="form-control<?= $invalido('correo') ?>" id="correo" name="correo" maxlength="120" required value="<?= e($u['correo']) ?>">
                <div class="invalid-feedback"><?= e($errores['correo'] ?? '') ?></div>
            </div>
            <div class="col-md-6">
                <label for="telefono" class="form-label">Teléfono *</label>
                <input type="tel" class="form-control<?= $invalido('telefono') ?>" id="telefono" name="telefono" maxlength="20" required value="<?= e($u['telefono']) ?>">
                <div class="invalid-feedback"><?= e($errores['telefono'] ?? '') ?></div>
            </div>
            <div class="col-md-4">
                <label for="id_rol" class="form-label">Rol *</label>
                <select class="form-select<?= $invalido('id_rol') ?>" id="id_rol" name="id_rol">
                    <?php foreach ($roles as $idR => $nombreR): ?>
                        <option value="<?= (int) $idR ?>" <?= (string) $u['id_rol'] === (string) $idR ? 'selected' : '' ?>><?= e($nombreR) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback"><?= e($errores['id_rol'] ?? '') ?></div>
            </div>
            <div class="col-md-4">
                <label for="estado" class="form-label">Estado *</label>
                <select class="form-select<?= $invalido('estado') ?>" id="estado" name="estado">
                    <option value="ACTIVO" <?= $u['estado'] === 'ACTIVO' ? 'selected' : '' ?>>ACTIVO</option>
                    <option value="INACTIVO" <?= $u['estado'] === 'INACTIVO' ? 'selected' : '' ?>>INACTIVO</option>
                </select>
                <div class="invalid-feedback"><?= e($errores['estado'] ?? '') ?></div>
            </div>
            <div class="col-md-4">
                <label for="password" class="form-label">Contraseña <?= $esEdicion ? '(dejar vacía para no cambiarla)' : '*' ?></label>
                <input type="password" class="form-control<?= $invalido('password') ?>" id="password" name="password" maxlength="72" autocomplete="new-password" <?= $esEdicion ? '' : 'required' ?>>
                <div class="invalid-feedback"><?= e($errores['password'] ?? '') ?></div>
                <div class="form-text">Mínimo 8 caracteres con mayúscula, minúscula, número y símbolo.</div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="<?= e(url('admin/usuarios.php')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-kz-verde"><i class="fa-solid fa-floppy-disk me-1"></i><?= $esEdicion ? 'Guardar cambios' : 'Crear usuario' ?></button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
