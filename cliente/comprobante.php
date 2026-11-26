<?php
/**
 * KickZone - Comprobante imprimible de una reserva.
 * Un cliente solo puede ver sus propios comprobantes (verificación de propiedad);
 * el encargado y el administrador pueden ver cualquiera.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/reservas.php';
requiere_rol(ROL_CLIENTE, ROL_ENCARGADO, ROL_ADMIN);

$codigo = get_texto('codigo');
$reserva = preg_match('/^KZ-\d{4}-\d{4,}$/', $codigo) ? obtener_reserva_autorizada($codigo) : null;

if (!$reserva) {
    // Mismo mensaje si no existe o si es de otro cliente: no se revela qué códigos existen.
    flash('danger', 'No encontramos ese comprobante.');
    redirigir(ruta_inicio_rol($_SESSION['usuario']['rol']));
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT i.nombre, ri.cantidad, ri.precio_unitario, ri.subtotal
       FROM reserva_implementos ri JOIN implementos i ON i.id_implemento = ri.id_implemento
      WHERE ri.id_reserva = :id ORDER BY i.nombre'
);
$stmt->execute([':id' => $reserva['id_reserva']]);
$lineas = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT metodo_pago, referencia_pago, monto, estado_pago, fecha_pago FROM pagos WHERE id_reserva = :id ORDER BY id_pago DESC LIMIT 1');
$stmt->execute([':id' => $reserva['id_reserva']]);
$pago = $stmt->fetch();

$puedeCancelar = tiene_rol(ROL_CLIENTE) && in_array($reserva['estado'], ESTADOS_ACTIVOS, true);

$titulo = 'Comprobante ' . $reserva['codigo_reserva'];
$activo = 'mis_reservas';
require __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 d-print-none">
        <a href="<?= e(url(ruta_inicio_rol($_SESSION['usuario']['rol']))) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Volver
        </a>
        <div class="d-flex gap-2">
            <?php if ($puedeCancelar): ?>
                <form method="post" action="<?= e(url('cliente/cancelar.php')) ?>"
                      data-confirmar="¿Cancelar la reserva <?= e($reserva['codigo_reserva']) ?>?">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id_reserva" value="<?= (int) $reserva['id_reserva'] ?>">
                    <button class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-ban me-1"></i>Cancelar reserva</button>
                </form>
            <?php endif; ?>
            <button type="button" class="btn btn-kz-azul btn-sm" data-imprimir><i class="fa-solid fa-print me-1"></i>Imprimir</button>
        </div>
    </div>

    <article class="kz-comprobante card kz-card mx-auto">
        <header class="kz-comprobante-header d-flex justify-content-between align-items-start">
            <div>
                <div class="h4 fw-bold mb-0"><i class="fa-solid fa-futbol text-kz-verde me-1"></i>KickZone</div>
                <div class="small opacity-75">Centro Deportivo Integral</div>
            </div>
            <div class="text-end">
                <div class="small text-uppercase opacity-75">Comprobante de reserva</div>
                <div class="h5 fw-bold mb-0"><?= e($reserva['codigo_reserva']) ?></div>
            </div>
        </header>

        <div class="p-4">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-4">
                <span class="badge kz-estado kz-estado-<?= e($reserva['estado']) ?> fs-6"><?= e($reserva['estado']) ?></span>
                <span class="small text-muted">Emitido: <?= e(date('d/m/Y H:i', strtotime($reserva['fecha_creacion']))) ?></span>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-sm-6">
                    <h2 class="kz-comprobante-titulo">Cliente</h2>
                    <div class="fw-semibold"><?= e($reserva['cliente']) ?></div>
                    <div class="small text-muted"><?= e($reserva['correo']) ?> · <?= e($reserva['telefono']) ?></div>
                </div>
                <div class="col-sm-6">
                    <h2 class="kz-comprobante-titulo">Cancha</h2>
                    <div class="fw-semibold"><?= e($reserva['cancha']) ?></div>
                    <div class="small text-muted"><?= e($reserva['deporte']) ?> · <?= e($reserva['superficie']) ?></div>
                </div>
                <div class="col-sm-6">
                    <h2 class="kz-comprobante-titulo">Fecha y horario</h2>
                    <div class="fw-semibold">
                        <?= e(date('d/m/Y', strtotime($reserva['fecha_reserva']))) ?> ·
                        <?= e(substr($reserva['hora_inicio'], 0, 5)) ?>–<?= e(substr($reserva['hora_fin'], 0, 5)) ?>
                    </div>
                    <div class="small <?= (int) $reserva['es_tarifa_nocturna'] === 1 ? 'kz-tarifa-noche' : 'text-muted' ?>">
                        <?= (int) $reserva['es_tarifa_nocturna'] === 1 ? 'Incluye tarifa nocturna' : 'Tarifa diurna' ?>
                    </div>
                </div>
                <div class="col-sm-6">
                    <h2 class="kz-comprobante-titulo">Pago</h2>
                    <?php if ($pago): ?>
                        <div class="fw-semibold"><?= e(METODOS_PAGO[$pago['metodo_pago']] ?? $pago['metodo_pago']) ?>
                            <span class="badge kz-estado kz-estado-<?= e($pago['estado_pago']) ?> ms-1"><?= e($pago['estado_pago']) ?></span></div>
                        <div class="small text-muted"><?= $pago['referencia_pago'] ? 'Ref. ' . e($pago['referencia_pago']) : 'Sin referencia' ?></div>
                    <?php else: ?>
                        <div class="text-muted">Sin pago registrado</div>
                    <?php endif; ?>
                </div>
            </div>

            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Concepto</th><th class="text-center">Cant.</th><th class="text-end">Precio</th><th class="text-end">Subtotal</th></tr></thead>
                <tbody>
                <tr>
                    <td>Alquiler de cancha (<?= (int) ((strtotime($reserva['hora_fin']) - strtotime($reserva['hora_inicio'])) / 3600) ?> h)</td>
                    <td class="text-center">1</td>
                    <td class="text-end">—</td>
                    <td class="text-end">$<?= e(number_format((float) $reserva['subtotal_cancha'], 2)) ?></td>
                </tr>
                <?php foreach ($lineas as $l): ?>
                    <tr>
                        <td><?= e($l['nombre']) ?></td>
                        <td class="text-center"><?= (int) $l['cantidad'] ?></td>
                        <td class="text-end">$<?= e(number_format((float) $l['precio_unitario'], 2)) ?></td>
                        <td class="text-end">$<?= e(number_format((float) $l['subtotal'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr class="kz-comprobante-total">
                    <th colspan="3" class="text-end">TOTAL</th>
                    <th class="text-end">$<?= e(number_format((float) $reserva['total_pagar'], 2)) ?></th>
                </tr>
                </tfoot>
            </table>

            <?php if ($reserva['notas_cliente']): ?>
                <p class="small text-muted mt-3 mb-0"><strong>Notas:</strong> <?= e($reserva['notas_cliente']) ?></p>
            <?php endif; ?>
            <p class="small text-muted mt-3 mb-0">
                Presenta este código en recepción. Puedes cancelar sin costo hasta <?= HORAS_MIN_CANCELACION ?> horas antes del inicio.
            </p>
        </div>
    </article>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
