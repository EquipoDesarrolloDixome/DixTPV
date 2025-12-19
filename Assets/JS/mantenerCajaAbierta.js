function abrirCaja(dineroInicial, camareroId) {
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: {
            action: 'seleccionTerminal',
            dinero_inicial: dineroInicial,
            camarero: camareroId
        },
        success: function (response) {
            const data = JSON.parse(response);
            if (data.success) {
                // Guardar en localStorage
                localStorage.setItem("idcaja", data.idcaja);
                localStorage.setItem("cajaActivaFecha", data.cajaActivaFecha);
                localStorage.setItem("dineroInicial", data.dineroInicial);
                localStorage.setItem("camareroActivo", data.camareroActivo);

                console.log("Caja guardada en LocalStorage:", data);
            }
        }
    });
}

window.addEventListener("load", function () {
    if (localStorage.getItem("idcaja")) {
        $.ajax({
            method: "POST",
            url: window.location.href + "?action=recuperarCajaDesdeLocalStorage",
            data: {
                idcaja: localStorage.getItem("idcaja"),
                cajaActivaFecha: localStorage.getItem("cajaActivaFecha"),
                dineroInicial: localStorage.getItem("dineroInicial"),
                camareroActivo: localStorage.getItem("camareroActivo")
            }
        }).done(function (response) {
            console.log("Caja restaurada desde LocalStorage:", response);
        });
    }
});
