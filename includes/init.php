<?php
/**
 * KickZone - Punto de arranque común.
 * Toda página PHP del sistema debe incluir este archivo en su primera línea.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/seguridad.php';

enviar_cabeceras_seguridad();
iniciar_sesion_segura();
