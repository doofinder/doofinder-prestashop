//implementation of "add to cart" functionality for prestashop 1.7.x

let dfAddToCart = (cartOptions) => {
    /**
     * 1º create the necessary form to add to cart with the required inputs.
     * (the "name" attribute of the inputs is necessary to trigger the event that prestashop uses natively
     * for "add to cart").
     */
    let form = document.createElement("form");
    form.setAttribute("method", "post");
    form.setAttribute("action", cartOptions.cartURL);
    form.setAttribute("style", "display: none;");

    let cuantityInput = document.createElement("input");
    cuantityInput.setAttribute("type", "number");
    cuantityInput.setAttribute("name", "qty");
    cuantityInput.setAttribute("value", cartOptions.cuantity);
    cuantityInput.setAttribute("min", 1);

    let productAttributeInput = document.createElement("input");
    productAttributeInput.setAttribute("type", "hidden");
    productAttributeInput.setAttribute("name", "id_product_attribute");
    productAttributeInput.setAttribute("value", cartOptions.customizationID);

    let productInput = document.createElement("input");
    productInput.setAttribute("type", "hidden");
    productInput.setAttribute("name", "id_product");
    productInput.setAttribute("value", cartOptions.productID);

    let tokenInput = document.createElement("input");
    tokenInput.setAttribute("type", "hidden");
    tokenInput.setAttribute("name", "token");
    tokenInput.setAttribute("value", cartOptions.cartToken);

    let submit = document.createElement("input");
    submit.setAttribute("type", "submit");
    submit.setAttribute("data-button-action", "add-to-cart");

    form.appendChild(cuantityInput);
    form.appendChild(productAttributeInput);
    form.appendChild(productInput);
    form.appendChild(tokenInput);
    form.appendChild(submit);

    /**
     * 2º Add to DOM the form.
     */
    document.body.appendChild(form);

    /**
     * 3º clicks on the submit (does not work if you invoke the submit event) 
     * and removes the form from the DOM
     */
    submit.click();
    form.remove();
}

let doofinderManageCart = (cartOptions) => {
    dfAddToCart(cartOptions);
    closeDoofinderLayer();
}
