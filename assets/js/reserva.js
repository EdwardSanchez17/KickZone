/**
 * KickZone - Pantalla de reserva: resumen y total en vivo.
 * Solo es ayuda visual: el servidor recalcula y valida todo al confirmar.
 */
(() => {
    const form = document.getElementById('formReserva');
    if (!form) return;

    const tarifaDia = parseFloat(form.dataset.tarifaDia);
    const tarifaNoche = parseFloat(form.dataset.tarifaNoche);
    const horaNoche = parseInt(form.dataset.horaNoche, 10);
    const urlDisponibilidad = form.dataset.disponibilidadUrl;
    const idCancha = form.querySelector('[name="id_cancha"]').value;
    const fecha = document.getElementById('fechaReserva').value;

    const selDuracion = document.getElementById('duracion');
    const cantidades = [...form.querySelectorAll('.kz-cantidad')];
    const $ = (id) => document.getElementById(id);
    const dinero = (n) => '$' + n.toFixed(2);
    const pad = (h) => String(h).padStart(2, '0') + ':00';

    // Cambiar la fecha recarga los bloques de ese día
    const fechaConsulta = $('fechaConsulta');
    if (fechaConsulta) {
        fechaConsulta.addEventListener('change', () => $('formFecha').submit());
    }

    const horaSeleccionada = () => {
        const r = form.querySelector('input[name="hora"]:checked');
        return r ? parseInt(r.value, 10) : null;
    };

    /** ¿Están libres todas las horas desde h durante d horas? */
    const rangoLibre = (h, d) => {
        for (let i = h; i < h + d; i++) {
            const radio = $('hora' + i);
            if (!radio || radio.disabled) return false;
        }
        return true;
    };

    /** Deshabilita duraciones que chocarían con un bloque ocupado o con el cierre. */
    const ajustarDuraciones = (h) => {
        [...selDuracion.options].forEach((opt) => {
            opt.disabled = h !== null && !rangoLibre(h, parseInt(opt.value, 10));
        });
        if (selDuracion.selectedOptions[0] && selDuracion.selectedOptions[0].disabled) {
            selDuracion.value = '1';
        }
    };

    /** Consulta el stock libre de implementos para el horario elegido. */
    let peticion = 0;
    const actualizarStock = async (h, d) => {
        const id = ++peticion;
        try {
            const params = new URLSearchParams({ cancha: idCancha, fecha, hora: h, duracion: d });
            const resp = await fetch(`${urlDisponibilidad}?${params}`, { credentials: 'same-origin' });
            if (!resp.ok || id !== peticion) return;
            const datos = await resp.json();
            (datos.implementos || []).forEach((imp) => {
                const input = form.querySelector(`[name="implementos[${imp.id}]"]`);
                if (!input) return;
                input.max = Math.min(5, imp.disponible);
                if (parseInt(input.value || '0', 10) > imp.disponible) input.value = imp.disponible;
                input.closest('.kz-implemento').classList.toggle('kz-agotado', imp.disponible === 0);
            });
            recalcular(false);
        } catch (e) {
            /* sin conexión: el servidor validará el stock al confirmar */
        }
    };

    const recalcular = (consultarStock = true) => {
        const h = horaSeleccionada();
        ajustarDuraciones(h);
        const d = parseInt(selDuracion.value, 10);

        let subCancha = 0;
        let nocturna = false;
        if (h !== null) {
            for (let i = h; i < h + d; i++) {
                const esNoche = i >= horaNoche;
                nocturna = nocturna || esNoche;
                subCancha += esNoche ? tarifaNoche : tarifaDia;
            }
        }

        let subImp = 0;
        cantidades.forEach((input) => {
            const cant = Math.max(0, parseInt(input.value || '0', 10));
            subImp += cant * parseFloat(input.dataset.precio);
        });

        $('resumenHorario').textContent = h === null ? 'Elige un bloque' : `${pad(h)} – ${pad(h + d)}`;
        $('resumenTarifa').textContent = h === null ? '—' : (nocturna ? 'Nocturna' : 'Diurna');
        $('resumenTarifa').classList.toggle('kz-tarifa-noche', nocturna);
        $('resumenCancha').textContent = h === null ? '—' : dinero(subCancha);
        $('resumenImplementos').textContent = dinero(subImp);
        $('resumenTotal').textContent = h === null ? '—' : dinero(subCancha + subImp);

        if (consultarStock && h !== null) actualizarStock(h, d);
    };

    form.addEventListener('change', (ev) => {
        if (ev.target.matches('input[name="hora"], #duracion')) recalcular(true);
        else if (ev.target.matches('.kz-cantidad')) recalcular(false);
    });
    cantidades.forEach((input) => input.addEventListener('input', () => recalcular(false)));

    form.addEventListener('submit', (ev) => {
        if (horaSeleccionada() === null) {
            ev.preventDefault();
            $('bloques').classList.add('kz-bloques-error');
            $('bloques').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    recalcular(true);
})();
