document.addEventListener("DOMContentLoaded", function () {
    actualizarCarrito();
    eventosModales();

    const configEl = document.getElementById('config-terminal');
    const mostrarCamarero = configEl?.dataset?.mostrarCamarero === 'true';
    const camareroMode = configEl?.dataset?.camareroMode ?? 'list';
    window.dixTpvCamareroMode = camareroMode;
    actualizarModoCamarero();
    const printPolicyRaw = configEl?.dataset?.printPolicy;
    window.dixTpvPrintPolicy = parseInt(printPolicyRaw ?? '1', 10);
    const warehouseCode = configEl?.dataset?.codalmacen ?? '';
    window.dixTpvTerminalWarehouse = warehouseCode;
    const modalCamarerosEl = document.getElementById('modalcamareros');
    const bootstrapModal = window.bootstrap?.Modal;

    if (mostrarCamarero) {
        if (modalCamarerosEl) {
            if (bootstrapModal) {
                const modalInstance = bootstrapModal.getOrCreateInstance(modalCamarerosEl, {
                    backdrop: 'static',
                    keyboard: false
                });
                modalInstance.show();
            } else if (window.jQuery) {
                $('#modalcamareros').modal({
                    show: true,
                    backdrop: 'static',
                    keyboard: false
                });
            }
        }
        return;
    }

    // Buscar el primer camarero en el DOM y guardar su codagente
    const primerCamarero = document.querySelector('[data-codagente]');
    if (primerCamarero) {
        const codagente = primerCamarero.dataset.codagente;
        localStorage.setItem('camarero', parseInt(codagente, 10));
    } else {
        console.warn("No se encontró ningún camarero en la vista.");
    }
});



function eventosModales() {
    $('#modalmesas').on('shown.bs.modal', function () {
        const primerSalon = document.querySelector('#modalmesas .salon-seleccionado');
        if (primerSalon) {
            primerSalon.click();
        }

    });

    $('#modalcamareros').on('shown.bs.modal', function () {
        actualizarModoCamarero();
        if (window.dixTpvCamareroMode === 'pin') {
            const pinInput = document.getElementById('clave-camarero');
            if (pinInput) {
                pinInput.focus();
                pinInput.select();
            }
        }
    });

    $('#modalfinalizar').on('shown.bs.modal', function () {
        // Llama a la función en utilidades_cobro.js para actualizar el total
        //actualizarTotalDesdeLocalStorage();
        toggleNormal();
        inicializarModalCobro();
    });

    $('#modalfinalizar').on('hidden.bs.modal', function () {
        // Recuperar carritos del localStorage
        let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
        let carritofin = JSON.parse(localStorage.getItem('carritofin')) || [];
        $("#listaProductosCobrados").html("");
        cargarProductosCesta();

        // Devolver productos de carritofin al carrito
        carrito = carrito.concat(carritofin);

        // Guardar carrito actualizado y limpiar carritofin
        localStorage.setItem('carrito', JSON.stringify(carrito));
        localStorage.setItem('carritofin', JSON.stringify([]));

        // Llamar a la función de limpieza
        localStorage.removeItem('carritofin');

        console.log('Productos devueltos al carrito:', carrito);
        console.log('Modal de finalizar cerrado');
    });
}

function actualizarModoCamarero() {
    const usePin = window.dixTpvCamareroMode === 'pin';
    document.querySelectorAll('.waiter-mode-list').forEach((el) => {
        el.style.display = usePin ? 'none' : '';
    });
    document.querySelectorAll('.waiter-mode-pin').forEach((el) => {
        el.style.display = usePin ? '' : 'none';
    });
}
