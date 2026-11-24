<?php
/**
 * KickZone - Página 403. Se muestra cuando un usuario intenta entrar
 * a una sección que su rol no permite (control hecho en requiere_rol()).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

http_response_code(403);
$titulo = 'Acceso denegado';
$inicio = esta_autenticado() ? ruta_inicio_rol($_SESSION['usuario']['rol']) : 'index.php';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-5 text-center">
    <i class="fa-solid fa-lock fa-3x text-kz-ambar mb-3"></i>
    <h1 class="h3 fw-bold">Acceso denegado</h1>
    <p class="text-muted">No tienes permisos para ver esta sección con tu rol actual.</p>
    <a href="<?= e(url($inicio)) ?>" class="btn btn-kz-azul mt-2">
        <i class="fa-solid fa-house me-1"></i>Volver a mi inicio
    </a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
