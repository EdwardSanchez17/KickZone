<?php
/**
 * KickZone - Crear / editar implemento. Acceso exclusivo: ADMIN.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requiere_rol(ROL_ADMIN);

$pdo = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$esEdicion = $id !== null;

$imp = ['id_deporte' => '', 'nombre' => '', 'descripcion' => '', 'precio_alquiler' => '', 'stock_total' => '10', 'estado' => 'ACTIVO'];

if ($esEdicion) {
    $stmt = $pdo->prepare('SELECT * FROM implementos WHERE id_implemento = :id');
    $stmt->execute([':id' => $id]);
    $fila = $stmt->fetch();
    if (!$fila) {
        flash('danger', 'El implemento no existe.');
        redirigir('admin/implementos.php');
    }
    $imp = array_merge($imp, $fila);
    $imp['id_deporte'] = $imp['id_deporte'] ?? '';
}

$deportes = $pdo->query('SELECT id_deporte, nombre FROM deportes ORDER BY nombre')->fetchAll();
$idsDeportes = array_map('intval', array_column($deportes, 'id_deporte'));
$errores = [];

if (es_post()) {
    csrf_verificar();

    $imp = [
        'id_deporte'      => post_texto('id_deporte'),
        'nombre'          => post_texto('nombre'),
        'descripcion'     => post_texto('descripcion'),
        'precio_alquiler' => post_texto('precio_alquiler'),
        'stock_total'     => post_texto('stock_total'),
        'estado'          => post_texto('estado'),
    ];

    $idDeporte = null;
    if ($imp['id_deporte'] !== '') {
        $idDeporte = filter_var($imp['id_deporte'], FILTER_VALIDATE_INT);
        if ($idDeporte === false || !in_array($idDeporte, $idsDeportes, true)) {
            $errores['id_deporte'] = 'Selecciona un deporte válido.';
        }
    }
    if (mb_strlen($imp['nombre']) < 3 || mb_strlen($imp['nombre']) > 100) {
        $errores['nombre'] = 'El nombre debe tener entre 3 y 100 caracteres.';
    }
    if (mb_strlen($imp['descripcion']) < 5 || mb_strlen($imp['descripcion']) > 200) {
        $errores['descripcion'] = 'La descripción debe tener entre 5 y 200 caracteres.';
    }
    $precio = filter_var($imp['precio_alquiler'], FILTER_VALIDATE_FLOAT);
    if ($precio === false || $precio <= 0 || $precio > 500) {
        $errores['precio_alquiler'] = 'El precio debe ser mayor que 0 y menor que 500.';
    }
    $stock = filter_var($imp['stock_total'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 500]]);
    if ($stock === false) {
        $errores['stock_total'] = 'El stock debe ser un número entre 0 y 500.';
    }
    if (!in_array($imp['estado'], ['ACTIVO', 'INACTIVO'], true)) {
        $errores['estado'] = 'Selecciona un estado válido.';
    }

    if (!$errores) {
        $params = [
            ':deporte' => $idDeporte, ':nombre' => $imp['nombre'], ':descripcion' => $imp['descripcion'],
            ':precio' => round($precio, 2), ':stock' => $stock, ':estado' => $imp['estado'],
        ];
        if ($esEdicion) {
            $params[':id'] = $id;
            $pdo->prepare(
                'UPDATE implementos SET id_deporte = :deporte, nombre = :nombre, descripcion = :descripcion,
                        precio_alquiler = :precio, stock_total = :stock, estado = :estado
                  WHERE id_implemento = :id'
            )->execute($params);
            flash('success', 'Implemento «' . $imp['nombre'] . '» actualizado.');
        } else {
            $pdo->prepare(
                'INSERT INTO implementos (id_deporte, nombre, descripcion, precio_alquiler, stock_total, estado)
                 VALUES (:deporte, :nombre, :descripcion, :precio, :stock, :estado)'
            )->execute($params);
            flash('success', 'Implemento «' . $imp['nombre'] . '» creado.');
        }
        redirigir('admin/implementos.php');
    }
}

$invalido = static fn(string $c): string => isset($errores[$c]) ? ' is-invalid' : '';
$titulo = $esEdicion ? 'Editar implemento' : 'Nuevo implemento';
$activo = 'implementos_admin';
$accionForm = $esEdicion ? 'admin/implemento_form.php?id=' . $id : 'admin/implemento_form.php';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('admin/implementos.php')) ?>">Implementos</a></li>
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
            <div class="col-md-8">
                <label for="nombre" class="form-label">Nombre *</label>
                <input type="text" class="form-control<?= $invalido('nombre') ?>" id="nombre" name="nombre" maxlength="100" required value="<?= e($imp['nombre']) ?>">
                <div class="invalid-feedback"><?= e($errores['nombre'] ?? '') ?></div>
            </div>
            <div class="col-md-4">
                <label for="id_deporte" class="form-label">Deporte</label>
                <select class="form-select<?= $invalido('id_deporte') ?>" id="id_deporte" name="id_deporte">
                    <option value="">Multideporte</option>
                    <?php foreach ($deportes as $d): ?>
                        <option value="<?= (int) $d['id_deporte'] ?>" <?= (string) $imp['id_deporte'] === (string) $d['id_deporte'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback"><?= e($errores['id_deporte'] ?? '') ?></div>
            </div>
            <div class="col-12">
                <label for="descripcion" class="form-label">Descripción *</label>
                <input type="text" class="form-control<?= $invalido('descripcion') ?>" id="descripcion" name="descripcion" maxlength="200" required value="<?= e($imp['descripcion']) ?>">
                <div class="invalid-feedback"><?= e($errores['descripcion'] ?? '') ?></div>
            </div>
            <div class="col-md-4">
                <label for="precio_alquiler" class="form-label">Precio de alquiler ($) *</label>
                <input type="number" class="form-control<?= $invalido('precio_alquiler') ?>" id="precio_alquiler" name="precio_alquiler" min="0.01" max="500" step="0.01" required value="<?= e($imp['precio_alquiler']) ?>">
                <div class="invalid-feedback"><?= e($errores['precio_alquiler'] ?? '') ?></div>
            </div>
            <div class="col-md-4">
                <label for="stock_total" class="form-label">Stock total *</label>
                <input type="number" class="form-control<?= $invalido('stock_total') ?>" id="stock_total" name="stock_total" min="0" max="500" required value="<?= e($imp['stock_total']) ?>">
                <div class="invalid-feedback"><?= e($errores['stock_total'] ?? '') ?></div>
            </div>
            <div class="col-md-4">
                <label for="estado" class="form-label">Estado *</label>
                <select class="form-select<?= $invalido('estado') ?>" id="estado" name="estado">
                    <option value="ACTIVO" <?= $imp['estado'] === 'ACTIVO' ? 'selected' : '' ?>>ACTIVO</option>
                    <option value="INACTIVO" <?= $imp['estado'] === 'INACTIVO' ? 'selected' : '' ?>>INACTIVO</option>
                </select>
                <div class="invalid-feedback"><?= e($errores['estado'] ?? '') ?></div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="<?= e(url('admin/implementos.php')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-kz-verde"><i class="fa-solid fa-floppy-disk me-1"></i><?= $esEdicion ? 'Guardar cambios' : 'Crear implemento' ?></button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
