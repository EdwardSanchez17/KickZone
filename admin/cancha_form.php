<?php
/**
 * KickZone - Crear / editar cancha.
 * Acceso exclusivo: ADMIN. Validación completa del lado del servidor.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requiere_rol(ROL_ADMIN);

$pdo = db();
$estados = ['DISPONIBLE', 'MANTENIMIENTO', 'INACTIVA'];

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$esEdicion = $id !== null;

$cancha = [
    'id_deporte'           => '',
    'nombre'               => '',
    'descripcion'          => '',
    'superficie'           => '',
    'capacidad_jugadores'  => '',
    'tarifa_diurna'        => '',
    'tarifa_nocturna'      => '',
    'hora_inicio_nocturna' => '18:00',
    'estado'               => 'DISPONIBLE',
];

if ($esEdicion) {
    $stmt = $pdo->prepare('SELECT * FROM canchas WHERE id_cancha = :id');
    $stmt->execute([':id' => $id]);
    $encontrada = $stmt->fetch();
    if (!$encontrada) {
        flash('danger', 'La cancha solicitada no existe.');
        redirigir('admin/canchas.php');
    }
    $cancha = array_merge($cancha, $encontrada);
    $cancha['hora_inicio_nocturna'] = substr((string) $cancha['hora_inicio_nocturna'], 0, 5);
}

$deportes = $pdo->query('SELECT id_deporte, nombre FROM deportes ORDER BY nombre')->fetchAll();
$idsDeportes = array_map('intval', array_column($deportes, 'id_deporte'));

$errores = [];

if (es_post()) {
    csrf_verificar();

    $cancha = [
        'id_deporte'           => post_texto('id_deporte'),
        'nombre'               => post_texto('nombre'),
        'descripcion'          => post_texto('descripcion'),
        'superficie'           => post_texto('superficie'),
        'capacidad_jugadores'  => post_texto('capacidad_jugadores'),
        'tarifa_diurna'        => post_texto('tarifa_diurna'),
        'tarifa_nocturna'      => post_texto('tarifa_nocturna'),
        'hora_inicio_nocturna' => post_texto('hora_inicio_nocturna'),
        'estado'               => post_texto('estado'),
    ];

    $idDeporte = filter_var($cancha['id_deporte'], FILTER_VALIDATE_INT);
    $capacidad = filter_var($cancha['capacidad_jugadores'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 2, 'max_range' => 30]]);
    $tarifaDia = filter_var($cancha['tarifa_diurna'], FILTER_VALIDATE_FLOAT);
    $tarifaNoche = filter_var($cancha['tarifa_nocturna'], FILTER_VALIDATE_FLOAT);

    if ($idDeporte === false || !in_array($idDeporte, $idsDeportes, true)) {
        $errores['id_deporte'] = 'Selecciona un deporte válido.';
    }
    if (mb_strlen($cancha['nombre']) < 3 || mb_strlen($cancha['nombre']) > 100) {
        $errores['nombre'] = 'El nombre debe tener entre 3 y 100 caracteres.';
    }
    if (mb_strlen($cancha['descripcion']) < 10 || mb_strlen($cancha['descripcion']) > 1000) {
        $errores['descripcion'] = 'La descripción debe tener entre 10 y 1000 caracteres.';
    }
    if (mb_strlen($cancha['superficie']) < 3 || mb_strlen($cancha['superficie']) > 60) {
        $errores['superficie'] = 'La superficie debe tener entre 3 y 60 caracteres.';
    }
    if ($capacidad === false) {
        $errores['capacidad_jugadores'] = 'La capacidad debe ser un número entre 2 y 30 jugadores.';
    }
    if ($tarifaDia === false || $tarifaDia <= 0 || $tarifaDia > 999) {
        $errores['tarifa_diurna'] = 'La tarifa diurna debe ser mayor que 0 y menor que 1000.';
    }
    if ($tarifaNoche === false || $tarifaNoche <= 0 || $tarifaNoche > 999) {
        $errores['tarifa_nocturna'] = 'La tarifa nocturna debe ser mayor que 0 y menor que 1000.';
    } elseif ($tarifaDia !== false && $tarifaNoche < $tarifaDia) {
        $errores['tarifa_nocturna'] = 'La tarifa nocturna no puede ser menor que la diurna.';
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $cancha['hora_inicio_nocturna'])) {
        $errores['hora_inicio_nocturna'] = 'Ingresa una hora válida (HH:MM).';
    }
    if (!in_array($cancha['estado'], $estados, true)) {
        $errores['estado'] = 'Selecciona un estado válido.';
    }

    if (!$errores) {
        $parametros = [
            ':deporte'     => $idDeporte,
            ':nombre'      => $cancha['nombre'],
            ':descripcion' => $cancha['descripcion'],
            ':superficie'  => $cancha['superficie'],
            ':capacidad'   => $capacidad,
            ':diurna'      => round($tarifaDia, 2),
            ':nocturna'    => round($tarifaNoche, 2),
            ':hora'        => $cancha['hora_inicio_nocturna'] . ':00',
            ':estado'      => $cancha['estado'],
        ];

        if ($esEdicion) {
            $parametros[':id'] = $id;
            $pdo->prepare(
                'UPDATE canchas
                    SET id_deporte = :deporte, nombre = :nombre, descripcion = :descripcion,
                        superficie = :superficie, capacidad_jugadores = :capacidad,
                        tarifa_diurna = :diurna, tarifa_nocturna = :nocturna,
                        hora_inicio_nocturna = :hora, estado = :estado
                  WHERE id_cancha = :id'
            )->execute($parametros);
            flash('success', 'Cancha «' . $cancha['nombre'] . '» actualizada correctamente.');
        } else {
            $pdo->prepare(
                'INSERT INTO canchas (id_deporte, nombre, descripcion, superficie, capacidad_jugadores,
                                      tarifa_diurna, tarifa_nocturna, hora_inicio_nocturna, estado)
                 VALUES (:deporte, :nombre, :descripcion, :superficie, :capacidad,
                         :diurna, :nocturna, :hora, :estado)'
            )->execute($parametros);
            flash('success', 'Cancha «' . $cancha['nombre'] . '» creada correctamente.');
        }
        redirigir('admin/canchas.php');
    }
}

/** Clase de Bootstrap para marcar un campo con error. */
$invalido = static fn(string $campo): string => isset($errores[$campo]) ? ' is-invalid' : '';

$titulo = $esEdicion ? 'Editar cancha' : 'Nueva cancha';
$activo = 'admin_canchas';
$accionForm = $esEdicion ? 'admin/cancha_form.php?id=' . $id : 'admin/cancha_form.php';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('admin/canchas.php')) ?>">Canchas</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($titulo) ?></li>
        </ol>
    </nav>

    <h1 class="h3 kz-titulo-seccion mb-3">
        <i class="fa-solid <?= $esEdicion ? 'fa-pen-to-square' : 'fa-plus' ?> me-2"></i><?= e($titulo) ?>
    </h1>

    <?php if ($errores): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-1"></i>Corrige los campos marcados en rojo.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url($accionForm)) ?>" class="card kz-card p-4" novalidate>
        <?= csrf_campo() ?>
        <div class="row g-3">
            <div class="col-md-8">
                <label for="nombre" class="form-label">Nombre de la cancha *</label>
                <input type="text" class="form-control<?= $invalido('nombre') ?>" id="nombre" name="nombre"
                       maxlength="100" required value="<?= e($cancha['nombre']) ?>">
                <div class="invalid-feedback"><?= e($errores['nombre'] ?? '') ?></div>
            </div>
            <div class="col-md-4">
                <label for="id_deporte" class="form-label">Deporte *</label>
                <select class="form-select<?= $invalido('id_deporte') ?>" id="id_deporte" name="id_deporte" required>
                    <option value="">Selecciona…</option>
                    <?php foreach ($deportes as $d): ?>
                        <option value="<?= (int) $d['id_deporte'] ?>" <?= (string) $cancha['id_deporte'] === (string) $d['id_deporte'] ? 'selected' : '' ?>>
                            <?= e($d['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback"><?= e($errores['id_deporte'] ?? '') ?></div>
            </div>
            <div class="col-12">
                <label for="descripcion" class="form-label">Descripción *</label>
                <textarea class="form-control<?= $invalido('descripcion') ?>" id="descripcion" name="descripcion"
                          rows="3" maxlength="1000" required><?= e($cancha['descripcion']) ?></textarea>
                <div class="invalid-feedback"><?= e($errores['descripcion'] ?? '') ?></div>
            </div>
            <div class="col-md-6">
                <label for="superficie" class="form-label">Superficie *</label>
                <input type="text" class="form-control<?= $invalido('superficie') ?>" id="superficie" name="superficie"
                       maxlength="60" placeholder="Grama sintética, cristal templado…" required value="<?= e($cancha['superficie']) ?>">
                <div class="invalid-feedback"><?= e($errores['superficie'] ?? '') ?></div>
            </div>
            <div class="col-md-3">
                <label for="capacidad_jugadores" class="form-label">Capacidad (jugadores) *</label>
                <input type="number" class="form-control<?= $invalido('capacidad_jugadores') ?>" id="capacidad_jugadores"
                       name="capacidad_jugadores" min="2" max="30" required value="<?= e($cancha['capacidad_jugadores']) ?>">
                <div class="invalid-feedback"><?= e($errores['capacidad_jugadores'] ?? '') ?></div>
            </div>
            <div class="col-md-3">
                <label for="estado" class="form-label">Estado *</label>
                <select class="form-select<?= $invalido('estado') ?>" id="estado" name="estado" required>
                    <?php foreach ($estados as $est): ?>
                        <option value="<?= e($est) ?>" <?= $cancha['estado'] === $est ? 'selected' : '' ?>><?= e($est) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback"><?= e($errores['estado'] ?? '') ?></div>
            </div>
            <div class="col-md-4">
                <label for="tarifa_diurna" class="form-label">Tarifa diurna ($/hora) *</label>
                <input type="number" class="form-control<?= $invalido('tarifa_diurna') ?>" id="tarifa_diurna"
                       name="tarifa_diurna" min="0.01" max="999" step="0.01" required value="<?= e($cancha['tarifa_diurna']) ?>">
                <div class="invalid-feedback"><?= e($errores['tarifa_diurna'] ?? '') ?></div>
            </div>
            <div class="col-md-4">
                <label for="tarifa_nocturna" class="form-label">Tarifa nocturna ($/hora) *</label>
                <input type="number" class="form-control<?= $invalido('tarifa_nocturna') ?>" id="tarifa_nocturna"
                       name="tarifa_nocturna" min="0.01" max="999" step="0.01" required value="<?= e($cancha['tarifa_nocturna']) ?>">
                <div class="invalid-feedback"><?= e($errores['tarifa_nocturna'] ?? '') ?></div>
            </div>
            <div class="col-md-4">
                <label for="hora_inicio_nocturna" class="form-label">Inicio tarifa nocturna *</label>
                <input type="time" class="form-control<?= $invalido('hora_inicio_nocturna') ?>" id="hora_inicio_nocturna"
                       name="hora_inicio_nocturna" required value="<?= e($cancha['hora_inicio_nocturna']) ?>">
                <div class="invalid-feedback"><?= e($errores['hora_inicio_nocturna'] ?? '') ?></div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="<?= e(url('admin/canchas.php')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-kz-verde">
                <i class="fa-solid fa-floppy-disk me-1"></i><?= $esEdicion ? 'Guardar cambios' : 'Crear cancha' ?>
            </button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
