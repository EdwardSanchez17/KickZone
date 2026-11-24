<?php
/**
 * KickZone - Inicio de sesión.
 *  - password_verify() contra el hash Bcrypt almacenado
 *  - Bloqueo temporal tras LOGIN_MAX_INTENTOS intentos fallidos (fuerza bruta)
 *  - session_regenerate_id(true) al autenticar (evita fijación de sesión)
 *  - Mensaje de error genérico (no revela si el correo existe)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (esta_autenticado()) {
    redirigir(ruta_inicio_rol($_SESSION['usuario']['rol']));
}

$errores = [];
$correo = '';

if (es_post()) {
    csrf_verificar();

    $correo = mb_strtolower(post_texto('correo'));
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if ($correo === '' || $password === '') {
        $errores[] = 'Ingresa tu correo y tu contraseña.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El formato del correo no es válido.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.password_hash, u.estado,
                    u.intentos_fallidos, u.bloqueado_hasta, r.nombre_rol,
                    (u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW()) AS esta_bloqueado,
                    GREATEST(CEIL(TIMESTAMPDIFF(SECOND, NOW(), u.bloqueado_hasta) / 60), 1) AS minutos_restantes
               FROM usuarios u
               JOIN roles r ON r.id_rol = u.id_rol
              WHERE u.correo = :correo
              LIMIT 1'
        );
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch();

        if ($usuario && (int) $usuario['esta_bloqueado'] === 1) {
            $errores[] = sprintf(
                'Cuenta bloqueada temporalmente por múltiples intentos fallidos. Intenta de nuevo en %d minuto(s).',
                (int) $usuario['minutos_restantes']
            );
        } else {
            // Si el correo no existe, se verifica contra un hash ficticio para que
            // el tiempo de respuesta sea similar y no se puedan enumerar cuentas.
            $hash = $usuario['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
            $passwordOk = password_verify($password, $hash);

            if ($usuario && $passwordOk) {
                if ($usuario['estado'] !== 'ACTIVO') {
                    $errores[] = 'Tu cuenta está inactiva. Contacta al administrador.';
                } else {
                    // Éxito: reiniciar contador de intentos
                    $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = :id')
                        ->execute([':id' => $usuario['id_usuario']]);

                    // Actualizar el hash si PHP recomienda un algoritmo/costo más nuevo
                    if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
                        $pdo->prepare('UPDATE usuarios SET password_hash = :h WHERE id_usuario = :id')
                            ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $usuario['id_usuario']]);
                    }

                    session_regenerate_id(true); // nuevo ID de sesión tras autenticar
                    unset($_SESSION['csrf_token']); // nuevo token para la sesión autenticada
                    $_SESSION['usuario'] = [
                        'id'     => (int) $usuario['id_usuario'],
                        'nombre' => $usuario['nombre'] . ' ' . $usuario['apellido'],
                        'correo' => $usuario['correo'],
                        'rol'    => $usuario['nombre_rol'],
                    ];
                    $_SESSION['ultima_actividad'] = time();

                    flash('success', '¡Bienvenido, ' . $usuario['nombre'] . '!');
                    redirigir(ruta_inicio_rol($usuario['nombre_rol']));
                }
            } else {
                if ($usuario) {
                    $intentos = (int) $usuario['intentos_fallidos'] + 1;
                    if ($intentos >= LOGIN_MAX_INTENTOS) {
                        $pdo->prepare(
                            'UPDATE usuarios
                                SET intentos_fallidos = 0,
                                    bloqueado_hasta = DATE_ADD(NOW(), INTERVAL :min MINUTE)
                              WHERE id_usuario = :id'
                        )->execute([':min' => LOGIN_MINUTOS_BLOQUEO, ':id' => $usuario['id_usuario']]);
                        error_log("[KickZone] Cuenta bloqueada por fuerza bruta: {$correo}");
                    } else {
                        $pdo->prepare('UPDATE usuarios SET intentos_fallidos = :i WHERE id_usuario = :id')
                            ->execute([':i' => $intentos, ':id' => $usuario['id_usuario']]);
                    }
                }
                $errores[] = 'Correo o contraseña incorrectos.';
            }
        }
    }
}

$titulo = 'Iniciar sesión';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="kz-auth-wrapper mx-auto">
        <div class="card kz-card kz-auth-card p-4">
            <div class="text-center mb-3">
                <i class="fa-solid fa-futbol fa-2x text-kz-verde"></i>
                <h1 class="h4 fw-bold mt-2 mb-0">Inicia sesión</h1>
                <p class="text-muted small">Accede a tus reservas y paneles de KickZone</p>
            </div>

            <?php if ($errores): ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ($errores as $err): ?>
                        <div><i class="fa-solid fa-circle-exclamation me-1"></i><?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('login.php')) ?>" class="needs-validation" novalidate>
                <?= csrf_campo() ?>
                <div class="mb-3">
                    <label for="correo" class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control" id="correo" name="correo" maxlength="120"
                           value="<?= e($correo) ?>" required autocomplete="email" autofocus>
                    <div class="invalid-feedback">Ingresa un correo válido.</div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" maxlength="72"
                               required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="password"
                                aria-label="Mostrar contraseña"><i class="fa-solid fa-eye"></i></button>
                        <div class="invalid-feedback">Ingresa tu contraseña.</div>
                    </div>
                </div>
                <button type="submit" class="btn btn-kz-verde w-100 py-2">
                    <i class="fa-solid fa-right-to-bracket me-1"></i>Entrar
                </button>
            </form>

            <p class="text-center small mt-3 mb-0">
                ¿No tienes cuenta? <a href="<?= e(url('registro.php')) ?>" class="fw-semibold">Regístrate aquí</a>
            </p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
