/**
 * Carga los productos de una familia específica mediante una solicitud AJAX y actualiza el contenido de la vista.
 * @param {string} codfamilia - Código de la familia de productos a cargar.
 */
function loadFamilyProducts(codfamilia) {
    data = {
        action: 'getFamilyProducts',
        codfamilia: codfamilia
    };
    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        datatype: "json",
        success: function (results) {
            data = JSON.parse(results);
            $("#productos").html(data.htmlFamily);
        },
        error: function (msg) {
            console.log("Error en la solicitud AJAX: " + msg.status + " " + msg.responseText);
        }
    });
}

if (typeof window === 'object' && typeof window.loadFamilyProducts !== 'function') {
    window.loadFamilyProducts = loadFamilyProducts;
}
