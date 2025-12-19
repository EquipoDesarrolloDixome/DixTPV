
function seleccionarCamarero(codagente, claveCorrecta) {

}

function verificarClave(codagente) {
    const claveInput = document.getElementById('clave-input-' + codagente);
    const claveIngresada = claveInput.value;
    const claveCorrecta = claveInput.getAttribute('data-clave-correcta');

    if (claveIngresada === claveCorrecta) {
        localStorage.setItem('camarero', JSON.stringify(codagente));
        $('#modalcamareros').modal('hide');
    } else {
        alert("Clave incorrecta. Intente nuevamente.");
    }

    claveInput.value = '';
}


function agregarNumeroCam(numero) {
    if (inputSeleccionado) {
        // Añadir el número al final del contenido actual del input
        const currentValue = inputSeleccionado.value;

        // Verificar si el valor actual es un número o vacío
        if (currentValue === "0") {
            inputSeleccionado.value = numero; // Si está vacío o es cero, reemplazar
        } else {
            inputSeleccionado.value += numero; // Añadir el número al final
        }

        const index = inputSeleccionado.dataset.index;

        // Actualiza la cantidad en localStorage y en la vista
    }
}

function seleccionarInputCam(input) {
    inputSeleccionado = input;
    inputSeleccionado.focus();
    inputSeleccionado.select();
    inputSeleccionado.value = "";
}
