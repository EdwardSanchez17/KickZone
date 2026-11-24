<?php
/**
 * KickZone - Encabezado y menú de navegación común.
 * Variables opcionales antes de incluir:
 *   $titulo  (string) título de la pestaña
 *   $activo  (string) clave del enlace activo del menú
 */

$titulo = isset($titulo) ? $titulo . ' | ' . APP_NOMBRE : APP_NOMBRE . ' — Centro Deportivo Integral';
$activo = $activo ?? '';
$u = usuario_actual();

/** Devuelve la clase "active" para el enlace del menú actual. */
$claseActivo = static fn(string $clave): string => $activo === $clave ? ' active' : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet"
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="<?= e(url('assets/css/estilos.css')) ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark kz-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>">
            <i class="fa-solid fa-futbol me-1 text-kz-verde"></i> Kick<span class="text-kz-verde">Zone</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal"
                aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link<?= $claseActivo('inicio') ?>" href="<?= e(url('index.php')) ?>">Inicio</a></li>
                <li class="nav-item"><a class="nav-link<?= $claseActivo('canchas') ?>" href="<?= e(url('index.php#canchas')) ?>">Canchas</a></li>
                <li class="nav-item"><a class="nav-link<?= $claseActivo('implementos') ?>" href="<?= e(url('index.php#implementos')) ?>">Implementos</a></li>

                <?php if (tiene_rol(ROL_ADMIN)): ?>
                    <li class="nav-item"><a class="nav-link<?= $claseActivo('dashboard') ?>" href="<?= e(url('admin/dashboard.php')) ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link<?= $claseActivo('admin_canchas') ?>" href="<?= e(url('admin/canchas.php')) ?>">Gestión de canchas</a></li>
                    <li class="nav-item"><a class="nav-link<?= $claseActivo('usuarios') ?>" href="<?= e(url('admin/usuarios.php')) ?>">Usuarios</a></li>
                <?php elseif (tiene_rol(ROL_ENCARGADO)): ?>
                    <li class="nav-item"><a class="nav-link<?= $claseActivo('agenda') ?>" href="<?= e(url('encargado/dashboard.php')) ?>">Agenda del día</a></li>
                <?php elseif (tiene_rol(ROL_CLIENTE)): ?>
                    <li class="nav-item"><a class="nav-link<?= $claseActivo('mis_reservas') ?>" href="<?= e(url('cliente/panel.php')) ?>">Mis reservas</a></li>
                <?php endif; ?>
            </ul>

            <?php if ($u): ?>
                <div class="d-flex align-items-center gap-2">
                    <span class="navbar-text small">
                        <i class="fa-solid fa-circle-user me-1"></i><?= e($u['nombre']) ?>
                        <span class="badge kz-badge-rol ms-1"><?= e($u['rol']) ?></span>
                    </span>
                    <form action="<?= e(url('logout.php')) ?>" method="post" class="m-0">
                        <?= csrf_campo() ?>
                        <button type="submit" class="btn btn-sm btn-outline-light">
                            <i class="fa-solid fa-right-from-bracket me-1"></i>Salir
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-light" href="<?= e(url('login.php')) ?>">Iniciar sesión</a>
                    <a class="btn btn-sm btn-kz-verde" href="<?= e(url('registro.php')) ?>">Registrarse</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="flex-grow-1">
    <?php $mensajes = obtener_flash(); ?>
    <?php if ($mensajes): ?>
        <div class="container mt-3">
            <?php foreach ($mensajes as $m): ?>
                <div class="alert alert-<?= e($m['tipo']) ?> alert-dismissible fade show" role="alert">
                    <?= e($m['mensaje']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
