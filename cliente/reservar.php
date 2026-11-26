<?php
/**
 * KickZone - Nueva reserva (cliente).
 * Paso 1: fecha y bloque horario · Paso 2: implementos opcionales · Resumen y pago.
 * El precio final SIEMPRE se calcula en el servidor (includes/reservas.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/reservas.php';
requiere_rol(ROL_CLIENTE);

$pdo = db();
$idCliente = (int) $_SESSION['usuario']['id'];

$idCancha = filter_var($_GET['cancha'] ?? $_POST['id_cancha'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$cancha = $idCancha ? obtener_cancha_reservable($idCancha) : null;

$errores = [];
$seleccion = ['hora' => null, 'duracion' => 1, 'metodo' => 'YAPPY', 'notas' => '', 'implementos' => []];

$fechaTexto = es_post() ? post_texto('fecha') : get_texto('fecha');
$fechaObj = validar_fecha_reserva($fechaTexto !== '' ? $fechaTexto : date('Y-m-d'));
if (!$fechaObj) {
    if ($fechaTexto !== '') {
        $errores[] = 'La fecha elegida no es válida. Puedes reservar desde hoy hasta ' . DIAS_MAX_ANTICIPACION . ' días adelante.';
    }
    $fechaObj = new DateTimeImmutable('today');
}
$fecha = $fechaObj->format('Y-m-d');

/* ---------------------------------------------------------------------------
 * Confirmar reserva (POST)
 * ------------------------------------------------------------------------- */
if (es_post()) {
    csrf_verificar();

    $hora = filter_var($_POST['hora'] ?? null, FILTER_VALIDATE_INT);
    $duracion = filter_var($_POST['duracion'] ?? null, FILTER_VALIDATE_INT);
    $metodo = post_texto('metodo_pago');
    $notas = post_texto('notas');

    $implementos = [];
    $entrada = $_POST['implementos'] ?? [];
    if (is_array($entrada)) {
        foreach ($entrada as $idImp => $cant) {
            $idImp = filter_var($idImp, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $cant = filter_var($cant, FILTER_VALIDATE_INT);
            if ($idImp !== false && $cant !== false) {
                $implementos[$idImp] = $cant;
            }
        }
    }

    $seleccion = [
        'hora'        => $hora === false ? null : $hora,
        'duracion'    => $duracion === false ? 1 : $duracion,
        'metodo'      => $metodo,
        'notas'       => $notas,
        'implementos' => $implementos,
    ];

    if (!$cancha) {
        $errores[] = 'La cancha seleccionada no está disponible.';
    } elseif ($hora === false || $hora === null) {
        $errores[] = 'Selecciona un bloque horario.';
    } elseif ($duracion === false) {
        $errores[] = 'Selecciona la duración.';
    } elseif (!$errores) {
        try {
            $reserva = crear_reserva($idCliente, $idCancha, $fecha, $hora, $duracion, $implementos, $metodo, $notas);
            flash('success', sprintf('¡Reserva %s registrada! Total: $%s. El encargado verificará tu pago.',
                $reserva['codigo'], number_format($reserva['total'], 2)));
            redirigir('cliente/comprobante.php?codigo=' . urlencode($reserva['codigo']));
        } catch (ReservaException $ex) {
            $errores[] = $ex->getMessage();
        }
    }
}

/* ---------------------------------------------------------------------------
 * Datos para la vista
 * ------------------------------------------------------------------------- */
if ($cancha) {
    $bloques = bloques_disponibles($cancha, $fechaObj);
    $implementosDisp = implementos_para_cancha($cancha);
} else {
    $canchas = $pdo->query(
        "SELECT c.id_cancha, c.nombre, c.superficie, c.tarifa_diurna, c.tarifa_nocturna, d.nombre AS deporte
           FROM canchas c JOIN deportes d ON d.id_deporte = c.id_deporte
          WHERE c.estado = 'DISPONIBLE'
          ORDER BY d.id_deporte, c.nombre"
    )->fetchAll();
}

$titulo = 'Nueva reserva';
$activo = 'reservar';
$scriptsExtra = ['assets/js/reserva.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
<?php if (!$cancha): ?>
    <h1 class="h3 kz-titulo-seccion mb-1"><i class="fa-solid fa-calendar-plus me-2"></i>Nueva reserva</h1>
    <p class="text-muted">Elige la cancha que quieres reservar.</p>
    <?php foreach ($errores as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>
    <div class="row g-3">
        <?php foreach ($canchas as $c): ?>
            <div class="col-md-6 col-lg-4">
                <a class="kz-card d-block p-3 h-100 text-decoration-none text-reset"
                   href="<?= e(url('cliente/reservar.php?cancha=' . (int) $c['id_cancha'])) ?>">
                    <span class="badge text-bg-light mb-2"><?= e($c['deporte']) ?></span>
                    <h2 class="h6 fw-bold mb-1"><?= e($c['nombre']) ?></h2>
                    <div class="small text-muted mb-2"><?= e($c['superficie']) ?></div>
                    <span class="kz-tarifa-dia small">Día $<?= e(number_format((float) $c['tarifa_diurna'], 2)) ?></span> ·
                    <span class="kz-tarifa-noche small">Noche $<?= e(number_format((float) $c['tarifa_nocturna'], 2)) ?></span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= e(url('cliente/reservar.php')) ?>">Canchas</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($cancha['nombre']) ?></li>
        </ol>
    </nav>
    <h1 class="h3 kz-titulo-seccion mb-0">Reservar: <?= e($cancha['nombre']) ?></h1>
    <p class="text-muted"><?= e($cancha['deporte']) ?> · <?= e($cancha['superficie']) ?> · Hasta <?= (int) $cancha['capacidad_jugadores'] ?> jugadores</p>

    <?php if ($errores): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach ($errores as $err): ?>
                <div><i class="fa-solid fa-circle-exclamation me-1"></i><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="get" action="<?= e(url('cliente/reservar.php')) ?>" class="mb-3" id="formFecha">
        <input type="hidden" name="cancha" value="<?= (int) $cancha['id_cancha'] ?>">
        <div class="row g-2 align-items-end">
            <div class="col-sm-5 col-lg-3">
                <label for="fechaConsulta" class="form-label">Fecha</label>
                <input type="date" class="form-control" id="fechaConsulta" name="fecha" value="<?= e($fecha) ?>"
                       min="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d', strtotime('+' . DIAS_MAX_ANTICIPACION . ' days'))) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-kz-azul"><i class="fa-solid fa-rotate me-1"></i>Ver horarios</button>
            </div>
        </div>
    </form>

    <form method="post" action="<?= e(url('cliente/reservar.php?cancha=' . (int) $cancha['id_cancha'])) ?>" id="formReserva"
          data-tarifa-dia="<?= e($cancha['tarifa_diurna']) ?>"
          data-tarifa-noche="<?= e($cancha['tarifa_nocturna']) ?>"
          data-hora-noche="<?= hora_nocturna($cancha) ?>"
          data-disponibilidad-url="<?= e(url('cliente/disponibilidad.php')) ?>">
        <?= csrf_campo() ?>
        <input type="hidden" name="id_cancha" value="<?= (int) $cancha['id_cancha'] ?>">
        <input type="hidden" name="fecha" value="<?= e($fecha) ?>" id="fechaReserva">

        <div class="row g-4">
            <div class="col-lg-8">
                <section class="card kz-card p-4 mb-4">
                    <h2 class="h5 fw-bold mb-3">Paso 1 · Bloque horario y duración</h2>
                    <div class="kz-bloques mb-3" id="bloques" role="radiogroup" aria-label="Bloques horarios">
                        <?php foreach ($bloques as $b): ?>
                            <?php $libre = $b['estado'] === 'libre'; ?>
                            <input type="radio" class="btn-check" name="hora" id="hora<?= $b['hora'] ?>" value="<?= $b['hora'] ?>"
                                   <?= $libre ? '' : 'disabled' ?> <?= $seleccion['hora'] === $b['hora'] ? 'checked' : '' ?>
                                   data-nocturna="<?= $b['nocturna'] ? 1 : 0 ?>">
                            <label class="kz-bloque kz-bloque-<?= e($b['estado']) ?><?= $b['nocturna'] ? ' kz-bloque-noche' : '' ?>" for="hora<?= $b['hora'] ?>">
                                <span class="kz-bloque-hora"><?= e($b['etiqueta']) ?></span>
                                <span class="kz-bloque-estado">
                                    <?= $b['estado'] === 'libre' ? ($b['nocturna'] ? 'Nocturna' : 'Libre') : ($b['estado'] === 'ocupado' ? 'Ocupado' : 'Pasado') ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="row g-3 align-items-end">
                        <div class="col-sm-4">
                            <label for="duracion" class="form-label">Duración</label>
                            <select class="form-select" id="duracion" name="duracion">
                                <?php for ($d = 1; $d <= DURACION_MAX_HORAS; $d++): ?>
                                    <option value="<?= $d ?>" <?= (int) $seleccion['duracion'] === $d ? 'selected' : '' ?>><?= $d ?> hora<?= $d > 1 ? 's' : '' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-sm-8 small text-kz-ambar fw-semibold">
                            <i class="fa-solid fa-moon me-1"></i>Desde las <?= e(substr($cancha['hora_inicio_nocturna'], 0, 5)) ?> se aplica la tarifa nocturna
                            ($<?= e(number_format((float) $cancha['tarifa_nocturna'], 2)) ?>/h).
                        </div>
                    </div>
                </section>

                <section class="card kz-card p-4">
                    <h2 class="h5 fw-bold mb-3">Paso 2 · Implementos opcionales</h2>
                    <?php if (!$implementosDisp): ?>
                        <p class="text-muted mb-0">No hay implementos en alquiler para esta disciplina.</p>
                    <?php endif; ?>
                    <?php foreach ($implementosDisp as $imp): ?>
                        <?php $id = (int) $imp['id_implemento']; $max = min(MAX_CANTIDAD_IMPLEMENTO, (int) $imp['disponible']); ?>
                        <div class="kz-implemento d-flex align-items-center gap-3 border rounded p-2 mb-2">
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= e($imp['nombre']) ?></div>
                                <div class="small text-muted"><?= e($imp['descripcion']) ?></div>
                            </div>
                            <span class="kz-tarifa-dia text-nowrap">$<?= e(number_format((float) $imp['precio_alquiler'], 2)) ?></span>
                            <input type="number" class="form-control form-control-sm kz-cantidad" name="implementos[<?= $id ?>]"
                                   min="0" max="<?= $max ?>" value="<?= (int) ($seleccion['implementos'][$id] ?? 0) ?>"
                                   data-precio="<?= e($imp['precio_alquiler']) ?>" data-nombre="<?= e($imp['nombre']) ?>"
                                   aria-label="Cantidad de <?= e($imp['nombre']) ?>">
                        </div>
                    <?php endforeach; ?>
                    <p class="small text-muted mb-0 mt-2">El stock se confirma para el horario elegido al registrar la reserva.</p>
                </section>
            </div>

            <div class="col-lg-4">
                <aside class="card kz-card kz-resumen">
                    <div class="card-header bg-kz-azul text-white fw-bold">RESUMEN DE RESERVA</div>
                    <div class="card-body">
                        <dl class="row small mb-2">
                            <dt class="col-6 fw-normal text-muted">Cancha</dt><dd class="col-6 text-end fw-semibold"><?= e($cancha['nombre']) ?></dd>
                            <dt class="col-6 fw-normal text-muted">Fecha</dt><dd class="col-6 text-end fw-semibold"><?= e($fechaObj->format('d/m/Y')) ?></dd>
                            <dt class="col-6 fw-normal text-muted">Horario</dt><dd class="col-6 text-end fw-semibold" id="resumenHorario">—</dd>
                            <dt class="col-6 fw-normal text-muted">Tarifa</dt><dd class="col-6 text-end fw-semibold" id="resumenTarifa">—</dd>
                            <dt class="col-6 fw-normal text-muted">Subtotal cancha</dt><dd class="col-6 text-end fw-semibold" id="resumenCancha">—</dd>
                            <dt class="col-6 fw-normal text-muted">Subtotal implementos</dt><dd class="col-6 text-end fw-semibold" id="resumenImplementos">—</dd>
                        </dl>
                        <div class="mb-3">
                            <label for="metodo_pago" class="form-label">Método de pago</label>
                            <select class="form-select" id="metodo_pago" name="metodo_pago">
                                <?php foreach (METODOS_PAGO as $clave => $nombre): ?>
                                    <option value="<?= e($clave) ?>" <?= $seleccion['metodo'] === $clave ? 'selected' : '' ?>><?= e($nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="notas" class="form-label">Notas (opcional)</label>
                            <textarea class="form-control" id="notas" name="notas" rows="2" maxlength="255"><?= e($seleccion['notas']) ?></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-3 mb-3">
                            <span class="fw-bold text-kz-azul">TOTAL A PAGAR</span>
                            <span class="h4 fw-bold kz-tarifa-dia mb-0" id="resumenTotal">—</span>
                        </div>
                        <button type="submit" class="btn btn-kz-verde w-100 py-2">
                            <i class="fa-solid fa-check me-1"></i>Confirmar reserva
                        </button>
                        <p class="small text-muted mt-2 mb-0">El total definitivo se calcula en el servidor al confirmar.</p>
                    </div>
                </aside>
            </div>
        </div>
    </form>
<?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
