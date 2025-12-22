var inputSeleccionado = null;
const DIX_COBRO_CLIENT_STORAGE_KEY = 'dixtpvCobroClient';

function dixSyncCobroClientTargets(code, name) {
    const clientCode = code || '';
    const clientName = typeof name === 'string' ? name : '';
    const hiddenMain = document.getElementById('cliente');
    if (hiddenMain) {
        hiddenMain.value = clientCode;
    }
    const textMain = document.getElementById('nombre_cliente');
    if (textMain) {
        textMain.value = clientName;
    }
    const aparcarHidden = document.getElementById('cliente_aparcar');
    if (aparcarHidden) {
        aparcarHidden.value = clientCode;
    }
    const aparcarText = document.getElementById('nombre_cliente_aparcar');
    if (aparcarText) {
        aparcarText.value = clientName;
    }
    const cobrarHidden = document.querySelector('#cliente_cobro_modal input[name="codcliente"]');
    if (cobrarHidden) {
        cobrarHidden.value = clientCode;
    }
}

function dixStoreCobroClientSelection(code, name) {
    if (typeof sessionStorage === 'undefined') {
        return;
    }
    try {
        sessionStorage.setItem(DIX_COBRO_CLIENT_STORAGE_KEY, JSON.stringify({
            code: code || '',
            name: name || ''
        }));
    } catch (err) {
        console.warn('No se pudo guardar el cliente seleccionado para cobrar.', err);
    }
}

function dixRestoreCobroClientSelection() {
    if (typeof sessionStorage === 'undefined') {
        return;
    }
    const raw = sessionStorage.getItem(DIX_COBRO_CLIENT_STORAGE_KEY);
    if (!raw) {
        return;
    }
    let saved = null;
    try {
        saved = JSON.parse(raw);
    } catch (err) {
        console.warn('No se pudo leer el cliente guardado para cobrar.', err);
        return;
    }
    const code = (saved && typeof saved.code === 'string') ? saved.code.trim() : '';
    if (code === '') {
        return;
    }
    const name = (saved && typeof saved.name === 'string') ? saved.name : '';
    dixSyncCobroClientTargets(code, name);
    if (typeof window.dixOnClientChanged === 'function') {
        window.dixOnClientChanged(code);
    }
}

function agregarNumero(numero) {
    if (!inputSeleccionado) return;

    // Normaliza botón "./," o "," a "."
    let ch = String(numero);
    if (ch === './,' || ch === './' || ch === ',') ch = '.';

    // Detecta el contexto
    const kind = inputSeleccionado.dataset.kind;     // "qty" | "pvp" (opcional)
    const ref  = inputSeleccionado.dataset.ref;      // referencia (opcional)
    const imp  = parseFloat(inputSeleccionado.dataset.impuesto) || 0; // para pvp (opcional)
    const idx  = inputSeleccionado.dataset.index;    // índice (fallback)
    const cur  = String(inputSeleccionado.value || '');

    // Validaciones de carácter
    if (ch === '.') {
        // Cantidad no permite decimales
        if (kind === 'qty') return;

        // Precio permite UN solo punto
        if (cur.includes('.')) return;
    } else if (!(ch >= '0' && ch <= '9')) {
        return; // solo números
    }

    // Construir nuevo valor
    let next = (cur === '0' && ch !== '.') ? ch : (cur + ch);
    if (next === '.') next = '0.'; // si empieza por ".", lo dejamos como "0."

    // Setear y confirmar
    inputSeleccionado.value = next;
    _commitInputSeleccionado(true /*soft*/);
}
/**
 * Borra el último carácter del valor del input seleccionado.
 */
function borrarNumero() {
    if (!inputSeleccionado) return;

    const cur = String(inputSeleccionado.value || '');
    const next = cur.length > 0 ? cur.slice(0, -1) : '';

    inputSeleccionado.value = next;
    _commitInputSeleccionado(true /*soft*/);
}

/**
 * Confirma el valor actual del input seleccionado.
 * - soft=true: no repinta toda la tabla; recalcula fila/total si hay helpers por ref.
 * - soft=false: repinta (por ejemplo en onblur).
 */
function _commitInputSeleccionado(soft = false) {
    if (!inputSeleccionado) return;

    const kind = inputSeleccionado.dataset.kind;        // "qty" | "pvp" (opcional)
    const ref  = inputSeleccionado.dataset.ref;         // referencia (opcional)
    const imp  = parseFloat(inputSeleccionado.dataset.impuesto) || 0; // para pvp (opcional)
    const idx  = inputSeleccionado.dataset.index;       // índice (fallback)
    const raw  = String(inputSeleccionado.value || '');

    // No commit si vacío o sólo "."
    if (raw === '' || raw === '.') return;

    if (kind === 'qty' && ref) {
        const n = parseInt(raw, 10);
        if (isNaN(n) || n < 1) return;
        // requiere que exista actualizarCantidadPorRef(referencia, cantidad, repaint)
        if (typeof actualizarCantidadPorRef === 'function') {
            actualizarCantidadPorRef(ref, n, soft ? false : true);
            return;
        }
    }

    if (kind === 'pvp' && ref) {
        const limpio = raw.replace(',', '.'); // por si viene coma del SO
        const n = parseFloat(limpio);
        if (isNaN(n) || n < 0) return;
        // requiere que exista actualizarPrecioPorRef(referencia, pvpConIVA, impuesto, repaint)
        if (typeof actualizarPrecioPorRef === 'function') {
            actualizarPrecioPorRef(ref, limpio, imp, soft ? false : true);
            return;
        }
    }

    // --- Fallback a tu lógica por índice (para compatibilidad) ---
    // Si sólo existe dataset.index y la función actualizarCantidad(index, ...)
    if (typeof idx !== 'undefined' && typeof actualizarCantidad === 'function') {
        // Si el input es de cantidad, guarda como entero
        if (kind === 'qty' || !kind) {
            const n = parseInt(raw, 10);
            if (!isNaN(n) && n >= 1) {
                actualizarCantidad(idx, n);
            }
        } else {
            // Si es precio pero no tenemos helpers por ref, no hacemos nada aquí
            // (porque tu actualizarCantidad por índice solo maneja cantidad)
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
    inputSeleccionado.select(); // si no quieres seleccionar todo, quítalo
}

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

(function () {
    let cambioClienteModalInstance = null;

    function getCambioClienteModal() {
        const modalElement = document.getElementById('modalCambiarCliente');
        if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return null;
        }
        if (!cambioClienteModalInstance) {
            cambioClienteModalInstance = new bootstrap.Modal(modalElement, {
                backdrop: 'static'
            });
        }
        return cambioClienteModalInstance;
    }

    window.abrirModalCambioCliente = function () {
        const modal = getCambioClienteModal();
        if (!modal) {
            return;
        }

        const hiddenMain = document.getElementById('cliente');
        const textMain = document.getElementById('nombre_cliente');
        const hiddenModal = document.getElementById('cliente_cambio');
        const textModal = document.getElementById('nombre_cliente_cambio');

        if (hiddenModal) {
            hiddenModal.value = hiddenMain ? (hiddenMain.value || '') : '';
        }
        if (textModal) {
            textModal.value = textMain ? (textMain.value || '') : '';
            setTimeout(() => textModal.focus(), 200);
        }

        modal.show();
    };

    window.confirmarCambioClienteManual = function () {
        const hiddenModal = document.getElementById('cliente_cambio');
        const textModal = document.getElementById('nombre_cliente_cambio');
        if (!hiddenModal) {
            return;
        }

        const code = (hiddenModal.value || '').trim();
        if (code === '') {
            alert('Selecciona un cliente antes de guardar.');
            return;
        }

        const clientName = textModal ? (textModal.value || '') : '';
        dixSyncCobroClientTargets(code, clientName);
        dixStoreCobroClientSelection(code, clientName);

        if (typeof window.dixOnClientChanged === 'function') {
            window.dixOnClientChanged(code);
        }

        const modal = getCambioClienteModal();
        if (modal) {
            modal.hide();
        }
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    dixRestoreCobroClientSelection();
});

function imprimirUltimo() {
    const storedDoc = localStorage.getItem('ultimoDocumentoTPV');
    if (!storedDoc) {
        alert('No hay un ticket reciente para reimprimir.');
        return;
    }

    let docInfo = null;
    try {
        docInfo = JSON.parse(storedDoc);
    } catch (err) {
        console.error('No se pudo interpretar la información del último documento.', err);
    }

    if (!docInfo || !docInfo.modelClassName || !docInfo.modelCode) {
        alert('No se encontró información válida del último documento.');
        return;
    }

    Promise.resolve(automaticTicketPrint(docInfo));
}



// Función para alternar la visibilidad de la sección "PP"
function togglePP() {
    const seccionPP = document.getElementById('seccionPP');
    const seccionCesta = document.getElementById('seccionCesta');
    const seccionNormal = document.getElementById('seccionNormal');
    // const seccionCarritofin = document.getElementById('seccionCarritofin');

    // Verificar si cada sección existe antes de acceder a sus propiedades
    if (seccionPP)
        seccionPP.style.display = 'block'; // Mostrar sección PP
    if (seccionCesta)
        seccionCesta.style.display = 'none'; // Ocultar sección Cesta
    if (seccionNormal)
        seccionNormal.style.display = 'none'; // Ocultar sección Normal
    document.getElementById('lblbtnnormal').classList.remove("active");
    //document.getElementById('lblbtnPP').classList.add("active");
    document.getElementById('btnDivCuenta').classList.remove("active");
}
function toggleNormal() {
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    let carritofin = JSON.parse(localStorage.getItem('carritofin')) || [];
    $("#listaProductosCobrados").html("");
    cargarProductosCesta();
    carrito = carrito.concat(carritofin);
    localStorage.setItem('carrito', JSON.stringify(carrito));
    localStorage.setItem('carritofin', JSON.stringify([]));
    localStorage.removeItem('carritofin');

    tipoCobro = TCOBRO_NORMAL;
    document.getElementById('dividir_personas').classList.add('ocultar');
    document.getElementById('botonpasartodo').classList.add('ocultar');
    importeCobrar = parseFloat(localStorage.getItem('precioacobrar')) || 0;
    totalVenta = parseFloat(localStorage.getItem('precioacobrar')) || 0;
    importeEntregado = 0.0;
    importeCambio = 0.0;
    actualizaImportesTotales();
    document.getElementById('lblbtnnormal').classList.add("active");
    // document.getElementById('lblbtnPP').classList.remove("active");
    document.getElementById('lblbtnDivCuenta').classList.remove("active");
    document.getElementById('seccionCesta').classList.add('ocultar');
}

// Función para alternar la visibilidad de la sección "Div Cuenta"
function toggleDivCuenta() {
    let carrito = JSON.parse(localStorage.getItem('carrito')) || [];
    let carritofin = JSON.parse(localStorage.getItem('carritofin')) || [];
    $("#listaProductosCobrados").html("");
    cargarProductosCesta();
    carrito = carrito.concat(carritofin);
    localStorage.setItem('carrito', JSON.stringify(carrito));
    localStorage.setItem('carritofin', JSON.stringify([]));
    localStorage.removeItem('carritofin');

    tipoCobro = TCOBRO_DC;
    document.getElementById('seccionCesta').classList.remove('ocultar');
    document.getElementById('botonpasartodo').classList.remove('ocultar');
    importeCobrar = 0.0;
    importeEntregado = 0.0;
    importeCambio = 0.0;
    actualizaImportesTotales();
    cargarProductosCesta();
    cargarProductosCobrados();
    document.getElementById('lblbtnnormal').classList.remove("active");
    document.getElementById('lblbtnDivCuenta').classList.add("active");
}

let currentParent = null;
const familyHistory = [];
let activeFamilyCode = null;
let activeFamilyLabel = '';
let initialProductsLoaded = false;
let lastInputTimestamp = 0;
let scannerTyping = false;
let rapidDigitCount = 0;

function escapeCssSelector(value) {
  if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
    return CSS.escape(value);
  }
  return value.replace(/([ #;?%&,.+*~\':"!^$[\]()=>|/@])/g, '\\$1');
}

function getFamilyLabelByCode(code) {
  if (!code) {
    return '';
  }
  const card = document.querySelector(`.fam-card[data-code="${escapeCssSelector(code)}"]`);
  if (!card) {
    return '';
  }
  return card.dataset.name || card.textContent || '';
}

function applyActiveFamilyHighlight() {
  if (typeof document === 'undefined') {
    return;
  }
  document.querySelectorAll('.fam-card').forEach(card => {
    if (card.classList.contains('fam-back')) {
      card.classList.remove('active');
      return;
    }
    const isActive = activeFamilyCode && card.dataset.code === activeFamilyCode;
    card.classList.toggle('active', Boolean(isActive));
  });
}

function emitFamilyChange() {
  if (typeof document === 'undefined') {
    return;
  }
  const event = new CustomEvent('dix:family-changed', {
    detail: {
      family: activeFamilyCode,
      label: activeFamilyLabel
    }
  });
  document.dispatchEvent(event);
}

function setActiveFamily(code) {
  activeFamilyCode = code || null;
  activeFamilyLabel = activeFamilyCode ? getFamilyLabelByCode(activeFamilyCode) : '';
  applyActiveFamilyHighlight();
  emitFamilyChange();
}

function resetFamilyLayout() {
  hideBackCard();
  familyHistory.length = 0;
  currentParent = null;
  document.querySelectorAll('.fam-card').forEach(card => {
    if (card.classList.contains('fam-back')) {
      card.style.display = 'none';
      return;
    }
    if (card.classList.contains('is-child')) {
      card.style.display = 'none';
    } else {
      card.style.display = '';
    }
  });
  applyActiveFamilyHighlight();
}

function showChildrenOnly(parentCode) {
  document.querySelectorAll('.fam-card').forEach(card => {
    if (card.classList.contains('fam-back')) {
      return;
    }
    const isDirectChild = card.dataset.parent === parentCode;
    card.style.display = isDirectChild ? '' : 'none';
  });
  showBackCard();
  applyActiveFamilyHighlight();
}

function getFamilyLoader() {
  if (typeof window !== 'undefined' && typeof window.loadFamilyProducts === 'function') {
    return window.loadFamilyProducts;
  }
  if (typeof loadFamilyProducts === 'function') {
    return loadFamilyProducts;
  }
  return null;
}

function loadProductsForFamily(familyCode) {
  const loader = getFamilyLoader();
  if (loader) {
    loader(familyCode);
  } else {
    console.error('Función loadFamilyProducts no está disponible.');
  }
}

function ensureBackCard() {
  let backCard = document.querySelector('.fam-card.fam-back');
  if (backCard) {
    return backCard;
  }

  const container = document.querySelector('.fam-content');
  if (!container) {
    return null;
  }

  backCard = document.createElement('div');
  backCard.className = 'fam-card fam-back col-6 col-sm-4 col-lg-3 col-xl-2';
  backCard.innerHTML = `
    <div class="fam-card-img no-image">
      <span class="fam-back-arrow">&larr;</span>
    </div>
  `;
  backCard.addEventListener('click', navigateBack);
  container.prepend(backCard);
  return backCard;
}

function showBackCard() {
  const backCard = ensureBackCard();
  if (backCard) {
    backCard.style.display = 'block';
  }
}

function hideBackCard() {
  const backCard = document.querySelector('.fam-card.fam-back');
  if (backCard) {
    backCard.style.display = 'none';
  }
}

function navigateBack() {
  if (currentParent === null && familyHistory.length === 0) {
    if (activeFamilyCode) {
      setActiveFamily(null);
      loadProductsForFamily('');
    }
    return;
  }

  const previousParent = familyHistory.pop() ?? null;
  if (previousParent === null) {
    currentParent = null;
    resetFamilyLayout();
    setActiveFamily(null);
    loadProductsForFamily('');
    return;
  }

  currentParent = previousParent;
  showChildrenOnly(previousParent);
  setActiveFamily(previousParent);
  loadProductsForFamily(previousParent);
}

function enterFamily(familyCode, pushHistory = true) {
  const children = document.querySelectorAll(`.fam-card[data-parent="${familyCode}"]`);
  loadProductsForFamily(familyCode);
  setActiveFamily(familyCode);

  if (children.length === 0) {
    return;
  }

  if (pushHistory) {
    familyHistory.push(currentParent);
  }

  currentParent = familyCode;
  showChildrenOnly(familyCode);
}

function handleFamilyClick(familyCode) {
  if (activeFamilyCode === familyCode) {
    setActiveFamily(null);
    resetFamilyLayout();
    loadProductsForFamily('');
    return;
  }
  enterFamily(familyCode, true);
}

function escapeHtml(str = '') {
  return String(str).replace(/[&<>"']/g, function (char) {
    switch (char) {
      case '&':
        return '&amp;';
      case '<':
        return '&lt;';
      case '>':
        return '&gt;';
      case '"':
        return '&quot;';
      case "'":
        return '&#39;';
      default:
        return char;
    }
  });
}

function setSearchLoadingState(container, isLoading) {
  if (!container) {
    return;
  }
  container.classList.toggle('is-loading', Boolean(isLoading));
  const spinner = container.querySelector('.tpv-search-spinner');
  if (spinner) {
    spinner.classList.toggle('d-none', !isLoading);
  }
}

function reloadProductsAfterSearchClear() {
  const grid = document.getElementById('productos');
  if (!grid) {
    return;
  }
  if (activeFamilyCode) {
    loadProductsForFamily(activeFamilyCode);
  } else {
    loadProductsForFamily('');
  }
}

function initProductSearchWidget() {
  const container = document.querySelector('.tpv-product-search');
  if (!container) {
    return;
  }

  const input = document.getElementById('productSearchInput');
  const helper = document.getElementById('productSearchHelper');

  if (!input || !helper) {
    return;
  }

  let debounceTimer = null;
  let lastRequestToken = 0;

  const productsContainer = document.getElementById('productos');
  const looksLikeBarcode = term => /^[0-9]{4,}$/.test(term);

  function updateHelperText(customText = '') {
    if (customText) {
      helper.textContent = customText;
      return;
    }

    let text = 'Escribe para buscar por nombre o referencia';
    if (activeFamilyCode) {
      text += activeFamilyLabel ? ` en ${activeFamilyLabel}` : ' en la familia seleccionada';
    } else {
      text += ' en todas las familias';
    }
    text += '. Pulsa Enter para añadir un código de barras.';
    helper.textContent = text;
  }

  function renderEmptyState(term) {
    if (!productsContainer) {
      return;
    }
    productsContainer.innerHTML = `
      <div class="tpv-search-empty">
        No encontramos productos que coincidan con "${escapeHtml(term)}".
      </div>
    `;
  }

  function processTextSearchResult(data, term) {
    if (!productsContainer) {
      return;
    }
    if (!data || data.resultado !== 'OK') {
      updateHelperText(data && data.message ? data.message : 'No se pudo completar la búsqueda.');
      return;
    }
    const html = data.htmlFamily || '';
    if (html.trim() === '') {
      renderEmptyState(term);
    } else {
      productsContainer.innerHTML = html;
      updateHelperText(`Mostrando resultados para "${escapeHtml(term)}".`);
    }
  }

  function processBarcodeSearchResult(data) {
    if (!data || data.resultado !== 'OK' || !data.product) {
      updateHelperText(data && data.message ? data.message : 'Producto no encontrado.');
      if (typeof setToast === 'function') {
        setToast('No se encontró el código de barras indicado.', 'warning');
      }
      return;
    }

    const product = data.product;
    if (typeof annadir === 'function') {
      annadir(
        product.referencia,
        product.descripcion,
        product.pvp,
        product.codimpuesto,
        product.stock,
        product.ventasinstock
      );
    }
    input.value = '';
    updateHelperText('Código añadido a la cuenta.');
  }

  function performSearch(term, mode) {
    if (mode === 'barcode') {
      scannerTyping = false;
      lastInputTimestamp = 0;
      rapidDigitCount = 0;
    }
    const requestToken = ++lastRequestToken;
    setSearchLoadingState(container, true);

    $.ajax({
      method: 'POST',
      url: window.location.href,
      data: {
        action: 'searchProducts',
        mode: mode,
        term: term,
        family: activeFamilyCode || ''
      },
      success: function (response) {
        if (requestToken !== lastRequestToken) {
          return;
        }
        setSearchLoadingState(container, false);

        let data;
        try {
          data = typeof response === 'object' ? response : JSON.parse(response);
        } catch (error) {
          console.error('Respuesta de búsqueda inválida', error);
          updateHelperText('No se pudo interpretar la respuesta.');
          return;
        }

        if (mode === 'barcode') {
          processBarcodeSearchResult(data);
        } else {
          processTextSearchResult(data, term);
        }
      },
      error: function (xhr) {
        if (requestToken !== lastRequestToken) {
          return;
        }
        setSearchLoadingState(container, false);
        console.error('Error en la búsqueda de productos', xhr);
        updateHelperText('No se pudo buscar productos. Inténtalo de nuevo.');
      }
    });
  }

  function queueTextSearch(term) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      if (term === '') {
        reloadProductsAfterSearchClear();
        updateHelperText();
        rapidDigitCount = 0;
        scannerTyping = false;
        return;
      }
      performSearch(term, 'text');
    }, 150);
  }

  input.addEventListener('input', function () {
    const term = input.value.trim();
    const now = Date.now();
    const delta = now - (lastInputTimestamp || 0);
    lastInputTimestamp = now;
    const isOnlyDigits = /^\d+$/.test(term);

    if (!isOnlyDigits) {
      scannerTyping = false;
      rapidDigitCount = 0;
    } else {
      if (delta < 60) {
        rapidDigitCount += 1;
      } else {
        rapidDigitCount = 1;
      }
      scannerTyping = rapidDigitCount >= 4 && term.length >= 4;
    }

    if (term === '') {
      reloadProductsAfterSearchClear();
      updateHelperText();
      rapidDigitCount = 0;
      scannerTyping = false;
      return;
    }
    if (isOnlyDigits && scannerTyping) {
      clearTimeout(debounceTimer);
      updateHelperText('Introduce el código completo y pulsa Enter para añadirlo.');
      return;
    }
    queueTextSearch(term);
  });

  input.addEventListener('keypress', function (event) {
    const key = event.key || event.which || event.keyCode;
    const isEnterKey = key === 'Enter' || key === 13;
    if (!isEnterKey) {
      return;
    }
    event.preventDefault();
    const term = input.value.trim();
    if (term === '') {
      return;
    }
    const mode = looksLikeBarcode(term) ? 'barcode' : 'text';
    performSearch(term, mode);
  });

  document.addEventListener('dix:family-changed', function () {
    updateHelperText();
    const term = input.value.trim();
    if (term === '') {
      return;
    }
    if (/^\d+$/.test(term) && scannerTyping) {
      updateHelperText('Introduce el código completo y pulsa Enter para añadirlo.');
      return;
    }
    if (looksLikeBarcode(term)) {
      updateHelperText('Introduce el código completo y pulsa Enter para añadirlo.');
      return;
    }
    queueTextSearch(term);
  });

  updateHelperText();
}

function ensureInitialProducts() {
  if (initialProductsLoaded) {
    return;
  }
  const loader = getFamilyLoader();
  if (loader) {
    loader('');
    initialProductsLoaded = true;
  }
}

if (typeof document !== 'undefined') {
  document.addEventListener('DOMContentLoaded', () => {
    initProductSearchWidget();
    ensureInitialProducts();
  });
}

if (typeof $ !== 'undefined' && typeof document !== 'undefined') {
  $(document).on('show.bs.modal', '.modal', function () {
    const visibleModals = $('.modal.show').length;
    const zIndex = 1050 + (10 * visibleModals);
    $(this).css('z-index', zIndex);
    setTimeout(() => {
      $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
    }, 0);
  });

  $(document).on('hidden.bs.modal', '.modal', function () {
    $(this).css('z-index', '');
    const remaining = $('.modal.show').length;
    if (remaining > 0) {
      $('body').addClass('modal-open');
    } else {
      $('.modal-backdrop').removeClass('modal-stack').css('z-index', '');
    }
  });
}
