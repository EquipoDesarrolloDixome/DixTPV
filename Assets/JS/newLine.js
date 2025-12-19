function addProducto() {
  const nombreRaw = (document.getElementById('newName').value || '').trim();
  const nombre = nombreRaw === '' ? 'Sin nombre' : nombreRaw;

  // Precio con IVA desde input (permite coma o punto)
  const pvpBrutoStr = String(document.getElementById('newPvp').value || '').replace(',', '.');
  const pvpBruto = parseFloat(pvpBrutoStr);

  // IVA %
  const ivaStr = String(document.getElementById('newIva').value || '').replace(',', '.');
  const iva = parseFloat(ivaStr);

  // Validaciones
  if (!Number.isFinite(pvpBruto) || pvpBruto <= 0) {
    alert('El precio debe ser un número válido y mayor que 0.');
    return;
  }
  if (!Number.isFinite(iva) || iva < 0) {
    alert('El IVA debe ser un número válido (0 o más).');
    return;
  }

  // Convertimos a PVP sin IVA
  const pvpSinIVA = pvpBruto / (1 + iva / 100);

  // Carrito
  let carrito = JSON.parse(localStorage.getItem('carrito')) || [];

  const referencia = 'cust' + Date.now(); // id único
  const nuevoProducto = {
    referencia: referencia,
    descripcion: nombre,
    pvp: parseFloat(pvpSinIVA.toFixed(6)),  // guardamos sin IVA
    codimpuesto: parseFloat(iva.toFixed(2)),// IVA % (para contabilidad)
    cantidad: 1
  };

  carrito.push(nuevoProducto);
  localStorage.setItem('carrito', JSON.stringify(carrito));

  // Limpiar y cerrar
  document.getElementById('newName').value = 'Varios';
  document.getElementById('newPvp').value = '1.00';
  document.getElementById('newIva').value = '21';
  $('#newLine').modal('hide');

  // Refrescar UI
  actualizarCarrito();
}

function agregarNumeroNL(numero) {
  if (!inputSeleccionado) return;

  let ch = String(numero);
  if (ch === './,' || ch === './' || ch === ',') ch = '.';

  const kind   = inputSeleccionado.dataset.kind || '';
  const hasRef = !!inputSeleccionado.dataset.ref;
  const cur    = String(inputSeleccionado.value || '');

  // Reglas por tipo:
  if (kind === 'qty') {
    if (!/^\d$/.test(ch)) return;
    const next = (cur === '0') ? ch : (cur + ch);
    inputSeleccionado.value = next.replace(/^0+(\d)/, '$1');
    // Para qty con ref sí queremos commit suave
    if (hasRef) _commitInputSeleccionado(true);
    return;
  }

  // pvp: decimal, pero SOLO comitea si tiene ref (en tabla)
  if (kind === 'pvp') {
    if (ch === '.') { if (cur.includes('.')) return; }
    else if (!/^\d$/.test(ch)) return;

    let next = (cur === '0' && ch !== '.') ? ch : (cur + ch);
    if (next === '.') next = '0.';
    inputSeleccionado.value = next;

    if (hasRef) _commitInputSeleccionado(true); // en modal NO hay ref → no comitea
    return;
  }

  // plain-decimal / iva: solo escribir, sin commits
  if (kind === 'plain-decimal' || kind === 'iva' || !hasRef) {
    if (ch === '.') { if (cur.includes('.')) return; }
    else if (!/^\d$/.test(ch)) return;

    let next = (cur === '0' && ch !== '.') ? ch : (cur + ch);
    if (next === '.') next = '0.';
    inputSeleccionado.value = next;
    return;
  }
}

function borrarNumero() {
  if (!inputSeleccionado) return;

  const kind   = inputSeleccionado.dataset.kind || '';
  const hasRef = !!inputSeleccionado.dataset.ref;
  const cur    = String(inputSeleccionado.value || '');
  const next   = cur.length > 0 ? cur.slice(0, -1) : '';

  inputSeleccionado.value = next;

  if (kind === 'qty' && hasRef) {
    _commitInputSeleccionado(true);
  } else if (kind === 'pvp' && hasRef) {
    _commitInputSeleccionado(true);
  } // plain-decimal / iva → sin commit
}
