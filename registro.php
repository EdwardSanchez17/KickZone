<?php
/**
 * KickZone - Registro de clientes.
 * El rol se asigna en el servidor (siempre CLIENTE): no se acepta desde el formulario.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (esta_autenticado()) {
    redirigir(ruta_inicio_rol($_SESSION['usuario']['rol']));
}

$errores = [];
$datos = ['nombre' => '', 'apellido' => '', 'correo' => '', 'telefono' => ''];

if (es_post()) {
    csrf_verificar();

    $datos = [
        'nombre'   => post_texto('nombre'),
        'apellido' => post_texto('apellido'),
        'correo'   => mb_strtolower(post_texto('correo')),
        'telefono' => post_texto('telefono'),
    ];
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmar = is_string($_POST['confirmar'] ?? null) ? $_POST['confirmar'] : '';

    // --- Validaciones del servidor ---
    $soloLetras = '/^[\p{L} \'-]{2,80}$/u';
    if (!preg_match($soloLetras, $datos['nombre'])) {
        $errores[] = 'El nombre debe tener entre 2 y 80 letras.';
    }
    if (!preg_match($soloLetras, $datos['apellido'])) {
        $errores[] = 'El apellido debe tener entre 2 y 80 letras.';
    }
    if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($datos['correo']) > 120) {
        $errores[] = 'Ingresa un correo electrónico válido.';
    }
    if (!preg_match('/^\+?[0-9 \-]{7,20}$/', $datos['telefono'])) {
        $errores[] = 'Ingresa un teléfono válido (ej. 6000-0000).';
    }
    foreach (validar_politica_password($password) as $regla) {
        $errores[] = 'Contraseña: ' . $regla;
    }
    if ($password !== $confirmar) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        $pdo = db();
        $existe = $pdo->prepare('SELECT 1 FROM usuarios WHERE correo = :correo LIMIT 1');
        $existe->execute([':correo' => $datos['correo']]);

        if ($existe->fetchColumn()) {
            $errores[] = 'Ya existe una cuenta registrada con ese correo.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (id_rol, nombre, apellido, correo, telefono, password_hash)
                 VALUES (:rol, :nombre, :apellido, :correo, :telefono, :hash)'
            );
            $stmt->execute([
                ':rol'      => ID_ROL_CLIENTE,
                ':nombre'   => $datos['nombre'],
                ':apellido' => $datos['apellido'],
                ':correo'   => $datos['correo'],
                ':telefono' => $datos['telefono'],
                ':hash'     => password_hash($password, PASSWORD_DEFAULT),
            ]);

            flash('success', 'Cuenta creada correctamente. Ya puedes iniciar sesión.');
            redirigir('login.php');
        }
    }
}

$titulo = 'Crear cuenta';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="kz-auth-wrapper mx-auto">
        <div class="card kz-card kz-auth-card p-4">
            <div class="text-center mb-3">
                <i class="fa-solid fa-user-plus fa-2x text-kz-verde"></i>
                <h1 class="h4 fw-bold mt-2 mb-0">Crea tu cuenta</h1>
                <p class="text-muted small">Reserva canchas en segundos, sin llamadas</p>
            </div>

            <?php if ($errores): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Revisa los siguientes campos:</strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach ($errores as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('registro.php')) ?>" class="needs-validation" novalidate>
                <?= csrf_campo() ?>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" maxlength="80"
                               value="<?= e($datos['nombre']) ?>" required autocomplete="given-name">
                        <div class="invalid-feedback">Ingresa tu nombre.</div>
                    </div>
                    <div class="col-sm-6">
                        <label for="apellido" class="form-label">Apellido</label>
                        <input type="text" class="form-control" id="apellido" name="apellido" maxlength="80"
                               value="<?= e($datos['apellido']) ?>" required autocomplete="family-name">
                        <div class="invalid-feedback">Ingresa tu apellido.</div>
                    </div>
                    <div class="col-12">
                        <label for="correo" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="correo" name="correo" maxlength="120"
                               value="<?= e($datos['correo']) ?>" required autocomplete="email">
                        <div class="invalid-feedback">Ingresa un correo válido.</div>
                    </div>
                    <div class="col-12">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" maxlength="20"
                               placeholder="6000-0000" value="<?= e($datos['telefono']) ?>" required autocomplete="tel">
                        <div class="invalid-feedback">Ingresa tu teléfono.</div>
                    </div>
                    <div class="col-12">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password"
                                   minlength="8" maxlength="72" required autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="password"
                                    aria-label="Mostrar contraseña"><i class="fa-solid fa-eye"></i></button>
                        </div>
                        <ul class="kz-requisitos list-unstyled mt-2 mb-0" id="requisitosPassword">
                            <li data-regla="largo">Mínimo 8 caracteres</li>
                            <li data-regla="mayuscula">Una letra mayúscula</li>
                            <li data-regla="minuscula">Una letra minúscula</li>
                            <li data-regla="numero">Un número</li>
                            <li data-regla="especial">Un carácter especial (* ! # $ …)</li>
                        </ul>
                    </div>
                    <div class="col-12">
                        <label for="confirmar" class="form-label">Confirmar contraseña</label>
                        <input type="password" class="form-control" id="confirmar" name="confirmar"
                               minlength="8" maxlength="72" required autocomplete="new-password">
                        <div class="invalid-feedback">Confirma tu contraseña.</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-kz-verde w-100 py-2 mt-4">
                    <i class="fa-solid fa-check me-1"></i>Crear cuenta
                </button>
            </form>

            <p class="text-center small mt-3 mb-0">
                ¿Ya tienes cuenta? <a href="<?= e(url('login.php')) ?>" class="fw-semibold">Inicia sesión</a>
            </p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
