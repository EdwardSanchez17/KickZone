# 🔒 KickZone — Evidencias de seguridad (Entregable 3)

Pruebas realizadas el 26/11/2026 sobre la instalación local (MAMP · PHP 8.3 · MySQL 8.0) en
`http://localhost:8888/KickZone/`. Cada captura corresponde a un control exigido en la sección 7 del
enunciado del proyecto.

| # | Control | Prueba realizada | Resultado esperado | Captura |
|---|---|---|---|---|
| 1 | Prevención de inyección SQL (7.3) | Búsqueda en *Gestión de canchas* con `' OR '1'='1' -- ` | La entrada se trata como texto literal: **0 resultados** | [01_sql_injection_fallido.jpg](01_sql_injection_fallido.jpg) |
| 2 | Protección contra XSS (7.4) | Reserva con la nota `<script>alert('XSS')</script><img src=x onerror=alert(1)>` | El código se muestra como texto en el comprobante y **no se ejecuta** | [02_xss_neutralizado.jpg](02_xss_neutralizado.jpg) |
| 3 | Protección CSRF (7.5) | Cancelar una reserva enviando el formulario con `csrf_token` alterado | **HTTP 403**: “Solicitud rechazada: token de seguridad (CSRF) inválido” | [03_csrf_token_invalido_403.jpg](03_csrf_token_invalido_403.jpg) |
| 4 | Cabeceras de seguridad (7.6) | Lectura de las cabeceras de `index.php` desde el navegador | `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`; sin `X-Powered-By` | [04_cabeceras_seguridad.jpg](04_cabeceras_seguridad.jpg) |
| 5 | Intentos múltiples de login (7.1) | 5 contraseñas incorrectas para `jean@correo.pa` y luego la contraseña **correcta** | Cuenta **bloqueada 15 minutos** aunque la contraseña sea correcta | [05_bloqueo_intentos_login.jpg](05_bloqueo_intentos_login.jpg) |
| 6 | Propiedad de los datos (7.2) | Un cliente abre `cliente/comprobante.php?codigo=KZ-2026-0002`, que es de otro cliente | Se le redirige con “No encontramos ese comprobante” (no se revela si existe) | [06_acceso_dato_ajeno_bloqueado.jpg](06_acceso_dato_ajeno_bloqueado.jpg) |
| 7 | Control de acceso por rol (7.2) | Un CLIENTE escribe la URL `admin/usuarios.php` | **HTTP 403** “Acceso denegado” generado en PHP (no solo se oculta el botón) | [07_control_acceso_por_rol_403.jpg](07_control_acceso_por_rol_403.jpg) |

## Dónde está implementado cada control

| Control | Implementación |
|---|---|
| Consultas preparadas | `config/database.php` (`PDO::ATTR_EMULATE_PREPARES = false`) y `prepare()` + `execute()` con marcadores en todo el proyecto. Los filtros dinámicos solo concatenan fragmentos fijos con marcadores (`admin/canchas.php`, `encargado/reservas.php`). |
| Escape de salida | Función `e()` en `includes/seguridad.php` (`htmlspecialchars` con `ENT_QUOTES`, UTF-8) usada en todas las vistas. |
| CSRF | `csrf_token()`, `csrf_campo()` y `csrf_verificar()` (`hash_equals`) en `includes/seguridad.php`; presente en login, registro, logout, crear, editar, eliminar, cambiar estado, reservar, cancelar y pagos. |
| Cabeceras | `enviar_cabeceras_seguridad()` en `includes/seguridad.php`. La CSP no permite scripts en línea: todo el JS está en `assets/js/`. |
| Autenticación | `password_hash()` / `password_verify()`, `session_regenerate_id(true)` al iniciar y cerrar sesión, política de contraseña (`validar_politica_password()`), bloqueo tras 5 intentos (`login.php`). |
| Roles | `requiere_rol()` vuelve a consultar el estado y el rol del usuario en cada petición, así que un usuario desactivado pierde el acceso al instante. |
| Propiedad de datos | `obtener_reserva_autorizada()` y `cambiar_estado_reserva()` en `includes/reservas.php` filtran por `id_cliente` de la sesión; `cliente/panel.php` solo lista las reservas del cliente autenticado. |
| Integridad transaccional | `crear_reserva()` usa `beginTransaction()` + `SELECT … FOR UPDATE` para que dos clientes no reserven el mismo bloque al mismo tiempo. |

> Para la Entrega Final se agregará una captura de la pestaña *Red* de las DevTools del navegador mostrando las mismas cabeceras.
