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
// Esta función se llama cuando se abre el modal para un nuevo producto
function modificarProducto(pvp, referencia, cantidad, descripcion, codimpuesto) {
    // Almacena los datos en localStorage
    localStorage.setItem('precio', pvp);
    localStorage.setItem('referencia', referencia);
    localStorage.setItem('descripcion', descripcion);
    localStorage.setItem('codimpuesto', codimpuesto);
    // Actualiza el contenido del modal
    pvpConIva=pvp*(1+codimpuesto/100);
    document.getElementById('modifprod').innerHTML = `
            <div>Referencia: ${referencia}</div>
            <div>Descripción: ${descripcion}</div>
            <div>Precio: <input id="inputPvp" value="${pvpConIva.toFixed(2)}" type="number" min="0" step="0.01"/></div>
            <div hidden>Impuesto: <input id="inputImpuesto" value="${codimpuesto}" type="number" min="0"/></div>
            <button class="btn btn-success" onclick="guardarProducto();">Guardar</button>
        `;
}
// Llama a esta función para limpiar los datos al cerrar el modal
function cerrarModalmodif() {
    $('#modalmodifprod').modal('hide');
    localStorage.removeItem("precio");
    localStorage.removeItem("referencia");
    localStorage.removeItem("descripcion");
    localStorage.removeItem("codimpuesto");
}

function guardarProducto() {
    //alert('Producto modificado y guardado');

    // Obtén el nuevo valor de 'pvp' desde el input
    const nuevoPvp = parseFloat(document.getElementById('inputPvp').value);
    const nuevoImpuesto = parseFloat(document.getElementById('inputImpuesto').value);
    const referencia = localStorage.getItem('referencia');
    const precioSinIVA = nuevoPvp / (1 + nuevoImpuesto / 100)

    // Actualiza el precio en el carrito en localStorage
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    if (carrito.length > 0) {
        carrito = carrito.map(item => {
            if (item.referencia === referencia) {
                return {...item, pvp: precioSinIVA, codimpuesto: nuevoImpuesto, pvpManual: true};
            }
            return item;
        });
        localStorage.setItem('carrito', JSON.stringify(carrito));
    } else {
        alert('No hay productos en el carrito.');
        return;
    }
    cerrarModalmodif();
    actualizarCarrito();
}
// Evento para detectar cuando el modal se oculta y actualizar la vista del carrito
$('#modalmodifprod').on('hidden.bs.modal', function () {
    actualizarCarrito();
    cerrarModalmodif(); // Limpia los datos de localStorage
});
