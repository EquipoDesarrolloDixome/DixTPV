// Validar selección antes de guardar
function validarYGuardar() {
    const tipoApertura = document.getElementById('tipoapertura').value;
    if (!tipoApertura) {
        alert("Por favor, selecciona un tipo de apertura.");
        return;
    }
    // Llamar a la función original si la validación es exitosa
    aperturaCajon();
}

// Función para limpiar y cargar el ID del camarero al abrir el modal
$('#modalAbrirCaja').on('shown.bs.modal', function () {
    document.getElementById('idCamarero').value = '';
    const camareroId = localStorage.getItem('camarero');
    if (camareroId) {
        document.getElementById('idCamarero').value = camareroId;
    }
    dineroManejado = document.getElementById('dineroManejado');
    seleccionarInput(dineroManejado);
});

function abrirCaja(dineroInicial, camareroId) {
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: {
            action: 'seleccionTerminal',
            dinero_inicial: dineroInicial,
            camarero: camareroId
        },
        success: function (response) {
            const data = JSON.parse(response);
            if (data.success) {
                // Guardar en localStorage
                localStorage.setItem("idcaja", data.idcaja);
                localStorage.setItem("cajaActivaFecha", data.cajaActivaFecha);
                localStorage.setItem("dineroInicial", data.dineroInicial);
                localStorage.setItem("camareroActivo", data.camareroActivo);

                console.log("Caja guardada en LocalStorage:", data);
            }
        }
    });
}

window.addEventListener("load", function () {
    if (localStorage.getItem("idcaja")) {
        $.ajax({
            method: "POST",
            url: window.location.href + "?action=recuperarCajaDesdeLocalStorage",
            data: {
                idcaja: localStorage.getItem("idcaja"),
                cajaActivaFecha: localStorage.getItem("cajaActivaFecha"),
                dineroInicial: localStorage.getItem("dineroInicial"),
                camareroActivo: localStorage.getItem("camareroActivo")
            }
        }).done(function (response) {
            console.log("Caja restaurada desde LocalStorage:", response);
        });
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

    // Devolver productos de carritofin al carrito
    carrito = carrito.concat(carritofin);

    // Guardar carrito actualizado y limpiar carritofin
    localStorage.setItem('carrito', JSON.stringify(carrito));
    localStorage.setItem('carritofin', JSON.stringify([]));

    // Llamar a la función de limpieza
    localStorage.removeItem('carritofin');

    const productoCobrado = document.querySelector('.producto-cobrado-item');
    if (productoCobrado) {
        productoCobrado.innerHTML = '';
    }
    cargarProductosCesta();
    cargarProductosCobrados();
    console.log('Productos devueltos al carrito:', carrito);
    console.log('Modal de finalizar cerrado');
});