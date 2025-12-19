async function printRequest( {print_job_id}) {
    const data = new FormData();

    data.append('print_job_id', print_job_id);


    await printOnDesktop(data);
}
async function printOnDesktop(data) {
    data.set('action', 'print-desktop-ticket');

    return printerServiceRequest(data);

}

function dixTpvHandleCashDrawerResponse(response) {
    if (!response) {
        return Promise.resolve(false);
    }

    if (response.print_job_id && typeof printRequest === 'function') {
        return printRequest(response);
    }

    if (response.escpos && typeof sendEscposToPrinter === 'function') {
        const readyPromise = (typeof ensureQZSession === 'function')
            ? Promise.resolve(ensureQZSession()).catch(() => false)
            : Promise.resolve(true);

        return readyPromise.then(isReady => {
            if (false === isReady) {
                console.warn('QZ Tray no está listo para abrir el cajón.');
                return false;
            }

            let payload = response.escpos;
            if ((response.escposEncoding || '').toLowerCase() === 'base64' && typeof atob === 'function') {
                try {
                    payload = atob(response.escpos);
                } catch (decodeErr) {
                    console.warn('No se pudo decodificar la orden del cajón.', decodeErr);
                    return false;
                }
            }

            return sendEscposToPrinter(payload, 'modalPrintAlert', 'modalPrintAlertMessage');
        });
    }

    return Promise.resolve(false);
}
window.dixTpvHandleCashDrawerResponse = dixTpvHandleCashDrawerResponse;
async function printerServiceRequest( {print_job_id}) {
    let params = new URLSearchParams({"print_job_id": print_job_id});
    const query = window.location.search;
    const paramsTotal = new URLSearchParams(query);

    await fetch('http://127.0.0.1:8089?' + params, {
        mode: 'no-cors', method: 'GET'
    }).then(response => {
        window.location.href = 'DixTPV';
    });

}
function ImprimirViejo(id, modelClassName) {
    const resolvedClass = modelClassName && modelClassName !== '' ? modelClassName : 'FacturaCliente';
    Promise.resolve(automaticTicketPrint({
        modelClassName: resolvedClass,
        modelCode: id
    })).then(() => {
        if (typeof setToast === 'function') {
            setToast('Ticket enviado a impresión', 'success', 'Imprimir ticket', 3000);
        }
    });
}
function imprimirComprobante() {
    const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    let printerId = '';
    if (typeof resolveStoredTicketPrinterId === 'function') {
        printerId = resolveStoredTicketPrinterId() || '';
    }
    let terminalId = '';
    const terminalInput = document.getElementById('selectedTerminalId');
    if (terminalInput && terminalInput.value) {
        terminalId = terminalInput.value;
    } else if (typeof selectedTerminalId !== 'undefined' && selectedTerminalId) {
        terminalId = selectedTerminalId;
    }
    console.log("Carrito");
    console.log(carrito);
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: {
            action: 'printVoucher',
            carrito: JSON.stringify(carrito),
            template: 'VoucherTicket',
            printerId: printerId,
            terminalId: terminalId
        },
        success: function (response) {
            const jsonResponse = JSON.parse(response);
            console.log("Hemos impreso con ");
            console.log(jsonResponse);
            setToast('Comprobante enviado a impresión', 'success', 'Imprimir comprobante', 5000);
            if (jsonResponse.printed && jsonResponse.escpos && typeof sendEscposToPrinter === 'function') {
                const readyPromise = (typeof ensureQZSession === 'function')
                    ? Promise.resolve(ensureQZSession()).catch(() => false)
                    : Promise.resolve(true);

                readyPromise.then(isReady => {
                    if (false === isReady) {
                        console.warn('QZ Tray no está listo para imprimir el comprobante.');
                        return;
                    }
                    let escposData = jsonResponse.escpos;
                    if ((jsonResponse.escposEncoding || '').toLowerCase() === 'base64' && typeof atob === 'function') {
                        try {
                            escposData = atob(jsonResponse.escpos);
                        } catch (decodeErr) {
                            console.warn('No se pudo decodificar el comprobante base64.', decodeErr);
                            return false;
                        }
                    }
                    return sendEscposToPrinter(escposData, 'modalPrintAlert', 'modalPrintAlertMessage');
                }).catch(err => {
                    console.error('No se pudo enviar el comprobante a la impresora.', err);
                });
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log("Error en la solicitud: " + textStatus + ", " + errorThrown);
            alert('Error al comunicar con el servidor. Intenta de nuevo.');
        }
    });

}
function abrirCajon() {

    return new Promise((resolve, reject) => {
        $.ajax({
            method: "POST",
            url: window.location.href,
            data: {action: 'open-drawer',  template: 'CashDrawer'},
            success: function (response) {
                let jsonResponse = response;
                if (typeof response === 'string') {
                    try {
                        jsonResponse = JSON.parse(response);
                    } catch (err) {
                        console.warn('Respuesta inesperada al abrir cajón.', err, response);
                        jsonResponse = {};
                    }
                }

                Promise.resolve(dixTpvHandleCashDrawerResponse(jsonResponse)).finally(() => {
                    console.log("Enviada orden de abrir cajón ");
                    console.log(jsonResponse);
                    setToast('Abrir cajón portamonedas', 'success', 'Apertura cajón', 5000);
                    resolve(jsonResponse);
                });
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log("Error en la solicitud: " + textStatus + ", " + errorThrown);
                alert('Error al comunicar con el servidor. Intenta de nuevo.');
                reject(errorThrown || textStatus);
            }
        });
    });
}
