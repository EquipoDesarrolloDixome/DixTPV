const TCOBRO_NORMAL = 0; // Tipo cobro normal
const TCOBRO_PP = 1; // Tipo cobro Por persona
const TCOBRO_DC = 2; // Tipo cobro dividir cuenta
const DEFAULT_TICKET_FORMAT = 'FacturaScripts\\Plugins\\Tickets\\Lib\\Tickets\\Normal';
const DIXTPV_FALLBACK_TICKET_FORMAT = 'FacturaScripts\\Plugins\\DixTPV\\Lib\\Tickets\\CashClosingTicket';
var tipoCobro = TCOBRO_NORMAL;

var totalVenta = 0.0;
var importeCobrar = 0.00;
var importeEntregado = 0.00;
var importeCambio = 0.00;

function getStoredArray(key) {
    try {
        const rawValue = localStorage.getItem(key);
        if (!rawValue) {
            return [];
        }
        const parsed = JSON.parse(rawValue);
        return Array.isArray(parsed) ? parsed : [];
    } catch (err) {
        return [];
    }
}

// Función para cargar los productos desde localStorage en la cesta
function cargarProductosCesta() {
    const listaProductosCesta = document.getElementById('listaProductosCesta');
    listaProductosCesta.innerHTML = '';
    const headerDiv = document.createElement('div');
    headerDiv.classList.add('d-flex', 'w-100', 'p-2', 'fw-bold', 'border-bottom', 'cab-carrito');

    const referenciaHeader = document.createElement('div');
    referenciaHeader.classList.add('hidden');
    referenciaHeader.textContent = 'Ref.';

    const descripcionHeader = document.createElement('div');
    descripcionHeader.classList.add('col-9', 'fw-bold');
    descripcionHeader.textContent = 'Desc.';

    const cantidadHeader = document.createElement('div');
    cantidadHeader.classList.add('col-1', 'text-end', 'fw-bold');
    cantidadHeader.textContent = 'Ud.';

    const precioHeader = document.createElement('div');
    precioHeader.classList.add('col-1', 'text-end', 'fw-bold');
    precioHeader.textContent = 'PVP';

    const accionHeader = document.createElement('div');
    accionHeader.classList.add('col-2', 'text-end');
    accionHeader.textContent = '';

    headerDiv.appendChild(referenciaHeader);
    headerDiv.appendChild(descripcionHeader);
    headerDiv.appendChild(cantidadHeader);
    headerDiv.appendChild(precioHeader);
    //headerDiv.appendChild(accionHeader);

    listaProductosCesta.appendChild(headerDiv);
    const productos = JSON.parse(localStorage.getItem('carrito')) || [];
    totalVenta = 0;
    productos.forEach((producto, index) => {
        const productoDiv = document.createElement('div');
        productoDiv.classList.add('row', 'align-items-center', 'border-bottom', 'py-2', 'px-3', 'producto-item');
        productoDiv.onclick = () => cobrarProducto(index);

        const referenciaDiv = document.createElement('div');
        referenciaDiv.classList.add('producto-item', 'hidden');
        referenciaDiv.textContent = producto.referencia;

        const descripcionDiv = document.createElement('div');
        descripcionDiv.classList.add('col-9', 'producto-item');
        descripcionDiv.textContent = producto.descripcion.length > 30 ? producto.descripcion.slice(15) + '...' : producto.descripcion;

        const cantidadDiv = document.createElement('div');
        cantidadDiv.classList.add('col-1', 'producto-item', 'text-end');
        cantidadDiv.textContent = producto.cantidad;

        const precioDiv = document.createElement('div');
        precioDiv.classList.add('col-1', 'producto-item', 'text-end');
        const precioConIVA = producto.pvp * (1 + producto.codimpuesto / 100);

        const precio = new Intl.NumberFormat('es-ES', {
            style: 'decimal',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(precioConIVA);

        precioDiv.textContent = `${precio}`;

        // Agregar todos los elementos a la fila principal
        productoDiv.appendChild(referenciaDiv);
        productoDiv.appendChild(descripcionDiv);
        productoDiv.appendChild(cantidadDiv);
        productoDiv.appendChild(precioDiv);
        //productoDiv.appendChild(botonDiv);

        listaProductosCesta.appendChild(productoDiv);
        totalVenta += producto.pvp * producto.cantidad * (1 + producto.codimpuesto / 100);
    });
    actualizaImportesTotales();
    sincronizarImporteCobrarDesdeStorage();
}

function cargarProductosCobrados() {
    const listaProductosCobrados = document.getElementById('listaProductosCobrados');
    const seccionCarritofin = document.getElementById('seccionCarritofin');
    const totalCuentaElement = document.getElementById('totalCuentaDividida');
    let totalCuenta = 0;

    if (listaProductosCobrados) {
        listaProductosCobrados.innerHTML = ''; // Limpiar la lista antes de cargar
        const productosCobrados = JSON.parse(localStorage.getItem('carritofin')) || [];

        const headerDiv = document.createElement('div');
        headerDiv.classList.add('d-flex', 'w-100', 'p-2', 'fw-bold', 'border-bottom', 'cab-carrito');

        const accionHeader = document.createElement('div');
        accionHeader.classList.add('col-2', 'text-end');
        accionHeader.textContent = '';

        const referenciaHeader = document.createElement('div');
        referenciaHeader.classList.add('hidden');
        referenciaHeader.textContent = 'Ref.';

        const descripcionHeader = document.createElement('div');
        descripcionHeader.classList.add('col-9', 'fw-bold');
        descripcionHeader.textContent = 'Desc.';

        const cantidadHeader = document.createElement('div');
        cantidadHeader.classList.add('col-1', 'text-end', 'fw-bold');
        cantidadHeader.textContent = 'Ud.';

        const precioHeader = document.createElement('div');
        precioHeader.classList.add('col-1', 'text-end', 'fw-bold');
        precioHeader.textContent = 'PVP';

        // headerDiv.appendChild(accionHeader);
        headerDiv.appendChild(referenciaHeader);
        headerDiv.appendChild(descripcionHeader);
        headerDiv.appendChild(cantidadHeader);
        headerDiv.appendChild(precioHeader);

        listaProductosCobrados.appendChild(headerDiv);

        productosCobrados.forEach((producto, index) => {
            const productoDiv = document.createElement('div');
            productoDiv.classList.add('row', 'producto-item', 'py-2', 'border-bottom', 'align-items-center', );
            productoDiv.onclick = () => devolverProducto(index);
            const referenciaDiv = document.createElement('div');
            referenciaDiv.classList.add('producto-item', 'hidden');
            referenciaDiv.textContent = producto.referencia;

            const descripcionDiv = document.createElement('div');
            descripcionDiv.classList.add('col-9', 'producto-item');
            descripcionDiv.textContent = producto.descripcion.length > 15 ? producto.descripcion.slice(15) + '...' : producto.descripcion;
            ;

            const cantidadDiv = document.createElement('div');
            cantidadDiv.classList.add('col-1', 'producto-item');
            cantidadDiv.textContent = producto.cantidad;

            const precioDiv = document.createElement('div');
            precioDiv.classList.add('col-1', 'producto-item', 'text-end');
            const precioConIVA = producto.pvp * (1 + producto.codimpuesto / 100);

            const precio = new Intl.NumberFormat('es-ES', {
                style: 'decimal',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(precioConIVA);

            precioDiv.textContent = `${precio}`;

            productoDiv.appendChild(referenciaDiv);
            productoDiv.appendChild(descripcionDiv);
            productoDiv.appendChild(cantidadDiv);
            productoDiv.appendChild(precioDiv);

            listaProductosCobrados.appendChild(productoDiv);

            totalCuenta += producto.pvp * producto.cantidad * (1 + producto.codimpuesto / 100);
        });

        if (seccionCarritofin) {
            seccionCarritofin.style.display = productosCobrados.length > 0 ? 'block' : 'none';
        }

        if (totalCuentaElement) {
            totalCuentaElement.textContent = `${totalCuenta.toFixed(2)} €`;
        }
        importeCobrar = totalCuenta;
        calcularCambio();
        actualizaImportesTotales();
        //document.getElementById("totCobrar").textContent = parseFloat(totalCuenta) || 0;
        localStorage.setItem('totalCuentaDividida', totalCuenta);
    }
}
// Función para devolver un producto y actualizar `carrito`
function devolverProducto(index) {
    const productosCobrados = JSON.parse(localStorage.getItem('carritofin')) || [];
    const productoDevuelto = productosCobrados[index]; // Obtener el producto que se va a devolver

    if (!productoDevuelto)
        return; // Si no hay producto en el índice, salimos

    let carrito = JSON.parse(localStorage.getItem('carrito')) || []; // Carrito original

    // Si la cantidad a devolver es mayor que 1, solo se devolverá una unidad
    if (productoDevuelto.cantidad > 1) {
        productoDevuelto.cantidad -= 1;
    } else {
        // Si la cantidad es 1, eliminamos el producto de carritofin
        productosCobrados.splice(index, 1);
    }

    // Restaurar la cantidad del producto al carrito
    const productoEnCarrito = carrito.find(item => item.referencia === productoDevuelto.referencia);
    if (productoEnCarrito) {
        productoEnCarrito.cantidad += 1;
    } else {
        carrito.push({...productoDevuelto});
    }


    localStorage.setItem('carrito', JSON.stringify(carrito)); // Guardar el carrito con el producto devuelto
    localStorage.setItem('carritofin', JSON.stringify(productosCobrados)); // Actualizar el carrito de cobrados

    cargarProductosCesta();
    cargarProductosCobrados();

}

// Función para cobrar un producto y actualizar `carritofin`
function cobrarProducto(index) {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    const producto = carrito[index];

    if (!producto || producto.cantidad <= 0)
        return;

    let carritofin = JSON.parse(localStorage.getItem('carritofin')) || [];

    producto.cantidad -= 1;

    const productoEnCarritofin = carritofin.find(item => item.referencia === producto.referencia);
    if (productoEnCarritofin) {
        productoEnCarritofin.cantidad += 1;
    } else {
        carritofin.push({...producto, cantidad: 1});
    }

    localStorage.setItem("carritofin", JSON.stringify(carritofin));

    if (producto.cantidad === 0) {
        carrito.splice(index, 1);
    }
    localStorage.setItem("carrito", JSON.stringify(carrito));

    cargarProductosCesta();
    cargarProductosCobrados(); // Actualizar la vista de productos cobrados
}

// Función para cerrar el modal y limpiar todos los datos
function cerrarModalYLimpiarDatos() {
    // Borrar datos relacionados del localStorage
    localStorage.removeItem('carrito');
    localStorage.removeItem('carritofin');
    localStorage.removeItem('precioacobrar');

    // Limpiar las secciones visuales
    document.getElementById('totalCuenta').textContent = '0.00 €';
    document.getElementById('totalPersona').textContent = '0.00 €';
    document.getElementById('listaProductosCesta').innerHTML = '';
    document.getElementById('listaProductosCobrados').innerHTML = '';
    document.getElementById('personas').value = 1;
}

function cobrarCuenta() {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    const camarero = localStorage.getItem('camarero');
    const divPago = document.querySelector('#formasPago .pago.activo');
    let formapago = null;
    let esEfectivo = false;

    if (divPago) {
        formapago = divPago.dataset.valor;
        esEfectivo = divPago.dataset.esefectivo;
    }

    const idcliente = document.getElementById('cliente').value; // Obtener el valor del select
    const serieSeleccionada = document.getElementById('SerieSeleccionada').value; // 👈 Aquí recogemos la serie

    if (carrito.length === 0) {
        alert('No hay productos en la cesta para cobrar.');
        return;
    }

    const salonSeleccionado = sessionStorage.getItem('salonSeleccionado') || '1';
    const mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada') || 'BRA-000';
    const idComandaActual = sessionStorage.getItem('idcomanda') || localStorage.getItem('idcomanda') || 0;

    const pagoParcial = importeEntregado < importeCobrar;
    if (pagoParcial) {
        const confirmar = window.confirm('Está registrando un pago parcial. ¿Desea continuar?');
        if (!confirmar) {
            return;
        }
    }


    const data = {
        action: 'cobrarCuenta',
        cesta: carrito,
        camarero: camarero,
        formapago: formapago,
        idcliente: idcliente,
        salon: salonSeleccionado,
        mesa: mesaSeleccionada,
        precioacobrar: importeCobrar,
        importeentregado: importeEntregado,
        serie: serieSeleccionada,
        idcomanda: idComandaActual
    };
    const docTypeField = document.getElementById('TipoDocumento');
    if (docTypeField && docTypeField.value) {
        data.doctype = docTypeField.value;
    }

    if (window.DixTPVBonos && window.DixTPVBonos.context && window.DixTPVBonos.context.aplicado > 0) {
        data.bonoCodigo = window.DixTPVBonos.context.codigo;
        data.bonoImporte = window.DixTPVBonos.context.aplicado;
        if (typeof window.DixTPVBonos.context.importeOriginal === 'number') {
            data.bonoTotal = window.DixTPVBonos.context.importeOriginal;
        }
    }

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        success: function (response) {
            try {
                const jsonResponse = JSON.parse(response);
                const finalizeSale = () => {
                    localStorage.removeItem('carrito');
                    localStorage.removeItem('camarero');
                    sessionStorage.removeItem('salonSeleccionado');
                    sessionStorage.removeItem('mesaSeleccionada');
                    sessionStorage.removeItem('idcomanda');
                    localStorage.removeItem('idcomanda');
                    actualizarCarrito();
                    location.reload();
                };

                if (!!jsonResponse.success) {
                    const docInfo = jsonResponse.document || null;
                    if (docInfo) {
                        localStorage.setItem('ultimoDocumentoTPV', JSON.stringify(docInfo));
                    }
                    const triggerDrawer = () => {
                        if (jsonResponse.cashDrawer && typeof window.dixTpvHandleCashDrawerResponse === 'function') {
                            return Promise.resolve(window.dixTpvHandleCashDrawerResponse(jsonResponse.cashDrawer));
                        }
                        if (typeof abrirCajon === 'function') {
                            try {
                                return Promise.resolve(abrirCajon());
                            } catch (openErr) {
                                console.warn('No se pudo ejecutar la llamada estándar para abrir el cajón.', openErr);
                            }
                        }
                        return Promise.resolve(false);
                    };

                    const drawerPromise = triggerDrawer().catch(err => {
                        console.warn('Fallo abriendo el cajón.', err);
                    });
                    const printPromise = Promise.resolve(automaticTicketPrint(docInfo));

                    Promise.allSettled([drawerPromise, printPromise]).finally(finalizeSale);
                } else {
                    alert('Error: ' + (jsonResponse.message || 'Error desconocido.'));
                }
            } catch (e) {
                console.error('Error al procesar la respuesta del servidor:', e);
                console.error('Respuesta del servidor:', response);
                alert('Error en la respuesta del servidor. Contacte con soporte técnico.');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('Error en la solicitud AJAX:', textStatus, errorThrown);
            alert('Error al comunicar con el servidor. Por favor, inténtalo de nuevo.');
        }
    });

}
function CobrarCuentaDividida() {
    const carritofin = JSON.parse(localStorage.getItem('carritofin')) || [];
    const precioacobrar = parseFloat(localStorage.getItem('totalCuentaDividida') || '0');
    const activePayment = document.querySelector('#formasPago .pago.activo');
    let formapago = activePayment ? activePayment.dataset.valor : '';
    if (!formapago) {
        const selectPago = document.getElementById('FormaPago');
        formapago = selectPago ? selectPago.value : '';
    }
    //const codcliente = document.getElementById('clienteDiv').value;
    const codcliente = document.getElementById('cliente').value; // Obtener el valor del select
    const serieSeleccionada = document.getElementById('SerieSeleccionada').value; // 👈 Aquí recogemos la serie

    if (carritofin.length === 0) {
        alert('No hay productos en la cesta para cobrar.');
        return;
    }
    const pagoParcial = importeEntregado < precioacobrar;
    if (pagoParcial) {
        const confirmar = window.confirm('Está registrando un pago parcial. ¿Desea continuar?');
        if (!confirmar) {
            return;
        }
    }
    const salonSeleccionado = sessionStorage.getItem('salonSeleccionado') || '1';
    const mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada') || 'BRA-000';
    const idComandaActual = sessionStorage.getItem('idcomanda') || localStorage.getItem('idcomanda') || 0;
    const data = {
        action: 'cobrarCuentaDividida',
        cesta: carritofin,
        formapago: formapago,
        codcliente: codcliente,
        salon: salonSeleccionado,
        mesa: mesaSeleccionada,
        precioacobrar: precioacobrar,
        importeentregado: importeEntregado,
        serie: serieSeleccionada,
        idcomanda: idComandaActual
    };
    const docTypeField = document.getElementById('TipoDocumento');
    if (docTypeField && docTypeField.value) {
        data.doctype = docTypeField.value;
    }

    if (window.DixTPVBonos && window.DixTPVBonos.context && window.DixTPVBonos.context.aplicado > 0) {
        data.bonoCodigo = window.DixTPVBonos.context.codigo;
        data.bonoImporte = window.DixTPVBonos.context.aplicado;
        if (typeof window.DixTPVBonos.context.importeOriginal === 'number') {
            data.bonoTotal = window.DixTPVBonos.context.importeOriginal;
        }
    }

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        success: function (response) {
            try {
                const jsonResponse = JSON.parse(response);
                if (!!jsonResponse.success) {
                    const finalizarCobro = () => {
                        const carritoPendiente = getStoredArray('carrito');
                        if (!carritoPendiente.length) {
                            localStorage.removeItem('carritofin');
                            localStorage.removeItem('camarero');
                            sessionStorage.removeItem('salonSeleccionado');
                            sessionStorage.removeItem('mesaSeleccionada');
                            sessionStorage.removeItem('idcomanda');
                            localStorage.removeItem('idcomanda');
                            cargarProductosCobrados();
                            actualizarCarrito();
                            location.reload();
                        } else {
                            localStorage.removeItem('carritofin');
                            cargarProductosCobrados();
                            actualizarCarrito();
                        }
                    };

                    const docInfo = jsonResponse.document || null;
                    if (docInfo) {
                        localStorage.setItem('ultimoDocumentoTPV', JSON.stringify(docInfo));
                    }
                    const triggerDrawer = () => {
                        if (jsonResponse.cashDrawer && typeof window.dixTpvHandleCashDrawerResponse === 'function') {
                            return Promise.resolve(window.dixTpvHandleCashDrawerResponse(jsonResponse.cashDrawer));
                        }
                        if (typeof abrirCajon === 'function') {
                            try {
                                return Promise.resolve(abrirCajon());
                            } catch (openErr) {
                                console.warn('No se pudo ejecutar la llamada estándar para abrir el cajón (dividido).', openErr);
                            }
                        }
                        return Promise.resolve(false);
                    };

                    const drawerPromise = triggerDrawer().catch(err => {
                        console.warn('Fallo abriendo el cajón (dividido).', err);
                    });
                    const printPromise = Promise.resolve(automaticTicketPrint(docInfo));

                    Promise.allSettled([drawerPromise, printPromise]).finally(finalizarCobro);
                } else {
                    alert('Error: ' + (jsonResponse.message || 'Error desconocido.'));
                }
            } catch (e) {
                console.error('Error al procesar la respuesta del servidor:', e);
                console.error('Respuesta del servidor:', response);
                alert('Error en la respuesta del servidor. Contacte con soporte técnico.');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('Error en la solicitud AJAX:', textStatus, errorThrown);
            alert('Error al comunicar con el servidor. Por favor, inténtalo de nuevo.');
        }
    });
}

function limpiarCobrado() {
    importeEntregado = 0;
    calcularCambio();
}

function CobrarModal() {

    const valor = tipoCobro;
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    const carritofin = JSON.parse(localStorage.getItem('carritofin')) || [];


    if (carrito.length === 0 && carritofin.length === 0) {
        alert('No hay productos en la cesta para cobrar.');
        return;
    }

    switch (tipoCobro) {
        case TCOBRO_NORMAL:
            cobrarCuenta();
            break;
        case TCOBRO_DC:
            CobrarCuentaDividida();
            break;
    }
}


const monedasYBilletes = document.querySelectorAll('.moneda, .billete');
const formas = document.querySelectorAll('.pago');



document.addEventListener("DOMContentLoaded", function () {
    tipoCobro = TCOBRO_NORMAL;

    const formasPago = document.getElementById("formasPago");
    const divMonedasBilletes = document.getElementById("monedasBilletes");
    const botonesPago = document.querySelectorAll("#formasPago .pago");

    const botonesSerie = document.querySelectorAll("#series .pago"); // 👈 añadimos selector para series

    if (formasPago && divMonedasBilletes) {
        agregarEventosPago(botonesPago, divMonedasBilletes);
    }

    if (botonesSerie.length > 0) {
        botonesSerie.forEach(boton => {
            boton.addEventListener("click", function () {
                botonesSerie.forEach(btn => btn.classList.remove("activo"));
                boton.classList.add("activo");

                const valor = boton.getAttribute("data-valor");
                document.getElementById("SerieSeleccionada").value = valor;
            });
        });
    }
    const docTypeButtons = document.querySelectorAll("#tiposDocumento .pago");
    const docTypeInput = document.getElementById("TipoDocumento");
    if (docTypeButtons.length > 0 && docTypeInput) {
        docTypeButtons.forEach(boton => {
            boton.addEventListener("click", function () {
                docTypeButtons.forEach(btn => btn.classList.remove("activo"));
                boton.classList.add("activo");
                docTypeInput.value = boton.getAttribute("data-valor");
            });
        });
    }

    $('#modalfinalizar').on('shown.bs.modal', function () {
        inicializarModalCobro();
    });
});


function agregarEventosPago(botones, divMonedasBilletes) {
    botones.forEach(boton => {
        boton.addEventListener("click", function () {
            botones.forEach(btn => btn.classList.remove("activo"));
            boton.classList.add("activo");

            const esEfectivo = boton.getAttribute("data-esefectivo") === "true";
            actualizarVisibilidad(esEfectivo, divMonedasBilletes);
        });
    });

    const monedasYBilletes = document.querySelectorAll('.moneda, .billete'); // <-- mueve aquí
    monedasYBilletes.forEach(element => {
        element.addEventListener('click', function () {
            const valor = parseFloat(this.getAttribute('data-valor'));
            importeEntregado += valor;
            document.getElementById('totEntregado').textContent = importeEntregado.toFixed(2);
            calcularCambio();
        });
    });
}

function calcularCambio() {
    importeCambio = Math.max(importeEntregado - importeCobrar, 0);
    actualizaImportesTotales();
}

function sincronizarImporteCobrarDesdeStorage() {
    const almacenado = parseFloat(localStorage.getItem('precioacobrar'));
    const totalCuentaDividida = parseFloat(localStorage.getItem('totalCuentaDividida'));
    const nuevoImporte = !isNaN(almacenado)
        ? almacenado
        : (!isNaN(totalCuentaDividida) ? totalCuentaDividida : totalVenta);
    if (!isNaN(nuevoImporte)) {
        importeCobrar = nuevoImporte;
        calcularCambio();
    } else {
        actualizaImportesTotales();
    }
}

window.dixSyncCobroTotals = sincronizarImporteCobrarDesdeStorage;

window.dixTpvLastTicketDoc = window.dixTpvLastTicketDoc || null;

function fallbackWaitForQZ(maxAttempts = 240, interval = 500) {
    if (typeof isQZAvailable !== 'function' || !isQZAvailable() || typeof qz === 'undefined' || !qz.websocket) {
        return Promise.resolve(false);
    }

    if (qz.websocket.isActive()) {
        return Promise.resolve(true);
    }

    return new Promise(resolve => {
        let attempts = 0;

        const checkStatus = () => {
            if (typeof isQZAvailable !== 'function' || !isQZAvailable() || typeof qz === 'undefined' || !qz.websocket) {
                resolve(false);
                return;
            }

            if (qz.websocket.isActive()) {
                resolve(true);
                return;
            }

            attempts += 1;
            if (attempts >= maxAttempts) {
                resolve(false);
                return;
            }

            setTimeout(checkStatus, interval);
        };

        checkStatus();
    });
}

function ensureQZSession(maxAttempts = 240, interval = 500) {
    let connectionAttempt = Promise.resolve(false);

    if (typeof connectQZTray === 'function') {
        try {
            connectionAttempt = Promise.resolve(connectQZTray()).catch(err => {
                console.warn('No se pudo iniciar la conexión con QZ Tray.', err);
                return false;
            });
        } catch (err) {
            console.warn('No se pudo iniciar la conexión con QZ Tray.', err);
        }
    }

    return connectionAttempt.then(() => {
        if (typeof waitForQZConnection === 'function') {
            try {
                return Promise.resolve(waitForQZConnection(maxAttempts, interval)).catch(() => fallbackWaitForQZ(maxAttempts, interval));
            } catch (err) {
                console.warn('Error esperando la conexión de QZ Tray.', err);
            }
        }
        return fallbackWaitForQZ(maxAttempts, interval);
    }).catch(() => fallbackWaitForQZ(maxAttempts, interval));
}

function resolveStoredTicketPrinterId() {
    let storedId = localStorage.getItem('selectedQzPrinterId') || '';
    if (!storedId) {
        const selector = document.getElementById('qzPrinterSelector');
        if (selector) {
            const firstOption = selector.querySelector('option[value]:not([value=""])');
            if (firstOption) {
                storedId = firstOption.value;
                localStorage.setItem('selectedQzPrinterId', storedId);
            }
        }
    }
    return storedId;
}

function resolveStoredTicketFormat(format) {
    if (format && format.length) {
        localStorage.setItem('ticketFormatClass', format);
        return format;
    }

    const storedFormat = localStorage.getItem('ticketFormatClass');
    if (storedFormat && storedFormat.length) {
        if (storedFormat === 'FacturaScripts\\Plugins\\Tickets\\Lib\\Tickets\\Normal') {
            localStorage.setItem('ticketFormatClass', DEFAULT_TICKET_FORMAT);
            return DEFAULT_TICKET_FORMAT;
        }
        return storedFormat;
    }

    const defaultFormat = DEFAULT_TICKET_FORMAT;
    localStorage.setItem('ticketFormatClass', defaultFormat);
    return defaultFormat;
}

function resolveStoredTicketPaperWidth(paperWidth) {
    if (paperWidth && paperWidth.length) {
        localStorage.setItem('ticketPaperWidth', paperWidth);
        return paperWidth;
    }

    const storedWidth = localStorage.getItem('ticketPaperWidth');
    if (storedWidth && storedWidth.length) {
        return storedWidth;
    }

    const defaultWidth = '80';
    localStorage.setItem('ticketPaperWidth', defaultWidth);
    return defaultWidth;
}

function requestTicketEscposData(docInfo, printerId, formatClass, paperWidth) {
    const attemptRequest = (selectedFormat, triedFallback) => {
        return new Promise((resolve, reject) => {
            $.ajax({
                method: 'POST',
                url: 'SendTicket',
                dataType: 'json',
                data: {
                    action: 'get-escpos',
                    modelClassName: docInfo.modelClassName,
                    modelCode: docInfo.modelCode,
                    printer: printerId,
                    format: selectedFormat,
                    paperWidth: paperWidth
                }
            }).done(function (response) {
                if (response && response.ok && response.data) {
                    if (response.base64) {
                        try {
                            const decoder = (typeof window !== 'undefined' && typeof window.atob === 'function') ? window.atob : (typeof atob === 'function' ? atob : null);
                            if (!decoder) {
                                throw new Error('Base64 decoder not available');
                            }
                            response.data = decoder(response.data);
                        } catch (err) {
                            reject({ error: 'Invalid ticket payload', details: err });
                            return;
                        }
                    }
                    resolve(response);
                } else if (!triedFallback && response && typeof response.error === 'string' &&
                    response.error.toLowerCase().indexOf('formato') !== -1) {
                    console.warn('Formato de ticket no disponible. Probando con el formato de respaldo.');
                    attemptRequest(DIXTPV_FALLBACK_TICKET_FORMAT, true).then(resolve).catch(reject);
                } else {
                    reject(response);
                }
            }).fail(function (jqXHR, textStatus, errorThrown) {
                reject({ error: errorThrown || textStatus });
            });
        });
    };

    return attemptRequest(formatClass, false);
}

function ensureSelectedPrinterName() {
    let storedName = '';
    try {
        storedName = localStorage.getItem('selectedQzPrinterName') || '';
    } catch (err) {
        console.warn('No se pudo acceder al nombre de impresora almacenado.', err);
    }

    if (storedName) {
        if (typeof setSelectedPrinterName === 'function') {
            try {
                setSelectedPrinterName(storedName);
            } catch (applyErr) {
                console.warn('No se pudo aplicar el nombre de impresora guardado.', applyErr);
            }
        }
        return Promise.resolve(storedName);
    }

    if (typeof qz === 'undefined' || !qz.printers || typeof qz.printers.find !== 'function') {
        return Promise.resolve('');
    }

    return qz.printers.find().then(result => {
        const printers = Array.isArray(result) ? result : (result ? [result] : []);
        if (printers.length > 0) {
            const firstName = printers[0];
            try {
                localStorage.setItem('selectedQzPrinterName', firstName);
            } catch (storeErr) {
                console.warn('No se pudo guardar el nombre de la impresora seleccionada.', storeErr);
            }

            if (typeof setSelectedPrinterName === 'function') {
                try {
                    setSelectedPrinterName(firstName);
                } catch (innerErr) {
                    console.warn('No se pudo actualizar la impresora seleccionada automáticamente.', innerErr);
                }
            }
            return firstName;
        }
        return '';
    }).catch(err => {
        console.warn('No se pudo recuperar la lista de impresoras QZ.', err);
        return '';
    });
}

if (typeof window.skipDefaultTicketPreviewLoad === 'undefined') {
    window.skipDefaultTicketPreviewLoad = false;
}

function displayTicketPreview(escposData) {
    return new Promise((resolve) => {
        if (typeof $ === 'undefined') {
            resolve();
            return;
        }

        const modalEl = $('#ticketPreviewPrintModal');
        if (!modalEl.length) {
            resolve();
            return;
        }

        let resolved = false;
        const finish = () => {
            if (resolved) {
                return;
            }
            resolved = true;
            window.skipDefaultTicketPreviewLoad = false;
            resolve();
        };

        const applyPreview = () => {
            try {
                const container = $('#ticketPreviewContainer');
                if (typeof generateHtmlPreview === 'function') {
                    const previewHtml = generateHtmlPreview(escposData);
                    if (container.length) {
                        container.html(previewHtml);
                    }
                } else if (container.length) {
                    container.text('Vista previa de ticket no disponible.');
                }
            } catch (previewErr) {
                console.warn('No se pudo generar la vista previa del ticket.', previewErr);
            }

            const printBtn = $('#printTicketButton');
            if (printBtn.length) {
                printBtn.prop('disabled', false);
            }

            try {
                receivedESCPOSTData = escposData;
            } catch (assignErr) {
                window.receivedESCPOSTData = escposData;
            }

            if (typeof showModalAlert === 'function') {
                try {
                    showModalAlert('Ticket preparado para impresión.', 'info', 3000, 'modalPrintAlert', 'modalPrintAlertMessage');
                } catch (alertErr) {
                    console.warn('No se pudo mostrar el aviso de impresión.', alertErr);
                }
            }

            finish();
        };

        window.skipDefaultTicketPreviewLoad = true;

        if (!modalEl.hasClass('show')) {
            modalEl.one('shown.autoTicketPreview', function () {
                modalEl.off('shown.autoTicketPreview');
                applyPreview();
            });
            modalEl.one('hidden.autoTicketPreview', function () {
                modalEl.off('hidden.autoTicketPreview');
                finish();
            });
            try {
                modalEl.modal('show');
            } catch (modalErr) {
                console.warn('No se pudo abrir el modal de ticket.', modalErr);
                finish();
            }
        } else {
            applyPreview();
        }
    });
}

function loadTicketDataAsync(printerId, formatClass, paperWidth, docInfo) {
    const effectiveDoc = docInfo || window.dixTpvLastTicketDoc;

    return new Promise((resolve, reject) => {
        if (!effectiveDoc || !effectiveDoc.modelClassName || !effectiveDoc.modelCode) {
            reject(new Error('No hay datos del ticket para imprimir.'));
            return;
        }

        if (!printerId) {
            if (typeof showModalAlert === 'function') {
                showModalAlert('Selecciona una impresora antes de imprimir.', 'warning', 5000, 'modalPrintAlert', 'modalPrintAlertMessage');
            }
            reject(new Error('No printer selected'));
            return;
        }

        const formatOverride = (effectiveDoc && effectiveDoc.formatClass) ? effectiveDoc.formatClass : null;
        const widthOverrideRaw = (effectiveDoc && effectiveDoc.paperWidth) ? String(effectiveDoc.paperWidth) : null;
        const resolvedFormat = formatOverride || resolveStoredTicketFormat(formatClass || null);
        const resolvedWidth = widthOverrideRaw || resolveStoredTicketPaperWidth(paperWidth || null);

        try {
            localStorage.setItem('selectedQzPrinterId', printerId);
            localStorage.setItem('ticketPaperWidth', widthOverrideRaw || resolvedWidth);
            localStorage.setItem('ticketFormatClass', formatOverride || resolvedFormat);
        } catch (storeErr) {
            console.warn('No se pudieron guardar las preferencias de impresión.', storeErr);
        }

        if (typeof $ !== 'undefined') {
            const printBtn = $('#printTicketButton');
            if (printBtn.length) {
                printBtn.prop('disabled', true);
            }
            const previewContainer = $('#ticketPreviewContainer');
            if (previewContainer.length) {
                previewContainer.html('<p class="text-muted text-center">Cargando ticket...</p>');
            }
        }

        if (typeof showModalAlert === 'function') {
            showModalAlert('Preparando vista previa...', 'info', 0, 'modalPrintAlert', 'modalPrintAlertMessage');
        }

        requestTicketEscposData(effectiveDoc, printerId, resolvedFormat, resolvedWidth).then(response => {
            if (!response || !response.ok || !response.data) {
                const errorMsg = (response && response.error) || 'No se recibieron datos válidos del ticket.';
                if (typeof $ !== 'undefined') {
                    const previewContainer = $('#ticketPreviewContainer');
                    if (previewContainer.length) {
                        previewContainer.html('<p class="text-danger text-center">' + errorMsg + '</p>');
                    }
                    const printBtn = $('#printTicketButton');
                    if (printBtn.length) {
                        printBtn.prop('disabled', true);
                    }
                }
                if (typeof showModalAlert === 'function') {
                    showModalAlert(errorMsg, 'danger', 0, 'modalPrintAlert', 'modalPrintAlertMessage');
                }
                reject(new Error(errorMsg));
                return;
            }

            const escpos = response.data;
            let previewHtml = '';

            if (typeof generateHtmlPreview === 'function') {
                try {
                    previewHtml = generateHtmlPreview(escpos);
                } catch (previewErr) {
                    console.warn('No se pudo generar la vista previa del ticket.', previewErr);
                }
            }

            if (typeof $ !== 'undefined') {
                const previewContainer = $('#ticketPreviewContainer');
                if (previewContainer.length) {
                    if (previewHtml) {
                        previewContainer.html(previewHtml);
                    } else {
                        previewContainer.text('Ticket preparado para impresión.');
                    }
                }
                const printBtn = $('#printTicketButton');
                if (printBtn.length) {
                    printBtn.prop('disabled', false);
                }
            }

            if (typeof showModalAlert === 'function') {
                showModalAlert('Ticket listo para imprimir.', 'success', 5000, 'modalPrintAlert', 'modalPrintAlertMessage');
            }

            try {
                receivedESCPOSTData = escpos;
            } catch (assignErr) {
                window.receivedESCPOSTData = escpos;
            }

            resolve({ escpos, previewHtml, format: resolvedFormat, paperWidth: resolvedWidth });
        }).catch(error => {
            const errorMsg = (error && error.error) || (error && error.message) || 'Error al solicitar los datos del ticket.';
            if (typeof $ !== 'undefined') {
                const previewContainer = $('#ticketPreviewContainer');
                if (previewContainer.length) {
                    previewContainer.html('<p class="text-danger text-center">' + errorMsg + '</p>');
                }
                const printBtn = $('#printTicketButton');
                if (printBtn.length) {
                    printBtn.prop('disabled', true);
                }
            }
            if (typeof showModalAlert === 'function') {
                showModalAlert(errorMsg, 'danger', 0, 'modalPrintAlert', 'modalPrintAlertMessage');
            }
            reject(new Error(errorMsg));
        });
    });
}

window.dixTpvLoadTicketData = function (printerId, format, paperWidth) {
    const docInfo = window.dixTpvLastTicketDoc;
    if (!docInfo) {
        return false;
    }

    const resolvedPrinterId = printerId || resolveStoredTicketPrinterId();
    if (!resolvedPrinterId) {
        alert('Configura una impresora de tickets en el módulo Tickets antes de imprimir.');
        return true;
    }

    const formatClass = resolveStoredTicketFormat(format || null);
    const resolvedPaperWidth = resolveStoredTicketPaperWidth(paperWidth || null);

    if (typeof $ !== 'undefined') {
        const selector = $('#qzPrinterSelector');
        if (selector.length) {
            selector.val(resolvedPrinterId);
        }
        const paperSelect = $('#paperWidth');
        if (paperSelect.length) {
            paperSelect.val(resolvedPaperWidth);
        }
        const formatSelect = $('select[name="format"]');
        if (formatSelect.length) {
            formatSelect.val(formatClass);
        }
    }

    loadTicketDataAsync(resolvedPrinterId, formatClass, resolvedPaperWidth, docInfo).catch(error => {
        console.error('Error recargando el ticket para la vista previa.', error);
    });

    return true;
};

async function imprimirTicketSecuencia(docInfoInput) {
    const docInfo = (docInfoInput && docInfoInput.modelClassName && docInfoInput.modelCode)
        ? docInfoInput
        : window.dixTpvLastTicketDoc;

    if (!docInfo || !docInfo.modelClassName || !docInfo.modelCode) {
        console.warn('No hay información del ticket para imprimir.');
        if (typeof showModalAlert === 'function') {
            showModalAlert('No hay ticket disponible para imprimir.', 'warning', 5000, 'modalPrintAlert', 'modalPrintAlertMessage');
        }
        return false;
    }

    window.dixTpvLastTicketDoc = docInfo;

    let modalEl = null;
    let manualCompletionPromise = null;
    let manualCleanup = null;

    const requestManualFallback = () => {
        if (manualCompletionPromise) {
            return manualCompletionPromise;
        }

        if (typeof $ === 'undefined') {
            return Promise.resolve(false);
        }

        modalEl = $('#ticketPreviewPrintModal');
        if (!modalEl.length) {
            return Promise.resolve(false);
        }

        manualCompletionPromise = new Promise(resolve => {
            const finalize = (result) => {
                if (modalEl && modalEl.length) {
                    modalEl.off('.dixTpvAuto');
                    if (result && modalEl.hasClass('show')) {
                        try {
                            modalEl.modal('hide');
                        } catch (hideErr) {
                            console.warn('No se pudo cerrar el modal tras la impresión.', hideErr);
                        }
                    }
                }
                manualCleanup = null;
                modalEl = null;
                const outcome = !!result;
                const resolver = resolve;
                manualCompletionPromise = null;
                resolver(outcome);
            };

            const onPrinted = () => finalize(true);
            const onHidden = () => finalize(false);

            manualCleanup = finalize;
            modalEl.one('dixTpvTicketPrinted.dixTpvAuto', onPrinted);
            modalEl.one('hidden.bs.modal.dixTpvAuto', onHidden);
        });

        try {
            modalEl.modal('show');
        } catch (modalErr) {
            console.warn('No se pudo abrir el modal de impresión.', modalErr);
        }

        if (typeof loadFromLocalStorage === 'function') {
            try {
                loadFromLocalStorage();
            } catch (loadErr) {
                console.warn('No se pudieron cargar los ajustes guardados.', loadErr);
            }
        }

        if (typeof listSystemPrinters === 'function') {
            try {
                listSystemPrinters();
            } catch (listErr) {
                console.warn('No se pudieron listar las impresoras disponibles.', listErr);
            }
        }

        return manualCompletionPromise;
    };

    if (typeof loadFromLocalStorage === 'function') {
        try {
            loadFromLocalStorage();
        } catch (loadErr) {
            console.warn('No se pudieron cargar los ajustes guardados.', loadErr);
        }
    }

    const qzReadyPromise = ensureQZSession();

    let printerId = docInfo.printerId || resolveStoredTicketPrinterId();
    if (typeof $ !== 'undefined') {
        const selector = $('#qzPrinterSelector');
        if (selector.length) {
            if (!printerId) {
                const firstOption = selector.find('option[value]:not([value=""])').first().val();
                printerId = firstOption || selector.val() || printerId;
            }
            if (printerId) {
                selector.val(printerId);
            }
        }
    }
    if (!printerId) {
        printerId = resolveStoredTicketPrinterId();
    }
    if (!printerId) {
        if (typeof showModalAlert === 'function') {
            showModalAlert('Selecciona una impresora antes de imprimir.', 'warning', 5000, 'modalPrintAlert', 'modalPrintAlertMessage');
        }
        return requestManualFallback();
    }

    const selectedFormatValue = typeof $ !== 'undefined' ? ($('select[name="format"]').val() || null) : null;
    const docFormatClass = docInfo.formatClass || null;
    const docPaperWidth = docInfo.paperWidth || null;
    const formatClass = docFormatClass || resolveStoredTicketFormat(selectedFormatValue);
    const paperWidth = docPaperWidth || resolveStoredTicketPaperWidth(typeof $ !== 'undefined' ? ($('#paperWidth').val() || null) : null);

    if (typeof $ !== 'undefined') {
        const paperSelect = $('#paperWidth');
        if (paperSelect.length) {
            paperSelect.val(paperWidth);
        }
        const formatSelect = $('select[name="format"]');
        if (formatSelect.length && formatClass) {
            if (formatSelect.find('option[value="' + formatClass + '"]').length) {
                formatSelect.val(formatClass);
            }
        }
    }

    let ticketData;
    try {
        ticketData = await loadTicketDataAsync(printerId, formatClass, paperWidth, docInfo);
    } catch (ticketErr) {
        console.error('Error cargando el ticket antes de imprimir.', ticketErr);
        return requestManualFallback();
    }

    let qzReady = false;
    try {
        qzReady = await qzReadyPromise;
    } catch (readyErr) {
        console.warn('No se pudo confirmar la conexión con QZ Tray.', readyErr);
    }

    if (!qzReady) {
        console.warn('QZ Tray no está conectado; se deja la vista previa para que el usuario imprima manualmente.');
        return requestManualFallback();
    }

    try {
        await ensureSelectedPrinterName();
    } catch (nameErr) {
        console.warn('No se pudo asegurar el nombre de la impresora seleccionada.', nameErr);
    }

    if (typeof sendEscposToPrinter !== 'function') {
        console.warn('La función sendEscposToPrinter no está disponible.');
        return requestManualFallback();
    }

    try {
        const result = await sendEscposToPrinter(ticketData.escpos, 'modalPrintAlert', 'modalPrintAlertMessage');
        if (!result) {
            console.warn('La impresión no se completó correctamente.');
            return requestManualFallback();
        }
        if (manualCleanup) {
            manualCleanup(true);
        }
        return true;
    } catch (printErr) {
        console.error('Error enviando los datos ESC/POS a QZ Tray.', printErr);
        return requestManualFallback();
    }
}

function automaticTicketPrint(docInfo) {
    const policy = parseInt(window.dixTpvPrintPolicy ?? '1', 10);

    if (policy === 0) {
        // Nunca imprimir automáticamente
        return Promise.resolve(false);
    }

    if (policy === -1) {
        return requestPrintConfirmation().then(shouldPrint => {
            if (!shouldPrint) {
                return false;
            }
            return imprimirTicketSecuencia(docInfo);
        }).catch(err => {
            console.error('Error en la confirmación de impresión.', err);
            return false;
        });
    }

    return Promise.resolve(imprimirTicketSecuencia(docInfo)).catch(err => {
        console.error('Error en la impresión automática del ticket.', err);
    });
}

function requestPrintConfirmation() {
    return new Promise(resolve => {
        const overlay = document.getElementById('printConfirmOverlay');
        if (!overlay) {
            resolve(window.confirm('¿Quieres imprimir el ticket?'));
            return;
        }

        const yesBtn = overlay.querySelector('.btn-print-yes');
        const noBtn = overlay.querySelector('.btn-print-no');

        const cleanup = (result) => {
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
            yesBtn && yesBtn.removeEventListener('click', onYes);
            noBtn && noBtn.removeEventListener('click', onNo);
            overlay.removeEventListener('click', onOverlayClick);
            document.removeEventListener('keydown', onKey);
            resolve(result);
        };

        const onYes = () => cleanup(true);
        const onNo = () => cleanup(false);
        const onOverlayClick = (event) => {
            if (event.target === overlay) {
                cleanup(false);
            }
        };
        const onKey = (event) => {
            if (event.key === 'Escape') {
                cleanup(false);
            }
        };

        yesBtn && yesBtn.addEventListener('click', onYes, {once: true});
        noBtn && noBtn.addEventListener('click', onNo, {once: true});
        overlay.addEventListener('click', onOverlayClick);
        document.addEventListener('keydown', onKey);

        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
    });
}

if (typeof $ !== 'undefined') {
    $(document).on('click.dixTpvAutoPrint', '#printTicketForThermalPrinter', function (e) {
        const docInfo = window.dixTpvLastTicketDoc;
        if (!docInfo || !docInfo.modelClassName || !docInfo.modelCode) {
            return;
        }
        e.preventDefault();
        Promise.resolve(imprimirTicketSecuencia(docInfo)).catch(err => {
            console.error('Error lanzando la impresión automática desde el botón.', err);
        });
    });
}

function actualizaImportesTotales() {
    document.getElementById('totVenta').textContent = totalVenta.toFixed(2);
    document.getElementById('totCobrar').textContent = importeCobrar.toFixed(2);
    document.getElementById('totEntregado').textContent = importeEntregado.toFixed(2);
    document.getElementById('totCambio').textContent = importeCambio.toFixed(2);
}

function inicializarModalCobro() {
    //console.log("Inicializamos valores cuando se muestra el Modal");
    toggleNormal();
    iniciaValores();
    actualizaImportesTotales();
}
function iniciaValores() {
    document.getElementById("totVenta").textContent = parseFloat(totalVenta) || 0;
    document.getElementById("totCobrar").textContent = parseFloat(localStorage.getItem('precioacobrar')) || 0;
    document.getElementById("totEntregado").textContent = 0.00;
    document.getElementById("totCambio").textContent = 0.00;


    importeCobrar = parseFloat(localStorage.getItem('precioacobrar')) || 0;
    importeEntregado = 0.00;
    importeCambio = 0.00;
}

function actualizarVisibilidad(esEfectivo, div) {
    div.style.display = esEfectivo ? "flex" : "none";

    if (esEfectivo) {
        importeEntregado = 0;
        actualizaImportesTotales();
    } else {
        setEntregadoToTotal();
    }
}

function setEntregadoToTotal() {
    const total = parseFloat(localStorage.getItem('importeCobrar')) || 0;
    const totalDiv = parseFloat(localStorage.getItem('totalCuentaDividida')) || 0;
    importeEntregado = importeCobrar;
    calcularCambio();
    actualizaImportesTotales();

}

function cobrar() {
    idcomanda = localStorage.getItem('idcomanda');
    eliminarCesta();
}

function pagarFacturaAnterior(idFactura) {
    if (!idFactura) {
        return;
    }

    solicitarFormaPagoParaFactura().then(codPago => {
        if (!codPago) {
            return;
        }

        const proceed = window.confirm('¿Quieres registrar el cobro pendiente de esta factura?');
        if (!proceed) {
            return;
        }

        $.ajax({
            method: 'POST',
            url: window.location.href,
            data: {
                action: 'pagarFacturaAnterior',
                idfactura: idFactura,
                codpago: codPago
            },
            success: function (response) {
                const payload = parseDixAjaxJson(response);
                if (!payload || typeof payload.success === 'undefined') {
                    setToast('Respuesta inesperada al cobrar la factura.', 'danger', 'Cobro de factura', 4000);
                    return;
                }

                if (payload.success) {
                    actualizarFilaFacturaPagada(idFactura);
                    if (payload.cashDrawer && typeof window.dixTpvHandleCashDrawerResponse === 'function') {
                        Promise.resolve(window.dixTpvHandleCashDrawerResponse(payload.cashDrawer)).catch(() => {
                            console.warn('No se pudo abrir el cajón después del cobro.');
                        });
                    }
                    setToast(payload.message || 'Factura cobrada correctamente.', 'success', 'Cobro de factura', 4000);
                } else {
                    setToast(payload.message || 'No se pudo registrar el cobro de la factura.', 'danger', 'Cobro de factura', 4000);
                }
            },
            error: function () {
                setToast('No se pudo comunicar con el servidor para cobrar la factura.', 'danger', 'Cobro de factura', 4000);
            }
        });
    });
}

function facturarAlbaranAnterior(idAlbaran) {
    if (!idAlbaran) {
        return;
    }

    const proceed = window.confirm('¿Deseas generar una factura para este albarán?');
    if (!proceed) {
        return;
    }

    $.ajax({
        method: 'POST',
        url: window.location.href,
        data: {
            action: 'facturarAlbaranAnterior',
            idalbaran: idAlbaran
        },
        success: function (response) {
            const payload = parseDixAjaxJson(response);
            if (!payload || typeof payload.success === 'undefined') {
                setToast('Respuesta inesperada al facturar el albarán.', 'danger', 'Facturar albarán', 4000);
                return;
            }

            if (payload.success) {
                actualizarFilaAlbaranFacturado(idAlbaran, payload.factura || {});
                setToast(payload.message || 'Albarán facturado correctamente.', 'success', 'Facturar albarán', 4000);
            } else {
                setToast(payload.message || 'No se pudo facturar el albarán.', 'danger', 'Facturar albarán', 4000);
            }
        },
        error: function () {
            setToast('No se pudo comunicar con el servidor para facturar el albarán.', 'danger', 'Facturar albarán', 4000);
        }
    });
}

function actualizarFilaFacturaPagada(idFactura) {
    const selector = `.previous-doc-row[data-doc-type="FacturaCliente"][data-doc-id="${idFactura}"]`;
    const row = document.querySelector(selector);
    if (!row) {
        return;
    }

    row.classList.remove('text-danger', 'previous-doc-unpaid', 'fw-bold');
    row.dataset.pagada = '1';

    const payButton = row.querySelector('.btn-pay-invoice');
    if (payButton) {
        payButton.remove();
    }
}

function actualizarFilaAlbaranFacturado(idAlbaran, facturaDatos) {
    const selector = `.previous-doc-row[data-doc-type="AlbaranCliente"][data-doc-id="${idAlbaran}"]`;
    const row = document.querySelector(selector);
    if (!row) {
        return;
    }

    row.classList.add('previous-doc-converted', 'text-success', 'fw-bold');
    row.dataset.facturado = '1';

    const actionContainer = row.querySelector('.doc-actions');
    if (actionContainer) {
        const convertBtn = actionContainer.querySelector('.btn-convert-albaran');
        if (convertBtn) {
            convertBtn.remove();
        }

        if (!actionContainer.querySelector('.previous-doc-badge')) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-success text-white previous-doc-badge';
            badge.textContent = 'Facturado';
            actionContainer.appendChild(badge);
        }
    }

    if (facturaDatos && facturaDatos.codigo) {
        const codeContainer = row.querySelector('.previous-doc-code');
        if (codeContainer && !codeContainer.querySelector('.linked-invoice-label')) {
            const info = document.createElement('small');
            info.className = 'd-block text-success fw-semibold linked-invoice-label';
            info.textContent = `Factura ${facturaDatos.codigo}`;
            codeContainer.appendChild(info);
        }
    }

    if (facturaDatos) {
        agregarFacturaAnteriorDesdeDatos(facturaDatos);
        marcarPedidoFacturadoDesdeAlbaran(idAlbaran, facturaDatos);
    }
}

function marcarPedidoFacturadoDesdeAlbaran(idAlbaran, facturaDatos = {}) {
    if (!idAlbaran) {
        return;
    }
    const rows = document.querySelectorAll(`.previous-doc-row[data-doc-type="PedidoCliente"][data-albaran-id="${idAlbaran}"]`);
    if (!rows.length) {
        return;
    }

    rows.forEach(row => {
        row.dataset.facturaId = facturaDatos.id || row.dataset.facturaId || '';
        row.dataset.facturaCode = facturaDatos.codigo || row.dataset.facturaCode || '';
        row.dataset.facturado = '1';
        row.classList.remove('text-warning');
        row.classList.add('text-success', 'fw-bold', 'previous-doc-converted');

        const codeContainer = row.querySelector('.previous-doc-code');
        const actions = row.querySelector('.doc-actions');

        if (codeContainer && facturaDatos.codigo) {
            setPedidoLabel(codeContainer, 'pedido-factura-label', `Factura ${facturaDatos.codigo}`, 'text-success');
        }
        if (actions) {
            togglePedidoActionButton(actions, '.btn-pedido-factura', 'badge bg-success text-white pedido-factura-badge', 'Facturado');
        }
    });
}

function parseDixAjaxJson(response) {
    if (typeof response === 'object' && response !== null) {
        return response;
    }

    if (typeof response === 'string') {
        try {
            return JSON.parse(response);
        } catch (err) {
            console.warn('No se pudo parsear la respuesta JSON.', err, response);
            return null;
        }
    }

    return null;
}

function obtenerOpcionesFormasPago() {
    const nodes = document.querySelectorAll('#formasPago .pago');
    if (!nodes.length) {
        return [];
    }

    return Array.from(nodes).map(node => ({
        value: node.dataset.valor || '',
        label: node.textContent ? node.textContent.trim() : '',
        activo: node.classList.contains('activo')
    })).filter(option => option.value !== '');
}

function solicitarFormaPagoParaFactura() {
    const opciones = obtenerOpcionesFormasPago();
    if (!opciones.length || typeof bootbox === 'undefined') {
        const activa = opciones.find(opt => opt.activo);
        return Promise.resolve(activa ? activa.value : (opciones[0] ? opciones[0].value : null));
    }

    const selectId = `bootboxFormaPago-${Date.now()}`;
    const defaultValue = (opciones.find(opt => opt.activo) || opciones[0]).value;
    const selectOptions = opciones.map(option => {
        const selected = option.value === defaultValue ? 'selected' : '';
        return `<option value="${option.value}" ${selected}>${option.label}</option>`;
    }).join('');

    const message = `
        <div class="mb-3">
            <label for="${selectId}" class="form-label">Forma de pago</label>
            <select id="${selectId}" class="form-select">
                ${selectOptions}
            </select>
        </div>`;

    return new Promise(resolve => {
        bootbox.dialog({
            title: 'Seleccionar forma de pago',
            message: message,
            onEscape: function () {
                resolve(null);
            },
            buttons: {
                cancel: {
                    label: 'Cancelar',
                    className: 'btn-secondary',
                    callback: function () {
                        resolve(null);
                    }
                },
                ok: {
                    label: 'Aceptar',
                    className: 'btn-primary',
                    callback: function () {
                        const select = document.getElementById(selectId);
                        resolve(select ? select.value : defaultValue);
                    }
                }
            }
        });
    });
}

function agregarFacturaAnteriorDesdeDatos(datos) {
    if (!datos || !datos.id) {
        return;
    }

    const existingRow = document.querySelector(`.previous-doc-row[data-doc-type="FacturaCliente"][data-doc-id="${datos.id}"]`);
    if (existingRow) {
        if (datos.pagada) {
            existingRow.classList.remove('text-danger', 'previous-doc-unpaid', 'fw-bold');
            existingRow.dataset.pagada = '1';
            const payButton = existingRow.querySelector('.btn-pay-invoice');
            if (payButton) {
                payButton.remove();
            }
        }
        return;
    }

    const container = document.querySelector('#anteriores-facturas .row.justify-content-center');
    if (!container) {
        return;
    }

    const emptyMessage = container.querySelector('.col-12.text-center');
    if (emptyMessage) {
        emptyMessage.remove();
    }

    const col = document.createElement('div');
    col.className = 'col-12';

    const row = document.createElement('div');
    row.className = 'row align-items-center text-center py-2 previous-doc-row';
    row.dataset.docType = 'FacturaCliente';
    row.dataset.docId = datos.id;
    row.dataset.docCode = datos.codigo || '';
    row.dataset.pagada = datos.pagada ? '1' : '0';
    row.dataset.facturado = '0';

    if (!datos.pagada) {
        row.classList.add('text-danger', 'fw-bold', 'previous-doc-unpaid');
    }

    const idCol = document.createElement('div');
    idCol.className = 'col-1';
    idCol.hidden = true;
    idCol.textContent = datos.id;

    const codeCol = document.createElement('div');
    codeCol.className = 'col-3 previous-doc-code';
    codeCol.textContent = datos.codigo || '';

    const fechaCol = document.createElement('div');
    fechaCol.className = 'col-3';
    fechaCol.textContent = datos.fecha || '';

    const horaCol = document.createElement('div');
    horaCol.className = 'col-3';
    horaCol.textContent = (datos.hora || '').substring(0, 5);

    const actionsCol = document.createElement('div');
    actionsCol.className = 'col-2 d-flex justify-content-center gap-2 doc-actions';

    const rectifyBtn = document.createElement('button');
    rectifyBtn.className = 'btn btn-sm btn-outline-warning';
    rectifyBtn.innerHTML = '<i class="fas fa-pen"></i>';
    rectifyBtn.addEventListener('click', () => abrirRectificativa(datos.id));

    const printBtn = document.createElement('button');
    printBtn.className = 'btn btn-sm btn-outline-primary';
    printBtn.innerHTML = '<i class="fas fa-print"></i>';
    printBtn.addEventListener('click', () => ImprimirViejo(datos.id, 'FacturaCliente'));

    actionsCol.appendChild(rectifyBtn);
    actionsCol.appendChild(printBtn);

    if (!datos.pagada) {
        const payBtn = document.createElement('button');
        payBtn.className = 'btn btn-sm btn-outline-success btn-pay-invoice';
        payBtn.title = 'Cobrar factura';
        payBtn.innerHTML = '<i class="fas fa-coins"></i>';
        payBtn.addEventListener('click', () => pagarFacturaAnterior(datos.id));
        actionsCol.appendChild(payBtn);
    }

    row.appendChild(idCol);
    row.appendChild(codeCol);
    row.appendChild(fechaCol);
    row.appendChild(horaCol);
    row.appendChild(actionsCol);

    col.appendChild(row);

    const header = container.querySelector('.col-12');
    if (header && header.nextSibling) {
        container.insertBefore(col, header.nextSibling);
    } else {
        container.appendChild(col);
    }
}
