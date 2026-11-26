<?php
/**
 * KickZone - Motor de reservas (lógica de negocio del módulo transaccional).
 *
 * Reglas:
 *  - Horario operativo de 07:00 a 23:00, en bloques de 1 hora en punto (1 a 3 horas por reserva).
 *  - Se puede reservar desde hoy hasta DIAS_MAX_ANTICIPACION días en el futuro.
 *  - Una cancha no admite dos reservas activas (PENDIENTE/CONFIRMADA) que se solapen.
 *  - Cada hora desde hora_inicio_nocturna se cobra con tarifa nocturna.
 *  - Los implementos tienen stock compartido: se descuenta lo reservado en el mismo horario.
 *  - Todo se registra dentro de una transacción con bloqueo de fila (SELECT ... FOR UPDATE).
 */

declare(strict_types=1);

require_once __DIR__ . '/seguridad.php';

const HORA_APERTURA = 7;
const HORA_CIERRE = 23;
const DURACION_MAX_HORAS = 3;
const DIAS_MAX_ANTICIPACION = 30;
const MAX_CANTIDAD_IMPLEMENTO = 5;
const HORAS_MIN_CANCELACION = 2;
const METODOS_PAGO = [
    'YAPPY'           => 'Yappy',
    'EFECTIVO_CANCHA' => 'Efectivo en cancha',
    'TRANSFERENCIA'   => 'Transferencia bancaria',
    'TARJETA'         => 'Tarjeta',
];
const ESTADOS_ACTIVOS = ['PENDIENTE', 'CONFIRMADA'];

/** Excepción para errores de negocio que se muestran al usuario tal cual. */
final class ReservaException extends RuntimeException
{
}

/** Cancha reservable (estado DISPONIBLE) o null. */
function obtener_cancha_reservable(int $idCancha): ?array
{
    $stmt = db()->prepare(
        "SELECT c.id_cancha, c.id_deporte, c.nombre, c.descripcion, c.superficie, c.capacidad_jugadores,
                c.tarifa_diurna, c.tarifa_nocturna, c.hora_inicio_nocturna, c.estado, d.nombre AS deporte
           FROM canchas c
           JOIN deportes d ON d.id_deporte = c.id_deporte
          WHERE c.id_cancha = :id AND c.estado = 'DISPONIBLE'"
    );
    $stmt->execute([':id' => $idCancha]);
    return $stmt->fetch() ?: null;
}

/** Valida una fecha Y-m-d dentro del rango permitido. Devuelve null si es inválida. */
function validar_fecha_reserva(string $fecha): ?DateTimeImmutable
{
    $f = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    if (!$f || $f->format('Y-m-d') !== $fecha) {
        return null;
    }
    $hoy = new DateTimeImmutable('today');
    $limite = $hoy->modify('+' . DIAS_MAX_ANTICIPACION . ' days');
    return ($f < $hoy || $f > $limite) ? null : $f;
}

/** Hora (entera) a partir de la cual aplica tarifa nocturna en una cancha. */
function hora_nocturna(array $cancha): int
{
    return (int) substr((string) $cancha['hora_inicio_nocturna'], 0, 2);
}

/**
 * Calcula el costo de la cancha para un rango de horas.
 * @return array{subtotal: float, nocturna: bool, detalle: array<int, array{hora:int, nocturna:bool, precio:float}>}
 */
function calcular_tarifa(array $cancha, int $horaInicio, int $duracion): array
{
    $desdeNoche = hora_nocturna($cancha);
    $detalle = [];
    $subtotal = 0.0;
    $nocturna = false;

    for ($h = $horaInicio; $h < $horaInicio + $duracion; $h++) {
        $esNoche = $h >= $desdeNoche;
        $precio = (float) ($esNoche ? $cancha['tarifa_nocturna'] : $cancha['tarifa_diurna']);
        $detalle[] = ['hora' => $h, 'nocturna' => $esNoche, 'precio' => $precio];
        $subtotal += $precio;
        $nocturna = $nocturna || $esNoche;
    }

    return ['subtotal' => round($subtotal, 2), 'nocturna' => $nocturna, 'detalle' => $detalle];
}

/**
 * Bloques horarios del día para una cancha: libre, ocupado o pasado.
 * @return array<int, array{hora:int, etiqueta:string, estado:string, nocturna:bool, precio:float}>
 */
function bloques_disponibles(array $cancha, DateTimeImmutable $fecha): array
{
    $stmt = db()->prepare(
        "SELECT HOUR(hora_inicio) AS hi, HOUR(hora_fin) AS hf
           FROM reservas
          WHERE id_cancha = :cancha AND fecha_reserva = :fecha
            AND estado IN ('PENDIENTE', 'CONFIRMADA')"
    );
    $stmt->execute([':cancha' => $cancha['id_cancha'], ':fecha' => $fecha->format('Y-m-d')]);

    $ocupadas = [];
    foreach ($stmt->fetchAll() as $r) {
        $fin = (int) $r['hf'] === 0 ? 24 : (int) $r['hf'];
        for ($h = (int) $r['hi']; $h < $fin; $h++) {
            $ocupadas[$h] = true;
        }
    }

    $esHoy = $fecha->format('Y-m-d') === date('Y-m-d');
    $horaActual = (int) date('G');
    $desdeNoche = hora_nocturna($cancha);
    $bloques = [];

    for ($h = HORA_APERTURA; $h < HORA_CIERRE; $h++) {
        $estado = 'libre';
        if ($esHoy && $h <= $horaActual) {
            $estado = 'pasado';
        } elseif (isset($ocupadas[$h])) {
            $estado = 'ocupado';
        }
        $esNoche = $h >= $desdeNoche;
        $bloques[] = [
            'hora'     => $h,
            'etiqueta' => sprintf('%02d:00', $h),
            'estado'   => $estado,
            'nocturna' => $esNoche,
            'precio'   => (float) ($esNoche ? $cancha['tarifa_nocturna'] : $cancha['tarifa_diurna']),
        ];
    }
    return $bloques;
}

/**
 * Implementos que se pueden alquilar para la cancha, con el stock libre en ese horario.
 * Si no se indica horario se devuelve el stock total.
 */
function implementos_para_cancha(array $cancha, ?string $fecha = null, ?int $horaInicio = null, ?int $duracion = null): array
{
    $sql = "SELECT i.id_implemento, i.nombre, i.descripcion, i.precio_alquiler, i.stock_total";
    $params = [':deporte' => $cancha['id_deporte']];

    if ($fecha !== null && $horaInicio !== null && $duracion !== null) {
        $sql .= ", i.stock_total - COALESCE((
                    SELECT SUM(ri.cantidad)
                      FROM reserva_implementos ri
                      JOIN reservas r ON r.id_reserva = ri.id_reserva
                     WHERE ri.id_implemento = i.id_implemento
                       AND r.fecha_reserva = :fecha
                       AND r.estado IN ('PENDIENTE', 'CONFIRMADA')
                       AND r.hora_inicio < :fin AND r.hora_fin > :inicio), 0) AS disponible";
        $params[':fecha'] = $fecha;
        $params[':inicio'] = sprintf('%02d:00:00', $horaInicio);
        $params[':fin'] = sprintf('%02d:00:00', $horaInicio + $duracion);
    } else {
        $sql .= ', i.stock_total AS disponible';
    }

    $sql .= " FROM implementos i
             WHERE i.estado = 'ACTIVO' AND (i.id_deporte = :deporte OR i.id_deporte IS NULL)
             ORDER BY i.nombre";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Crea una reserva completa (reserva + implementos + pago pendiente) en una sola transacción.
 *
 * @param array<int,int> $implementos id_implemento => cantidad
 * @return array{id:int, codigo:string, total:float}
 * @throws ReservaException si alguna regla de negocio no se cumple
 */
function crear_reserva(int $idCliente, int $idCancha, string $fecha, int $horaInicio, int $duracion,
                       array $implementos, string $metodoPago, string $notas): array
{
    // --- Validaciones que no requieren la base de datos ---
    $fechaObj = validar_fecha_reserva($fecha);
    if (!$fechaObj) {
        throw new ReservaException('La fecha no es válida. Puedes reservar desde hoy hasta ' . DIAS_MAX_ANTICIPACION . ' días adelante.');
    }
    if ($duracion < 1 || $duracion > DURACION_MAX_HORAS) {
        throw new ReservaException('La duración debe ser de 1 a ' . DURACION_MAX_HORAS . ' horas.');
    }
    if ($horaInicio < HORA_APERTURA || $horaInicio + $duracion > HORA_CIERRE) {
        throw new ReservaException(sprintf('El horario debe estar entre las %02d:00 y las %02d:00.', HORA_APERTURA, HORA_CIERRE));
    }
    if ($fecha === date('Y-m-d') && $horaInicio <= (int) date('G')) {
        throw new ReservaException('Ese horario ya pasó. Elige una hora posterior.');
    }
    if (!array_key_exists($metodoPago, METODOS_PAGO)) {
        throw new ReservaException('Selecciona un método de pago válido.');
    }
    if (mb_strlen($notas) > 255) {
        throw new ReservaException('Las notas no pueden superar 255 caracteres.');
    }
    foreach ($implementos as $idImp => $cant) {
        if ($cant < 0 || $cant > MAX_CANTIDAD_IMPLEMENTO) {
            throw new ReservaException('La cantidad de cada implemento debe estar entre 0 y ' . MAX_CANTIDAD_IMPLEMENTO . '.');
        }
    }
    $implementos = array_filter($implementos, static fn(int $c): bool => $c > 0);

    $inicio = sprintf('%02d:00:00', $horaInicio);
    $fin = sprintf('%02d:00:00', $horaInicio + $duracion);

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // 1. Bloquear la cancha: dos clientes no pueden reservar la misma cancha a la vez.
        $stmt = $pdo->prepare(
            "SELECT id_cancha, id_deporte, nombre, tarifa_diurna, tarifa_nocturna, hora_inicio_nocturna, estado
               FROM canchas WHERE id_cancha = :id FOR UPDATE"
        );
        $stmt->execute([':id' => $idCancha]);
        $cancha = $stmt->fetch();
        if (!$cancha || $cancha['estado'] !== 'DISPONIBLE') {
            throw new ReservaException('La cancha no está disponible para reservas.');
        }

        // 2. Validación anti-solapamiento
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM reservas
              WHERE id_cancha = :cancha AND fecha_reserva = :fecha
                AND estado IN ('PENDIENTE', 'CONFIRMADA')
                AND hora_inicio < :fin AND hora_fin > :inicio"
        );
        $stmt->execute([':cancha' => $idCancha, ':fecha' => $fecha, ':inicio' => $inicio, ':fin' => $fin]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new ReservaException('Ese horario se acaba de ocupar. Elige otro bloque.');
        }

        // 3. Implementos: deben corresponder al deporte y tener stock en ese horario
        $subtotalImplementos = 0.0;
        $lineas = [];
        if ($implementos) {
            $stmtImp = $pdo->prepare(
                "SELECT id_implemento, nombre, precio_alquiler, stock_total, id_deporte, estado
                   FROM implementos WHERE id_implemento = :id FOR UPDATE"
            );
            $stmtUso = $pdo->prepare(
                "SELECT COALESCE(SUM(ri.cantidad), 0)
                   FROM reserva_implementos ri
                   JOIN reservas r ON r.id_reserva = ri.id_reserva
                  WHERE ri.id_implemento = :id AND r.fecha_reserva = :fecha
                    AND r.estado IN ('PENDIENTE', 'CONFIRMADA')
                    AND r.hora_inicio < :fin AND r.hora_fin > :inicio"
            );

            foreach ($implementos as $idImp => $cantidad) {
                $stmtImp->execute([':id' => $idImp]);
                $imp = $stmtImp->fetch();
                $deporteOk = $imp && ($imp['id_deporte'] === null || (int) $imp['id_deporte'] === (int) $cancha['id_deporte']);
                if (!$imp || $imp['estado'] !== 'ACTIVO' || !$deporteOk) {
                    throw new ReservaException('Uno de los implementos seleccionados no está disponible para esta cancha.');
                }

                $stmtUso->execute([':id' => $idImp, ':fecha' => $fecha, ':inicio' => $inicio, ':fin' => $fin]);
                $libre = (int) $imp['stock_total'] - (int) $stmtUso->fetchColumn();
                if ($cantidad > $libre) {
                    throw new ReservaException(sprintf('Solo quedan %d unidad(es) de «%s» en ese horario.', max($libre, 0), $imp['nombre']));
                }

                $precio = (float) $imp['precio_alquiler'];
                $sub = round($precio * $cantidad, 2);
                $subtotalImplementos += $sub;
                $lineas[] = [$idImp, $cantidad, $precio, $sub];
            }
        }

        // 4. Tarifa diurna / nocturna calculada en el servidor (nunca se confía en el cliente)
        $tarifa = calcular_tarifa($cancha, $horaInicio, $duracion);
        $total = round($tarifa['subtotal'] + $subtotalImplementos, 2);

        // 5. Insertar la reserva con un código temporal y generar el código definitivo con su ID
        $stmt = $pdo->prepare(
            "INSERT INTO reservas (codigo_reserva, id_cliente, id_cancha, fecha_reserva, hora_inicio, hora_fin,
                                   es_tarifa_nocturna, subtotal_cancha, subtotal_implementos, total_pagar,
                                   notas_cliente, estado)
             VALUES (:codigo, :cliente, :cancha, :fecha, :inicio, :fin, :nocturna, :sub_cancha, :sub_imp, :total,
                     :notas, 'PENDIENTE')"
        );
        $stmt->execute([
            ':codigo'     => 'TMP-' . bin2hex(random_bytes(6)),
            ':cliente'    => $idCliente,
            ':cancha'     => $idCancha,
            ':fecha'      => $fecha,
            ':inicio'     => $inicio,
            ':fin'        => $fin,
            ':nocturna'   => $tarifa['nocturna'] ? 1 : 0,
            ':sub_cancha' => $tarifa['subtotal'],
            ':sub_imp'    => round($subtotalImplementos, 2),
            ':total'      => $total,
            ':notas'      => $notas !== '' ? $notas : null,
        ]);
        $idReserva = (int) $pdo->lastInsertId();
        $codigo = sprintf('KZ-%s-%04d', date('Y'), $idReserva);

        $pdo->prepare('UPDATE reservas SET codigo_reserva = :codigo WHERE id_reserva = :id')
            ->execute([':codigo' => $codigo, ':id' => $idReserva]);

        // 6. Detalle de implementos
        if ($lineas) {
            $stmt = $pdo->prepare(
                'INSERT INTO reserva_implementos (id_reserva, id_implemento, cantidad, precio_unitario, subtotal)
                 VALUES (:reserva, :imp, :cant, :precio, :sub)'
            );
            foreach ($lineas as [$idImp, $cantidad, $precio, $sub]) {
                $stmt->execute([':reserva' => $idReserva, ':imp' => $idImp, ':cant' => $cantidad, ':precio' => $precio, ':sub' => $sub]);
            }
        }

        // 7. Pago pendiente de verificación por el encargado
        $pdo->prepare(
            "INSERT INTO pagos (id_reserva, metodo_pago, monto, estado_pago)
             VALUES (:reserva, :metodo, :monto, 'PENDIENTE')"
        )->execute([':reserva' => $idReserva, ':metodo' => $metodoPago, ':monto' => $total]);

        $pdo->commit();
        return ['id' => $idReserva, 'codigo' => $codigo, 'total' => $total];
    } catch (Throwable $ex) {
        $pdo->rollBack();
        if ($ex instanceof ReservaException) {
            throw $ex;
        }
        error_log('[KickZone] Error al crear reserva: ' . $ex->getMessage());
        throw new ReservaException('No se pudo registrar la reserva. Inténtalo de nuevo.');
    }
}

/**
 * Busca una reserva verificando la PROPIEDAD de los datos:
 * un cliente solo obtiene sus propias reservas; encargado y admin, cualquiera.
 */
function obtener_reserva_autorizada(string $codigo): ?array
{
    $sql = "SELECT r.*, c.nombre AS cancha, c.superficie, d.nombre AS deporte,
                   CONCAT(u.nombre, ' ', u.apellido) AS cliente, u.correo, u.telefono
              FROM reservas r
              JOIN canchas c ON c.id_cancha = r.id_cancha
              JOIN deportes d ON d.id_deporte = c.id_deporte
              JOIN usuarios u ON u.id_usuario = r.id_cliente
             WHERE r.codigo_reserva = :codigo";
    $params = [':codigo' => $codigo];

    if (!tiene_rol(ROL_ADMIN, ROL_ENCARGADO)) {
        $sql .= ' AND r.id_cliente = :cliente';
        $params[':cliente'] = (int) $_SESSION['usuario']['id'];
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

/**
 * Cambia el estado de una reserva respetando las transiciones válidas:
 *   PENDIENTE  -> CONFIRMADA | CANCELADA
 *   CONFIRMADA -> COMPLETADA | CANCELADA
 * Al cancelar: los pagos verificados pasan a REEMBOLSADO y los pendientes se anulan.
 *
 * @throws ReservaException
 */
function cambiar_estado_reserva(int $idReserva, string $nuevoEstado, ?int $idCliente = null): string
{
    $transiciones = [
        'PENDIENTE'  => ['CONFIRMADA', 'CANCELADA'],
        'CONFIRMADA' => ['COMPLETADA', 'CANCELADA'],
    ];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT id_reserva, codigo_reserva, id_cliente, fecha_reserva, hora_inicio, hora_fin, estado
                  FROM reservas WHERE id_reserva = :id';
        $params = [':id' => $idReserva];
        if ($idCliente !== null) {
            $sql .= ' AND id_cliente = :cliente'; // propiedad del dato
            $params[':cliente'] = $idCliente;
        }
        $stmt = $pdo->prepare($sql . ' FOR UPDATE');
        $stmt->execute($params);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            throw new ReservaException('La reserva no existe o no te pertenece.');
        }
        if (!in_array($nuevoEstado, $transiciones[$reserva['estado']] ?? [], true)) {
            throw new ReservaException(sprintf('No se puede pasar una reserva %s a %s.', $reserva['estado'], $nuevoEstado));
        }

        $inicio = new DateTimeImmutable($reserva['fecha_reserva'] . ' ' . $reserva['hora_inicio']);
        if ($nuevoEstado === 'COMPLETADA' && $inicio > new DateTimeImmutable()) {
            throw new ReservaException('Solo puedes completar una reserva cuyo horario ya comenzó.');
        }
        if ($nuevoEstado === 'CANCELADA' && $idCliente !== null) {
            $limite = (new DateTimeImmutable())->modify('+' . HORAS_MIN_CANCELACION . ' hours');
            if ($inicio < $limite) {
                throw new ReservaException('Solo puedes cancelar con al menos ' . HORAS_MIN_CANCELACION . ' horas de anticipación.');
            }
        }

        $pdo->prepare('UPDATE reservas SET estado = :estado WHERE id_reserva = :id')
            ->execute([':estado' => $nuevoEstado, ':id' => $idReserva]);

        if ($nuevoEstado === 'CANCELADA') {
            $pdo->prepare("UPDATE pagos SET estado_pago = 'REEMBOLSADO' WHERE id_reserva = :id AND estado_pago = 'VERIFICADO'")
                ->execute([':id' => $idReserva]);
            $pdo->prepare("DELETE FROM pagos WHERE id_reserva = :id AND estado_pago = 'PENDIENTE'")
                ->execute([':id' => $idReserva]);
        }

        $pdo->commit();
        return $reserva['codigo_reserva'];
    } catch (Throwable $ex) {
        $pdo->rollBack();
        if ($ex instanceof ReservaException) {
            throw $ex;
        }
        error_log('[KickZone] Error al cambiar estado: ' . $ex->getMessage());
        throw new ReservaException('No se pudo actualizar la reserva.');
    }
}
