function seleccionarSalon(idSalon, nombreSalon) {
    sessionStorage.setItem('salonSeleccionado', nombreSalon);

    const mesas = document.querySelectorAll('.mesa-card');

    // Ocultar todas las mesas
    mesas.forEach(mesa => {
        mesa.classList.add('hidden');
    });

    // Mostrar solo las mesas del salón seleccionado
    mesas.forEach(mesa => {
        const idsalonmesa = mesa.getAttribute('data-iddelsalon');
        if (idsalonmesa === idSalon) {
            mesa.classList.remove('hidden'); // Asegurar que las mesas correctas se muestren
        }
    });

    // Asegurar que el contenedor de mesas se muestra correctamente
    const mesasContainer = document.getElementById('mesasContainer');
    if (mesasContainer) {
        mesasContainer.classList.remove('d-none');
    }

    // Marcar el salón seleccionado visualmente
    $(".salon-card").removeClass("salon-seleccionado");
    $('.salon-card[data-id="' + idSalon + '"]').addClass("salon-seleccionado");

    // Llamar a mostrarMesasSalon() con el ID correcto
    mostrarMesasSalon(idSalon);

    // Asegurar que la mesa seleccionada se visualiza correctamente en la UI
    mostrarMesaSeleccionada();

    const cachedAparcados = window.dixTpvAparcadosCache || (typeof aparcados !== 'undefined' ? aparcados : []);
    aplicarEstadoMesas(cachedAparcados);
    comprobarAparcados();
}


/***  Muestra los div que representan las mesas de un determinado salon   */
/* @param  int salonid */
function mostrarMesasSalon(idSalon) {
    $(".mesa-card").addClass("hidden"); // Ocultar todas las mesas
    $('.mesa-card[data-iddelsalon="' + idSalon + '"]').removeClass("hidden"); // Mostrar solo las mesas del salón seleccionado
}

function seleccionarMesa1(mesaNombre) {
    sessionStorage.setItem('mesaSeleccionada', mesaNombre);

    // 🔥 Quitar el foco de cualquier elemento activo antes de cerrar el modal
    document.activeElement.blur();

    // 🔥 Cerrar el modal con un pequeño retraso
    setTimeout(() => {
        $("#modalmesas").modal("hide");
    }, 100);

    // 🔥 Asegurar que el nombre de la mesa se actualiza inmediatamente después de cerrar el modal
    setTimeout(() => {
        mostrarMesaSeleccionada();
    }, 200);

    aparcarCuenta(); // Llamar a aparcar cuenta con la nueva mesa
}


function seleccionarMesa(nombreMesa, idComanda) {
    const aparcado = aparcados.find(aparcado => aparcado.nombremesa === nombreMesa);
    sessionStorage.setItem('mesaSeleccionada', nombreMesa);
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    // Llamar a mostrarMesaSeleccionada después de cerrar el modal
    setTimeout(() => {
        mostrarMesaSeleccionada();
    }, 100);

    if (aparcado) {
        cerrarModal();
        // AJAX para recuperar la cuenta aparcada
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
                const productos = response.productos;
                // Guardar los productos en el localStorage
                localStorage.setItem('carrito', JSON.stringify(productos));
                // Actualizar la vista del carrito
                actualizarCarro(productos, aparcado.idcomanda);
            },
            error: function (msg) {
                console.log("Error en postRequest: " + msg.status + " " + msg.responseText);
            }
        });
    } else {
// Si la mesa está disponible, permitir el aparcado
        sessionStorage.setItem('mesaSeleccionada', nombreMesa);
        $("#modalmesas").modal("hide"); // Cerrar modal tras la selección
        if (carrito.length > 0) {
            aparcarCuenta();
        }
    }

}
const dixBaseEliminarCesta = window.eliminarCesta;
const dixBaseActualizarCarro = window.actualizarCarro;

function eliminarCesta() {
    if (typeof dixBaseEliminarCesta === 'function') {
        dixBaseEliminarCesta();
        return;
    }

    localStorage.removeItem('carrito');
    actualizarCarrito();
}

function actualizarCarro(productos = null, id) {
    if (typeof dixBaseActualizarCarro === 'function') {
        dixBaseActualizarCarro(productos, id);
        return;
    }

    let carrito = productos || JSON.parse(localStorage.getItem('carrito')) || [];
    sessionStorage.setItem('idcomanda', id);
    const itemList = document.getElementById('item-list');
    if (!itemList) {
        return;
    }

    itemList.innerHTML = '';

    carrito.forEach((item, index) => {
        const impuesto = parseFloat(item.codimpuesto) || 0;
        const pvpConIVA = (parseFloat(item.pvp) || 0) * (1 + impuesto / 100);
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
                       onblur="actualizarCantidadPorRef('${item.referencia}', this.value, true)"/>
            </td>
            <td>
                <input class="precio-input"
                       type="text" inputmode="decimal" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       value="${pvpConIVA.toFixed(2)}"
                       data-kind="pvp" data-ref="${item.referencia}" data-impuesto="${impuesto}"
                       onclick="seleccionarInput(this);"
                       oninput="actualizarPrecioPorRef('${item.referencia}', this.value, ${impuesto}, false)"
                       onblur="(function(el){ 
                            const v = (el.value||'').replace(',', '.'); 
                            if(v===''||v==='.') return; 
                            el.value = (parseFloat(v)||0).toFixed(2);
                            actualizarPrecioPorRef('${item.referencia}', el.value, ${impuesto}, true);
                        })(this)"/>
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
        const pvpIVA = (parseFloat(it.pvp) || 0) * (1 + imp / 100);
        return acc + pvpIVA * it.cantidad;
    }, 0);

    localStorage.setItem('precioacobrar', total.toFixed(2));
    const totalNode = document.getElementById('total-amount');
    if (totalNode) {
        totalNode.textContent = total.toFixed(2);
    }
}

function cerrarModal() {
    $('#modalmesas').modal('hide');
}

function mostrarMesaSeleccionada() {
    setTimeout(() => {
        const salonSeleccionado = sessionStorage.getItem('salonSeleccionado') || '';
        const mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada') || '';
        const mesaElemento = document.getElementById('mesaSeleccionada');

        if (mesaElemento) {
            mesaElemento.textContent = salonSeleccionado && mesaSeleccionada
                    ? salonSeleccionado + ' / ' + mesaSeleccionada
                    : 'Servicio rápido';
        }
    }, 50); // Espera 50ms para asegurarte de que sessionStorage se actualizó
}

function aplicarEstadoMesas(aparcadosLista) {
    const mesas = document.querySelectorAll('.mesa-card');
    if (!mesas.length) {
        return;
    }

    const ocupadas = new Set();
    if (Array.isArray(aparcadosLista)) {
        aparcadosLista.forEach(item => {
            if (item && item.nombremesa) {
                ocupadas.add(String(item.nombremesa).trim());
            }
        });
    }

    mesas.forEach(mesa => {
        const nombreMesa = (mesa.dataset.mesaNombre || mesa.dataset.nombre || mesa.textContent || '').trim();

        if (!nombreMesa) {
            return;
        }

        if (nombreMesa === 'BRA-000') {
            mesa.remove();
            return;
        }

        if (ocupadas.has(nombreMesa)) {
            mesa.classList.add('mesa-ocupada');
            mesa.classList.remove('mesa-disponible');
        } else {
            mesa.classList.add('mesa-disponible');
            mesa.classList.remove('mesa-ocupada');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    mostrarMesaSeleccionada();
    const initialAparcados = Array.isArray(window.dixTpvAparcadosCache)
        ? window.dixTpvAparcadosCache
        : (typeof aparcados !== 'undefined' && Array.isArray(aparcados) ? aparcados : []);

    if (Array.isArray(initialAparcados)) {
        window.dixTpvAparcadosCache = initialAparcados;
        aplicarEstadoMesas(initialAparcados);
    }
});

function comprobarAparcados() {
    const data = {action: 'get-aparcados'};

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        dataType: "json",
        success: function (response) {
            const lista = Array.isArray(response) ? response : [];
            window.dixTpvAparcadosCache = lista;
            aplicarEstadoMesas(lista);
        },
        error: function (msg) {
            console.log("Error en postRequest: " + msg.status + " " + msg.responseText);
        }
    });
}
