function actualizarCantidad(index, cantidad) {
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    carrito[index].cantidad = parseInt(cantidad);
    localStorage.setItem('carrito', JSON.stringify(carrito));
    actualizarCarrito();
}
function mostrarAviso(titulo = 'Aviso', mensaje = '') {
    if (window.jQuery && $('#stockToast').length) {
        $('#stockToastTitle').text(titulo);
        $('#stockToastBody').text(mensaje);
        $('#stockToast').toast({delay: 3000});
        $('#stockToast').toast('show');
    } else {
        alert(`${titulo}\n\n${mensaje}`);
}
}

const dixTarifaCache = {};

function dixTarifaCacheKey(codCliente) {
    return codCliente && codCliente !== '' ? codCliente : '_default';
}

function dixSetCachedTarifa(codCliente, referencia, precio) {
    const key = dixTarifaCacheKey(codCliente);
    if (!dixTarifaCache[key]) {
        dixTarifaCache[key] = {};
    }
    dixTarifaCache[key][referencia] = precio;
}

function dixGetCachedTarifa(codCliente, referencia) {
    const key = dixTarifaCacheKey(codCliente);
    if (dixTarifaCache[key] && typeof dixTarifaCache[key][referencia] !== 'undefined') {
        return parseFloat(dixTarifaCache[key][referencia]);
    }
    return null;
}

function dixGetSelectedClientCode() {
    const hidden = document.getElementById('cliente');
    return hidden ? hidden.value : '';
}

function dixQuoteTariffPrices(referencias, codCliente) {
    return new Promise((resolve) => {
        if (!window.dixTarifasAvanzadas || false === Array.isArray(referencias) || referencias.length === 0) {
            resolve({});
            return;
        }

        $.ajax({
            method: 'POST',
            url: window.location.href,
            dataType: 'json',
            data: {
                action: 'quoteTariffPrice',
                codcliente: codCliente || '',
                referencias: referencias
            },
            success: function (resp) {
                if (resp && resp.prices) {
                    resolve(resp.prices);
                } else {
                    resolve({});
                }
            },
            error: function () {
                resolve({});
            }
        });
    });
}

async function dixGetTariffPriceForReference(referencia, precioBase) {
    if (!window.dixTarifasAvanzadas) {
        return precioBase;
    }

    const codCliente = dixGetSelectedClientCode();
    const cached = dixGetCachedTarifa(codCliente, referencia);
    if (cached !== null && !isNaN(cached)) {
        return cached;
    }

    const prices = await dixQuoteTariffPrices([referencia], codCliente);
    if (prices && typeof prices[referencia] !== 'undefined') {
        const value = parseFloat(prices[referencia]);
        if (!isNaN(value)) {
            dixSetCachedTarifa(codCliente, referencia, value);
            return value;
        }
    }

    return precioBase;
}

async function dixActualizarCarritoConTarifa(codCliente) {
    if (!window.dixTarifasAvanzadas) {
        return;
    }

    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    if (!carrito.length) {
        return;
    }

    const referencias = carrito
        .map(item => item.referencia)
        .filter(ref => typeof ref === 'string' && ref.length > 0);
    if (!referencias.length) {
        return;
    }

    const prices = await dixQuoteTariffPrices(referencias, codCliente);
    if (!prices || Object.keys(prices).length === 0) {
        return;
    }

    let updated = false;
    const nuevoCarrito = carrito.map(item => {
        if (item && item.pvpManual) {
            return item;
        }
        const ref = item.referencia;
        if (ref && Object.prototype.hasOwnProperty.call(prices, ref)) {
            const nuevoPrecio = parseFloat(prices[ref]);
            if (!isNaN(nuevoPrecio)) {
                dixSetCachedTarifa(codCliente, ref, nuevoPrecio);
                if (Math.abs((item.pvp || 0) - nuevoPrecio) > 0.0001) {
                    updated = true;
                    return Object.assign({}, item, {pvp: nuevoPrecio});
                }
            }
        }
        return item;
    });

    if (updated) {
        localStorage.setItem('carrito', JSON.stringify(nuevoCarrito));
        actualizarCarrito();
        if (typeof cargarProductosCesta === 'function') {
            try {
                cargarProductosCesta();
            } catch (err) {
                console.warn('No se pudo refrescar la cesta del modal:', err);
            }
        }
        if (typeof cargarProductosCobrados === 'function') {
            try {
                cargarProductosCobrados();
            } catch (err) {
                console.warn('No se pudo refrescar la lista de cobrados:', err);
            }
        }
        if (typeof window.dixSyncCobroTotals === 'function') {
            try {
                window.dixSyncCobroTotals();
            } catch (err) {
                console.warn('No se pudo sincronizar el importe de cobro:', err);
            }
        }
    }
}

window.dixOnClientChanged = function (codCliente) {
    if (!window.dixTarifasAvanzadas) {
        return;
    }
    const code = codCliente && codCliente !== '' ? codCliente : dixGetSelectedClientCode();
    dixActualizarCarritoConTarifa(code);
};

async function annadir(referencia, descripcion, pvp, codimpuesto, stock, ventasinstock) {
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    let productoExistente = carrito.find(item => item.referencia === referencia);

    // Normalizamos ventasinstock a booleano
    let permitirSinStock = ventasinstock === 1 || ventasinstock === true;

    // Si no permite ventas sin stock y stock <= 0 => aviso
    if (!permitirSinStock && stock <= 0) {
        const almacen = window.dixTpvTerminalWarehouse || '';
        const ubicacion = almacen ? ` en el almacén ${almacen}` : '';
        mostrarAviso('Sin stock', `El producto "${descripcion}" no tiene stock disponible${ubicacion}.`);
        return;
    }

    if (productoExistente) {
        const cantidadActual = parseFloat(productoExistente.cantidad) || 0;
        productoExistente.cantidad = cantidadActual + 1;
    } else {
        const precioBase = parseFloat(pvp) || 0;
        const precioCalculado = await dixGetTariffPriceForReference(referencia, precioBase);
        let nuevoProducto = {
            referencia: referencia,
            descripcion: descripcion,
            pvp: precioCalculado,
            codimpuesto: parseFloat(codimpuesto) || 0,
            cantidad: 1
        };
        carrito.push(nuevoProducto);
    }

    localStorage.setItem('carrito', JSON.stringify(carrito));
    actualizarCarrito();
}



/**
 * Elimina todos los productos del carrito en localStorage.
 */
function eliminarCesta() {
    localStorage.removeItem('carrito');
    actualizarCarrito();
}

/**
 * Elimina un producto específico del carrito en localStorage.
 * @param {number} index - Índice del producto a eliminar.
 */
function eliminarProducto(index) {
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    carrito.splice(index, 1);
    localStorage.setItem('carrito', JSON.stringify(carrito));
    actualizarCarrito();
    if (typeof window.dixSyncCarritoTrasEliminacion === 'function') {
        window.dixSyncCarritoTrasEliminacion();
    }
}

/**
 * Actualiza la visualización del carrito en la interfaz de usuario y el total de la compra.
 */
function actualizarCarrito() {
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    const itemList = document.getElementById('item-list');
    if (!itemList)
        return;

    itemList.innerHTML = '';

    carrito.forEach((item, index) => {
        const impuesto = parseFloat(item.codimpuesto) || 0;
        const pvpConIVA = item.pvp * (1 + impuesto / 100);
        const totalConIVA = item.cantidad * pvpConIVA;

        const row = document.createElement('tr');
        row.setAttribute('data-ref', item.referencia);
        row.innerHTML = `
            <td class="desc-cart">${item.descripcion}</td>

            <td>
                <input class="cant-input" type="number" min="1" step="1"
                       value="${item.cantidad}"
                       data-kind="qty" data-ref="${item.referencia}"
                       onclick="seleccionarInput(this);"
                       oninput="actualizarCantidadPorRef('${item.referencia}', this.value, false)"
                       onblur="actualizarCantidadPorRef('${item.referencia}', this.value, true)"
                />
            </td>

            <td>
<input
  class="precio-input"
  type="text" inputmode="decimal" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
  value="${pvpConIVA.toFixed(2)}"
  data-kind="pvp" data-ref="${item.referencia}" data-impuesto="${impuesto}"
  onclick="seleccionarInput(this);"
  oninput="actualizarPrecioPorRef('${item.referencia}', this.value, ${impuesto}, false)"
  onblur="/* normaliza a 2 decimales y repinta */ (function(el){ 
              const v = (el.value||'').replace(',', '.'); 
              if(v===''||v==='.') return; 
              el.value = (parseFloat(v)||0).toFixed(2);
              actualizarPrecioPorRef('${item.referencia}', el.value, ${impuesto}, true);
           })(this)"
/>

            </td>

            <td class="celda-total">${totalConIVA.toFixed(2)} €</td>
            <td data-bs-target="#modalmodifprod" data-bs-toggle="modal" href="#modalmodifprod"
                onclick="modificarProducto(${item.pvp}, '${item.referencia}', ${item.cantidad}, '${item.descripcion}', ${impuesto})">
                <i class="fas fa-pencil"></i>
            </td>
            <td onclick="eliminarProducto(${index})"><i class="fas fa-trash"></i></td>
        `;
        itemList.appendChild(row);
    });

    const total = carrito.reduce((acc, it) => {
        const imp = parseFloat(it.codimpuesto) || 0;
        const pvpIVA = it.pvp * (1 + imp / 100);
        return acc + pvpIVA * it.cantidad;
    }, 0);

    localStorage.setItem('precioacobrar', total.toFixed(2));
    const totalNode = document.getElementById('total-amount');
    if (totalNode)
        totalNode.textContent = total.toFixed(2);
}

function pintarCantidadPorRef(ref, cantidad) {
    const fila = document.querySelector(`tr[data-ref="${CSS.escape(ref)}"]`);
    if (!fila)
        return;
    const qty = fila.querySelector('input[data-kind="qty"]');
    if (qty)
        qty.value = cantidad;
}


function vaciarCesta() {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];

    const salonSeleccionado = sessionStorage.getItem('salonSeleccionado') || '1';
    const mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada') || 'BRA-000';
    const esHosteleria = window.dixModoHosteleria !== false && window.dixModoHosteleria !== 'false';
    const idComandaActual = sessionStorage.getItem('idcomanda') || localStorage.getItem('idcomanda') || 0;

    const data = {
        action: 'vaciarCesta',
        cesta: carrito,
        salon: salonSeleccionado,
        mesa: mesaSeleccionada
    };

    if (!esHosteleria) {
        data.idcomanda = idComandaActual;
    }

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        success: function (response) {
            try {
                const jsonResponse = JSON.parse(response);

                if (jsonResponse.success) {
                    // Eliminar carrito y datos de la mesa
                    localStorage.removeItem('carrito');
                    localStorage.removeItem('camarero');
                    sessionStorage.removeItem('salonSeleccionado');
                    sessionStorage.removeItem('mesaSeleccionada');
                    sessionStorage.removeItem('idcomanda');
                    localStorage.removeItem('idcomanda');
                    if (typeof window.dixResetAparcadoOrigenPendiente === 'function') {
                        window.dixResetAparcadoOrigenPendiente();
                    }

                    // Actualizar la vista
                    actualizarCarrito();
                    if (esHosteleria) {
                        liberarMesa(mesaSeleccionada); // ← Marcar la mesa como disponible
                        comprobarAparcados(); // ← Verificar estado de mesas en servidor
                        activarServicioRapido();
                    }
                    location.reload(); // Recargar solo si es necesario
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

function moverMesa() {
    const carritoActual = JSON.parse(localStorage.getItem('carrito')) || [];

    // Guardar carrito actual temporalmente
    localStorage.setItem('carritoTemporal', JSON.stringify(carritoActual));

    vaciarCesta1(false);

    $("#modalmesas").modal("show");
    seleccionarSalon(1);
}

function vaciarCesta1(borrarCarrito = true) {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];

    const salonSeleccionado = sessionStorage.getItem('salonSeleccionado') || '1';
    const mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada') || 'BRA-000';
    const esHosteleria = window.dixModoHosteleria !== false && window.dixModoHosteleria !== 'false';
    const idComandaActual = sessionStorage.getItem('idcomanda') || localStorage.getItem('idcomanda') || 0;

    const data = {
        action: 'vaciarCesta',
        cesta: carrito,
        salon: salonSeleccionado,
        mesa: mesaSeleccionada
    };

    if (!esHosteleria) {
        data.idcomanda = idComandaActual;
    }

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        success: function (response) {
            try {
                const jsonResponse = JSON.parse(response);

                if (jsonResponse.success) {
                    if (borrarCarrito) {
                        localStorage.removeItem('carrito');
                    }
                    sessionStorage.removeItem('salonSeleccionado');
                    sessionStorage.removeItem('mesaSeleccionada');
                    sessionStorage.removeItem('idcomanda');
                    localStorage.removeItem('idcomanda');
                    if (typeof window.dixResetAparcadoOrigenPendiente === 'function') {
                        window.dixResetAparcadoOrigenPendiente();
                    }

                    actualizarCarrito();
                    if (esHosteleria) {
                        liberarMesa(mesaSeleccionada);
                        comprobarAparcados();
                    }
                } else {
                    alert('Error: ' + (jsonResponse.message || 'Error desconocido.'));
                }
            } catch (e) {
                console.error('Error en la respuesta del servidor:', e, response);
                alert('Error en el servidor. Contacta con soporte.');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('Error AJAX:', textStatus, errorThrown);
            alert('No se pudo comunicar con el servidor.');
        }
    });
}

function vaciarCesta1(borrarCarrito = true) {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];

    const salonSeleccionado = sessionStorage.getItem('salonSeleccionado') || '1';
    const mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada') || 'BRA-000';
    const esHosteleria = window.dixModoHosteleria !== false && window.dixModoHosteleria !== 'false';
    const idComandaActual = sessionStorage.getItem('idcomanda') || localStorage.getItem('idcomanda') || 0;

    const data = {
        action: 'vaciarCesta',
        cesta: carrito,
        salon: salonSeleccionado,
        mesa: mesaSeleccionada
    };

    if (!esHosteleria) {
        data.idcomanda = idComandaActual;
    }

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        success: function (response) {
            try {
                const jsonResponse = JSON.parse(response);

                if (jsonResponse.success) {
                    if (borrarCarrito) {
                        localStorage.removeItem('carrito');
                    }
                    sessionStorage.removeItem('salonSeleccionado');
                    sessionStorage.removeItem('mesaSeleccionada');
                    sessionStorage.removeItem('idcomanda');
                    localStorage.removeItem('idcomanda');

                    actualizarCarrito();
                    if (esHosteleria) {
                        liberarMesa(mesaSeleccionada);
                        comprobarAparcados();
                    }
                } else {
                    alert('Error: ' + (jsonResponse.message || 'Error desconocido.'));
                }
            } catch (e) {
                console.error('Error en la respuesta del servidor:', e, response);
                alert('Error en el servidor. Contacta con soporte.');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('Error AJAX:', textStatus, errorThrown);
            alert('No se pudo comunicar con el servidor.');
        }
    });
}

/*function addProducto() {
 let nombre = document.getElementById('newName').value.trim();
 let precio = parseFloat(document.getElementById('newPvp').value);
 
 // Validaciones
 if (nombre === "") {
 alert("El nombre del producto no puede estar vacío.");
 return;
 }
 if (isNaN(precio) || precio <= 0) {
 alert("El precio debe ser un número válido y mayor que 0.");
 return;
 }
 
 // Obtener el carrito actual
 let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
 
 // Crear una referencia única (puede ser un timestamp)
 let referencia = "cust" + Date.now();
 
 // Crear el producto personalizado
let nuevoProducto = {
referencia: referencia, // Generar un identificador único
descripcion: nombre,
pvp: precio,
cantidad: 1,
codimpuesto: 0
};
 
 // Agregar al carrito
 carrito.push(nuevoProducto);
 localStorage.setItem('carrito', JSON.stringify(carrito));
 
 // Actualizar la interfaz del carrito
 actualizarCarrito();
 
 // Limpiar los campos después de agregar el producto
 document.getElementById('newName').value = "Varios";
 document.getElementById('newPvp').value = "1";
 $("#newLine").modal("hide");
 }*/

function eliminarCesta() {
    // Eliminar el carrito del localStorage
    localStorage.removeItem('carrito');
    // Actualizar la tabla del carrito
    actualizarCarrito();
}

function actualizarCarro(productos = null, id) {
    let carrito = productos || JSON.parse(localStorage.getItem('carrito')) || [];
    sessionStorage.setItem('idcomanda', id);
    const itemList = document.getElementById('item-list');
    if (!itemList)
        return;

    itemList.innerHTML = '';

    carrito.forEach((item, index) => {
        const impuesto = parseFloat(item.codimpuesto) || 0;
        const pvpConIVA = item.pvp * (1 + impuesto / 100);
        const totalConIVA = item.cantidad * pvpConIVA;

        const row = document.createElement('tr');
        row.setAttribute('data-ref', item.referencia);
        row.innerHTML = `
            <td class="desc-cart">${item.descripcion}</td>

            <td>
                <input class="cant-input" type="number" min="1" step="1"
                       value="${item.cantidad}"
                       data-kind="qty" data-ref="${item.referencia}"
                       onclick="seleccionarInput(this);"
                       oninput="actualizarCantidadPorRef('${item.referencia}', this.value, false)"
                       onblur="actualizarCantidadPorRef('${item.referencia}', this.value, true)"
                />
            </td>

            <td>
<input
  class="precio-input"
  type="text" inputmode="decimal" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
  value="${pvpConIVA.toFixed(2)}"
  data-kind="pvp" data-ref="${item.referencia}" data-impuesto="${impuesto}"
  onclick="seleccionarInput(this);"
  oninput="actualizarPrecioPorRef('${item.referencia}', this.value, ${impuesto}, false)"
  onblur="/* normaliza a 2 decimales y repinta */ (function(el){ 
              const v = (el.value||'').replace(',', '.'); 
              if(v===''||v==='.') return; 
              el.value = (parseFloat(v)||0).toFixed(2);
              actualizarPrecioPorRef('${item.referencia}', el.value, ${impuesto}, true);
           })(this)"
/>

            </td>
            <td class="celda-total">${totalConIVA.toFixed(2)} €</td>
            <td data-bs-target="#modalmodifprod" data-bs-toggle="modal" href="#modalmodifprod"
                onclick="modificarProducto(${item.pvp}, '${item.referencia}', ${item.cantidad}, '${item.descripcion}', ${impuesto})">
                <i class="fas fa-pencil"></i>
            </td>
            <td onclick="eliminarProducto(${index})"><i class="fas fa-trash"></i></td>
        `;
        itemList.appendChild(row);
    });

    const total = carrito.reduce((acc, it) => {
        const imp = parseFloat(it.codimpuesto) || 0;
        const pvpIVA = it.pvp * (1 + imp / 100);
        return acc + pvpIVA * it.cantidad;
    }, 0);

    localStorage.setItem('precioacobrar', total.toFixed(2));
    const totalNode = document.getElementById('total-amount');
    if (totalNode)
        totalNode.textContent = total.toFixed(2);
}


function findIndexByRef(carrito, ref) {
    return carrito.findIndex(it => String(it.referencia) === String(ref));
}

function actualizarCantidadPorRef(referencia, cantidad, repaint = true) {
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    const i = findIndexByRef(carrito, referencia);
    if (i === -1)
        return;

    carrito[i].cantidad = Math.max(1, parseInt(cantidad, 10) || 1);
    localStorage.setItem('carrito', JSON.stringify(carrito));

    // 👇 asegura reflejar el valor en el input cuando no repintas toda la tabla
    if (!repaint)
        pintarCantidadPorRef(referencia, carrito[i].cantidad);

    repaint ? actualizarCarrito() : recalcularFilaYTotal(referencia);
}


function actualizarPrecioPorRef(referencia, nuevoPvpConIVA, codimpuesto, repaint = true) {
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    const i = findIndexByRef(carrito, referencia);
    if (i === -1)
        return;

    const item = carrito[i];
    const impuesto = parseFloat(codimpuesto) || 0;
    const bruto = parseFloat(nuevoPvpConIVA);
    if (isNaN(bruto))
        return;

    const sinIVA = bruto / (1 + impuesto / 100);
    item.pvp = parseFloat(sinIVA.toFixed(6));
    item.codimpuesto = impuesto;
    item.pvpManual = true;

    localStorage.setItem('carrito', JSON.stringify(carrito));

    repaint ? actualizarCarrito() : recalcularFilaYTotal(referencia);
}

function recalcularFilaYTotal(referencia) {
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    const item = carrito.find(it => String(it.referencia) === String(referencia));
    if (!item)
        return;

    const impuesto = parseFloat(item.codimpuesto) || 0;
    const pvpConIVA = item.pvp * (1 + impuesto / 100);
    const totalConIVA = item.cantidad * pvpConIVA;

    // Actualiza solo esa fila
    const fila = document.querySelector(`tr[data-ref="${CSS.escape(referencia)}"]`);
    if (fila) {
        const totalCell = fila.querySelector('.celda-total');
        if (totalCell)
            totalCell.textContent = `${totalConIVA.toFixed(2)} €`;
    }

    // Recalcula total general
    const total = carrito.reduce((acc, it) => {
        const imp = parseFloat(it.codimpuesto) || 0;
        const pvpIVA = it.pvp * (1 + imp / 100);
        return acc + pvpIVA * it.cantidad;
    }, 0);

    localStorage.setItem('precioacobrar', total.toFixed(2));
    const totalNode = document.getElementById('total-amount');
    if (totalNode)
        totalNode.textContent = total.toFixed(2);
}

function dixSyncCarritoTrasEliminacion() {
    const esHosteleria = window.dixModoHosteleria !== false && window.dixModoHosteleria !== 'false';
    if (!esHosteleria) {
        return;
    }

    const mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada') || '';
    if (!mesaSeleccionada || mesaSeleccionada === 'BRA-000') {
        return;
    }

    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    if (carrito.length > 0) {
        return;
    }

    const salonSeleccionado = sessionStorage.getItem('salonSeleccionado') || '1';

    $.ajax({
        method: 'POST',
        url: window.location.href,
        data: {
            action: 'vaciarCesta',
            cesta: [],
            salon: salonSeleccionado,
            mesa: mesaSeleccionada
        },
        success: function (response) {
            let parsed = response;
            if (typeof response === 'string') {
                try {
                    parsed = JSON.parse(response);
                } catch (err) {
                    console.warn('No se pudo interpretar la respuesta al vaciar la mesa.', err);
                    parsed = {};
                }
            }

            if (parsed && parsed.success) {
                sessionStorage.removeItem('idcomanda');
                localStorage.removeItem('idcomanda');
                if (typeof liberarMesa === 'function') {
                    liberarMesa(mesaSeleccionada);
                }
                if (typeof comprobarAparcados === 'function') {
                    comprobarAparcados();
                }
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.warn('No se pudo liberar la mesa tras eliminar todas las líneas.', textStatus, errorThrown);
        }
    });
}

window.dixSyncCarritoTrasEliminacion = dixSyncCarritoTrasEliminacion;


function pasarTodoACarritoFin() {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    let carritofin = JSON.parse(localStorage.getItem('carritofin')) || [];

    carrito.forEach(producto => {
        const existente = carritofin.find(p => p.referencia === producto.referencia);
        if (existente) {
            existente.cantidad += producto.cantidad;
        } else {
            carritofin.push({...producto});
        }
    });

    // Vaciar carrito original
    localStorage.setItem('carrito', JSON.stringify([]));
    localStorage.setItem('carritofin', JSON.stringify(carritofin));

    cargarProductosCesta();
    cargarProductosCobrados();
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.dixTarifasAvanzadas) {
        return;
    }
    const codigo = dixGetSelectedClientCode();
    if (codigo) {
        dixActualizarCarritoConTarifa(codigo);
    }
    if (typeof window.dixSyncCobroTotals === 'function') {
        window.dixSyncCobroTotals();
    }
});
