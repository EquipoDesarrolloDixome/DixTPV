var inputSeleccionado = null;




function seleccionarCamarero(codagente, claveCorrecta) {
  // visual
  document.querySelectorAll('.card-camarero').forEach(el => el.classList.remove('camarero-seleccionado'));
  const card = document.getElementById(`camarero_${codagente}`);
  if (card) card.classList.add('camarero-seleccionado');

  // autocompleta el input oculto con la clave del camarero
  const inputClave = document.getElementById('clave-camarero');
  if (inputClave) {
    inputClave.value = claveCorrecta || '';
  }

  // reutiliza tu verificación existente (cerrará el modal y guardará en localStorage)
  verificarClave();
}

function verificarClave() {
  const claveInput = document.getElementById('clave-camarero');
  const claveIngresada = claveInput ? claveInput.value : '';

  if (window.dixTpvCamareroMode === 'pin' && !claveIngresada) {
    alert('Introduce clave');
    if (claveInput) {
      claveInput.focus();
    }
    return;
  }

  // Busca el camarero por su data-pwdcamarero
  let camareroEncontrado = null;
  document.querySelectorAll('[data-pwdcamarero]').forEach(camarero => {
    if (camarero.dataset.pwdcamarero === claveIngresada) {
      camareroEncontrado = camarero;
    }
  });

  if (camareroEncontrado) {
    // Guardar el código del camarero y cerrar
    localStorage.setItem('camarero', parseInt(camareroEncontrado.dataset.codagente));
    $('#modalcamareros').modal('hide');
  } else {
    alert('Clave incorrecta. Intente nuevamente.');
  }

  if (claveInput) claveInput.value = '';
}


function seleccionarInputCam(input) {
  inputSeleccionado = input;
  input.focus();
}

function borrarUltimoDigito() {
  const input = document.getElementById('clave-camarero');
  if (input) input.value = input.value.slice(0, -1);
}

function agregarNumeroCam(numero) {
  const input = document.getElementById('clave-camarero');
  if (!input) return;
  input.value = (input.value === '0') ? String(numero) : input.value + String(numero);
}

document.addEventListener('DOMContentLoaded', function () {
  const pinInput = document.getElementById('clave-camarero');
  const pinForm = document.getElementById('form-pin-camarero');

  if (pinInput) {
    pinInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        verificarClave();
      }
    });
  }

  if (pinForm) {
    pinForm.addEventListener('submit', function (event) {
      event.preventDefault();
      verificarClave();
    });
  }
});
