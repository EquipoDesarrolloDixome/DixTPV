function nuevoCliente() {
    // Cierra el modal actual
    const modalActual = document.getElementById('modalfinalizar');
    if (modalActual) {
        $(modalActual).modal('hide'); // Bootstrap 4 usa jQuery para modales
    }

    // Abre el nuevo modal
    const modalNuevo = document.getElementById('modalNuevoCliente');
    if (modalNuevo) {
        $(modalNuevo).modal('show');
    } else {
        console.warn("El modal 'nuevoCliente' no existe en el DOM.");
    }
}

function anhadirNuevoCliente() {
    const razonSocial = document.getElementById('razonSocial');
    const tipoIdFiscal = document.getElementById('tipoIdFiscal');
    const numeroIdFiscal = document.getElementById('numeroIdFiscal');
    const direccion = document.getElementById('direccion');
    const codigoPostal = document.getElementById('codigoPostal');
    const ciudad = document.getElementById('ciudad');
    const provincia = document.getElementById('provincia');
    const pais = document.getElementById('codpais');
    const data = {
        action: 'anhadirNuevoCliente',
        razonSocial: razonSocial.value,
        tipoIdFiscal: tipoIdFiscal.value,
        numeroIdFiscal: numeroIdFiscal.value,
        direccion: direccion.value,
        codigoPostal: codigoPostal.value,
        ciudad: ciudad.value,
        provincia: provincia.value,
        pais: pais.value

    };
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        datatype: "json",
        success: function (results) {
            // Recargar la página una vez completada la solicitud
            const modalNuevo = document.getElementById('modalNuevoCliente');
            $(modalNuevo).modal('hide');
            location.reload();
        },
        error: function (msg) {
            // Mostrar mensaje de error en la consola
            console.log("error postRequest" + msg.status + " " + msg.responseText);
        }
    });
}
