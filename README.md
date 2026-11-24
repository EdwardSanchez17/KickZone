# ⚽ KickZone - Sistema Integral de Gestión y Reserva de Canchas Deportivas

Proyecto Final de Desarrollo Web — **Universidad Tecnológica de Panamá (UTP)**

Plataforma web para reservar en línea canchas de Fútbol 5, Fútbol 7, Pádel y Baloncesto, con tarifa
nocturna automática (desde las 18:00) y alquiler de implementos deportivos con control de inventario.

## 👥 Equipo de Desarrollo

| Integrante | Rol en el equipo | Responsabilidades |
|---|---|---|
| David Rios | Líder de Proyecto & Arquitectura Backend | Conexión PDO, seguridad (sesiones, roles, CSRF), autenticación, CRUD de canchas, lógica de reservas |
| Edward Sanchez | DBA & Control de Versiones | Modelo ER, script `kickzone_db.sql`, integridad referencial, datos de prueba, repositorio |
| Edgardo Cordoba | Frontend & Diseño UI/UX | Plantilla base (header/footer), `estilos.css`, portal público, panel del cliente |
| Jean Llanten | Módulos Operativos & QA | Dashboard del administrador, gestión de usuarios, agenda del encargado, pagos, reseñas, pruebas |

## 🛠️ Tecnologías Utilizadas
* **Frontend:** HTML5, CSS3, Bootstrap 5.3, JavaScript (ES6+), FontAwesome 6
* **Backend:** PHP 8+ (PDO con consultas preparadas)
* **Base de Datos:** MySQL / MariaDB (`kickzone_db`, 9 tablas InnoDB)
* **Entorno Local:** XAMPP (Apache + MySQL)

## 🚀 Instalación Local (Para el equipo)
1. Clonar este repositorio dentro de `C:\xampp\htdocs\KickZone` (Windows) o `/Applications/XAMPP/htdocs/KickZone` (macOS).
2. Iniciar **Apache** y **MySQL** en el panel de control de XAMPP.
3. Abrir `http://localhost/phpmyadmin/`, ir a la pestaña **SQL** e importar el archivo `database/kickzone_db.sql`.
4. Si tu MySQL tiene contraseña, ajústala en `config/config.php` (`DB_USER` / `DB_PASS`).
5. Acceder desde el navegador a `http://localhost/KickZone/`.

## 🔑 Cuentas de prueba

Todas usan la contraseña **`KickZone2026*`**

| Rol | Correo | Página de inicio |
|---|---|---|
| ADMIN | `admin@kickzone.pa` | `admin/dashboard.php` |
| ENCARGADO | `encargado@kickzone.pa` | `encargado/dashboard.php` |
| CLIENTE | `edward@correo.pa`, `jean@correo.pa`, `ana.perez@correo.pa` … | `cliente/panel.php` |

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
│   └── footer.php          # Pie de página
├── admin/                  # Solo ADMIN
│   ├── dashboard.php       # KPIs y últimas reservas
│   ├── canchas.php         # CRUD canchas: listado, búsqueda, filtros, estado, eliminar
│   ├── cancha_form.php     # CRUD canchas: crear / editar
│   └── usuarios.php        # Gestión de usuarios: rol, estado, desbloqueo
├── encargado/
│   └── dashboard.php       # Agenda del día (ENCARGADO y ADMIN)
├── cliente/
│   └── panel.php           # Mis reservas (solo las del cliente en sesión)
├── assets/css/estilos.css  # Hoja de estilos institucional
├── assets/js/app.js        # JS propio (sin scripts en línea por la CSP)
├── database/kickzone_db.sql
├── docs/                   # Documentación de entregables
└── security/               # Evidencias de seguridad (Entrega Final)
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
