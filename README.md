# ⚽ KickZone - Sistema Integral de Gestión y Reserva de Canchas Deportivas

Proyecto Final de Desarrollo Web — **Universidad Tecnológica de Panamá (UTP)**

Plataforma web para reservar en línea canchas de Fútbol 5, Fútbol 7, Pádel y Baloncesto, con tarifa
nocturna automática (desde las 18:00) y alquiler de implementos deportivos con control de inventario.

## 👥 Equipo de Desarrollo

| Integrante | Rol en el equipo | Responsabilidades |
|---|---|---|
| David Rios | Líder de Proyecto & Arquitectura Backend | Conexión PDO, seguridad (sesiones, roles, CSRF), autenticación, CRUD de canchas, motor de reservas transaccional |
| Edward Sanchez | DBA & Control de Versiones | Modelo ER, script `kickzone_db.sql`, integridad referencial, datos de prueba, repositorio |
| Edgardo Cordoba | Frontend & Diseño UI/UX | Plantilla base (header/footer), `estilos.css`, portal público, pantalla de reserva, comprobante, panel del cliente, diseño en Figma |
| Jean Llanten | Módulos Operativos & QA | Dashboard del administrador, CRUD de usuarios e implementos, agenda y gestión de reservas/pagos del encargado, pruebas |

## 🛠️ Tecnologías Utilizadas
* **Frontend:** HTML5, CSS3, Bootstrap 5.3, JavaScript (ES6+), FontAwesome 6
* **Backend:** PHP 8+ (PDO con consultas preparadas)
* **Base de Datos:** MySQL / MariaDB (`kickzone_db`, 9 tablas InnoDB)
* **Entorno Local:** XAMPP (Apache + MySQL)

## 🚀 Instalación Local (Para el equipo)
1. Clonar este repositorio dentro de `C:\xampp\htdocs\KickZone` (Windows) o `/Applications/XAMPP/htdocs/KickZone` (macOS).
2. Iniciar **Apache** y **MySQL** en el panel de control de XAMPP.
3. Abrir `http://localhost/phpmyadmin/`, ir a la pestaña **SQL** e importar el archivo `database/kickzone_db.sql`.
4. Si tu MySQL usa otro puerto o contraseña, copia `config/config.local.example.php` como `config/config.local.php` y ajústalo (este archivo no se sube a GitHub).
5. Acceder desde el navegador a `http://localhost/KickZone/`.

### macOS con MAMP
1. Clonar el repositorio dentro de `/Applications/MAMP/htdocs/KickZone`.
2. Copiar `config/config.local.example.php` como `config/config.local.php` (ya trae los valores de MAMP: puerto `8889`, usuario `root`, contraseña `root`).
3. Iniciar MAMP (**Start**) con **PHP 8.x**, abrir `http://localhost:8888/phpMyAdmin5/` e importar `database/kickzone_db.sql`.
4. Acceder a `http://localhost:8888/KickZone/`.

## 🔑 Cuentas de prueba

Todas usan la contraseña **`KickZone2026*`**

| Rol | Correo | Página de inicio |
|---|---|---|
| ADMIN | `admin@kickzone.pa` | `admin/dashboard.php` |
| ENCARGADO | `encargado@kickzone.pa` | `encargado/dashboard.php` |
| CLIENTE | `edward@correo.pa`, `jean@correo.pa`, `ana.perez@correo.pa` … | `cliente/panel.php` |

### Actualizar una base del Entregable 2
Si ya tenías `kickzone_db` creada, ejecuta **una sola vez** `database/migracion_entregable3.sql` en phpMyAdmin
(agrega columnas, índices y restricciones sin borrar datos). Si prefieres empezar limpio, vuelve a importar `database/kickzone_db.sql`.

## 📁 Estructura del proyecto

```
KickZone/
├── index.php               # Portal público: catálogo de canchas filtrable + implementos
├── login.php               # Inicio de sesión (bloqueo tras 5 intentos fallidos)
├── registro.php            # Registro de clientes con política de contraseña
├── logout.php              # Cierre de sesión (solo POST + CSRF)
├── acceso_denegado.php     # Página 403 por rol
├── config/
│   ├── config.php          # Constantes (BD, sesión, roles)
│   └── database.php        # Conexión PDO única
├── includes/
│   ├── init.php            # Arranque común de todas las páginas
│   ├── seguridad.php       # Cabeceras, sesión, CSRF, escape XSS, control de roles
│   ├── header.php          # Encabezado + menú según rol
│   ├── footer.php          # Pie de página
│   └── reservas.php        # Motor de reservas: disponibilidad, tarifas, stock, transacción
├── admin/                  # Solo ADMIN
│   ├── dashboard.php       # KPIs y últimas reservas
│   ├── canchas.php / cancha_form.php          # CRUD de canchas
│   ├── implementos.php / implemento_form.php  # CRUD de implementos
│   └── usuarios.php / usuario_form.php        # CRUD de usuarios (rol, estado, desbloqueo)
├── encargado/              # ENCARGADO y ADMIN
│   ├── dashboard.php       # Agenda del día
│   └── reservas.php        # Confirmar, completar, cancelar, verificar y cobrar pagos
├── cliente/                # Solo CLIENTE (el comprobante también para staff)
│   ├── reservar.php        # Nueva reserva (bloques, tarifa nocturna, implementos, pago)
│   ├── disponibilidad.php  # JSON de bloques y stock para la pantalla de reserva
│   ├── comprobante.php     # Comprobante imprimible (verifica propiedad)
│   ├── cancelar.php        # Cancelación con 2 h de anticipación
│   └── panel.php           # Mis reservas
├── assets/css/estilos.css  # Hoja de estilos institucional
├── assets/js/              # app.js y reserva.js (sin scripts en línea por la CSP)
├── database/               # kickzone_db.sql y migracion_entregable3.sql
├── docs/                   # Entregable 1 y diseño en Figma (SVG)
└── security/               # Evidencias de seguridad con capturas
```

## ✅ Entregable 2 — Prototipo Funcional Inicial

| Requisito | Estado | Dónde |
|---|---|---|
| Base de datos con todas las tablas principales | ✅ | `database/kickzone_db.sql` (9 tablas, PK/FK, índices, estados) |
| Proyecto PHP con estructura ordenada | ✅ | `config/`, `includes/`, carpetas por rol |
| Plantilla base (header, menú, footer) | ✅ | `includes/header.php`, `includes/footer.php` |
| Registro, login y logout | ✅ | `registro.php`, `login.php`, `logout.php` |
| Control de roles básico | ✅ | `requiere_rol()` en `includes/seguridad.php` (validado en servidor) |
| Mínimo 1 módulo CRUD completo | ✅ | Canchas: `admin/canchas.php` + `admin/cancha_form.php` |
| Datos de prueba | ✅ | 7 usuarios, 6 canchas, 5 implementos, 9 reservas, pagos y reseñas |

## ✅ Entregable 3 — Versión Avanzada con Seguridad

| Requisito | Estado | Dónde |
|---|---|---|
| Mínimo 2 módulos CRUD completos | ✅ | Canchas, Implementos y Usuarios (listado, búsqueda, filtros, crear, editar, eliminar) |
| Módulo principal transaccional iniciado | ✅ | Reservas: `includes/reservas.php`, `cliente/reservar.php`, `encargado/reservas.php` |
| PDO preparado en toda consulta | ✅ | Sin concatenar variables en SQL |
| `password_hash()` y validación | ✅ | Registro, login y CRUD de usuarios con política de contraseña |
| `htmlspecialchars()` en vistas | ✅ | Función `e()` |
| Token CSRF | ✅ | Todos los formularios que crean, editan, eliminan o cambian estado |
| Control de rol avanzado | ✅ | `requiere_rol()` + verificación de propiedad de reservas y comprobantes |
| Evidencias iniciales de seguridad | ✅ | Carpeta [`security/`](security/README.md) |
| Flujo funcional parcial | ✅ | Cliente reserva → encargado confirma y verifica pago → completa; cliente puede cancelar |

### Reglas del motor de reservas
* Bloques de 1 hora entre las 07:00 y las 23:00; de 1 a 3 horas por reserva; hasta 30 días de anticipación.
* Sin solapamiento por cancha (validado dentro de una transacción con `SELECT … FOR UPDATE`).
* Tarifa nocturna automática por cada hora desde `hora_inicio_nocturna` (18:00 por defecto).
* Stock de implementos compartido entre canchas en el mismo horario; solo implementos del deporte de la cancha.
* Código único `KZ-AAAA-NNNN` y pago en estado PENDIENTE hasta que el encargado lo verifica.
* Estados: PENDIENTE → CONFIRMADA → COMPLETADA, o CANCELADA (el cliente solo con 2 h de anticipación; los pagos verificados pasan a REEMBOLSADO).

## 🔒 Seguridad implementada
* `password_hash()` / `password_verify()` (Bcrypt) y política de contraseña (8+ caracteres, mayúscula, minúscula, número y símbolo).
* `session_regenerate_id(true)` al iniciar y cerrar sesión; cookies `HttpOnly` + `SameSite=Strict`; expiración por inactividad (30 min).
* Bloqueo temporal de 15 minutos tras 5 intentos fallidos de login.
* PDO con consultas preparadas reales (`ATTR_EMULATE_PREPARES = false`) en todo el proyecto.
* `htmlspecialchars()` (función `e()`) en toda salida de datos.
* Token CSRF en login, registro, logout, crear, editar, eliminar y cambiar estado.
* Cabeceras `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`.
* Control de acceso por rol en el servidor y verificación de propiedad de datos (el cliente solo ve sus reservas).
* `.htaccess` bloquea el acceso directo a `config/`, `includes/` y `database/`.
