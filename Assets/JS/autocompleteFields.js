function initAutocompletadoCliente(inputSelector, hiddenSelector, appendToSelector) {
    const $input = $(inputSelector);
    const $hidden = $(hiddenSelector);
    if (!$input.length) {
        return;
    }

    const appendTarget = appendToSelector || "#modalfinalizar";

    $input.autocomplete({
        delay: 500,
        appendTo: appendTarget,
        source: function (request, response) {
            $.ajax({
                url: location.href,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    search: request.term,
                    action: 'searchclient'
                },
                success: function (data) {
                    console.log(data);
                    response(data);
                }
            });
        },
        select: function (event, ui) {
            $input.val(ui.item.label);
            if ($hidden.length) {
                $hidden.val(ui.item.value);
                if ($hidden.is('#cliente') && typeof window.dixOnClientChanged === 'function') {
                    window.dixOnClientChanged(ui.item.value);
                }
            }
            return false;
        },
        focus: function (event, ui) {
            $input.val(ui.item.label);
            if ($hidden.length) {
                $hidden.val(ui.item.value);
            }
            return false;
        }
    });
}

$(document).ready(function () {
    initAutocompletadoCliente("#nombre_cliente", "#cliente", "#modalfinalizar");
    initAutocompletadoCliente("#nombre_clienteDiv", "#clienteDiv", "#modalfinalizar");
    initAutocompletadoCliente("#nombre_cliente_aparcar", "#cliente_aparcar", "#modalClienteAparcar");
    initAutocompletadoCliente("#nombre_cliente_cambio", "#cliente_cambio", "#modalCambiarCliente");
});
