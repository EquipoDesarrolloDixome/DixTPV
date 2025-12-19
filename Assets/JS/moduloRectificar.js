/* global bootstrap, automaticTicketPrint */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const state = {
        invoiceId: null,
        invoiceCode: '',
        clientName: '',
        available: [],
        selected: [],
        totalSelected: 0,
        modal: null,
        seriesRect: [],
        selectedSerie: ''
    };

    function getModalInstance() {
        const modalElement = document.getElementById('modalRectificarFactura');
        if (!modalElement) {
            return null;
        }
        if (!state.modal) {
            state.modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        }
        return state.modal;
    }

    function renderLists() {
        const availableContainer = document.getElementById('rectify-available');
        const selectionWrapper = document.getElementById('rectify-selection-wrapper');
        const selectedContainer = document.getElementById('rectify-selected');
        const totalLabel = document.getElementById('rectify-total');
        const modeLabel = document.getElementById('rectify-mode-label');
        const availableWrapper = document.getElementById('rectify-available-wrapper');

        if (availableContainer) {
            availableContainer.innerHTML = '';
            state.available.forEach((line) => {
                if (line.availableQty <= 0) {
                    return;
                }
                const row = document.createElement('div');
                row.className = 'rectify-row';
                row.addEventListener('click', () => addUnit(line.idlinea));
                row.innerHTML =
                    '<div class="flex-grow-1">' +
                    `<strong>${line.descripcion || ''}</strong>` +
                    `<small>${line.referencia || ''}</small>` +
                    '</div>' +
                    `<div class="rectify-qty">${line.availableQty.toFixed(2)}</div>` +
                    `<div class="rectify-price">${formatMoney(line.unitWithTax)}</div>`;
                availableContainer.appendChild(row);
            });
        }

        if (availableWrapper) {
            const hasAvailable = state.available.some((line) => line.availableQty > 0);
            availableWrapper.classList.toggle('d-none', !hasAvailable);
        }

        if (selectedContainer) {
            selectedContainer.innerHTML = '';
            state.selected = state.selected.filter((line) => line.selectedQty > 0);
            state.selected.forEach((line) => {
                const row = document.createElement('div');
                row.className = 'rectify-row is-selected';
                row.addEventListener('click', () => removeUnit(line.idlinea));
                row.innerHTML =
                    '<div class="flex-grow-1">' +
                    `<strong>${line.descripcion || ''}</strong>` +
                    `<small>${line.referencia || ''}</small>` +
                    '</div>' +
                    `<div class="rectify-qty">${line.selectedQty.toFixed(2)}</div>` +
                    `<div class="rectify-price">${formatMoney(line.selectedQty * line.unitWithTax)}</div>`;
                selectedContainer.appendChild(row);
            });
        }

        if (selectionWrapper) {
            selectionWrapper.classList.toggle('d-none', state.selected.length === 0);
        }

        state.totalSelected = state.selected.reduce((carry, line) => {
            return carry + (line.selectedQty * line.unitWithTax);
        }, 0);

        if (totalLabel) {
            totalLabel.textContent = formatMoney(state.totalSelected);
        }

        const mode = isAnnulment();
        if (modeLabel) {
            modeLabel.textContent = mode ? 'Anulativa' : 'Rectificativa';
            modeLabel.classList.toggle('badge-success', mode);
        }
        updateSerieDisplay();
    }

    function addUnit(lineId) {
        const line = state.available.find((item) => item.idlinea === lineId);
        if (!line || line.availableQty <= 0) {
            return;
        }
        line.availableQty -= 1;
        const selectedLine = state.selected.find((item) => item.idlinea === lineId);
        if (selectedLine) {
            selectedLine.selectedQty += 1;
        } else {
            state.selected.push({
                ...line,
                selectedQty: 1
            });
        }
        renderLists();
    }

    function removeUnit(lineId) {
        const selectedLine = state.selected.find((item) => item.idlinea === lineId);
        if (!selectedLine || selectedLine.selectedQty <= 0) {
            return;
        }
        selectedLine.selectedQty -= 1;
        const availableLine = state.available.find((item) => item.idlinea === lineId);
        if (availableLine) {
            availableLine.availableQty += 1;
        }
        renderLists();
    }

    function isAnnulment() {
        return state.available.every((line) => line.availableQty <= 0);
    }

    function formatMoney(value) {
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'EUR'
        }).format(value || 0);
    }

    function updateSerieDisplay() {
        const label = document.getElementById('rectify-serie-label');
        let selected = state.seriesRect.find((opt) => opt.code === state.selectedSerie);
        if (!selected) {
            selected = state.seriesRect.find((opt) => opt.selected);
        }
        if (!selected && state.seriesRect.length) {
            selected = state.seriesRect[0];
        }
        state.selectedSerie = selected ? selected.code : '';
        if (label) {
            label.textContent = selected ? (selected.description || selected.code) : 'Serie rectificativa';
        }
    }

    function resetState(data) {
        state.invoiceId = data.invoice.id;
        state.invoiceCode = data.invoice.code;
        state.clientName = data.invoice.clientName || '';
        state.available = (data.lines || []).map((line) => ({
            ...line,
            availableQty: line.cantidad,
            selectedQty: 0
        }));
        state.selected = [];
        state.totalSelected = 0;
        state.seriesRect = data.series || [];
        state.selectedSerie = '';

        const codeLabel = document.getElementById('rectify-invoice-code');
        if (codeLabel) {
            codeLabel.textContent = data.invoice.code;
        }
        const clientLabel = document.getElementById('rectify-client');
        if (clientLabel) {
            clientLabel.textContent = data.invoice.clientName || '';
        }
        const notes = document.getElementById('rectifyNotes');
        if (notes) {
            notes.value = '';
        }

        updateSerieDisplay();
        renderLists();
    }

    function fetchInvoice(invoiceId) {
        $.ajax({
            method: 'POST',
            url: window.location.href,
            data: {action: 'loadInvoiceLines', invoiceId},
            success: function (response) {
                let payload;
                try {
                    payload = JSON.parse(response);
                } catch (err) {
                    console.error('Respuesta no válida al cargar líneas de factura.', err, response);
                    window.alert('No se pudo leer la respuesta del servidor.');
                    return;
                }
                if (!payload.success) {
                    window.alert(payload.message || 'No se pudo cargar la factura.');
                    return;
                }
                resetState(payload);
                const modal = getModalInstance();
                if (modal) {
                    modal.show();
                }
            },
            error: function (xhr, status, error) {
                console.error('Error al cargar líneas de factura.', status, error);
                window.alert('No se pudo cargar la factura seleccionada.');
            }
        });
    }

    function collectSelectedLines() {
        return state.selected
            .filter((line) => line.selectedQty > 0)
            .map((line) => ({
                idlinea: line.idlinea,
                cantidad: line.selectedQty
            }));
    }

    function finalizeRectification(reload = false) {
        const modal = getModalInstance();
        if (modal) {
            modal.hide();
        }
        if (reload) {
            window.location.reload();
        } else {
            resetState({
                invoice: {id: null, code: '', clientName: ''},
                lines: [],
                series: [],
                annulmentSeries: []
            });
        }
    }

    function createRectificationRequest(options = {}) {
        const silent = !!options.silent;
        const lines = collectSelectedLines();
        if (!lines.length) {
            if (!silent) {
                window.alert('Selecciona los productos a devolver.');
            }
            return Promise.reject(new Error('no-lines'));
        }

        const notes = document.getElementById('rectifyNotes');
        return new Promise((resolve, reject) => {
            $.ajax({
                method: 'POST',
                url: window.location.href,
                data: {
                    action: 'createRectificativa',
                    invoiceId: state.invoiceId,
                    lines: JSON.stringify(lines),
                    serie: state.selectedSerie || '',
                    notes: notes ? notes.value : ''
                },
                success: function (response) {
                    let payload;
                    try {
                        payload = JSON.parse(response);
                    } catch (err) {
                        console.error('Respuesta no válida al crear rectificativa.', err, response);
                        if (!silent) {
                            window.alert('No se pudo leer la respuesta del servidor.');
                        }
                        reject(err);
                        return;
                    }

                    if (!payload.success) {
                        if (!silent) {
                            window.alert(payload.message || 'No se pudo crear la rectificativa.');
                        }
                        reject(new Error(payload.message || 'rectify-error'));
                        return;
                    }

                    resolve(payload);
                },
                error: function (xhr, status, error) {
                    console.error('Error al crear rectificativa.', status, error);
                    if (!silent) {
                        window.alert('No se pudo crear la rectificativa.');
                    }
                    reject(new Error('ajax-error'));
                }
            });
        });
    }

    function confirmRectification() {
        createRectificationRequest().then((payload) => {
            const finish = () => {
                window.alert(payload.message || 'Factura rectificativa creada.');
                finalizeRectification(true);
            };

            if (payload.document && typeof automaticTicketPrint === 'function') {
                Promise.resolve(automaticTicketPrint(payload.document)).finally(finish);
            } else {
                finish();
            }
        }).catch(() => {
            // handled in request
        });
    }

    function selectAll() {
        state.available.forEach((line) => {
            const qty = line.availableQty;
            if (qty <= 0) {
                return;
            }
            line.availableQty = 0;
            const selectedLine = state.selected.find((item) => item.idlinea === line.idlinea);
            if (selectedLine) {
                selectedLine.selectedQty += qty;
            } else {
                state.selected.push({
                    ...line,
                    selectedQty: qty
                });
            }
        });
        renderLists();
    }

    function clearSelection() {
        state.selected.forEach((line) => {
            const availableLine = state.available.find((item) => item.idlinea === line.idlinea);
            if (availableLine) {
                availableLine.availableQty += line.selectedQty;
            }
            line.selectedQty = 0;
        });
        renderLists();
    }

    window.abrirRectificativa = function (invoiceId) {
        if (!invoiceId) {
            window.alert('Factura no válida.');
            return;
        }
        fetchInvoice(invoiceId);
    };

    window.rectifySelectAll = selectAll;
    window.rectifyClearSelection = clearSelection;
    window.confirmarRectificativa = confirmRectification;
    window.dixRectifyCreateRectificativa = createRectificationRequest;
    window.dixRectifyFinalizeRectification = finalizeRectification;
})(window, window.jQuery);
