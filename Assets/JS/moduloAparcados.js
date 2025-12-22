/**
 * Guarda el estado actual del carrito en el servidor y limpia el carrito local.
 */
function isModoHosteleria() {
    return window.dixModoHosteleria !== false && window.dixModoHosteleria !== 'false';
}

let clienteAparcarResolver = null;
let destinoAparcadoResolver = null;
let aparcadoOrigenPendiente = null;
let moverMesaEstadoPrevio = null;
let moverMesaCompletando = false;
let moverMesaModalActivo = false;

function restaurarComandaTrasCancelarMovimiento(productos) {
    if (!moverMesaEstadoPrevio || !Array.isArray(productos) || productos.length === 0) {
        return;
    }
    const mesaOriginal = moverMesaEstadoPrevio.mesa;
    if (!mesaOriginal || mesaOriginal === 'BRA-000') {
        return;
    }
    const salonOriginal = moverMesaEstadoPrevio.salon || sessionStorage.getItem('salonSeleccionado') || '1';
    const idComandaOriginal = moverMesaEstadoPrevio.idcomanda || 0;
    const codCliente = moverMesaEstadoPrevio.codcliente || document.getElementById('cliente')?.value || '';
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: {
            action: 'aparcarCuenta',
            cesta: productos,
            salon: salonOriginal,
            mesa: mesaOriginal,
            codcliente: codCliente,
            idcomanda: idComandaOriginal
        }
    }).fail(function (jqXHR, textStatus) {
        console.warn('No se pudo restaurar la comanda tras cancelar mover mesa.', textStatus, jqXHR?.responseText);
    });
}

function resetAparcadoOrigenPendiente() {
    aparcadoOrigenPendiente = null;
}

function rememberMoverMesaState() {
    moverMesaEstadoPrevio = {
        salon: sessionStorage.getItem('salonSeleccionado'),
        mesa: sessionStorage.getItem('mesaSeleccionada'),
        idcomanda: sessionStorage.getItem('idcomanda'),
        appendMode: sessionStorage.getItem('aparcadoAppendMode'),
        codcliente: document.getElementById('cliente')?.value || ''
    };
    moverMesaCompletando = false;
    moverMesaModalActivo = true;
}

function restoreSessionEntry(key, value) {
    if (!key) {
        return;
    }
    if (value === null || typeof value === 'undefined') {
        sessionStorage.removeItem(key);
        return;
    }
    sessionStorage.setItem(key, value);
}

function resetMoverMesaState() {
    moverMesaEstadoPrevio = null;
    moverMesaCompletando = false;
    moverMesaModalActivo = false;
}

function cancelarMoverMesaSiAplica() {
    if (!moverMesaEstadoPrevio && !localStorage.getItem('carritoPendienteAsignar')) {
        moverMesaModalActivo = false;
        return;
    }
    const temporalRaw = localStorage.getItem('carritoTemporal');
    let carritoRecuperado = null;
    if (temporalRaw) {
        try {
            const productos = JSON.parse(temporalRaw) || [];
            carritoRecuperado = productos;
            localStorage.setItem('carrito', JSON.stringify(productos));
        } catch (err) {
            console.warn('No se pudo restaurar el carrito temporal al cancelar mover mesa.', err);
        }
        localStorage.removeItem('carritoTemporal');
        if (typeof actualizarCarrito === 'function') {
            try {
                actualizarCarrito();
            } catch (refreshErr) {
                console.warn('No se pudo refrescar el carrito tras cancelar mover mesa.', refreshErr);
            }
        }
    }
    if (moverMesaEstadoPrevio) {
        restoreSessionEntry('salonSeleccionado', moverMesaEstadoPrevio.salon);
        restoreSessionEntry('mesaSeleccionada', moverMesaEstadoPrevio.mesa);
        restoreSessionEntry('idcomanda', moverMesaEstadoPrevio.idcomanda);
        restoreSessionEntry('aparcadoAppendMode', moverMesaEstadoPrevio.appendMode);
    }
    if (carritoRecuperado && carritoRecuperado.length) {
        restaurarComandaTrasCancelarMovimiento(carritoRecuperado);
    }
    localStorage.removeItem('carritoPendienteAsignar');
    aparcadoOrigenPendiente = null;
    resetMoverMesaState();
}

function getClienteActualParaAparcar() {
    const hiddenPrincipal = document.getElementById('cliente');
    if (hiddenPrincipal) {
        return hiddenPrincipal.value || '';
    }

    const fallback = document.getElementById('cliente_aparcar');
    return fallback ? (fallback.value || '') : '';
}

function solicitarDestinoAparcado() {
    return new Promise((resolve) => {
        const modal = $('#modalDestinoAparcado');
        if (!modal.length) {
            resolve('listado');
            return;
        }

        destinoAparcadoResolver = resolve;
        modal.modal('show');
    });
}

function confirmarDestinoAparcado(destino) {
    const modal = $('#modalDestinoAparcado');
    if (modal.length) {
        modal.modal('hide');
    }
    if (typeof destinoAparcadoResolver === 'function') {
        destinoAparcadoResolver(destino);
        destinoAparcadoResolver = null;
    }
}

function cancelarDestinoAparcado() {
    const modal = $('#modalDestinoAparcado');
    if (modal.length) {
        modal.modal('hide');
    }
    if (typeof destinoAparcadoResolver === 'function') {
        destinoAparcadoResolver(null);
        destinoAparcadoResolver = null;
    }
}

function seleccionarClienteParaAparcar() {
    return Promise.resolve(getClienteActualParaAparcar());
}

function confirmarSeleccionClienteAparcar() {
    const inputCodigo = document.getElementById('cliente_aparcar');
    const codigo = inputCodigo ? inputCodigo.value.trim() : '';
    if (codigo === '') {
        alert('Selecciona un cliente para aparcar.');
        return;
    }
    const nombre = document.getElementById('nombre_cliente_aparcar')?.value || '';
    const hiddenPrincipal = document.getElementById('cliente');
    if (hiddenPrincipal) {
        hiddenPrincipal.value = codigo;
        if (typeof window.dixOnClientChanged === 'function') {
            window.dixOnClientChanged(codigo);
        }
    }
    const nombrePrincipal = document.getElementById('nombre_cliente');
    if (nombrePrincipal && nombre) {
        nombrePrincipal.value = nombre;
    }

    $('#modalClienteAparcar').modal('hide');
    if (typeof clienteAparcarResolver === 'function') {
        clienteAparcarResolver(codigo);
        clienteAparcarResolver = null;
    }
}

function cancelarSeleccionClienteAparcar() {
    $('#modalClienteAparcar').modal('hide');
    if (typeof clienteAparcarResolver === 'function') {
        clienteAparcarResolver(null);
        clienteAparcarResolver = null;
    }
}

function aparcarCuenta() {
    seleccionarClienteParaAparcar().then(codCliente => {
        if (!codCliente) {
            return;
        }
        procesarAparcadoConCliente(codCliente);
    });
}

function procesarAparcadoConCliente(codCliente) {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];

    const esHosteleria = isModoHosteleria();
    if (esHosteleria) {
        const salonSeleccionado = sessionStorage.getItem('salonSeleccionado') || '1';
        const mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada') || 'BRA-000';
        if (!mesaSeleccionada || mesaSeleccionada === 'BRA-000') {
            solicitarDestinoAparcado().then(destino => {
                if (!destino) {
                    return;
                }
                if (destino === 'mesa') {
                    mostrarModalSeleccionMesas();
                } else if (destino === 'listado') {
                    aparcarEnListadoGeneral(codCliente);
                }
            });
            return;
        }

        enviarAparcado({
            action: 'aparcarCuenta',
            cesta: carrito,
            salon: salonSeleccionado,
            mesa: mesaSeleccionada,
            codcliente: codCliente
        });
        return;
    }

    enviarAparcado({
        action: 'aparcarCuenta',
        cesta: carrito,
        idcomanda: sessionStorage.getItem('idcomanda') || localStorage.getItem('idcomanda') || 0,
        codcliente: codCliente
    });
}

function aparcarEnListadoGeneral(codCliente) {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    if (carrito.length === 0) {
        alert('No hay productos en la cesta para aparcar.');
        return;
    }

    enviarAparcado({
        action: 'aparcarCuenta',
        cesta: carrito,
        idcomanda: sessionStorage.getItem('idcomanda') || localStorage.getItem('idcomanda') || 0,
        codcliente: codCliente,
        aparcarlistado: 1
    });
}

function mostrarModalSeleccionMesas() {
    const modal = $('#modalmesas');
    if (!modal.length) {
        alert('No hay un plano de mesas configurado.');
        return;
    }
    modal.modal('show');
}

function obtenerTotalCarritoConIVA(carrito) {
    const lineas = carrito || JSON.parse(localStorage.getItem('carrito')) || [];
    return lineas.reduce((acc, item) => {
        const unidad = parseFloat(item.pvp) || 0;
        const impuesto = parseFloat(item.codimpuesto) || 0;
        const cantidad = parseFloat(item.cantidad) || 1;
        const lineaBruta = unidad * cantidad * (1 + impuesto / 100);
        return acc + lineaBruta;
    }, 0);
}

function enviarAparcado(data) {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    if (carrito.length === 0) {
        alert('No hay productos en la cesta para aparcar.');
        return;
    }

    if (typeof data.totalconiva === 'undefined') {
        data.totalconiva = obtenerTotalCarritoConIVA(carrito);
    }

    const idComandaActual = sessionStorage.getItem('idcomanda') || localStorage.getItem('idcomanda') || 0;
    if (typeof data.idcomanda === 'undefined') {
        data.idcomanda = aparcadoOrigenPendiente ? 0 : idComandaActual;
    }

    const appendMode = sessionStorage.getItem('aparcadoAppendMode') === '1';
    if (appendMode) {
        data.append_mode = 1;
    }

    if (aparcadoOrigenPendiente) {
        data.aparcado_origen = aparcadoOrigenPendiente;
    }

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        success: function (response) {
            console.log("📦 Respuesta cruda del servidor:", response); // 🔥 Ver qué responde PHP

            try {
                const jsonResponse = JSON.parse(response);
                console.log("✅ JSON recibido:", jsonResponse);

                if (jsonResponse.success) {
                    localStorage.removeItem('carrito');
                    localStorage.removeItem('camarero');
                    sessionStorage.removeItem('salonSeleccionado');
                    sessionStorage.removeItem('mesaSeleccionada');
                    sessionStorage.removeItem('idcomanda');
                    localStorage.removeItem('idcomanda');
                    aparcadoOrigenPendiente = null;
                    sessionStorage.removeItem('aparcadoAppendMode');
                    actualizarCarrito();
                    location.reload();
                } else {
                    alert('Error al guardar el tiquet: ' + jsonResponse.message);
                }
            } catch (error) {
                console.error("❌ Error al parsear JSON:", error, response);
                alert("El servidor devolvió una respuesta inesperada. Revisa la consola.");
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log("❌ Error en la solicitud AJAX:", textStatus, errorThrown);
            alert("Error al comunicar con el servidor.");
        }
    });
}

window.dixProcesarAparcadoConCliente = procesarAparcadoConCliente;
window.dixSeleccionarClienteParaAparcar = seleccionarClienteParaAparcar;
window.dixResetAparcadoOrigenPendiente = resetAparcadoOrigenPendiente;

$(document).ready(function () {
    $('#modalmesas').on('hide.bs.modal', function () {
        if (moverMesaModalActivo && moverMesaEstadoPrevio && !moverMesaCompletando) {
            cancelarMoverMesaSiAplica();
        }
    });

    $('#modalmesas').on('hidden.bs.modal', function () {
        const mesaActual = sessionStorage.getItem('mesaSeleccionada') || 'BRA-000';
        if ((mesaActual === 'BRA-000' || mesaActual === '') && aparcadoOrigenPendiente) {
            aparcadoOrigenPendiente = null;
        }
        if (!moverMesaModalActivo || !moverMesaCompletando) {
            moverMesaEstadoPrevio = null;
            moverMesaModalActivo = false;
        }
    });

    $('#modalDestinoAparcado').on('hidden.bs.modal', function () {
        if (typeof destinoAparcadoResolver === 'function') {
            destinoAparcadoResolver(null);
            destinoAparcadoResolver = null;
        }
    });
});

function moverAparcadoListadoAMesa(idcomanda) {
    const parsedId = parseInt(idcomanda, 10);
    if (!parsedId) {
        return;
    }

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: {
            action: 'recuperarAparcado',
            idcomanda: parsedId
        },
        dataType: "json",
        success: function (response) {
            if (!response || !Array.isArray(response.productos) || response.productos.length === 0) {
                alert('El aparcado seleccionado no tiene productos disponibles.');
                return;
            }

            aparcadoOrigenPendiente = parsedId;
            const salonPrevio = sessionStorage.getItem('salonSeleccionado');
            const mesaPrevia = sessionStorage.getItem('mesaSeleccionada');
            const idComandaPrevio = sessionStorage.getItem('idcomanda');
            const appendPrevio = sessionStorage.getItem('aparcadoAppendMode');
            sessionStorage.removeItem('mesaSeleccionada');
            sessionStorage.removeItem('salonSeleccionado');
            sessionStorage.removeItem('idcomanda');
            localStorage.removeItem('idcomanda');
            const payload = {
                productos: response.productos,
                idcomanda: parsedId
            };
            localStorage.setItem('carritoPendienteAsignar', JSON.stringify(payload));
            moverMesaEstadoPrevio = {
                salon: salonPrevio,
                mesa: mesaPrevia,
                idcomanda: idComandaPrevio,
                appendMode: appendPrevio,
                codcliente: document.getElementById('cliente')?.value || ''
            };
            moverMesaCompletando = false;
            moverMesaModalActivo = true;
            cerrarModalAparcadosVisibles();
            mostrarModalSeleccionMesas();
        },
        error: function (msg) {
            console.log("Error al recuperar aparcado: " + msg.status + " " + msg.responseText);
            alert('No se pudo preparar el aparcado para moverlo a una mesa.');
        }
    });
}

window.dixMoverAparcadoListadoAMesa = moverAparcadoListadoAMesa;

function aparcarCuentaDiv() {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    const clienteSelect = document.getElementById('cliente');
    const codCliente = clienteSelect ? clienteSelect.value : '';

    if (carrito.length === 0) {
        alert('No hay productos en la cesta para aparcar.');
        return;
    }

    const salonSeleccionado = sessionStorage.getItem('salonSeleccionado');
    const mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada');

    if (isModoHosteleria() && (!mesaSeleccionada || mesaSeleccionada === 'BRA-000')) {
        $("#modalmesas").modal("show"); // Mostrar el modal para elegir mesa
        return;
    }

    const data = {
        action: 'aparcarCuenta',
        cesta: carrito,
        salon: salonSeleccionado,
        mesa: mesaSeleccionada,
        idcomanda: sessionStorage.getItem('idcomanda') || localStorage.getItem('idcomanda') || 0,
        codcliente: codCliente
    };

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        success: function (response) {
            const jsonResponse = JSON.parse(response);

            if (jsonResponse.success) {

            } else {
                alert('Error al guardar el tiquet: ' + jsonResponse.message);
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log("Error en la solicitud: " + textStatus + ", " + errorThrown);
            alert('Error al comunicar con el servidor. Intenta de nuevo.');
        }
    });
}

function moverMesa() {
    const carritoActual = JSON.parse(localStorage.getItem('carrito')) || [];

    const salonActual = sessionStorage.getItem('salonSeleccionado') || '1';
    const mesaActual = sessionStorage.getItem('mesaSeleccionada') || 'BRA-000';

    rememberMoverMesaState();
    moverMesaCompletando = false;

    if (mesaActual !== 'BRA-000') {
        localStorage.setItem('carritoTemporal', JSON.stringify(carritoActual));
        vaciarCesta1(false);
    } else {
        localStorage.setItem('carritoTemporal', JSON.stringify(carritoActual));
    }

    $("#modalmesas").modal("show");
    seleccionarSalon(1);
}
function seleccionarMesa(nombreMesa, idComanda) {
    moverMesaCompletando = true;
    const aparcado = (aparcados || []).find(ap => ap.nombremesa === nombreMesa) || null;
    const mesaAnterior = sessionStorage.getItem('mesaSeleccionada');
    const salonAnterior = sessionStorage.getItem('salonSeleccionado');

    const carritoTemporal = JSON.parse(localStorage.getItem('carritoTemporal'));
    if (!localStorage.getItem('carrito')) {
        localStorage.setItem('carrito', JSON.stringify([]));
    }
    const hayCarritoTemporal = carritoTemporal && carritoTemporal.length > 0;

    const carritoActual = JSON.parse(localStorage.getItem('carrito')) || [];
    const debeGuardarMesaAnterior = !hayCarritoTemporal &&
        mesaAnterior && mesaAnterior !== 'BRA-000' &&
        carritoActual.length > 0;

    const continuarSeleccion = () => {
        sessionStorage.setItem('mesaSeleccionada', nombreMesa);
        if (!aparcado) {
            sessionStorage.removeItem('aparcadoAppendMode');
        }

        setTimeout(() => {
            mostrarMesaSeleccionada();
        }, 100);

        if (aparcado && !hayCarritoTemporal) {
            cerrarModal();
            const carritoRefrescado = JSON.parse(localStorage.getItem('carrito')) || [];
            const tieneCarritoActual = carritoRefrescado.length > 0;
            if (tieneCarritoActual) {
                sessionStorage.setItem('idcomanda', aparcado.idcomanda);
                localStorage.setItem('idcomanda', aparcado.idcomanda);
                sessionStorage.setItem('aparcadoAppendMode', '1');
                resetMoverMesaState();
                $("#modalmesas").modal("hide");
                setTimeout(() => {
                    actualizarCarrito();
                    aparcarCuenta();
                }, 200);
                return;
            }
            sessionStorage.removeItem('aparcadoAppendMode');
            const pendientes = consumirCarritoPendienteAsignar();
            if (pendientes && Array.isArray(pendientes.productos) && pendientes.productos.length) {
                const finalizar = (lineasBase = []) => {
                    const mezclado = mezclarCarritos(lineasBase, pendientes.productos);
                    const idDestino = aparcado ? aparcado.idcomanda : 0;
                    localStorage.setItem('carrito', JSON.stringify(mezclado));
                    sessionStorage.setItem('idcomanda', idDestino);
                    localStorage.setItem('idcomanda', idDestino);
                    resetMoverMesaState();
                    $("#modalmesas").modal("hide");
                    setTimeout(() => {
                        actualizarCarrito();
                        aparcarCuenta();
                    }, 200);
                };
                if (aparcado && aparcado.idcomanda) {
                    obtenerProductosDeComanda(aparcado.idcomanda)
                        .then(finalizar)
                        .catch(() => finalizar([]));
                } else {
                    finalizar(JSON.parse(localStorage.getItem('carrito')) || []);
                }
                return;
            }
            const data = {
                action: 'recuperarAparcado',
                idcomanda: aparcado.idcomanda
            };
            $.ajax({
                method: "POST",
                url: window.location.href,
                data: data,
                dataType: "json",
                success: function (response) {
                    const productos = response.productos || [];
                    localStorage.setItem('carrito', JSON.stringify(productos));
                    sessionStorage.setItem('idcomanda', aparcado.idcomanda);
                    localStorage.setItem('idcomanda', aparcado.idcomanda);
                    actualizarCarro(productos, aparcado.idcomanda);
                },
                error: function (msg) {
                    console.log("Error al recuperar aparcado: " + msg.status + " " + msg.responseText);
                }
            });
            return;
        }

        const pendientesGenerales = consumirCarritoPendienteAsignar();
        if (pendientesGenerales) {
            localStorage.setItem('carrito', JSON.stringify(pendientesGenerales.productos || []));
            if (pendientesGenerales.idcomanda) {
                sessionStorage.setItem('idcomanda', pendientesGenerales.idcomanda);
                localStorage.setItem('idcomanda', pendientesGenerales.idcomanda);
            }

            resetMoverMesaState();
            $("#modalmesas").modal("hide");
            setTimeout(() => {
                actualizarCarrito();
                aparcarCuenta();
            }, 200);
            return;
        }

        if (hayCarritoTemporal) {
            localStorage.setItem('carrito', JSON.stringify(carritoTemporal));
            localStorage.removeItem('carritoTemporal');
            resetMoverMesaState();

            $("#modalmesas").modal("hide");

            setTimeout(() => {
                actualizarCarrito();
                aparcarCuenta();
            }, 200);
            return;
        }

        const carritoRefrescado = JSON.parse(localStorage.getItem('carrito')) || [];
        resetMoverMesaState();
        $("#modalmesas").modal("hide");

        const esServicioRapido = mesaAnterior === null || mesaAnterior === 'BRA-000';
        if (esServicioRapido && carritoRefrescado.length > 0) {
            setTimeout(() => aparcarCuenta(), 200);
        } else if (!esServicioRapido) {
            eliminarCesta();
        }
    };

    if (debeGuardarMesaAnterior) {
        guardarMesaActualAntesDeCambiar(salonAnterior, mesaAnterior)
            .then(() => {
                sessionStorage.removeItem('aparcadoAppendMode');
                continuarSeleccion();
            })
            .catch((error) => {
                console.error('No se pudo guardar la mesa actual antes de cambiar.', error);
                alert('No se pudo guardar la mesa actual antes de cambiar.');
                continuarSeleccion();
            });
    } else {
        continuarSeleccion();
    }
}

function guardarMesaActualAntesDeCambiar(salon, mesa) {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    if (!mesa || mesa === 'BRA-000' || carrito.length === 0) {
        return Promise.resolve();
    }

    const data = {
        action: 'aparcarCuenta',
        cesta: carrito,
        salon: salon || sessionStorage.getItem('salonSeleccionado') || '1',
        mesa: mesa,
        idcomanda: sessionStorage.getItem('idcomanda') || localStorage.getItem('idcomanda') || 0,
        codcliente: document.getElementById('cliente')?.value || ''
    };

    if (sessionStorage.getItem('aparcadoAppendMode') === '1') {
        data.append_mode = 1;
    }

    return new Promise((resolve, reject) => {
        $.ajax({
            method: "POST",
            url: window.location.href,
            data: data,
            success: function (response) {
                let parsed = response;
                if (typeof response === 'string') {
                    try {
                        parsed = JSON.parse(response);
                    } catch (err) {
                        reject(err);
                        return;
                    }
                }

                if (parsed && parsed.success) {
                    localStorage.removeItem('carrito');
                    localStorage.removeItem('camarero');
                    localStorage.removeItem('idcomanda');
                    sessionStorage.removeItem('idcomanda');
                    actualizarCarrito();
                    resolve(parsed);
                } else {
                    reject(parsed && parsed.message ? parsed.message : 'No se pudo guardar la mesa actual.');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                reject(errorThrown || textStatus);
            }
        });
    });
}

function comprobarAparcados() {
    const mesas = document.querySelectorAll('.mesa-card');
    const data = {action: 'get-aparcados'};

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        dataType: "json",
        success: function (response) {
            const aparcados = response;
            if (!aparcados || aparcados.length === 0) {
                console.warn("No se encontraron aparcados.");
                return;
            }

            mesas.forEach(mesa => {
                const nombreMesa = mesa.textContent.trim();

                // Evitar eliminar mesas directamente
                if (nombreMesa === 'BRA-000') {
                    mesa.remove();
                    return;
                }

                const aparcado = aparcados.find(aparcado => aparcado.nombremesa === nombreMesa);

                if (aparcado) {
                    mesa.classList.add('mesa-ocupada');
                    mesa.classList.remove('mesa-disponible');
                } else {
                    mesa.classList.add('mesa-disponible');
                    mesa.classList.remove('mesa-ocupada');
                }
            });
        },
        error: function (msg) {
            console.log("Error en postRequest: " + msg.status + " " + msg.responseText);
        }
    });
}

function cargarAparcado(idcomanda) {
    if (!idcomanda) {
        return;
    }

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: {
            action: 'recuperarAparcado',
            idcomanda: idcomanda
        },
        dataType: "json",
        success: function (response) {
            if (response && Array.isArray(response.productos)) {
                localStorage.setItem('carrito', JSON.stringify(response.productos));
                sessionStorage.setItem('idcomanda', idcomanda);
                localStorage.setItem('idcomanda', idcomanda);
                actualizarCarro(response.productos, idcomanda);
                cerrarModalAparcadosVisibles();
            }
        },
        error: function (msg) {
            console.log("Error al recuperar aparcado: " + msg.status + " " + msg.responseText);
        }
    });
}

function imprimirAparcado(idcomanda) {
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: {
            action: 'printAparcado',
            idcomanda: idcomanda
        },
        success: function (response) {
            let jsonResponse = response;
            if (typeof response === 'string') {
                try {
                    jsonResponse = JSON.parse(response);
                } catch (err) {
                    console.warn('Respuesta inesperada al imprimir aparcado.', err, response);
                    jsonResponse = {};
                }
            }

            if (jsonResponse.printed && jsonResponse.escpos && typeof sendEscposToPrinter === 'function') {
                const readyPromise = (typeof ensureQZSession === 'function')
                    ? Promise.resolve(ensureQZSession()).catch(() => false)
                    : Promise.resolve(true);

                readyPromise.then(isReady => {
                    if (false === isReady) {
                        console.warn('QZ Tray no está listo para imprimir el aparcado.');
                        return;
                    }

                    let escposData = jsonResponse.escpos;
                    if ((jsonResponse.escposEncoding || '').toLowerCase() === 'base64' && typeof atob === 'function') {
                        try {
                            escposData = atob(jsonResponse.escpos);
                        } catch (decodeErr) {
                            console.warn('No se pudo decodificar el ticket base64.', decodeErr);
                            return;
                        }
                    }
                    return sendEscposToPrinter(escposData, 'modalPrintAlert', 'modalPrintAlertMessage');
                }).catch(err => {
                    console.error('No se pudo enviar el aparcado a la impresora.', err);
                });
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log("Error en la impresión de aparcado: " + textStatus + ", " + errorThrown);
            alert('Error al comunicar con el servidor. Intenta de nuevo.');
        }
    });
}

function eliminarAparcado(idcomanda, trigger) {
    if (!confirm('¿Eliminar el ticket aparcado?')) {
        return;
    }

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: {
            action: 'deleteAparcado',
            idcomanda: idcomanda
        },
        success: function (response) {
            let jsonResponse = response;
            if (typeof response === 'string') {
                try {
                    jsonResponse = JSON.parse(response);
                } catch (err) {
                    console.warn('Respuesta inesperada al eliminar aparcado.', err, response);
                    jsonResponse = {};
                }
            }

            if (jsonResponse.success) {
                const card = trigger ? trigger.closest('[data-aparcado]') : document.querySelector('[data-aparcado="' + idcomanda + '"]');
                if (card) {
                    card.remove();
                }
                if (typeof setToast === 'function') {
                    setToast('Aparcado eliminado', 'success', 'Aparcados', 3000);
                }
            } else {
                alert(jsonResponse.message || 'No se pudo eliminar el aparcado.');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log("Error eliminando aparcado: " + textStatus + ", " + errorThrown);
            alert('Error al comunicar con el servidor. Intenta de nuevo.');
        }
    });
}
window.dixObtenerTotalCarritoConIVA = obtenerTotalCarritoConIVA;

function cerrarModalAparcadosVisibles() {
    const modales = ['#modalaparcadosgenerales', '#modalaparcados'];
    modales.forEach(id => {
        const modal = $(id);
        if (modal.length && modal.hasClass('show')) {
            modal.modal('hide');
        }
    });
}

function mezclarCarritos(base = [], extra = []) {
    const resultado = [];
    const indice = {};

    const clonar = (item) => Object.assign({}, item, {
        cantidad: parseFloat(item.cantidad) || 0
    });

    const clave = (item) => {
        const ref = (item.referencia || '').trim();
        if (ref !== '') {
            return 'ref:' + ref.toLowerCase();
        }
        const desc = (item.descripcion || '').trim().toLowerCase();
        const precio = Number(parseFloat(item.pvp) || 0).toFixed(6);
        return 'desc:' + desc + '|p:' + precio;
    };

    base.forEach(linea => {
        const copia = clonar(linea);
        const key = clave(copia);
        indice[key] = resultado.length;
        resultado.push(copia);
    });

    extra.forEach(linea => {
        const key = clave(linea);
        if (Object.prototype.hasOwnProperty.call(indice, key)) {
            const pos = indice[key];
            resultado[pos].cantidad = (parseFloat(resultado[pos].cantidad) || 0) + (parseFloat(linea.cantidad) || 0);
            if (!resultado[pos].codimpuesto && linea.codimpuesto) {
                resultado[pos].codimpuesto = linea.codimpuesto;
            }
        } else {
            const copia = clonar(linea);
            indice[key] = resultado.length;
            resultado.push(copia);
        }
    });

    return resultado;
}

function obtenerProductosDeComanda(idcomanda) {
    return new Promise((resolve, reject) => {
        if (!idcomanda) {
            resolve([]);
            return;
        }

        $.ajax({
            method: "POST",
            url: window.location.href,
            data: {
                action: 'recuperarAparcado',
                idcomanda: idcomanda
            },
            dataType: "json",
            success: function (response) {
                if (response && Array.isArray(response.productos)) {
                    resolve(response.productos);
                } else {
                    resolve([]);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                reject(errorThrown || textStatus);
            }
        });
    });
}

function consumirCarritoPendienteAsignar() {
    const raw = localStorage.getItem('carritoPendienteAsignar');
    if (!raw) {
        return null;
    }
    try {
        const data = JSON.parse(raw);
        localStorage.removeItem('carritoPendienteAsignar');
        if (data && Array.isArray(data.productos)) {
            return data;
        }
    } catch (err) {
        console.warn('No se pudo interpretar el carrito pendiente.', err);
    }
    return null;
}
