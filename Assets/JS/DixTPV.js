// Modulo principal que coordina eventos del TPV y sincroniza el estado en localStorage.
var inputSeleccionado = null;
/**
 * Carga los productos de una familia específica mediante una solicitud AJAX y actualiza el contenido de la vista.
 * @param {string} codfamilia - Código de la familia de productos a cargar.
 */
function loadFamilyProducts(codfamilia) {
    data = {
        action: 'getFamilyProducts',
        codfamilia: codfamilia
    };
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        datatype: "json",
        success: function (results) {
            data = JSON.parse(results);
            $("#productos").html(data.htmlFamily);
        },
        error: function (msg) {
            console.log("Error en la solicitud AJAX: " + msg.status + " " + msg.responseText);
        }
    });
}

/**
 * Procesa la acción de cobrar eliminando el carrito del localStorage.
 */
function cobrar() {
    idcomanda = localStorage.getItem('idcomanda');
    eliminarCesta();
}

/**
 * Actualiza la cantidad indicada en la fila correspondiente del carrito.
 * Recalcula y persiste el listado antes de redibujar la tabla.
 */
function actualizarCantidad(index, cantidad) {
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    carrito[index].cantidad = parseInt(cantidad);
    localStorage.setItem('carrito', JSON.stringify(carrito));
    actualizarCarrito();
}
/**
 * Inserta un producto en el carrito respetando las restricciones de stock.
 * Incrementa la cantidad si la referencia ya esta presente.
 */
function annadir(referencia, descripcion, pvp, codimpuesto, stock, ventasinstock) {
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
        productoExistente.cantidad += 1;
    } else {
        let nuevoProducto = {
            referencia: referencia,
            descripcion: descripcion,
            pvp: parseFloat(pvp) || 0,
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
}

/**
 * Redibuja la tabla del carrito y recalcula importes cada vez que cambia el contenido.
 */
function actualizarCarrito() {
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    const itemList = document.getElementById('item-list');
    if (!itemList) {
        return;
    }
    itemList.innerHTML = '';

    carrito.forEach((item, index) => {
        const impuesto = parseFloat(item.codimpuesto) || 0;
        const pvpConIVA = item.pvp * (1 + impuesto / 100);
        const totalConIVA = item.cantidad * pvpConIVA;

        const row = document.createElement('tr');
        row.innerHTML = `
        <td>${item.descripcion}</td>
        <td>
            <input id="${item.referencia}" type="number" min="1" step="any" value="${item.cantidad}" 
                   data-index="${index}" 
                   onchange="actualizarCantidad(${index}, this.value)" 
                   style="width: 60px;" 
                   onclick="seleccionarInput(this);"/>
        </td>
        <td class="text-end">${totalConIVA.toFixed(2)} €</td>
        <td onclick="eliminarProducto(${index})"><i class="fas fa-trash"></i></td>
        <td data-bs-target="#modalmodifprod" data-bs-toggle="modal" href="#modalmodifprod" 
            onclick="modificarProducto(${item.pvp}, '${item.referencia}', ${item.cantidad}, '${item.descripcion}' , ${item.codimpuesto})">
            <i class="fas fa-pencil"></i>
        </td>
    `;
        itemList.appendChild(row);
    });

    let noItem = document.getElementById('noItem');
    noItem.classList.toggle('d-none', carrito.length > 0);

    let total = carrito.reduce((acc, item) => {
        const impuesto = parseFloat(item.codimpuesto) || 0;
        const pvpConIVA = item.pvp * (1 + impuesto / 100);
        return acc + (pvpConIVA * item.cantidad);
    }, 0);
    localStorage.setItem('precioacobrar', total.toFixed(2));
    document.getElementById('total-amount').textContent = total.toFixed(2);
}

/**
 * Modifica los detalles de un producto en el localStorage.
 * @param {number} pvp - Precio del producto.
 * @param {string} referencia - Referencia del producto.
 * @param {number} cantidad - Cantidad del producto.
 * @param {string} descripcion - Descripción del producto.
 */
function modificarProducto(pvp, referencia, cantidad, descripcion, codimpuesto) {
    localStorage.setItem('pvp', pvp);
    localStorage.setItem('referencia', referencia);
    localStorage.setItem('cantidad', cantidad);
    localStorage.setItem('descripcion', descripcion);
    localStorage.setItem('codimpuesto', codimpuesto);
}

/**
 * Guarda el estado actual del carrito en el servidor y limpia el carrito local.
 */
function aparcarCuenta() {
    if (typeof window.dixSeleccionarClienteParaAparcar === 'function'
        && typeof window.dixProcesarAparcadoConCliente === 'function') {
        window.dixSeleccionarClienteParaAparcar().then(function (codCliente) {
            if (!codCliente) {
                return;
            }
            window.dixProcesarAparcadoConCliente(codCliente);
        });
        return;
    }

    // Fallback legacy behaviour en caso de que no se haya cargado el módulo avanzado.
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    const salonSeleccionado = sessionStorage.getItem('salonSeleccionado') || '1';
    let mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada') || 'BRA-000';
    const clienteSelect = document.getElementById('cliente');
    const codCliente = clienteSelect ? clienteSelect.value : '';

    if (mesaSeleccionada === 'BRA-000') {
        $("#modalmesas").modal("show");
        return;
    }

    const data = {
        action: 'aparcarCuenta',
        cesta: carrito,
        salon: salonSeleccionado,
        mesa: mesaSeleccionada,
        codcliente: codCliente
    };

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        success: function (response) {
            try {
                const jsonResponse = JSON.parse(response);

                if (jsonResponse.success) {
                    localStorage.removeItem('carrito');
                    localStorage.removeItem('camarero');
                    sessionStorage.removeItem('salonSeleccionado');
                    sessionStorage.removeItem('mesaSeleccionada');
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

function obtenerMesasDisponibles() {
    return new Promise((resolve) => {
        let mesasDisponibles = [];

        // Seleccionamos todas las mesas con la clase 'mesa-disponible'
        document.querySelectorAll(".mesa-disponible").forEach(mesa => {
            mesasDisponibles.push(mesa.innerText.trim());
        });

        resolve(mesasDisponibles);
    });
}

window.addEventListener('load', function () {
    const wasFullScreen = localStorage.getItem('isFullScreen') === 'true';
    if (wasFullScreen && !document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            console.error(`Error al intentar restaurar pantalla completa: ${err.message}`);
        });
    }
});


function agregarNumero(numero) {

    if (inputSeleccionado) {
        // Añadir el número al final del contenido actual del input
        const currentValue = inputSeleccionado.value || '';

        // Verificar si el valor actual es un número o vacío
        if (currentValue === "0") {
            inputSeleccionado.value = numero; // Si está vacío o es cero, reemplazar

        } else if (numero === ".") {
            if (!currentValue.includes(".")) {
                inputSeleccionado.value = currentValue + ".";
            }
        } else {
            inputSeleccionado.value += numero; // Añadir el número al final
        }

        const index = inputSeleccionado.dataset.index;

        // Actualiza la cantidad en localStorage y en la vista
        actualizarCantidad(index, parseFloat(inputSeleccionado.value, 10));
    }
}
/**
 * Borra el último carácter del valor del input seleccionado.
 */
function borrarNumero() {
    if (inputSeleccionado) {
        const currentValue = inputSeleccionado.value;

        // Si el valor no está vacío, borra el último carácter
        if (currentValue.length > 0) {
            inputSeleccionado.value = currentValue.slice(0, -1);
        }

        const index = inputSeleccionado.dataset.index;

        // Actualiza la cantidad en localStorage y en la vista
        if (index !== undefined) {
            actualizarCantidad(index, parseInt(inputSeleccionado.value || 0, 10));
        }
    }
}



/**
 * Selecciona el input actual para su manipulación.
 * @param {HTMLElement} input - El input seleccionado.
 */
function seleccionarInput(input) {
    inputSeleccionado = input;
    inputSeleccionado.focus();
    inputSeleccionado.select();
    inputSeleccionado.value = "";
}


function vaciarCesta() {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];

    const salonSeleccionado = sessionStorage.getItem('salonSeleccionado') || '1';
    const mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada') || 'BRA-000';

    const data = {
        action: 'vaciarCesta',
        cesta: carrito,
        salon: salonSeleccionado,
        mesa: mesaSeleccionada
    };

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

                    // Actualizar la vista
                    actualizarCarrito();
                    liberarMesa(mesaSeleccionada); // ← Marcar la mesa como disponible
                    comprobarAparcados(); // ← Verificar estado de mesas en servidor
                    activarServicioRapido();
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
/**
 * Restaura el estado visual de una mesa en la interfaz tras cerrarla.
 */
function liberarMesa(nombreMesa) {
    const mesaElemento = document.querySelector(`.mesa-card[data-nombre="${nombreMesa}"]`);
    if (mesaElemento) {
        mesaElemento.classList.remove('mesa-ocupada');
        mesaElemento.classList.add('mesa-disponible');
    }
}


/**
 * Construye y muestra notificaciones toast con estilos reutilizados en todo el TPV.
 */
function setToast(message, style = 'info', title = '', time = 10000) {
    let icon = '';
    let styleBorder = '';
    let styleHeader = '';
    let role = 'status';
    let live = 'polite';

    switch (style) {
        case 'completed':
            styleHeader = 'bg-success text-white';
            styleBorder = 'border border-success';
            icon = '<i class="fas fa-check-circle me-1"></i>';
            title = title !== '' ? title : 'Completado';
            break;

        case 'critical':
        case 'error':
        case 'danger':
            role = 'alert';
            live = 'assertive';
            styleHeader = 'bg-danger text-white';
            styleBorder = 'border border-danger';
            icon = '<i class="fas fa-times-circle me-1"></i>';
            title = title !== '' ? title : 'Error';
            break;

        case 'info':
            styleHeader = 'bg-info text-white';
            styleBorder = 'border border-info';
            icon = '<i class="fas fa-info-circle me-1"></i>';
            title = title !== '' ? title : 'Info';
            break;

        case 'spinner':
            styleHeader = 'bg-info text-white';
            styleBorder = 'border border-info';
            icon = '<i class="fa-solid fa-circle-notch fa-spin me-1"></i>';
            title = title !== '' ? title : 'Procesando';
            break;

        case 'notice':
        case 'success':
            styleHeader = 'bg-success text-white';
            styleBorder = 'border border-success';
            icon = '<i class="fas fa-check-circle me-1"></i>';
            title = title !== '' ? title : 'Éxito';
            break;

        case 'warning':
            styleHeader = 'bg-warning';
            styleBorder = 'border border-warning';
            icon = '<i class="fas fa-exclamation-circle me-1"></i>';
            title = title !== '' ? title : 'Atención';
            break;
    }

    const autohideAttr = time > 0
        ? `data-bs-autohide="true" data-bs-delay="${time}"`
        : 'data-bs-autohide="false"';
    const closeClasses = styleHeader.includes('text-white')
        ? 'btn-close btn-close-white ms-2'
        : 'btn-close ms-2';
    const headerClasses = ['toast-header', styleHeader, message === '' ? 'border-bottom-0' : '']
        .filter(Boolean)
        .join(' ');
    const toastClasses = ['toast', `toast-${style}`, styleBorder, 'mt-3']
        .filter(Boolean)
        .join(' ');
    const titleHtml = title ? `${icon}${title}` : icon;
    const bodyHtml = message !== '' ? `<div class="toast-body">${message}</div>` : '';

    const html = `
        <div class="${toastClasses}" role="${role}" aria-live="${live}" aria-atomic="true" ${autohideAttr}>
            <div class="${headerClasses.trim()}">
                <strong class="me-auto">${titleHtml}</strong>
                <button type="button" class="${closeClasses}" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
            ${bodyHtml}
        </div>
    `;

    const $container = $('#messages-toasts');
    $container.find('.toast.hide').remove();

    const $toast = $(html);
    $container.append($toast);

    const toastInstance = window.bootstrap ? window.bootstrap.Toast.getOrCreateInstance($toast[0]) : null;
    if (toastInstance) {
        toastInstance.show();
    } else {
        $toast.toast('show');
    }
}
