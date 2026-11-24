<?php
/**
 * KickZone - Portal público: catálogo de canchas filtrable por deporte
 * y catálogo de implementos en alquiler.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$pdo = db();

$deportes = $pdo->query('SELECT id_deporte, nombre, icono FROM deportes ORDER BY id_deporte')->fetchAll();
$idsDeportes = array_map('intval', array_column($deportes, 'id_deporte'));

$filtro = filter_var($_GET['deporte'] ?? null, FILTER_VALIDATE_INT);
$filtro = ($filtro !== false && in_array($filtro, $idsDeportes, true)) ? $filtro : null;

$sql = "SELECT c.id_cancha, c.id_deporte, c.nombre, c.descripcion, c.superficie, c.capacidad_jugadores,
               c.tarifa_diurna, c.tarifa_nocturna, c.hora_inicio_nocturna, c.estado,
               d.nombre AS deporte, d.icono,
               ROUND(AVG(re.calificacion), 1) AS promedio, COUNT(re.id_resena) AS total_resenas
          FROM canchas c
          JOIN deportes d ON d.id_deporte = c.id_deporte
          LEFT JOIN resenas re ON re.id_cancha = c.id_cancha
         WHERE c.estado <> 'INACTIVA'";
$parametros = [];
if ($filtro) {
    $sql .= ' AND c.id_deporte = :deporte';
    $parametros[':deporte'] = $filtro;
}
$sql .= ' GROUP BY c.id_cancha ORDER BY c.id_deporte, c.nombre';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$canchas = $stmt->fetchAll();

$implementos = $pdo->query(
    "SELECT i.nombre, i.descripcion, i.precio_alquiler, i.stock_total, d.nombre AS deporte
       FROM implementos i
       LEFT JOIN deportes d ON d.id_deporte = i.id_deporte
      WHERE i.estado = 'ACTIVO'
      ORDER BY d.nombre, i.nombre"
)->fetchAll();

$totalCanchas = (int) $pdo->query("SELECT COUNT(*) FROM canchas WHERE estado <> 'INACTIVA'")->fetchColumn();

/** Icono FontAwesome según el deporte (lista blanca, nunca HTML libre desde la BD). */
function icono_deporte(int $idDeporte): string
{
    return match ($idDeporte) {
        1, 2    => 'fa-futbol',
        3       => 'fa-table-tennis-paddle-ball',
        4       => 'fa-basketball',
        default => 'fa-medal',
    };
}

$titulo = null;
$activo = 'inicio';
require __DIR__ . '/includes/header.php';
?>

<section class="kz-hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-success bg-opacity-25 text-white mb-3">
                    <i class="fa-solid fa-bolt me-1"></i>Reservas en línea 24/7
                </span>
                <h1 class="display-5 mb-3">Tu cancha lista en segundos, sin llamadas ni WhatsApp.</h1>
                <p class="lead mb-4">
                    Consulta disponibilidad en tiempo real, reserva Fútbol 5, Fútbol 7, Pádel o Baloncesto
                    y alquila implementos en un solo paso.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="#canchas" class="btn btn-kz-verde btn-lg"><i class="fa-solid fa-calendar-check me-1"></i>Ver canchas</a>
                    <?php if (!esta_autenticado()): ?>
                        <a href="<?= e(url('registro.php')) ?>" class="btn btn-outline-light btn-lg">Crear cuenta gratis</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-6"><div class="kz-hero-dato"><div class="h3 fw-bold mb-0"><?= $totalCanchas ?></div><small>canchas activas</small></div></div>
                    <div class="col-6"><div class="kz-hero-dato"><div class="h3 fw-bold mb-0"><?= count($deportes) ?></div><small>disciplinas</small></div></div>
                    <div class="col-6"><div class="kz-hero-dato"><div class="h3 fw-bold mb-0">7–23 h</div><small>horario diario</small></div></div>
                    <div class="col-6"><div class="kz-hero-dato"><div class="h3 fw-bold mb-0 text-kz-ambar"><i class="fa-solid fa-moon"></i> 18:00</div><small>tarifa nocturna</small></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="canchas" class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h2 class="kz-titulo-seccion mb-1">Nuestras canchas</h2>
            <p class="text-muted mb-0">Filtra por disciplina y compara tarifas diurnas y nocturnas.</p>
        </div>
        <div class="kz-filtros d-flex flex-wrap gap-2">
            <a href="<?= e(url('index.php#canchas')) ?>" class="btn btn-sm btn-outline-secondary<?= $filtro === null ? ' active' : '' ?>">Todas</a>
            <?php foreach ($deportes as $d): ?>
                <a href="<?= e(url('index.php?deporte=' . (int) $d['id_deporte'] . '#canchas')) ?>"
                   class="btn btn-sm btn-outline-secondary<?= $filtro === (int) $d['id_deporte'] ? ' active' : '' ?>">
                    <i class="fa-solid <?= e(icono_deporte((int) $d['id_deporte'])) ?> me-1"></i><?= e($d['nombre']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$canchas): ?>
        <div class="alert alert-info">No hay canchas disponibles para esta disciplina por ahora.</div>
    <?php endif; ?>

    <div class="row g-4">
        <?php foreach ($canchas as $c): ?>
            <?php $idDep = (int) $c['id_deporte']; ?>
            <div class="col-sm-6 col-lg-4">
                <article class="kz-card h-100 d-flex flex-column">
                    <div class="kz-card-portada kz-portada-<?= $idDep <= 4 ? $idDep : 0 ?>">
                        <i class="fa-solid <?= e(icono_deporte($idDep)) ?>"></i>
                    </div>
                    <div class="p-3 d-flex flex-column flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="badge text-bg-light"><?= e($c['deporte']) ?></span>
                            <span class="badge kz-estado kz-estado-<?= e($c['estado']) ?>"><?= e($c['estado']) ?></span>
                        </div>
                        <h3 class="h5 fw-bold mb-1"><?= e($c['nombre']) ?></h3>
                        <p class="small text-muted mb-2"><?= e($c['descripcion']) ?></p>
                        <ul class="list-unstyled small mb-3">
                            <li><i class="fa-solid fa-layer-group me-1 text-kz-azul"></i><?= e($c['superficie']) ?></li>
                            <li><i class="fa-solid fa-users me-1 text-kz-azul"></i>Hasta <?= (int) $c['capacidad_jugadores'] ?> jugadores</li>
                            <li class="kz-estrellas">
                                <i class="fa-solid fa-star me-1"></i>
                                <?= $c['promedio'] !== null ? e($c['promedio']) . ' (' . (int) $c['total_resenas'] . ' reseñas)' : '<span class="text-muted">Sin reseñas aún</span>' ?>
                            </li>
                        </ul>
                        <div class="d-flex justify-content-between border-top pt-2 mt-auto">
                            <div>
                                <small class="text-muted d-block"><i class="fa-solid fa-sun me-1"></i>Día</small>
                                <span class="kz-tarifa-dia">$<?= e(number_format((float) $c['tarifa_diurna'], 2)) ?>/h</span>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block"><i class="fa-solid fa-moon me-1"></i>Noche (desde <?= e(substr($c['hora_inicio_nocturna'], 0, 5)) ?>)</small>
                                <span class="kz-tarifa-noche">$<?= e(number_format((float) $c['tarifa_nocturna'], 2)) ?>/h</span>
                            </div>
                        </div>
                        <?php if ($c['estado'] === 'DISPONIBLE'): ?>
                            <a href="<?= e(url(esta_autenticado() ? 'cliente/panel.php' : 'login.php')) ?>" class="btn btn-kz-verde w-100 mt-3">
                                <i class="fa-solid fa-calendar-plus me-1"></i>Ver horarios y reservar
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-secondary w-100 mt-3" disabled>
                                <i class="fa-solid fa-screwdriver-wrench me-1"></i>En mantenimiento
                            </button>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section id="implementos" class="bg-white border-top border-bottom py-5">
    <div class="container">
        <h2 class="kz-titulo-seccion mb-1">Implementos en alquiler</h2>
        <p class="text-muted mb-4">Agrégalos a tu reserva y te esperan listos en la cancha.</p>
        <div class="row g-3">
            <?php foreach ($implementos as $i): ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="kz-card p-3 h-100">
                        <div class="d-flex justify-content-between">
                            <h3 class="h6 fw-bold mb-1"><?= e($i['nombre']) ?></h3>
                            <span class="kz-tarifa-dia">$<?= e(number_format((float) $i['precio_alquiler'], 2)) ?></span>
                        </div>
                        <p class="small text-muted mb-2"><?= e($i['descripcion']) ?></p>
                        <div class="small">
                            <span class="badge text-bg-light"><?= e($i['deporte'] ?? 'Multideporte') ?></span>
                            <span class="text-muted ms-1"><i class="fa-solid fa-boxes-stacked me-1"></i><?= (int) $i['stock_total'] ?> en stock</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
