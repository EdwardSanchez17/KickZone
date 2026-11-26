/**
 * KickZone - Scripts del lado del cliente.
 * Sin JavaScript en línea: la Content-Security-Policy solo permite archivos propios.
 * La validación aquí es solo de apoyo visual; el servidor siempre valida de nuevo.
 */
document.addEventListener('DOMContentLoaded', () => {

    // Confirmación antes de acciones destructivas: <form data-confirmar="¿Seguro?">
    document.querySelectorAll('form[data-confirmar]').forEach((form) => {
        form.addEventListener('submit', (ev) => {
            if (!window.confirm(form.dataset.confirmar)) {
                ev.preventDefault();
            }
        });
    });

    // Indicador en vivo de la política de contraseña (registro)
    const inputPassword = document.getElementById('password');
    const listaRequisitos = document.getElementById('requisitosPassword');
    if (inputPassword && listaRequisitos) {
        const reglas = {
            largo: (v) => v.length >= 8,
            mayuscula: (v) => /[A-Z]/.test(v),
            minuscula: (v) => /[a-z]/.test(v),
            numero: (v) => /\d/.test(v),
            especial: (v) => /[^A-Za-z0-9]/.test(v),
        };
        inputPassword.addEventListener('input', () => {
            const valor = inputPassword.value;
            Object.entries(reglas).forEach(([clave, cumple]) => {
                const item = listaRequisitos.querySelector(`[data-regla="${clave}"]`);
                if (item) {
                    item.classList.toggle('ok', cumple(valor));
                }
            });
        });
    }

    // Mostrar / ocultar contraseña: <button data-toggle-password="idDelInput">
    document.querySelectorAll('[data-toggle-password]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const input = document.getElementById(boton.dataset.togglePassword);
            if (!input) return;
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            const icono = boton.querySelector('i');
            if (icono) {
                icono.classList.toggle('fa-eye', visible);
                icono.classList.toggle('fa-eye-slash', !visible);
            }
        });
    });

    // Validación nativa de Bootstrap en formularios con .needs-validation
    document.querySelectorAll('form.needs-validation').forEach((form) => {
        form.addEventListener('submit', (ev) => {
            if (!form.checkValidity()) {
                ev.preventDefault();
                ev.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Botón de imprimir comprobante: <button data-imprimir>
    document.querySelectorAll('[data-imprimir]').forEach((boton) => {
        boton.addEventListener('click', () => window.print());
    });

    // Enviar automáticamente los filtros con <select data-autosubmit>
    document.querySelectorAll('select[data-autosubmit]').forEach((select) => {
        select.addEventListener('change', () => select.form && select.form.submit());
    });
});
