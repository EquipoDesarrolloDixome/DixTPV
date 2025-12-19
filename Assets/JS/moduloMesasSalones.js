const FLOOR_STORAGE_PREFIX = 'dixtpv_floor_plan';
const FLOOR_POSITION_CLAMP = { min: 5, max: 95 };

document.addEventListener('DOMContentLoaded', () => {
    actualizarCarrito();
    mostrarMesaSeleccionada();
    inicializarPlanoInteractivo();

    const salonGuardado = sessionStorage.getItem('salonSeleccionado');
    if (salonGuardado) {
        actualizarNombreSalonActivo(salonGuardado);
    }

    $('#modalmesas').on('shown.bs.modal', () => {
        const primerSalon = document.querySelector('#modalmesas .salon-seleccionado');
        if (primerSalon) {
            primerSalon.click();
        }
    });
});

function cerrarModal() {
    $('#modalmesas').modal('hide');
}

function obtenerMesasDisponibles() {
    return new Promise((resolve) => {
        const mesasDisponibles = [];
        document.querySelectorAll('.mesa-disponible').forEach((mesa) => {
            mesasDisponibles.push(mesa.innerText.trim());
        });
        resolve(mesasDisponibles);
    });
}

function mostrarMesaSeleccionada() {
    setTimeout(() => {
        const salonSeleccionado = sessionStorage.getItem('salonSeleccionado') || '';
        const mesaSeleccionada = sessionStorage.getItem('mesaSeleccionada') || '';
        const mesaElemento = document.getElementById('mesaSeleccionada');

        if (mesaElemento) {
            mesaElemento.textContent = salonSeleccionado && mesaSeleccionada
                ? `${salonSeleccionado} / ${mesaSeleccionada}`
                : 'Servicio r\u00e1pido';
        }

        if (salonSeleccionado) {
            actualizarNombreSalonActivo(salonSeleccionado);
        }
    }, 50);
}

function liberarMesa(nombreMesa) {
    const mesaElemento = document.querySelector(`.mesa-card[data-nombre="${nombreMesa}"]`);
    if (mesaElemento) {
        mesaElemento.classList.remove('mesa-ocupada');
        mesaElemento.classList.add('mesa-disponible');
    }
}

function activarServicioRapido() {
    const carrito = localStorage.getItem('carrito');

    if (carrito && carrito.trim() !== '' && carrito !== '[]') {
        aparcarCuenta();
        return;
    }

    sessionStorage.removeItem('mesaSeleccionada');
    sessionStorage.removeItem('salonSeleccionado');

    const mesaElemento = document.getElementById('mesaSeleccionada');
    if (mesaElemento) {
        mesaElemento.textContent = 'Servicio r\u00e1pido';
    }

    eliminarCesta();
}

function seleccionarSalon(idSalon, nombreSalon) {
    sessionStorage.setItem('salonSeleccionado', nombreSalon);

    const mesas = document.querySelectorAll('.mesa-card');
    const mesasContainer = document.getElementById('mesasContainer');

    mesas.forEach((mesa) => {
        mesa.classList.add('hidden');
    });

    mesas.forEach((mesa) => {
        if (mesa.getAttribute('data-iddelsalon') === idSalon) {
            mesa.classList.remove('hidden');
        }
    });

    if (mesasContainer) {
        mesasContainer.classList.remove('d-none');
        mesasContainer.dataset.activeSalon = idSalon;
        organizarMesasSalon(idSalon);
    }

    $('.salon-card').removeClass('salon-seleccionado');
    $(`.salon-card[data-id="${idSalon}"]`).addClass('salon-seleccionado');

    mostrarMesasSalon(idSalon);
    mostrarMesaSeleccionada();

    const cachedAparcados = window.dixTpvAparcadosCache || (typeof aparcados !== 'undefined' ? aparcados : []);
    if (typeof aplicarEstadoMesas === 'function') {
        aplicarEstadoMesas(cachedAparcados);
    }
    if (typeof comprobarAparcados === 'function') {
        comprobarAparcados();
    }
}

function mostrarMesasSalon(idSalon) {
    $('.mesa-card').addClass('hidden');
    $(`.mesa-card[data-iddelsalon="${idSalon}"]`).removeClass('hidden');
    organizarMesasSalon(idSalon);
}

function seleccionarMesa1(mesaNombre) {
    sessionStorage.setItem('mesaSeleccionada', mesaNombre);

    setTimeout(() => {
        document.activeElement.blur();
        $('#modalmesas').modal('hide');
    }, 100);

    setTimeout(() => {
        mostrarMesaSeleccionada();
    }, 200);

    aparcarCuenta();
}

function handleMesaClick(elemento, mesaNombre, idComanda) {
    if (elemento.dataset.dragging === 'true') {
        elemento.dataset.dragging = 'false';
        return;
    }

    if (typeof seleccionarMesa === 'function') {
        seleccionarMesa(mesaNombre, idComanda);
        return;
    }

    if (typeof seleccionarMesa1 === 'function') {
        seleccionarMesa1(mesaNombre);
    }
}

function actualizarNombreSalonActivo(nombreSalon) {
    const titulo = document.getElementById('salonActivoNombre');
    if (titulo) {
        titulo.textContent = nombreSalon || titulo.textContent;
    }
}

function resetearPlanoSalones() {
    const container = document.getElementById('mesasContainer');
    if (!container) {
        return;
    }

    const salonId = container.dataset.activeSalon;
    const mesas = Array.from(document.querySelectorAll(`.mesa-card[data-iddelsalon="${salonId}"]`));

    mesas.forEach((mesa, index) => {
        const posicion = calcularPosicionPorDefecto(index, mesas.length);
        posicionarMesa(mesa, posicion.x, posicion.y);
        delete mesa.dataset.posx;
        delete mesa.dataset.posy;
    });

    solicitarResetSalonRemoto(salonId);
}

function inicializarPlanoInteractivo() {
    const container = document.getElementById('mesasContainer');
    if (!container) {
        return;
    }

    const mesas = Array.from(document.querySelectorAll('.mesa-card'));
    const mesasPorSalon = {};

    mesas.forEach((mesa) => {
        const salonId = mesa.dataset.iddelsalon;
        if (!mesasPorSalon[salonId]) {
            mesasPorSalon[salonId] = [];
        }
        mesasPorSalon[salonId].push(mesa);
    });

    Object.keys(mesasPorSalon).forEach((salonId) => {
        organizarMesasSalon(salonId, mesasPorSalon[salonId]);
    });

    mesas.forEach((mesa) => {
        habilitarArrastreMesa(mesa, container);
    });
}

function organizarMesasSalon(idSalon, listaMesas) {
    const container = document.getElementById('mesasContainer');
    if (!container) {
        return;
    }

    const mesas = listaMesas || Array.from(document.querySelectorAll(`.mesa-card[data-iddelsalon="${idSalon}"]`));
    const total = mesas.length || 1;

    mesas.forEach((mesa, index) => {
        const datasetX = parseFloat(mesa.dataset.posx);
        const datasetY = parseFloat(mesa.dataset.posy);

        if (!Number.isNaN(datasetX) && !Number.isNaN(datasetY)) {
            posicionarMesa(mesa, datasetX, datasetY);
            return;
        }

        const storageKey = obtenerStorageKey(idSalon, mesa.dataset.mesaId);
        const datosGuardados = localStorage.getItem(storageKey);

        if (datosGuardados) {
            try {
                const { x, y } = JSON.parse(datosGuardados);
                posicionarMesa(mesa, x, y);
                persistirPosicionMesaRemota(mesa, x, y, storageKey);
                return;
            } catch (error) {
                console.warn('No se pudo restaurar la posicion de la mesa', error);
            }
        }

        const posicion = calcularPosicionPorDefecto(index, total);
        posicionarMesa(mesa, posicion.x, posicion.y);
    });
}

function calcularPosicionPorDefecto(index, total) {
    const columnas = Math.ceil(Math.sqrt(total));
    const filas = Math.ceil(total / columnas);
    const columna = index % columnas;
    const fila = Math.floor(index / columnas);

    const pasoX = 100 / (columnas + 1);
    const pasoY = 100 / (filas + 1);

    let x = (columna + 1) * pasoX;
    let y = (fila + 1) * pasoY;

    x = clamp(x, FLOOR_POSITION_CLAMP.min, FLOOR_POSITION_CLAMP.max);
    y = clamp(y, FLOOR_POSITION_CLAMP.min, FLOOR_POSITION_CLAMP.max);

    return { x, y };
}

function posicionarMesa(mesa, x, y) {
    mesa.style.left = `${x}%`;
    mesa.style.top = `${y}%`;
}

function habilitarArrastreMesa(mesa, container) {
    mesa.dataset.dragging = mesa.dataset.dragging || 'false';
    mesa.style.touchAction = 'none';

    let movimiento = false;
    let pointerId = null;

    mesa.addEventListener('pointerdown', (event) => {
        pointerId = event.pointerId;
        mesa.setPointerCapture(pointerId);
        mesa.dataset.dragging = 'false';
        movimiento = false;

        const limites = container.getBoundingClientRect();

        function onPointerMove(ev) {
            if (!movimiento) {
                movimiento = true;
                mesa.dataset.dragging = 'true';
            }

            const x = ((ev.clientX - limites.left) / limites.width) * 100;
            const y = ((ev.clientY - limites.top) / limites.height) * 100;

            const posX = clamp(x, FLOOR_POSITION_CLAMP.min, FLOOR_POSITION_CLAMP.max);
            const posY = clamp(y, FLOOR_POSITION_CLAMP.min, FLOOR_POSITION_CLAMP.max);

            posicionarMesa(mesa, posX, posY);
        }

        function onPointerUp(ev) {
            mesa.releasePointerCapture(pointerId);
            window.removeEventListener('pointermove', onPointerMove);
            window.removeEventListener('pointerup', onPointerUp);
            window.removeEventListener('pointercancel', onPointerUp);

            if (movimiento) {
                guardarPosicionMesa(mesa);
                setTimeout(() => {
                    mesa.dataset.dragging = 'false';
                }, 0);
            }
        }

        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', onPointerUp);
        window.addEventListener('pointercancel', onPointerUp);
    });
}

function guardarPosicionMesa(mesa) {
    const container = document.getElementById('mesasContainer');
    if (!container) {
        return;
    }

    const rect = container.getBoundingClientRect();
    const mesaRect = mesa.getBoundingClientRect();

    const x = ((mesaRect.left + mesaRect.width / 2) - rect.left) / rect.width * 100;
    const y = ((mesaRect.top + mesaRect.height / 2) - rect.top) / rect.height * 100;

    const posX = clamp(x, FLOOR_POSITION_CLAMP.min, FLOOR_POSITION_CLAMP.max);
    const posY = clamp(y, FLOOR_POSITION_CLAMP.min, FLOOR_POSITION_CLAMP.max);

    persistirPosicionMesaRemota(mesa, posX, posY);
}

function obtenerStorageKey(salonId, mesaId) {
    return `${FLOOR_STORAGE_PREFIX}_${salonId}_${mesaId}`;
}

function clamp(valor, min, max) {
    return Math.max(min, Math.min(max, valor));
}

function persistirPosicionMesaRemota(mesa, posX, posY, storageKey) {
    if (!mesa || !mesa.dataset) {
        return;
    }

    const mesaId = mesa.dataset.mesaId;
    if (!mesaId) {
        return;
    }

    mesa.dataset.posx = posX;
    mesa.dataset.posy = posY;

    if (typeof $ === 'undefined') {
        if (storageKey) {
            try {
                localStorage.removeItem(storageKey);
            } catch (err) {
                console.warn('No se pudo limpiar la posición antigua del almacenamiento local.', err);
            }
        }
        return;
    }

    $.ajax({
        method: 'POST',
        url: window.location.href,
        dataType: 'json',
        data: {
            action: 'guardarPosicionMesa',
            mesaId: mesaId,
            posX: posX,
            posY: posY
        }
    }).done(() => {
        if (storageKey) {
            try {
                localStorage.removeItem(storageKey);
            } catch (err) {
                console.warn('No se pudo limpiar la posición antigua del almacenamiento local.', err);
            }
        }
    }).fail((jqXHR, textStatus, errorThrown) => {
        console.warn('No se pudo guardar la posición de la mesa.', textStatus || errorThrown);
    });
}

function solicitarResetSalonRemoto(salonId) {
    if (typeof $ === 'undefined') {
        return;
    }

    $.ajax({
        method: 'POST',
        url: window.location.href,
        dataType: 'json',
        data: {
            action: 'resetearPosicionesSalon',
            salonId: salonId
        }
    }).fail((jqXHR, textStatus, errorThrown) => {
        console.warn('No se pudo resetear las posiciones del salón.', textStatus || errorThrown);
    });
}
