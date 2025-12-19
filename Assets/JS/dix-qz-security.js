(function (window) {
    'use strict';

    const config = window.dixTpvQzConfig || {};
    let certificatePromise = null;

    if (config.configured === false) {
        console.warn('QZ Tray: no se han encontrado los certificados configurados. Se usará el flujo manual.');
    }

    function hasValidConfig() {
        return (typeof config.certificateContent === 'string' && config.certificateContent.length > 0) ||
            (typeof config.certificateUrl === 'string' && config.certificateUrl.length > 0);
    }

    function requestSignature(toSign) {
        return fetch(config.signUrl, {
            method: 'POST',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'text/plain'
            },
            body: 'payload=' + encodeURIComponent(toSign)
        }).then(response => {
            if (!response.ok) {
                throw new Error('La firma del mensaje fue rechazada por el servidor.');
            }
            return response.text();
        }).then(signature => {
            if (!signature.trim()) {
                throw new Error('Firma vacía recibida desde el servidor.');
            }
            return signature;
        });
    }

    function ensureCertificate() {
        if (typeof window.dixTpvCachedCertificate === 'string' && window.dixTpvCachedCertificate.length > 0) {
            return Promise.resolve(window.dixTpvCachedCertificate);
        }

        if (certificatePromise) {
            return certificatePromise;
        }

        if (typeof config.certificateContent === 'string' && config.certificateContent.length > 0) {
            window.dixTpvCachedCertificate = config.certificateContent;
            certificatePromise = Promise.resolve(window.dixTpvCachedCertificate);
            return certificatePromise;
        }

        if (!(typeof config.certificateUrl === 'string' && config.certificateUrl.length > 0)) {
            certificatePromise = Promise.resolve('');
            return certificatePromise;
        }

        certificatePromise = fetch(config.certificateUrl, {
            cache: 'no-store',
            headers: {
                'Accept': 'text/plain'
            }
        }).then(response => {
            if (!response.ok) {
                throw new Error('No se pudo descargar el certificado público de QZ Tray.');
            }
            return response.text();
        }).then(text => {
            if (!text.trim()) {
                throw new Error('El certificado QZ Tray está vacío.');
            }
            window.dixTpvCachedCertificate = text;
            return window.dixTpvCachedCertificate;
        });

        return certificatePromise;
    }

    async function applySecurity() {
        if (window.dixTpvQzSecurityApplied) {
            return true;
        }

        if (!hasValidConfig()) {
            console.warn('QZ Tray: configuración de certificado no disponible.');
            return false;
        }

        if (typeof window.qz === 'undefined' || !window.qz.security) {
            return false;
        }

        const certificate = await ensureCertificate().catch(err => {
            console.error('QZ Tray: error al obtener el certificado.', err);
            return '';
        });

        if (!certificate) {
            return false;
        }

        try {
            window.qz.security.setCertificatePromise(function (resolve) {
                resolve(certificate);
            });

            if (typeof window.qz.security.setSignatureAlgorithm === 'function') {
                window.qz.security.setSignatureAlgorithm('SHA512');
            }

            window.qz.security.setSignaturePromise(function (toSign) {
                return function (resolve, reject) {
                    requestSignature(toSign).then(resolve).catch(reject);
                };
            });

            if (window.qz.audit && typeof window.qz.audit.setSignaturePromise === 'function') {
                window.qz.audit.setSignaturePromise(function () {
                    return function (resolve) {
                        resolve('');
                    };
                });
            }

            window.dixTpvQzSecurityApplied = true;
            return true;
        } catch (err) {
            console.error('QZ Tray: error al aplicar la configuración de seguridad.', err);
            return false;
        }
    }

    function wrapGlobalFunction(name, wrapper) {
        const current = window[name];
        if (typeof current !== 'function' || current._dixTpvWrapped) {
            return false;
        }
        window[name] = wrapper(current);
        window[name]._dixTpvWrapped = true;
        return true;
    }

    function wrapFunctions() {
        const wrappedConfigure = wrapGlobalFunction('configureQZSecurity', original => function () {
            original.apply(this, arguments);
            applySecurity().catch(err => console.error(err));
        });

        const wrappedConnect = wrapGlobalFunction('connectQZTray', original => function () {
            return ensureCertificate()
                .then(() => applySecurity())
                .then(() => original.apply(this, arguments));
        });

        return wrappedConfigure && wrappedConnect;
    }

    wrapFunctions();
    const wrapTimer = window.setInterval(() => {
        if (wrapFunctions()) {
            window.clearInterval(wrapTimer);
        }
    }, 200);

    ensureCertificate().catch(err => console.error(err));

    window.addEventListener('load', function () {
        applySecurity().catch(err => console.error(err));
    });
})(window);
