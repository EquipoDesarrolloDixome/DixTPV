var selectedTerminalId = "";
function seleccionarTerminal(div) {
    document.querySelectorAll('.terminal-card').forEach(card => {
        card.classList.remove('terminal-card-selected');
    });

    div.classList.add('terminal-card-selected');

    document.getElementById('selectedTerminalId').value = div.getAttribute('data-terminal-id');
    document.getElementById('selectedTerminalName').value = div.getAttribute('data-terminal-name');

    const cashInput = document.getElementById('dinero-inicial');
    if (cashInput) {
        const cashAttr = div.getAttribute('data-terminal-cash');
        if (cashAttr !== null && cashAttr !== '') {
            const cashValue = parseFloat(cashAttr);
            cashInput.value = isNaN(cashValue) ? '' : cashValue.toFixed(2);
        } else {
            cashInput.value = '';
        }
    }
}

function seleccionarPrimerTerminalDisponible() {
    const terminales = document.querySelectorAll('.terminal-card');
    for (let terminal of terminales) {
        if (!terminal.classList.contains('terminal-card-selected')) {
            seleccionarTerminal(terminal);
            break;
        }
    }
}

/**
 * Función para guardar la selección de terminal y caja en el sistema.
 * Bloquea los selectores después de seleccionar, muestra el campo de dinero inicial
 * y envía los datos al servidor mediante AJAX.
 */
function guardarSeleccion() {
    const terminalSelect = document.getElementById('selectedTerminalId');
    const cajaSelect = document.getElementById('caja');
    const dineroInicial = document.getElementById('dinero-inicial');

    const selectedTerminalText = document.getElementById('selectedTerminalName') || 'No seleccionado';
    const dinero_inicial = dineroInicial.value;
    if (terminalSelect === "") {
        alert("Por favor, necesita seleccionar un terminal antes de continuar.");
        return; // Salir de la función sin continuar
    }

    // Bloquear selección de terminal y caja para evitar cambios accidentales
    terminalSelect.disabled = true;
    cajaSelect.disabled = true;

    // Mostrar el campo de dinero inicial para ingresar el monto
    document.getElementById('dinero-inicial-container').classList.remove('d-none');
    dineoini = document.getElementById('dinero-inicial');
    seleccionarInputTer(dineoini);
    selectedTerminalId = terminalSelect.value;
    // Verificar si se ha ingresado un valor en el campo de dinero inicial
    if (dinero_inicial === '') {
        return; // Si está vacío, salir de la función sin hacer nada
    } else {
        // Datos a enviar al servidor
        const data = {
            action: 'seleccionTerminal',
            dinero_inicial: dinero_inicial,
            selectedTerminalId: selectedTerminalId,
            selectedCajaId: cajaSelect.value
        };

        // Enviar los datos mediante AJAX al servidor
        $.ajax({
            method: "POST",
            url: window.location.href,
            data: data,
            datatype: "json",
            success: function (results) {
                // Recargar la página una vez completada la solicitud
                location.reload();
            },
            error: function (msg) {
                // Mostrar mensaje de error en la consola
                console.log("error postRequest" + msg.status + " " + msg.responseText);
            }
        });
    }
}


/**
 * Función para mostrar el modal de cierre de caja.
 * Este modal permite ingresar el monto final en caja antes de cerrar.
 */
function cerrarCaja() {
    // Mostrar el modal para ingresar el dinero final
    $('#modalCerrarCaja').modal('show');
    dineroFinal = document.getElementById('dineroFinal');
    seleccionarInputTer(dineroFinal);
}

/**
 * Función para procesar el cierre de caja, enviando el monto final al servidor.
 * Si la solicitud es exitosa, recarga la página para reflejar el cierre.
 */
function procesarCerrarCaja() {
    const dineroFinal = document.getElementById('dineroFinal').value;
    camarero = localStorage.getItem('camarero');

    // Verificar si el monto final ha sido ingresado
    if (dineroFinal === '') {
        return; // Si está vacío, salir de la función
    }

    // Datos a enviar al servidor para el cierre de caja
    const data = {
        action: 'cerrarCaja',
        dineroFinal: dineroFinal,
        camarero: camarero
    };

    // Enviar los datos mediante AJAX al servidor
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        dataType: "json",
        success: function (results) {
            try {
                if (results.status === 'success') {
                    $('#modalCerrarCaja').modal('hide');
                    handleCajaClosingPrint(results);
                    return;
                }
            } catch (error) {
                console.log("Error al procesar la respuesta: " + error);
            }
        },
        error: function (msg) {
            // Mostrar mensaje de error en la consola en caso de fallo de solicitud
            //console.log("Error en la solicitud: " + msg.status + " " + msg.responseText);
            Swal.fire({
                title: 'Error',
                text: 'No se pudo cerrar la caja, existe un descuadre.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonText: 'Aceptar',
                cancelButtonText: 'Continuar', // Botón "Continuar"
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#modalCerrarCaja').modal('hide');
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    aceptaDescuadre = true;
                    procesarCerrarCaja2(aceptaDescuadre);
                }
            });
        }
    });
}


function procesarCerrarCaja2(aceptaDescuadre) {
    const dineroFinal = document.getElementById('dineroFinal').value;
    camarero = localStorage.getItem('camarero');

    const data = {
        action: 'cerrarCaja',
        aceptaDescuadre: aceptaDescuadre,
        camarero: camarero,
        dineroFinal: dineroFinal

    };
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        dataType: "json",
        success: function (results) {
            try {
                if (results.status === 'success') {
                    $('#modalCerrarCaja').modal('hide');
                    handleCajaClosingPrint(results);
                    return;
                }
            } catch (error) {
                console.log("Error al procesar la respuesta: " + error);
            }
        },
        error: function (msg) {
            // Mostrar mensaje de error en la consola en caso de fallo de solicitud
            console.log("Error en la solicitud: " + msg.status + " " + msg.responseText);

        }
    });
}


function aperturaCajon() {
    const dineroManejado = document.getElementById('dineroManejado').value;
    const tipoapertura = document.getElementById('tipoapertura').value;
    camarero = localStorage.getItem('camarero');


    // Verificar si el monto final ha sido ingresado
    if (dineroFinal === '') {
        return; // Si está vacío, salir de la función
    }

    // Datos a enviar al servidor para el cierre de caja
    const data = {
        action: 'abrirCaja',
        dineroManejado: dineroManejado,
        camarero: camarero,
        tipoapertura: tipoapertura
    };
    //debugger;
    // Enviar los datos mediante AJAX al servidor
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        dataType: "json",
        success: function (results) {
            try {
                if (results.success) {
                    $('#modalAbrirCaja').modal('hide');
                    location.reload();
                } else {
                    alert(results.resultado);  // ← Muestra mensaje del backend
                }
            } catch (error) {
                console.log("Error al procesar la respuesta: " + error);
            }
        },
        error: function (msg) {
            // Mostrar mensaje de error en la consola en caso de fallo de solicitud
            console.log("Error postRequest " + msg.status + " " + msg.responseText);
        }
    });
}

function procesarCerrarCajaAuto() {
    const dineroFinal = document.getElementById('dineroFinal').value;
    camarero = localStorage.getItem('camarero');

    const data = {
        action: 'cerrarCajaAuto',
        camarero: camarero,
        dineroFinal: dineroFinal

    };
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        dataType: "json",
        success: function (results) {
            try {
                if (results.status === 'success') {
                    $('#modalCerrarCaja').modal('hide');
                    handleCajaClosingPrint(results);
                    return;
                }
            } catch (error) {
                console.log("Error al procesar la respuesta: " + error);
            }
        },
        error: function (msg) {
            // Mostrar mensaje de error en la consola en caso de fallo de solicitud
            console.log("Error en la solicitud: " + msg.status + " " + msg.responseText);

        }
    });
}

function agregarNumeroTer(numero) {
    if (inputSeleccionado) {
        // Añadir el número al final del contenido actual del input
        const currentValue = inputSeleccionado.value;

        if (numero === '.' && currentValue.includes('.')) {
            return; // no hacer nada si ya hay un punto
        }
        if (numero === '#') {
            inputSeleccionado.value = '';
            return;
        }
        // Verificar si el valor actual es un número o vacío
        if (currentValue === "0") {
            inputSeleccionado.value = numero; // Si está vacío o es cero, reemplazar
        } else {
            inputSeleccionado.value += numero; // Añadir el número al final
        }

        const index = inputSeleccionado.dataset.index;
    }
    // Actualiza la cantidad en localStorage y en la vista

}

function seleccionarInputTer(input) {
    inputSeleccionado = input;
    inputSeleccionado.focus();
    inputSeleccionado.select();
    inputSeleccionado.value = "";
}

function handleCajaClosingPrint(resultPayload) {
    const finalize = () => {
        try {
            location.reload();
        } catch (reloadErr) {
            console.warn('No se pudo recargar la página tras el cierre de caja.', reloadErr);
        }
    };

    if (!resultPayload || !resultPayload.docInfo) {
        finalize();
        return;
    }

    const docInfo = Object.assign({}, resultPayload.docInfo);
    if (!docInfo.modelCode) {
        finalize();
        return;
    }

    docInfo.modelClassName = docInfo.modelClassName || 'DixTPVCaja';

    try {
        window.dixTpvLastTicketDoc = docInfo;
    } catch (assignErr) {
        console.warn('No se pudo almacenar la información del ticket de cierre.', assignErr);
    }

    let printPromise = null;
    if (typeof imprimirTicketSecuencia === 'function') {
        printPromise = imprimirTicketSecuencia(docInfo);
    } else if (typeof automaticTicketPrint === 'function') {
        printPromise = automaticTicketPrint(docInfo);
    }

    if (!printPromise || typeof printPromise.finally !== 'function') {
        finalize();
        return;
    }

    Promise.resolve(printPromise).finally(finalize);
}

document.addEventListener("DOMContentLoaded", function () {
    const inputDineroFinal = document.getElementById("dineroFinal");
    const inputDineroManejado = document.getElementById("dineroManejado");

    function manejarInput(input) {
        input.addEventListener("input", function () {
            let valor = this.value;

            // Si se escribe '#', limpiar el campo
            if (valor.includes('#')) {
                this.value = '';
                return;
            }

            // Eliminar todos los caracteres no válidos excepto números y punto
            valor = valor.replace(/[^0-9.]/g, '');

            // Permitir solo un punto
            const partes = valor.split('.');
            if (partes.length > 2) {
                valor = partes[0] + '.' + partes.slice(1).join('');
            }

            this.value = valor;
        });
    }

    manejarInput(inputDineroFinal);
    manejarInput(inputDineroManejado);
});
