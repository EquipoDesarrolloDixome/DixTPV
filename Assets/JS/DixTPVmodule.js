
export async function postRequest(data) {
    const response = await fetch('POS', {
        method: 'POST',
        body: data
    });

    if (!response.ok)
        requestErrorHandler(response.status);

    let result = await response.json();
    showMessages(result);

    return result;
}

 